<?php

/**
 * Plugin Pagamentos
 *
 * - Tela "Solicitar pagamento" (abre chamado para o Financeiro, com anexos).
 * - "Minhas solicitações" para acompanhar os pedidos.
 * - Tela do Financeiro para gerar a planilha Excel dos pedidos por período.
 */

use GlpiPlugin\Pagamentos\Pedido;
use GlpiPlugin\Pagamentos\Tela;

define('PLUGIN_PAGAMENTOS_VERSION', '4.3.2');

function plugin_version_pagamentos()
{
    return [
        'name'         => 'Pagamentos',
        'version'      => PLUGIN_PAGAMENTOS_VERSION,
        'author'       => 'Unity Saúde',
        'license'      => 'GPLv3',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => '11.0.0',
                'max' => '11.99.99',
            ],
        ],
    ];
}

function plugin_init_pagamentos()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['pagamentos'] = true;

    if (Session::getLoginUserID() === false) {
        return;
    }

    $menus = [];
    // Assistência > Solicitar pagamento (+ cartão no Catálogo de serviços)
    if (Pedido::podeSolicitar()) {
        $menus['helpdesk'] = Pedido::class;
        $PLUGIN_HOOKS['add_javascript']['pagamentos'] = 'js/catalogo.js';
    }
    // Ferramentas > Planilha de Pagamentos (Financeiro e administradores)
    if (Tela::canView()) {
        $menus['tools'] = Tela::class;
    }
    if ($menus !== []) {
        $PLUGIN_HOOKS['menu_toadd']['pagamentos'] = $menus;
    }

    if (Session::haveRight('empresa_pagamentos', UPDATE)) {
        $PLUGIN_HOOKS['config_page']['pagamentos'] = 'front/config.form.php';
    }
}

function plugin_pagamentos_check_prerequisites()
{
    return true;
}

function plugin_pagamentos_check_config($verbose = false)
{
    return true;
}
