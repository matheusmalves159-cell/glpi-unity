<?php

use Glpi\Application\View\TemplateRenderer;
use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Pagamentos\Dados;
use GlpiPlugin\Pagamentos\Pedido;
use GlpiPlugin\Pagamentos\Settings;
use GlpiPlugin\Pagamentos\Tela;

Session::checkLoginUser();
if (!Tela::canView()) {
    throw new AccessDeniedHttpException();
}

$filtros = Tela::filtros($_GET);
$pedidos = Tela::pedidos($filtros);

// Setores para o filtro: os configurados + os que aparecem nos pedidos (ex.: formulário antigo)
$setores = Settings::lista('setores');
foreach (Dados::buscar($filtros['de'], $filtros['ate']) as $p) {
    if ($p['setor'] !== '' && !in_array($p['setor'], $setores, true)) {
        $setores[] = $p['setor'];
    }
}
sort($setores, SORT_LOCALE_STRING);

// Total por setor (quando há mais de um)
$por_setor = [];
foreach (Dados::porSetor($pedidos) as $setor => $lista) {
    $por_setor[$setor] = [
        'qtde'  => count($lista),
        'total' => array_sum(array_map(fn ($p) => (float) ($p['valor'] ?? 0), $lista)),
    ];
}

Html::header(Tela::getTypeName(), '', 'tools', Tela::class);
TemplateRenderer::getInstance()->display('@pagamentos/planilha.html.twig', [
    'filtros'     => $filtros,
    'setores'     => $setores,
    'formas'      => Pedido::FORMAS,
    'pedidos'     => $pedidos,
    'por_setor'   => $por_setor,
    'total'       => array_sum(array_map(fn ($p) => (float) ($p['valor'] ?? 0), $pedidos)),
    'nome_arquivo' => Tela::nomeArquivo($filtros),
    'gerar_url'   => '/plugins/pagamentos/front/gerar.php?' . http_build_query($filtros),
    'configurado' => true,
    'admin'       => Session::haveRight('empresa_pagamentos', UPDATE),
]);
Html::footer();
