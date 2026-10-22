<?php

namespace GlpiPlugin\Salasreuniao;

use CommonITILObject;
use Session;
use Ticket;

/**
 * Cartões de painel "Reuniões em andamento" e "Reuniões agendadas"
 * (aparecem em "Adicionar cartão" do painel, como os cartões do GLPI).
 */
class Painel
{
    public const CARTOES = [
        'andamento' => [
            'label'  => 'Reuniões em andamento',
            'icon'   => 'ti ti-presentation',
            'cor'    => '#fdeccf',
            'status' => [CommonITILObject::PLANNED],
        ],
        'agendadas' => [
            'label'  => 'Reuniões agendadas',
            'icon'   => 'ti ti-calendar-event',
            'cor'    => '#ece6f8',
            'status' => [CommonITILObject::INCOMING, CommonITILObject::ASSIGNED],
        ],
        'encerradas' => [
            'label'  => 'Reuniões encerradas',
            'icon'   => 'ti ti-calendar-check',
            'cor'    => '#dcefe4',
            'status' => [CommonITILObject::CLOSED],
        ],
    ];

    /**
     * Só Super-Admin (quem administra a configuração) e o setor responsável (Atendimento).
     */
    public static function podeVer(): bool
    {
        if (Session::getLoginUserID() === false) {
            return false;
        }
        if (Session::haveRight('empresa_reservas', READ)) {
            return true;
        }
        $grupo = (int) Settings::get('groups_id_assign');
        return $grupo > 0 && in_array($grupo, array_map('intval', (array) ($_SESSION['glpigroups'] ?? [])), true);
    }

    /**
     * Definição dos cartões para o hook "dashboard_cards".
     */
    public static function cartoes(): array
    {
        $cartoes = [];
        foreach (self::CARTOES as $tipo => $c) {
            $cartoes['plugin_salasreuniao_' . $tipo] = [
                'widgettype' => ['bigNumber'],
                'itemtype'   => '\\' . Ticket::class,
                'group'      => 'Salas de Reunião',
                'label'      => $c['label'],
                'provider'   => self::class . '::cartao',
                'args'       => ['tipo' => $tipo],
                'cache'      => false,
                'filters'    => [],
            ];
        }
        return $cartoes;
    }

    /**
     * Dados do cartão (mesmo formato dos cartões de chamados do GLPI).
     */
    public static function cartao(string $tipo, array $params = []): array
    {
        $c         = self::CARTOES[$tipo] ?? self::CARTOES['agendadas'];
        $categoria = (int) Settings::get('itilcategories_id');
        $numero    = 0;
        if ($categoria > 0 && self::podeVer()) {
            $numero = countElementsInTable('glpi_tickets', [
                'itilcategories_id' => $categoria,
                'is_deleted'        => 0,
                'status'            => $c['status'],
            ]);
        }

        return [
            'number' => $numero,
            'url'    => self::link($categoria, $c['status']),
            'label'  => $c['label'],
            'icon'   => $c['icon'],
        ];
    }

    /**
     * Lista de chamados filtrada: categoria de reserva de sala + status.
     */
    private static function link(int $categoria, array $status): string
    {
        global $CFG_GLPI;

        $grupo = [];
        foreach (array_values($status) as $i => $s) {
            $grupo[] = ['link' => $i === 0 ? 'AND' : 'OR', 'field' => 12, 'searchtype' => 'equals', 'value' => $s];
        }
        return $CFG_GLPI['root_doc'] . '/front/ticket.php?' . http_build_query([
            'criteria' => [
                ['field' => 7, 'searchtype' => 'equals', 'value' => $categoria],
                ['link' => 'AND', 'criteria' => $grupo],
            ],
            'reset' => 'reset',
        ]);
    }

    /**
     * Coloca os dois cartões no painel da lista de chamados (só se ainda não estiverem lá).
     */
    public static function adicionarAoPainelDeChamados(): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $chave  = (string) (\Config::getConfigurationValues('core', ['default_dashboard_mini_ticket'])['default_dashboard_mini_ticket'] ?? 'mini_tickets');
        $painel = $DB->request(['FROM' => 'glpi_dashboards_dashboards', 'WHERE' => ['key' => $chave]])->current();
        if (!$painel) {
            return;
        }
        $itens = iterator_to_array($DB->request(['FROM' => 'glpi_dashboards_items', 'WHERE' => ['dashboards_dashboards_id' => $painel['id']]]));

        // Depois do último cartão da primeira linha
        $x = 0;
        foreach ($itens as $item) {
            if ((int) $item['y'] === 0) {
                $x = max($x, (int) $item['x'] + (int) $item['width']);
            }
        }

        foreach (self::CARTOES as $tipo => $c) {
            $card_id = 'plugin_salasreuniao_' . $tipo;
            if (array_filter($itens, fn ($i) => $i['card_id'] === $card_id) !== []) {
                continue;
            }
            $DB->insert('glpi_dashboards_items', [
                'dashboards_dashboards_id' => $painel['id'],
                'gridstack_id'             => $card_id . '_' . bin2hex(random_bytes(8)),
                'card_id'                  => $card_id,
                'x'                        => $x,
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
            $x += 4;
        }
    }
}
