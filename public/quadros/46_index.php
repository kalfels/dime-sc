<?php
$dime_id = isset($_GET['dime_id']) ? (int)$_GET['dime_id'] : 1;

// Carregar dados existentes se houver
$dados_quadro_46 = [];
$total_creditos = 0;
try {
    if (file_exists(__DIR__ . '/../includes/config.php')) {
        require_once __DIR__ . '/../includes/config.php';

        if (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS') && defined('DB_NAME')) {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
                PDO::ATTR_TIMEOUT => 5
            ]);

            // Carregar dados do Quadro 46 (apenas créditos, sem o totalizador)
            $stmt = $pdo->prepare("SELECT * FROM dime_quadro_46 WHERE dime_declaracao_id = ? AND sequencia != '990' ORDER BY CAST(sequencia AS UNSIGNED)");
            $stmt->execute([$dime_id]);
            $dados_quadro_46 = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Calcular total dos créditos válidos
            $stmt = $pdo->prepare("SELECT SUM(valor) as total FROM dime_quadro_46 WHERE dime_declaracao_id = ? AND sequencia != '990' AND valor > 0");
            $stmt->execute([$dime_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_creditos = $result['total'] ?: 0;
        }
    }
} catch (Exception $e) {
    error_log('Erro ao carregar dados do Quadro 46: ' . $e->getMessage());
}

// Função auxiliar para formatar valores
function formatar_valor($valor) {
    return number_format($valor, 2, ',', '.');
}

function get_valor($campo) {
    global $dados_quadro_46;
    return isset($dados_quadro_46[$campo]) ? $dados_quadro_46[$campo] : 0;
}

// Função para formatar origem
function formatar_origem($origem) {
    $origens = [
        '1' => 'Autorização de Utilização de Crédito - AUC',
        '3' => 'Sistema de Apuração e Controle dos Créditos de Ressarcimento',
        '13' => 'Sistema de Apuração para fins de compensação escritural',
        '14' => 'DCIP - Demonstrativo de Crédito',
        '16' => 'DCIP Crédito de Imposto Retido',
        '17' => 'DCIP Crédito Transferível Relativo à Exportação',
        '18' => 'DCIP Crédito Transferível Relativo à Saída Isenta',
        '19' => 'DCIP Crédito Transferível Relativo à Saída Diferida'
    ];
    return $origens[$origem] ?? $origem;
}
?>

