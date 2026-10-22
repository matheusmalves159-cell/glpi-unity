<?php

namespace GlpiPlugin\Chamadoti;

use Config;

/**
 * Listas usadas pelos campos extras das telas de chamado.
 */
class Settings
{
    public const CONTEXT = 'plugin:chamadoti';

    public const DEFAULTS = [
        // Usados só para criar a tela de TI na primeira instalação
        'itilcategories_id' => '516',
        'groups_id_assign'  => '57',
        // Opções do campo "Equipamento"
        'equipamentos'      => "Notebook\nDesktop\nMonitor\nTeclado\nMouse\nImpressora\nHeadSet\nTV\nOutros",
        // Locais/unidades (manutenção, agenda)
        'locais'            => "UNITY SAUDE ESCRITÓRIO\nUNIDADE CEILÂNDIA\nUNIDADE ASA SUL\nUNIDADE NOVA SEDE\nARQUIVO UNITY\nLOJA PATIO CAPITAL UNITY",
        // Eventos internos: onde pode acontecer
        'locais_evento'     => "Sala Branca\nSala Azul\nSala Amarela\nSala US\nÁrea Livre\nRefeitório\nSetor",
        // Eventos: o que precisa de suporte
        'suporte_evento'    => "WIFI\nComputador ou Notebook\nProjetor\nSom\nMicrofone\nMóveis\nKit itens personalizados\nMaterial de Papelaria\nOutros",
    ];

    /** Listas que aparecem na Central de telas */
    public const LISTAS = [
        'equipamentos'   => 'Equipamentos (TI)',
        'locais'         => 'Locais / unidades',
        'locais_evento'  => 'Locais de evento interno',
        'suporte_evento' => 'Suporte para eventos',
    ];

    /**
     * Lista configurada (uma opção por linha), sem linhas vazias ou repetidas.
     *
     * @return string[]
     */
    public static function lista(string $nome): array
    {
        $itens = array_map('trim', preg_split('/\R/', self::get($nome)) ?: []);
        return array_values(array_unique(array_filter($itens, fn ($i) => $i !== '')));
    }

    public static function get(string $nome): string
    {
        $valores = array_merge(self::DEFAULTS, Config::getConfigurationValues(self::CONTEXT));
        return (string) ($valores[$nome] ?? '');
    }

    public static function save(array $valores): void
    {
        Config::setConfigurationValues(self::CONTEXT, array_intersect_key($valores, self::DEFAULTS));
    }
}
