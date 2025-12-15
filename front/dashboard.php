<?php

include ("../../../inc/includes.php");

// Verifica se o usuário está logado e tem permissão de leitura
Session::checkRight("config", READ);

// Chama a função estática da nossa classe que está em /src/Dashboard.php
GlpiPlugin\Meuplugin\Dashboard::displayContent();