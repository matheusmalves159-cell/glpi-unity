<?php

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Chamadoti\Central;
use GlpiPlugin\Chamadoti\Settings;
use GlpiPlugin\Chamadoti\Tela;

Session::checkRight('empresa_telas', UPDATE);

// Aba "Listas": opções dos campos (uma por linha)
if (isset($_POST['salvar_listas'])) {
    $valores = [];
    foreach (array_keys(Settings::LISTAS) as $nome) {
        $itens = array_map('trim', preg_split('/\R/', (string) ($_POST[$nome] ?? '')) ?: []);
        $valores[$nome] = implode("\n", array_values(array_unique(array_filter($itens, fn ($i) => $i !== ''))));
    }
    Settings::save($valores);
    Session::addMessageAfterRedirect(htmlescape('Listas salvas.'));
    Html::back();
}

$abas = ['telas' => ['Telas de chamado', 'ti ti-layout-grid']];
if (Plugin::isPluginActive('pagamentos')) {
    $abas['pagamentos'] = ['Solicitação de pagamento', 'ti ti-cash'];
}
if (Plugin::isPluginActive('salasreuniao')) {
    $abas['salas'] = ['Salas de Reunião', 'ti ti-calendar-event'];
}
$abas['listas'] = ['Listas dos campos', 'ti ti-list'];
$aba = array_key_exists((string) ($_GET['aba'] ?? ''), $abas) ? (string) $_GET['aba'] : 'telas';

$dados = ['abas' => $abas, 'aba' => $aba];
switch ($aba) {
    case 'telas':
        $telas = [];
        foreach ((new Tela())->find([], ['ordem ASC', 'nome ASC']) as $t) {
            $cfg = json_decode((string) $t['config'], true);
            $telas[] = $t + [
                'cor'       => (string) ($cfg['opcoes']['cor'] ?? ''),
                'restrita'  => !empty($cfg['opcoes']['grupos_ver']),
                'categoria' => Dropdown::getDropdownName('glpi_itilcategories', (int) $t['itilcategories_id']),
                'grupo'     => (int) $t['groups_id'] > 0 ? Dropdown::getDropdownName('glpi_groups', (int) $t['groups_id']) : '—',
            ];
        }
        $dados['telas'] = $telas;
        break;
    case 'pagamentos':
        $dados['pagamentos'] = GlpiPlugin\Pagamentos\Settings::dadosConfig();
        break;
    case 'salas':
        $dados['salas'] = GlpiPlugin\Salasreuniao\Settings::dadosConfig();
        break;
    case 'listas':
        $listas = [];
        foreach (Settings::LISTAS as $nome => $rotulo) {
            $listas[$nome] = ['rotulo' => $rotulo, 'valor' => implode("\n", Settings::lista($nome))];
        }
        $dados['listas'] = $listas;
        break;
}

Html::header(Central::getTypeName(), '', 'config', Central::class);
TemplateRenderer::getInstance()->display('@chamadoti/central.html.twig', $dados);
Html::footer();
