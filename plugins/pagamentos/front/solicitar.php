<?php

use Glpi\Application\View\TemplateRenderer;
use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Pagamentos\Pedido;
use GlpiPlugin\Pagamentos\Settings;

Session::checkLoginUser();
if (!Pedido::podeSolicitar()) {
    throw new AccessDeniedHttpException();
}

// Se o envio deu erro, o formulário volta preenchido
$antigo = $_SESSION['plugin_pagamentos_antigo'] ?? [];
unset($_SESSION['plugin_pagamentos_antigo']);

Pedido::cabecalho('Solicitação de pagamento');
TemplateRenderer::getInstance()->display('@pagamentos/solicitar.html.twig', [
    'antigo'     => $antigo,
    'aviso'      => Settings::avisoHtml(),
    'locais'     => Settings::lista('locais'),
    'setores'    => Settings::lista('setores'),
    'formas'     => Pedido::FORMAS,
    'extensoes'  => '.' . implode(',.', Pedido::EXTENSOES),
    'max_anexos' => Pedido::MAX_ANEXOS,
    'max_mb'     => Pedido::MAX_MB,
]);
Pedido::rodape();
