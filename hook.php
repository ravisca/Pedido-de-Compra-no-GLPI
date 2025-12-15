<?php

/**
 * Executado ao clicar em "Instalar"
 */
function plugin_vitruvio_install() {
    global $DB;

    // AQUI entraremos com criação de tabelas no futuro
    // Exemplo: $DB->query("CREATE TABLE IF NOT EXISTS ...");

    return true;
}

/**
 * Executado ao clicar em "Desinstalar"
 */
function plugin_vitruvio_uninstall() {
    global $DB;

    // AQUI removemos tabelas para limpar o banco
    // Exemplo: $DB->query("DROP TABLE ...");

    return true;
}