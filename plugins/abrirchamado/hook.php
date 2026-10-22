<?php

function plugin_abrirchamado_install()
{
    plugin_abrirchamado_cartoes_no_painel();
    \GlpiPlugin\Abrirchamado\Perfil::instalar();
    \GlpiPlugin\Abrirchamado\SincronizarAd::instalar();
    return true;
}

/**
 * Cartões "Estou atendendo" e "Abri", no painel da lista de chamados
 * (junto de Novos, Atribuídos, Pendentes...). Contam só chamados em aberto.
 */
function plugin_abrirchamado_cartoes_def(): array
{
    return [
        'atendendo' => ['label' => 'Estou atendendo', 'icon' => 'ti ti-tool',      'cor' => '#fdc300', 'campo' => 5, 'tipo' => CommonITILActor::ASSIGN],
        'abri'      => ['label' => 'Abri',            'icon' => 'ti ti-user-edit', 'cor' => '#206bc4', 'campo' => 4, 'tipo' => CommonITILActor::REQUESTER],
    ];
}

function plugin_abrirchamado_dashboard_cards($cartoes)
{
    $cartoes = is_array($cartoes) ? $cartoes : [];
    foreach (plugin_abrirchamado_cartoes_def() as $chave => $c) {
        $cartoes['plugin_abrirchamado_' . $chave] = [
            'widgettype' => ['bigNumber'],
            'itemtype'   => '\\' . Ticket::class,
            'group'      => __('Assistance'),
            'label'      => $c['label'],
            'provider'   => 'plugin_abrirchamado_cartao',
            'args'       => ['chave' => $chave],
            'cache'      => false,
            'filters'    => [],
        ];
    }
    return $cartoes;
}

function plugin_abrirchamado_cartao(string $chave, array $params = []): array
{
    /** @var \DBmysql $DB */
    global $DB, $CFG_GLPI;

    $c      = plugin_abrirchamado_cartoes_def()[$chave] ?? plugin_abrirchamado_cartoes_def()['abri'];
    $numero = (int) $DB->request([
        'COUNT'      => 'cpt',
        'FROM'       => 'glpi_tickets',
        'INNER JOIN' => [
            'glpi_tickets_users' => ['ON' => ['glpi_tickets_users' => 'tickets_id', 'glpi_tickets' => 'id']],
        ],
        'WHERE'      => [
            'glpi_tickets.is_deleted'     => 0,
            'glpi_tickets.status'         => Ticket::getNotSolvedStatusArray(),
            'glpi_tickets_users.users_id' => Session::getLoginUserID(),
            'glpi_tickets_users.type'     => $c['tipo'],
        ],
    ])->current()['cpt'];

    $url = $CFG_GLPI['root_doc'] . '/front/ticket.php?' . http_build_query([
        'criteria' => [
            ['field' => $c['campo'], 'searchtype' => 'equals', 'value' => 'myself'],
            ['link' => 'AND', 'field' => 12, 'searchtype' => 'equals', 'value' => 'notold'],
        ],
        'reset' => 'reset',
    ]);

    return ['number' => $numero, 'url' => $url, 'label' => $c['label'], 'icon' => $c['icon']];
}

/**
 * Coloca os cartões no início do painel da lista de chamados (só se ainda não estiverem lá).
 */
