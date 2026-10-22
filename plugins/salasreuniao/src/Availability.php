<?php

namespace GlpiPlugin\Salasreuniao;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/**
 * Calcula os horários livres de cada sala a partir da agenda do Google
 * e das reservas feitas pelo GLPI.
 */
class Availability
{
    private const DIAS = [1 => 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado', 'domingo'];

    /**
     * Salas que comportam as pessoas, cada uma com seus horários livres no dia.
     *
     * @return array<int, array{id:int, nome:string, capacidade:int, local:string, horarios:string[], erro:string}>
     */
    public static function doDia(string $data, int $duracao, int $pessoas, int $ignorar_reserva = 0): array
    {
        self::validar($data, $duracao, $pessoas);

        $google = new GoogleClient();
        $salas  = [];
        foreach (Room::paraPessoas($pessoas) as $sala) {
            $item = [
                'id'         => (int) $sala['id'],
                'nome'       => (string) $sala['name'],
                'capacidade' => (int) $sala['capacity'],
                'local'      => (string) ($sala['location'] ?? ''),
                'horarios'   => [],
                'erro'       => '',
            ];
            try {
                $item['horarios'] = self::horariosDaSala($google, $sala, $data, $duracao, $ignorar_reserva);
            } catch (Throwable $e) {
                $item['erro'] = 'Não foi possível consultar a agenda desta sala: ' . $e->getMessage();
            }
            $salas[] = $item;
        }
        return $salas;
    }

    /**
     * Horários (HH:MM) em que uma reunião da duração informada cabe na sala,
     * deixando o intervalo configurado antes e depois.
     *
     * @return string[]
     */
    public static function horariosDaSala(GoogleClient $google, array $sala, string $data, int $duracao, int $ignorar_reserva = 0): array
    {
        $tz        = self::tz();
        $intervalo = max(0, (int) Settings::get('intervalo_min'));
        $passo     = max(5, (int) Settings::get('passo_min'));

        $abre  = new DateTimeImmutable($data . ' ' . Settings::get('hora_inicio') . ':00', $tz);
        $fecha = new DateTimeImmutable($data . ' ' . Settings::get('hora_fim') . ':00', $tz);

        $ocupado = self::ocupados($google, $sala, $abre->modify('-1 day'), $fecha->modify('+1 day'), $ignorar_reserva);

        $agora    = new DateTimeImmutable('now', $tz);
        $horarios = [];
        for ($inicio = $abre; $inicio->modify("+{$duracao} minutes") <= $fecha; $inicio = $inicio->modify("+{$passo} minutes")) {
            if ($inicio <= $agora) {
                continue;
            }
            $fim = $inicio->modify("+{$duracao} minutes");
            // Precisa de "intervalo" minutos livres antes e depois da reunião
            $de  = $inicio->modify("-{$intervalo} minutes");
            $ate = $fim->modify("+{$intervalo} minutes");
            $livre = true;
            foreach ($ocupado as [$oc_ini, $oc_fim]) {
                if ($oc_ini < $ate && $oc_fim > $de) {
                    $livre = false;
                    break;
                }
            }
            if ($livre) {
                $horarios[] = $inicio->format('H:i');
            }
        }
        return $horarios;
    }

    /**
     * Períodos ocupados da sala: eventos do Google + reservas ativas do GLPI.
     *
     * @return array<int, array{0:DateTimeImmutable, 1:DateTimeImmutable}>
     */
    private static function ocupados(GoogleClient $google, array $sala, DateTimeImmutable $de, DateTimeImmutable $ate, int $ignorar_reserva): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $tz = self::tz();

        // Evento da reserva que está sendo remarcada não conta como ocupado
        $ignorar_evento = '';
        $reservas_criterio = [
            'plugin_salasreuniao_rooms_id' => (int) $sala['id'],
            'status'                       => 'ativa',
            'begin'                        => ['<', $ate->format('Y-m-d H:i:s')],
            'end'                          => ['>', $de->format('Y-m-d H:i:s')],
        ];
        if ($ignorar_reserva > 0) {
            $reservas_criterio['NOT'] = ['id' => $ignorar_reserva];
            $anterior = (new Booking())->find(['id' => $ignorar_reserva]);
            $anterior = reset($anterior);
            if ($anterior && (int) $anterior['plugin_salasreuniao_rooms_id'] === (int) $sala['id']) {
                $ignorar_evento = (string) $anterior['google_event_id'];
            }
        }

        $ocupado = [];
        foreach ($google->listarEventos($sala['google_calendar_id'], $de, $ate) as $ev) {
            if (
                ($ev['status'] ?? '') === 'cancelled'
                || ($ev['transparency'] ?? '') === 'transparent'
                || ($ignorar_evento !== '' && ($ev['id'] ?? '') === $ignorar_evento)
            ) {
                continue;
            }
            $ini = self::dataGoogle($ev['start'] ?? [], $tz);
            $fim = self::dataGoogle($ev['end'] ?? [], $tz);
            if ($ini !== null && $fim !== null) {
                $ocupado[] = [$ini, $fim];
            }
        }

        // Reservas do GLPI (protege contra atraso do Google em refletir um evento recém-criado)
        foreach ($DB->request(['FROM' => Booking::getTable(), 'WHERE' => $reservas_criterio]) as $r) {
            $ocupado[] = [new DateTimeImmutable($r['begin'], $tz), new DateTimeImmutable($r['end'], $tz)];
        }

        return $ocupado;
    }

    private static function dataGoogle(array $valor, DateTimeZone $tz): ?DateTimeImmutable
    {
        if (!empty($valor['dateTime'])) {
            return (new DateTimeImmutable($valor['dateTime']))->setTimezone($tz);
        }
        if (!empty($valor['date'])) {
            // Evento de dia inteiro
            return new DateTimeImmutable($valor['date'] . ' 00:00:00', $tz);
        }
        return null;
    }

    public static function validar(string $data, int $duracao, int $pessoas): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || strtotime($data) === false) {
            throw new RuntimeException('Escolha o dia da reunião.');
        }
        if (!in_array($duracao, Settings::duracoes(), true)) {
            throw new RuntimeException('Escolha a duração da reunião.');
        }
        if ($pessoas < 1) {
            throw new RuntimeException('Informe quantas pessoas vão participar.');
        }

        $tz    = self::tz();
        $dia   = new DateTimeImmutable($data, $tz);
        $hoje  = new DateTimeImmutable('today', $tz);
        $limite = $hoje->modify('+' . (int) Settings::get('antecedencia_dias') . ' days');
        if ($dia < $hoje) {
            throw new RuntimeException('Não é possível agendar em uma data que já passou.');
        }
        if ($dia > $limite) {
            throw new RuntimeException(sprintf(
                'É possível agendar com até %d dias de antecedência (até %s).',
                (int) Settings::get('antecedencia_dias'),
                $limite->format('d/m/Y')
            ));
        }
        $dia_semana = (int) $dia->format('N');
        if (!in_array($dia_semana, Settings::diasSemana(), true)) {
            throw new RuntimeException(sprintf('Não há agendamento de salas neste dia da semana (%s).', self::DIAS[$dia_semana]));
        }
    }

    public static function tz(): DateTimeZone
    {
        return new DateTimeZone(date_default_timezone_get());
    }
}
