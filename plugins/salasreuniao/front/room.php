<?php

use GlpiPlugin\Salasreuniao\Room;

Session::checkRight('empresa_salas', UPDATE);

Html::header(Room::getTypeName(Session::getPluralNumber()), '', 'config', Room::class);
Search::show(Room::class);
Html::footer();
