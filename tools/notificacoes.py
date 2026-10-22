#!/usr/bin/env python3
# Gera o SQL das notificações de chamado (homologação). Uso: python3 notificacoes.py > saida.sql
import sys

def q(s):
    return "'" + s.replace("\\", "\\\\").replace("'", "''") + "'"

# Destinatários (Notification::*)
ASSIGN_TECH, AUTHOR, OBSERVER, SUPERVISOR_ASSIGN_GROUP = 2, 3, 21, 10

LOGO = 'https://unitysaude.com.br/_next/static/media/logo_unity.55931398.svg'


def html(titulo, corpo):
    """Mesmo visual dos e-mails atuais (faixa escura, cartão branco, rodapé com logo)."""
    return (
        '<table style="border-spacing:0;border-collapse:collapse;width:100%;" border="0" width="100%" cellspacing="0" cellpadding="0"><tbody><tr>'
        '<td style="font-family:Arial,sans-serif;min-width:600px;background-color:#ebeff2;width:100%;" valign="top">'
        '<table style="border-spacing:0;border-collapse:collapse;margin:0 auto;width:62%;" border="0" cellspacing="0" cellpadding="0" align="center"><tbody>'
        '<tr><td style="background-color:#0b131c;padding:28px 40px;font-family:Arial,sans-serif;color:#ffffff;font-size:22px;font-weight:bold;">' + titulo + '</td></tr>'
        '<tr><td style="background-color:#ffffff;padding:40px;font-family:Arial,sans-serif;color:#333333;line-height:1.6;">' + corpo +
        '<p style="text-align:center;margin:40px 0;"><a style="background-color:#0b131c;color:#ffffff;padding:12px 20px;text-decoration:none;border-radius:5px;font-weight:bold;font-size:16px;" href="##ticket.url##">Clique aqui para acessar o Chamado</a></p>'
        '<p style="text-align:center;margin-top:30px;font-size:14px;color:#555;"><strong>..:: POR FAVOR, NÃO RESPONDA ESSE E-MAIL ::..</strong></p></td></tr>'
        '<tr><td style="background-color:#0b131c;padding:25px;text-align:center;border-bottom-left-radius:10px;border-bottom-right-radius:10px;"><img style="max-width:120px;height:56px;" src="' + LOGO + '" alt="Unity Saúde" width="179"></td></tr>'
        '</tbody></table></td></tr></tbody></table>'
    )

def caixa(rotulo, valor):
    return ('<div style="background-color:#f8f9fa;border-left:3px solid #0b131c;padding:15px;margin:25px 0;font-size:16px;">'
            '<strong>' + rotulo + ':</strong> ' + valor + '</div>')

def ficha():
    return ('<ul><li><strong>Chamado:</strong> ##ticket.id## - ##ticket.title##</li>'
            '<li><strong>Requerente:</strong> ##ticket.authors##</li>'
            '<li><strong>Técnico:</strong> ##ticket.assigntousers##</li>'
            '<li><strong>Abertura:</strong> ##ticket.creationdate##</li></ul>')

ULTIMO = ('##FOREACH LAST 1 followups##<p style="background-color:#f8f9fa;padding:12px;font-size:15px;">'
          '[##followup.date##] ##followup.description##</p>##ENDFOREACHfollowups##')