function plugin_abrirchamado_cartoes_no_painel(): void
{
    /** @var \DBmysql $DB */
    global $DB;

    // Só na primeira instalação: se alguém tirar os cartões do painel, não voltam sozinhos
    if (Config::getConfigurationValues('plugin:abrirchamado', ['cartoes_no_painel'])['cartoes_no_painel'] ?? false) {
        return;
    }
    Config::setConfigurationValues('plugin:abrirchamado', ['cartoes_no_painel' => 1]);

    $chave  = (string) (Config::getConfigurationValues('core', ['default_dashboard_mini_ticket'])['default_dashboard_mini_ticket'] ?? 'mini_tickets');
    $painel = $DB->request(['FROM' => 'glpi_dashboards_dashboards', 'WHERE' => ['key' => $chave]])->current();
    if (!$painel) {
        return;
    }
    $itens = iterator_to_array($DB->request(['FROM' => 'glpi_dashboards_items', 'WHERE' => ['dashboards_dashboards_id' => $painel['id']]]));
    $novos = array_filter(
        array_keys(plugin_abrirchamado_cartoes_def()),
        fn ($k) => array_filter($itens, fn ($i) => $i['card_id'] === 'plugin_abrirchamado_' . $k) === []
    );
    if ($novos === []) {
        return;
    }

    foreach ($novos as $k) {
        $c = plugin_abrirchamado_cartoes_def()[$k];
        $DB->insert('glpi_dashboards_items', [
            'dashboards_dashboards_id' => $painel['id'],
            'gridstack_id'             => 'plugin_abrirchamado_' . $k . '_' . bin2hex(random_bytes(8)),
            'card_id'                  => 'plugin_abrirchamado_' . $k,
            'x'                        => -1,
            'y'                        => 0,
            'width'                    => 4,
            'height'                   => 2,
            'card_options'             => json_encode([
                'color'        => $c['cor'],
                'widgettype'   => 'bigNumber',
                'use_gradient' => '0',
                'limit'        => '7',
            ]),
        ]);
    }

    // Reorganiza a primeira linha: estes cartões primeiro e todos cabendo nas
    // 33 colunas do painel da lista de chamados (cartões fora da grade travam o painel)
    $linha = iterator_to_array($DB->request([
        'FROM'  => 'glpi_dashboards_items',
        'WHERE' => ['dashboards_dashboards_id' => $painel['id'], 'y' => 0],
        'ORDER' => ['x', 'id'],
    ]));
    $largura = max(2, min(4, intdiv(33, max(1, count($linha)))));
    $x = 0;
    foreach ($linha as $item) {
        $DB->update('glpi_dashboards_items', ['x' => $x, 'width' => $largura], ['id' => $item['id']]);
        $x += $largura;
    }
}

function plugin_abrirchamado_uninstall()
{
    return true;
}

/**
 * Categoria do usuário = setor dele no AD (a pasta/OU), em vez do "departamento" do RH,
 * que tem setores que não existem e pessoas no setor errado. Diretores ficam em "Diretoria".
 */
function plugin_abrirchamado_categoria_do_usuario(string $login, string $dn): ?string
{
    $diretores = ['dulio.santana', 'emileidy.viana', 'hebert.santos', 'weber.lima', 'marcia.rodrigues', 'larissa.siqueira', 'luiz.sena'];
    if (in_array(strtolower($login), $diretores, true)) {
        return 'Diretoria';
    }

    $setores = [
        'ADMINISTRATIVO' => 'Administrativo', 'ATENDIMENTO' => 'Atendimento', 'CADASTRO' => 'Cadastro',
        'COMERCIAL' => 'Comercial', 'CONCIERGE' => 'Concierge', 'CONTASMEDICAS' => 'Contas Médicas',
        'CREDENCIAMENTO' => 'Credenciamento', 'SAC' => 'SAC', 'REGULACAO' => 'Regulação',
        'PRIVACIDADE' => 'Conformidade Jurídica', 'JURIDICO' => 'Conformidade Jurídica',
        'CONFORMIDADE' => 'Conformidade Jurídica', 'MARKETING' => 'Marketing', 'GC' => 'Gente e Cultura',
        'FINANCEIRO' => 'Financeiro', 'DIRETORIA' => 'Diretoria', 'TI' => 'Tecnologia e Inovação',
    ];

    if (preg_match('/OU=([^,]+),OU=UNITY,/i', $dn, $m)) {
        return $setores[strtoupper($m[1])] ?? null;
    }

    return null;
}

/**
 * Hook de computador criado/atualizado pelo inventário: liga ao usuário do último login,
 * ao grupo e ao local (setor) dele.
 */
function plugin_abrirchamado_computador_salvo(Computer $computador)
{
    \GlpiPlugin\Abrirchamado\Computadores::vincular((int) $computador->getID());
}

/**
 * Hook de usuário criado/atualizado (inclusive pela sincronização com o AD):
 * mantém a categoria igual ao setor.
 */
function plugin_abrirchamado_usuario_salvo(User $usuario)
{
    /** @var \DBmysql $DB */
    global $DB;

    $atual = $DB->request([
        'SELECT' => ['name', 'user_dn', 'usercategories_id'],
        'FROM'   => 'glpi_users',
        'WHERE'  => ['id' => $usuario->getID()],
    ])->current();
    if (!$atual) {
        return;
    }

    $nome = plugin_abrirchamado_categoria_do_usuario((string) $atual['name'], (string) $atual['user_dn']);
    if ($nome === null) {
        return;
    }

    $achada = $DB->request(['SELECT' => 'id', 'FROM' => 'glpi_usercategories', 'WHERE' => ['name' => $nome]])->current();
    $id     = $achada ? (int) $achada['id'] : (int) (new UserCategory())->add(['name' => $nome]);

    if ($id && (int) $atual['usercategories_id'] !== $id) {
        $DB->update('glpi_users', ['usercategories_id' => $id], ['id' => $usuario->getID()]);
    }
}

