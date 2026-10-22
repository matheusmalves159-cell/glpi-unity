<?php

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Chamadoti\Acessos;

Session::checkRight('profile', UPDATE);

global $CFG_GLPI;
$base = $CFG_GLPI['root_doc'] . '/plugins/chamadoti/front/acessos.php';

if (isset($_POST['salvar_perfil'])) {
    $perfil = (int) ($_POST['profiles_id'] ?? 0);
    Acessos::salvarAreas($perfil, (array) ($_POST['area'] ?? []));
    Acessos::salvarDescricao($perfil, (string) ($_POST['descricao'] ?? ''));
    Session::addMessageAfterRedirect(htmlescape('Acessos do perfil salvos. Valem a partir do próximo login de quem usa o perfil.'));
    Html::redirect($base . '#perfil-' . $perfil);
}

if (isset($_POST['personalizar'])) {
    $usuario = (int) ($_POST['users_id'] ?? 0);
    $novo = Acessos::criarIndividual($usuario);
    Session::addMessageAfterRedirect(
        htmlescape($novo ? 'Perfil individual criado. Ajuste as áreas abaixo.' : 'Não foi possível personalizar este usuário.'),
        false,
        $novo ? INFO : ERROR
    );
    Html::redirect($base . '?aba=usuario&usuario=' . $usuario);
}

if (isset($_POST['salvar_usuario'])) {
    $usuario = (int) ($_POST['users_id'] ?? 0);
    $dados = Acessos::usuario($usuario);
    if ($dados && $dados['individual']) {
        Acessos::salvarAreas($dados['individual'], (array) ($_POST['area'] ?? []));
        Session::addMessageAfterRedirect(htmlescape('Acessos do usuário salvos. Valem a partir do próximo login dele.'));
    }
    Html::redirect($base . '?aba=usuario&usuario=' . $usuario);
}

if (isset($_POST['remover_individual'])) {
    $usuario = (int) ($_POST['users_id'] ?? 0);
    Acessos::removerIndividual($usuario);
    Session::addMessageAfterRedirect(htmlescape('Personalização desfeita: o usuário voltou ao perfil que tinha.'));
    Html::redirect($base . '?aba=usuario&usuario=' . $usuario);
}

$aba = ($_GET['aba'] ?? '') === 'usuario' ? 'usuario' : 'perfis';
$usuario = (int) ($_GET['usuario'] ?? 0);

Html::header(Acessos::getTypeName(), '', 'admin', Acessos::class);
TemplateRenderer::getInstance()->display('@chamadoti/acessos.html.twig', [
    'aba'      => $aba,
    'areas'    => Acessos::AREAS,
    'perfis'   => $aba === 'perfis' ? Acessos::perfis() : [],
    'usuarios' => $aba === 'usuario' ? Acessos::usuarios() : [],
    'usuario'  => $usuario > 0 ? Acessos::usuario($usuario) : null,
    'total_id' => Acessos::PERFIL_TOTAL,
    'meu'      => (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0),
]);
Html::footer();
