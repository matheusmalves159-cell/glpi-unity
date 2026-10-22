<?php

namespace GlpiPlugin\Salasreuniao;

use CommonDBTM;
use CommonITILActor;
use CommonITILObject;
use CronTask;
use DateTimeImmutable;
use Group_Ticket;
use Html;
use ITILFollowup;
use Log;
use Session;
use Throwable;
use Ticket;
use User;
use UserEmail;

/**
 * Reserva de sala feita pelo GLPI (espelho do evento criado no Google Agenda).
 */
class Booking extends CommonDBTM
{
    public static $rightname = 'config';

    /**
     * Status "Em andamento" das reuniões: é o "Em atendimento (planejado)" do GLPI,
     * que não é usado no dia a dia, renomeado em files/_locales/core/pt_BR.php.
     */
    public const EM_ANDAMENTO = CommonITILObject::PLANNED;

    public const TIPOS = [
        'interna' => 'Interna',
        'externa' => 'Externa',
    ];

    public static function getTypeName($nb = 0)
    {
        return 'Salas de Reunião';
    }

    public static function getIcon()
    {
        return 'ti ti-calendar-event';
    }

    /** Qualquer usuário logado pode agendar e ver as próprias reservas */
    public static function canView(): bool
    {
        return Session::getLoginUserID() !== false;
    }

    public static function canCreate(): bool
    {
        return Session::getLoginUserID() !== false;
    }

    public static function getMenuContent()
    {
        return [
            'title' => self::getTypeName(),
            'page'  => '/plugins/salasreuniao/front/agendar.php',
            'icon'  => self::getIcon(),
            'links' => [
                'search' => '/plugins/salasreuniao/front/minhasreservas.php',
            ],
        ];
    }

    /** Pode ver as reservas de toda a empresa (permissão do perfil: Módulos da Empresa). */
    public static function podeVerTodas(): bool
    {
        return Session::haveRight('empresa_reservas', READ);
    }

    /** Pode cancelar e remarcar reservas de outras pessoas. */
    public static function isAdmin(): bool
    {
        return Session::haveRight('empresa_reservas', UPDATE);
    }

    /**
     * Ainda pode ser cancelada/remarcada por quem está logado.
     */
    public function podeAlterar(): bool
    {
        return ($this->fields['status'] ?? '') === 'ativa'
            && strtotime((string) $this->fields['end']) > time()
            && ((int) $this->fields['users_id'] === (int) Session::getLoginUserID() || self::isAdmin());
    }

    /**
     * Agenda (ou remarca, com "remarcar" = ID da reserva) a partir dos dados do formulário.
     *
     * @return array{ok:bool, msg:string}
     */
    public static function agendar(array $in): array
    {
        $sala      = new Room();
        $rooms_id  = (int) ($in['rooms_id'] ?? 0);
        $data      = (string) ($in['data'] ?? '');
        $hora      = (string) ($in['hora'] ?? '');
        $duracao   = (int) ($in['duracao'] ?? 0);
        $pessoas   = (int) ($in['pessoas'] ?? 0);
        $tipo      = (string) ($in['tipo'] ?? '');
        $assunto   = mb_substr(trim((string) ($in['assunto'] ?? '')), 0, 200);
        $remarcar  = (int) ($in['remarcar'] ?? 0);

        try {
            Availability::validar($data, $duracao, $pessoas);
        } catch (Throwable $e) {
            return self::erro($e->getMessage());
        }
        if (!$sala->getFromDB($rooms_id) || !$sala->fields['is_active']) {
            return self::erro('Escolha uma sala e um horário.');
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $hora)) {
            return self::erro('Escolha um horário.');
        }
        if ((int) $sala->fields['capacity'] > 0 && $pessoas > (int) $sala->fields['capacity']) {
            return self::erro(sprintf('A sala %s comporta até %d pessoas.', $sala->getName(), $sala->fields['capacity']));
        }
        if (!array_key_exists($tipo, self::TIPOS)) {
            return self::erro('Informe se a reunião é interna ou externa.');
        }
        if ($assunto === '') {
            return self::erro('Informe o assunto da reunião.');
        }

