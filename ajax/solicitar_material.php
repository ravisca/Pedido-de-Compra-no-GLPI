<?php
// ajax/solicitar_material.php

// Define cabeçalho JSON imediatamente
header("Content-Type: application/json; charset=UTF-8");

// Ativa exibição de erros temporariamente para debug (será capturado pelo JSON)
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Função para capturar erros fatais (Tela Branca)
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro Fatal PHP: ' . $error['message'] . ' na linha ' . $error['line']]);
        exit;
    }
});

try {
    // 1. Carrega o GLPI
    if (!file_exists("../../../inc/includes.php")) {
        throw new Exception("Arquivo includes.php não encontrado.");
    }
    include ("../../../inc/includes.php");

    // Limpa qualquer lixo de saída (avisos do GLPI)
    if (ob_get_length()) ob_clean();

    Session::checkLoginUser();

    // 2. Recebe dados
    $tickets_id = isset($_POST['tickets_id']) ? (int)$_POST['tickets_id'] : 0;
    $texto      = isset($_POST['material_text']) ? $_POST['material_text'] : '';

    if ($tickets_id === 0) throw new Exception("ID do chamado inválido.");

    // 3. Carregamento Manual da Classe (Para testar sintaxe)
    $classFile = __DIR__ . '/../src/MaterialWorkflow.php';
    if (!file_exists($classFile)) {
        throw new Exception("Arquivo da classe não encontrado em: $classFile");
    }
    
    // Require Once vai disparar erro se tiver erro de sintaxe no arquivo
    require_once($classFile);

    // 4. Verifica se a classe foi carregada
    if (!class_exists('GlpiPlugin\Vitruvio\MaterialWorkflow')) {
        throw new Exception("A classe MaterialWorkflow não foi definida corretamente. Verifique o namespace.");
    }

    // 5. Executa
    $resultado = GlpiPlugin\Vitruvio\MaterialWorkflow::processarSolicitacao($tickets_id, $texto);

    echo json_encode($resultado);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
} catch (Throwable $t) {
    echo json_encode(['success' => false, 'message' => 'Erro Crítico: ' . $t->getMessage()]);
}