<style>
    .calculation-row {
        background-color: #f8f9fa;
        font-weight: bold;
    }
    .section-title {
        color: #0d6efd;
        border-bottom: 2px solid #0d6efd;
        padding-bottom: 5px;
        margin-bottom: 20px;
        margin-top: 20px;
    }
    .info-box {
        background-color: #e7f3ff;
        border-left: 4px solid #0d6efd;
        padding: 15px;
        margin-bottom: 20px;
    }
    .total-row {
        background-color: #fff3cd;
        font-weight: bold;
        border-top: 3px solid #ffc107;
    }
    .credito-item {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        background-color: #f8f9fa;
    }
    .credito-item.total {
        background-color: #fff3cd;
        border-color: #ffc107;
        border-width: 2px;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        <i class="bi bi-credit-card text-success me-2"></i>
        Quadro 46 - Créditos por Regimes e Autorizações Especiais
    </h5>
    <button type="button" class="btn btn-outline-info btn-sm" onclick="mostrarAjudaQuadro46()">
        <i class="bi bi-question-circle me-1"></i>Ajuda
    </button>
</div>

<div class="info-box">
    <strong>💡 Sobre o Quadro 46:</strong> Discriminar os valores de créditos fiscais decorrentes de autorizações específicas geradas pelo S@T - Sistema de Administração Tributária da Secretaria de Estado da Fazenda.
</div>

<div class="alert alert-warning">
    <strong>⚠️ Regras do Quadro 46:</strong>
    <ul class="mb-0">
        <li>Sequência deve iniciar em 01 e terminar em 990</li>
        <li>Item 990 é sempre o totalizador (preenchido automaticamente)</li>
        <li>Identificação deve ser preenchida com "zeros" no item 990</li>
        <li>Origem deve ser preenchida com "00" no item 990</li>
    </ul>
</div>

<form id="formQuadro46">
    <input type="hidden" name="dime_declaracao_id" value="<?php echo $dime_id; ?>">

    <div class="section-title">Créditos por Regimes e Autorizações Especiais</div>

    <div id="creditosContainer">
        <?php
        $total_geral = 0;
        $tem_item_990 = false;

        foreach ($dados_quadro_46 as $credito):
            $total_geral += $credito['valor'];
            $is_total = $credito['sequencia'] === '990';
            if ($is_total) $tem_item_990 = true;
        ?>
            <div class="credito-item <?php echo $is_total ? 'total' : ''; ?>" data-sequencia="<?php echo $credito['sequencia']; ?>">
                <div class="row">
                    <div class="col-md-1">
                        <label class="form-label">
                            <strong>Seq</strong>
                        </label>
                        <input type="text" class="form-control" name="sequencia[]" value="<?php echo $credito['sequencia']; ?>"
                               readonly <?php echo $is_total ? 'style="background-color: #fff3cd; font-weight: bold;"' : ''; ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">
                            <strong>Identificação do Regime</strong>
                        </label>
                        <input type="text" class="form-control" name="identificacao_regime[]" value="<?php echo $credito['identificacao_regime']; ?>"
                               placeholder="Código do regime/autorização" <?php echo $is_total ? 'readonly style="background-color: #fff3cd;"' : ''; ?>>
                        <small class="form-text text-muted">Código gerado pelo S@T</small>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">
                            <strong>Valor</strong>
                        </label>
                        <input type="text" class="form-control currency" name="valor[]" value="<?php echo formatar_valor($credito['valor']); ?>"
                               placeholder="0,00" <?php echo $is_total ? 'readonly style="background-color: #fff3cd; font-weight: bold;"' : ''; ?>>
                        <small class="form-text text-muted">Valor do crédito</small>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">
                            <strong>Origem</strong>
                        </label>
                        <select class="form-select" name="origem[]" <?php echo $is_total ? 'disabled style="background-color: #fff3cd;"' : ''; ?>>
                            <option value="">Selecione...</option>
                            <option value="1" <?php echo $credito['origem'] === '1' ? 'selected' : ''; ?>>1 - AUC</option>
                            <option value="3" <?php echo $credito['origem'] === '3' ? 'selected' : ''; ?>>3 - Ressarcimento</option>
                            <option value="13" <?php echo $credito['origem'] === '13' ? 'selected' : ''; ?>>13 - Compensação</option>
                            <option value="14" <?php echo $credito['origem'] === '14' ? 'selected' : ''; ?>>14 - DCIP</option>
                            <option value="16" <?php echo $credito['origem'] === '16' ? 'selected' : ''; ?>>16 - DCIP Retido</option>
                            <option value="17" <?php echo $credito['origem'] === '17' ? 'selected' : ''; ?>>17 - DCIP Exportação</option>
                            <option value="18" <?php echo $credito['origem'] === '18' ? 'selected' : ''; ?>>18 - DCIP Isenta</option>
                            <option value="19" <?php echo $credito['origem'] === '19' ? 'selected' : ''; ?>>19 - DCIP Diferida</option>
                        </select>
                        <small class="form-text text-muted">Tipo de origem</small>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">
                            <strong>Descrição Origem</strong>
                        </label>
                        <input type="text" class="form-control" value="<?php echo formatar_origem($credito['origem']); ?>"
                               readonly style="background-color: #e9ecef;">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <?php if (!$is_total): ?>
                                <button type="button" class="btn btn-danger btn-sm w-100" onclick="removerCredito(this)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            <?php else: ?>
                                <div style="height: 38px;"></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($dados_quadro_46)): ?>
            <!-- Container vazio - créditos serão adicionados dinamicamente -->
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Nenhum crédito cadastrado.</strong> Clique em "Adicionar Crédito" para começar.
            </div>
        <?php endif; ?>
    </div>

    <!-- Botões de ação -->
    <div class="row mb-3">
        <div class="col-md-6">
            <button type="button" class="btn btn-success" onclick="adicionarCredito()">
                <i class="bi bi-plus-circle me-2"></i>Adicionar Crédito
            </button>
            <small class="text-muted d-block mt-1">O totalizador (990) é calculado automaticamente</small>
        </div>
        <div class="col-md-6 text-end">
            <button type="button" class="btn btn-primary" onclick="salvarQuadro46()">
                <i class="bi bi-check-circle me-2"></i>Salvar Quadro 46
            </button>
        </div>
    </div>

    <!-- Totalizador - aparece apenas se houver créditos -->
    <div class="row">
        <div class="col-12">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0">
                        <i class="bi bi-calculator me-2"></i>
                        Totalizador Automático (Item 990)
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Quantidade de Créditos:</strong> <span id="quantidadeCreditos"><?php echo count(array_filter($dados_quadro_46, function($credito) { return $credito['valor'] > 0; })); ?></span></p>
                            <p><strong>Total dos Créditos:</strong> <span id="valorTotal">R$ <?php echo formatar_valor($total_creditos); ?></span></p>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-info">
                                <small>
                                    <i class="bi bi-info-circle me-1"></i>
                                    O item 990 (totalizador) é calculado dinamicamente e incluído apenas durante a geração do arquivo DIME para não confundir os lançamentos.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
