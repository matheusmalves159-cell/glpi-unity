<?php

namespace GlpiPlugin\Abrirchamado;

use CommonGLPI;
use Computer;
use Group_Item;
use Location;
use Session;

/**
 * Ativos > Situação dos computadores:
 * - liga cada computador ao usuário que o usa (pelo último login do inventário), ao grupo e ao local (setor);
 * - mostra quem está sem comunicação, sem usuário ou com Windows desatualizado.
 */
class Computadores extends CommonGLPI
{
    /** Dias sem falar com o agente para considerar o computador "sem comunicação" */
    public const DIAS_SEM_CONTATO = 30;

    public static function getTypeName($nb = 0)
    {
        return 'Situação dos computadores';
    }

    public static function getIcon()
    {
        return 'ti ti-heart-rate-monitor';
    }

    public static function canView(): bool
    {
        return Session::getLoginUserID() !== false && Session::haveRight('computer', READ);
    }

    public static function getMenuContent()
    {
        return [
            'title' => self::getTypeName(),
            'page'  => '/plugins/abrirchamado/front/computadores.php',
            'icon'  => self::getIcon(),
        ];
    }

    /** Estado do computador pelo nome (cria se não existir). */
    public static function estado(string $nome): int
    {
        /** @var \DBmysql $DB */
        global $DB;

        $achado = $DB->request(['SELECT' => 'id', 'FROM' => 'glpi_states', 'WHERE' => ['name' => $nome]])->current();
        if ($achado) {
            return (int) $achado['id'];
        }

        return (int) (new \State())->add([
            'name'                => $nome,
            'entities_id'         => 0,
            'is_recursive'        => 1,
            'is_visible_computer' => 1,
        ]);
    }

    /** Login do usuário a partir do campo "contato" do inventário (usuario, usuario@dominio, DOMINIO\usuario). */
    private static function loginDoContato(string $contato): string
    {
        $login = strtolower(trim($contato));
        $login = preg_replace('/@.*$/', '', $login);
        $login = preg_replace('/^.*\\\\/', '', $login);

        return (string) $login;
    }

    /**
     * Liga o computador ao usuário do último login, ao grupo dele e ao local (setor).
     * Só preenche o que ainda está vazio (grupo e local), para não desfazer ajustes manuais.
     */
    public static function vincular(int $id): bool
    {
        /** @var \DBmysql $DB */
        global $DB;

        $pc = $DB->request(['FROM' => 'glpi_computers', 'WHERE' => ['id' => $id]])->current();
        // Servidores (SRV-...) não têm "dono": o último login é de quem foi administrar
        if (!$pc || $pc['is_template'] || trim((string) $pc['contact']) === '' || preg_match('/^srv[-_]/i', (string) $pc['name'])) {
            return false;
        }

        $login = self::loginDoContato((string) $pc['contact']);
        if ($login === '') {
            return false;
        }

        $usuarios = iterator_to_array($DB->request([
            'SELECT' => ['id', 'usercategories_id'],
            'FROM'   => 'glpi_users',
            'WHERE'  => ['name' => $login, 'is_deleted' => 0],
        ]), false);
        if (count($usuarios) !== 1) {
            return false;
        }
        $usuario = $usuarios[0];

        $mudou = [];
        if ((int) $pc['users_id'] !== (int) $usuario['id']) {
            $mudou['users_id'] = (int) $usuario['id'];
        }

        // Local = setor (categoria do usuário)
        if ((int) $pc['locations_id'] === 0 && (int) $usuario['usercategories_id'] > 0) {
            $setor = $DB->request(['SELECT' => 'name', 'FROM' => 'glpi_usercategories', 'WHERE' => ['id' => $usuario['usercategories_id']]])->current();
            if ($setor) {
                $mudou['locations_id'] = self::local((string) $setor['name']);
            }
        }

        if ($mudou) {
            $DB->update('glpi_computers', $mudou, ['id' => $id]);
        }

        // Grupo = primeiro grupo do usuário, se o computador ainda não tem grupo
        if (!countElementsInTable('glpi_groups_items', ['itemtype' => Computer::class, 'items_id' => $id, 'type' => Group_Item::GROUP_TYPE_NORMAL])) {
            $grupo = $DB->request([
                'SELECT'    => ['g.id'],
                'FROM'      => 'glpi_groups_users AS gu',
                'INNER JOIN' => ['glpi_groups AS g' => ['ON' => ['gu' => 'groups_id', 'g' => 'id']]],
                'WHERE'     => ['gu.users_id' => $usuario['id'], 'g.is_itemgroup' => 1],
                'ORDER'     => 'g.completename',
                'LIMIT'     => 1,
            ])->current();
            if ($grupo) {
                $DB->insert('glpi_groups_items', [
                    'groups_id' => $grupo['id'],
                    'itemtype'  => Computer::class,
                    'items_id'  => $id,
                    'type'      => Group_Item::GROUP_TYPE_NORMAL,
                ]);
            }
        }

        return $mudou !== [];
    }

