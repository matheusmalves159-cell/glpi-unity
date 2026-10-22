<?php

use GlpiPlugin\Abrirchamado\Perfil;

/**
 * Grava a aba "Módulos da Empresa" do perfil (Administração > Perfis).
 */

Session::checkLoginUser();
Session::checkRight('profile', UPDATE);

$perfil = (int) ($_POST['profiles_id'] ?? 0);

if (isset($_POST['salvar']) && $perfil > 0) {
    Perfil::salvar($perfil, is_array($_POST['d'] ?? null) ? $_POST['d'] : []);
    Session::addMessageAfterRedirect(htmlescape('Permissões dos módulos salvas. Valem no próximo login de quem usa o perfil.'));
}

Html::back();
