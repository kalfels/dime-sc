<?php
$dime_id = isset($_GET['dime_id']) ? (int)$_GET['dime_id'] : 1;

// Inicializar arrays vazios por segurança
$codigos_receita = [];
$classes_vencimento = [];

// Tentar carregar dados do banco, mas não falhar se der erro
try {
    if (file_exists(__DIR__ . '/../includes/config.php')) {
        require_once __DIR__ . '/../includes/config.php';
        
        if (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS') && defined('DB_NAME')) {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
                PDO::ATTR_TIMEOUT => 5
            ]);
            
            // Carregar códigos de receita
            $stmt = $pdo->query("SELECT id, codigo, descricao FROM t_codigos_receita ORDER BY codigo");
            if ($stmt) {
                $codigos_receita = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
            
            // Carregar classes de vencimento
            $stmt = $pdo->query("SELECT id, codigo, descricao FROM t_classes_vencimento ORDER BY codigo");
            if ($stmt) {
                $classes_vencimento = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }
    }
} catch (Exception $e) {
    // Silenciar erros e continuar com arrays vazios
    error_log('Erro ao carregar dados referenciais: ' . $e->getMessage());
}

// Garantir que os arrays sejam válidos para JSON
$codigos_receita = is_array($codigos_receita) ? $codigos_receita : [];
$classes_vencimento = is_array($classes_vencimento) ? $classes_vencimento : [];

