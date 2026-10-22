<?php

namespace GlpiPlugin\Relatorios;

use Calendar;
use CommonITILActor;
use ITILCategory;
use Ticket;

/**
 * Carrega os chamados conforme os filtros e calcula indicadores, SLA e rankings.
 *
 * Tempos de resposta e de solução são contados em horário comercial
 * (mesmo calendário dos SLAs), para comparar técnicos e setores de forma justa.
 */
class Dados
{
    public const PRIORIDADES = [6 => 'Crítica', 5 => 'Muito alta', 4 => 'Alta', 3 => 'Média', 2 => 'Baixa', 1 => 'Muito baixa'];

    public const TIPOS = [Ticket::INCIDENT_TYPE => 'Incidente', Ticket::DEMAND_TYPE => 'Requisição'];

    public const SITUACOES = ['' => 'Todas', 'abertos' => 'Em aberto', 'solucionados' => 'Solucionados/fechados'];

    public const DIAS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    /** Calendário usado para contar o tempo em horário comercial */
    private static ?Calendar $calendario = null;

    private static bool $calendarioCarregado = false;

    /**
     * Filtros vindos do formulário, já validados.
     */
    public static function filtros(array $in): array
    {
        $data = static function ($valor, string $padrao): string {
            $d = \DateTime::createFromFormat('Y-m-d', (string) $valor);
            return $d ? $d->format('Y-m-d') : $padrao;
        };
        $f = [
            'de'           => $data($in['de'] ?? '', date('Y-m-01')),
            'ate'          => $data($in['ate'] ?? '', date('Y-m-d')),
            'categoria'    => max(0, (int) ($in['categoria'] ?? 0)),
            'setor'        => max(0, (int) ($in['setor'] ?? 0)),
            'tecnico'      => max(0, (int) ($in['tecnico'] ?? 0)),
            'grupo'        => max(0, (int) ($in['grupo'] ?? 0)),
            'prioridade'   => isset(self::PRIORIDADES[(int) ($in['prioridade'] ?? 0)]) ? (int) $in['prioridade'] : 0,
            'tipo'         => isset(self::TIPOS[(int) ($in['tipo'] ?? 0)]) ? (int) $in['tipo'] : 0,
            'situacao'     => array_key_exists($situacao = (string) ($in['situacao'] ?? ''), self::SITUACOES) ? $situacao : '',
        ];
        if ($f['de'] > $f['ate']) {
            [$f['de'], $f['ate']] = [$f['ate'], $f['de']];
        }
        return $f;
    }

