#!/usr/bin/env python3
# Gera o SQL que traduz nomes de cadastros do GLPI que vieram em inglês.
# Uso: nomes_pt.py <regras.tsv: id<TAB>nome> > saida.sql
import re, sys

q = lambda s: "'" + s.replace("\\", "\\\\").replace("'", "''") + "'"
out = ['SET NAMES utf8mb4;']

def upd(tabela, id_, novo):
    out.append("UPDATE %s SET name=%s WHERE id=%d;" % (tabela, q(novo), id_))

ENT = {'Computer': 'Computador', 'Printer': 'Impressora', 'NetworkEquipment': 'Equipamento de rede',
       'Device': 'Dispositivo', 'Monitor': 'Monitor', 'Phone': 'Telefone', 'Cluster': 'Cluster',
       'Enclosure': 'Gabinete', 'Global': 'Global', 'Database': 'Banco de dados',
       'Unmanaged': 'Dispositivo não gerenciado'}
ACOES = {'update': 'atualizar', 'import': 'importar', 'import denied': 'importação negada', 'constraint': 'restrição'}

def detalhe(d):
    d = d.replace('by ', 'por ').replace('is empty in GLPI', 'está vazio no GLPI')
    d = re.sub(r'\bmac\b', 'MAC', d)
    d = re.sub(r'\bname\b', 'nome', d)
    return d

def regra_import(nome):
    m = re.match(r'^(Computer|Printer|NetworkEquipment|Device|Monitor|Phone|Cluster|Enclosure|Global|Database|Unmanaged) '
                 r'(update|import denied|import|constraint)\s*(\(.*\))?$', nome)
    if not m:
        return None
    ent, acao, det = m.groups()
    txt = ENT[ent] + ': ' + ACOES[acao]
    if det:
        txt += ' ' + detalhe(det)
    return txt

ESPECIAIS = {
    1: 'Sem criação em importação parcial',
    8: 'Atualizar apenas o endereço MAC (MAC na porta do switch)',
    9: 'Importar apenas o endereço MAC (MAC na porta do switch)',
    80: 'Atribuição de usuário do domínio', 81: 'Vários usuários: atribuir ao primeiro', 82: 'Atribuição de um usuário',
    83: 'Limpar nome do SO Linux', 84: 'Limpar nome do SO Windows', 85: 'Limpar nome do SO Windows Server',
    89: 'Limpar edição do SO Linux', 90: 'Limpar edição do SO Windows', 91: 'Limpar edição do SO Windows Server',
    86: 'Limpar versão do SO Linux', 87: 'Limpar versão do SO Windows', 88: 'Limpar versão do SO Windows Server',
    72: 'Notificações do GLPI', 74: 'Resposta automática (Auto-Submitted)', 75: 'Raiz', 76: 'Raiz',
    77: 'Importar categoria da ferramenta de inventário',
}

for linha in open(sys.argv[1], encoding='utf-8'):
    id_, nome = linha.rstrip('\n').split('\t', 1)
    id_ = int(id_)
    novo = ESPECIAIS.get(id_)
    if novo is None:
        m = re.match(r'^Global (update|import) \(by (mac\+ifnumber|ip\+ifdescr)( not restricted port| restricted port)?\)$', nome)
        if m:
            acao = 'atualizar' if m.group(1) == 'update' else 'importar'
            porta = m.group(3) or ''
            extra = ', porta não restrita' if 'not' in porta else (', porta restrita' if porta else '')
            novo = 'Global: %s (por %s%s)' % (acao, m.group(2), extra)
        else:
            novo = regra_import(nome)
    if novo and novo != nome:
        upd('glpi_rules', id_, novo)

for tabela, id_, nome in [
    ('glpi_calendars', 1, 'Padrão'), ('glpi_changetemplates', 1, 'Padrão'), ('glpi_problemtemplates', 1, 'Padrão'),
    ('glpi_tickettemplates', 8, 'Técnicos'),
    ('glpi_requesttypes', 1, 'Portal de Chamados'), ('glpi_requesttypes', 2, 'E-mail'),
    ('glpi_dashboards_dashboards', 4, 'Mini painel de chamados'),
    ('glpi_snmpcredentials', 1, 'Comunidade pública v1'), ('glpi_snmpcredentials', 2, 'Comunidade pública v2c'),
    ('glpi_apiclients', 1, 'Acesso total a partir do localhost'),
    ('glpi_networkporttypes', 1, 'outro'), ('glpi_softwarecategories', 26, 'E-mail'),
]:
    upd(tabela, id_, nome)

print('\n'.join(out))
