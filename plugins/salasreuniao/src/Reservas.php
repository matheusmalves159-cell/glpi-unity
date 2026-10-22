<?php

namespace GlpiPlugin\Salasreuniao;

use Session;
use User;

/**
 * Listas de reservas: "Minhas reservas" (só as do usuário, filtro simples) e
 * "Reservas da empresa" (Super-Admin, filtros detalhados).
 */
class Reservas
{
    /** Filtros simples de "Minhas reservas". */
    public const VISOES = [
        'proximas'   => ['Próximas', 'ti ti-calendar-event'],
        'passadas'   => ['Passadas', 'ti ti-history'],
        'canceladas' => ['Canceladas', 'ti ti-calendar-x'],
        'todas'      => ['Todas', 'ti ti-list'],
    ];

    public const SITUACOES = [
        'confirmada' => 'Confirmadas (ainda vão acontecer)',
        'encerrada'  => 'Encerradas',
        'cancelada'  => 'Canceladas',
    ];

    /** Condição SQL de cada situação/visão. */
    private static function condicao(string $situacao): array
    {
        $t = Booking::getTable();
        $agora = date('Y-m-d H:i:s');
        return match ($situacao) {
            'confirmada', 'proximas' => [$t . '.status' => 'ativa', $t . '.end' => ['>=', $agora]],
            'encerrada', 'passadas'  => [$t . '.status' => 'ativa', $t . '.end' => ['<', $agora]],
            'cancelada', 'canceladas' => [$t . '.status' => 'cancelada'],
            default => [],
        };
    }

