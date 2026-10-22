#!/usr/bin/env python3
# Troca o cabeçalho dos e-mails novos pela imagem de acompanhamento (mesmo layout dos e-mails atuais).
# Uso: python3 modelos-com-imagem.py > saida.sql
BASE = 'https://unitysaude.com.br/_next/static/media/'
LOGO = BASE + 'logo_unity.55931398.svg'

def q(s):
    return "'" + s.replace("\\", "\\\\").replace("'", "''") + "'"

def caixa(rotulo, valor):
    return ('<div style="background-color:#f8f9fa;border-left:3px solid #0b131c;padding:15px;margin:25px 0;font-size:16px;">'
            '<strong>' + rotulo + ':</strong> ' + valor + '</div>')

FICHA = ('<ul><li><strong>Chamado:</strong> ##ticket.id## - ##ticket.title##</li>'
         '<li><strong>Requerente:</strong> ##ticket.authors##</li>'
         '<li><strong>Técnico:</strong> ##ticket.assigntousers##</li>'
         '<li><strong>Abertura:</strong> ##ticket.creationdate##</li></ul>')
ULTIMO = ('##FOREACH LAST 1 followups##<p style="background-color:#f8f9fa;padding:12px;font-size:15px;">'
          '[##followup.date##] ##followup.description##</p>##ENDFOREACHfollowups##')

def html(imagem, alt, corpo):
    return (
        '<table style="border-spacing:0;border-collapse:collapse;width:100%;" border="0" width="100%" cellspacing="0" cellpadding="0"><tbody><tr>'
        '<td style="font-family:Arial,sans-serif;min-width:600px;background-color:#ebeff2;width:100%;" valign="top">'
        '<table style="border-spacing:0;border-collapse:collapse;margin:0 auto;width:62%;" border="0" cellspacing="0" cellpadding="0" align="center"><tbody>'
        '<tr><td style="width:100%;background-color:#0b131c;" valign="top" bgcolor="#0B131C">'
        '<img style="width:100%;max-width:600px;height:auto;display:block;margin:0 auto;" src="' + BASE + imagem + '" alt="' + alt + '"></td></tr>'
        '<tr><td style="background-color:#ffffff;padding:40px;font-family:Arial,sans-serif;color:#333333;line-height:1.6;">' + corpo +
        '<p style="text-align:center;margin:40px 0;"><a style="background-color:#0b131c;color:#ffffff;padding:12px 20px;text-decoration:none;border-radius:5px;font-weight:bold;font-size:16px;" href="##ticket.url##">Clique aqui para acessar o Chamado</a></p>'
        '<p style="text-align:center;margin-top:30px;font-size:14px;color:#555;"><strong>..:: POR FAVOR, NÃO RESPONDA ESSE E-MAIL ::..</strong></p></td></tr>'
        '<tr><td style="background-color:#0b131c;padding:25px;text-align:center;border-bottom-left-radius:10px;border-bottom-right-radius:10px;"><img style="max-width:120px;height:56px;" src="' + LOGO + '" alt="Unity Saúde" width="179"></td></tr>'
        '</tbody></table></td></tr></tbody></table>'
    )

MODELOS = {
    'E-mail - Pendência Colocada - Requerente': ('Pendente.png', 'Chamado em Pendência',
        '<p style="font-size:16px;">Olá, <strong>##ticket.authors##</strong>,</p>'
        '<p style="font-size:16px;">O seu chamado <strong>##ticket.id##</strong> foi colocado em <strong>pendência</strong>.</p>'
        + caixa('Motivo da pendência', '##ticket.pendencia##') + ULTIMO + FICHA +
        '<p>O atendimento continua assim que você responder ou a pendência for resolvida.</p>'),
    'E-mail - Pendência Retirada': ('Atendimento.png', 'Pendência Retirada',
        '<p style="font-size:16px;">Olá,</p>'
        '<p style="font-size:16px;">A pendência do chamado <strong>##ticket.id##</strong> foi retirada e o <strong>atendimento foi retomado</strong>.</p>'
        + ULTIMO + FICHA),
    'E-mail - Solução Reprovada - Técnico': ('Reprovado.png', 'Solução Reprovada',
        '<p style="font-size:16px;">Olá, <strong>##ticket.assigntousers##</strong>,</p>'
        '<p style="font-size:16px;">O requerente <strong>reprovou a solução</strong> do chamado <strong>##ticket.id##</strong>. O chamado voltou para atendimento.</p>'
        + ULTIMO + FICHA),
    'E-mail - Prazo do Chamado (SLA)': ('Prazo.png', 'Atenção ao Prazo do Chamado',
        '<p style="font-size:16px;">Olá,</p>'
        '<p style="font-size:16px;">O prazo (SLA) do chamado <strong>##ticket.id##</strong> está próximo do vencimento ou já venceu.</p>'
        + caixa('Prazo para solução', '##ticket.duedate##') + caixa('Status', '##ticket.status##') + FICHA),
}

print('SET NAMES utf8mb4;')
for nome, (img, alt, corpo) in MODELOS.items():
    print("UPDATE glpi_notificationtemplatetranslations t JOIN glpi_notificationtemplates n ON n.id=t.notificationtemplates_id "
          "SET t.content_html=%s WHERE n.name=%s;" % (q(html(img, alt, corpo)), q(nome)))
