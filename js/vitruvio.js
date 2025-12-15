$(document).ready(function() {

    const AJAX_URL = CFG_GLPI.root_doc + '/plugins/vitruvio/ajax/solicitar_material.php';

    function addMaterialButton() {
        // Evita duplicidade
        if ($('#btn-solicitar-material').length > 0) return;

        // Procura a barra de ações correta
        var container = $('.main-actions');

        if (container.length > 0) {
            // Cria o botão com o mesmo estilo dos outros
            var btnHtml = '<button type="button" id="btn-solicitar-material" class="ms-2 mb-2 btn btn-primary answer-action" title="Solicitar Material">' +
                          '<i class="ti ti-box"></i>' +
                          '<span class="ms-1">Solicitar Material</span>' +
                          '</button>';
            
            container.append(btnHtml);
            
            // Evento de clique
            $('#btn-solicitar-material').on('click', function(e) {
                e.preventDefault();
                abrirModalLista();
            });
        }
    }

    // Monitora a página para garantir que o botão não suma
    var observer = new MutationObserver(function(mutations) {
        addMaterialButton();
    });
    observer.observe(document.body, { childList: true, subtree: true });
    
    // Inicia
    addMaterialButton();

    // --- NOVA FUNÇÃO: Janela com Lista de Itens ---
    function abrirModalLista() {
        var params = new URLSearchParams(window.location.search);
        var ticketId = params.get('id');
        if (!ticketId) ticketId = $('input[name="tickets_id"]').val();

        if (!ticketId) {
            Swal.fire('Erro', 'ID do chamado não encontrado.', 'error');
            return;
        }

        // HTML da janela (Tabela)
        const htmlContent = `
            <div style="text-align: left; font-size: 0.9rem;">
                <p class="mb-2">Adicione os itens que você precisa:</p>
                <table class="table table-sm" id="tabela-materiais">
                    <thead>
                        <tr>
                            <th width="70%">Item / Material</th>
                            <th width="20%">Qtd.</th>
                            <th width="10%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><input type="text" class="form-control item-nome" placeholder="Ex: Cabo de Rede"></td>
                            <td><input type="number" class="form-control item-qtd" value="1" min="1"></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="btn-add-linha">
                    <i class="ti ti-plus"></i> Adicionar outro item
                </button>
            </div>
        `;

        Swal.fire({
            title: 'Solicitar Material',
            html: htmlContent,
            width: '600px',
            showCancelButton: true,
            confirmButtonText: 'Enviar Solicitação',
            cancelButtonText: 'Cancelar',
            focusConfirm: false,
            didOpen: () => {
                const popup = Swal.getPopup();
                const btnAdd = popup.querySelector('#btn-add-linha');
                const tbody = popup.querySelector('#tabela-materiais tbody');

                btnAdd.addEventListener('click', () => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><input type="text" class="form-control item-nome" placeholder="Item..."></td>
                        <td><input type="number" class="form-control item-qtd" value="1" min="1"></td>
                        <td><button type="button" class="btn btn-sm btn-ghost-danger btn-remove"><i class="ti ti-trash"></i></button></td>
                    `;
                    tbody.appendChild(tr);
                    tr.querySelector('.btn-remove').addEventListener('click', () => tr.remove());
                });
            },
            showLoaderOnConfirm: true,
            // AQUI COMEÇA O BLOCO QUE VOCÊ PEDIU
            preConfirm: () => {
                // 1. Coleta dados da tabela
                const linhas = document.querySelectorAll('#tabela-materiais tbody tr');
                let textoFinal = "";
                
                linhas.forEach(tr => {
                    const nome = tr.querySelector('.item-nome').value;
                    const qtd = tr.querySelector('.item-qtd').value;
                    if (nome.trim() !== "") {
                        textoFinal += `- ${nome} (Qtd: ${qtd})\n`;
                    }
                });

                if (textoFinal === "") {
                    Swal.showValidationMessage('Adicione pelo menos um item na lista.');
                    return false;
                }

                // 2. Envia para o PHP
                return $.ajax({
                    url: AJAX_URL,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        tickets_id: ticketId,
                        material_text: textoFinal
                    }
                }).then(response => {
                    // Verifica se a resposta existe
                    if (!response) {
                        throw new Error("O servidor respondeu com vazio.");
                    }
                    if (!response.success) {
                        throw new Error(response.message || "Erro desconhecido no servidor.");
                    }
                    return response;
                }).catch(error => {
                    console.error("Erro Ajax:", error); // Debug no F12

                    let msg = "Erro desconhecido ao processar.";

                    // Tratamento específico para o erro que você teve (Content-Length: 0)
                    if (error.status === 200 && !error.responseJSON && error.responseText === "") {
                        msg = "O servidor retornou uma resposta VAZIA. Verifique se o arquivo PHP existe e não tem erros de sintaxe.";
                    }
                    // Erro de Parse (JSON inválido)
                    else if (error.status === 200 && error.parsererror) {
                        msg = "O servidor retornou dados inválidos (não é JSON). Pode ser um erro PHP fatal.";
                    }
                    // Erro retornado pelo nosso PHP (throw new Error)
                    else if (error.responseJSON && error.responseJSON.message) {
                        msg = error.responseJSON.message;
                    }
                    // Erro lançado manualmente no Javascript
                    else if (error.message) {
                        msg = error.message;
                    }

                    Swal.showValidationMessage(`Falha: ${msg}`);
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire('Sucesso!', 'Lista enviada para o Almoxarifado.', 'success').then(() => {
                    location.reload();
                });
            }
        });
    }
});