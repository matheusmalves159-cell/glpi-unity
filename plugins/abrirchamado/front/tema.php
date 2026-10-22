<?php

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Botão de tema do menu: grava o tema claro/escuro nas preferências do próprio usuário.
 */

Session::checkLoginUser();

/** @var \DBmysql $DB */
global $DB;

$temas = ['claro' => 'auror', 'escuro' => 'auror_dark'];
$tema  = (string) ($_POST['tema'] ?? '');
if (!isset($temas[$tema])) {
    return new JsonResponse(['ok' => false], 400);
}

$DB->update('glpi_users', ['palette' => $temas[$tema]], ['id' => Session::getLoginUserID()]);
$_SESSION['glpipalette'] = $temas[$tema];

return new JsonResponse(['ok' => true, 'tema' => $temas[$tema]]);
