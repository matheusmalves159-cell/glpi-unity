<?php

use GlpiPlugin\Chamadoti\Tela;
use Symfony\Component\HttpFoundation\JsonResponse;

Session::checkLoginUser();

// Telas ativas para os cartões do Catálogo de serviços
$lista = [];
foreach (Tela::ativasParaMim() as $t) {
    $cfg = json_decode((string) $t['config'], true);
    $lista[] = [
        'id'        => (int) $t['id'],
        'titulo'    => (string) $t['titulo'],
        'descricao' => (string) $t['descricao'],
        'icone'     => (string) $t['icone'],
        'cor'       => (string) ($cfg['opcoes']['cor'] ?? ''),
    ];
}

return new JsonResponse($lista);
