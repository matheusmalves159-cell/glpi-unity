<?php

/**
 * Plugin Chamado por setor
 *
 * - Telas de abertura de chamado por setor (TI, Administrativo…): o usuário
 *   busca ou escolhe o pedido em botões, informa a prioridade (que define o SLA)
 *   e vê os prazos antes de enviar. Campos extras conforme o pedido.
 * - "Abrir chamado": escolha do setor; "Meus chamados" com prazos.
 * - Central de telas (Configurar): telas por setor (textos, campos, cartões,
 *   campos extras e links por categoria), solicitação de pagamento, salas de
 *   reunião e listas usadas nos campos.
 * - Acessos (Administração): permissões por áreas, por perfil e para um usuário específico.
 */

use GlpiPlugin\Chamadoti\Acessos;
use GlpiPlugin\Chamadoti\Central;

define('PLUGIN_CHAMADOTI_VERSION', '2.6.2');

function plugin_version_chamadoti()
{
    return [
        'name'         => 'Chamado por setor',
        'version'      => PLUGIN_CHAMADOTI_VERSION,
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

function plugin_init_chamadoti()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['chamadoti'] = true;

    if (Session::getLoginUserID() === false) {
        return;
    }

    // As telas entram como cartões no Catálogo de serviços (Assistência > Catálogo de serviços)
    $PLUGIN_HOOKS['add_javascript']['chamadoti'] = 'js/catalogo.js';
    $PLUGIN_HOOKS['add_css']['chamadoti']        = 'css/chamadoti.css';

    $menus = [];
    if (Central::canView()) {
        // Configurar > Central de telas
        $menus['config'] = Central::class;
        $PLUGIN_HOOKS['config_page']['chamadoti'] = 'front/central.php';
    }
    // Administração > Acessos (permissões simplificadas): fora do menu por enquanto, a tela
    // ainda vai ser refeita. O endereço /plugins/chamadoti/front/acessos.php continua funcionando.
    // if (Acessos::canView()) {
    //     $menus['admin'] = Acessos::class;
    // }
    if ($menus !== []) {
        $PLUGIN_HOOKS['menu_toadd']['chamadoti'] = $menus;
    }
}

function plugin_chamadoti_check_prerequisites()
{
    return true;
}

function plugin_chamadoti_check_config($verbose = false)
{
    return true;
}