# ---- e-mails novos: (chave, nome, assunto, titulo, corpo html, texto puro)
EMAILS = {
    'pend_add': ('Pendência Colocada - Requerente', 'Chamado em Pendência', 'Chamado em Pendência',
        '<p style="font-size:16px;">Olá, <strong>##ticket.authors##</strong>,</p>'
        '<p style="font-size:16px;">O seu chamado <strong>##ticket.id##</strong> foi colocado em <strong>pendência</strong>.</p>'
        + caixa('Motivo da pendência', '##ticket.reminder.name##') + ULTIMO + ficha() +
        '<p>O atendimento continua assim que você responder ou a pendência for resolvida.</p>',
        'Olá, ##ticket.authors##. O seu chamado ##ticket.id## foi colocado em pendência. Motivo: ##ticket.reminder.name##. Responda o chamado para o atendimento continuar. ##ticket.url##'),
    'pend_del': ('Pendência Retirada', 'Pendência Retirada do Chamado', 'Pendência Retirada',
        '<p style="font-size:16px;">Olá,</p>'
        '<p style="font-size:16px;">A pendência do chamado <strong>##ticket.id##</strong> foi retirada e o atendimento foi retomado.</p>'
        + caixa('Status atual', '##ticket.status##') + ULTIMO + ficha(),
        'A pendência do chamado ##ticket.id## (##ticket.title##) foi retirada. Status atual: ##ticket.status##. ##ticket.url##'),
    'reject': ('Solução Reprovada - Técnico', 'Solução Reprovada pelo Requerente', 'Solução Reprovada',
        '<p style="font-size:16px;">Olá, <strong>##ticket.assigntousers##</strong>,</p>'
        '<p style="font-size:16px;">O requerente <strong>reprovou a solução</strong> do chamado <strong>##ticket.id##</strong>. O chamado voltou para atendimento.</p>'
        + ULTIMO + ficha(),
        'O requerente reprovou a solução do chamado ##ticket.id## (##ticket.title##). O chamado voltou para atendimento. ##ticket.url##'),
    'recall': ('Prazo do Chamado (SLA)', 'Atenção ao Prazo do Chamado', 'Atenção ao Prazo do Chamado',
        '<p style="font-size:16px;">Olá,</p>'
        '<p style="font-size:16px;">O prazo (SLA) do chamado <strong>##ticket.id##</strong> está próximo do vencimento ou já venceu.</p>'
        + caixa('Prazo para solução', '##ticket.duedate##') + caixa('Status', '##ticket.status##') + ficha(),
        'Atenção ao prazo (SLA) do chamado ##ticket.id## (##ticket.title##). Prazo para solução: ##ticket.duedate##. Status: ##ticket.status##. ##ticket.url##'),
}

# ---- avisos laterais (navegador): (chave, assunto, texto)
AVISOS = {
    'novo_grupo':   ('Novo Chamado ##ticket.id##', '##ticket.title## - ##ticket.authors##'),
    'atrib_tecnico': ('Você foi atribuído ao chamado ##ticket.id##', '##ticket.title##'),
    'atrib_req':    ('Chamado ##ticket.id## em atendimento', '##ticket.assigntousers## assumiu o seu chamado.'),
    'resp_req':     ('Atualização no chamado ##ticket.id##', 'Há uma nova resposta no seu chamado: ##ticket.title##'),
    'resp_tec':     ('Nova resposta no chamado ##ticket.id##', '##ticket.title##'),
    'pend_add':     ('Chamado ##ticket.id## em pendência', 'Motivo: ##ticket.reminder.name##'),
    'pend_del':     ('Pendência retirada - chamado ##ticket.id##', 'O atendimento foi retomado: ##ticket.title##'),
    'solucionado':  ('Chamado ##ticket.id## solucionado', 'Acesse para aprovar ou reprovar a solução.'),
    'encerrado':    ('Chamado ##ticket.id## encerrado', '##ticket.title##'),
    'reject':       ('Solução reprovada - chamado ##ticket.id##', 'O requerente reprovou a solução: ##ticket.title##'),
    'recall':       ('Prazo do chamado ##ticket.id##', 'Prazo: ##ticket.duedate## - ##ticket.title##'),
}

out = []
w = out.append
w('SET NAMES utf8mb4;')
w('START TRANSACTION;')

# 1) limpa notificações de teste sem destinatário nem modelo (141, 143, 144, 145)
w("DELETE FROM glpi_notifications WHERE id IN (141,143,144,145) AND NOT EXISTS (SELECT 1 FROM glpi_notificationtargets t WHERE t.notifications_id=glpi_notifications.id);")

# 2) variáveis de usuário SQL guardam os ids criados
for k, (nome, assunto, titulo, corpo, texto) in EMAILS.items():
    w("INSERT INTO glpi_notificationtemplates (name,itemtype,date_mod) VALUES (%s,'Ticket',NOW());" % q('E-mail - ' + nome))
    w("SET @em_%s = LAST_INSERT_ID();" % k)
    w("INSERT INTO glpi_notificationtemplatetranslations (notificationtemplates_id,language,subject,content_text,content_html) VALUES (@em_%s,'pt_BR',%s,%s,%s);"
      % (k, q(assunto), q(texto), q(html(titulo, corpo))))

for k, (assunto, texto) in AVISOS.items():
    w("INSERT INTO glpi_notificationtemplates (name,itemtype,date_mod) VALUES (%s,'Ticket',NOW());" % q('Aviso Lateral - ' + k))
    w("SET @av_%s = LAST_INSERT_ID();" % k)
    w("INSERT INTO glpi_notificationtemplatetranslations (notificationtemplates_id,language,subject,content_text,content_html) VALUES (@av_%s,'pt_BR',%s,%s,NULL);"
      % (k, q(assunto), q(texto)))