        $anterior = null;
        if ($remarcar > 0) {
            $anterior = new self();
            if (!$anterior->getFromDB($remarcar) || !$anterior->podeAlterar()) {
                return self::erro('Essa reserva não pode mais ser remarcada.');
            }
        }

        try {
            $google = new GoogleClient();
            // Confere de novo na hora de gravar: outra pessoa pode ter pego o horário
            $livres = Availability::horariosDaSala($google, $sala->fields, $data, $duracao, $remarcar);
        } catch (Throwable $e) {
            return self::erro('Não foi possível consultar a agenda da sala: ' . $e->getMessage());
        }
        if (!in_array($hora, $livres, true)) {
            return self::erro('Esse horário não está mais disponível nesta sala. Escolha outro.');
        }

        $tz     = Availability::tz();
        $inicio = new DateTimeImmutable($data . ' ' . $hora . ':00', $tz);
        $fim    = $inicio->modify("+{$duracao} minutes");
        $dono   = $anterior ? (int) $anterior->fields['users_id'] : (int) Session::getLoginUserID();
        $evento = self::evento($google, $sala, $dono, $inicio, $fim, $pessoas, $tipo, $assunto);

        try {
            $mesma_sala = $anterior
                && (int) $anterior->fields['plugin_salasreuniao_rooms_id'] === $rooms_id
                && $anterior->fields['google_event_id'] !== '';
            if ($mesma_sala) {
                $google->alterarEvento($sala->fields['google_calendar_id'], $anterior->fields['google_event_id'], $evento);
                $evento_id = (string) $anterior->fields['google_event_id'];
            } else {
                $criado    = $google->criarEvento($sala->fields['google_calendar_id'], $evento);
                $evento_id = (string) ($criado['id'] ?? '');
                if ($anterior) {
                    self::excluirEventoGoogle($google, $anterior);
                }
            }
        } catch (Throwable $e) {
            return self::erro('O Google Agenda recusou o agendamento: ' . $e->getMessage());
        }

        $campos = [
            'plugin_salasreuniao_rooms_id' => $rooms_id,
            'begin'                        => $inicio->format('Y-m-d H:i:s'),
            'end'                          => $fim->format('Y-m-d H:i:s'),
            'duration'                     => $duracao,
            'participants'                 => $pessoas,
            'meeting_type'                 => $tipo,
            'subject'                      => $assunto,
            'google_event_id'              => $evento_id,
            'status'                       => 'ativa',
        ];
        $detalhes = self::detalhesHtml($sala, $inicio, $fim, $duracao, $pessoas, $tipo, $assunto);
        if ($anterior) {
            $anterior->update(['id' => $remarcar] + $campos);
            $tickets_id = (int) $anterior->fields['tickets_id'];
            if ($tickets_id > 0) {
                (new Ticket())->update(['id' => $tickets_id, 'name' => self::tituloChamado($sala, $inicio, $fim)]);
                self::acompanhamento($tickets_id, '<p><strong>Reserva remarcada.</strong> Novos dados:</p>' . $detalhes);
            }
        } else {
            $tickets_id = self::abrirChamado($sala, $dono, $inicio, $fim, $detalhes);
            (new self())->add($campos + ['users_id' => $dono, 'tickets_id' => $tickets_id]);
        }