/**
 * Campos do GLPI que decidem se a categoria aparece na lista de categorias dos chamados.
 */
function plugin_abrirchamado_campos_visibilidade(): array
{
    return ['is_incident', 'is_request', 'is_helpdeskvisible'];
}

/**
 * Interruptor "Exibir nos chamados" no topo do cadastro de categoria.
 */
function plugin_abrirchamado_pre_item_form(array $params)
{
    $item = $params['item'] ?? null;

    // Perfis: resumo do que o perfil pode fazer
    if ($item instanceof Profile) {
        if (!$item->isNewItem() && ($item->fields['interface'] ?? '') === 'central') {
            \GlpiPlugin\Abrirchamado\Perfil::resumo((int) $item->getID());
        }
        return;
    }

    if (!$item instanceof ITILCategory || !$item->canUpdateItem()) {
        return;
    }

    $nova    = $item->isNewItem();
    // Categoria nova começa escondida: só aparece quando alguém ligar.
    // Exceção: criada pelo "+" dentro do chamado (janela), já que é para usar ali.
    if ($nova) {
        $visivel = (string) ($_REQUEST['_in_modal'] ?? '') === '1';
    } else {
        $visivel = (int) $item->fields['is_incident'] === 1 || (int) $item->fields['is_request'] === 1;
    }
    $filhas  = $nova ? 0 : count(getSonsOf(ITILCategory::getTable(), $item->getID())) - 1;
    $id      = 'abrirchamado_visivel_' . mt_rand();

    echo '<div class="card-body pb-0">';
    echo '<div class="alert alert-info d-flex flex-column gap-2 mb-0">';
    echo '<input type="hidden" name="_abrirchamado_visivel" value="0">';
    echo '<label class="form-check form-switch mb-0" for="' . $id . '">';
    echo '<input class="form-check-input" type="checkbox" role="switch" id="' . $id . '" name="_abrirchamado_visivel" value="1"' . ($visivel ? ' checked' : '') . '>';
    echo '<span class="form-check-label fw-bold">Exibir nos chamados</span>';
    echo '</label>';
    echo '<div class="text-muted small">Desligado: a categoria não aparece na lista de categorias ao abrir ou editar chamados.</div>';
    if ($filhas > 0) {
        echo '<label class="form-check mb-0">';
        echo '<input type="hidden" name="_abrirchamado_subcategorias" value="0">';
        echo '<input class="form-check-input" type="checkbox" name="_abrirchamado_subcategorias" value="1">';
        echo '<span class="form-check-label">Aplicar também às ' . $filhas . ' subcategorias</span>';
        echo '</label>';
    }
    echo '</div>';
    echo '</div>';

    // Mantém as opções "Visível para incidente/requisição/interface simplificada" iguais ao interruptor
    echo Html::scriptBlock(
        "document.getElementById('" . $id . "').addEventListener('change', function () {
            var form = this.form;
            if (!form) { return; }
            var ligado = this.checked;
            ['is_incident', 'is_request', 'is_helpdeskvisible'].forEach(function (nome) {
                form.querySelectorAll('input[type=checkbox][name=' + nome + ']').forEach(function (c) {
                    c.checked = ligado;
                });
                form.querySelectorAll('select[name=' + nome + ']').forEach(function (s) {
                    $(s).val(ligado ? '1' : '0').trigger('change');
                });
            });
        });"
    );
}

/**
 * Aplica o interruptor aos campos de visibilidade do GLPI antes de gravar.
 */
function plugin_abrirchamado_categoria_salvar(ITILCategory $item)
{
    if (!is_array($item->input) || !isset($item->input['_abrirchamado_visivel'])) {
        return;
    }

    $ligado = (int) $item->input['_abrirchamado_visivel'] === 1;
    $campos = plugin_abrirchamado_campos_visibilidade();

    if (!$ligado) {
        foreach ($campos as $campo) {
            $item->input[$campo] = 0;
        }
        return;
    }

    // Ligado sem nenhum tipo marcado: mostra em incidentes, requisições e interface simplificada
    $incidente  = (int) ($item->input['is_incident'] ?? $item->fields['is_incident'] ?? 0);
    $requisicao = (int) ($item->input['is_request'] ?? $item->fields['is_request'] ?? 0);
    if ($incidente === 0 && $requisicao === 0) {
        foreach ($campos as $campo) {
            $item->input[$campo] = 1;
        }
    }
}

/**
 * "Aplicar também às subcategorias": copia a visibilidade para todas as categorias abaixo.
 */
