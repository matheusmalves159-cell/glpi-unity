<?php
use Glpi\Kernel\Kernel;
use GlpiPlugin\Abrirchamado\Computadores;
require '/var/www/glpi/vendor/autoload.php';
$kernel = new Kernel('production'); $kernel->boot();
global $DB;
include_once '/var/www/glpi/plugins/abrirchamado/hook.php';
$_SESSION['glpiID']=2; $_SESSION['glpiname']='glpi'; $_SESSION['glpiactive_entity']=0; $_SESSION['glpiactiveentities']=[0]; $_SESSION['glpiactiveentities_string']='0'; $_SESSION['glpi_use_mode']=0;

echo "constantes: limpar=" . Glpi\Inventory\Conf::STALE_AGENT_ACTION_CLEAN . " mudar status=" . Glpi\Inventory\Conf::STALE_AGENT_ACTION_STATUS . "\n";

// Estados
$emUso = Computadores::estado('Em uso');
$semCom = Computadores::estado('Sem comunicação');

// Inventário automático: computador novo entra "Em uso"; sem contato por 30 dias vira "Sem comunicação"
Config::setConfigurationValues('inventory', [
    'states_id_default'             => $emUso,
    'stale_agents_delay'            => Computadores::DIAS_SEM_CONTATO,
    'stale_agents_action'           => json_encode([(string) Glpi\Inventory\Conf::STALE_AGENT_ACTION_STATUS]),
    'stale_agents_status'           => $semCom,
    'stale_agents_status_condition' => json_encode(['all']),
]);

// Grupos podem ser dono de equipamentos (cada setor tem os seus)
$DB->update('glpi_groups', ['is_itemgroup' => 1], ['id' => ['>', 0]]);

// Colunas da lista de computadores
$DB->delete('glpi_displaypreferences', ['itemtype' => 'Computer', 'users_id' => 0]);
foreach ([1, 31, 70, 71, 3, 45, 46, 40, 9] as $rank => $num) {
    $DB->insert('glpi_displaypreferences', ['itemtype' => 'Computer', 'num' => $num, 'rank' => $rank, 'users_id' => 0]);
}

// Vincula os computadores existentes ao usuário, grupo e setor
$ids = []; foreach ($DB->request(['SELECT' => 'id', 'FROM' => 'glpi_computers', 'WHERE' => ['is_deleted' => 0, 'is_template' => 0]]) as $r) { $ids[] = (int) $r['id']; }
$vinc = 0; foreach ($ids as $id) { if (Computadores::vincular($id)) { $vinc++; } }

// Estado dos existentes pelo último contato do agente
$limite = date('Y-m-d H:i:s', strtotime('-' . Computadores::DIAS_SEM_CONTATO . ' days'));
$sem = 0; $uso = 0;
foreach ($ids as $id) {
    $ult = $DB->request(['SELECT' => [new Glpi\DBAL\QueryExpression('MAX(last_contact) AS m')],'FROM' => 'glpi_agents', 'WHERE' => ['itemtype' => 'Computer', 'items_id' => $id]])->current();
    $estado = ($ult && $ult['m'] !== null && $ult['m'] >= $limite) ? $emUso : $semCom;
    $DB->update('glpi_computers', ['states_id' => $estado], ['id' => $id, 'states_id' => 0]);
    $estado === $emUso ? $uso++ : $sem++;
}
echo "computadores: " . count($ids) . " | vinculados a usuário agora: $vinc | em uso: $uso | sem comunicação: $sem\n";
