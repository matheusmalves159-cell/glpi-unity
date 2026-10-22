#!/usr/bin/env python3
# Lista os textos do código do GLPI (__(), _n(), _x()) que não existem (ou estão vazios) no pt_BR.po.
# Uso: faltam_traducoes.py <pasta_do_codigo> <pt_BR.po> [saida.tsv]
import os, re, sys, json

raiz, po_arq = sys.argv[1], sys.argv[2]
saida = sys.argv[3] if len(sys.argv) > 3 else None

def desescapa_po(s):
    return s.replace('\\"', '"').replace('\\n', '\n').replace('\\t', '\t').replace('\\\\', '\\')

# --- catálogo
po = {}
bloco = {}
def fecha():
    if 'id' in bloco:
        ctx = bloco.get('ctx', '')
        po[(ctx, bloco['id'])] = (bloco.get('str', ''), bloco.get('pl', None), bloco.get('strs', []))
    bloco.clear()
campo = None
for linha in open(po_arq, encoding='utf-8'):
    linha = linha.rstrip('\n')
    if linha.startswith('#') or linha == '':
        if linha == '' :
            fecha(); campo = None
        continue
    m = re.match(r'^(msgctxt|msgid_plural|msgid|msgstr(?:\[(\d+)\])?)\s+"(.*)"$', linha)
    if m:
        k = m.group(1); v = desescapa_po(m.group(3))
        if k == 'msgctxt': bloco['ctx'] = v; campo = 'ctx'
        elif k == 'msgid': bloco['id'] = v; campo = 'id'
        elif k == 'msgid_plural': bloco['pl'] = v; campo = 'pl'
        elif k.startswith('msgstr['):
            bloco.setdefault('strs', []).append(v); campo = ('strs', int(m.group(2)))
        else: bloco['str'] = v; campo = 'str'
        continue
    m = re.match(r'^"(.*)"$', linha)
    if m and campo:
        v = desescapa_po(m.group(1))
        if isinstance(campo, tuple): bloco['strs'][campo[1]] += v
        else: bloco[campo] = bloco.get(campo, '') + v
fecha()

def traduzido(ctx, msgid):
    e = po.get((ctx, msgid))
    if e is None: return False
    s, pl, strs = e
    return (s.strip() != '') or any(x.strip() for x in strs)

# --- código
lit = r'''(?P<q>['"])(?P<t>(?:\\.|(?!(?P=q)).)*)(?P=q)'''
def L(n): return lit.replace('(?P<q>', '(?P<q%s>' % n).replace('(?P<t>', '(?P<t%s>' % n).replace('(?P=q)', '(?P=q%s)' % n)
re_simples = re.compile(r'\b_{1,2}s?\(\s*' + L(1) + r'\s*[,)]')
re_x = re.compile(r'\b_s?x\(\s*' + L(1) + r'\s*,\s*' + L(2) + r'\s*[,)]')
re_n = re.compile(r'\b_s?n\(\s*' + L(1) + r'\s*,\s*' + L(2))
re_tw_simples = re.compile(r'\b_{1,2}\(\s*' + L(1) + r'\s*[,)]')

def un(t, q):
    t = t.replace('\\' + q, q).replace('\\\\', '\\')
    return t.replace('\\n', '\n') if q == '"' else t

faltam = {}
for pasta, _, arqs in os.walk(raiz):
    if any(x in pasta for x in ('/vendor', '/node_modules', '/tests', '/locales', '/plugins', '/marketplace', '/public/lib', '/files')):
        continue
    for a in arqs:
        if not (a.endswith('.php') or a.endswith('.twig') or a.endswith('.js')): continue
        if a.endswith('.min.js'): continue
        caminho = os.path.join(pasta, a)
        try: s = open(caminho, encoding='utf-8', errors='ignore').read()
        except: continue
        achou = []
        for m in re_x.finditer(s): achou.append((un(m.group('t1'), m.group('q1')), un(m.group('t2'), m.group('q2'))))
        for m in re_n.finditer(s): achou.append(('', un(m.group('t1'), m.group('q1'))))
        for m in re_simples.finditer(s):
            t = un(m.group('t1'), m.group('q1'))
            achou.append(('', t))
        for ctx, t in achou:
            if t.strip() == '' or not re.search(r'[A-Za-z]{3}', t): continue
            if '%s' in t and len(t) < 4: continue
            if not traduzido(ctx, t):
                faltam.setdefault((ctx, t), caminho.replace(raiz, ''))

itens = sorted(faltam.items(), key=lambda kv: kv[0][1].lower())
print('textos no catálogo:', len(po), '| faltam traduzir:', len(itens))
if saida:
    with open(saida, 'w', encoding='utf-8') as f:
        for (ctx, t), arq in itens:
            f.write(json.dumps({'ctx': ctx, 'id': t, 'arq': arq}, ensure_ascii=False) + '\n')
for (ctx, t), arq in itens[:15]:
    print(' -', (ctx + ' | ' if ctx else '') + t[:90], '|', arq)