    /**
     * Opções dos filtros (categorias, grupos, técnicos).
     */
    public static function opcoes(): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $categorias = [];
        foreach ($DB->request(['SELECT' => ['id', 'completename'], 'FROM' => ITILCategory::getTable(), 'WHERE' => ['level' => [1, 2]], 'ORDER' => 'completename']) as $c) {
            $categorias[(int) $c['id']] = (string) $c['completename'];
        }
        $grupos = [];
        foreach ($DB->request(['SELECT' => ['id', 'completename'], 'FROM' => 'glpi_groups', 'ORDER' => 'completename']) as $g) {
            $grupos[(int) $g['id']] = (string) $g['completename'];
        }
        // Técnicos: quem já foi atribuído a algum chamado
        $tecnicos = [];
        foreach ($DB->request([
            'SELECT'     => ['u.id', 'u.name', 'u.firstname', 'u.realname'],
            'DISTINCT'   => true,
            'FROM'       => 'glpi_tickets_users AS tu',
            'INNER JOIN' => ['glpi_users AS u' => ['ON' => ['tu' => 'users_id', 'u' => 'id']]],
            'WHERE'      => ['tu.type' => CommonITILActor::ASSIGN],
        ]) as $u) {
            $tecnicos[(int) $u['id']] = self::nomeUsuario($u);
        }
        asort($tecnicos, SORT_NATURAL | SORT_FLAG_CASE);
        return ['categorias' => $categorias, 'grupos' => $grupos, 'tecnicos' => $tecnicos];
    }

    /**
     * Chamados do período com tudo o que os relatórios usam.
     *
     * @return array<int, array>
     */
    public static function chamados(array $f): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $where = [
            't.is_deleted' => 0,
            ['t.date' => ['>=', $f['de'] . ' 00:00:00']],
            ['t.date' => ['<=', $f['ate'] . ' 23:59:59']],
        ] + getEntitiesRestrictCriteria('t', '', '', true);
        if ($f['categoria'] > 0) {
            $where['t.itilcategories_id'] = array_map('intval', array_keys(getSonsOf(ITILCategory::getTable(), $f['categoria'])));
        }
        if ($f['prioridade'] > 0) {
            $where['t.priority'] = $f['prioridade'];
        }
        if ($f['tipo'] > 0) {
            $where['t.type'] = $f['tipo'];
        }
        $fechados = array_merge(Ticket::getSolvedStatusArray(), Ticket::getClosedStatusArray());
        if ($f['situacao'] === 'abertos') {
            $where[] = ['NOT' => ['t.status' => $fechados]];
        } elseif ($f['situacao'] === 'solucionados') {
            $where['t.status'] = $fechados;
        }

        $linhas = iterator_to_array($DB->request([
            'SELECT'    => [
                't.id', 't.name', 't.date', 't.type', 't.status', 't.urgency', 't.priority', 't.itilcategories_id',
                't.requesttypes_id', 't.takeintoaccountdate', 't.solvedate', 't.closedate', 't.time_to_own', 't.time_to_resolve',
                'c.completename AS categoria', 'rt.name AS origem', 's.satisfaction',
            ],
            'FROM'      => 'glpi_tickets AS t',
            'LEFT JOIN' => [
                'glpi_itilcategories AS c'     => ['ON' => ['c' => 'id', 't' => 'itilcategories_id']],
                'glpi_requesttypes AS rt'      => ['ON' => ['rt' => 'id', 't' => 'requesttypes_id']],
                'glpi_ticketsatisfactions AS s' => ['ON' => ['s' => 'tickets_id', 't' => 'id']],
            ],
            'WHERE'     => $where,
            'ORDER'     => 't.date',
        ]), false);
        if ($linhas === []) {
            return [];
        }

        $ids     = array_map(fn ($l) => (int) $l['id'], $linhas);
        $atores  = self::atores($ids);
        $agora   = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $chamados = [];
        foreach ($linhas as $l) {
            $id     = (int) $l['id'];
            $a      = $atores[$id] ?? ['requerentes' => [], 'tecnicos' => [], 'setores' => [], 'grupos' => [], 'requerentes_ids' => [], 'tecnicos_ids' => [], 'setores_ids' => [], 'grupos_ids' => []];
            $aberto = !in_array((int) $l['status'], $fechados, true);

            // Primeira resposta: quando a TI assumiu (ou, se não houve, quando solucionou)
            $resposta = $l['takeintoaccountdate'] ?: $l['solvedate'];

            $chamado = [
                'id'               => $id,
                'titulo'           => (string) $l['name'],
                'abertura'         => (string) $l['date'],
                'tipo'             => self::TIPOS[(int) $l['type']] ?? '',
                'status_id'        => (int) $l['status'],
                'status'           => Ticket::getStatus((int) $l['status']),
                'aberto'           => $aberto,
                'prioridade_id'    => (int) $l['priority'],
                'prioridade'       => self::PRIORIDADES[(int) $l['priority']] ?? '',
                'urgencia'         => self::PRIORIDADES[(int) $l['urgency']] ?? '',
                'categoria'        => (string) ($l['categoria'] ?? '') ?: 'Sem categoria',
                'origem'           => (string) ($l['origem'] ?? '') ?: '—',
                'requerente'       => $a['requerentes'][0] ?? 'Sem requerente',
                'requerente_id'    => $a['requerentes_ids'][0] ?? 0,
                'setor'            => $a['setores'][0] ?? 'Sem setor',
                'setor_id'         => $a['setores_ids'][0] ?? 0,
                'tecnicos'         => $a['tecnicos'] ?: ['Sem técnico'],
                'tecnicos_ids'     => $a['tecnicos_ids'],
                'grupos'           => $a['grupos'] ?: ['Sem grupo'],
                'grupos_ids'       => $a['grupos_ids'],
                'primeira_resposta' => (string) ($resposta ?? ''),
                'solucao'          => (string) ($l['solvedate'] ?? ''),
                'fechamento'       => (string) ($l['closedate'] ?? ''),
                'prazo_resposta'   => (string) ($l['time_to_own'] ?? ''),
                'prazo_solucao'    => (string) ($l['time_to_resolve'] ?? ''),
                'sla_resposta'     => self::situacaoSla($l['time_to_own'], $resposta, $agora),
                'sla_solucao'      => self::situacaoSla($l['time_to_resolve'], $l['solvedate'], $agora),
                'tempo_resposta'   => $resposta ? self::tempoUtil((string) $l['date'], (string) $resposta) : null,
                'tempo_solucao'    => $l['solvedate'] ? self::tempoUtil((string) $l['date'], (string) $l['solvedate']) : null,
                'satisfacao'       => $l['satisfaction'] !== null ? (int) $l['satisfaction'] : null,
            ];

            // Filtros que dependem dos atores
            if ($f['setor'] > 0 && !in_array($f['setor'], $a['setores_ids'], true)) {
                continue;
            }
            if ($f['tecnico'] > 0 && !in_array($f['tecnico'], $a['tecnicos_ids'], true)) {
                continue;
            }
            if ($f['grupo'] > 0 && !in_array($f['grupo'], $a['grupos_ids'], true)) {
                continue;
            }
            $chamados[] = $chamado;
        }
        return $chamados;
    }

    /**
     * Requerentes, técnicos, setor (grupo requerente) e grupo atribuído de cada chamado.
     */
    private static function atores(array $ids): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $atores  = [];
        $vazio   = ['requerentes' => [], 'tecnicos' => [], 'setores' => [], 'grupos' => [], 'requerentes_ids' => [], 'tecnicos_ids' => [], 'setores_ids' => [], 'grupos_ids' => []];
        $usuariosRequerentes = [];
        foreach (array_chunk($ids, 1000) as $lote) {
            foreach ($DB->request([
                'SELECT'     => ['tu.tickets_id', 'tu.type', 'u.id', 'u.name', 'u.firstname', 'u.realname'],
                'FROM'       => 'glpi_tickets_users AS tu',
                'INNER JOIN' => ['glpi_users AS u' => ['ON' => ['tu' => 'users_id', 'u' => 'id']]],
                'WHERE'      => ['tu.tickets_id' => $lote, 'tu.type' => [CommonITILActor::REQUESTER, CommonITILActor::ASSIGN]],
                'ORDER'      => 'tu.id',
            ]) as $r) {
                $t = (int) $r['tickets_id'];
                $atores[$t] ??= $vazio;
                $chave = (int) $r['type'] === CommonITILActor::REQUESTER ? 'requerentes' : 'tecnicos';
                if (!in_array((int) $r['id'], $atores[$t][$chave . '_ids'], true)) {
                    $atores[$t][$chave][]          = self::nomeUsuario($r);
                    $atores[$t][$chave . '_ids'][] = (int) $r['id'];
                }
                if ($chave === 'requerentes') {
                    $usuariosRequerentes[(int) $r['id']] = true;
                }
            }
            foreach ($DB->request([
                'SELECT'     => ['gt.tickets_id', 'gt.type', 'g.id', 'g.completename'],
                'FROM'       => 'glpi_groups_tickets AS gt',
                'INNER JOIN' => ['glpi_groups AS g' => ['ON' => ['gt' => 'groups_id', 'g' => 'id']]],
                'WHERE'      => ['gt.tickets_id' => $lote, 'gt.type' => [CommonITILActor::REQUESTER, CommonITILActor::ASSIGN]],
                'ORDER'      => 'gt.id',
            ]) as $r) {
                $t = (int) $r['tickets_id'];
                $atores[$t] ??= $vazio;
                $chave = (int) $r['type'] === CommonITILActor::REQUESTER ? 'setores' : 'grupos';
                if (!in_array((int) $r['id'], $atores[$t][$chave . '_ids'], true)) {
                    $atores[$t][$chave][]          = (string) $r['completename'];
                    $atores[$t][$chave . '_ids'][] = (int) $r['id'];
                }
            }
        }

        // Chamado sem grupo requerente: usa o setor (grupo) do requerente
        $gruposDoUsuario = [];
        if ($usuariosRequerentes !== []) {
            foreach ($DB->request([
                'SELECT'     => ['gu.users_id', 'g.id', 'g.completename'],
                'FROM'       => 'glpi_groups_users AS gu',
                'INNER JOIN' => ['glpi_groups AS g' => ['ON' => ['gu' => 'groups_id', 'g' => 'id']]],
                'WHERE'      => ['gu.users_id' => array_keys($usuariosRequerentes)],
                'ORDER'      => ['gu.is_dynamic DESC', 'g.completename'],
            ]) as $g) {
                $gruposDoUsuario[(int) $g['users_id']] ??= [(int) $g['id'], (string) $g['completename']];
            }
        }
        foreach ($atores as $t => $a) {
            if ($a['setores'] === [] && isset($a['requerentes_ids'][0], $gruposDoUsuario[$a['requerentes_ids'][0]])) {
                [$gid, $gnome] = $gruposDoUsuario[$a['requerentes_ids'][0]];
                $atores[$t]['setores']     = [$gnome];
                $atores[$t]['setores_ids'] = [$gid];
            }
        }
        return $atores;
    }

    private static function nomeUsuario(array $u): string
    {
        $nome = trim(($u['firstname'] ?? '') . ' ' . ($u['realname'] ?? ''));
        return $nome !== '' ? $nome : (string) $u['name'];
    }

    /**
     * 'no_prazo', 'atrasado', 'andamento' (ainda dentro do prazo) ou 'sem_sla'.
     */
    private static function situacaoSla(?string $prazo, ?string $feito, string $agora): string
    {
        if (!$prazo) {
            return 'sem_sla';
        }
        if ($feito) {
            return $feito <= $prazo ? 'no_prazo' : 'atrasado';
        }
        return $agora > $prazo ? 'atrasado' : 'andamento';
    }

    /**
     * Segundos em horário comercial entre duas datas (sem calendário: tempo corrido).
     */
    private static function tempoUtil(string $inicio, string $fim): int
    {
        if ($fim <= $inicio) {
            return 0;
        }
        if (!self::$calendarioCarregado) {
            self::$calendarioCarregado = true;
            $id  = (int) Settings::get('calendars_id');
            $cal = new Calendar();
            if ($id > 0 && $cal->getFromDB($id) && $cal->hasAWorkingDay()) {
                self::$calendario = $cal;
            }
        }
        if (self::$calendario !== null) {
            return (int) self::$calendario->getActiveTimeBetween($inicio, $fim);
        }
        return strtotime($fim) - strtotime($inicio);
    }

    /**
     * Indicadores, gráficos e rankings a partir dos chamados.
     */
    public static function analisar(array $chamados, array $f): array
    {
        $kpis = self::estatisticas($chamados);
        $kpis['abertos_atrasados'] = count(array_filter($chamados, fn ($c) => $c['aberto'] && $c['sla_solucao'] === 'atrasado'));

        // Evolução: por dia (até ~2 meses), por semana (até ~6 meses) ou por mês
        $dias  = (strtotime($f['ate']) - strtotime($f['de'])) / 86400;
        $passo = $dias <= 62 ? 'dia' : ($dias <= 190 ? 'semana' : 'mes');
        $chave = static fn (int $t): string => match ($passo) {
            'dia'    => date('Y-m-d', $t),
            'semana' => date('Y-m-d', strtotime('monday this week', $t)),
            default  => date('Y-m-01', $t),
        };
        $evolucao = [];
        $inicio   = strtotime($chave(strtotime($f['de'])));
        for ($d = $inicio; $d <= strtotime($f['ate']); $d = strtotime(['dia' => '+1 day', 'semana' => '+1 week', 'mes' => '+1 month'][$passo], $d)) {
            $evolucao[date('Y-m-d', $d)] = ['abertos' => 0, 'solucionados' => 0];
        }
        foreach ($chamados as $c) {
            $k = $chave(strtotime($c['abertura']));
            if (isset($evolucao[$k])) {
                $evolucao[$k]['abertos']++;
            }
            if ($c['solucao'] !== '') {
                $k = $chave(strtotime($c['solucao']));
                if (isset($evolucao[$k])) {
                    $evolucao[$k]['solucionados']++;
                }
            }
        }
        $rotulos = array_map(fn ($k) => $passo === 'mes' ? date('m/Y', strtotime($k)) : date('d/m', strtotime($k)), array_keys($evolucao));
        $porDia  = $passo === 'dia';

        // Mapa de calor: dia da semana × hora de abertura
        $calor = [];
        foreach ($chamados as $c) {
            $t = strtotime($c['abertura']);
            $chave = (int) date('w', $t) . '-' . (int) date('G', $t);
            $calor[$chave] = ($calor[$chave] ?? 0) + 1;
        }

        return [
            'kpis'         => $kpis,
            'evolucao'     => ['rotulos' => $rotulos, 'abertos' => array_column($evolucao, 'abertos'), 'solucionados' => array_column($evolucao, 'solucionados'), 'por_dia' => $porDia, 'passo' => $passo],
            'status'       => self::contar($chamados, fn ($c) => [$c['status']]),
            'tipos'        => self::contar($chamados, fn ($c) => [$c['tipo']]),
            'origens'      => self::contar($chamados, fn ($c) => [$c['origem']]),
            'prioridades'  => self::agrupar($chamados, fn ($c) => [$c['prioridade'] ?: 'Sem prioridade'], array_values(self::PRIORIDADES)),
            'setores'      => self::agrupar($chamados, fn ($c) => [$c['setor']]),
            'requerentes'  => self::agrupar($chamados, fn ($c) => [$c['requerente'] . "\t" . $c['setor']]),
            'tecnicos'     => self::agrupar($chamados, fn ($c) => $c['tecnicos']),
            'grupos'       => self::agrupar($chamados, fn ($c) => $c['grupos']),
            'categorias'   => self::agrupar($chamados, fn ($c) => [$c['categoria']]),
            'calor'        => $calor,
        ];
    }

    /**
     * Totais, SLA e tempos médios de um conjunto de chamados.
     */
    public static function estatisticas(array $chamados): array
    {
        $e = [
            'total' => count($chamados), 'abertos' => 0, 'solucionados' => 0, 'abertos_atrasados' => 0,
            'tto_ok' => 0, 'tto_atraso' => 0, 'ttr_ok' => 0, 'ttr_atraso' => 0,
            'resp_soma' => 0, 'resp_n' => 0, 'sol_soma' => 0, 'sol_n' => 0, 'sol_max' => 0, 'sat_soma' => 0, 'sat_n' => 0,
        ];
        foreach ($chamados as $c) {
            $c['aberto'] ? $e['abertos']++ : $e['solucionados']++;
            if ($c['aberto'] && $c['sla_solucao'] === 'atrasado') {
                $e['abertos_atrasados']++;
            }
            $e['tto_ok']     += $c['sla_resposta'] === 'no_prazo' ? 1 : 0;
            $e['tto_atraso'] += $c['sla_resposta'] === 'atrasado' ? 1 : 0;
            $e['ttr_ok']     += $c['sla_solucao'] === 'no_prazo' ? 1 : 0;
            $e['ttr_atraso'] += $c['sla_solucao'] === 'atrasado' ? 1 : 0;
            if ($c['tempo_resposta'] !== null) {
                $e['resp_soma'] += $c['tempo_resposta'];
                $e['resp_n']++;
            }
            if ($c['tempo_solucao'] !== null) {
                $e['sol_soma'] += $c['tempo_solucao'];
                $e['sol_n']++;
                $e['sol_max'] = max($e['sol_max'], $c['tempo_solucao']);
            }
            if ($c['satisfacao'] !== null) {
                $e['sat_soma'] += $c['satisfacao'];
                $e['sat_n']++;
            }
        }
        $e['sla_resposta'] = ($e['tto_ok'] + $e['tto_atraso']) > 0 ? round(100 * $e['tto_ok'] / ($e['tto_ok'] + $e['tto_atraso']), 1) : null;
        $e['sla_solucao']  = ($e['ttr_ok'] + $e['ttr_atraso']) > 0 ? round(100 * $e['ttr_ok'] / ($e['ttr_ok'] + $e['ttr_atraso']), 1) : null;
        $e['media_resposta'] = $e['resp_n'] ? (int) round($e['resp_soma'] / $e['resp_n']) : null;
        $e['media_solucao']  = $e['sol_n'] ? (int) round($e['sol_soma'] / $e['sol_n']) : null;
        $e['satisfacao']     = $e['sat_n'] ? round($e['sat_soma'] / $e['sat_n'], 1) : null;
        return $e;
    }

    /**
     * Estatísticas por grupo (setor, técnico, categoria…), do maior para o menor.
     *
     * @param callable(array): string[] $chave
     */
    private static function agrupar(array $chamados, callable $chave, array $ordem = []): array
    {
        $grupos = [];
        foreach ($chamados as $c) {
            foreach (array_unique($chave($c)) as $k) {
                $grupos[$k][] = $c;
            }
        }
        $lista = [];
        foreach ($grupos as $nome => $itens) {
            $lista[] = ['nome' => (string) $nome] + self::estatisticas($itens);
        }
        if ($ordem !== []) {
            $pos = array_flip($ordem);
            usort($lista, fn ($a, $b) => ($pos[$a['nome']] ?? 99) <=> ($pos[$b['nome']] ?? 99));
        } else {
            usort($lista, fn ($a, $b) => [$b['total'], $a['nome']] <=> [$a['total'], $b['nome']]);
        }
        return $lista;
    }

    private static function contar(array $chamados, callable $chave): array
    {
        $contagem = [];
        foreach ($chamados as $c) {
            foreach ($chave($c) as $k) {
                $contagem[$k] = ($contagem[$k] ?? 0) + 1;
            }
        }
        arsort($contagem);
        return $contagem;
    }

    /**
     * "27 h 05 min", "12 min" (horas de horário comercial: não converte em dias,
     * que confundiria com dias corridos).
     */
    public static function duracao(?int $segundos): string
    {
        if ($segundos === null) {
            return '—';
        }
        $min = intdiv($segundos, 60);
        $h   = intdiv($min, 60);
        if ($h > 0) {
            return sprintf('%d h %02d min', $h, $min % 60);
        }
        return sprintf('%d min', $min);
    }

    public static function data(?string $valor): string
    {
        return $valor ? date('d/m/Y H:i', strtotime($valor)) : '';
    }

    public const SLA_ROTULOS = ['no_prazo' => 'No prazo', 'atrasado' => 'Atrasado', 'andamento' => 'Em andamento (no prazo)', 'sem_sla' => 'Sem SLA'];

    /**
     * Texto dos filtros aplicados (cabeçalho do Excel e do PDF).
     */
    public static function descreverFiltros(array $f, array $opcoes): array
    {
        $d = ['Período' => date('d/m/Y', strtotime($f['de'])) . ' a ' . date('d/m/Y', strtotime($f['ate']))];
        if ($f['categoria']) {
            $d['Categoria'] = $opcoes['categorias'][$f['categoria']] ?? $f['categoria'];
        }
        if ($f['setor']) {
            $d['Setor'] = $opcoes['grupos'][$f['setor']] ?? $f['setor'];
        }
        if ($f['grupo']) {
            $d['Grupo atribuído'] = $opcoes['grupos'][$f['grupo']] ?? $f['grupo'];
        }
        if ($f['tecnico']) {
            $d['Técnico'] = $opcoes['tecnicos'][$f['tecnico']] ?? $f['tecnico'];
        }
        if ($f['prioridade']) {
            $d['Prioridade'] = self::PRIORIDADES[$f['prioridade']];
        }
        if ($f['tipo']) {
            $d['Tipo'] = self::TIPOS[$f['tipo']];
        }
        if ($f['situacao']) {
            $d['Situação'] = self::SITUACOES[$f['situacao']];
        }
        return $d;
    }
}
