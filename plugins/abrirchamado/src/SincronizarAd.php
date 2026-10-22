<?php

namespace GlpiPlugin\Abrirchamado;

use CommonGLPI;
use CronTask;

/**
 * Sincronização automática de usuários com o AD (todo dia de madrugada):
 * cria quem entrou, atualiza grupos e perfis pelas regras de autorização,
 * desativa quem saiu do AD (ou foi desativado) e reativa quem voltou.
 */
class SincronizarAd extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return 'Sincronização com o AD';
    }

    public static function instalar(): void
    {
        CronTask::register(
            self::class,
            'sincronizarad',
            DAY_TIMESTAMP,
            [
                'mode'    => CronTask::MODE_EXTERNAL,
                'state'   => CronTask::STATE_WAITING,
                'hourmin' => 1,
                'hourmax' => 5,
                'comment' => 'Cria, atualiza e desativa usuários conforme o AD',
            ]
        );
    }

    public static function cronInfo($name)
    {
        return ['description' => 'Sincronizar usuários com o AD (criar novos, atualizar e desativar quem saiu)'];
    }

    public static function cronSincronizarad(CronTask $task)
    {
        $comando = 'php ' . escapeshellarg(GLPI_ROOT . '/bin/console') . ' ldap:synchronize_users'
            . ' --no-interaction --deleted-user-strategy=1,1,1 --restored-user-strategy=3 2>&1';

        $saida = [];
        $codigo = 0;
        exec($comando, $saida, $codigo);

        $resumo = trim(implode("\n", array_slice($saida, -8)));
        $task->log($resumo !== '' ? $resumo : 'Sincronização concluída');
        $task->addVolume(1);

        return $codigo === 0 ? 1 : -1;
    }
}
