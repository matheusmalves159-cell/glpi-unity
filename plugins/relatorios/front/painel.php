<?php

use Glpi\Application\View\TemplateRenderer;
use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Relatorios\Dados;
use GlpiPlugin\Relatorios\Relatorio;

Session::checkLoginUser();
if (!Relatorio::canView()) {
    throw new AccessDeniedHttpException();
}

$filtros  = Dados::filtros($_GET);
$opcoes   = Dados::opcoes();
$chamados = Dados::chamados($filtros);
$analise  = Dados::analisar($chamados, $filtros);

// Dados dos gráficos (montados no navegador)
$graficos = [
    'evolucao'    => $analise['evolucao'],
    'status'      => $analise['status'],
    'tipos'       => $analise['tipos'],
    'origens'     => $analise['origens'],
    'prioridades' => array_map(fn ($p) => ['nome' => $p['nome'], 'total' => $p['total'], 'ttr_ok' => $p['ttr_ok'], 'ttr_atraso' => $p['ttr_atraso'], 'tto_ok' => $p['tto_ok'], 'tto_atraso' => $p['tto_atraso']], $analise['prioridades']),
    'setores'     => array_map(fn ($s) => ['nome' => $s['nome'], 'total' => $s['total']], array_slice(array_values(array_filter($analise['setores'], fn ($s) => $s['nome'] !== 'Sem setor')), 0, 12)),
    'requerentes' => array_map(fn ($s) => ['nome' => explode("\t", $s['nome'])[0], 'total' => $s['total']], array_slice(array_values(array_filter($analise['requerentes'], fn ($s) => !str_starts_with($s['nome'], 'Sem requerente'))), 0, 10)),
    'categorias'  => array_map(fn ($s) => ['nome' => $s['nome'], 'total' => $s['total']], array_slice($analise['categorias'], 0, 10)),
    'tecnicos'    => array_map(fn ($s) => [
        'nome'    => $s['nome'],
        'total'   => $s['total'],
        'solucao' => $s['media_solucao'] !== null ? round($s['media_solucao'] / 3600, 1) : null,
        'sla'     => $s['sla_solucao'],
    ], array_slice(array_values(array_filter($analise['tecnicos'], fn ($t) => $t['nome'] !== 'Sem técnico')), 0, 15)),
    'sla'         => ['tto' => [$analise['kpis']['tto_ok'], $analise['kpis']['tto_atraso']], 'ttr' => [$analise['kpis']['ttr_ok'], $analise['kpis']['ttr_atraso']]],
    'calor'       => $analise['calor'],
    'dias'        => Dados::DIAS,
];

// Tempos e datas já formatados para as tabelas
$texto = fn (array $lista) => array_map(fn ($g) => $g + [
    'resp_txt' => Dados::duracao($g['media_resposta']),
    'sol_txt'  => Dados::duracao($g['media_solucao']),
    'max_txt'  => Dados::duracao($g['sol_max'] ?: null),
], $lista);
foreach (['tecnicos', 'grupos', 'setores', 'requerentes', 'categorias', 'prioridades'] as $chave) {
    $analise[$chave] = $texto($analise[$chave]);
}
$analise['kpis'] += [
    'resp_txt' => Dados::duracao($analise['kpis']['media_resposta']),
    'sol_txt'  => Dados::duracao($analise['kpis']['media_solucao']),
];
$recentes = array_map(fn ($c) => $c + [
    'abertura_txt' => Dados::data($c['abertura']),
    'tempo_txt'    => Dados::duracao($c['tempo_solucao']),
], array_slice(array_reverse($chamados), 0, 200));

Html::header(Relatorio::getTypeName(), '', 'helpdesk', Relatorio::class);
TemplateRenderer::getInstance()->display('@relatorios/painel.html.twig', [
    'f'            => $filtros,
    'opcoes'       => $opcoes,
    'prioridades'  => Dados::PRIORIDADES,
    'tipos'        => Dados::TIPOS,
    'situacoes'    => Dados::SITUACOES,
    'a'            => $analise,
    'k'            => $analise['kpis'],
    'chamados'     => $recentes,
    'total'        => count($chamados),
    'graficos'     => $graficos,
    'sla_rotulos'  => Dados::SLA_ROTULOS,
]);
Html::footer();
