<?php

/**
 * Plugin Abrir Chamado
 *
 * - Adiciona o botão "Abrir Chamados" no menu principal (à esquerda de "Ativos").
 * - Encerra a sessão após 1 hora sem atividade do usuário.
 * - Remove a página Home: a página inicial e os logos levam à lista de chamados.
 * - Esconde "Pesquisas salvas" (menu, painel e botão das listas).
 * - Lista de chamados: cartões "Estou atendendo" e "Abri" no painel (como Novos, Atribuídos...).
 * - Atalho "Sala de reunião" no Catálogo de serviços.
 * - Tela de login: renova a sessão se ficar aberta muito tempo (evita "Sua sessão expirou").
 * - Categorias em árvore: a lista abre recolhida e cada categoria expande ao clicar.
 * - Cadastro de categoria: interruptor "Exibir nos chamados" (categoria nova começa escondida).
 * - Tela do chamado: sem "Itens" e "Objetos de assistência relacionados"; botões
 *   "Adicionar solução" e "Pendência" ao lado de "Responder" (sem abrir a seta);
 *   "Responder" não marca pendência; sem lembretes automáticos de pendência.
 * - Sem vermelho: erros/alertas do GLPI em laranja-escuro (css/cores.css).
 * - Menu principal organizado em grupos; listas sem "Mostrar no mapa" e sem nomes de coluna repetidos.
 * - Botão de tema claro/escuro ao lado do usuário (gravado nas preferências dele).
 * - "Catálogo de serviços" aparece como "Abrir Chamados" (inclusive no menu guardado na sessão).
 *
 * Não altera nenhum arquivo do núcleo do GLPI.
 */

define('PLUGIN_ABRIRCHAMADO_VERSION', '1.21.1');

function plugin_version_abrirchamado()
{
    return [
        'name'         => 'Abrir Chamado',
        'version'      => PLUGIN_ABRIRCHAMADO_VERSION,
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

function plugin_init_abrirchamado()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['abrirchamado']                = true;
    $PLUGIN_HOOKS['add_css']['abrirchamado']                       = ['css/abrirchamado.css', 'css/cores.css'];
    $PLUGIN_HOOKS['add_javascript']['abrirchamado']                = ['js/abrirchamado.js', 'js/categorias.js', 'js/chamado.js', 'js/maiusculas.js', 'js/avisos.js', 'js/perfis.js'];
    $PLUGIN_HOOKS['add_javascript_anonymous_page']['abrirchamado'] = ['js/login.js', 'js/maiusculas.js'];
    $PLUGIN_HOOKS['add_css_anonymous_page']['abrirchamado']        = 'css/cores.css';

    // Aba "Módulos da Empresa" na tela de Perfis (permissões de relatórios, reservas, pagamentos, telas)
    Plugin::registerClass(\GlpiPlugin\Abrirchamado\Perfil::class, ['addtabon' => [Profile::class]]);

    // Cartões "Estou atendendo" e "Abri" no painel da lista de chamados
    $PLUGIN_HOOKS['dashboard_cards']['abrirchamado'] = 'plugin_abrirchamado_dashboard_cards';

    // Interruptor "Exibir nos chamados" no cadastro de categorias
    $PLUGIN_HOOKS['pre_item_form']['abrirchamado']   = 'plugin_abrirchamado_pre_item_form';
    $PLUGIN_HOOKS['item_get_datas']['abrirchamado']  = [NotificationTargetTicket::class => 'plugin_abrirchamado_dados_notificacao'];
    $PLUGIN_HOOKS['pre_item_add']['abrirchamado']    = [ITILCategory::class => 'plugin_abrirchamado_categoria_salvar'];
    $PLUGIN_HOOKS['pre_item_update']['abrirchamado'] = [ITILCategory::class => 'plugin_abrirchamado_categoria_salvar'];
    $PLUGIN_HOOKS['item_add']['abrirchamado']        = [ITILCategory::class => 'plugin_abrirchamado_categoria_salva', User::class => 'plugin_abrirchamado_usuario_salvo', Computer::class => 'plugin_abrirchamado_computador_salvo'];
    $PLUGIN_HOOKS['item_update']['abrirchamado']     = [ITILCategory::class => 'plugin_abrirchamado_categoria_salva', User::class => 'plugin_abrirchamado_usuario_salvo', Computer::class => 'plugin_abrirchamado_computador_salvo'];

    // Ativos > Situação dos computadores
    $PLUGIN_HOOKS['menu_toadd']['abrirchamado'] = ['assets' => \GlpiPlugin\Abrirchamado\Computadores::class];
}

function plugin_abrirchamado_check_prerequisites()
{
    return true;
}

function plugin_abrirchamado_check_config($verbose = false)
{
    return true;
}
