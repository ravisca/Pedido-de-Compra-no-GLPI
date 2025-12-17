<?php

namespace GlpiPlugin\Vitruvio;

class MaterialWorkflow {

    const ID_GRUPO_ALMOXARIFADO = 7; 
    const NOME_ETIQUETA         = "Material";
    const ID_STATUS_PENDENTE    = 4;

    public static function processarSolicitacao($tickets_id, $items) {
        global $DB;

        if (empty($tickets_id) || empty($items)) {
             return ['success' => false, 'message' => 'Dados incompletos.'];
        }

        $ticket = new \Ticket();
        if (!$ticket->getFromDB($tickets_id)) {
            return ['success' => false, 'message' => 'Chamado não encontrado.'];
        }

        // 1. Prepara texto e separa arquivos
        $textoTarefa = "";
        $arquivosParaAnexar = [];

        foreach ($items as $index => $item) {
            $nome = $item['nome'] ?? 'Item sem nome';
            $qtd  = $item['qtd'] ?? 1;
            $obs  = $item['obs'] ?? '';
            
            $textoTarefa .= "- {$nome} (Qtd: {$qtd})";
            if (!empty($obs)) {
                $textoTarefa .= " [Obs: {$obs}]";
            }
            $textoTarefa .= "\n";

            if (isset($item['arquivo_info']) && $item['arquivo_info']['error'] === 0) {
                $arquivosParaAnexar[] = [
                    'info' => $item['arquivo_info'],
                    'nomeItem' => $nome
                ];
            }
        }

        // 2. Cria a Tarefa
        $taskID = self::adicionarTarefa($tickets_id, $textoTarefa);
        
        if (!$taskID) {
            return ['success' => false, 'message' => 'Erro ao criar a tarefa.'];
        }

        // 3. Upload e Vínculo com a TAREFA
        $arquivosEnviados = 0;
        $errosUpload = [];

        foreach ($arquivosParaAnexar as $arq) {
            // Vincula ao TicketTask ($taskID)
            $uploadRes = self::anexarArquivo($taskID, 'TicketTask', $arq['info'], "Anexo do item: " . $arq['nomeItem']);
            
            if ($uploadRes === true) {
                $arquivosEnviados++;
            } else {
                $errosUpload[] = $arq['nomeItem'];
            }
        }

        // --- AÇÕES SECUNDÁRIAS ---
        $avisos = [];
        if (!empty($errosUpload)) {
            $avisos[] = "Falha ao anexar: " . implode(", ", $errosUpload);
        }

        try { self::atribuirGrupo($tickets_id); } catch (\Throwable $e) { $avisos[] = "Grupo"; }
        try { self::adicionarEtiqueta($tickets_id); } catch (\Throwable $e) { }

        try {
            $ticket->getFromDB($tickets_id);
            $ticket->update(['id' => $tickets_id, 'status' => self::ID_STATUS_PENDENTE]);
        } catch (\Throwable $e) {
             $avisos[] = "Status";
        }

        $msg = "Solicitação enviada!";
        if ($arquivosEnviados > 0) $msg .= " ($arquivosEnviados arquivos anexados)";
        if (count($avisos) > 0) $msg .= " (Avisos: " . implode(", ", $avisos) . ")";

        return ['success' => true, 'message' => $msg];
    }

    private static function adicionarTarefa($tickets_id, $content) {
        $ticketTask = new \TicketTask();
        return $ticketTask->add([
            'tickets_id' => $tickets_id,
            'content'    => "Solicitação de Material:\n" . $content,
            'users_id'   => \Session::getLoginUserID(),
            'state'      => \Planning::TODO,
            'is_private' => 0
        ]);
    }

    /**
     * Função Blindada de Upload
     */
    private static function anexarArquivo($items_id, $itemtype, $fileInfo, $comment = '') {
        $document = new \Document();
        
        // 1. MOVER O ARQUIVO PARA UMA ÁREA SEGURA (GLPI_TMP_DIR)
        $tmp_name = $fileInfo['tmp_name'];
        $name     = $fileInfo['name'];
        $destPath = GLPI_TMP_DIR . '/' . $name;

        // Se o arquivo veio via upload HTTP, usamos move_uploaded_file. 
        if (!@move_uploaded_file($tmp_name, $destPath)) {
            if (!@copy($tmp_name, $destPath)) {
                return "Erro: Não foi possível mover o arquivo temporário.";
            }
        }

        // 2. Prepara a variável global $_FILES simulada
        $_FILES['filename'] = [
            'name'     => $name,
            'type'     => $fileInfo['type'],
            'tmp_name' => $destPath,
            'error'    => 0,
            'size'     => filesize($destPath)
        ];

        // 3. Adiciona o Documento
        $docID = $document->add([
            'name'        => $name,
            'entities_id' => \Session::getActiveEntity(),
            'is_recursive'=> 0,
            'comment'     => $comment,
            '_filename'   => [$name]
        ]);

        if (file_exists($destPath)) {
            @unlink($destPath);
        }

        if ($docID) {
            // 4. Cria o Vínculo (Document_Item)
            $docItem = new \Document_Item();
            $docItem->add([
                'documents_id' => $docID,
                'itemtype'     => $itemtype, // 'TicketTask'
                'items_id'     => $items_id,
                'users_id'     => \Session::getLoginUserID()
            ]);
            return true;
        } else {
            return "Erro ao registrar documento no banco.";
        }
    }

    private static function atribuirGrupo($tickets_id) {
        global $DB;
        $iterator = $DB->request(['FROM' => 'glpi_groups_tickets', 'WHERE' => ['tickets_id' => $tickets_id, 'groups_id' => self::ID_GRUPO_ALMOXARIFADO]]);
        if (count($iterator) == 0) {
            $group = new \Group_Ticket();
            $group->add(['tickets_id' => $tickets_id, 'groups_id' => self::ID_GRUPO_ALMOXARIFADO, 'type' => \CommonITILActor::OBSERVER]);
        }
    }

    private static function adicionarEtiqueta($tickets_id) {
        global $DB;
        if (!class_exists('\PluginTagTag')) return;
        
        $iterator = $DB->request(['SELECT' => 'id', 'FROM' => 'glpi_plugin_tag_tags', 'WHERE' => ['name' => self::NOME_ETIQUETA]]);
        if (count($iterator) == 0) return;
        $tag_id = $iterator->current()['id'];

        $exists = count($DB->request(['FROM' => 'glpi_plugin_tag_tagitems', 'WHERE' => ['items_id' => $tickets_id, 'itemtype' => 'Ticket', 'plugin_tag_tags_id' => $tag_id]]));
        if ($exists == 0) {
            $tagItem = new \PluginTagTagItem();
            $tagItem->add(['items_id' => $tickets_id, 'itemtype' => 'Ticket', 'plugin_tag_tags_id' => $tag_id]);
        }
    }
}