let sequenciaCounter = <?php echo count($dados_quadro_46) > 0 ? max(array_column($dados_quadro_46, 'sequencia')) : 0; ?>;

function adicionarCredito() {
    const container = document.getElementById('creditosContainer');
    const sequencia = String(parseInt(sequenciaCounter) + 1).padStart(2, '0');

    // Removido: Verificação desnecessária do item 990
    // Removido: Limitação desnecessária de sequência

    const creditoHTML = `
        <div class="credito-item" data-sequencia="${sequencia}">
            <div class="row">
                <div class="col-md-1">
                    <label class="form-label"><strong>Seq</strong></label>
                    <input type="text" class="form-control" name="sequencia[]" value="${sequencia}" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><strong>Identificação do Regime</strong></label>
                    <input type="text" class="form-control" name="identificacao_regime[]" placeholder="Código do regime/autorização">
                    <small class="form-text text-muted">Código gerado pelo S@T</small>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><strong>Valor</strong></label>
                    <input type="text" class="form-control currency" name="valor[]" placeholder="0,00">
                    <small class="form-text text-muted">Valor do crédito</small>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><strong>Origem</strong></label>
                    <select class="form-select" name="origem[]">
                        <option value="">Selecione...</option>
                        <option value="1">1 - AUC</option>
                        <option value="3">3 - Ressarcimento</option>
                        <option value="13">13 - Compensação</option>
                        <option value="14">14 - DCIP</option>
                        <option value="16">16 - DCIP Retido</option>
                        <option value="17">17 - DCIP Exportação</option>
                        <option value="18">18 - DCIP Isenta</option>
                        <option value="19">19 - DCIP Diferida</option>
                    </select>
                    <small class="form-text text-muted">Tipo de origem</small>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><strong>Descrição Origem</strong></label>
                    <input type="text" class="form-control" name="descricao_origem[]" readonly style="background-color: #e9ecef;">
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" class="btn btn-danger btn-sm w-100" onclick="removerCredito(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', creditoHTML);
    sequenciaCounter = sequencia;

    // Reaplicar máscaras após adicionar novo crédito
    aplicarMascaras();
    atualizarTotalizadorAutomaticamente();
}

function removerCredito(button) {
    const item = button.closest('.credito-item');
    const sequencia = item.dataset.sequencia;

    if (sequencia === '990') {
        alert('Não é possível remover o totalizador (990). Ele é gerado automaticamente.');
        return;
    }

    if (confirm('Tem certeza que deseja remover este crédito?')) {
        item.remove();
        atualizarTotalizadorAutomaticamente();
        recalcularSequencias();
    }
}

function recalcularSequencias() {
    const items = document.querySelectorAll('#creditosContainer .credito-item');
    items.forEach((item, index) => {
        const sequencia = String(index + 1).padStart(2, '0');
        const seqInput = item.querySelector('input[name="sequencia[]"]');
        seqInput.value = sequencia;
        item.dataset.sequencia = sequencia;
    });
    sequenciaCounter = items.length;
}

function atualizarTotalizadorAutomaticamente() {
    const valores = document.querySelectorAll('input[name="valor[]"]:not([readonly])');
    let total = 0;
    let quantidade = 0;

    valores.forEach(valor => {
        // Só contar valores que não estão vazios
        if (valor.value && valor.value.trim() !== '' && valor.value !== '0,00') {
            const numValue = parseFloat(valor.value.replace(/\./g, '').replace(',', '.')) || 0;
            if (numValue > 0) {
                total += numValue;
                quantidade++;
            }
        }
    });

    // Atualizar totais na interface
    const quantidadeCreditos = document.getElementById('quantidadeCreditos');
    const valorTotal = document.getElementById('valorTotal');

    if (quantidadeCreditos) quantidadeCreditos.textContent = quantidade;
    if (valorTotal) valorTotal.textContent = 'R$ ' + formatarMoeda(total);
}

function salvarQuadro46() {
    const form = document.getElementById('formQuadro46');
    const formData = new FormData(form);
    const data = {};

    // Converter FormData para objeto
    for (let [key, value] of formData.entries()) {
        if (data[key]) {
            if (!Array.isArray(data[key])) {
                data[key] = [data[key]];
            }
            data[key].push(value);
        } else {
            data[key] = value;
        }
    }

    // Debug: Log dos dados do formulário
    console.log('[DEBUG] FormData entries:');
    for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
    }

    console.log('[DEBUG] Data object:', data);

    // Processar campos de array
    data.creditos = [];
    const sequencias = formData.getAll('sequencia[]');
    const identificacoes = formData.getAll('identificacao_regime[]');
    const valores = formData.getAll('valor[]');
    const origens = formData.getAll('origem[]');

    // Debug: Log dos arrays
    console.log('[DEBUG] Arrays:');
    console.log('Sequencias:', sequencias);
    console.log('Identificacoes:', identificacoes);
    console.log('Valores:', valores);
    console.log('Origens:', origens);

    for (let i = 0; i < sequencias.length; i++) {
        const valor = parseFloat(valores[i].replace(/\./g, '').replace(',', '.')) || 0;
        const origem = origens[i];

        // Só incluir créditos que têm valor > 0 ou origem preenchida
        if (valor > 0 || origem) {
            data.creditos.push({
                sequencia: sequencias[i],
                identificacao_regime: identificacoes[i],
                valor: valores[i],
                origem: origens[i]
            });
        }
    }

    console.log('[DEBUG] Salvando Quadro 46...');
    console.log('[DEBUG] Dados a enviar:', data);

    const btnSalvar = document.querySelector('.btn-primary');
    if (btnSalvar) {
        btnSalvar.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Salvando...';
        btnSalvar.disabled = true;
    }

    fetch('../quadros/ajax_save_46.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        console.log('[DEBUG] HTTP Response status:', response.status);
        console.log('[DEBUG] HTTP Response headers:', [...response.headers.entries()]);

        // Log do response object completo
        console.log('[DEBUG] Response object:', response);

        // Verificar se a resposta é ok
        if (!response.ok) {
            console.error('[DEBUG] Response not ok:', response.status, response.statusText);
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        // Tentar obter o texto da resposta primeiro
        return response.text().then(text => {
            console.log('[DEBUG] Raw response text:', text);
            console.log('[DEBUG] Response text length:', text.length);

            // Verificar se o texto está vazio
            if (!text || text.trim() === '') {
                console.error('[DEBUG] Empty response text!');
                throw new Error('Resposta vazia do servidor');
            }

            // Tentar fazer parse do JSON
            try {
                const jsonData = JSON.parse(text);
                console.log('[DEBUG] JSON parse successful:', jsonData);
                return jsonData;
            } catch (parseError) {
                console.error('[DEBUG] JSON parse error:', parseError);
                console.error('[DEBUG] Invalid JSON received:', text);
                throw new Error(`JSON inválido: ${parseError.message}. Response: ${text}`);
            }
        });
    })
    .then(data => {
        console.log('[DEBUG] Final parsed data:', data);

        if (data && typeof data === 'object') {
            if (data.success) {
                console.log('[DEBUG] Success response received');
                alert('✅ Quadro 46 salvo com sucesso!');
                // Recarregar a página para mostrar dados atualizados
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                console.error('[DEBUG] Error response:', data.message);
                alert('❌ Erro ao salvar: ' + (data.message || 'Erro desconhecido'));
            }
        } else {
            console.error('[DEBUG] Invalid response format:', data);
            alert('❌ Resposta inválida do servidor');
        }
    })
    .catch(error => {
        console.error('[DEBUG] Connection error:', error);
        console.error('[DEBUG] Error stack:', error.stack);
        alert('❌ Erro de conexão: ' + error.message);
    })
    .finally(() => {
        if (btnSalvar) {
            btnSalvar.innerHTML = '<i class="bi bi-check-circle me-2"></i>Salvar Quadro 46';
            btnSalvar.disabled = false;
        }
        console.log('[DEBUG] Request completed');
    });
}

function aplicarMascaras() {
    // Remover event listeners existentes para evitar duplicação
    const currencyInputs = document.querySelectorAll('.currency');
    currencyInputs.forEach(input => {
        // Remover event listeners anteriores
        input.removeEventListener('input', handleCurrencyInput);
        input.removeEventListener('blur', handleCurrencyBlur);

        // Adicionar novos event listeners
        input.addEventListener('input', handleCurrencyInput);
        input.addEventListener('blur', handleCurrencyBlur);
    });

    // Atualizar descrição da origem quando selecionada
    const origemSelects = document.querySelectorAll('select[name="origem[]"]');
    origemSelects.forEach(select => {
        select.removeEventListener('change', handleOrigemChange);
        select.addEventListener('change', handleOrigemChange);
    });
}

// Funções separadas para os event handlers
function handleCurrencyInput(e) {
    let value = e.target.value;

    // Se o valor está vazio ou é apenas "0,00", limpar
    if (!value || value === '0,00' || value === '0.00') {
        e.target.value = '';
        atualizarTotalizadorAutomaticamente();
        return;
    }

    // Se contém ponto (formato brasileiro), converter para numérico
    if (value.includes('.')) {
        value = value.replace(/\./g, '').replace(',', '.');
    } else if (value.includes(',')) {
        value = value.replace(',', '.');
    }

    // Remover tudo que não é dígito
    value = value.replace(/\D/g, '');

    // Se não há dígitos, deixar vazio
    if (!value) {
        e.target.value = '';
        atualizarTotalizadorAutomaticamente();
        return;
    }

    // Converter para centavos e formatar
    const numericValue = parseFloat(value) / 100;
    e.target.value = formatarMoeda(numericValue);
    atualizarTotalizadorAutomaticamente();
}

function handleCurrencyBlur(e) {
    let value = e.target.value;

    // Se está vazio, deixar vazio
    if (!value || value.trim() === '') {
        e.target.value = '';
        atualizarTotalizadorAutomaticamente();
        return;
    }

    // Se contém ponto (formato brasileiro), converter para numérico
    if (value.includes('.')) {
        value = value.replace(/\./g, '').replace(',', '.');
    } else if (value.includes(',')) {
        value = value.replace(',', '.');
    }

    // Remover tudo que não é dígito
    value = value.replace(/\D/g, '');

    // Se não há dígitos, deixar vazio
    if (!value) {
        e.target.value = '';
        atualizarTotalizadorAutomaticamente();
        return;
    }

    // Converter para centavos e formatar
    const numericValue = parseFloat(value) / 100;
    e.target.value = formatarMoeda(numericValue);
    atualizarTotalizadorAutomaticamente();
}

function handleOrigemChange() {
    const descricaoInput = this.closest('.row').querySelector('input[name="descricao_origem[]"]');
    const origem = this.value;
    const descricoes = {
        '1': 'Autorização de Utilização de Crédito - AUC',
        '3': 'Sistema de Apuração e Controle dos Créditos de Ressarcimento',
        '13': 'Sistema de Apuração para fins de compensação escritural',
        '14': 'DCIP - Demonstrativo de Crédito',
        '16': 'DCIP Crédito de Imposto Retido',
        '17': 'DCIP Crédito Transferível Relativo à Exportação',
        '18': 'DCIP Crédito Transferível Relativo à Saída Isenta',
        '19': 'DCIP Crédito Transferível Relativo à Saída Diferida'
    };
    if (descricaoInput) {
        descricaoInput.value = descricoes[origem] || '';
    }
}

function formatarMoeda(valor) {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(valor);
}

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    // Pequeno delay para garantir que todos os elementos DOM estejam carregados
    setTimeout(function() {
        aplicarMascaras();
        atualizarTotalizadorAutomaticamente();
        formatarValoresExistentes();
    }, 100);
});

function formatarValoresExistentes() {
    // Formatar valores existentes que vieram do banco de dados
    const currencyInputs = document.querySelectorAll('.currency');
    currencyInputs.forEach(input => {
        if (input.value && input.value.trim() !== '' && input.value !== '0,00') {
            // Se o valor contém ponto (formato brasileiro), converter para formato de entrada
            if (input.value.includes('.')) {
                // Converter "1.000,00" para "1000.00" (formato numérico)
                const numericValue = parseFloat(input.value.replace(/\./g, '').replace(',', '.')) || 0;
                // Aplicar formatação da máscara
                input.value = formatarMoeda(numericValue);
            } else if (input.value.includes(',')) {
                // Se já tem vírgula, pode estar no formato correto ou incorreto
                const numericValue = parseFloat(input.value.replace(',', '.')) || 0;
                input.value = formatarMoeda(numericValue);
            }
        }
    });
}

function mostrarAjudaQuadro46() {
    console.log('[DEBUG] mostrarAjudaQuadro46 chamada');

    if (!document.getElementById('modalAjudaQuadro46')) {
        const modalHTML = `
            <div class="modal fade" id="modalAjudaQuadro46" tabindex="-1" aria-labelledby="modalAjudaQuadro46Label" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="modalAjudaQuadro46Label">
                                <i class="bi bi-question-circle me-2"></i>
                                Manual - Quadro 46: Créditos por Regimes e Autorizações Especiais
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <strong>📋 Quadro 46:</strong> Discriminar os valores de créditos fiscais decorrentes de autorizações específicas geradas pelo S@T - Sistema de Administração Tributária da Secretaria de Estado da Fazenda.
                            </div>

                            <h5 class="text-primary">Funcionalidades Automáticas:</h5>
                            <ul>
                                <li><strong>Totalizador Transparente:</strong> O item 990 é calculado dinamicamente e incluído apenas na geração do arquivo</li>
                                <li><strong>Cálculo em Tempo Real:</strong> Valores atualizados instantaneamente</li>
                                <li><strong>Integração Limpa:</strong> Não há elementos visuais desnecessários na lista</li>
                                <li><strong>Validação Contínua:</strong> Regras aplicadas automaticamente durante a edição</li>
                            </ul>

                            <h5 class="text-primary">Códigos de Origem:</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <ul>
                                        <li><strong>1:</strong> Autorização de Utilização de Crédito - AUC</li>
                                        <li><strong>3:</strong> Sistema de Apuração e Controle dos Créditos de Ressarcimento</li>
                                        <li><strong>13:</strong> Sistema de Apuração para fins de compensação escritural</li>
                                        <li><strong>14:</strong> DCIP - Demonstrativo de Crédito</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul>
                                        <li><strong>16:</strong> DCIP Crédito de Imposto Retido</li>
                                        <li><strong>17:</strong> DCIP Crédito Transferível Relativo à Exportação</li>
                                        <li><strong>18:</strong> DCIP Crédito Transferível Relativo à Saída Isenta</li>
                                        <li><strong>19:</strong> DCIP Crédito Transferível Relativo à Saída Diferida</li>
                                    </ul>
                                </div>
                            </div>

                            <h5 class="text-primary">Integrações com Outros Quadros:</h5>
                            <ul>
                                <li><strong>Origem 1:</strong> Transporta para item 070 do Quadro 09</li>
                                <li><strong>Origem 14:</strong> Transporta para item 075 do Quadro 09</li>
                                <li><strong>Origem 16:</strong> Transporta para item 105 do Quadro 11</li>
                                <li><strong>Origens 17-19:</strong> Transporta para itens 217-219 do Quadro 41</li>
                                <li><strong>Origem 3:</strong> Transporta para item 070 do Quadro 09</li>
                                <li><strong>Origem 13:</strong> Deve ser igual ao valor contábil do CFOP 1.603 no Quadro 01</li>
                            </ul>

                            <div class="alert alert-warning">
                                <strong>⚠️ Validações Automáticas:</strong>
                                <ul>
                                    <li>Sequência obrigatória e única</li>
                                    <li>Item 990 calculado dinamicamente durante a geração do arquivo (não armazenado no banco)</li>
                                    <li>Valores não negativos</li>
                                    <li>Origem obrigatória para todos os itens</li>
                                    <li>Integração com outros quadros conforme especificações</li>
                                    <li>Totalizador atualizado em tempo real</li>
                                </ul>
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

    const modal = new bootstrap.Modal(document.getElementById('modalAjudaQuadro46'));
    modal.show();
}
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
