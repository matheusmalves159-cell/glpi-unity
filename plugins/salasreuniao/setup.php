<?php

/**
 * Plugin Salas de reunião
 *
 * Formulário de agendamento de salas de reunião ligado ao Google Agenda:
 * mostra só os horários livres de cada sala (respeitando o intervalo entre
 * reuniões), grava o evento na agenda da sala pela API do Google e permite
 * ver, cancelar e remarcar em "Minhas reservas" (só as da pessoa, com filtro simples);
 * o Super-Admin vê todas em "Reservas da empresa", com filtros detalhados.
 */

use GlpiPlugin\Salasreuniao\Booking;
use GlpiPlugin\Salasreuniao\Painel;
use GlpiPlugin\Salasreuniao\Room;

define('PLUGIN_SALASREUNIAO_VERSION', '1.6.2');

function plugin_version_salasreuniao()
{
    return [
        'name'         => 'Salas de Reunião',
        'version'      => PLUGIN_SALASREUNIAO_VERSION,
        'author'       => 'Unity Saúde',
        'license'      => 'GPLv3',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => '11.0.0',
                'max' => '11.99.99',
            ],
            'php' => [
                'exts' => ['openssl' => ['required' => true]],
            ],
        ],
    ];
}

function plugin_init_salasreuniao()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['salasreuniao'] = true;

    // A chave da conta de serviço do Google fica criptografada no banco
    $PLUGIN_HOOKS['secured_configs']['salasreuniao'] = ['service_account_key'];

    if (Session::getLoginUserID() === false) {
        return;
    }

    // Interface padrão: Assistência > Salas de reunião
    $PLUGIN_HOOKS['menu_toadd']['salasreuniao'] = ['helpdesk' => Booking::class];

    // Interface simplificada (Self-Service)
    $PLUGIN_HOOKS['helpdesk_menu_entry']['salasreuniao']      = '/front/agendar.php';
    $PLUGIN_HOOKS['helpdesk_menu_entry_icon']['salasreuniao'] = Booking::getIcon();

    // Cartões de painel "Reuniões em andamento/agendadas" (Adicionar cartão > Salas de Reunião).
    // Ficam escondidos para quem não é Super-Admin nem do setor responsável.
    $PLUGIN_HOOKS['dashboard_cards']['salasreuniao'] = 'plugin_salasreuniao_dashboard_cards';
    $PLUGIN_HOOKS['add_css']['salasreuniao']         = 'css/painel.css';
    if (Painel::podeVer()) {
        $PLUGIN_HOOKS['add_javascript']['salasreuniao'] = 'js/painel.js';
    }

    if (Session::haveRight('empresa_salas', UPDATE)) {
        $PLUGIN_HOOKS['menu_toadd']['salasreuniao']['config'] = Room::class;
        $PLUGIN_HOOKS['config_page']['salasreuniao']          = 'front/config.form.php';
    }
}

function plugin_salasreuniao_check_prerequisites()
{
    return true;
}

function plugin_salasreuniao_check_config($verbose = false)
{
    return true;
}
