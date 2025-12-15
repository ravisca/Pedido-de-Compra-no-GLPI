<?php

namespace GlpiPlugin\Meuplugin;

use CommonGLPI;
use Html;
use Session;

// Herda de CommonGLPI para ganhar funcionalidades nativas (logs, links, direitos)
class Dashboard extends CommonGLPI {

    // Define o nome que aparece no título da página
    static function getTypeName($nb = 0) {
        return 'Meu Painel Personalizado';
    }

    // Função principal que desenha o conteúdo
    static function displayContent() {
        // Exibe o cabeçalho padrão do GLPI
        // O primeiro parâmetro nulo diz que não há um menu de "voltar" específico
        Html::header(self::getTypeName(), $_SERVER['PHP_SELF'], "tools", "plugin_meuplugin_menu");

        echo "<div class='card p-4 m-4'>";
        echo "<h2>Olá, " . $_SESSION['glpifirstname'] . "!</h2>";
        echo "<p>Este é o conteúdo do seu plugin rodando no GLPI 10.</p>";
        
        // Exemplo de uso de ícones novos
        echo "<i class='ti ti-check fs-1 text-success'></i> Sistema Operacional Ok";
        echo "</div>";

        // Exibe o rodapé padrão
        Html::footer();
    }
}