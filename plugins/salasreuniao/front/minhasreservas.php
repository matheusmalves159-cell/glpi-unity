<?php

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Salasreuniao\Booking;
use GlpiPlugin\Salasreuniao\Reservas;

Session::checkLoginUser();

// "Minhas reservas": só as reservas de quem está logado (inclusive para o Super-Admin,
// que vê as da empresa em "Reservas da empresa")
$visao = array_key_exists((string) ($_GET['ver'] ?? ''), Reservas::VISOES) ? (string) $_GET['ver'] : 'proximas';

Booking::cabecalho('Minhas Reservas de Sala');
TemplateRenderer::getInstance()->display('@salasreuniao/minhasreservas.html.twig', [
    'reservas' => Reservas::minhas($visao),
    'visao'    => $visao,
    'visoes'   => Reservas::VISOES,
    'contagem' => Reservas::contagemMinhas(),
    'admin'    => Booking::podeVerTodas(),
]);
Booking::rodape();
