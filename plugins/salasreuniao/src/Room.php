<?php

namespace GlpiPlugin\Salasreuniao;

use CommonDBTM;
use Glpi\Application\View\TemplateRenderer;
use Session;

/**
 * Sala de reunião, ligada a uma agenda do Google.
 */
class Room extends CommonDBTM
{
    public static $rightname = 'config';

    public $dohistory = true;

    public static function getTypeName($nb = 0)
    {
        return $nb > 1 ? 'Salas de Reunião' : 'Sala de Reunião';
    }

    public static function getIcon()
    {
        return 'ti ti-door';
    }

    public static function canView(): bool
    {
        return Session::haveRight('empresa_salas', UPDATE);
    }

    public static function canCreate(): bool
    {
        return Session::haveRight('empresa_salas', UPDATE);
    }

    public static function canUpdate(): bool
    {
        return Session::haveRight('empresa_salas', UPDATE);
    }

    public static function canPurge(): bool
    {
        return Session::haveRight('empresa_salas', UPDATE);
    }

    public static function canDelete(): bool
    {
        return false;
    }

    public static function getMenuContent()
    {
        return [
            'title' => self::getTypeName(Session::getPluralNumber()),
            'page'  => self::getSearchURL(false),
            'icon'  => self::getIcon(),
            'links' => [
                'search' => self::getSearchURL(false),
                'add'    => self::getFormURL(false),
                'config' => '/plugins/salasreuniao/front/config.form.php',
            ],
        ];
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'   => 'common',
            'name' => self::getTypeName(1),
        ];
        $tab[] = [
            'id'            => '1',
            'table'         => $this->getTable(),
            'field'         => 'name',
            'name'          => __('Name'),
            'datatype'      => 'itemlink',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'            => '2',
            'table'         => $this->getTable(),
            'field'         => 'id',
            'name'          => __('ID'),
            'datatype'      => 'number',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'       => '3',
            'table'    => $this->getTable(),
            'field'    => 'capacity',
            'name'     => 'Capacidade',
            'datatype' => 'number',
        ];
        $tab[] = [
            'id'       => '4',
            'table'    => $this->getTable(),
            'field'    => 'location',
            'name'     => 'Local',
            'datatype' => 'string',
        ];
        $tab[] = [
            'id'       => '5',
            'table'    => $this->getTable(),
            'field'    => 'is_active',
            'name'     => __('Active'),
            'datatype' => 'bool',
        ];
        $tab[] = [
            'id'            => '6',
            'table'         => $this->getTable(),
            'field'         => 'google_calendar_id',
            'name'          => 'Agenda do Google',
            'datatype'      => 'string',
            'massiveaction' => false,
        ];

        return $tab;
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);

        TemplateRenderer::getInstance()->display('@salasreuniao/room.html.twig', [
            'item'          => $this,
            'params'        => $options,
            'conta_servico' => GoogleClient::emailContaServico(),
        ]);
        return true;
    }

    public function prepareInputForAdd($input)
    {
        return $this->validar($input, true);
    }

    public function prepareInputForUpdate($input)
    {
        return $this->validar($input, false);
    }

    private function validar(array $input, bool $nova)
    {
        if ($nova || array_key_exists('google_calendar_id', $input)) {
            $input['google_calendar_id'] = trim((string) ($input['google_calendar_id'] ?? ''));
            // Vazio é permitido (sala cadastrada mas ainda desativada)
            if ($input['google_calendar_id'] !== '' && !preg_match('/^[^\s@]+@[^\s@]+$/', $input['google_calendar_id'])) {
                Session::addMessageAfterRedirect(
                    htmlescape('Informe o ID da agenda do Google da sala (ex.: xxxx@group.calendar.google.com).'),
                    false,
                    ERROR
                );
                return false;
            }
        }
        if ($nova || array_key_exists('capacity', $input)) {
            // 0 = sem limite de pessoas
            $input['capacity'] = max(0, (int) ($input['capacity'] ?? 0));
        }
        // Sala ativa precisa de agenda do Google (inclusive ativação em massa pela lista)
        $ativa  = (int) ($input['is_active'] ?? ($this->fields['is_active'] ?? 1));
        $agenda = trim((string) ($input['google_calendar_id'] ?? ($this->fields['google_calendar_id'] ?? '')));
        if ($ativa === 1 && $agenda === '') {
            Session::addMessageAfterRedirect(
                htmlescape(sprintf(
                    'A sala %s só pode ser ativada depois de informar o ID da agenda do Google.',
                    $input['name'] ?? ($this->fields['name'] ?? '')
                )),
                false,
                ERROR
            );
            return false;
        }
        return $input;
    }

    public function post_updateItem($history = true)
    {
        // Agenda do Google trocada: as reservas futuras vão junto para a nova agenda
        if (in_array('google_calendar_id', $this->updates, true) && $this->fields['google_calendar_id'] !== '') {
            try {
                [$movidas, $falhas] = Booking::moverParaNovaAgenda($this, (string) ($this->oldvalues['google_calendar_id'] ?? ''));
                if ($movidas > 0) {
                    Session::addMessageAfterRedirect(htmlescape(sprintf(
                        '%d reserva(s) futura(s) desta sala foram levadas para a nova agenda do Google.',
                        $movidas
                    )));
                }
                foreach ($falhas as $falha) {
                    Session::addMessageAfterRedirect(htmlescape('Não foi possível levar para a nova agenda: ' . $falha), false, ERROR);
                }
            } catch (\Throwable $e) {
                Session::addMessageAfterRedirect(htmlescape('Reservas futuras não foram levadas para a nova agenda: ' . $e->getMessage()), false, ERROR);
            }
        }
        parent::post_updateItem($history);
    }

    /**
     * Salas ativas que comportam a quantidade de pessoas (capacidade 0 = sem limite).
     *
     * @return array<int, array>
     */
    public static function paraPessoas(int $pessoas): array
    {
        return array_values((new self())->find(
            [
                'is_active'          => 1,
                'google_calendar_id' => ['<>', ''],
                'OR'                 => [
                    ['capacity' => 0],
                    ['capacity' => ['>=', max(1, $pessoas)]],
                ],
            ],
            ['name ASC']
        ));
    }

    /**
     * Alguma sala ativa tem limite de pessoas cadastrado?
     */
    public static function algumaComCapacidade(): bool
    {
        return countElementsInTable(self::getTable(), ['is_active' => 1, 'capacity' => ['>', 0]]) > 0;
    }
}
