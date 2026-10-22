<?php

use GlpiPlugin\Salasreuniao\Availability;

Session::checkLoginUser();

header('Content-Type: application/json; charset=UTF-8');

try {
    $salas = Availability::doDia(
        (string) ($_GET['data'] ?? ''),
        (int) ($_GET['duracao'] ?? 0),
        (int) ($_GET['pessoas'] ?? 0),
        (int) ($_GET['remarcar'] ?? 0)
    );
    echo json_encode(['ok' => true, 'salas' => $salas]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
}