        $msg = sprintf(
            '%s: %s, %s, das %s às %s.',
            $anterior ? 'Reserva remarcada' : 'Sala reservada',
            $sala->getName(),
            $inicio->format('d/m/Y'),
            $inicio->format('H:i'),
            $fim->format('H:i')
        );
        if ($tickets_id > 0) {
            $msg .= sprintf(' Chamado nº %d.', $tickets_id);
        }
        return ['ok' => true, 'msg' => $msg];
    }

    /**
     * @return array{ok:bool, msg:string}
     */
    public static function cancelar(int $id): array
    {
        $reserva = new self();
        if (!$reserva->getFromDB($id) || !$reserva->podeAlterar()) {
            return self::erro('Essa reserva não pode mais ser cancelada.');
        }
        try {
            self::excluirEventoGoogle(new GoogleClient(), $reserva);
        } catch (Throwable $e) {
            return self::erro('O Google Agenda não confirmou o cancelamento: ' . $e->getMessage());
        }
        $reserva->update(['id' => $id, 'status' => 'cancelada']);

        $tickets_id = (int) $reserva->fields['tickets_id'];
        if ($tickets_id > 0) {
            $quem = 'pelo solicitante';
            if ((int) $reserva->fields['users_id'] !== (int) Session::getLoginUserID()) {
                $user = new User();
                $quem = 'por ' . ($user->getFromDB((int) Session::getLoginUserID()) ? $user->getFriendlyName() : 'outro usuário');
            }
            self::acompanhamento($tickets_id, sprintf(
                '<p><strong>Reserva cancelada %s.</strong> O horário foi liberado no Google Agenda.</p>',
                htmlescape($quem)
            ));
            self::fecharChamado($tickets_id);
        }

        return ['ok' => true, 'msg' => 'Reserva cancelada e horário liberado no Google Agenda.'];
    }

    /**
     * Abre o chamado da reserva: requerente = quem agendou, atribuído ao setor configurado.
     */
    private static function abrirChamado(Room $sala, int $users_id, DateTimeImmutable $inicio, DateTimeImmutable $fim, string $detalhes): int
    {
        $grupo  = (int) Settings::get('groups_id_assign');
        $atores = [
            'requester' => [[
                'itemtype'          => 'User',
                'items_id'          => $users_id,
                'use_notification'  => 1,
                'alternative_email' => '',
            ]],
        ];
        if ($grupo > 0) {
            $atores['assign'] = [['itemtype' => 'Group', 'items_id' => $grupo]];
        }

        $id = (int) (new Ticket())->add([
            'name'              => self::tituloChamado($sala, $inicio, $fim),
            'content'           => '<p>Reserva de Sala de Reunião feita pelo agendamento do GLPI.</p>' . $detalhes,
            'type'              => Ticket::DEMAND_TYPE,
            'itilcategories_id' => (int) Settings::get('itilcategories_id'),
            'entities_id'       => (int) ($_SESSION['glpiactive_entity'] ?? 0),
            '_actors'           => $atores,
        ]);

        // Quem não tem direito de atribuir não consegue atribuir na abertura: garante o setor
        if ($id > 0 && $grupo > 0 && countElementsInTable(Group_Ticket::getTable(), [
            'tickets_id' => $id,
            'groups_id'  => $grupo,
            'type'       => CommonITILActor::ASSIGN,
        ]) === 0) {
            (new Group_Ticket())->add([
                'tickets_id' => $id,
                'groups_id'  => $grupo,
                'type'       => CommonITILActor::ASSIGN,
            ]);
        }

        return $id;
    }

    public static function cronInfo($name)
    {
        return ['description' => 'Chamados de Reserva de Sala: "Em atendimento" no início da reunião e fechados no fim'];
    }

    /**
     * Ao começar a reunião, o chamado passa para "Em atendimento";
     * ao terminar o horário reservado, o chamado é fechado.
     */
    public static function cronStatusreunioes(CronTask $task)
    {
        /** @var \DBmysql $DB */
        global $DB;

        $agora    = date('Y-m-d H:i:s');
        $abertos  = array_diff(
            array_keys(Ticket::getAllStatusArray()),
            Ticket::getClosedStatusArray()
        );
        $iniciadas = 0;
        $fechadas  = 0;

        $reservas = $DB->request([
            'SELECT'     => [self::getTable() . '.begin', self::getTable() . '.end', 'glpi_tickets.id AS tickets_id', 'glpi_tickets.status'],
            'FROM'       => self::getTable(),
            'INNER JOIN' => [
                'glpi_tickets' => [
                    'ON' => ['glpi_tickets' => 'id', self::getTable() => 'tickets_id'],
                ],
            ],
            'WHERE'      => [
                self::getTable() . '.status' => 'ativa',
                self::getTable() . '.begin'  => ['<=', $agora],
                // Não volta muito no tempo: só reuniões dos últimos 30 dias
                self::getTable() . '.end'    => ['>=', date('Y-m-d H:i:s', strtotime('-30 days'))],
                'glpi_tickets.status'        => $abertos,
                'glpi_tickets.is_deleted'    => 0,
            ],
        ]);

        foreach ($reservas as $r) {
            $ticket = new Ticket();
            if ($r['end'] <= $agora) {
                // Reunião terminou: fecha o chamado
                if ($ticket->update(['id' => (int) $r['tickets_id'], 'status' => CommonITILObject::CLOSED, '_disablenotif' => true])) {
                    $fechadas++;
                }
            } elseif ((int) $r['status'] !== self::EM_ANDAMENTO) {
                // Reunião em andamento. A tarefa roda sem usuário logado, e o GLPI só
                // permite trocar status pela matriz do perfil logado: grava direto
                // e registra no histórico do chamado.
                $DB->update('glpi_tickets', [
                    'status'   => self::EM_ANDAMENTO,
                    'date_mod' => $agora,
                ], ['id' => (int) $r['tickets_id']]);
                Log::history((int) $r['tickets_id'], Ticket::class, [12, (int) $r['status'], self::EM_ANDAMENTO]);
                $iniciadas++;
            }
        }

        if ($iniciadas + $fechadas > 0) {
            $task->log(sprintf('%d reunião(ões) em andamento, %d chamado(s) fechado(s).', $iniciadas, $fechadas));
        }
        $task->setVolume($iniciadas + $fechadas);
        return ($iniciadas + $fechadas) > 0 ? 1 : 0;
    }

    private static function tituloChamado(Room $sala, DateTimeImmutable $inicio, DateTimeImmutable $fim): string
    {
        return sprintf(
            'Reserva de Sala: %s — %s, %s às %s',
            $sala->getName(),
            $inicio->format('d/m/Y'),
            $inicio->format('H:i'),
            $fim->format('H:i')
        );
    }

    private static function detalhesHtml(
        Room $sala,
        DateTimeImmutable $inicio,
        DateTimeImmutable $fim,
        int $duracao,
        int $pessoas,
        string $tipo,
        string $assunto
    ): string {
        $linhas = [
            'Sala'           => $sala->getName() . (!empty($sala->fields['location']) ? ' (' . $sala->fields['location'] . ')' : ''),
            'Dia'            => $inicio->format('d/m/Y'),
            'Horário'        => $inicio->format('H:i') . ' às ' . $fim->format('H:i'),
            'Duração'        => Settings::rotuloDuracao($duracao),
            'Participantes'  => (string) $pessoas,
            'Tipo'           => self::TIPOS[$tipo] ?? $tipo,
            'Assunto'        => $assunto,
        ];
        $html = '<table>';
        foreach ($linhas as $rotulo => $valor) {
            if ($valor === '') {
                continue;
            }
            $html .= sprintf('<tr><td><strong>%s</strong></td><td>%s</td></tr>', htmlescape($rotulo), htmlescape($valor));
        }
        return $html . '</table>';
    }

    private static function acompanhamento(int $tickets_id, string $html): void
    {
        $ticket = new Ticket();
        if (!$ticket->getFromDB($tickets_id) || in_array((int) $ticket->fields['status'], Ticket::getClosedStatusArray(), true)) {
            return;
        }
        (new ITILFollowup())->add([
            'itemtype'   => Ticket::class,
            'items_id'   => $tickets_id,
            'content'    => $html,
            'is_private' => 0,
            '_no_reopen' => true,
        ]);
    }

    private static function fecharChamado(int $tickets_id): void
    {
        $ticket = new Ticket();
        if ($ticket->getFromDB($tickets_id) && !in_array((int) $ticket->fields['status'], Ticket::getClosedStatusArray(), true)) {
            $ticket->update(['id' => $tickets_id, 'status' => CommonITILObject::CLOSED]);
        }
    }

    /**
     * A sala passou a usar outra agenda do Google: recria nela os eventos das
     * reservas futuras e apaga da agenda antiga.
     *
     * @return array{0:int, 1:string[]} [quantas foram movidas, falhas]
     */
    public static function moverParaNovaAgenda(Room $sala, string $agenda_antiga): array
    {
        $google  = new GoogleClient();
        $tz      = Availability::tz();
        $movidas = 0;
        $falhas  = [];

        $futuras = (new self())->find([
            'plugin_salasreuniao_rooms_id' => $sala->getID(),
            'status'                       => 'ativa',
            'end'                          => ['>', date('Y-m-d H:i:s')],
        ], ['begin ASC']);

        foreach ($futuras as $row) {
            $inicio = new DateTimeImmutable($row['begin'], $tz);
            try {
                $evento = self::evento(
                    $google,
                    $sala,
                    (int) $row['users_id'],
                    $inicio,
                    new DateTimeImmutable($row['end'], $tz),
                    (int) $row['participants'],
                    (string) $row['meeting_type'],
                    (string) $row['subject']
                );
                $criado = $google->criarEvento($sala->fields['google_calendar_id'], $evento);

                if ($agenda_antiga !== '' && $row['google_event_id'] !== '') {
                    try {
                        $google->excluirEvento($agenda_antiga, $row['google_event_id']);
                    } catch (Throwable $e) {
                        // A agenda antiga pode não estar mais compartilhada: o importante é a nova
                    }
                }

                (new self())->update([
                    'id'              => (int) $row['id'],
                    'google_event_id' => (string) ($criado['id'] ?? ''),
                ]);
                $movidas++;
            } catch (Throwable $e) {
                $falhas[] = sprintf('%s %s: %s', $inicio->format('d/m/Y H:i'), $sala->getName(), $e->getMessage());
            }
        }

        return [$movidas, $falhas];
    }

    private static function excluirEventoGoogle(GoogleClient $google, self $reserva): void
    {
        $sala = new Room();
        if ($reserva->fields['google_event_id'] !== '' && $sala->getFromDB($reserva->fields['plugin_salasreuniao_rooms_id'])) {
            $google->excluirEvento($sala->fields['google_calendar_id'], $reserva->fields['google_event_id']);
        }
    }

    private static function evento(
        GoogleClient $google,
        Room $sala,
        int $users_id,
        DateTimeImmutable $inicio,
        DateTimeImmutable $fim,
        int $pessoas,
        string $tipo,
        string $assunto
    ): array {
        $user  = new User();
        $nome  = $user->getFromDB($users_id) ? $user->getFriendlyName() : 'Usuário #' . $users_id;
        $email = (string) UserEmail::getDefaultForUser($users_id);

        $descricao = implode("\n", array_filter([
            'Agendado pelo GLPI.',
            'Responsável: ' . $nome . ($email !== '' ? ' <' . $email . '>' : ''),
            'Participantes: ' . $pessoas,
            'Tipo: ' . (self::TIPOS[$tipo] ?? $tipo),
            $assunto !== '' ? 'Assunto: ' . $assunto : '',
        ]));

        $evento = [
            'summary'     => ($assunto !== '' ? $assunto : 'Reunião') . ' — ' . $nome,
            'description' => $descricao,
            'location'    => trim($sala->getName() . ' ' . ($sala->fields['location'] ?? '')),
            'start'       => ['dateTime' => $inicio->format(DATE_RFC3339), 'timeZone' => $inicio->getTimezone()->getName()],
            'end'         => ['dateTime' => $fim->format(DATE_RFC3339), 'timeZone' => $fim->getTimezone()->getName()],
            'extendedProperties' => [
                'private' => [
                    'glpi_salasreuniao' => '1',
                    'participantes'     => (string) $pessoas,
                    'tipo'              => $tipo,
                ],
            ],
        ];

        // Convite para a agenda de quem agendou: só com Google Workspace (delegação)
        if ($google->usaDelegacao() && $email !== '') {
            $evento['attendees'] = [['email' => $email, 'responseStatus' => 'accepted']];
        }

        return $evento;
    }

    /**
     * @return array{ok:bool, msg:string}
     */
    private static function erro(string $msg): array
    {
        return ['ok' => false, 'msg' => $msg];
    }

    /**
     * Cabeçalho da página nas duas interfaces (padrão e Self-Service).
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
