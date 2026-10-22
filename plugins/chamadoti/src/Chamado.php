<?php

namespace GlpiPlugin\Chamadoti;

use CommonGLPI;
use CommonITILActor;
use Document;
use Group_Ticket;
use Html;
use ITILCategory;
use Session;
use Throwable;
use Ticket;
use User;
use UserTitle;

/**
 * Abertura de chamado pelas telas de cada setor (TI, Administrativo…).
 */
class Chamado extends CommonGLPI
{
    /** Anexos aceitos (prints, documentos, planilhas) */
    public const EXTENSOES = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'log', 'xls', 'xlsx', 'csv', 'doc', 'docx'];

    public const MAX_ANEXOS = 5;

    public const MAX_MB = 10;

    public const MAX_COLABORADORES = 10;

    /** Situação do colaborador que vai receber acesso */
    public const TIPOS_COLABORADOR = [
        'novo'       => 'Novo funcionário',
        'remanejado' => 'Remanejado',
    ];

    /** Quantas pessoas o erro afeta: sugere a prioridade */
    public const AFETADOS = [
        'eu'      => ['Só eu', 3],
        'setor'   => ['Meu setor', 4],
        'empresa' => ['Vários setores ou a empresa toda', 5],
    ];

    public static function getTypeName($nb = 0)
    {
        return 'Abrir chamado';
    }

    public static function getIcon()
    {
        return 'ti ti-headset';
    }

    public static function canView(): bool
    {
        return Session::getLoginUserID() !== false;
    }

    public static function getMenuContent()
    {
        return [
            'title' => self::getTypeName(),
            'page'  => '/ServiceCatalog',
            'icon'  => self::getIcon(),
            'links' => [
                'search' => '/plugins/chamadoti/front/meus.php',
                'add'    => '/ServiceCatalog',
            ],
        ];
    }

    /**
     * Tela pelo id (só ativa), ou null.
     */
    public static function tela(int $id): ?Tela
    {
        $tela = new Tela();
        return $id > 0 && $tela->getFromDB($id) && (int) $tela->fields['is_active'] === 1 && Tela::visivelParaMim($tela->fields) ? $tela : null;
    }

    /**
     * Árvore de categorias da tela (visíveis nos chamados e não ocultas na tela).
     *
     * @return array{nos: array<int, array>, raiz: int}
     */
    public static function arvore(Tela $tela): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $raiz = (int) $tela->fields['itilcategories_id'];
        $ids  = $raiz > 0 ? array_map('intval', array_keys(getSonsOf(ITILCategory::getTable(), $raiz))) : [];
        $nos  = [];
        if ($ids === []) {
            return ['nos' => [], 'raiz' => $raiz];
        }

        foreach ($DB->request([
            'FROM'  => ITILCategory::getTable(),
            'WHERE' => [
                'id' => $ids,
                'OR' => ['is_request' => 1, 'is_incident' => 1],
            ],
            'ORDER' => ['name'],
        ]) as $c) {
            $id  = (int) $c['id'];
            $cfg = $tela->configCategoria($id);
            if ($id !== $raiz && $cfg['oculto']) {
                continue;
            }
            $nos[$id] = [
                'id'      => $id,
                'nome'    => (string) $c['name'],
                'pai'     => (int) $c['itilcategories_id'],
                'caminho' => (string) $c['completename'],
                'filhos'  => [],
                'ordem'   => $cfg['ordem'],
                'cfg'     => $cfg,
            ];
        }
        // A raiz pode estar com "Exibir nos chamados" desligado: entra só como ponto de partida
        if (!isset($nos[$raiz])) {
            $r = $DB->request(['FROM' => ITILCategory::getTable(), 'WHERE' => ['id' => $raiz]])->current();
            $nos[$raiz] = ['id' => $raiz, 'nome' => (string) ($r['name'] ?? ''), 'pai' => 0, 'caminho' => (string) ($r['completename'] ?? ''), 'filhos' => [], 'ordem' => 0, 'cfg' => $tela->configCategoria($raiz)];
        }

        // Filhos de quem ficou na árvore (categoria oculta some com as de baixo)
        $presentes = static function (int $id) use (&$nos, $raiz): bool {
            $atual = $id;
            while ($atual !== $raiz) {
                if (!isset($nos[$atual])) {
                    return false;
                }
                $atual = $nos[$atual]['pai'];
            }
            return true;
        };
        foreach (array_keys($nos) as $id) {
            if ($id !== $raiz && !$presentes($id)) {
                unset($nos[$id]);
            }
        }
        foreach ($nos as $id => $no) {
            if ($id !== $raiz && isset($nos[$no['pai']])) {
                $nos[$no['pai']]['filhos'][] = $id;
            }
        }
        // Ordem configurada (depois alfabética)
        foreach ($nos as $id => $no) {
            usort($nos[$id]['filhos'], fn ($a, $b) => [$nos[$a]['ordem'] ?: 999, $nos[$a]['nome']] <=> [$nos[$b]['ordem'] ?: 999, $nos[$b]['nome']]);
        }

        // Campos extras e link, herdados dos níveis acima
        foreach ($nos as $id => $no) {
            $extras = [];
            $campos = [];
            $link   = '';
            $atual  = $id;
            while (isset($nos[$atual])) {
                $extras = array_merge($nos[$atual]['cfg']['extras'], $extras);
                $campos = array_merge($nos[$atual]['cfg']['campos'], $campos);
                $link   = $link !== '' ? $link : $nos[$atual]['cfg']['link'];
                if ($atual === $raiz) {
                    break;
                }
                $atual = $nos[$atual]['pai'];
            }
            $nos[$id]['extras']    = array_values(array_unique($extras));
            $nos[$id]['campos']    = $campos;
            $nos[$id]['link']      = $link;
            $nos[$id]['incidente'] = in_array('impacto', $nos[$id]['extras'], true);
            $nos[$id]['rotulo']    = trim(preg_replace('/^' . preg_quote($nos[$raiz]['caminho'], '/') . '\s*>\s*/', '', $no['caminho']));
        }

        return ['nos' => $nos, 'raiz' => $raiz];
    }

    /**
     * Cartões do primeiro nível, com ícone e explicação (da configuração ou padrão).
     */
    public static function tipos(array $arvore): array
    {
        $tipos = [];
        foreach ($arvore['nos'][$arvore['raiz']]['filhos'] ?? [] as $id) {
            $no      = $arvore['nos'][$id];
            $padrao  = Tela::VISUAL_PADRAO[$no['nome']] ?? ['ti-point', ''];
            $icone   = $no['cfg']['icone'] !== '' ? $no['cfg']['icone'] : $padrao[0];
            $tipos[] = [
                'id'    => $id,
                'nome'  => $no['nome'],
                'icone' => str_starts_with($icone, 'ti-') ? $icone : 'ti-' . $icone,
                'texto' => $no['cfg']['texto'] !== '' ? $no['cfg']['texto'] : $padrao[1],
            ];
        }
        return $tipos;
    }

    /**
     * Usuários ativos para o campo "Copiar acesso de".
     *
     * @return string[]
     */
    public static function usuarios(): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $lista = [];
        foreach ($DB->request([
            'SELECT' => ['name', 'realname', 'firstname'],
            'FROM'   => 'glpi_users',
            'WHERE'  => ['is_active' => 1, 'is_deleted' => 0, 'NOT' => ['name' => ['glpi-system', '']]],
            'ORDER'  => ['firstname', 'realname'],
        ]) as $u) {
            $nome    = trim($u['firstname'] . ' ' . $u['realname']);
            $lista[] = $nome !== '' ? sprintf('%s (%s)', $nome, $u['name']) : (string) $u['name'];
        }
        return $lista;
    }

    /**
     * Setores (grupos com pessoas ativas) e as pessoas de cada um,
     * para "Copiar acesso de" mostrar só quem é do setor de destino.
     *
     * @return array{setores: array<int, string>, pessoas: array<int, string[]>}
     */
    public static function setores(): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $setores = [];
        $pessoas = [];
        foreach ($DB->request([
            'SELECT'     => ['g.id AS gid', 'g.name AS setor', 'u.name', 'u.firstname', 'u.realname'],
            // Só setores: subsetores (nível 3 em diante) não entram; as pessoas deles contam no setor pai
            'FROM'       => 'glpi_groups_users AS gu',
            'INNER JOIN' => [
                'glpi_groups AS g' => ['ON' => ['gu' => 'groups_id', 'g' => 'id']],
                'glpi_users AS u'  => ['ON' => ['gu' => 'users_id', 'u' => 'id']],
            ],
            'WHERE'      => ['u.is_active' => 1, 'u.is_deleted' => 0, 'NOT' => ['u.name' => ['glpi-system', '']]],
            'ORDER'      => ['g.completename', 'u.firstname', 'u.realname'],
        ]) as $r) {
            $gid = self::setorDoGrupo((int) $r['gid']);
            // Só o nome final do setor (sem "CEO >" na frente)
            $setores[$gid] ??= self::nomeDoGrupo($gid) ?? (string) $r['setor'];
            $nome = trim($r['firstname'] . ' ' . $r['realname']);
            $pessoas[$gid][] = $nome !== '' ? sprintf('%s (%s)', $nome, $r['name']) : (string) $r['name'];
        }
        return ['setores' => $setores, 'pessoas' => $pessoas];
    }

    /**
     * Setor (grupo) do usuário logado, para já vir escolhido.
     */
    public static function meuSetor(): int
    {
        /** @var \DBmysql $DB */
        global $DB;

        $g = $DB->request([
            'SELECT' => 'groups_id',
            'FROM'   => 'glpi_groups_users',
            'WHERE'  => ['users_id' => (int) Session::getLoginUserID()],
            'ORDER'  => ['is_dynamic DESC', 'id'],
            'LIMIT'  => 1,
        ])->current();
        return $g ? self::setorDoGrupo((int) $g['groups_id']) : 0;
    }

    /** Sobe da equipe (ex.: TI > Sustentação) até o setor (nível 2, logo abaixo do CEO). */
    private static function setorDoGrupo(int $id): int
    {
        /** @var \DBmysql $DB */
        global $DB;

        for ($i = 0; $i < 6; $i++) {
            $g = $DB->request(['SELECT' => ['groups_id', 'level'], 'FROM' => 'glpi_groups', 'WHERE' => ['id' => $id]])->current();
            if (!$g || (int) $g['level'] <= 2 || (int) $g['groups_id'] === 0) {
                return $id;
            }
            $id = (int) $g['groups_id'];
        }

        return $id;
    }

    private static function nomeDoGrupo(int $id): ?string
    {
        /** @var \DBmysql $DB */
        global $DB;

        $g = $DB->request(['SELECT' => 'name', 'FROM' => 'glpi_groups', 'WHERE' => ['id' => $id]])->current();

        return $g ? (string) $g['name'] : null;
    }

    /**
     * Cargos já cadastrados no GLPI (campo "Título" do usuário), para sugerir.
     *
     * @return string[]
     */
    public static function cargos(): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        return array_column(iterator_to_array($DB->request([
            'SELECT' => 'name',
            'FROM'   => 'glpi_usertitles',
            'ORDER'  => 'name',
        ])), 'name');
    }

    /**
     * Cargo do usuário logado (campo "Título" do cadastro).
     */
    public static function meuCargo(): string
    {
        $usuario = new User();
        if (!$usuario->getFromDB((int) Session::getLoginUserID()) || (int) $usuario->fields['usertitles_id'] <= 0) {
            return '';
        }
        $titulo = new UserTitle();
        return $titulo->getFromDB((int) $usuario->fields['usertitles_id']) ? (string) $titulo->fields['name'] : '';
    }

    /**
     * Valida e abre o chamado.
     *
     * @return array{ok:bool, msg:string, tickets_id?:int}
     */
    public static function criar(array $in, array $arquivos): array
    {
        $texto = static fn (string $campo, int $max = 255): string => mb_substr(trim((string) ($in[$campo] ?? '')), 0, $max);

        $tela = self::tela((int) ($in['tela'] ?? 0));
        if ($tela === null) {
            return ['ok' => false, 'msg' => 'Escolha para qual setor é o chamado.'];
        }
        $arvore    = self::arvore($tela);
        $categoria = (int) ($in['categoria'] ?? 0);
        $no        = $arvore['nos'][$categoria] ?? null;
        $opcoes    = $tela->opcoes();
        $titulo    = $texto('titulo');
        // Descrição escondida na tela: não grava nada que venha no envio
        $descricao = $opcoes['desc_modo'] === 'oculta' ? '' : $texto('descricao', 10000);
        // Prioridade escondida na tela: usa a prioridade padrão configurada
        $urgencia  = $tela->fields['mostrar_prioridade'] ? (int) ($in['urgencia'] ?? 0) : (int) $tela->fields['prioridade_padrao'];
        if (!$tela->fields['mostrar_anexos']) {
            $arquivos = [];
        }

        $erros = [];
        if ($no === null || $categoria === $arvore['raiz'] || $no['filhos'] !== []) {
            $erros[] = 'Escolha o tipo de pedido até o último nível.';
        } elseif ($no['link'] !== '') {
            $erros[] = 'Este pedido é feito em outra tela.';
        }
        if (mb_strlen($titulo) < 3) {
            $erros[] = 'Informe o título do chamado.';
        }
        if ($opcoes['desc_modo'] === 'obrigatoria' && mb_strlen($descricao) < 5) {
            $erros[] = 'Descreva o pedido.';
        }
        if (!isset(Prazos::PRIORIDADES[$urgencia])) {
            $erros[] = 'Escolha a prioridade.';
        }

        $linhas = [];
        $blocos = '';
        foreach ($no['extras'] ?? [] as $extra) {
            switch ($extra) {
                case 'impacto':
                    $afetados = (string) ($in['afetados'] ?? '');
                    if (!isset(self::AFETADOS[$afetados])) {
                        $erros[] = 'Informe quem está sendo afetado pelo erro.';
                    } else {
                        $linhas['Quem está afetado'] = self::AFETADOS[$afetados][0];
                    }
                    $desde = self::dataHora($in['desde'] ?? '');
                    if ($desde !== '') {
                        $linhas['Desde quando'] = $desde;
                    }
                    break;

                case 'equipamento':
                    $tipos = array_values(array_intersect((array) ($in['equipamentos'] ?? []), Settings::lista('equipamentos')));
                    if ($tipos === []) {
                        $erros[] = 'Marque qual equipamento.';
                    } else {
                        $linhas['Equipamento'] = implode(', ', $tipos);
                    }
                    if (($patrimonio = $texto('patrimonio', 100)) !== '') {
                        $linhas['Patrimônio/etiqueta'] = $patrimonio;
                    }
                    break;

                case 'emails':
                    $emails = $texto('emails', 2000);
                    if (!preg_match('/\S+@\S+/', $emails)) {
                        $erros[] = 'Informe os e-mails que devem ser configurados.';
                    } else {
                        $linhas['E-mails para configurar'] = $emails;
                    }
                    break;

                case 'backup':
                    $caminho = $texto('caminho', 500);
                    if ($caminho === '') {
                        $erros[] = 'Informe o caminho (pasta) do arquivo a restaurar.';
                    } else {
                        $linhas['Caminho do arquivo'] = $caminho;
                    }
                    $de  = self::dataHora($in['versao_de'] ?? '');
                    $ate = self::dataHora($in['versao_ate'] ?? '');
                    if ($de !== '' || $ate !== '') {
                        $linhas['Versão desejada'] = trim(($de !== '' ? 'de ' . $de : '') . ($ate !== '' ? ' até ' . $ate : ''));
                    }
                    break;

                case 'colaboradores':
                    // Cargo de quem abre: vem do cadastro do usuário (campo "Título")
                    $meuCargo = self::meuCargo();
                    if ($meuCargo !== '') {
                        $linhas['Cargo do solicitante'] = $meuCargo;
                    }
                    $blocos .= self::colaboradores($in, $erros);
                    break;

                case 'itens':
                    $itens = $texto('itens', 4000);
                    if ($itens === '') {
                        $erros[] = 'Informe os itens e as quantidades.';
                    } else {
                        $linhas['Itens e quantidades'] = $itens;
                    }
                    break;

                case 'local':
                    $local = self::daLista($texto('local'), Settings::lista('locais'));
                    if ($local === '') {
                        $erros[] = 'Escolha o local / unidade.';
                    } else {
                        $linhas['Local / unidade'] = $local;
                    }
                    if (($onde = $texto('local_detalhe')) !== '') {
                        $linhas['Onde exatamente'] = $onde;
                    }
                    break;

                case 'veiculo':
                    $veiculo = $texto('veiculo', 100);
                    if ($veiculo === '') {
                        $erros[] = 'Informe o veículo (modelo e placa).';
                    } else {
                        $linhas['Veículo'] = $veiculo;
                    }
                    break;

                case 'agenda':
                    $quando  = self::dataHora($in['agenda_data'] ?? '');
                    $origem  = $texto('agenda_origem');
                    $destino = $texto('agenda_destino');
                    if ($quando === '' || $origem === '' || $destino === '') {
                        $erros[] = 'Informe data e horário, origem e destino.';
                    } else {
                        $linhas['Data e horário'] = $quando;
                        $linhas['Origem']         = $origem;
                        $linhas['Destino']        = $destino;
                    }
                    if (($pass = (int) ($in['agenda_passageiros'] ?? 0)) > 0) {
                        $linhas['Passageiros'] = (string) min($pass, 99);
                    }
                    break;

                case 'evento':
                    $quando = self::dataHora($in['evento_data'] ?? '');
                    $pessoas = (int) ($in['evento_participantes'] ?? 0);
                    if ($quando === '' || $pessoas <= 0) {
                        $erros[] = 'Informe a data e o horário do evento e a quantidade de participantes.';
                    } else {
                        $linhas['Data e horário do evento'] = $quando;
                        $linhas['Participantes']            = (string) min($pessoas, 9999);
                    }
                    $suporte = array_values(array_intersect((array) ($in['evento_suporte'] ?? []), Settings::lista('suporte_evento')));
                    if ($suporte !== []) {
                        $linhas['Suporte necessário'] = implode(', ', $suporte);
                    }
                    if (($itensEvento = $texto('evento_itens', 4000)) !== '') {
                        $linhas['Itens e quantidades'] = $itensEvento;
                    }
                    break;

                case 'evento_interno':
                    $locais = array_values(array_intersect((array) ($in['evento_locais'] ?? []), Settings::lista('locais_evento')));
                    if ($locais === []) {
                        $erros[] = 'Marque onde será o evento.';
                    } else {
                        $linhas['Local do evento'] = implode(', ', $locais);
                    }
                    break;

                case 'procedimento':
                    $blocos .= self::procedimento($in, $erros, $linhas);
                    break;

                case 'evento_externo':
                    $empresa = $texto('evento_empresa');
                    $onde    = $texto('evento_localizacao');
                    if ($empresa === '' || $onde === '') {
                        $erros[] = 'Informe a empresa e a localização do evento externo.';
                    } else {
                        $linhas['Empresa']     = $empresa;
                        $linhas['Localização'] = $onde;
                    }
                    break;
            }
        }

        self::camposProprios($no['campos'] ?? [], (array) ($in['campo'] ?? []), $erros, $linhas);

        $anexos = self::validarAnexos($arquivos, $erros);
        if ($erros !== []) {
            return ['ok' => false, 'msg' => implode(' ', $erros)];
        }

        $users_id = (int) Session::getLoginUserID();
        $grupo    = (int) $tela->fields['groups_id'];

        $cabecalho = [
            'Setor'      => (string) $tela->fields['nome'],
            // Só o último nível (ex.: "Inclusão de Procedimento"); o caminho todo fica na categoria do chamado
            'Pedido'     => $no['nome'],
            'Prioridade' => Prazos::PRIORIDADES[$urgencia][0],
        ] + $linhas;

        // Prefixo do título e tipo do chamado, conforme as opções da tela
        $prefixo = $opcoes['titulo_prefixo'];
        if ($prefixo !== '' && !str_starts_with($titulo, $prefixo)) {
            $titulo = mb_substr($prefixo . ' ' . $titulo, 0, 255);
        }
        $tipoChamado = match ($opcoes['tipo']) {
            'incidente'  => Ticket::INCIDENT_TYPE,
            'requisicao' => Ticket::DEMAND_TYPE,
            default      => $no['incidente'] ? Ticket::INCIDENT_TYPE : Ticket::DEMAND_TYPE,
        };

        $ticket = new Ticket();
        $tickets_id = (int) $ticket->add([
            'name'              => $titulo,
            'content'           => self::descricao($cabecalho, $descricao, $blocos),
            'type'              => $tipoChamado,
            'urgency'           => $urgencia,
            'itilcategories_id' => $categoria,
            'entities_id'       => (int) ($_SESSION['glpiactive_entity'] ?? 0),
            '_actors'           => [
                'requester' => [[
                    'itemtype'          => 'User',
                    'items_id'          => $users_id,
                    'use_notification'  => 1,
                    'alternative_email' => '',
                ]],
                'assign'    => $grupo > 0 ? [['itemtype' => 'Group', 'items_id' => $grupo]] : [],
            ],
        ]);
        if ($tickets_id <= 0) {
            return ['ok' => false, 'msg' => 'Não foi possível abrir o chamado. Tente novamente.'];
        }

        // Quem não tem direito de atribuir não consegue atribuir na abertura: garante o grupo do setor
        if ($grupo > 0 && countElementsInTable(Group_Ticket::getTable(), [
            'tickets_id' => $tickets_id, 'groups_id' => $grupo, 'type' => CommonITILActor::ASSIGN,
        ]) === 0) {
            (new Group_Ticket())->add(['tickets_id' => $tickets_id, 'groups_id' => $grupo, 'type' => CommonITILActor::ASSIGN]);
        }

        $falhas = self::anexar($anexos, $tickets_id, (int) $ticket->fields['entities_id']);

        $ticket->getFromDB($tickets_id);
        $msg = sprintf('Chamado nº %d aberto.', $tickets_id);
        if (!empty($ticket->fields['time_to_resolve'])) {
            $msg .= ' Prazo de solução: ' . date('d/m/Y \à\s H:i', strtotime($ticket->fields['time_to_resolve'])) . '.';
        }
        if ($falhas > 0) {
            $msg .= sprintf(' Atenção: %d anexo(s) não puderam ser salvos; anexe pelo chamado.', $falhas);
        }
        if ($opcoes['msg_sucesso'] !== '') {
            $msg .= ' ' . $opcoes['msg_sucesso'];
        }
        return ['ok' => true, 'msg' => $msg, 'tickets_id' => $tickets_id];
    }

    /**
     * Chamados do usuário abertos nas telas dos setores, com prazos.
     */
    public static function meus(): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        // Categoria → [setor, rótulo] de todas as telas ativas
        $mapa = [];
        foreach (Tela::ativas() as $t) {
            $tela = new Tela();
            $tela->getFromDB((int) $t['id']);
            foreach (self::arvore($tela)['nos'] as $id => $no) {
                $mapa[$id] ??= [(string) $t['nome'], $no['rotulo']];
            }
        }
        $ids = array_keys($mapa);
        if ($ids === []) {
            return [];
        }

        $agora     = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $fechados  = array_merge(Ticket::getSolvedStatusArray(), Ticket::getClosedStatusArray());
        $lista     = [];
        foreach ($DB->request([
            'SELECT'     => ['t.id', 't.name', 't.date', 't.status', 't.urgency', 't.itilcategories_id', 't.time_to_resolve', 't.solvedate'],
            'FROM'       => 'glpi_tickets AS t',
            'INNER JOIN' => ['glpi_tickets_users AS tu' => ['ON' => ['tu' => 'tickets_id', 't' => 'id']]],
            'WHERE'      => [
                'tu.users_id'         => (int) Session::getLoginUserID(),
                'tu.type'             => CommonITILActor::REQUESTER,
                't.is_deleted'        => 0,
                't.itilcategories_id' => $ids,
            ],
            'ORDER'      => 't.date DESC',
            'LIMIT'      => 100,
        ]) as $t) {
            $status    = (int) $t['status'];
            $concluido = in_array($status, $fechados, true);
            $prazo     = $t['time_to_resolve'];
            $situacaoPrazo = null;
            if ($prazo) {
                $fim = $concluido ? ($t['solvedate'] ?: $agora) : $agora;
                $situacaoPrazo = $fim > $prazo ? 'atrasado' : 'no prazo';
            }
            $lista[] = [
                'id'         => (int) $t['id'],
                'titulo'     => (string) $t['name'],
                'data'       => date('d/m/Y H:i', strtotime((string) $t['date'])),
                'setor'      => $mapa[(int) $t['itilcategories_id']][0] ?? '',
                'pedido'     => $mapa[(int) $t['itilcategories_id']][1] ?? '',
                'prioridade' => Prazos::PRIORIDADES[(int) $t['urgency']] ?? ['—', '', 'secondary'],
                'situacao'   => Ticket::getStatus($status),
                'concluido'  => $concluido,
                'prazo'      => $prazo ? date('d/m/Y H:i', strtotime((string) $prazo)) : '',
                'no_prazo'   => $situacaoPrazo,
            ];
        }
        return $lista;
    }

    private static function colaboradores(array $in, array &$erros): string
    {
        $nomes    = (array) ($in['col_nome'] ?? []);
        $linhas   = '';
        $total    = 0;
        $cargos   = self::cargos();
        $setores  = self::setores();
        $campo  = static fn (string $nome, $i): string => mb_substr(trim((string) ($in[$nome][$i] ?? '')), 0, 255);
        foreach (array_slice($nomes, 0, self::MAX_COLABORADORES, true) as $i => $nome) {
            $nome   = mb_substr(trim((string) $nome), 0, 255);
            $cargo  = $campo('col_cargo', $i);
            $cpf    = preg_replace('/\D/', '', (string) ($in['col_cpf'][$i] ?? ''));
            $nasc   = (string) ($in['col_nasc'][$i] ?? '');
            $tipo   = (string) ($in['col_tipo'][$i] ?? '');
            $setor  = (int) ($in['col_setor'][$i] ?? 0);
            $perfil = $campo('col_perfil', $i);
            if ($nome === '' && $cargo === '' && $cpf === '' && $nasc === '' && $tipo === '' && $perfil === '') {
                continue;
            }
            $total++;
            // Cargo da lista; "copiar acesso de" só entre as pessoas do setor de destino
            $cargo  = $cargo !== '' ? self::daLista($cargo, $cargos) : '';
            $nomeSetor = $setores['setores'][$setor] ?? '';
            $perfil = $perfil !== '' && $nomeSetor !== '' ? self::daLista($perfil, $setores['pessoas'][$setor] ?? []) : '';
            $data = \DateTime::createFromFormat('Y-m-d', $nasc);
            if ($nome === '' || $cargo === '' || strlen($cpf) !== 6 || !$data || !isset(self::TIPOS_COLABORADOR[$tipo]) || $nomeSetor === '' || $perfil === '') {
                $erros[] = sprintf('Colaborador %d: preencha nome completo, cargo (da lista), os 6 primeiros dígitos do CPF, a data de nascimento, se é novo ou remanejado, o setor e de quem copiar o acesso (alguém do setor).', $total);
                continue;
            }
            $linhas .= sprintf(
                '<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $total,
                htmlescape($nome),
                htmlescape($cargo),
                htmlescape(self::TIPOS_COLABORADOR[$tipo]),
                htmlescape($nomeSetor),
                htmlescape($cpf),
                htmlescape($data->format('d/m/Y')),
                htmlescape($perfil)
            );
        }
        if ($total === 0) {
            $erros[] = 'Informe pelo menos um colaborador para criar o acesso.';
            return '';
        }
        return '<p><strong>Colaboradores</strong></p><table border="1" cellpadding="4"><tr><th>#</th><th>Nome completo</th><th>Cargo</th><th>Situação</th><th>Setor</th><th>CPF (6 primeiros)</th><th>Nascimento</th><th>Copiar acesso de</th></tr>'
            . $linhas . '</table>';
    }

    /** Respostas Sim/Não do procedimento (FacPlan) */
    private const SIM_NAO = ['sim' => 'Sim', 'nao' => 'Não'];

    /** Período da quantidade máxima do procedimento (FacPlan) */
    public const PERIODOS_PROCEDIMENTO = ['Por dia', 'Por semana', 'Por mês', 'Por trimestre', 'Por semestre', 'Por ano'];

    /**
     * Procedimento (FacPlan › Cadastro): na inserção pede todos os dados; na alteração,
     * só o código e a tabela do código. Os dados vão para o cabeçalho do chamado.
     */
    private static function procedimento(array $in, array &$erros, array &$linhas): string
    {
        $texto = static fn (string $campo, int $max = 255): string => mb_substr(trim((string) ($in[$campo] ?? '')), 0, $max);
        $operacao = (string) ($in['proc_operacao'] ?? '');
        if (!in_array($operacao, ['insercao', 'alteracao'], true)) {
            $erros[] = 'Escolha se é inserção ou alteração do procedimento.';
            return '';
        }
        $codigo = $texto('proc_codigo', 50);
        $tabela = $texto('proc_tabela', 100);
        if ($codigo === '' || $tabela === '') {
            $erros[] = 'Informe o código e a tabela do código do procedimento.';
            return '';
        }
        $linhas['Operação']         = $operacao === 'insercao' ? 'Inserção' : 'Alteração';
        $linhas['Código']           = $codigo;
        $linhas['Tabela do código'] = $tabela;
        if ($operacao === 'alteracao') {
            return '';
        }

        $descricao = $texto('proc_descricao');
        $porte     = $texto('proc_porte', 100);
        $sexo      = (string) ($in['proc_sexo'] ?? '');
        $idadeIni  = (string) ($in['proc_idade_ini'] ?? '');
        $idadeFim  = (string) ($in['proc_idade_fim'] ?? '');
        $tipo      = (string) ($in['proc_tipo'] ?? '');
        $sexos     = ['ambos' => 'Ambos', 'masculino' => 'Masculino', 'feminino' => 'Feminino'];
        $tipos     = ['consulta' => 'Consulta', 'exame' => 'Exame'];
        $idadeOk   = static fn (string $v): bool => $v !== '' && ctype_digit($v) && (int) $v <= 150;
        $simNao    = [];
        foreach ([
            'proc_pendencia'   => 'Cai em pendência',
            'proc_urgencia'    => 'Urgência e Emergência',
            'proc_dut'         => 'Tem DUT',
            'proc_limite'      => 'Tem limite',
            'proc_qtd'         => 'Tem quantidade máxima',
            'proc_dependencia' => 'Tem dependência proibitiva',
        ] as $campo => $rotulo) {
            $v = (string) ($in[$campo] ?? '');
            if (!isset(self::SIM_NAO[$v])) {
                $erros[] = 'Responda: ' . $rotulo . '?';
                return '';
            }
            $simNao[$rotulo] = self::SIM_NAO[$v];
        }
        if ($descricao === '' || $porte === '' || !isset($sexos[$sexo]) || !isset($tipos[$tipo]) || !$idadeOk($idadeIni) || !$idadeOk($idadeFim)) {
            $erros[] = 'Na inserção do procedimento, preencha descrição, porte hierárquico, sexo, idade inicial e final e o tipo (consulta ou exame).';
            return '';
        }
        if ((int) $idadeIni > (int) $idadeFim) {
            $erros[] = 'A idade inicial do procedimento não pode ser maior que a final.';
            return '';
        }
        $dutQual = $texto('proc_dut_qual');
        if ($simNao['Tem DUT'] === 'Sim' && $dutQual === '') {
            $erros[] = 'Informe qual é a DUT do procedimento.';
            return '';
        }
        $carencia = $texto('proc_carencia', 100);
        if ($carencia === '') {
            $erros[] = 'Informe o grupo de carência do procedimento.';
            return '';
        }
        $limiteGrupo = $texto('proc_limite_grupo', 100);
        if ($simNao['Tem limite'] === 'Sim' && $limiteGrupo === '') {
            $erros[] = 'Informe qual é o grupo de limite do procedimento.';
            return '';
        }
        $qtdMax     = (string) ($in['proc_qtd_max'] ?? '');
        $qtdPeriodo = (string) ($in['proc_qtd_periodo'] ?? '');
        if ($simNao['Tem quantidade máxima'] === 'Sim'
            && (!ctype_digit($qtdMax) || (int) $qtdMax < 1 || !in_array($qtdPeriodo, self::PERIODOS_PROCEDIMENTO, true))) {
            $erros[] = 'Informe a quantidade máxima do procedimento e em que período.';
            return '';
        }
        $dependenciaQual = $texto('proc_dependencia_qual');
        if ($simNao['Tem dependência proibitiva'] === 'Sim' && $dependenciaQual === '') {
            $erros[] = 'Informe de qual procedimento ele depende.';
            return '';
        }
        $linhas['Descrição do procedimento'] = $descricao;
        $linhas['Porte hierárquico']         = $porte;
        $linhas['Sexo']                      = $sexos[$sexo];
        $linhas['Idade']                     = $idadeIni . ' a ' . $idadeFim . ' anos';
        $linhas['Cai em pendência']      = $simNao['Cai em pendência'];
        $linhas['Urgência e Emergência'] = $simNao['Urgência e Emergência'];
        $linhas['Tem DUT']               = $simNao['Tem DUT'] === 'Sim' ? 'Sim — ' . $dutQual : 'Não';
        $linhas['Grupo de carência']     = $carencia;
        $linhas['Limite']                = $simNao['Tem limite'] === 'Sim' ? 'Sim — grupo ' . $limiteGrupo : 'Não';
        $linhas['Quantidade máxima']     = $simNao['Tem quantidade máxima'] === 'Sim'
            ? (int) $qtdMax . ' ' . mb_strtolower($qtdPeriodo)
            : 'Não';
        $linhas['Dependência proibitiva'] = $simNao['Tem dependência proibitiva'] === 'Sim' ? 'Sim — ' . $dependenciaQual : 'Não';
        $linhas['Tipo']                  = $tipos[$tipo];
        return '';
    }

    /**
     * Valor exatamente como está na lista (sem diferenciar maiúsculas), ou '' se não estiver.
     * Lista vazia (nada cadastrado ainda): aceita o que foi digitado.
     */
    /**
     * Campos criados pelo administrador no pedido: valida e acrescenta aos dados do chamado.
     *
     * @param array<int, array> $campos Definição dos campos (ver Tela::normalizarCampos)
     * @param array             $enviado Valores enviados em campo[id]
     */
    private static function camposProprios(array $campos, array $enviado, array &$erros, array &$linhas): void
    {
        foreach ($campos as $campo) {
            $bruto  = $enviado[$campo['id']] ?? null;
            $rotulo = (string) $campo['rotulo'];
            $texto  = is_scalar($bruto) ? trim((string) $bruto) : '';
            $valor  = '';

            switch ($campo['tipo']) {
                case 'texto_longo':
                    $valor = mb_substr($texto, 0, 4000);
                    break;

                case 'numero':
                    $n = str_replace(',', '.', $texto);
                    if ($n !== '' && !is_numeric($n)) {
                        $erros[] = sprintf('O campo "%s" precisa ser um número.', $rotulo);
                    } else {
                        $valor = $texto;
                    }
                    break;

                case 'data':
                    if ($texto !== '') {
                        $d = \DateTime::createFromFormat('Y-m-d', $texto);
                        if ($d && $d->format('Y-m-d') === $texto) {
                            $valor = $d->format('d/m/Y');
                        } else {
                            $erros[] = sprintf('A data do campo "%s" não é válida.', $rotulo);
                        }
                    }
                    break;

                case 'data_hora':
                    $valor = self::dataHora($bruto);
                    if ($texto !== '' && $valor === '') {
                        $erros[] = sprintf('A data e hora do campo "%s" não é válida.', $rotulo);
                    }
                    break;

                case 'lista':
                    if ($texto !== '' && !in_array($texto, $campo['opcoes'], true)) {
                        $erros[] = sprintf('Escolha uma opção válida em "%s".', $rotulo);
                    } else {
                        $valor = $texto;
                    }
                    break;

                case 'multipla':
                    $valor = implode(', ', array_values(array_intersect((array) $bruto, $campo['opcoes'])));
                    break;

                case 'sim_nao':
                    $valor = $texto === 'sim' ? 'Sim' : ($texto === 'nao' ? 'Não' : '');
                    break;

                case 'email':
                    if ($texto !== '' && !filter_var($texto, FILTER_VALIDATE_EMAIL)) {
                        $erros[] = sprintf('O e-mail do campo "%s" não é válido.', $rotulo);
                    } else {
                        $valor = mb_substr($texto, 0, 255);
                    }
                    break;

                case 'telefone':
                    $valor = mb_substr(preg_replace('/[^0-9 ()+.\-]/', '', $texto), 0, 30);
                    break;

                default:
                    $valor = mb_substr($texto, 0, 255);
            }

            if ($valor === '' && !empty($campo['obrigatorio'])) {
                $erros[] = sprintf('Preencha o campo "%s".', $rotulo);
            }
            if ($valor !== '') {
                $chave = $rotulo;
                for ($i = 2; isset($linhas[$chave]); $i++) {
                    $chave = $rotulo . ' (' . $i . ')';
                }
                $linhas[$chave] = $valor;
            }
        }
    }

    private static function daLista(string $valor, array $lista): string
    {
        if ($lista === []) {
            return $valor;
        }
        foreach ($lista as $item) {
            if (mb_strtolower($item) === mb_strtolower($valor)) {
                return $item;
            }
        }
        return '';
    }

    private static function dataHora(mixed $valor): string
    {
        $data = \DateTime::createFromFormat('Y-m-d\TH:i', (string) $valor);
        return $data ? $data->format('d/m/Y H:i') : '';
    }

    private static function descricao(array $cabecalho, string $descricao, string $blocos): string
    {
        $html = '<table>';
        foreach ($cabecalho as $rotulo => $valor) {
            $html .= sprintf('<tr><td><strong>%s</strong></td><td>%s</td></tr>', htmlescape($rotulo), nl2br(htmlescape($valor)));
        }
        $html .= '</table>';
        // Descrição é opcional: sem texto, o chamado fica só com o cabeçalho
        if (trim($descricao) !== '') {
            $html .= '<p><strong>Descrição</strong></p><p>' . nl2br(htmlescape($descricao)) . '</p>';
        }
        return $html . $blocos;
    }

    /**
     * @return array<int, array{nome:string, tmp:string}>
     */
    private static function validarAnexos(array $arquivos, array &$erros): array
    {
        $lista = [];
        $nomes = (array) ($arquivos['name'] ?? []);
        foreach ($nomes as $i => $nome) {
            if ((int) ($arquivos['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || $nome === '') {
                continue;
            }
            if ((int) $arquivos['error'][$i] !== UPLOAD_ERR_OK || !is_uploaded_file($arquivos['tmp_name'][$i])) {
                $erros[] = sprintf('Não foi possível receber o arquivo "%s".', $nome);
                continue;
            }
            $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
            if (!in_array($ext, self::EXTENSOES, true)) {
                $erros[] = sprintf('O arquivo "%s" não é aceito (use imagem, PDF, texto, Excel ou Word).', $nome);
                continue;
            }
            if ((int) $arquivos['size'][$i] > self::MAX_MB * 1024 * 1024) {
                $erros[] = sprintf('O arquivo "%s" passa de %d MB.', $nome, self::MAX_MB);
                continue;
            }
            $lista[] = ['nome' => $nome, 'tmp' => $arquivos['tmp_name'][$i]];
        }
        if (count($lista) > self::MAX_ANEXOS) {
            $erros[] = sprintf('Envie no máximo %d anexos.', self::MAX_ANEXOS);
        }
        return $lista;
    }

    /**
     * Anexa os arquivos ao chamado. Devolve quantos falharam.
     */
    private static function anexar(array $anexos, int $tickets_id, int $entities_id): int
    {
        $falhas = 0;
        foreach ($anexos as $anexo) {
            try {
                // O GLPI move o arquivo a partir da pasta temporária dele
                $seguro  = preg_replace('/[^A-Za-z0-9._-]+/', '_', $anexo['nome']);
                $prefixo = uniqid('', true) . '_';
                if (!move_uploaded_file($anexo['tmp'], GLPI_TMP_DIR . '/' . $prefixo . $seguro)) {
                    $falhas++;
                    continue;
                }
                $ok = (new Document())->add([
                    'name'             => $anexo['nome'],
                    'entities_id'      => $entities_id,
                    'is_recursive'     => 0,
                    'itemtype'         => Ticket::class,
                    'items_id'         => $tickets_id,
                    '_filename'        => [$prefixo . $seguro],
                    '_prefix_filename' => [$prefixo],
                ]);
                if (!$ok) {
                    $falhas++;
                }
            } catch (Throwable $e) {
                $falhas++;
            }
        }
        return $falhas;
    }

    /**
     * Cabeçalho da página nas duas interfaces.
     */
    public static function cabecalho(string $titulo): void
    {
        if (Session::getCurrentInterface() === 'helpdesk') {
            Html::helpHeader($titulo);
        } else {
            Html::header($titulo, '', 'helpdesk', self::class);
        }
    }

    public static function rodape(): void
    {
        if (Session::getCurrentInterface() === 'helpdesk') {
            Html::helpFooter();
        } else {
            Html::footer();
        }
    }
}
