<?php

Session::checkLoginUser();

global $CFG_GLPI;

// "Meus chamados" usa a lista de chamados do GLPI (com os filtros "Estou atendendo" / "Abri")
Html::redirect($CFG_GLPI['root_doc'] . '/front/ticket.php');
