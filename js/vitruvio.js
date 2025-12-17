$(document).ready(function() {

    const AJAX_URL = CFG_GLPI.root_doc + '/plugins/vitruvio/ajax/solicitar_material.php';

    function addMaterialButton() {
        if ($('#btn-solicitar-material').length > 0) return;

        var container = $('.main-actions');
        if (container.length > 0) {
            
            // Lógica de detecção de layout (Mesclado vs Separado)
            var dropdownMenu = container.find('.dropdown-menu');

            if (dropdownMenu.length > 0) {
                var itemHtml = '<li><a id="btn-solicitar-material" class="dropdown-item answer-action" href="#" title="Solicitar Material"><i class="ti ti-box"></i><span class="ms-1">Solicitar Material</span></a></li>';
                dropdownMenu.append(itemHtml);
            } else {
                var btnHtml = '<button type="button" id="btn-solicitar-material" class="ms-2 mb-2 btn btn-primary answer-action" title="Solicitar Material"><i class="ti ti-box"></i><span class="ms-1">Solicitar Material</span></button>';
                container.append(btnHtml);
            }
            
            $('#btn-solicitar-material').on('click', function(e) {
                e.preventDefault();
                if (dropdownMenu.length > 0) $('[data-bs-toggle="dropdown"]').dropdown('hide'); 
                abrirModalLista();
            });
        }
    }

    var observer = new MutationObserver(function(mutations) { addMaterialButton(); });
    observer.observe(document.body, { childList: true, subtree: true });
    addMaterialButton();

    function abrirModalLista() {
        var params = new URLSearchParams(window.location.search);
        var ticketId = params.get('id');
        if (!ticketId) ticketId = $('input[name="tickets_id"]').val();

        if (!ticketId) {
            Swal.fire('Erro', 'ID do chamado não encontrado.', 'error');
            return;
        }

        // HTML da Tabela Atualizado
        const htmlContent = `
            <div style="text-align: left; font-size: 0.9rem;">
                <p class="mb-2">Descreva os itens, observações e anexe arquivos se necessário:</p>
                <div class="table-responsive">
                    <table class="table table-sm" id="tabela-materiais">
                        <thead>
                            <tr>
                                <th width="35%">Item</th>
                                <th width="15%">Qtd.</th>
                                <th width="25%">Obs.</th>
                                <th width="20%">Anexo</th>
                                <th width="5%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input type="text" class="form-control form-control-sm item-nome" placeholder="Nome do item"></td>
                                <td><input type="number" class="form-control form-control-sm item-qtd" value="1" min="1"></td>
                                <td><input type="text" class="form-control form-control-sm item-obs" placeholder="Detalhes..."></td>
                                <td><input type="file" class="form-control form-control-sm item-file"></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="btn-add-linha">
                    <i class="ti ti-plus"></i> Adicionar item
                </button>
            </div>
        `;

        Swal.fire({
            title: 'Solicitar Material',
            html: htmlContent,
            width: '800px', 
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
                        <td><input type="text" class="form-control form-control-sm item-nome" placeholder="Item..."></td>
                        <td><input type="number" class="form-control form-control-sm item-qtd" value="1" min="1"></td>
                        <td><input type="text" class="form-control form-control-sm item-obs" placeholder="..."></td>
                        <td><input type="file" class="form-control form-control-sm item-file"></td>
                        <td><button type="button" class="btn btn-sm btn-ghost-danger btn-remove"><i class="ti ti-trash"></i></button></td>
                    `;
                    tbody.appendChild(tr);
                    tr.querySelector('.btn-remove').addEventListener('click', () => tr.remove());
                });
            },
            showLoaderOnConfirm: true,
            preConfirm: () => {
                const linhas = document.querySelectorAll('#tabela-materiais tbody tr');
                
                // Cria um objeto FormData para enviar arquivos
                let formData = new FormData();
                formData.append('tickets_id', ticketId);

                let contagem = 0;
                let temItem = false;

                linhas.forEach((tr, index) => {
                    const nome = tr.querySelector('.item-nome').value;
                    const qtd  = tr.querySelector('.item-qtd').value;
                    const obs  = tr.querySelector('.item-obs').value;
                    const fileInput = tr.querySelector('.item-file');

                    if (nome.trim() !== "") {
                        temItem = true;
                        // Adiciona os dados como array para o PHP processar
                        formData.append(`items[${index}][nome]`, nome);
                        formData.append(`items[${index}][qtd]`, qtd);
                        formData.append(`items[${index}][obs]`, obs);
                        
                        if (fileInput.files.length > 0) {
                            formData.append(`items[${index}][arquivo]`, fileInput.files[0]);
                        }
                        contagem++;
                    }
                });

                if (!temItem) {
                    Swal.showValidationMessage('Adicione pelo menos um item na lista.');
                    return false;
                }

                // Envia via AJAX com configurações para Upload
                return $.ajax({
                    url: AJAX_URL,
                    type: 'POST',
                    data: formData,
                    processData: false, 
                    contentType: false,
                    dataType: 'json'
                }).then(response => {
                    if (!response) throw new Error("Servidor não respondeu.");
                    if (!response.success) throw new Error(response.message || "Erro desconhecido.");
                    return response;
                }).catch(error => {
                    console.error("Erro Ajax:", error);
                    let msg = "Erro desconhecido.";
                    if (error.responseJSON && error.responseJSON.message) msg = error.responseJSON.message;
                    else if (error.message) msg = error.message;
                    Swal.showValidationMessage(`Falha: ${msg}`);
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire('Sucesso!', 'Solicitação enviada.', 'success').then(() => location.reload());
            }
        });
    }
});