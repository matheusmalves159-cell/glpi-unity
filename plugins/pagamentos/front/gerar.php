<?php

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Pagamentos\Planilha;
use GlpiPlugin\Pagamentos\Tela;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

Session::checkLoginUser();
if (!Tela::canView()) {
    throw new AccessDeniedHttpException();
}

$filtros  = Tela::filtros($_GET);
// Com um setor escolhido, a planilha tem só a aba do período (não repete a aba do setor)
$conteudo = Planilha::gerar(Tela::pedidos($filtros), $filtros['de'], $filtros['ate'], $filtros['setor'] === '');
$nome     = Tela::nomeArquivo($filtros);

$resposta = new Response($conteudo);
$resposta->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
$resposta->headers->set('Content-Disposition', $resposta->headers->makeDisposition(
    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
    $nome,
    iconv('UTF-8', 'ASCII//TRANSLIT', $nome) ?: 'pagamentos.xlsx'
));
$resposta->headers->set('Cache-Control', 'no-store');

return $resposta;
