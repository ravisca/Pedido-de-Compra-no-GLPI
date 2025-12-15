<?php

// Segurança: Bloqueia acesso direto via URL fora do GLPI
if (!defined('GLPI_ROOT')) {
    die("Desculpe. Você não pode acessar este arquivo diretamente.");
}

// Constantes para facilitar o uso no código
define('PLUGIN_VITRUVIO_VERSION', '1.0.1');
define('PLUGIN_VITRUVIO_DIR', 'vitruvio');

/**
 * Função de Inicialização (OBRIGATÓRIA)
 * É aqui que carregamos classes, menus e CSS/JS.
 */
function plugin_init_vitruvio() {
    global $PLUGIN_HOOKS;

    // Proteção CSRF (Padrão de segurança do GLPI 10)
    $PLUGIN_HOOKS['csrf_compliant']['vitruvio'] = true;

    // Registra a classe Dashboard
    if (class_exists('Plugin')) {
        Plugin::registerClass('GlpiPlugin\Vitruvio\Dashboard', [
            'addtabon' => [] 
        ]);
    }

    // Adiciona o botão no menu "Ferramentas"
    if (Session::haveRight('config', READ)) {
        $PLUGIN_HOOKS['menu_entry']['vitruvio'] = [
            'title' => 'Painel Vitruvio',
            'page'  => '/plugins/vitruvio/front/dashboard.php',
            'icon'  => 'ti ti-box'
        ];
    }

if (strpos($_SERVER['PHP_SELF'], 'ticket.form.php') !== false) {
        $PLUGIN_HOOKS['add_javascript']['vitruvio'][] = 'js/sweetalert2.all.min.js';
        $PLUGIN_HOOKS['add_javascript']['vitruvio'][] = 'js/vitruvio.js';
    }
}

/**
 * Informações de Versão
 */
function plugin_version_vitruvio() {
    return [
        'name'           => 'Vitruvio',
        'version'        => PLUGIN_VITRUVIO_VERSION,
        'author'         => 'Ruan Bastos',
        'license'        => 'GPLv2+',
        'homepage'       => 'https://www.linkedin.com/in/ruan-bastos/',
        'requirements'   => [
            'glpi' => [
                'min' => '10.0.0',
                'max' => '11.1.0'
            ]
        ]
    ];
}

/**
 * Verifica pré-requisitos antes de instalar
 */
function plugin_vitruvio_check_prerequisites() {
    if (version_compare(GLPI_VERSION, '10.0.0', 'lt')) {
        echo "Este plugin requer GLPI >= 10.0.0";
        return false;
    }
    return true;
}

/**
 * Verifica se a configuração está ok para habilitar o plugin
 */
function plugin_vitruvio_check_config() {
    return true;
}