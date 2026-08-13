<?php
$dime_id = isset($_GET['dime_id']) ? (int)$_GET['dime_id'] : 1;

// Carregar dados existentes se houver
$dados_quadro_14 = [];
try {
    if (file_exists(__DIR__ . '/../includes/config.php')) {
        require_once __DIR__ . '/../includes/config.php';

        if (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS') && defined('DB_NAME')) {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
                PDO::ATTR_TIMEOUT => 5
            ]);

            // Carregar dados do Quadro 14
            $stmt = $pdo->prepare("SELECT * FROM dime_quadro_14 WHERE dime_declaracao_id = ?");
            $stmt->execute([$dime_id]);
            $dados_quadro_14 = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }
    }
} catch (Exception $e) {
    error_log('Erro ao carregar dados do Quadro 14: ' . $e->getMessage());
}

// Função auxiliar para formatar valores
function formatar_valor($valor) {
    return number_format($valor, 2, ',', '.');
}

function get_valor($campo) {
    global $dados_quadro_14;
    return isset($dados_quadro_14[$campo]) ? $dados_quadro_14[$campo] : 0;
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
    }
    .info-box {
        background-color: #e7f3ff;
        border-left: 4px solid #0d6efd;
        padding: 15px;
        margin-bottom: 20px;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        <i class="bi bi-calculator text-primary me-2"></i>
        Quadro 14 - Demonstrativo da Apuração do Imposto Devido pela Apropriação de Crédito Presumido
    </h5>
    <button type="button" class="btn btn-outline-info btn-sm" onclick="mostrarAjudaQuadro14()">
        <i class="bi bi-question-circle me-1"></i>Ajuda
    </button>
</div>

<div class="info-box">
    <strong>💡 Sobre o Quadro 14:</strong> Demonstrativo da apuração do imposto relativo às operações contempladas com crédito presumido em substituição aos créditos pelas entradas, conforme Portaria SEF nº 186/17.
</div>

<form id="formQuadro14">
    <!-- Apuração do Débito pela Apropriação do Crédito Presumido -->
    <div class="section-title">Apuração do Débito pela Apropriação do Crédito Presumido</div>

    <div class="row mb-3">
        <div class="col-md-6">
            <label class="form-label">
                <strong>010 - Valor da Base de Cálculo das Saídas com Crédito Presumido</strong>
            </label>
            <input type="text" class="form-control currency" id="item_010_base_calculo"
                   value="<?php echo formatar_valor(get_valor('item_010_base_calculo')); ?>"
                   placeholder="0,00">
            <small class="form-text text-muted">Base de cálculo para aplicação do crédito presumido respectivo. Na hipótese do inciso VI do § 10 do art. 21 do Anexo do RICMS-01/SC, o valor informado pelo estabelecimento industrial deverá estar adicionada dos correspondentes valores das saídas de mercadorias promovidas pelo estabelecimento comercial do mesmo titular</small>
        </div>

        <div class="col-md-6">
            <label class="form-label">
                <strong>020 - (+) Débitos Relativos às Saídas com Crédito Presumido</strong>
            </label>
            <input type="text" class="form-control currency" id="item_020_debitos_saidas"
                   value="<?php echo formatar_valor(get_valor('item_020_debitos_saidas')); ?>"
                   placeholder="0,00">
            <small class="form-text text-muted">Débito integral das saídas beneficiadas pelo crédito presumido em substituição aos créditos pelas entradas (transportado do item 076 do Quadro 09)</small>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <label class="form-label">
                <strong>030 - (-) Crédito Presumido Utilizado</strong>
            </label>
            <input type="text" class="form-control currency" id="item_030_credito_presumido"
                   value="<?php echo formatar_valor(get_valor('item_030_credito_presumido')); ?>"
                   placeholder="0,00">
            <small class="form-text text-muted">Valores do crédito presumido em substituição aos créditos pela entrada declarados em DCIP (transportado do item 036 do Quadro 09)</small>
        </div>

        <div class="col-md-6">
            <label class="form-label">
                <strong>031 - (-) Créditos Permitidos para Compensar</strong>
            </label>
            <input type="text" class="form-control currency" id="item_031_creditos_compensar"
                   value="<?php echo formatar_valor(get_valor('item_031_creditos_compensar')); ?>"
                   placeholder="0,00">
            <small class="form-text text-muted">Outros créditos não vedados para compensação com o débito pela utilização de crédito presumido (transportado do item 038 do Quadro 09)</small>
        </div>
    </div>

    <div class="row mb-3 calculation-row">
        <div class="col-md-6">
            <label class="form-label">
                <strong>040 - (=) Saldo Devedor Apurado</strong>
            </label>
            <input type="text" class="form-control currency bg-light" id="item_040_saldo_devedor"
                   value="<?php echo formatar_valor(get_valor('item_040_saldo_devedor')); ?>"
                   readonly>
            <small class="form-text text-muted">020 - 030 - 031</small>
        </div>

        <div class="col-md-6">
            <label class="form-label">
                <strong>045 - (=) Débito pela Utilização do Crédito Presumido Consolidado</strong>
            </label>
            <input type="text" class="form-control currency" id="item_045_debito_consolidado"
                   value="<?php echo formatar_valor(get_valor('item_045_debito_consolidado')); ?>"
                   placeholder="0,00">
            <small class="form-text text-muted">Débitos de estabelecimentos consolidados</small>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <label class="form-label">
                <strong>050 - (=) Débito Apurado pela Apropriação Extemporânea</strong>
            </label>
            <input type="text" class="form-control currency" id="item_050_debito_extemporaneo"
                   value="<?php echo formatar_valor(get_valor('item_050_debito_extemporaneo')); ?>"
                   placeholder="0,00">
            <small class="form-text text-muted">Débito não recolhido no período de referência</small>
        </div>
    </div>

    <!-- Pagamentos Antecipados -->
    <div class="section-title">Pagamentos Antecipados</div>

    <div class="row mb-3">
        <div class="col-md-6">
            <label class="form-label">
                <strong>110 - (+) Saldo Credor das Antecipações (Mês Anterior)</strong>
            </label>
            <input type="text" class="form-control currency" id="item_110_saldo_credor_anterior"
                   value="<?php echo formatar_valor(get_valor('item_110_saldo_credor_anterior')); ?>"
                   placeholder="0,00">
            <small class="form-text text-muted">Valor do item 198 da DIME do mês imediatamente anterior</small>
        </div>

        <div class="col-md-6">
            <label class="form-label">
                <strong>120 - (+) Crédito decorrente do Pagamento Antecipado</strong>
            </label>
            <input type="text" class="form-control currency" id="item_120_credito_antecipado"
                   value="<?php echo formatar_valor(get_valor('item_120_credito_antecipado')); ?>"
                   placeholder="0,00">
            <small class="form-text text-muted">Pagamentos das antecipações decorrentes de importação, creditados em DCIP (transportado do item 037 do Quadro 09)</small>
        </div>
    </div>

    <div class="row mb-3 calculation-row">
        <div class="col-md-6">
            <label class="form-label">
                <strong>130 - (=) Total das Antecipações</strong>
            </label>
            <input type="text" class="form-control currency bg-light" id="item_130_total_antecipacoes"
                   value="<?php echo formatar_valor(get_valor('item_130_total_antecipacoes')); ?>"
                   readonly>
            <small class="form-text text-muted">110 + 120</small>
        </div>

        <div class="col-md-6">
            <label class="form-label">
                <strong>150 - (-) Débito pela Utilização do Crédito Presumido Transferido</strong>
            </label>
            <input type="text" class="form-control currency" id="item_150_debito_transferido"
                   value="<?php echo formatar_valor(get_valor('item_150_debito_transferido')); ?>"
                   placeholder="0,00">
            <small class="form-text text-muted">Débito transferido ao estabelecimento consolidador quando o declarante for estabelecimento consolidado (nos termos do art. 54 do RICMS-SC/01)</small>
        </div>
    </div>

    <!-- Imposto a Recolher ou Saldo Credor -->
    <div class="section-title">Imposto a Recolher ou Saldo Credor para o Mês Seguinte</div>

    <div class="row mb-3 calculation-row">
        <div class="col-md-6">
            <label class="form-label">
                <strong>199 - (=) Imposto a Recolher pela Utilização do Crédito Presumido</strong>
            </label>
            <input type="text" class="form-control currency bg-light" id="item_199_imposto_recolher"
                   value="<?php echo formatar_valor(get_valor('item_199_imposto_recolher')); ?>"
                   readonly>
            <small class="form-text text-muted">(040 + 045 + 050) - 130 - quando o somatório dos débitos for maior que as antecipações</small>
        </div>

        <div class="col-md-6">
            <label class="form-label">
                <strong>198 - (=) Saldo Credor das Antecipações para o Mês Seguinte</strong>
            </label>
            <input type="text" class="form-control currency bg-light" id="item_198_saldo_credor_seguinte"
                   value="<?php echo formatar_valor(get_valor('item_198_saldo_credor_seguinte')); ?>"
                   readonly>
            <small class="form-text text-muted">130 - (040 + 045 + 050) - quando as antecipações forem maiores que os débitos</small>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">
            <strong>Observações</strong>
        </label>
        <textarea class="form-control" id="observacoes" rows="3"
                  placeholder="Observações adicionais sobre o Quadro 14 (opcional)"><?php echo htmlspecialchars($dados_quadro_14['observacoes'] ?? ''); ?></textarea>
    </div>
</form>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <button type="button" class="btn btn-success" onclick="salvarQuadro14()">
            <i class="bi bi-check-circle me-2"></i>Salvar Quadro 14
        </button>
        <button type="button" class="btn btn-outline-secondary" onclick="calcularQuadro14()">
            <i class="bi bi-calculator me-2"></i>Calcular
        </button>
    </div>
</div>

<script>

// Configuração global
window.QUADRO14_CONFIG = {
    dimeId: <?php echo json_encode($dime_id); ?>,
    debug: true
};


// Máscaras para campos monetários
document.addEventListener('DOMContentLoaded', function() {
    const currencyInputs = document.querySelectorAll('.currency');
    currencyInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = (value / 100).toFixed(2);
            e.target.value = formatarMoeda(value);
        });

        input.addEventListener('blur', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value) {
                value = (value / 100).toFixed(2);
                e.target.value = formatarMoeda(value);
            }
        });
    });
});

