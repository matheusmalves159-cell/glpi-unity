<?php

namespace GlpiPlugin\Salasreuniao;

use Config;
use GLPIKey;

/**
 * Configurações gerais do plugin (tabela de configuração do GLPI).
 */
class Settings
{
    public const CONTEXT = 'plugin:salasreuniao';

    public const DEFAULTS = [
        // Horário de funcionamento das salas
        'hora_inicio'         => '08:00',
        'hora_fim'            => '18:00',
        // 1 = segunda ... 7 = domingo
        'dias_semana'         => '1,2,3,4,5',
        // Tempo livre obrigatório entre duas reuniões na mesma sala
        'intervalo_min'       => '15',
        // De quanto em quanto tempo os horários são oferecidos
        'passo_min'           => '30',
        // Até quantos dias à frente é possível agendar
        'antecedencia_dias'   => '60',
        // Durações oferecidas no formulário (minutos)
        'duracoes'            => '30,60,90,120,180,240',
        // Google Workspace: conta em nome da qual os eventos são criados
        // (exige delegação em todo o domínio). Vazio = a própria conta de serviço.
        'conta_delegada'      => '',
        // Conteúdo JSON da chave da conta de serviço (criptografado)
        'service_account_key' => '',
        'api_url'             => 'https://www.googleapis.com/calendar/v3',
        // Chamado aberto a cada reserva
        'groups_id_assign'    => '0',
        'itilcategories_id'   => '0',
    ];

    /**
     * @return array<string, string>
     */
    /**
     * Dados da tela de configuração (usada também na Central de telas).
     */
    public static function dadosConfig(): array
    {
        $grupos = [];
        foreach ((new \Group())->find(['is_assign' => 1], ['completename ASC']) as $g) {
            $grupos[(int) $g['id']] = $g['completename'];
        }
        // Todas as categorias: a de reserva pode estar oculta nos chamados ("Exibir nos chamados" desligado)
        $categorias = [];
        foreach ((new \ITILCategory())->find([], ['completename ASC']) as $c) {
            $categorias[(int) $c['id']] = $c['completename'];
        }
        return [
            'v'             => self::all(),
            'grupos'        => $grupos,
            'categorias'    => $categorias,
            'dias'          => self::diasSemana(),
            'duracoes'      => self::duracoes(),
            'conta_servico' => GoogleClient::emailContaServico(),
            'tem_chave'     => GoogleClient::isConfigured(),
        ];
    }

    public static function all(): array
    {
        $valores = array_merge(self::DEFAULTS, Config::getConfigurationValues(self::CONTEXT));
        $valores['service_account_key'] = (string) (new GLPIKey())->decrypt($valores['service_account_key'] ?: null);
        return $valores;
    }

    public static function get(string $nome): string
    {
        return (string) (self::all()[$nome] ?? '');
    }

    public static function save(array $valores): void
    {
        Config::setConfigurationValues(self::CONTEXT, array_intersect_key($valores, self::DEFAULTS));
    }

    /**
     * @return int[]
     */
    public static function duracoes(): array
    {
        $lista = array_filter(array_map('intval', explode(',', self::get('duracoes'))), fn ($v) => $v > 0);
        sort($lista);
        return array_values(array_unique($lista));
    }

    /**
     * @return int[]
     */
    public static function diasSemana(): array
    {
        return array_values(array_filter(
            array_map('intval', explode(',', self::get('dias_semana'))),
            fn ($v) => $v >= 1 && $v <= 7
        ));
    }

    public static function rotuloDuracao(int $minutos): string
    {
        $h = intdiv($minutos, 60);
        $m = $minutos % 60;
        if ($h === 0) {
            return $m . ' min';
        }
        if ($m === 0) {
            return $h === 1 ? '1 hora' : $h . ' horas';
        }
        return sprintf('%dh%02d', $h, $m);
    }
}
