<?php

namespace GlpiPlugin\Chamadoti;

use CommonGLPI;
use Session;

/**
 * Central de telas (Configurar): telas de chamado por setor, solicitação de
 * pagamento, salas de reunião e listas usadas nos campos.
 */
class Central extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return 'Central de telas';
    }

    public static function getIcon()
    {
        return 'ti ti-layout-dashboard';
    }

    public static function canView(): bool
    {
        return Session::haveRight('empresa_telas', UPDATE);
    }

    public static function getMenuContent()
    {
        return [
            'title' => self::getTypeName(),
            'page'  => '/plugins/chamadoti/front/central.php',
            'icon'  => self::getIcon(),
            'links' => [
                'add' => '/plugins/chamadoti/front/tela.form.php',
            ],
        ];
    }
}
