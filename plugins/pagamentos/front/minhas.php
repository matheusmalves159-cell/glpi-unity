<?php

use Glpi\Application\View\TemplateRenderer;
use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Pagamentos\Pedido;

Session::checkLoginUser();
if (!Pedido::podeSolicitar()) {
    throw new AccessDeniedHttpException();
}

/** @var \DBmysql $DB */
global $DB;

$pedidos = [];
foreach ($DB->request([
    'FROM'  => Pedido::getTable(),
    'WHERE' => ['users_id' => (int) Session::getLoginUserID()],
    'ORDER' => ['date_creation DESC'],
    'LIMIT' => 100,
]) as $p) {
    $ticket = new Ticket();
    $tem    = $ticket->getFromDB((int) $p['tickets_id']);
    $status = $tem ? (int) $ticket->fields['status'] : 0;
    // Aprovação do Financeiro (sem pedido de aprovação = solicitações antigas)
    $aprovacao = $tem ? (int) $ticket->fields['global_validation'] : CommonITILValidation::NONE;
    $pedidos[] = [
        'data'       => date('d/m/Y H:i', strtotime((string) $p['date_creation'])),
        'fornecedor' => (string) $p['fornecedor'],
        'valor'      => (float) $p['valor'],
        'servico'    => (string) $p['servico'],
        'setor'      => (string) $p['setor'],
        'forma'      => (string) $p['forma'],
        'chamado'    => (int) $p['tickets_id'],
        'situacao'   => $tem ? Ticket::getStatus($status) : 'Chamado removido',
        'concluido'  => $tem && in_array($status, array_merge(Ticket::getSolvedStatusArray(), Ticket::getClosedStatusArray()), true),
        'aprovacao'  => match ($aprovacao) {
            CommonITILValidation::WAITING  => ['Aguardando Financeiro', 'bg-yellow-lt'],
            CommonITILValidation::ACCEPTED => ['Aprovada', 'bg-green-lt'],
            CommonITILValidation::REFUSED  => ['Recusada', 'bg-red-lt'],
            default                        => null,
        },
    ];
}

Pedido::cabecalho('Minhas solicitações de pagamento');
TemplateRenderer::getInstance()->display('@pagamentos/minhas.html.twig', ['pedidos' => $pedidos]);
Pedido::rodape();