function plugin_abrirchamado_categoria_salva(ITILCategory $item)
{
    /** @var \DBmysql $DB */
    global $DB;

    if (!is_array($item->input) || (int) ($item->input['_abrirchamado_subcategorias'] ?? 0) !== 1) {
        return;
    }

    $filhas = array_diff(array_map('intval', array_keys(getSonsOf(ITILCategory::getTable(), $item->getID()))), [$item->getID()]);
    if ($filhas === []) {
        return;
    }

    $valores = [];
    foreach (plugin_abrirchamado_campos_visibilidade() as $campo) {
        $valores[$campo] = (int) $item->fields[$campo];
    }
    $DB->update(ITILCategory::getTable(), $valores + ['date_mod' => $_SESSION['glpi_currenttime']], ['id' => $filhas]);
}

/**
 * Variável extra dos e-mails e avisos de chamado: ##ticket.pendencia## (motivo da pendência atual).
 * O GLPI só entrega o nome do motivo para "lembretes automáticos", então buscamos direto.
 */
function plugin_abrirchamado_dados_notificacao(NotificationTarget $alvo)
{
    /** @var \DBmysql $DB */
    global $DB;

    $chamado = $alvo->obj ?? null;
    if (!$chamado instanceof Ticket || $chamado->isNewItem()) {
        return;
    }

    $linha = $DB->request([
        'SELECT'     => ['r.name'],
        'FROM'       => 'glpi_pendingreasons_items AS pi',
        'INNER JOIN' => ['glpi_pendingreasons AS r' => ['ON' => ['pi' => 'pendingreasons_id', 'r' => 'id']]],
        'WHERE'      => ['pi.itemtype' => 'Ticket', 'pi.items_id' => $chamado->getID()],
        'ORDER'      => 'pi.id DESC',
        'LIMIT'      => 1,
    ])->current();

    $alvo->data['##ticket.pendencia##'] = (string) ($linha['name'] ?? '');

    if (($alvo->raiseevent ?? '') === 'recall') {
        plugin_abrirchamado_dados_sla($alvo, $chamado);
    }
}

/**
 * Alerta de prazo (SLA): informa qual prazo está em jogo e quanto dele já foi consumido
 * (80%, 90% ou 100%), contando só o horário útil do calendário do SLA.
 *   ##ticket.sla_tipo##        Primeiro atendimento / Solução
 *   ##ticket.sla_percentual##  80% / 90% / 100%
 *   ##ticket.sla_prazo##       data limite
 */
function plugin_abrirchamado_dados_sla(NotificationTarget $alvo, Ticket $chamado): void
{
    $f = $chamado->fields;

    // Enquanto ninguém começou o atendimento, vale o prazo de primeiro atendimento
    $primeiro = (int) ($f['slas_id_tto'] ?? 0) > 0 && empty($f['takeintoaccountdate']) && !empty($f['time_to_own']);
    $limite   = $primeiro ? $f['time_to_own'] : ($f['time_to_resolve'] ?? null);
    $slaId    = (int) ($primeiro ? $f['slas_id_tto'] : ($f['slas_id_ttr'] ?? 0));
    if (empty($limite) || empty($f['date'])) {
        return;
    }

    $calendario = new Calendar();
    $calId = 0;
    if ($slaId > 0) {
        $sla = new SLA();
        if ($sla->getFromDB($slaId)) {
            $calId = (int) $sla->fields['calendars_id'];
            if ($calId === 0 && (int) $sla->fields['slms_id'] > 0) {
                $slm = new SLM();
                $calId = $slm->getFromDB((int) $sla->fields['slms_id']) ? (int) $slm->fields['calendars_id'] : 0;
            }
        }
    }

    $agora = date('Y-m-d H:i:s');
    if ($calId > 0 && $calendario->getFromDB($calId)) {
        $total  = (int) $calendario->getActiveTimeBetween($f['date'], $limite);
        $gasto  = (int) $calendario->getActiveTimeBetween($f['date'], min($agora, $limite));
    } else {
        $total  = max(0, strtotime($limite) - strtotime($f['date']));
        $gasto  = max(0, min(time(), strtotime($limite)) - strtotime($f['date']));
    }
    $pct = ($total > 0 && $agora < $limite) ? ($gasto / $total * 100) : 100;

    // Os níveis do SLA disparam em 80%, 90% e 100%; o atraso da tarefa agendada não muda o rótulo
    $rotulo = $pct < 85 ? '80%' : ($pct < 95 ? '90%' : '100%');

    $alvo->data['##ticket.sla_tipo##']       = $primeiro ? 'Primeiro atendimento' : 'Solução';
    $alvo->data['##ticket.sla_percentual##'] = $rotulo;
    $alvo->data['##ticket.sla_prazo##']      = Html::convDateTime($limite);
}