# 3) avisos laterais nas notificações que já existem
existentes = {106: 'novo_grupo', 105: 'atrib_tecnico', 103: 'atrib_req', 100: 'resp_req', 140: 'resp_tec',
              102: 'solucionado', 101: 'encerrado'}
for nid, k in existentes.items():
    w("INSERT INTO glpi_notifications_notificationtemplates (notifications_id,mode,notificationtemplates_id) "
      "SELECT %d,'ajax',@av_%s WHERE EXISTS (SELECT 1 FROM glpi_notifications WHERE id=%d) "
      "AND NOT EXISTS (SELECT 1 FROM glpi_notifications_notificationtemplates WHERE notifications_id=%d AND mode='ajax');" % (nid, k, nid, nid))

# 4) notificações novas: (chave, nome, evento, destinatários)
NOVAS = [
    ('pend_add', 'Pendência Colocada - Notificar Requerente', 'pendingreason_add', [AUTHOR]),
    ('pend_del', 'Pendência Retirada - Notificar Requerente e Técnico', 'pendingreason_del', [AUTHOR, ASSIGN_TECH]),
    ('reject',   'Solução Reprovada - Notificar Técnico', 'rejectsolution', [ASSIGN_TECH]),
    ('recall',   'Prazo do Chamado (SLA) - Notificar Técnico e Chefia', 'recall', [ASSIGN_TECH, SUPERVISOR_ASSIGN_GROUP]),
]
for k, nome, evento, alvos in NOVAS:
    w("INSERT INTO glpi_notifications (name,entities_id,itemtype,event,is_recursive,is_active,allow_response,date_mod,date_creation) "
      "VALUES (%s,0,'Ticket',%s,1,1,0,NOW(),NOW());" % (q(nome), q(evento)))
    w("SET @n_%s = LAST_INSERT_ID();" % k)
    for a in alvos:
        w("INSERT INTO glpi_notificationtargets (items_id,type,notifications_id) VALUES (%d,1,@n_%s);" % (a, k))
    w("INSERT INTO glpi_notifications_notificationtemplates (notifications_id,mode,notificationtemplates_id) VALUES (@n_%s,'mailing',@em_%s);" % (k, k))
    w("INSERT INTO glpi_notifications_notificationtemplates (notifications_id,mode,notificationtemplates_id) VALUES (@n_%s,'ajax',@av_%s);" % (k, k))

# 5) texto do e-mail de encerramento: deixa claro que é a aprovação do requerente
w("UPDATE glpi_notificationtemplatetranslations SET "
  "content_html=REPLACE(content_html,'foi fechado.','foi encerrado. A solução foi aprovada pelo requerente (ou o chamado foi encerrado automaticamente após o prazo de aprovação).'), "
  "content_text=REPLACE(content_text,'foi fechado.','foi encerrado. A solução foi aprovada pelo requerente (ou o chamado foi encerrado automaticamente após o prazo de aprovação).') "
  "WHERE notificationtemplates_id=46;")

# 6) alertas de prazo (SLA): aviso antes de vencer e aviso ao vencer.
#    TTR (solução) e TTO (primeiro atendimento); tempos em segundos
ANTES = {1: 3600, 2: 7200, 3: 14400, 11: 14400, 5: 28800,           # TTR
         6: 600, 7: 900, 8: 1800, 9: 3600, 10: 7200}                 # TTO
for sla, seg in ANTES.items():
    for nome, tempo in (('Alerta - Prazo Próximo do Vencimento', -seg), ('Alerta - Prazo Vencido', 0)):
        w("INSERT INTO glpi_slalevels (name,slas_id,execution_time,is_active,entities_id,is_recursive,`match`,uuid) "
          "SELECT %s,%d,%d,1,0,1,'AND',UUID() WHERE NOT EXISTS (SELECT 1 FROM glpi_slalevels WHERE slas_id=%d AND execution_time=%d);" % (q(nome), sla, tempo, sla, tempo))
        w("INSERT INTO glpi_slalevelactions (slalevels_id,action_type,field,value) "
          "SELECT l.id,'send','recall','1' FROM glpi_slalevels l WHERE l.slas_id=%d AND l.execution_time=%d "
          "AND NOT EXISTS (SELECT 1 FROM glpi_slalevelactions a WHERE a.slalevels_id=l.id);" % (sla, tempo))

w('COMMIT;')
print('\n'.join(out))
