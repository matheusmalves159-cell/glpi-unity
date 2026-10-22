<?php

namespace GlpiPlugin\Relatorios;

use CommonGLPI;
use Session;

/**
 * Item de menu e permissão dos relatórios.
 */
class Relatorio extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return 'Relatórios de chamados';
    }

    public static function getIcon()
    {
        return 'ti ti-report-analytics';
    }

    /**
     * Quem tem o direito "Estatísticas" do GLPI, além dos administradores.
     */
    public static function canView(): bool
    {
        return Session::getLoginUserID() !== false
            && Session::haveRight('empresa_relatorios', READ);
    }

    public static function getMenuContent()
    {
        return [
            'title' => self::getTypeName(),
            'page'  => '/plugins/relatorios/front/painel.php',
            'icon'  => self::getIcon(),
        ];
    }
}
