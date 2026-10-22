<?php

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Salasreuniao\Availability;
use GlpiPlugin\Salasreuniao\GoogleClient;
use GlpiPlugin\Salasreuniao\Room;
use GlpiPlugin\Salasreuniao\Settings;

Session::checkRight('empresa_salas', UPDATE);

$erro = static function (string $msg): void {
    Session::addMessageAfterRedirect(htmlescape($msg), false, ERROR);
    Html::back();
};

if (isset($_POST['update'])) {
    $hora = '/^([01]\d|2[0-3]):[0-5]\d$/';
    $inicio = (string) ($_POST['hora_inicio'] ?? '');
    $fim    = (string) ($_POST['hora_fim'] ?? '');
    if (!preg_match($hora, $inicio) || !preg_match($hora, $fim) || $inicio >= $fim) {
        $erro('Informe o horário de funcionamento (o início deve ser antes do fim).');
    }
    $dias = array_values(array_intersect(array_map('intval', (array) ($_POST['dias_semana'] ?? [])), range(1, 7)));
    if ($dias === []) {
        $erro('Escolha pelo menos um dia da semana.');
    }
    $duracoes = array_values(array_intersect(array_map('intval', (array) ($_POST['duracoes'] ?? [])), [15, 30, 45, 60, 90, 120, 180, 240, 300, 360, 480]));
    if ($duracoes === []) {
        $erro('Escolha pelo menos uma duração.');
    }
    $conta = trim((string) ($_POST['conta_delegada'] ?? ''));
    if ($conta !== '' && !filter_var($conta, FILTER_VALIDATE_EMAIL)) {
        $erro('A conta para agendar em nome deve ser um e-mail.');
    }

    $valores = [
        'hora_inicio'       => $inicio,
        'hora_fim'          => $fim,
        'dias_semana'       => implode(',', $dias),
        'duracoes'          => implode(',', $duracoes),
        'intervalo_min'     => (string) max(0, min(120, (int) ($_POST['intervalo_min'] ?? 0))),
        'passo_min'         => (string) (in_array((int) ($_POST['passo_min'] ?? 30), [15, 30, 60], true) ? (int) $_POST['passo_min'] : 30),
        'antecedencia_dias' => (string) max(1, min(365, (int) ($_POST['antecedencia_dias'] ?? 60))),
        'conta_delegada'    => $conta,
        'groups_id_assign'  => (string) max(0, (int) ($_POST['groups_id_assign'] ?? 0)),
        'itilcategories_id' => (string) max(0, (int) ($_POST['itilcategories_id'] ?? 0)),
    ];

    // Chave nova colada: só substitui se for uma chave de conta de serviço válida
    $chave = trim((string) ($_POST['service_account_key'] ?? ''));
    if ($chave !== '') {
        if (GoogleClient::lerChave($chave) === null) {
            $erro('O conteúdo colado não é um arquivo de chave JSON de conta de serviço do Google válido.');
        }
        $valores['service_account_key'] = $chave;
    } elseif (!empty($_POST['remover_chave'])) {
        $valores['service_account_key'] = '';
    }

    Settings::save($valores);
    Session::addMessageAfterRedirect(htmlescape('Configuração salva.'));
    Html::back();
} elseif (isset($_POST['testar'])) {
    try {
        $google = new GoogleClient();
        $tz     = Availability::tz();
        $de     = new DateTimeImmutable('today', $tz);
        $ok     = [];
        $falhas = [];
        foreach ((new Room())->find(['is_active' => 1], ['name ASC']) as $sala) {
            try {
                $google->listarEventos($sala['google_calendar_id'], $de, $de->modify('+1 day'));
                $ok[] = $sala['name'];
            } catch (Throwable $e) {
                $falhas[] = $sala['name'] . ': ' . $e->getMessage();
            }
        }
        if ($ok === [] && $falhas === []) {
            Session::addMessageAfterRedirect(htmlescape('Conexão com o Google OK, mas nenhuma sala ativa está cadastrada.'), false, WARNING);
        }
        if ($ok !== []) {
            Session::addMessageAfterRedirect(htmlescape('Agendas acessadas com sucesso: ' . implode(', ', $ok) . '.'));
        }
        foreach ($falhas as $falha) {
            Session::addMessageAfterRedirect(htmlescape($falha), false, ERROR);
        }
    } catch (Throwable $e) {
        Session::addMessageAfterRedirect(htmlescape('Falha na conexão com o Google: ' . $e->getMessage()), false, ERROR);
    }
    Html::back();
}

Html::header(Room::getTypeName(Session::getPluralNumber()), '', 'config', Room::class);
TemplateRenderer::getInstance()->display('@salasreuniao/config.html.twig', Settings::dadosConfig());
Html::footer();
