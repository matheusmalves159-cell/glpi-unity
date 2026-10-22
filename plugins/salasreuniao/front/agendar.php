<?php

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Salasreuniao\Availability;
use GlpiPlugin\Salasreuniao\Booking;
use GlpiPlugin\Salasreuniao\GoogleClient;
use GlpiPlugin\Salasreuniao\Room;
use GlpiPlugin\Salasreuniao\Settings;

Session::checkLoginUser();

/** @var \DBmysql $DB */
global $CFG_GLPI, $DB;

// Remarcação: o formulário vem preenchido com a reserva atual
$remarcar = null;
if (!empty($_GET['remarcar'])) {
    $reserva = new Booking();
    if ($reserva->getFromDB((int) $_GET['remarcar']) && $reserva->podeAlterar()) {
        $remarcar = $reserva->fields;
    } else {
        Session::addMessageAfterRedirect(htmlescape('Essa reserva não pode mais ser remarcada.'), false, ERROR);
        Html::redirect($CFG_GLPI['root_doc'] . '/plugins/salasreuniao/front/minhasreservas.php');
    }
}

$tz       = Availability::tz();
$hoje     = new DateTimeImmutable('today', $tz);
$duracoes = [];
foreach (Settings::duracoes() as $minutos) {
    $duracoes[$minutos] = Settings::rotuloDuracao($minutos);
}

$prontas      = ['is_active' => 1, 'google_calendar_id' => ['<>', '']];
$salas_ativas = countElementsInTable(Room::getTable(), $prontas);
$sem_limite   = countElementsInTable(Room::getTable(), $prontas + ['capacity' => 0]) > 0;
// Limite do campo "pessoas": só existe se todas as salas tiverem capacidade cadastrada
$max_pessoas = $sem_limite ? 0 : (int) ($DB->request([
    'SELECT' => ['MAX' => 'capacity AS m'],
    'FROM'   => Room::getTable(),
    'WHERE'  => ['is_active' => 1],
])->current()['m'] ?? 0);

Booking::cabecalho('Agendar Sala de Reunião');
TemplateRenderer::getInstance()->display('@salasreuniao/agendar.html.twig', [
    'pronto'      => GoogleClient::isConfigured() && $salas_ativas > 0,
    'filtra_capacidade' => Room::algumaComCapacidade(),
    'admin'       => Booking::podeVerTodas(),
    'duracoes'    => $duracoes,
    'tipos'       => Booking::TIPOS,
    'data_min'    => $hoje->format('Y-m-d'),
    'data_max'    => $hoje->modify('+' . (int) Settings::get('antecedencia_dias') . ' days')->format('Y-m-d'),
    'intervalo'   => (int) Settings::get('intervalo_min'),
    'expediente'  => Settings::get('hora_inicio') . ' às ' . Settings::get('hora_fim'),
    'fecha'       => Settings::get('hora_fim'),
    'max_pessoas' => $max_pessoas,
    'js_versao'   => (string) @filemtime(__DIR__ . '/../public/js/agendar.js'),
    'remarcar'    => $remarcar === null ? null : [
        'id'      => (int) $remarcar['id'],
        'data'    => substr($remarcar['begin'], 0, 10),
        'hora'    => substr($remarcar['begin'], 11, 5),
        'duracao' => (int) $remarcar['duration'],
        'pessoas' => (int) $remarcar['participants'],
        'tipo'    => $remarcar['meeting_type'],
        'assunto' => (string) $remarcar['subject'],
        'sala'    => (int) $remarcar['plugin_salasreuniao_rooms_id'],
    ],
]);
Booking::rodape();
