<?php

use GlpiPlugin\Pagamentos\Settings;

function plugin_pagamentos_install()
{
    /** @var \DBmysql $DB */
    global $DB;

    $charset   = DBConnection::getDefaultCharset();
    $collation = DBConnection::getDefaultCollation();
    $sign      = DBConnection::getDefaultPrimaryKeySignOption();

    if (!$DB->tableExists('glpi_plugin_pagamentos_pedidos')) {
        $DB->doQuery("CREATE TABLE `glpi_plugin_pagamentos_pedidos` (
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `tickets_id` int {$sign} NOT NULL DEFAULT '0',
            `users_id` int {$sign} NOT NULL DEFAULT '0',
            `local` varchar(255) DEFAULT NULL,
            `fornecedor` varchar(255) DEFAULT NULL,
            `documento` varchar(30) DEFAULT NULL,
            `valor` decimal(15,2) NOT NULL DEFAULT '0.00',
            `servico` text,
            `setor` varchar(255) DEFAULT NULL,
            `forma` varchar(50) DEFAULT NULL,
            `pix` varchar(255) DEFAULT NULL,
            `observacoes` text,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `tickets_id` (`tickets_id`),
            KEY `users_id` (`users_id`),
            KEY `date_creation` (`date_creation`),
            KEY `date_mod` (`date_mod`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC;");
    }

    $atuais = Config::getConfigurationValues(Settings::CONTEXT);
    $faltam = array_diff_key(Settings::DEFAULTS, $atuais);
    if ($faltam !== []) {
        Config::setConfigurationValues(Settings::CONTEXT, $faltam);
    }
    return true;
}

function plugin_pagamentos_uninstall()
{
    /** @var \DBmysql $DB */
    global $DB;

    if ($DB->tableExists('glpi_plugin_pagamentos_pedidos')) {
        $DB->doQuery('DROP TABLE `glpi_plugin_pagamentos_pedidos`');
    }
    Config::deleteConfigurationValues(Settings::CONTEXT, array_keys(Settings::DEFAULTS));
    return true;
}
