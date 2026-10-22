<?php

use GlpiPlugin\Salasreuniao\Booking;

Session::checkLoginUser();

global $CFG_GLPI;

$base   = $CFG_GLPI['root_doc'] . '/plugins/salasreuniao/front';
$minhas = $base . '/minhasreservas.php';

if (isset($_POST['agendar'])) {
    $resultado = Booking::agendar($_POST);
    Session::addMessageAfterRedirect(htmlescape($resultado['msg']), false, $resultado['ok'] ? INFO : ERROR);
    if ($resultado['ok']) {
        Html::redirect($minhas);
    }
    Html::back();
} elseif (isset($_POST['cancelar'])) {
    $resultado = Booking::cancelar((int) ($_POST['id'] ?? 0));
    Session::addMessageAfterRedirect(htmlescape($resultado['msg']), false, $resultado['ok'] ? INFO : ERROR);
    Html::redirect($minhas);
}

Html::redirect($base . '/agendar.php');
