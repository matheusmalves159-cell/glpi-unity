<?php

use Glpi\Event;
use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Relatorios\Dados;
use GlpiPlugin\Relatorios\Excel;
use GlpiPlugin\Relatorios\Pdf;
use GlpiPlugin\Relatorios\Relatorio;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

Session::checkLoginUser();
if (!Relatorio::canView()) {
    throw new AccessDeniedHttpException();
}

// Relatórios grandes: mais tempo e memória que uma página comum
@set_time_limit(300);
@ini_set('memory_limit', '1024M');

$filtros  = Dados::filtros($_POST);
$opcoes   = Dados::opcoes();
$chamados = Dados::chamados($filtros);
$analise  = Dados::analisar($chamados, $filtros);
$descr    = Dados::descreverFiltros($filtros, $opcoes);
$usuario  = getUserName(Session::getLoginUserID());
$pdf      = ($_POST['formato'] ?? '') === 'pdf';

$arquivo = $pdf
    ? Pdf::gerar($chamados, $analise, $descr, $usuario, array_map('strval', (array) ($_POST['graficos'] ?? [])))
    : Excel::gerar($chamados, $analise, $descr, $usuario);
$conteudo = (string) file_get_contents($arquivo);
@unlink($arquivo);

// Registro para auditoria: quem exportou, o quê e quando
Event::log(0, 'system', 4, 'tools', sprintf(
    '%s exportou o relatório de chamados (%s, %s, %d chamados)',
    $usuario,
    $pdf ? 'PDF' : 'Excel',
    $descr['Período'],
    count($chamados)
));

$nome = sprintf('relatorio_chamados_%s_a_%s.%s', $filtros['de'], $filtros['ate'], $pdf ? 'pdf' : 'xlsx');
$resposta = new Response($conteudo);
$resposta->headers->set('Content-Type', $pdf ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
$resposta->headers->set('Content-Disposition', $resposta->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $nome));
$resposta->headers->set('Cache-Control', 'no-store');

return $resposta;
