<?php

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Chamadoti\Central;
use GlpiPlugin\Chamadoti\Prazos;
use GlpiPlugin\Chamadoti\Tela;

Session::checkRight('empresa_telas', UPDATE);

global $CFG_GLPI, $DB;
$central = $CFG_GLPI['root_doc'] . '/plugins/chamadoti/front/central.php';
$tela    = new Tela();
$id      = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);

if (isset($_POST['excluir']) && $id > 0) {
    $tela->delete(['id' => $id], true);
    Session::addMessageAfterRedirect(htmlescape('Tela excluída.'));
    Html::redirect($central);
}

// Duplicar: cópia desativada, para ajustar antes de publicar
if (isset($_POST['duplicar']) && $id > 0 && $tela->getFromDB($id)) {
    $copia = $tela->fields;
    unset($copia['id'], $copia['date_mod'], $copia['date_creation']);
    $copia['nome']      = mb_substr($copia['nome'] . ' (cópia)', 0, 100);
    $copia['titulo']    = mb_substr($copia['titulo'] . ' (cópia)', 0, 255);
    $copia['is_active'] = 0;
    $copia['ordem']     = count((new Tela())->find()) + 1;
    $novo = (int) (new Tela())->add($copia);
    Session::addMessageAfterRedirect(htmlescape('Tela duplicada. A cópia está desativada: ajuste e ative quando estiver pronta.'));
    Html::redirect($CFG_GLPI['root_doc'] . '/plugins/chamadoti/front/' . ($novo > 0 ? 'tela.form.php?id=' . $novo : 'central.php'));
}

if (isset($_POST['salvar'])) {
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $raiz = (int) ($_POST['itilcategories_id'] ?? 0);
    if ($nome === '' || $raiz <= 0) {
        Session::addMessageAfterRedirect(htmlescape('Informe o nome do setor e a categoria.'), false, ERROR);
        Html::back();
    }

    // Configuração por categoria (só o que difere do padrão)
    $cats = [];
    if ($id > 0 && $tela->getFromDB($id) && (int) $tela->fields['itilcategories_id'] === $raiz) {
        foreach (array_keys((array) ($_POST['cat'] ?? [])) as $cid) {
            $c = (array) $_POST['cat'][$cid];
            $cfg = array_filter([
                'oculto' => empty($c['mostrar']) ? 1 : 0,
                'ordem'  => max(0, min(999, (int) ($c['ordem'] ?? 0))),
                'icone'  => preg_replace('/[^a-z0-9-]/', '', mb_strtolower(trim((string) ($c['icone'] ?? '')))),
                'texto'  => mb_substr(trim((string) ($c['texto'] ?? '')), 0, 200),
                'extras' => array_values(array_intersect((array) ($c['extras'] ?? []), array_keys(Tela::EXTRAS))),
                'link'   => mb_substr(trim((string) ($c['link'] ?? '')), 0, 500),
                'campos' => Tela::normalizarCampos(array_values((array) ($c['campos'] ?? []))),
            ], fn ($v) => $v !== '' && $v !== 0 && $v !== []);
            if ($cfg !== []) {
                $cats[(string) (int) $cid] = $cfg;
            }
        }
    }

    $valores = [
        'nome'               => $nome,
        'titulo'             => trim((string) ($_POST['titulo'] ?? '')) ?: 'Chamado ' . $nome,
        'icone'              => trim((string) ($_POST['icone'] ?? '')) ?: 'ti ti-headset',
        'descricao'          => (string) ($_POST['descricao'] ?? ''),
        'intro'              => mb_substr(trim(str_replace("\r", '', (string) ($_POST['intro'] ?? ''))), 0, 2000),
        'itilcategories_id'  => $raiz,
        'groups_id'          => max(0, (int) ($_POST['groups_id'] ?? 0)),
        'ordem'              => max(0, min(999, (int) ($_POST['ordem'] ?? 0))),
        'is_active'          => empty($_POST['is_active']) ? 0 : 1,
        // Título sempre obrigatório; ramal/telefone não é mais pedido
        'mostrar_titulo'     => 1,
        'mostrar_ramal'      => 0,
        'mostrar_anexos'     => empty($_POST['mostrar_anexos']) ? 0 : 1,
        'mostrar_prioridade' => empty($_POST['mostrar_prioridade']) ? 0 : 1,
        'prioridade_padrao'  => isset(Prazos::PRIORIDADES[(int) ($_POST['prioridade_padrao'] ?? 3)]) ? (int) $_POST['prioridade_padrao'] : 3,
        'config'             => json_encode([
            'cats'   => $cats,
            'opcoes' => Tela::normalizarOpcoes((array) ($_POST['opcoes'] ?? [])),
        ], JSON_UNESCAPED_UNICODE),
    ];
    if ($id > 0) {
        $tela->update(['id' => $id] + $valores);
    } else {
        $id = (int) $tela->add($valores);
    }
    Session::addMessageAfterRedirect(htmlescape('Tela salva.'));
    Html::redirect($CFG_GLPI['root_doc'] . '/plugins/chamadoti/front/tela.form.php?id=' . $id);
}

// Formulário
if ($id > 0 && !$tela->getFromDB($id)) {
    Html::redirect($central);
}
if ($id === 0) {
    $tela->fields = [
        'id' => 0, 'nome' => '', 'titulo' => '', 'icone' => 'ti ti-headset', 'descricao' => '', 'intro' => '',
        'itilcategories_id' => 0, 'groups_id' => 0, 'ordem' => count((new Tela())->find()) + 1, 'is_active' => 1,
        'mostrar_titulo' => 1, 'mostrar_ramal' => 1, 'mostrar_anexos' => 1, 'mostrar_prioridade' => 1, 'prioridade_padrao' => 3, 'config' => '',
    ];
}

// Categorias que podem ser raiz (têm subcategorias)
$raizes = [];
foreach ($DB->request([
    'SELECT'   => ['c.id', 'c.completename'],
    'DISTINCT' => true,
    'FROM'     => 'glpi_itilcategories AS c',
    'INNER JOIN' => ['glpi_itilcategories AS f' => ['ON' => ['f' => 'itilcategories_id', 'c' => 'id']]],
    'ORDER'    => 'c.completename',
]) as $c) {
    $raizes[(int) $c['id']] = $c['completename'];
}
$grupos = [];
foreach ((new Group())->find(['is_assign' => 1], ['completename ASC']) as $g) {
    $grupos[(int) $g['id']] = $g['completename'];
}

// Todos os grupos (para escolher quem vê a tela)
$gruposTodos = [];
foreach ((new Group())->find([], ['completename ASC']) as $g) {
    $gruposTodos[(int) $g['id']] = $g['completename'];
}

Html::header(Central::getTypeName(), '', 'config', Central::class);
TemplateRenderer::getInstance()->display('@chamadoti/tela_form.html.twig', [
    't'            => $tela->fields,
    'opcoes'       => $tela->opcoes(),
    'raizes'       => $raizes,
    'grupos'       => $grupos,
    'grupos_todos' => $gruposTodos,
    'categorias'   => $id > 0 ? $tela->categorias() : [],
    'extras'       => Tela::EXTRAS,
    'visual'       => Tela::VISUAL_PADRAO,
    'prioridades'  => Prazos::PRIORIDADES,
    'cores'        => Tela::CORES,
    'desc_modos'   => Tela::DESCRICAO_MODOS,
    'tipos_chamado' => Tela::TIPOS_CHAMADO,
    'tipos_campo'  => Tela::TIPOS_CAMPO,
]);
Html::footer();
