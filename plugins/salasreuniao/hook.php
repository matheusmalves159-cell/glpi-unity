<?php

use GlpiPlugin\Salasreuniao\Booking;
use GlpiPlugin\Salasreuniao\Painel;
use GlpiPlugin\Salasreuniao\Room;
use GlpiPlugin\Salasreuniao\Settings;

function plugin_salasreuniao_install()
{
    /** @var \DBmysql $DB */
    global $DB;

    $charset   = DBConnection::getDefaultCharset();
    $collation = DBConnection::getDefaultCollation();
    $sign      = DBConnection::getDefaultPrimaryKeySignOption();

    if (!$DB->tableExists('glpi_plugin_salasreuniao_rooms')) {
        $DB->doQuery("CREATE TABLE `glpi_plugin_salasreuniao_rooms` (
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `name` varchar(255) DEFAULT NULL,
            `google_calendar_id` varchar(255) NOT NULL DEFAULT '',
            `capacity` int NOT NULL DEFAULT '0',
            `location` varchar(255) DEFAULT NULL,
            `is_active` tinyint NOT NULL DEFAULT '1',
            `comment` text,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `name` (`name`),
            KEY `is_active` (`is_active`),
            KEY `date_creation` (`date_creation`),
            KEY `date_mod` (`date_mod`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC;");
    }

    if (!$DB->tableExists('glpi_plugin_salasreuniao_bookings')) {
        $DB->doQuery("CREATE TABLE `glpi_plugin_salasreuniao_bookings` (
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `plugin_salasreuniao_rooms_id` int {$sign} NOT NULL DEFAULT '0',
            `users_id` int {$sign} NOT NULL DEFAULT '0',
            `tickets_id` int {$sign} NOT NULL DEFAULT '0',
            `begin` timestamp NULL DEFAULT NULL,
            `end` timestamp NULL DEFAULT NULL,
            `duration` int NOT NULL DEFAULT '0',
            `participants` int NOT NULL DEFAULT '0',
            `meeting_type` varchar(10) NOT NULL DEFAULT 'interna',
            `subject` varchar(255) DEFAULT NULL,
            `google_event_id` varchar(255) NOT NULL DEFAULT '',
            `status` varchar(16) NOT NULL DEFAULT 'ativa',
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `plugin_salasreuniao_rooms_id` (`plugin_salasreuniao_rooms_id`),
            KEY `users_id` (`users_id`),
            KEY `tickets_id` (`tickets_id`),
            KEY `begin` (`begin`),
            KEY `end` (`end`),
            KEY `status` (`status`),
            KEY `date_creation` (`date_creation`),
            KEY `date_mod` (`date_mod`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC;");
    }

    // 1.1.0: cada reserva abre um chamado
    if (!$DB->fieldExists('glpi_plugin_salasreuniao_bookings', 'tickets_id')) {
        $DB->doQuery("ALTER TABLE `glpi_plugin_salasreuniao_bookings`
            ADD `tickets_id` int {$sign} NOT NULL DEFAULT '0' AFTER `users_id`,
            ADD KEY `tickets_id` (`tickets_id`)");
    }

    // Valores padrão só para o que ainda não foi configurado
    $atuais = Config::getConfigurationValues(Settings::CONTEXT);
    $faltam = array_diff_key(Settings::DEFAULTS, $atuais);
    if ($faltam !== []) {
        Config::setConfigurationValues(Settings::CONTEXT, $faltam);
    }

    // Colunas exibidas na lista de salas
    if (countElementsInTable(DisplayPreference::getTable(), ['itemtype' => Room::class, 'users_id' => 0]) === 0) {
        $prefs = new DisplayPreference();
        foreach ([3, 4, 5, 6] as $rank => $num) {
            $prefs->add([
                'itemtype' => Room::class,
                'num'      => $num,
                'rank'     => $rank + 1,
                'users_id' => 0,
            ]);
        }
    }

    // 1.4.0: cartões "Reuniões em andamento/agendadas" no painel da lista de chamados
    Painel::adicionarAoPainelDeChamados();

    // 1.2.0: status automático dos chamados de reserva (a cada minuto)
    CronTask::register(
        Booking::class,
        'statusreunioes',
        MINUTE_TIMESTAMP,
        [
            'mode'    => CronTask::MODE_EXTERNAL,
            'state'   => CronTask::STATE_WAITING,
            'comment' => 'Chamados de Reserva de Sala: em atendimento no início e fechados no fim da reunião',
        ]
    );

    return true;
}

/**
 * Hook "dashboard_cards": cartões "Reuniões em andamento" e "Reuniões agendadas".
 */
function plugin_salasreuniao_dashboard_cards($cartoes)
{
    return (is_array($cartoes) ? $cartoes : []) + Painel::cartoes();
}

function plugin_salasreuniao_uninstall()
{
    /** @var \DBmysql $DB */
    global $DB;

    foreach (['glpi_plugin_salasreuniao_bookings', 'glpi_plugin_salasreuniao_rooms'] as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `{$table}`");
        }
    }

    $DB->delete(DisplayPreference::getTable(), ['itemtype' => Room::class]);
    $DB->delete('glpi_dashboards_items', ['card_id' => ['LIKE', 'plugin_salasreuniao_%']]);
    Config::deleteConfigurationValues(Settings::CONTEXT, array_keys(Settings::DEFAULTS));
    CronTask::unregister('salasreuniao');

    return true;
}
