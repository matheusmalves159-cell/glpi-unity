<?php

/**
 * Traduções locais do GLPI (não são apagadas em atualizações).
 *
 * - O status "Em atendimento (planejado)" não é usado pela equipe e foi
 *   reaproveitado como "Em andamento" para os chamados de reserva de sala
 *   (plugin Salas de Reunião).
 * - O "Catálogo de serviços" aparece como "Abrir Chamados" (menu, título e caminho).
 * - Textos que o GLPI 11.0.9 ainda não traz traduzidos para pt_BR.
 */

return [
    "status\004Processing (planned)" => 'Em andamento',
    'Service catalog'                  => 'Abrir Chamados',

    // O catálogo oficial deixou estes em inglês
    'Home'                                                 => 'Início',
    'Dashboard'                                            => 'Painel',
    'Helpdesk'                                             => 'Atendimento',
    "Icon\004Helpdesk"                                     => 'Atendimento',
    'Download'                                             => 'Baixar',
    'On-premise'                                           => 'Local',

    'Add a contract'                                       => 'Adicionar um contrato',
    'Applies to Tickets, Changes, and Problems'            => 'Vale para chamados, mudanças e problemas',
    'Are you sure? Any users using the old link will no longer be able to access the form.'
        => 'Tem certeza? Quem estiver usando o link antigo não conseguirá mais acessar o formulário.',
    'Authentication code'                                  => 'Código de autenticação',
    'Besides, if you appreciate GLPI and its community, please take a minute to reference your organization by filling %1$s'
        => 'Além disso, se você gosta do GLPI e da comunidade, reserve um minuto para registrar a sua organização preenchendo %1$s',
    'Cannot update item: not enough right on the parent(s) item(s)'
        => 'Não foi possível atualizar o item: permissão insuficiente nos itens superiores',
    'Check if the database schema version matches the installed GLPI version.'
        => 'Verifica se a versão do esquema do banco de dados corresponde à versão instalada do GLPI.',
    'Copy UUID'                                            => 'Copiar UUID',
    'Create a ticket from this change'                     => 'Criar um chamado a partir desta mudança',
    'Custom Range'                                         => 'Período personalizado',
    'Email HTML body preview'                              => 'Pré-visualização do corpo do e-mail em HTML',
    'Error during mails collect (%s). Check in "%s" for more details'
        => 'Erro ao coletar e-mails (%s). Veja mais detalhes em "%s"',
    'Error during message import (%s). Check in "%s" for more details'
        => 'Erro ao importar a mensagem (%s). Veja mais detalhes em "%s"',
    'Failed to send webhook'                               => 'Falha ao enviar o webhook',
    'Filled automatically'                                 => 'Preenchido automaticamente',
    'For anonymous forms, the ALTCHA anti-bot widget will follow this same visibility.'
        => 'Em formulários anônimos, o widget anti-robô ALTCHA seguirá esta mesma visibilidade.',
    'For security reasons, your password has expired. Please contact your administrator to reset it.'
        => 'Por segurança, sua senha expirou. Entre em contato com o administrador para redefini-la.',
    'Form "%s" has a UUID ("%s") already used by another form in the Formcreator data. A new UUID has been generated to avoid merging them.'
        => 'O formulário "%s" tem um UUID ("%s") já usado por outro formulário nos dados do Formcreator. Foi gerado um novo UUID para evitar que sejam mesclados.',
    'Full form (all questions and answers)'                => 'Formulário completo (todas as perguntas e respostas)',
    'Generation of changes satisfaction surveys'           => 'Geração de pesquisas de satisfação das mudanças',
    'Generation of tickets satisfaction surveys'           => 'Geração de pesquisas de satisfação dos chamados',
    'Include members of sub-groups'                        => 'Incluir membros dos subgrupos',
    'Insert a rack here'                                   => 'Inserir um rack aqui',
    'Invalid or missing authentication code.'              => 'Código de autenticação inválido ou ausente.',
    'Item not added: a duplicate already exists (%s)'      => 'Item não adicionado: já existe um duplicado (%s)',
    'Logs'                                                 => 'Registros',
    'Mandatory fields are not filled.'                     => 'Os campos obrigatórios não foram preenchidos.',
    'Notifications to myself'                              => 'Notificações das minhas próprias ações',
    'Pending reason auto resolve'                          => 'Resolução automática por motivo de pendência',
    'Permissions for security keys files'                  => 'Permissões dos arquivos de chaves de segurança',
    'Pre-select me as a requester for new ITIL items'      => 'Selecionar-me previamente como requerente em novos chamados, mudanças e problemas',
    'Pre-select me as a technician for new ITIL items'     => 'Selecionar-me previamente como técnico em novos chamados, mudanças e problemas',
    'Resources'                                            => 'Recursos',
    'Results copied to clipboard'                          => 'Resultados copiados para a área de transferência',
    'Retried to send webhook'                              => 'Nova tentativa de envio do webhook',
    'Security key files are accessibles.'                  => 'Os arquivos de chaves de segurança estão acessíveis.',
    'Select a folder'                                      => 'Selecione uma pasta',
    'The database is not up to date.'                      => 'O banco de dados não está atualizado.',
    'The database is up to date.'                          => 'O banco de dados está atualizado.',
    'The number of uploaded files exceeds the maximum allowed by the "%1$s" PHP configuration directive (current value: %2$s). Part of the uploaded files has been dropped, therefore the request cannot be processed. Please upload fewer files at once, or ask your administrator to increase this limit.'
        => 'O número de arquivos enviados excede o máximo permitido pela diretiva de configuração do PHP "%1$s" (valor atual: %2$s). Parte dos arquivos foi descartada, então a solicitação não pode ser processada. Envie menos arquivos por vez ou peça ao administrador para aumentar esse limite.',
    'The security key file %s could not be created.'       => 'Não foi possível criar o arquivo de chave de segurança %s.',
    'The security key file %s is not readable.'            => 'O arquivo de chave de segurança %s não pode ser lido.',
    'The submitted data exceeds the maximum size allowed by the "%1$s" PHP configuration directive (current value: %2$s). The submitted data has been dropped, therefore the request cannot be processed. Please submit less data at once, or ask your administrator to increase this limit.'
        => 'Os dados enviados excedem o tamanho máximo permitido pela diretiva de configuração do PHP "%1$s" (valor atual: %2$s). Os dados enviados foram descartados, então a solicitação não pode ser processada. Envie menos dados por vez ou peça ao administrador para aumentar esse limite.',
    'The submitted form contains more fields than allowed by the "%1$s" PHP configuration directive (current value: %2$s). Part of the submitted data has been dropped, therefore the request cannot be processed. Please reduce the number of submitted items, or ask your administrator to increase this limit.'
        => 'O formulário enviado contém mais campos do que o permitido pela diretiva de configuração do PHP "%1$s" (valor atual: %2$s). Parte dos dados enviados foi descartada, então a solicitação não pode ser processada. Reduza o número de itens enviados ou peça ao administrador para aumentar esse limite.',
    'The system name is a reserved name. Please enter a different label or manually change the system name.'
        => 'O nome de sistema é um nome reservado. Informe outro rótulo ou altere o nome de sistema manualmente.',
    'The system name is already in use. Please enter a different label or manually change the system name.'
        => 'O nome de sistema já está em uso. Informe outro rótulo ou altere o nome de sistema manualmente.',
    'The system name is invalid. It must start with a letter and contain only alphanumeric chars.'
        => 'O nome de sistema é inválido. Ele deve começar com uma letra e conter apenas caracteres alfanuméricos.',
    'This software version is already installed on this item.' => 'Esta versão do software já está instalada neste item.',
    'Ticket %d and the selected problem do not belong to compatible entities.'
        => 'O chamado %d e o problema selecionado não pertencem a entidades compatíveis.',
    'Too many failed MFA attempts. Please try again later.' => 'Muitas tentativas de MFA malsucedidas. Tente novamente mais tarde.',
    'Unable to report rejected email (%s)'                 => 'Não foi possível informar o e-mail rejeitado (%s)',
    'Unable to send refused response for message from collector "%s" (%s) (%s)'
        => 'Não foi possível enviar a resposta de recusa da mensagem do coletor "%s" (%s) (%s)',
    'You can use this menu to upload one or several inventory files. Files must have a known extension (%1$s).'
        => 'Você pode usar este menu para enviar um ou vários arquivos de inventário. Os arquivos devem ter uma extensão conhecida (%1$s).',
];