function formatarMoeda(valor) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'decimal',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(valor);
}

function parseMoeda(valor) {
    if (!valor) return 0;
    return parseFloat(valor.replace(/\./g, '').replace(',', '.')) || 0;
}

function calcularQuadro14() {
    console.log('[DEBUG] Calculando Quadro 14');

    // Obter valores
    const item020 = parseMoeda(document.getElementById('item_020_debitos_saidas').value);
    const item030 = parseMoeda(document.getElementById('item_030_credito_presumido').value);
    const item031 = parseMoeda(document.getElementById('item_031_creditos_compensar').value);
    const item110 = parseMoeda(document.getElementById('item_110_saldo_credor_anterior').value);
    const item120 = parseMoeda(document.getElementById('item_120_credito_antecipado').value);
    const item040 = parseMoeda(document.getElementById('item_040_saldo_devedor').value);
    const item045 = parseMoeda(document.getElementById('item_045_debito_consolidado').value);
    const item050 = parseMoeda(document.getElementById('item_050_debito_extemporaneo').value);

    // Calcular campos automáticos
    const item130 = item110 + item120;
    const item199 = Math.max(0, (item040 + item045 + item050) - item130);
    const item198 = Math.max(0, item130 - (item040 + item045 + item050));

    // Atualizar campos calculados
    document.getElementById('item_130_total_antecipacoes').value = formatarMoeda(item130);
    document.getElementById('item_199_imposto_recolher').value = formatarMoeda(item199);
    document.getElementById('item_198_saldo_credor_seguinte').value = formatarMoeda(item198);

    console.log('[DEBUG] Cálculos realizados:', { item130, item199, item198 });
}

