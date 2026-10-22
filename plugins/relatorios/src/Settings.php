<?php

namespace GlpiPlugin\Relatorios;

use Config;

class Settings
{
    public const CONTEXT = 'plugin:relatorios';

    public const DEFAULTS = [
        // Calendário para contar o tempo em horário comercial (o mesmo dos SLAs)
        'calendars_id' => '2',
    ];

    public static function get(string $nome): string
    {
        $valores = array_merge(self::DEFAULTS, Config::getConfigurationValues(self::CONTEXT));
        return (string) ($valores[$nome] ?? '');
    }
}