    private static function local(string $nome): int
    {
        /** @var \DBmysql $DB */
        global $DB;

        $achado = $DB->request(['SELECT' => 'id', 'FROM' => 'glpi_locations', 'WHERE' => ['name' => $nome, 'locations_id' => 0]])->current();
        if ($achado) {
            return (int) $achado['id'];
        }

        return (int) (new Location())->add(['name' => $nome, 'locations_id' => 0, 'entities_id' => 0, 'is_recursive' => 1]);
    }

    /** Situação do Windows/servidor pelo sistema e versão. [texto, classe] */
    public static function situacaoSistema(string $sistema, string $versao): array
    {
        $s = strtolower($sistema);

        if (str_contains($s, 'windows 10')) {
            return ['Sem suporte: atualizar para o Windows 11', 'ac-comp-ruim'];
        }
        if (str_contains($s, 'windows 11') && in_array(strtoupper($versao), ['21H2', '22H2', '23H2'], true)) {
            return ['Desatualizado: instalar a versão mais nova', 'ac-comp-medio'];
        }
        if (str_contains($s, 'server 2019') || str_contains($s, 'server 2016')) {
            return ['Suporte estendido: planejar troca', 'ac-comp-medio'];
        }

        return ['Em dia', 'ac-comp-ok'];
    }

    /** Números e listas do painel. */
    public static function painel(): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $limite = date('Y-m-d H:i:s', strtotime('-' . self::DIAS_SEM_CONTATO . ' days'));

        $linhas = iterator_to_array($DB->request([
            'SELECT'   => [
                'c.id', 'c.name', 'c.users_id', 'c.contact',
                'os.name AS sistema', 'v.name AS versao',
                'u.name AS usuario', 's.name AS estado',
                new \Glpi\DBAL\QueryExpression('(SELECT MAX(a.last_contact) FROM glpi_agents a WHERE a.itemtype = \'Computer\' AND a.items_id = c.id) AS ultimo_contato'),
            ],
            'FROM'     => 'glpi_computers AS c',
            'LEFT JOIN' => [
                'glpi_items_operatingsystems AS io' => ['ON' => ['io' => 'items_id', 'c' => 'id', ['AND' => ['io.itemtype' => 'Computer']]]],
                'glpi_operatingsystems AS os'       => ['ON' => ['os' => 'id', 'io' => 'operatingsystems_id']],
                'glpi_operatingsystemversions AS v' => ['ON' => ['v' => 'id', 'io' => 'operatingsystemversions_id']],
                'glpi_users AS u'                   => ['ON' => ['u' => 'id', 'c' => 'users_id']],
                'glpi_states AS s'                  => ['ON' => ['s' => 'id', 'c' => 'states_id']],
            ],
            'WHERE'    => ['c.is_deleted' => 0, 'c.is_template' => 0],
        ]), false);

        $total = count($linhas);
        $semContato = $semUsuario = $desatualizados = 0;
        $sistemas = [];
        $atencao = [];

        foreach ($linhas as $l) {
            $motivos = [];
            if ($l['ultimo_contato'] === null || $l['ultimo_contato'] < $limite) {
                $semContato++;
                $motivos[] = 'Sem comunicação há mais de ' . self::DIAS_SEM_CONTATO . ' dias';
            }
            if ((int) $l['users_id'] === 0 && !preg_match('/^srv[-_]/i', (string) $l['name'])) {
                $semUsuario++;
                $motivos[] = 'Sem usuário';
            }
            [$texto, $classe] = self::situacaoSistema((string) $l['sistema'], (string) $l['versao']);
            if ($classe !== 'ac-comp-ok') {
                $desatualizados++;
                $motivos[] = $texto;
            }

            $chave = trim($l['sistema'] . ' ' . $l['versao']);
            $sistemas[$chave] = ($sistemas[$chave] ?? ['sistema' => (string) $l['sistema'], 'versao' => (string) $l['versao'], 'total' => 0]);
            $sistemas[$chave]['total']++;

            if ($motivos) {
                $atencao[] = $l + ['motivos' => $motivos];
            }
        }

        uasort($sistemas, fn ($a, $b) => $b['total'] <=> $a['total']);
        usort($atencao, fn ($a, $b) => count($b['motivos']) <=> count($a['motivos']) ?: strcmp((string) $a['name'], (string) $b['name']));

        return [
            'total'          => $total,
            'sem_contato'    => $semContato,
            'sem_usuario'    => $semUsuario,
            'desatualizados' => $desatualizados,
            'sistemas'       => array_values($sistemas),
            'atencao'        => $atencao,
        ];
    }
}