function salvarQuadro14() {
    console.log('[DEBUG] Salvando Quadro 14');

    const formData = {
        dime_declaracao_id: window.QUADRO14_CONFIG.dimeId,
        item_010_base_calculo: parseMoeda(document.getElementById('item_010_base_calculo').value),
        item_020_debitos_saidas: parseMoeda(document.getElementById('item_020_debitos_saidas').value),
        item_030_credito_presumido: parseMoeda(document.getElementById('item_030_credito_presumido').value),
        item_031_creditos_compensar: parseMoeda(document.getElementById('item_031_creditos_compensar').value),
        item_040_saldo_devedor: parseMoeda(document.getElementById('item_040_saldo_devedor').value),
        item_045_debito_consolidado: parseMoeda(document.getElementById('item_045_debito_consolidado').value),
        item_050_debito_extemporaneo: parseMoeda(document.getElementById('item_050_debito_extemporaneo').value),
        item_110_saldo_credor_anterior: parseMoeda(document.getElementById('item_110_saldo_credor_anterior').value),
        item_120_credito_antecipado: parseMoeda(document.getElementById('item_120_credito_antecipado').value),
        item_130_total_antecipacoes: parseMoeda(document.getElementById('item_130_total_antecipacoes').value),
        item_150_debito_transferido: parseMoeda(document.getElementById('item_150_debito_transferido').value),
        item_199_imposto_recolher: parseMoeda(document.getElementById('item_199_imposto_recolher').value),
        item_198_saldo_credor_seguinte: parseMoeda(document.getElementById('item_198_saldo_credor_seguinte').value),
        observacoes: document.getElementById('observacoes').value
    };

    console.log('[DEBUG] Dados para salvar:', formData);

    const btnSalvar = document.querySelector('.btn-success');
    if (btnSalvar) {
        btnSalvar.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Salvando...';
        btnSalvar.disabled = true;
    }

    fetch('../quadros/ajax_save_14.php', {
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
            alert('✅ Quadro 14 salvo com sucesso!');
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
            btnSalvar.innerHTML = '<i class="bi bi-check-circle me-2"></i>Salvar Quadro 14';
            btnSalvar.disabled = false;
        }
    });
}

