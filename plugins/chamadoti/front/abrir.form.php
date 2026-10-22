<?php

use GlpiPlugin\Chamadoti\Chamado;

Session::checkLoginUser();

global $CFG_GLPI;
$base = $CFG_GLPI['root_doc'] . '/plugins/chamadoti/front';
$tela = (int) ($_POST['tela'] ?? 0);

if (isset($_POST['enviar'])) {
    $resultado = Chamado::criar($_POST, $_FILES['anexos'] ?? []);
    Session::addMessageAfterRedirect(htmlescape($resultado['msg']), false, $resultado['ok'] ? INFO : ERROR);
    if ($resultado['ok']) {
        Html::redirect($CFG_GLPI['root_doc'] . '/front/ticket.php');
    }
    // Volta com o que foi digitado (anexos precisam ser escolhidos de novo)
    $_SESSION['plugin_chamadoti_antigo'] = array_intersect_key($_POST, array_flip([
        'tela', 'categoria', 'titulo', 'descricao', 'urgencia', 'campo', 'afetados', 'desde', 'equipamentos', 'patrimonio',
        'emails', 'caminho', 'versao_de', 'versao_ate',
        'col_nome', 'col_cargo', 'col_cpf', 'col_nasc', 'col_tipo', 'col_setor', 'col_perfil',
        'itens', 'local', 'local_detalhe', 'veiculo', 'agenda_data', 'agenda_origem', 'agenda_destino', 'agenda_passageiros',
        'evento_data', 'evento_participantes', 'evento_suporte', 'evento_itens', 'evento_locais', 'evento_empresa', 'evento_localizacao',
        'proc_operacao', 'proc_codigo', 'proc_tabela', 'proc_descricao', 'proc_porte', 'proc_sexo', 'proc_idade_ini', 'proc_idade_fim',
        'proc_pendencia', 'proc_urgencia', 'proc_dut', 'proc_dut_qual', 'proc_tipo',
        'proc_carencia', 'proc_limite', 'proc_limite_grupo', 'proc_qtd', 'proc_qtd_max', 'proc_qtd_periodo',
        'proc_dependencia', 'proc_dependencia_qual',
    ]));
}

Html::redirect($base . '/abrir.php?tela=' . $tela);