// Funções auxiliares
function safe_value($value, $default = '') {
    return isset($value) ? htmlspecialchars($value) : $default;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        <i class="bi bi-cash-coin text-success me-2"></i>
        Quadro 12 - Discriminação dos Pagamentos
    </h5>
</div>

<div class="alert alert-info">
    <strong>💡 Sobre o Quadro 12:</strong> Informe os pagamentos de imposto realizados ou a pagar.
</div>

<!-- Botões de ação -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <button type="button" class="btn btn-success btn-sm" onclick="adicionarPagamento()">
            <i class="bi bi-plus-circle me-2"></i>Adicionar Pagamento
        </button>
    </div>
    <div>
        <button type="button" class="btn btn-outline-info btn-sm" onclick="mostrarAjudaQuadro12()">
            <i class="bi bi-question-circle me-1"></i>Ajuda
        </button>
    </div>
</div>

<!-- Tabela de pagamentos -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h6 class="mb-0">Pagamentos do ICMS</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="tabelaPagamentos">
                <thead class="table-dark">
                    <tr>
                        <th width="8%">Origem</th>
                        <th width="15%">Código Receita</th>
                        <th width="15%">Classe Vencimento</th>
                        <th width="15%">Data Vencimento</th>
                        <th width="15%">Valor</th>
                        <th width="15%">Número Acordo</th>
                        <th width="10%">Ações</th>
                    </tr>
                </thead>
                <tbody id="tbodyPagamentos">
                    <tr id="linhaVazia">
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-info-circle me-2"></i>
                            Nenhum pagamento cadastrado. Clique em "Adicionar Pagamento" para começar.
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="table-primary fw-bold">
                        <td colspan="4" class="text-end">TOTAL:</td>
                        <td id="totalValor">0,00</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Modal para Adicionar Pagamento -->
<div class="modal fade" id="modalAdicionarPagamento" tabindex="-1" aria-labelledby="modalAdicionarPagamentoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modalAdicionarPagamentoLabel">
                    <i class="bi bi-plus-circle me-2"></i>Adicionar Pagamento
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formAdicionarPagamento">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="modal_origem" class="form-label">
                                <strong>Origem do Pagamento *</strong>
                            </label>
                            <select class="form-select" id="modal_origem" required>
                                <option value="">Selecione a origem...</option>
                                <option value="proprio">1 - Quadro 09 (Apuração do ICMS Próprio)</option>
                                <option value="st">2 - Quadro 11 (Substituição Tributária)</option>
                                <option value="outros">3 - Quadro 10 (Diferencial de Alíquota)</option>
                                <option value="outros">4 - Quadro 16 (Outros Débitos)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="modal_codigo_receita" class="form-label">
                                <strong>Código da Receita *</strong>
                            </label>
                            <select class="form-select" id="modal_codigo_receita" required>
                                <option value="">Selecione o código...</option>
                                <?php foreach ($codigos_receita as $codigo): ?>
                                <option value="<?php echo htmlspecialchars($codigo['codigo']); ?>">
                                    <?php echo htmlspecialchars($codigo['codigo']); ?> - <?php echo htmlspecialchars($codigo['descricao']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="modal_classe_vencimento" class="form-label">
                                <strong>Classe de Vencimento</strong>
                            </label>
                            <select class="form-select" id="modal_classe_vencimento">
                                <option value="">Selecione a classe...</option>
                                <?php foreach ($classes_vencimento as $classe): ?>
                                <option value="<?php echo htmlspecialchars($classe['codigo']); ?>">
                                    <?php echo htmlspecialchars($classe['codigo']); ?> - <?php echo htmlspecialchars($classe['descricao']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="modal_data_vencimento" class="form-label">
                                <strong>Data de Vencimento</strong>
                            </label>
                            <input type="date" class="form-control" id="modal_data_vencimento">
                        </div>

                        <div class="col-md-6">
                            <label for="modal_valor" class="form-label">
                                <strong>Valor (R$) *</strong>
                            </label>
                            <input type="text" class="form-control" id="modal_valor"
                                   placeholder="0,00" required>
                        </div>

                        <div class="col-md-6">
                            <label for="modal_numero_acordo" class="form-label">
                                <strong>Número do Acordo</strong>
                            </label>
                            <input type="text" class="form-control" id="modal_numero_acordo"
                                   placeholder="00000000000000" maxlength="20">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Cancelar
                </button>
                <button type="button" class="btn btn-success" onclick="confirmarAdicionarPagamento()">
                    <i class="bi bi-check-circle me-1"></i>Salvar Pagamento
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Debug logs para acompanhar execução -->
<script>
console.log('[DEBUG] Quadro 12: Script iniciado');

// Configuração global do Quadro 12
window.QUADRO12_CONFIG = {
    dimeId: <?php echo json_encode($dime_id); ?>,
    debug: true
};

console.log('[DEBUG] Configuração carregada:', window.QUADRO12_CONFIG);

// Função global para abrir modal
window.adicionarPagamento = function() {
    console.log('[DEBUG] adicionarPagamento chamada - abrindo modal');
    const modal = new bootstrap.Modal(document.getElementById('modalAdicionarPagamento'));
    modal.show();
};

// Funções de validação conforme manual DIME-SEF
function validarOrigemECodigo(origem, codigo) {
    const regras = {
        'proprio': ['1449', '1465', '1600', '3000'],  // Quadro 09
        'st': ['1473', '1740'],  // Quadro 11
        'outros': ['1554', '1570', '1589', '1619', '1627', '1643', '1651', '1716', '1724', '1740', '1759', '1767', '1791', '2140', '2496', '3662', '7137'],  // Quadro 10
        'fundos': ['3662', '7137']  // Quadro 16
    };
    return regras[origem]?.includes(codigo) || false;
}

function validarClasseVencimento(codigo, classe) {
    // Códigos que devem ter classe 19992 conforme manual 3.2.12.3.a
    const codigosClasse19992 = ['1554', '1570', '1589', '1619', '1627', '1643', '1651', '1716', '1724', '1740', '1759', '1767', '1791', '2140', '2496', '3662', '7137'];

    if (codigosClasse19992.includes(codigo)) {
        return classe === '19992';
    }
    return true; // Outros códigos podem ter qualquer classe
}

function validarNumeroAcordo(classe, numeroAcordo) {
    // Classes que exigem TTD conforme manual 3.2.12.9
    const classesComTTD = ['10022', '10197', '10243', '10308', '10340', '10359', '10405', '10448'];

    if (classesComTTD.includes(classe)) {
        return numeroAcordo && numeroAcordo.length === 14 && /^\d{14}$/.test(numeroAcordo);
    }
    return numeroAcordo === '00000000000000' || !numeroAcordo;
}

function formatarValorMonetario(valor) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(valor);
}

// Função para adicionar linha na tabela
function adicionarLinhaPagamento(origem, codigo, classeVencimento, dataVencimento, valor, numeroAcordo, id) {
    const tbody = document.getElementById('tbodyPagamentos');
    const linhaVazia = document.getElementById('linhaVazia');

    // Remover linha vazia se existir
    if (linhaVazia) {
        linhaVazia.remove();
    }

    // Criar nova linha
    const tr = document.createElement('tr');
    tr.setAttribute('data-id', id);

    const origemTexto = {
        'proprio': 'Quadro 09',
        'st': 'Quadro 11',
        'outros': 'Quadro 10',
        'fundos': 'Quadro 16'
    };

    tr.innerHTML = `
        <td>${origemTexto[origem] || origem}</td>
        <td>${codigo}</td>
        <td>${classeVencimento || '-'}</td>
        <td>${dataVencimento || '-'}</td>
        <td>${formatarValorMonetario(parseFloat(valor))}</td>
        <td>${numeroAcordo || '-'}</td>
        <td>
            <button class="btn btn-sm btn-outline-danger" onclick="removerPagamento(${id})" title="Remover">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;

    tbody.appendChild(tr);

    // Atualizar total
    atualizarTotal();
}

// Função para atualizar total
function atualizarTotal() {
    const tbody = document.getElementById('tbodyPagamentos');
    const totalElement = document.getElementById('totalValor');
    let total = 0;

    tbody.querySelectorAll('tr[data-id]').forEach(row => {
        const valorCell = row.cells[4];
        const valor = parseFloat(valorCell.textContent.replace(/[^\d,-]/g, '').replace(',', '.')) || 0;
        total += valor;
    });

    totalElement.textContent = formatarValorMonetario(total);
}

// Função para remover pagamento
function removerPagamento(id) {
    if (confirm('Tem certeza que deseja remover este pagamento?')) {
        console.log('[DEBUG] Removendo pagamento ID:', id);

        // Mostrar loading
        const row = document.querySelector(`tr[data-id="${id}"]`);
        if (row) {
            const deleteBtn = row.querySelector('.btn-outline-danger');
            if (deleteBtn) {
                deleteBtn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
                deleteBtn.disabled = true;
            }
        }

        // Fazer requisição AJAX
        fetch('quadros/ajax_remove_pagamento.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => {
            console.log('[DEBUG] Resposta HTTP:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('[DEBUG] Dados da resposta:', data);
            if (data.success) {
                // Remover linha da tabela
                const row = document.querySelector(`tr[data-id="${id}"]`);
                if (row) {
                    row.remove();
                    atualizarTotal();

                    // Se não há mais linhas, mostrar linha vazia
                    const tbody = document.getElementById('tbodyPagamentos');
                    if (tbody.children.length === 0) {
                        tbody.innerHTML = `
                            <tr id="linhaVazia">
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Nenhum pagamento cadastrado. Clique em "Adicionar Pagamento" para começar.
                                </td>
                            </tr>
                        `;
                    }
                }

                alert('✅ Pagamento removido com sucesso!');
            } else {
                // Erro
                console.error('[DEBUG] ❌ Erro:', data.message);
                alert('❌ Erro ao remover: ' + data.message);

                // Restaurar botão
                const deleteBtn = row?.querySelector('.btn-outline-danger');
                if (deleteBtn) {
                    deleteBtn.innerHTML = '<i class="bi bi-trash"></i>';
                    deleteBtn.disabled = false;
                }
            }
        })
        .catch(error => {
            console.error('[DEBUG] ❌ Erro de conexão:', error);
            alert('❌ Erro de conexão: ' + error.message);

            // Restaurar botão
            const deleteBtn = row?.querySelector('.btn-outline-danger');
            if (deleteBtn) {
                deleteBtn.innerHTML = '<i class="bi bi-trash"></i>';
                deleteBtn.disabled = false;
            }
        });
    }
}

// Função para confirmar adição
// Função para confirmar adição de pagamento
window.confirmarAdicionarPagamento = function() {
    console.log('[DEBUG] confirmarAdicionarPagamento executada');

    // Obter valores dos campos
    const origem = document.getElementById('modal_origem').value;
    const codigo = document.getElementById('modal_codigo_receita').value;
    const classeVencimento = document.getElementById('modal_classe_vencimento').value;
    const dataVencimento = document.getElementById('modal_data_vencimento').value;
    const valor = document.getElementById('modal_valor').value;
    const numeroAcordo = document.getElementById('modal_numero_acordo').value;

    console.log('[DEBUG] Valores obtidos:', {origem, codigo, valor, dataVencimento});

    // Validação básica
    if (!origem || !codigo || !valor) {
        alert('❌ Preencha Origem, Código da Receita e Valor!');
        return;
    }

    // Validações conforme manual DIME-SEF
    if (!validarOrigemECodigo(origem, codigo)) {
        alert('❌ Código da Receita inválido para a origem selecionada!');
        return;
    }

    if (!validarClasseVencimento(codigo, classeVencimento)) {
        alert('❌ Classe de Vencimento deve ser 19992 para este código!');
        return;
    }

    if (!validarNumeroAcordo(classeVencimento, numeroAcordo)) {
        alert('❌ Número do Acordo deve ter 14 dígitos para TTDs ou 00000000000000!');
        return;
    }

    // Validar formato do valor
    const valorNumerico = parseFloat(valor.replace(/\./g, '').replace(',', '.'));
    if (isNaN(valorNumerico) || valorNumerico <= 0) {
        alert('❌ Valor deve ser um número positivo!');
        return;
    }

    // Mostrar loading
    const botao = document.querySelector('#modalAdicionarPagamento .btn-success');
    if (botao) {
        botao.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Salvando...';
        botao.disabled = true;
    }

    // Preparar dados
    const pagamentoData = {
        origem: origem,
        codigo_receita: codigo,
        classe_vencimento: classeVencimento || null,
        data_vencimento: dataVencimento || null,
        valor: valorNumerico,
        numero_acordo: numeroAcordo || "00000000000000"
    };

    console.log('[DEBUG] Enviando dados:', pagamentoData);

    // Fazer requisição AJAX
    fetch(`quadros/ajax_save_pagamento_final.php?dime_id=${window.QUADRO12_CONFIG?.dimeId || 1}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(pagamentoData)
    })
    .then(response => {
        console.log('[DEBUG] Resposta HTTP:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('[DEBUG] Dados da resposta:', data);
        if (data.success) {
            // Sucesso
            console.log('[DEBUG] ✅ Sucesso! ID:', data.id);

            // Adicionar linha na tabela
            adicionarLinhaPagamento(origem, codigo, classeVencimento, dataVencimento, valor, numeroAcordo, data.id);

            // Fechar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalAdicionarPagamento'));
            if (modal) {
                modal.hide();
            }

            // Limpar formulário
            const form = document.getElementById('formAdicionarPagamento');
            if (form) {
                form.reset();
            }

            alert('✅ Pagamento salvo com sucesso! ID: ' + data.id);
        } else {
            // Erro
            console.error('[DEBUG] ❌ Erro:', data.message);
            alert('❌ Erro ao salvar: ' + data.message);
        }
    })
    .catch(error => {
        console.error('[DEBUG] ❌ Erro de conexão:', error);
        alert('❌ Erro de conexão: ' + error.message);
    })
    .finally(() => {
        // Restaurar botão
        if (botao) {
            botao.innerHTML = '<i class="bi bi-check-circle me-1"></i>Salvar Pagamento';
            botao.disabled = false;
        }
    });
};

// Função para mostrar ajuda
function mostrarAjudaQuadro12() {
    console.log('[DEBUG] mostrarAjudaQuadro12 chamada');

    // Criar modal de ajuda se não existir
    if (!document.getElementById('modalAjudaQuadro12')) {
        const modalHTML = `
            <div class="modal fade" id="modalAjudaQuadro12" tabindex="-1" aria-labelledby="modalAjudaQuadro12Label" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="modalAjudaQuadro12Label">
                                <i class="bi bi-question-circle me-2"></i>
                                Ajuda - Quadro 12: Discriminação dos Pagamentos
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <strong>📋 Quadro 12:</strong> Registra todos os pagamentos de ICMS realizados ou a pagar conforme sua modalidade e origem.
                            </div>

                            <h6 class="text-primary">Colunas Obrigatórias:</h6>
                            <ul>
                                <li><strong>Origem:</strong> Fonte do pagamento (1=Quadro 09, 2=Quadro 11, 3=Quadro 10, 4=Quadro 16)</li>
                                <li><strong>Código da Receita:</strong> Código específico conforme Tabela SEF</li>
                                <li><strong>Classe de Vencimento:</strong> Código de vencimento do imposto</li>
                                <li><strong>Data de Vencimento:</strong> Data limite para pagamento</li>
                                <li><strong>Valor:</strong> Valor do imposto a recolher ou recolhido</li>
                                <li><strong>Número do Acordo:</strong> Para TTDs específicos (14 dígitos)</li>
                            </ul>

                            <h6 class="text-info">Regras de Validação:</h6>
                            <div class="alert alert-warning">
                                <ul class="mb-0">
                                    <li><strong>Classe 19992:</strong> Obrigatória para códigos específicos conforme Portaria SEF</li>
                                    <li><strong>Número do Acordo:</strong> 14 dígitos para TTDs ou 00000000000000</li>
                                    <li><strong>Validação Cruzada:</strong> Sistema valida automaticamente origem vs código</li>
                                    <li><strong>Formatação:</strong> Valores em reais brasileiros (R$)</li>
                                </ul>
                            </div>

                            <h6 class="text-success">Tabela de Correlação:</h6>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Quadro</th>
                                            <th>Origem</th>
                                            <th>Códigos de Receita</th>
                                            <th>Classe Vencimento</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>09</strong></td>
                                            <td>1</td>
                                            <td>1449, 1465, 1600, 3000</td>
                                            <td>Conforme tabela</td>
                                        </tr>
                                        <tr class="table-warning">
                                            <td><strong>11</strong></td>
                                            <td>2</td>
                                            <td>1473, 1740</td>
                                            <td>19992</td>
                                        </tr>
                                        <tr>
                                            <td><strong>10</strong></td>
                                            <td>3</td>
                                            <td>1554, 1570, 1589...</td>
                                            <td>19992</td>
                                        </tr>
                                        <tr>
                                            <td><strong>16</strong></td>
                                            <td>4</td>
                                            <td>3662, 7137</td>
                                            <td>19992</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle me-1"></i>Fechar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    // Mostrar modal
    const modal = new bootstrap.Modal(document.getElementById('modalAjudaQuadro12'));
    modal.show();
}
