<?php

/**
 * Plugin Relatórios de chamados
 *
 * Painel com filtros, indicadores, gráficos e rankings (requerentes, setores,
 * técnicos, categorias, prioridades) e o cumprimento dos SLAs. Tudo pode ser
 * exportado em Excel (várias abas, lista completa para auditoria) e em PDF
 * (com os gráficos).
 */

use GlpiPlugin\Relatorios\Relatorio;

define('PLUGIN_RELATORIOS_VERSION', '1.0.2');

function plugin_version_relatorios()
{
    return [
        'name'         => 'Relatórios de chamados',
        'version'      => PLUGIN_RELATORIOS_VERSION,
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

function plugin_init_relatorios()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['relatorios'] = true;

    if (Session::getLoginUserID() === false) {
        return;
    }

    // Assistência > Relatórios de chamados (quem tem direito a Estatísticas)
    if (Relatorio::canView()) {
        $PLUGIN_HOOKS['menu_toadd']['relatorios'] = ['helpdesk' => Relatorio::class];
    }
}

function plugin_relatorios_check_prerequisites()
{
    return true;
}

function plugin_relatorios_check_config($verbose = false)
{
    return true;
}
