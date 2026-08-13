<?php
$dime_id = isset($_GET['dime_id']) ? (int)$_GET['dime_id'] : 1;

// Carregar dados existentes se houver
$dados_quadro_41 = [];
try {
    if (file_exists(__DIR__ . '/../includes/config.php')) {
        require_once __DIR__ . '/../includes/config.php';

        if (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS') && defined('DB_NAME')) {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
                PDO::ATTR_TIMEOUT => 5
            ]);

            // Carregar dados do Quadro 41
            $stmt = $pdo->prepare("SELECT * FROM dime_quadro_41 WHERE dime_declaracao_id = ?");
            $stmt->execute([$dime_id]);
            $dados_quadro_41 = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }
    }
} catch (Exception $e) {
    error_log('Erro ao carregar dados do Quadro 41: ' . $e->getMessage());
}

// Função auxiliar para formatar valores
function formatar_valor($valor) {
    return number_format($valor, 2, ',', '.');
}

function get_valor($campo) {
    global $dados_quadro_41;
    return isset($dados_quadro_41[$campo]) ? $dados_quadro_41[$campo] : 0;
}
?>

<div class="container-fluid mt-4">
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
    }
    .tab-pane {
        min-height: 400px;
    }
    /* Estilos específicos para as abas - para funcionar com AJAX */
    .nav-tabs .nav-link {
        color: #0d6efd !important;
        border: 1px solid #0d6efd !important;
        border-radius: 0.375rem 0.375rem 0 0 !important;
        border-bottom: none !important;
        transition: all 0.15s ease-in-out;
        padding: 0.75rem 1.5rem;
        font-weight: 500;
    }
    .nav-tabs .nav-link i {
        color: #0d6efd !important;
        transition: all 0.15s ease-in-out;
    }
    .nav-tabs .nav-link:hover {
        color: white !important;
        background-color: #0d6efd !important;
        border-color: #0d6efd !important;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    .nav-tabs .nav-link:hover i {
        color: white !important;
    }
    .nav-tabs .nav-link.active {
        color: white !important;
        background-color: #0d6efd !important;
        border-color: #0d6efd !important;
        border-bottom: 3px solid #0d6efd !important;
        font-weight: 600 !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    .nav-tabs .nav-link.active i {
        color: white !important;
    }
    /* Melhorar seções dentro das abas */
    .section-title {
        color: #0d6efd;
        border-bottom: 3px solid #0d6efd;
        padding-bottom: 8px;
        margin-bottom: 25px;
        margin-top: 25px;
        font-size: 1.1rem;
        font-weight: 600;
        position: relative;
    }
    .section-title::after {
        content: '';
        position: absolute;
        bottom: -3px;
        left: 0;
        width: 50px;
        height: 3px;
        background-color: #0d6efd;
        border-radius: 2px;
    }
    /* Melhorar aparência dos inputs */
    .form-control {
        border-radius: 0.375rem;
        border: 1.5px solid #ced4da;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    .form-control:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    }
    .form-control.currency {
        background-color: #fefefe;
    }
    .form-control.currency.bg-light {
        background-color: #f8f9fa !important;
        border-color: #0d6efd;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        <i class="bi bi-graph-up text-success me-2"></i>
        Quadro 41 - Demonstrativo de Créditos Acumulados
    </h5>
    <button type="button" class="btn btn-outline-info btn-sm" onclick="mostrarAjudaQuadro41()">
        <i class="bi bi-question-circle me-1"></i>Ajuda
    </button>
</div>

<div class="info-box mb-3">
    <strong>💡 Sobre o Quadro 41:</strong> Demonstrativo de Créditos Acumulados conforme Portaria SEF nº 199/14. Obrigatório para declarantes que acumularem crédito transferível.
</div>

<!-- Navegação das Abas -->
<ul class="nav nav-tabs" id="quadro41Tab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="abaA-tab" data-bs-toggle="tab" data-bs-target="#abaA" type="button" role="tab" aria-controls="abaA" aria-selected="true">
            <i class="bi bi-calculator-fill me-2"></i>Campos A - Créditos
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="abaB-tab" data-bs-toggle="tab" data-bs-target="#abaB" type="button" role="tab" aria-controls="abaB" aria-selected="false">
            <i class="bi bi-arrow-left-circle-fill me-2"></i>Campos B - Saldos
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="abaC-tab" data-bs-toggle="tab" data-bs-target="#abaC" type="button" role="tab" aria-controls="abaC" aria-selected="false">
            <i class="bi bi-pie-chart-fill me-2"></i>Campos C - Deduções
        </button>
    </li>
</ul>

<form id="formQuadro41">
    <!-- Conteúdo das Abas -->
    <div class="tab-content" id="quadro41TabContent">
    <!-- Aba Campos A -->
    <div class="tab-pane fade show active" id="abaA" role="tabpanel" aria-labelledby="abaA-tab">
        <div class="section-title">Percentual Aplicável no Mês</div>

        <div class="row mb-3">
            <div class="col-md-12">
                <label class="form-label">
                    <strong>010 - Percentual do Crédito em Relação ao Total das Aquisições</strong>
                </label>
                <div class="input-group">
                    <input type="text" class="form-control" id="item_010_percentual_credito"
                           value="<?php echo formatar_valor(get_valor('item_010_percentual_credito')); ?>"
                           placeholder="0,00">
                    <span class="input-group-text">%</span>
                </div>
                <small class="form-text text-muted">Percentual médio dos últimos 3 meses (média dos últimos 3 meses, incluindo o mês de referência)</small>
            </div>
        </div>

        <div class="section-title">Valor das Operações dos Produtos</div>

        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">
                    <strong>017 - Produtos Exportados no Mês</strong>
                </label>
                <input type="text" class="form-control currency" id="item_017_produtos_exportados"
                       value="<?php echo formatar_valor(get_valor('item_017_produtos_exportados')); ?>"
                       placeholder="0,00">
                <small class="form-text text-muted">Valor das operações com mercadorias efetivamente exportadas</small>
            </div>

            <div class="col-md-4">
                <label class="form-label">
                    <strong>018 - Produtos com Saída Isenta ou Não Tributada</strong>
                </label>
                <input type="text" class="form-control currency" id="item_018_produtos_isentos"
                       value="<?php echo formatar_valor(get_valor('item_018_produtos_isentos')); ?>"
                       placeholder="0,00">
                <small class="form-text text-muted">Produtos com saída isenta ou não tributada com manutenção de crédito</small>
            </div>

            <div class="col-md-4">
                <label class="form-label">
                    <strong>019 - Produtos com Saída Diferida ou Suspensa</strong>
                </label>
                <input type="text" class="form-control currency" id="item_019_produtos_diferidos"
                       value="<?php echo formatar_valor(get_valor('item_019_produtos_diferidos')); ?>"
                       placeholder="0,00">
                <small class="form-text text-muted">Produtos com saída diferida ou suspensa (arts. 45 e 47 do RICMS-SC/01)</small>
            </div>
        </div>

        <div class="section-title">Valor das Mercadorias, Materiais e Serviços Empregados</div>

        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">
                    <strong>020 - Mercadorias Empregadas em Produtos Exportados</strong>
                </label>
                <input type="text" class="form-control currency" id="item_020_mercadorias_exportadas"
                       value="<?php echo formatar_valor(get_valor('item_020_mercadorias_exportadas')); ?>"
                       placeholder="0,00">
                <small class="form-text text-muted">Matérias-primas, material secundário, embalagem, consumo e bens do ativo</small>
            </div>

            <div class="col-md-4">
                <label class="form-label">
                    <strong>030 - Mercadorias Empregadas em Produtos Isentos</strong>
                </label>
                <input type="text" class="form-control currency" id="item_030_mercadorias_isentas"
                       value="<?php echo formatar_valor(get_valor('item_030_mercadorias_isentas')); ?>"
                       placeholder="0,00">
                <small class="form-text text-muted">Empregados em produtos com saída isenta ou não tributada</small>
            </div>

            <div class="col-md-4">
                <label class="form-label">
                    <strong>040 - Mercadorias Empregadas em Produtos Diferidos</strong>
                </label>
                <input type="text" class="form-control currency" id="item_040_mercadorias_diferidas"
                       value="<?php echo formatar_valor(get_valor('item_040_mercadorias_diferidas')); ?>"
                       placeholder="0,00">
                <small class="form-text text-muted">Empregados em produtos com saída diferida ou suspensa</small>
            </div>
        </div>

        <div class="section-title">Créditos Gerados no Mês</div>

        <div class="row mb-3 calculation-row">
            <div class="col-md-4">
                <label class="form-label">
                    <strong>120 - (=) Créditos Gerados por Exportações</strong>
                </label>
                <input type="text" class="form-control currency bg-light" id="item_120_creditos_exportacao"
                       value="<?php echo formatar_valor(get_valor('item_120_creditos_exportacao')); ?>"
                       readonly>
                <small class="form-text text-muted">010 × 020</small>
            </div>

            <div class="col-md-4">
                <label class="form-label">
                    <strong>130 - (=) Créditos Gerados por Saídas Isentas</strong>
                </label>
                <input type="text" class="form-control currency bg-light" id="item_130_creditos_isentos"
                       value="<?php echo formatar_valor(get_valor('item_130_creditos_isentos')); ?>"
                       readonly>
                <small class="form-text text-muted">010 × 030</small>
            </div>

            <div class="col-md-4">
                <label class="form-label">
                    <strong>140 - (=) Créditos Gerados por Saídas Diferidas</strong>
                </label>
                <input type="text" class="form-control currency bg-light" id="item_140_creditos_diferidos"
                       value="<?php echo formatar_valor(get_valor('item_140_creditos_diferidos')); ?>"
                       readonly>
                <small class="form-text text-muted">010 × 040</small>
            </div>
        </div>
    </div>

    <!-- Aba Campos B -->
    <div class="tab-pane fade" id="abaB" role="tabpanel" aria-labelledby="abaB-tab">
        <div class="section-title">Saldo Credor Transferível do Mês Anterior</div>

        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">
                    <strong>160 - (+) Saldo Credor Transferível (Exportação)</strong>
                </label>
                <input type="text" class="form-control currency" id="item_160_saldo_anterior_exportacao"
                       value="<?php echo formatar_valor(get_valor('item_160_saldo_anterior_exportacao')); ?>"
                       placeholder="0,00">
                <small class="form-text text-muted">Item 160 do Quadro 09 do mês anterior</small>
            </div>

            <div class="col-md-4">
                <label class="form-label">
                    <strong>170 - (+) Saldo Credor Transferível (Saídas Isentas)</strong>
                </label>
                <input type="text" class="form-control currency" id="item_170_saldo_anterior_isentos"
                       value="<?php echo formatar_valor(get_valor('item_170_saldo_anterior_isentos')); ?>"
                       placeholder="0,00">
                <small class="form-text text-muted">Item 170 do Quadro 09 do mês anterior</small>
            </div>

            <div class="col-md-4">
                <label class="form-label">
                    <strong>180 - (+) Saldo Credor Transferível (Saídas Diferidas)</strong>
                </label>
                <input type="text" class="form-control currency" id="item_180_saldo_anterior_diferidos"
                       value="<?php echo formatar_valor(get_valor('item_180_saldo_anterior_diferidos')); ?>"
                       placeholder="0,00">
                <small class="form-text text-muted">Item 180 do Quadro 09 do mês anterior</small>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">
                    <strong>190 - Saldo Credor do Mês Anterior (Outros Créditos)</strong>
                </label>
                <input type="text" class="form-control currency" id="item_190_saldo_outros_creditos"
                       value="<?php echo formatar_valor(get_valor('item_190_saldo_outros_creditos')); ?>"
                       placeholder="0,00">
                <small class="form-text text-muted">Item 190 do Quadro 09 do mês anterior</small>
            </div>
        </div>

        <div class="section-title">Crédito Transferível Autorizado Lançado em DCIP</div>

        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">
                    <strong>217 - (+) Crédito Transferível Autorizado (Exportação)</strong>
                </label>
                <input type="text" class="form-control currency" id="item_217_credito_autorizado_exportacao"
                       value="<?php echo formatar_valor(get_valor('item_217_credito_autorizado_exportacao')); ?>"
                       placeholder="0,00">
                <small class="form-text text-muted">Quadro 46 - Créditos por Regimes e Autorizações Especiais</small>
            </div>

            <div class="col-md-4">
                <label class="form-label">
                    <strong>218 - (+) Crédito Transferível Autorizado (Saídas Isentas)</strong>
                </label>
                <input type="text" class="form-control currency" id="item_218_credito_autorizado_isentos"
                       value="<?php echo formatar_valor(get_valor('item_218_credito_autorizado_isentos')); ?>"
                       placeholder="0,00">
                <small class="form-text text-muted">Quadro 46 - Créditos por Regimes e Autorizações Especiais</small>
            </div>

            <div class="col-md-4">
                <label class="form-label">
                    <strong>219 - (+) Crédito Transferível Autorizado (Saídas Diferidas)</strong>
                </label>
                <input type="text" class="form-control currency" id="item_219_credito_autorizado_diferidos"
                       value="<?php echo formatar_valor(get_valor('item_219_credito_autorizado_diferidos')); ?>"
                       placeholder="0,00">
                <small class="form-text text-muted">Quadro 46 - Créditos por Regimes e Autorizações Especiais</small>
            </div>
        </div>
    </div>

    <!-- Aba Campos C -->
    <div class="tab-pane fade" id="abaC" role="tabpanel" aria-labelledby="abaC-tab">
        <div class="section-title">Saldo Credor Acumulado</div>

        <div class="row mb-3 calculation-row">
            <div class="col-md-4">
                <label class="form-label">
                    <strong>960 - (=) Saldo Credor Acumulado (Exportação)</strong>
                </label>
                <input type="text" class="form-control currency bg-light" id="item_960_saldo_acumulado_exportacao"
                       value="<?php echo formatar_valor(get_valor('item_960_saldo_acumulado_exportacao')); ?>"
                       readonly>
                <small class="form-text text-muted">120 + 160 + 217</small>
            </div>

            <div class="col-md-4">
                <label class="form-label">
                    <strong>961 - (=) Proporção Relativa à Exportação</strong>
                </label>
                <div class="input-group">
                    <input type="text" class="form-control bg-light" id="item_961_proporcao_exportacao"
                           value="<?php echo formatar_valor(get_valor('item_961_proporcao_exportacao')); ?>"
                           readonly>
                    <span class="input-group-text">%</span>
                </div>
                <small class="form-text text-muted">960 / (960 + 970 + 980) × 100</small>
            </div>

            <div class="col-md-4">
                <label class="form-label">
                    <strong>970 - (=) Saldo Credor Acumulado (Saídas Isentas)</strong>
                </label>
                <input type="text" class="form-control currency bg-light" id="item_970_saldo_acumulado_isentos"
                       value="<?php echo formatar_valor(get_valor('item_970_saldo_acumulado_isentos')); ?>"
                       readonly>
                <small class="form-text text-muted">130 + 170 + 218</small>
            </div>
        </div>

        <div class="row mb-3 calculation-row">
            <div class="col-md-4">
                <label class="form-label">
                    <strong>971 - (=) Proporção Relativa a Saídas Isentas</strong>
                </label>
                <div class="input-group">
                    <input type="text" class="form-control bg-light" id="item_971_proporcao_isentos"
                           value="<?php echo formatar_valor(get_valor('item_971_proporcao_isentos')); ?>"
                           readonly>
                    <span class="input-group-text">%</span>
                </div>
                <small class="form-text text-muted">970 / (960 + 970 + 980) × 100</small>
            </div>

            <div class="col-md-4">
                <label class="form-label">
                    <strong>980 - (=) Saldo Credor Acumulado (Saídas Diferidas)</strong>
                </label>
                <input type="text" class="form-control currency bg-light" id="item_980_saldo_acumulado_diferidos"
                       value="<?php echo formatar_valor(get_valor('item_980_saldo_acumulado_diferidos')); ?>"
                       readonly>
                <small class="form-text text-muted">140 + 180 + 219</small>
            </div>

            <div class="col-md-4">
                <label class="form-label">
                    <strong>981 - (=) Proporção Relativa a Saídas Diferidas</strong>
                </label>
                <div class="input-group">
                    <input type="text" class="form-control bg-light" id="item_981_proporcao_diferidos"
                           value="<?php echo formatar_valor(get_valor('item_981_proporcao_diferidos')); ?>"
                           readonly>
                    <span class="input-group-text">%</span>
                </div>
                <small class="form-text text-muted">980 / (960 + 970 + 980) × 100</small>
            </div>
        </div>

        <div class="section-title">Deduções no Saldo Credor para o Mês Seguinte</div>

        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">
                    <strong>991 - (+) Saldos Credores Recebidos de Estabelecimentos Consolidados</strong>
                </label>
                <input type="text" class="form-control currency" id="item_991_saldos_consolidados"
                       value="<?php echo formatar_valor(get_valor('item_991_saldos_consolidados')); ?>"
                       placeholder="0,00">
                <small class="form-text text-muted">Item 060 do Quadro 09</small>
            </div>

            <div class="col-md-4">
                <label class="form-label">
                    <strong>993 - (+) Crédito Recebido por Transferência</strong>
                </label>
                <input type="text" class="form-control currency" id="item_993_credito_transferencia"
                       value="<?php echo formatar_valor(get_valor('item_993_credito_transferencia')); ?>"
                       placeholder="0,00">
                <small class="form-text text-muted">Parcela do item 070 do Quadro 09</small>
            </div>

            <div class="col-md-4">
                <label class="form-label">
                    <strong>997 - (+) Outras Deduções do Saldo Credor</strong>
                </label>
                <input type="text" class="form-control currency" id="item_997_outras_deducoes"
                       value="<?php echo formatar_valor(get_valor('item_997_outras_deducoes')); ?>"
                       placeholder="0,00">
                <small class="form-text text-muted">Créditos irregulares estornados ou glosados</small>
            </div>
        </div>

        <div class="row mb-3 calculation-row">
            <div class="col-md-6">
                <label class="form-label">
                    <strong>999 - (=) Total de Dedução no Saldo Credor</strong>
                </label>
                <input type="text" class="form-control currency bg-light" id="item_999_total_deducoes"
                       value="<?php echo formatar_valor(get_valor('item_999_total_deducoes')); ?>"
                       readonly>
                <small class="form-text text-muted">991 + 993 + 997</small>
            </div>
        </div>
    </div>

    </div>

    <!-- Botão de Salvar -->
    <div class="d-flex justify-content-end mb-4">
        <button type="button" class="btn btn-success me-2" onclick="salvarQuadro41()">
            <i class="bi bi-check-circle me-2"></i>Salvar Quadro 41
        </button>
        <button type="button" class="btn btn-primary" onclick="calcularQuadro41()">
            <i class="bi bi-calculator me-2"></i>Calcular
        </button>
    </div>
</form>

<script>
function salvarQuadro41() {
    console.log('[DEBUG] Salvando Quadro 41');

    // Coletar dados do formulário
    const formData = {};
    const inputs = document.querySelectorAll('#formQuadro41 input');
    inputs.forEach(input => {
        if (input.id) {
            formData[input.id.replace('item_', '')] = input.value;
        }
    });

    console.log('[DEBUG] Dados para salvar:', formData);

    const btnSalvar = document.querySelector('.btn-success');
    if (btnSalvar) {
        btnSalvar.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Salvando...';
        btnSalvar.disabled = true;
    }

    fetch('../quadros/ajax_save_41.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
    })
    .then(response => {
        console.log('[DEBUG] Resposta HTTP:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('[DEBUG] Dados da resposta:', data);
        if (data.success) {
            alert('✅ Quadro 41 salvo com sucesso!');
        } else {
            alert('❌ Erro ao salvar: ' + data.message);
        }
    })
    .catch(error => {
        console.error('[DEBUG] Erro de conexão:', error);
        alert('❌ Erro de conexão: ' + error.message);
    })
    .finally(() => {
        if (btnSalvar) {
            btnSalvar.innerHTML = '<i class="bi bi-check-circle me-2"></i>Salvar Quadro 41';
            btnSalvar.disabled = false;
        }
    });
}

function calcularQuadro41() {
    console.log('[DEBUG] Calculando Quadro 41');

    // Obter valores
    const percentual = parseFloat(document.getElementById('item_010_percentual_credito').value.replace(',', '.')) || 0;
    const exportados = parseFloat(document.getElementById('item_020_mercadorias_exportadas').value.replace(/\./g, '').replace(',', '.')) || 0;
    const isentos = parseFloat(document.getElementById('item_030_mercadorias_isentas').value.replace(/\./g, '').replace(',', '.')) || 0;
    const diferidos = parseFloat(document.getElementById('item_040_mercadorias_diferidas').value.replace(/\./g, '').replace(',', '.')) || 0;

    const saldoAntExp = parseFloat(document.getElementById('item_160_saldo_anterior_exportacao').value.replace(/\./g, '').replace(',', '.')) || 0;
    const saldoAntIsen = parseFloat(document.getElementById('item_170_saldo_anterior_isentos').value.replace(/\./g, '').replace(',', '.')) || 0;
    const saldoAntDif = parseFloat(document.getElementById('item_180_saldo_anterior_diferidos').value.replace(/\./g, '').replace(',', '.')) || 0;

    const creditoAutExp = parseFloat(document.getElementById('item_217_credito_autorizado_exportacao').value.replace(/\./g, '').replace(',', '.')) || 0;
    const creditoAutIsen = parseFloat(document.getElementById('item_218_credito_autorizado_isentos').value.replace(/\./g, '').replace(',', '.')) || 0;
    const creditoAutDif = parseFloat(document.getElementById('item_219_credito_autorizado_diferidos').value.replace(/\./g, '').replace(',', '.')) || 0;

    const consolidados = parseFloat(document.getElementById('item_991_saldos_consolidados').value.replace(/\./g, '').replace(',', '.')) || 0;
    const transferencia = parseFloat(document.getElementById('item_993_credito_transferencia').value.replace(/\./g, '').replace(',', '.')) || 0;
    const outrasDed = parseFloat(document.getElementById('item_997_outras_deducoes').value.replace(/\./g, '').replace(',', '.')) || 0;

    // Calcular créditos gerados
    const creditosExp = (percentual / 100) * exportados;
    const creditosIsen = (percentual / 100) * isentos;
    const creditosDif = (percentual / 100) * diferidos;

    // Calcular saldos acumulados
    const saldoAcumExp = creditosExp + saldoAntExp + creditoAutExp;
    const saldoAcumIsen = creditosIsen + saldoAntIsen + creditoAutIsen;
    const saldoAcumDif = creditosDif + saldoAntDif + creditoAutDif;

    // Calcular proporções
    const totalAcumulado = saldoAcumExp + saldoAcumIsen + saldoAcumDif;
    const proporcaoExp = totalAcumulado > 0 ? (saldoAcumExp / totalAcumulado) * 100 : 0;
    const proporcaoIsen = totalAcumulado > 0 ? (saldoAcumIsen / totalAcumulado) * 100 : 0;
    const proporcaoDif = totalAcumulado > 0 ? (saldoAcumDif / totalAcumulado) * 100 : 0;

    // Calcular total de deduções
    const totalDeducoes = consolidados + transferencia + outrasDed;

    // Atualizar campos calculados
    document.getElementById('item_120_creditos_exportacao').value = formatarMoeda(creditosExp);
    document.getElementById('item_130_creditos_isentos').value = formatarMoeda(creditosIsen);
    document.getElementById('item_140_creditos_diferidos').value = formatarMoeda(creditosDif);

    document.getElementById('item_960_saldo_acumulado_exportacao').value = formatarMoeda(saldoAcumExp);
    document.getElementById('item_961_proporcao_exportacao').value = formatarMoeda(proporcaoExp);
    document.getElementById('item_970_saldo_acumulado_isentos').value = formatarMoeda(saldoAcumIsen);
    document.getElementById('item_971_proporcao_isentos').value = formatarMoeda(proporcaoIsen);
    document.getElementById('item_980_saldo_acumulado_diferidos').value = formatarMoeda(saldoAcumDif);
    document.getElementById('item_981_proporcao_diferidos').value = formatarMoeda(proporcaoDif);

    document.getElementById('item_999_total_deducoes').value = formatarMoeda(totalDeducoes);

    console.log('[DEBUG] Cálculos realizados:', {
        creditosExp, creditosIsen, creditosDif,
        saldoAcumExp, saldoAcumIsen, saldoAcumDif,
        proporcaoExp, proporcaoIsen, proporcaoDif,
        totalDeducoes
    });
}

function mostrarAjudaQuadro41() {
    console.log('[DEBUG] mostrarAjudaQuadro41 chamada');

    if (!document.getElementById('modalAjudaQuadro41')) {
        const modalHTML = `
            <div class="modal fade" id="modalAjudaQuadro41" tabindex="-1" aria-labelledby="modalAjudaQuadro41Label" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="modalAjudaQuadro41Label">
                                <i class="bi bi-question-circle me-2"></i>
                                Manual - Quadro 41: Demonstrativo de Créditos Acumulados
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <strong>📋 Quadro 41:</strong> Demonstrativo de Créditos Acumulados conforme Portaria SEF nº 199/14. Obrigatório para declarantes que acumularem crédito transferível.
                            </div>

                            <div class="alert alert-warning">
                                <strong>⚠️ Importante:</strong> Este quadro deve ser preenchido pelos declarantes que vierem a acumular crédito transferível a partir do período de referência em que se iniciar a sua acumulação.
                            </div>

                            <h5 class="text-primary">Cálculos Automáticos Implementados:</h5>
                            <ul>
                                <li><strong>Créditos Gerados:</strong></li>
                                <ul>
                                    <li><strong>120:</strong> 010 × 020 (Percentual × Mercadorias Exportadas)</li>
                                    <li><strong>130:</strong> 010 × 030 (Percentual × Mercadorias Isentas)</li>
                                    <li><strong>140:</strong> 010 × 040 (Percentual × Mercadorias Diferidas)</li>
                                </ul>
                                <li><strong>Saldos Acumulados:</strong></li>
                                <ul>
                                    <li><strong>960:</strong> 120 + 160 + 217 (Créditos Exportação + Saldo Anterior + Autorizado)</li>
                                    <li><strong>970:</strong> 130 + 170 + 218 (Créditos Isentos + Saldo Anterior + Autorizado)</li>
                                    <li><strong>980:</strong> 140 + 180 + 219 (Créditos Diferidos + Saldo Anterior + Autorizado)</li>
                                </ul>
                                <li><strong>Proporções Percentuais:</strong></li>
                                <ul>
                                    <li><strong>961:</strong> 960 / (960 + 970 + 980) × 100</li>
                                    <li><strong>971:</strong> 970 / (960 + 970 + 980) × 100</li>
                                    <li><strong>981:</strong> 980 / (960 + 970 + 980) × 100</li>
                                </ul>
                                <li><strong>Total de Deduções:</strong></li>
                                <ul>
                                    <li><strong>999:</strong> 991 + 993 + 997 (Consolidados + Transferência + Outras Deduções)</li>
                                </ul>
                            </ul>

                            <div class="alert alert-info">
                                <strong>💡 Campos Obrigatórios:</strong>
                                <ul>
                                    <li><strong>010:</strong> Percentual do crédito (média dos últimos 3 meses)</li>
                                    <li><strong>017-019:</strong> Valores das operações dos produtos</li>
                                    <li><strong>020-040:</strong> Mercadorias empregadas em cada tipo de produto</li>
                                    <li><strong>160-190:</strong> Saldos credores transferíveis do mês anterior</li>
                                    <li><strong>217-219:</strong> Créditos transferíveis autorizados via DCIP</li>
                                </ul>
                            </div>

                            <div class="alert alert-warning">
                                <strong>⚠️ Regras de Preenchimento:</strong>
                                <ul>
                                    <li>Percentual com duas casas decimais (ex: 5,95% = 00000000000000595)</li>
                                    <li>Exclusão de 50+ CFOPs específicos no cálculo do percentual</li>
                                    <li>Integração com Quadro 09 e DCIP</li>
                                    <li>Portaria SEF nº 199/14 e atualizações</li>
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

    const modal = new bootstrap.Modal(document.getElementById('modalAjudaQuadro41'));
    modal.show();
}

function formatarMoeda(valor) {
    return valor.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

$(document).ready(function() {
    // Aplicar máscaras monetárias
    $('.currency').mask('000.000.000.000.000,00', {reverse: true});

    // Inicializar tooltips se necessário
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

</div> <!-- container-fluid -->