function mostrarAjudaQuadro14() {

    if (!document.getElementById('modalAjudaQuadro14')) {
        const modalHTML = `
            <div class="modal fade" id="modalAjudaQuadro14" tabindex="-1" aria-labelledby="modalAjudaQuadro14Label" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="modalAjudaQuadro14Label">
                                <i class="bi bi-question-circle me-2"></i>
                                Manual - Quadro 14: Demonstrativo da Apuração do Imposto Devido pela Apropriação de Crédito Presumido
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <strong>📋 Quadro 14 - DAICP:</strong> Demonstrativo da apuração do imposto relativo às operações ou prestação contempladas com o crédito presumido em substituição aos créditos pelas entradas, no mesmo período de referência da DIME, a partir das informações segregadas do Quadro 09.
                            </div>

                            <div class="alert alert-warning">
                                <strong>⚠️ Importante:</strong> Este quadro será preenchido pelo contribuinte que apropriar crédito presumido na mesma referência da saída, mesmo que o resultado da apuração seja 0 (zero) ou resulte em saldo credor para o mês seguinte, ou, ainda, no caso de apropriação extemporânea do crédito presumido.
                                <br><br>
                                <strong>NOTA:</strong> A Portaria SEF 186/17 dispõe que as DIMES enviadas a partir de 1º de junho deverão atender às novas especificações do Quadro 09 e Quadro 14, inclusive no caso de retificação das informações prestadas para o período de referência 04/2017.
                            </div>

                            <h5 class="text-primary">3.2.25.1. Apuração do Débito pela Apropriação do Crédito Presumido</h5>

                            <h6 class="text-success">a) Item 010 - Valor da Base de Cálculo das Saídas com Crédito Presumido:</h6>
                            <p>Preencher com o valor previsto na legislação como base de cálculo para aplicação do crédito presumido respectivo. Na hipótese do inciso VI do § 10 do art. 21 do Anexo do RICMS-01/SC, o valor da base de cálculo informada pelo estabelecimento industrial deverá estar adicionada dos correspondentes valores das saídas de mercadorias promovidas pelo estabelecimento comercial do mesmo titular situado neste Estado.</p>
                            <p><em>(Redação dada pelo Art. 5º da Portaria SEF nº 186/17 - Efeitos fatos geradores a partir da referência abril/2017)</em></p>

                            <h6 class="text-success">b) Item 020 - Débitos Relativos às Saídas com Crédito Presumido em Substituição aos Créditos pelas Entradas:</h6>
                            <p>Preencher com o valor segregado da apuração do mês transportado do item 076 (Segregação dos Débitos Relativos às Saídas com Crédito Presumido em Substituição aos Créditos pelas Entradas) do Quadro 09 (Cálculo do Imposto a Pagar ou Saldo Credor), referente ao débito integral das saídas beneficiadas pelo crédito presumido em substituição aos créditos pelas entradas.</p>

                            <h6 class="text-success">c) Item 030 - Crédito Presumido Utilizado em Substituição aos Créditos pelas Entradas:</h6>
                            <p>Preencher com valor segregado da apuração do mês transportado do item 036 (Segregação do Crédito Presumido Utilizado em Substituição aos Créditos pelas Entradas) do quadro 09 (Cálculo do Imposto a Pagar ou Saldo Credor), referente aos valores do crédito presumido em substituição aos créditos pela entrada declarados em DCIP, relativas às operações de saídas efetivas no mês.</p>

                            <h6 class="text-success">c.1) Item 031 - Créditos Permitidos para Compensar com o Débito pela Utilização do Crédito Presumido:</h6>
                            <p>Preencher com valor segregado da apuração do mês transportado do item 038 (Segregação do crédito presumido utilizado em substituição aos créditos das entradas) do quadro 09 (Cálculo do Imposto a Pagar ou Saldo Credor), referente aos valores de outros créditos não vedados para compensação com o débito pela utilização de crédito presumido incorridos no mês.</p>
                            <p><em>(Redação dada pelo Art. 5º da Portaria SEF nº 186/17 - Efeitos fatos geradores a partir da referência abril/2017)</em></p>

                            <h6 class="text-success">d) Item 040 - Saldo Devedor Pela Apropriação do Crédito Presumido no Mês:</h6>
                            <p>Preencher com o valor da diferença entre o item 020 deduzidos dos itens 030 e 031, se o valor do débito for maior ou igual ao que o total de créditos. Preencher com 0 (zero) quando o resultado for igual a 0 (zero).</p>
                            <p><em>(Redação dada pelo Art. 5º da Portaria SEF nº 186/17 - Efeitos fatos geradores a partir da referência abril/2017)</em></p>

                            <h6 class="text-success">d.1) Item 045 - Débitos pela Utilização do Crédito Presumido Recebido de Estabelecimento Consolidado:</h6>
                            <p>Preencher com o valor dos débitos pela utilização do crédito presumido de estabelecimentos consolidados, recebidos em transferência no período de referência, quando o declarante for estabelecimento consolidador de empresa que adotar o regime de apuração consolidada. Será preenchido com 0 (zero) quando não for apurado o débito nos estabelecimentos consolidados.</p>
                            <p><em>(Redação dada pelo Art. 2º da Portaria SEF nº 230/17 - Efeitos a partir da referência agosto/2017)</em></p>

                            <h6 class="text-success">e) Item 050 - Débito Apurado pela Apropriação Extemporânea do Crédito Presumido:</h6>
                            <p>Preencher com o valor do débito não recolhido no período de referência da DIME, correspondente a saída em operação ou prestação contemplada com o benefício e cujo crédito presumido foi apropriado extemporaneamente em período de referência diverso.</p>

                            <h5 class="text-primary">3.2.25.2. Pagamentos Antecipados</h5>

                            <h6 class="text-success">a) Item 110 - Saldo Credor das Antecipações para o Mês Seguinte Apurado no Mês Anterior:</h6>
                            <p>Preencher com o valor informado no item 198 deste quadro da DIME do mês imediatamente anterior.</p>

                            <h6 class="text-success">b) Item 120 - Crédito decorrente do Pagamento Antecipado do ICMS Devido na Saída Subsequente à Importação, com Utilização de Crédito Presumido:</h6>
                            <p>Preencher com o valor segregado transportado do item 037 (Segregação do Crédito Decorrente do Pagamento Antecipado do ICMS Devido na Saída Subsequente à Importação, com Utilização de Crédito Presumido) do quadro 09 (Cálculo do Imposto a Pagar ou Saldo Credor), referente aos pagamentos das antecipações decorrentes de importação, creditados em DCIP.</p>

                            <h6 class="text-success">c) Item 130 - Total das Antecipações:</h6>
                            <p>Somatório dos itens 110 e 120 deste quadro.</p>

                            <h5 class="text-primary">3.2.25.3. Imposto a Recolher ou Saldo Credor para o Mês Seguinte</h5>

                            <h6 class="text-success">a) Item 150 - Débito pela Utilização do Crédito Presumido Transferido ao Estabelecimento Consolidador:</h6>
                            <p>Preencher com o valor integral do débito apurado, conforme o disposto na alínea "a.1", transferido ao estabelecimento consolidador no período de referência, quando o declarante for estabelecimento consolidado de empresa que adotar o regime de apuração consolidada, nos termos do art. 54 do RICMS-SC/01. Será preenchido com 0 (zero) quando o estabelecimento consolidado não apurar débito, inclusive se apurado saldo credor para o mês seguinte.</p>
                            <p><em>(Redação dada pelo Art. 3º da Portaria SEF nº 230/17 - Efeitos a partir da referência agosto/2017)</em></p>

                            <h6 class="text-success">a.1) Valor do débito transferido ao estabelecimento consolidador:</h6>
                            <p>É a diferença entre o somatório dos itens 040 (Saldo Devedor pela Apropriação do Crédito Presumido no Mês) e 050 (Débito Apurado pela Apropriação Extemporânea do Crédito Presumido) e o item 130 (Total das Antecipações).</p>
                            <p><em>(Redação dada pelo Art. 3º da Portaria SEF nº 230/17 - Efeitos a partir da referência agosto/2017)</em></p>

                            <h6 class="text-success">b) Item 199 - Imposto a Recolher pela Utilização do Crédito Presumido:</h6>
                            <p>Será preenchido:</p>
                            <ul>
                                <li><strong>b.1)</strong> com zero, quando o item 150 for preenchido com valor, mesmo que seja 0 (zero), quando se tratar de estabelecimento consolidado de empresa que adote apuração consolidada;</li>
                                <li><strong>b.2)</strong> com o valor da diferença entre o somatório dos itens 040 (Saldo Devedor pela Apropriação do Crédito Presumido no Mês), 045 (Saldo devedor pela utilização do crédito presumido recebido de estabelecimento consolidado) e 050 (Débito Apurado pela Apropriação Extemporânea do Crédito Presumido) e o item 130 (Total das Antecipações), se o somatório dos itens 040, 045 e 050 for maior que o item 130, quando o declarante for estabelecimento único ou empresa que não adote apuração consolidada ou for estabelecimento consolidador. Preencher com 0 (zero) quando o resultado for igual a 0 (zero) ou quando o valor do item 130 for maior que o somatório dos itens 040, 045 e 050.</li>
                            </ul>
                            <p><em>(Redação dada pelo Art. 3º da Portaria SEF nº 230/17 - Efeitos a partir da referência agosto/2017)</em></p>

                            <h6 class="text-success">c) Item 198 - Saldo Credor das Antecipações para o Mês Seguinte:</h6>
                            <p>Será preenchido com a diferença entre o item 130 (Total das Antecipações) e o somatório dos itens 040 (Saldo Devedor pela Apropriação do Crédito Presumido no Mês), 045 (Saldo devedor pela utilização do crédito presumido recebido de estabelecimento consolidado) e 050 (Débito Apurado pela Apropriação Extemporânea do Crédito Presumido), sempre que resultar valor maior que 0 (zero).</p>
                            <p><em>(Redação dada pelo Art. 3º da Portaria SEF nº 230/17 - Efeitos a partir da referência agosto/2017)</em></p>

                            <div class="alert alert-info mt-4">
                                <strong>💡 Cálculos Automáticos Implementados:</strong>
                                <ul>
                                    <li><strong>Item 040:</strong> 020 - 030 - 031</li>
                                    <li><strong>Item 130:</strong> 110 + 120</li>
                                    <li><strong>Item 199:</strong> (040 + 045 + 050) - 130</li>
                                    <li><strong>Item 198:</strong> 130 - (040 + 045 + 050)</li>
                                </ul>
                            </div>

                            <div class="alert alert-warning">
                                <strong>⚠️ Regras de Preenchimento:</strong>
                                <ul>
                                    <li>Obrigatório para contribuintes que apropriarem crédito presumido na mesma referência da saída</li>
                                    <li>Deve ser preenchido mesmo com resultado 0 (zero)</li>
                                    <li>Informações segregadas do Quadro 09</li>
                                    <li>Cumprimento ao disposto no inciso V do art. 23 do Anexo 2 do RICMS/SC-01</li>
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

    const modal = new bootstrap.Modal(document.getElementById('modalAjudaQuadro14'));
    modal.show();
}
</script>
