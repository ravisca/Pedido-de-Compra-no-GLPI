<?php
header("Content-Type: application/json; charset=UTF-8");
ini_set('display_errors', 0);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro Fatal PHP: ' . $error['message']]);
        exit;
    }
});

try {
    if (!file_exists("../../../inc/includes.php")) throw new Exception("GLPI não encontrado.");
    include ("../../../inc/includes.php");
    if (ob_get_length()) ob_clean();

    Session::checkLoginUser();

    $tickets_id = isset($_POST['tickets_id']) ? (int)$_POST['tickets_id'] : 0;
    
    // Pega os itens do POST
    $items = isset($_POST['items']) ? $_POST['items'] : [];

    if ($tickets_id === 0 || empty($items)) {
        throw new Exception("Dados inválidos (Chamado ou Itens vazios).");
    }

    // --- ORGANIZAÇÃO DOS ARQUIVOS ---
    if (isset($_FILES['items'])) {
        foreach ($_FILES['items']['name'] as $key => $val) {
            if (isset($_FILES['items']['name'][$key]['arquivo']) && !empty($_FILES['items']['name'][$key]['arquivo'])) {
                $items[$key]['arquivo_info'] = [
                    'name'     => $_FILES['items']['name'][$key]['arquivo'],
                    'type'     => $_FILES['items']['type'][$key]['arquivo'],
                    'tmp_name' => $_FILES['items']['tmp_name'][$key]['arquivo'],
                    'error'    => $_FILES['items']['error'][$key]['arquivo'],
                    'size'     => $_FILES['items']['size'][$key]['arquivo']
                ];
            }
        }
    }

    // Carrega a classe e executa
    $className = 'GlpiPlugin\Vitruvio\MaterialWorkflow';
    $classFile = __DIR__ . '/../src/MaterialWorkflow.php';
    
    if (!class_exists($className)) {
        if (file_exists($classFile)) require_once($classFile);
        else throw new Exception("Classe MaterialWorkflow não encontrada.");
    }

    $resultado = $className::processarSolicitacao($tickets_id, $items);

    echo json_encode($resultado);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}