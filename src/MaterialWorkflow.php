<?php

namespace GlpiPlugin\Vitruvio;

class MaterialWorkflow {

    const ID_GRUPO_ALMOXARIFADO = 7; 
    const NOME_ETIQUETA         = "Material";
    const ID_STATUS_PENDENTE    = 4; // Pendente (Waiting)

    public static function processarSolicitacao($tickets_id, $texto) {
        global $DB;

        if (empty($tickets_id) || empty($texto)) {
             return ['success' => false, 'message' => 'Dados incompletos.'];
        }

        // Carrega o chamado
        $ticket = new \Ticket();
        if (!$ticket->getFromDB($tickets_id)) {
            return ['success' => false, 'message' => 'Chamado não encontrado.'];
        }

        // 1. Cria a Tarefa (Prioridade Máxima)
        if (!self::adicionarTarefa($tickets_id, $texto)) {
            return ['success' => false, 'message' => 'Erro ao criar a tarefa.'];
        }

        // --- AÇÕES SECUNDÁRIAS ---
        $avisos = [];

        // 2. Atribui Grupo como OBSERVADOR
        try {
            self::atribuirGrupo($tickets_id);
        } catch (\Throwable $e) {
            $avisos[] = "Grupo";
        }

        // 3. Adiciona Etiqueta
        try {
            self::adicionarEtiqueta($tickets_id);
        } catch (\Throwable $e) {
             // Ignora erro de tag
        }

        // 4. Muda Status para PENDENTE (ID 4) - Última ação
        try {
            // Recarrega o objeto para garantir que temos os dados mais frescos antes do update
            $ticket->getFromDB($tickets_id);
            
            $updateResult = $ticket->update([
                'id'     => $tickets_id,
                'status' => self::ID_STATUS_PENDENTE
            ]);

            if (!$updateResult) {
                // Se falhar, tentamos descobrir por quê (pode ser permissão ou regra de negócio)
                $avisos[] = "Status (Erro GLPI)";
            }
        } catch (\Throwable $e) {
             $avisos[] = "Status (" . $e->getMessage() . ")";
        }

        // Monta mensagem final
        $msg = 'Solicitação enviada com sucesso!';
        if (count($avisos) > 0) {
            $msg .= " (Mas houve falha ao ajustar: " . implode(", ", $avisos) . ")";
        }

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

    private static function atribuirGrupo($tickets_id) {
        global $DB;
        
        // Verifica se já existe vínculo
        $iterator = $DB->request([
            'FROM'  => 'glpi_groups_tickets',
            'WHERE' => [
                'tickets_id' => $tickets_id,
                'groups_id'  => self::ID_GRUPO_ALMOXARIFADO
            ]
        ]);

        if (count($iterator) == 0) {
            $group_ticket = new \Group_Ticket();
            $group_ticket->add([
                'tickets_id' => $tickets_id,
                'groups_id'  => self::ID_GRUPO_ALMOXARIFADO,
                'type'       => \CommonITILActor::OBSERVER // <--- OBSERVADOR (Grupo)
            ]);
        }
    }

    private static function adicionarEtiqueta($tickets_id) {
        global $DB;

        if (!class_exists('\PluginTagTag') || !class_exists('\PluginTagTagItem')) {
            return;
        }

        $iterator = $DB->request([
            'SELECT' => 'id',
            'FROM'   => 'glpi_plugin_tag_tags',
            'WHERE'  => ['name' => self::NOME_ETIQUETA]
        ]);

        if (count($iterator) == 0) return;

        $tag_row = $iterator->current();
        $tag_id  = $tag_row['id'];

        $exists = count($DB->request([
            'FROM'  => 'glpi_plugin_tag_tagitems',
            'WHERE' => [
                'items_id'           => $tickets_id,
                'itemtype'           => 'Ticket',
                'plugin_tag_tags_id' => $tag_id
            ]
        ]));

        if ($exists == 0) {
            $tagItem = new \PluginTagTagItem();
            $tagItem->add([
                'items_id'           => $tickets_id,
                'itemtype'           => 'Ticket',
                'plugin_tag_tags_id' => $tag_id
            ]);
        }
    }
}
