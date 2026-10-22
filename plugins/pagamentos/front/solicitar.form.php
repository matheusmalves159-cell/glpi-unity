<?php

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Pagamentos\Pedido;

Session::checkLoginUser();
if (!Pedido::podeSolicitar()) {
    throw new AccessDeniedHttpException();
}

global $CFG_GLPI;
$base = $CFG_GLPI['root_doc'] . '/plugins/pagamentos/front';

if (isset($_POST['enviar'])) {
    $resultado = Pedido::criar($_POST, $_FILES['anexos'] ?? []);
    Session::addMessageAfterRedirect(htmlescape($resultado['msg']), false, $resultado['ok'] ? INFO : ERROR);
    if ($resultado['ok']) {
        Html::redirect($base . '/minhas.php');
    }
    // Volta com o que foi digitado (anexos precisam ser escolhidos de novo)
    $_SESSION['plugin_pagamentos_antigo'] = array_intersect_key($_POST, array_flip([
        'fornecedor', 'documento', 'valor', 'forma', 'pix', 'servico', 'setor', 'local', 'observacoes',
    ]));
}

Html::redirect($base . '/solicitar.php');
