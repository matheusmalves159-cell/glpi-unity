<?php

use GlpiPlugin\Salasreuniao\Room;

Session::checkRight('empresa_salas', UPDATE);

$sala = new Room();

if (isset($_POST['add'])) {
    $sala->check(-1, CREATE, $_POST);
    $id = $sala->add($_POST);
    if ($id) {
        Html::redirect($sala->getFormURLWithID($id));
    }
    Html::back();
} elseif (isset($_POST['update'])) {
    $sala->check((int) $_POST['id'], UPDATE);
    $sala->update($_POST);
    Html::back();
} elseif (isset($_POST['purge'])) {
    $sala->check((int) $_POST['id'], PURGE);
    $sala->delete($_POST, true);
    $sala->redirectToList();
} else {
    Html::header(Room::getTypeName(Session::getPluralNumber()), '', 'config', Room::class);
    $sala->display(['id' => (int) ($_GET['id'] ?? 0)]);
    Html::footer();
}
