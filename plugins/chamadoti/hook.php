<?php

use GlpiPlugin\Chamadoti\Settings;

function plugin_chamadoti_install()
{
    /** @var \DBmysql $DB */
    global $DB;

    $charset   = DBConnection::getDefaultCharset();
    $collation = DBConnection::getDefaultCollation();
    $sign      = DBConnection::getDefaultPrimaryKeySignOption();

    if (!$DB->tableExists('glpi_plugin_chamadoti_telas')) {
        $DB->doQuery("CREATE TABLE `glpi_plugin_chamadoti_telas` (
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `nome` varchar(255) DEFAULT NULL,
            `titulo` varchar(255) DEFAULT NULL,
            `icone` varchar(100) NOT NULL DEFAULT 'ti ti-headset',
            `descricao` varchar(255) DEFAULT NULL,
            `intro` text,
            `itilcategories_id` int {$sign} NOT NULL DEFAULT '0',
            `groups_id` int {$sign} NOT NULL DEFAULT '0',
            `ordem` int NOT NULL DEFAULT '0',
            `is_active` tinyint NOT NULL DEFAULT '1',
            `mostrar_titulo` tinyint NOT NULL DEFAULT '1',
            `mostrar_ramal` tinyint NOT NULL DEFAULT '1',
            `mostrar_anexos` tinyint NOT NULL DEFAULT '1',
            `mostrar_prioridade` tinyint NOT NULL DEFAULT '1',
            `prioridade_padrao` int NOT NULL DEFAULT '3',
            `config` longtext,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `is_active` (`is_active`),
            KEY `itilcategories_id` (`itilcategories_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC;");
    }

    // Valores padrão só para o que ainda não foi configurado
    $atuais = Config::getConfigurationValues(Settings::CONTEXT);
    $faltam = array_diff_key(Settings::DEFAULTS, $atuais);
    if ($faltam !== []) {
        Config::setConfigurationValues(Settings::CONTEXT, $faltam);
    }

    // 2.0.0: telas por setor. Cria TI (da configuração antiga) e Administrativo.
    if (countElementsInTable('glpi_plugin_chamadoti_telas') === 0) {
        plugin_chamadoti_telas_iniciais();
    }
    return true;
}

/**
 * Telas iniciais: TI (igual à tela que já existia) e Administrativo (no lugar do formulário antigo).
 */
function plugin_chamadoti_telas_iniciais(): void
{
    /** @var \DBmysql $DB */
    global $DB;

    $porCaminho = static function (array $mapa) use ($DB): array {
        $cats = [];
        foreach ($mapa as $caminho => $cfg) {
            $c = $DB->request(['SELECT' => 'id', 'FROM' => 'glpi_itilcategories', 'WHERE' => ['completename' => $caminho]])->current();
            if ($c) {
                $cats[(string) $c['id']] = $cfg;
            }
        }
        return ['cats' => $cats];
    };
    $agora = date('Y-m-d H:i:s');
    $sala  = (string) (Config::getConfigurationValues('plugin:salasreuniao', ['itilcategories_id'])['itilcategories_id'] ?? '');

    $ti = $porCaminho([
        'Ti > Erros'                           => ['ordem' => 1, 'extras' => ['impacto']],
        'Ti > Acessos e Senhas'                => ['ordem' => 2],
        'Ti > Acessos e Senhas > Criar Acesso' => ['extras' => ['colaboradores']],
        'Ti > Equipamentos'                    => ['ordem' => 3, 'extras' => ['equipamento']],
        'Ti > Instalação/Configuração'         => ['ordem' => 4],
        'Ti > Instalação/Configuração > Email' => ['extras' => ['emails']],
        'Ti > Backup'                          => ['ordem' => 5],
        'Ti > Backup > Restauração de Arquivo' => ['extras' => ['backup']],
        'Ti > Desenvolvimento'                 => ['ordem' => 6],
        'Ti > Relatórios'                      => ['ordem' => 7],
        'Ti > Contratação de Sistema'          => ['ordem' => 8],
        'Ti > Acesso às Câmeras'               => ['ordem' => 9],
        'Ti > Dúvidas'                         => ['ordem' => 10, 'texto' => 'Tire uma dúvida com a TI'],
    ]);
    $DB->insert('glpi_plugin_chamadoti_telas', [
        'nome'              => 'TI',
        'titulo'            => 'Chamado de TI',
        'icone'             => 'ti ti-device-desktop-cog',
        'descricao'         => 'Erros, acessos, equipamentos, instalações e outros pedidos para a TI',
        'intro'             => '',
        'itilcategories_id' => (int) Settings::get('itilcategories_id'),
        'groups_id'         => (int) Settings::get('groups_id_assign'),
        'ordem'             => 1,
        'config'            => json_encode($ti, JSON_UNESCAPED_UNICODE),
        'date_creation'     => $agora,
        'date_mod'          => $agora,
    ]);

    $adm = $porCaminho([
        'Adm > Manutenção'                       => ['ordem' => 1],
        'Adm > Manutenção > Geral'               => ['extras' => ['local']],
        'Adm > Manutenção > Veicular'            => ['extras' => ['veiculo']],
        'Adm > Solicitação'                      => ['ordem' => 2],
        'Adm > Solicitação > Motorista'          => ['extras' => ['agenda']],
        'Adm > Solicitação > Uber'               => ['extras' => ['agenda']],
        'Adm > Solicitação > Reserva de Veículo' => ['extras' => ['agenda']],
        'Adm > Compras'                          => ['ordem' => 3, 'extras' => ['itens']],
        'Adm > Eventos'                          => ['ordem' => 4, 'extras' => ['evento']],
        'Adm > Eventos > Interno'                => ['extras' => ['evento_interno']],
        'Adm > Eventos > Externo'                => ['extras' => ['evento_externo']],
        'Adm > Dúvidas'                          => ['ordem' => 5, 'texto' => 'Tire uma dúvida com o Administrativo'],
    ]);
    // Reserva de sala tem tela própria: o cartão leva até ela
    if ($sala !== '' && (int) $sala > 0) {
        $adm['cats'][$sala] = ['link' => '/plugins/salasreuniao/front/agendar.php'];
    }
    $grupoAdm = $DB->request(['SELECT' => 'id', 'FROM' => 'glpi_groups', 'WHERE' => ['name' => 'Administrativo']])->current();
    $raizAdm  = $DB->request(['SELECT' => 'id', 'FROM' => 'glpi_itilcategories', 'WHERE' => ['completename' => 'Adm']])->current();
    if ($raizAdm) {
        $DB->insert('glpi_plugin_chamadoti_telas', [
            'nome'              => 'Administrativo',
            'titulo'            => 'Chamado Administrativo',
            'icone'             => 'ti ti-building',
            'descricao'         => 'Manutenção, compras, eventos, chaves, motorista e outros pedidos',
            'intro'             => '',
            'itilcategories_id' => (int) $raizAdm['id'],
            'groups_id'         => (int) ($grupoAdm['id'] ?? 0),
            'ordem'             => 2,
            'config'            => json_encode($adm, JSON_UNESCAPED_UNICODE),
            'date_creation'     => $agora,
            'date_mod'          => $agora,
        ]);
    }
}

function plugin_chamadoti_uninstall()
{
    /** @var \DBmysql $DB */
    global $DB;

    if ($DB->tableExists('glpi_plugin_chamadoti_telas')) {
        $DB->doQuery('DROP TABLE `glpi_plugin_chamadoti_telas`');
    }
    Config::deleteConfigurationValues(Settings::CONTEXT, array_keys(Settings::DEFAULTS));
    return true;
}
