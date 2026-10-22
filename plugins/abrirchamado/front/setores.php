<?php

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Setores da lista de chamados: as telas ativas do Chamado TI (nome e categoria raiz).
 */

Session::checkLoginUser();

/** @var \DBmysql $DB */
global $DB;

$setores = [];
if ($DB->tableExists('glpi_plugin_chamadoti_telas')) {
    foreach ($DB->request([
        'SELECT' => ['nome', 'itilcategories_id'],
        'FROM'   => 'glpi_plugin_chamadoti_telas',
        'WHERE'  => ['is_active' => 1, ['itilcategories_id' => ['>', 0]]],
        'ORDER'  => ['ordem ASC', 'nome ASC'],
    ]) as $t) {
        $setores[] = ['id' => (int) $t['itilcategories_id'], 'nome' => (string) $t['nome']];
    }
}

return new JsonResponse($setores);
