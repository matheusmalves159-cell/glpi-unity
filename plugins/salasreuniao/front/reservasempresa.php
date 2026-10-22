<?php

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Salasreuniao\Booking;
use GlpiPlugin\Salasreuniao\Reservas;

Session::checkLoginUser();

// Todas as reservas da empresa: só Super-Admin
if (!Booking::podeVerTodas()) {
    Html::displayRightError();
}

$filtros  = Reservas::filtrosEmpresa($_GET);
$reservas = Reservas::empresa($filtros);

Booking::cabecalho('Reservas de Sala da Empresa');
TemplateRenderer::getInstance()->display('@salasreuniao/reservasempresa.html.twig', [
    'f'         => $filtros,
    'reservas'  => $reservas,
    'resumo'    => Reservas::resumo($reservas),
    'opcoes'    => Reservas::opcoesEmpresa(),
    'situacoes' => Reservas::SITUACOES,
    'hoje'      => date('Y-m-d'),
]);
Booking::rodape();
