<?php

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pagamentos\Settings;
use GlpiPlugin\Pagamentos\Tela;

Session::checkRight('empresa_pagamentos', UPDATE);

if (isset($_POST['update'])) {
    $ids   = static fn ($v) => implode(',', array_filter(array_map('intval', (array) $v), fn ($i) => $i > 0));
    $linhas = static function ($v): string {
        $itens = array_map('trim', preg_split('/\R/', (string) $v) ?: []);
        return implode("\n", array_values(array_unique(array_filter($itens, fn ($i) => $i !== ''))));
    };
    $setores = $linhas($_POST['setores'] ?? '');
    $locais  = $linhas($_POST['locais'] ?? '');
    if ($setores === '' || $locais === '') {
        Session::addMessageAfterRedirect(htmlescape('Informe pelo menos um setor e um local.'), false, ERROR);
        Html::back();
    }
    Settings::save([
        'setores'            => $setores,
        'locais'             => $locais,
        'aviso'              => trim(str_replace("\r", '', (string) ($_POST['aviso'] ?? ''))),
        'forms_ids'          => $ids($_POST['forms_ids'] ?? []),
        'groups_ids'         => $ids($_POST['groups_ids'] ?? []),
        'request_groups_ids' => $ids($_POST['request_groups_ids'] ?? []),
        'groups_id_assign'   => (string) max(0, (int) ($_POST['groups_id_assign'] ?? 0)),
        'itilcategories_id'  => (string) max(0, (int) ($_POST['itilcategories_id'] ?? 0)),
    ]);
    Session::addMessageAfterRedirect(htmlescape('Configuração salva.'));
    Html::back();
}

Html::header(Tela::getTypeName(), '', 'tools', Tela::class);
TemplateRenderer::getInstance()->display('@pagamentos/config.html.twig', Settings::dadosConfig());
Html::footer();