    /**
     * Busca as reservas.
     *
     * @param array $where condições extras
     * @param bool  $recentesPrimeiro ordem decrescente (passadas/canceladas)
     */
    public static function buscar(array $where, bool $recentesPrimeiro = false, int $limite = 500): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $t = Booking::getTable();
        $r = Room::getTable();
        $lista = [];
        foreach ($DB->request([
            'SELECT'    => [$t . '.*', $r . '.name AS sala', $r . '.location AS local'],
            'FROM'      => $t,
            'LEFT JOIN' => [$r => ['ON' => [$r => 'id', $t => 'plugin_salasreuniao_rooms_id']]],
            'WHERE'     => $where,
            'ORDER'     => [$t . '.begin ' . ($recentesPrimeiro ? 'DESC' : 'ASC')],
            'LIMIT'     => $limite,
        ]) as $row) {
            $reserva = new Booking();
            $reserva->getFromResultSet($row);
            $passou = strtotime((string) $row['end']) < time();
            $lista[] = [
                'id'       => (int) $row['id'],
                'data'     => date('d/m/Y', strtotime((string) $row['begin'])),
                'semana'   => ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'][(int) date('w', strtotime((string) $row['begin']))],
                'inicio'   => date('H:i', strtotime((string) $row['begin'])),
                'fim'      => date('H:i', strtotime((string) $row['end'])),
                'minutos'  => (int) $row['duration'],
                'duracao'  => Settings::rotuloDuracao((int) $row['duration']),
                'sala'     => (string) $row['sala'],
                'local'    => (string) ($row['local'] ?? ''),
                'pessoas'  => (int) $row['participants'],
                'tipo'     => $row['meeting_type'] === 'externa' ? 'Externa' : 'Interna',
                'assunto'  => (string) $row['subject'],
                'situacao' => $row['status'] === 'cancelada' ? 'cancelada' : ($passou ? 'encerrada' : 'confirmada'),
                'pode'     => $reserva->podeAlterar(),
                'users_id' => (int) $row['users_id'],
                'quem'     => self::nome((int) $row['users_id']),
                'chamado'  => (int) $row['tickets_id'],
            ];
        }
        return $lista;
    }

    private static function nome(int $users_id): string
    {
        static $cache = [];
        if (!isset($cache[$users_id])) {
            $u = new User();
            $cache[$users_id] = $u->getFromDB($users_id) ? $u->getFriendlyName() : '—';
        }
        return $cache[$users_id];
    }

    /** "Minhas reservas": reservas do usuário logado numa visão. */
    public static function minhas(string $visao): array
    {
        $where = [Booking::getTable() . '.users_id' => (int) Session::getLoginUserID()] + self::condicao($visao);
        return self::buscar($where, in_array($visao, ['passadas', 'canceladas', 'todas'], true));
    }

    /** Quantas reservas do usuário logado em cada visão (para os números nas abas). */
    public static function contagemMinhas(): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $contagem = [];
        foreach (array_keys(self::VISOES) as $visao) {
            $contagem[$visao] = (int) $DB->request([
                'COUNT' => 'cpt',
                'FROM'  => Booking::getTable(),
                'WHERE' => [Booking::getTable() . '.users_id' => (int) Session::getLoginUserID()] + self::condicao($visao),
            ])->current()['cpt'];
        }
        return $contagem;
    }

    /** Filtros de "Reservas da empresa", lidos da URL e já validados. */
    public static function filtrosEmpresa(array $get): array
    {
        $data = static fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : '';
        $f = [
            'de'       => $data($get['de'] ?? ''),
            'ate'      => $data($get['ate'] ?? ''),
            'sala'     => (int) ($get['sala'] ?? 0),
            'usuario'  => (int) ($get['usuario'] ?? 0),
            'situacao' => array_key_exists((string) ($get['situacao'] ?? ''), self::SITUACOES) ? (string) $get['situacao'] : '',
            'tipo'     => in_array((string) ($get['tipo'] ?? ''), ['interna', 'externa'], true) ? (string) $get['tipo'] : '',
            'q'        => mb_substr(trim((string) ($get['q'] ?? '')), 0, 100),
        ];
        // Sem nenhum filtro: de hoje em diante
        if (!isset($get['de']) && !isset($get['ate'])) {
            $f['de'] = date('Y-m-d');
        }
        return $f;
    }

    /** Reservas da empresa com os filtros detalhados. */
    public static function empresa(array $f): array
    {
        $t = Booking::getTable();
        $where = self::condicao($f['situacao']);
        if ($f['de'] !== '') {
            $where[] = [$t . '.begin' => ['>=', $f['de'] . ' 00:00:00']];
        }
        if ($f['ate'] !== '') {
            $where[] = [$t . '.begin' => ['<=', $f['ate'] . ' 23:59:59']];
        }
        if ($f['sala'] > 0) {
            $where[$t . '.plugin_salasreuniao_rooms_id'] = $f['sala'];
        }
        if ($f['usuario'] > 0) {
            $where[$t . '.users_id'] = $f['usuario'];
        }
        if ($f['tipo'] !== '') {
            $where[$t . '.meeting_type'] = $f['tipo'];
        }
        if ($f['q'] !== '') {
            $where[$t . '.subject'] = ['LIKE', '%' . $f['q'] . '%'];
        }
        return self::buscar($where, $f['de'] === '' && $f['ate'] !== '', 1000);
    }

    /** Números do topo de "Reservas da empresa". */
    public static function resumo(array $reservas): array
    {
        $r = ['total' => count($reservas), 'confirmada' => 0, 'encerrada' => 0, 'cancelada' => 0, 'minutos' => 0, 'salas' => []];
        foreach ($reservas as $x) {
            $r[$x['situacao']]++;
            if ($x['situacao'] !== 'cancelada') {
                $r['minutos'] += $x['minutos'];
                $r['salas'][$x['sala']] = ($r['salas'][$x['sala']] ?? 0) + 1;
            }
        }
        arsort($r['salas']);
        $r['sala_top'] = $r['salas'] === [] ? '—' : array_key_first($r['salas']);
        $r['sala_top_qtd'] = $r['salas'] === [] ? 0 : reset($r['salas']);
        $r['horas'] = $r['minutos'] >= 60
            ? floor($r['minutos'] / 60) . ' h' . ($r['minutos'] % 60 ? ' ' . ($r['minutos'] % 60) . ' min' : '')
            : $r['minutos'] . ' min';
        return $r;
    }

    /** Opções dos filtros: salas e pessoas que já reservaram. */
    public static function opcoesEmpresa(): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $salas = [];
        foreach ($DB->request(['FROM' => Room::getTable(), 'ORDER' => 'name']) as $s) {
            $salas[(int) $s['id']] = (string) $s['name'];
        }
        $pessoas = [];
        foreach ($DB->request(['SELECT' => 'users_id', 'DISTINCT' => true, 'FROM' => Booking::getTable()]) as $u) {
            $pessoas[(int) $u['users_id']] = self::nome((int) $u['users_id']);
        }
        asort($pessoas);
        return ['salas' => $salas, 'pessoas' => $pessoas];
    }
}
