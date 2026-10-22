<?php

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Chamadoti\Chamado;
use GlpiPlugin\Chamadoti\Prazos;
use GlpiPlugin\Chamadoti\Settings;
use GlpiPlugin\Chamadoti\Tela;

Session::checkLoginUser();

global $CFG_GLPI;

// Sem tela escolhida: com uma só tela ativa vai direto, senão escolhe o setor
$tela = Chamado::tela((int) ($_GET['tela'] ?? 0));
if ($tela === null) {
    $ativas = Tela::ativasParaMim();
    if (count($ativas) === 1) {
        Html::redirect($CFG_GLPI['root_doc'] . '/plugins/chamadoti/front/abrir.php?tela=' . (int) array_key_first($ativas));
    }
    Html::redirect($CFG_GLPI['root_doc'] . '/ServiceCatalog');
}

// Se o envio deu erro, o formulário volta preenchido
$antigo = $_SESSION['plugin_chamadoti_antigo'] ?? [];
unset($_SESSION['plugin_chamadoti_antigo']);
if ((int) ($antigo['tela'] ?? 0) !== (int) $tela->getID()) {
    $antigo = [];
}

$arvore = Chamado::arvore($tela);
$nos    = [];
foreach ($arvore['nos'] as $id => $no) {
    $nos[$id] = [
        'nome'      => $no['nome'],
        'pai'       => $no['pai'],
        'rotulo'    => $no['rotulo'],
        'filhos'    => $no['filhos'],
        'extras'    => $no['extras'],
        'campos'    => $no['campos'],
        'incidente' => $no['incidente'],
        'link'      => $no['link'] !== '' ? (str_starts_with($no['link'], '/') ? $CFG_GLPI['root_doc'] . $no['link'] : $no['link']) : '',
    ];
}
$setores = Chamado::setores();

Chamado::cabecalho((string) $tela->fields['titulo']);
TemplateRenderer::getInstance()->display('@chamadoti/abrir.html.twig', [
    'tela'              => $tela->fields,
    'opcoes'            => $tela->opcoes(),
    'antigo'            => $antigo,
    'antigo_campo'      => is_array($antigo['campo'] ?? null) ? $antigo['campo'] : [],
    'tipos'             => Chamado::tipos($arvore),
    'nos'               => $nos,
    'prioridades'       => Prazos::PRIORIDADES,
    'prazos'            => Prazos::porPrioridade(),
    'afetados'          => Chamado::AFETADOS,
    'equipamentos'      => Settings::lista('equipamentos'),
    'locais'            => Settings::lista('locais'),
    'locais_evento'     => Settings::lista('locais_evento'),
    'suporte_evento'    => Settings::lista('suporte_evento'),
    'periodos_procedimento' => Chamado::PERIODOS_PROCEDIMENTO,
    'cargos'            => Chamado::cargos(),
    'setores'           => $setores['setores'],
    'pessoas_setor'     => $setores['pessoas'],
    'meu_setor'         => Chamado::meuSetor(),
    'tipos_colaborador' => Chamado::TIPOS_COLABORADOR,
    'max_colaboradores' => Chamado::MAX_COLABORADORES,
    'extensoes'         => '.' . implode(',.', Chamado::EXTENSOES),
    'max_anexos'        => Chamado::MAX_ANEXOS,
    'max_mb'            => Chamado::MAX_MB,
    'outras_telas'      => count(Tela::ativasParaMim()) > 1,
]);
Chamado::rodape();
