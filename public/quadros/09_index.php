<?php
/**
 * DIME-SC - Quadro 09 - Cálculo do Imposto a Pagar ou Saldo Credor
 * Sistema de gestão de declarações DIME para Santa Catarina
 */

// Verificar se está sendo chamado diretamente
if (!isset($_GET['dime_id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Parâmetro dime_id obrigatório';
    exit;
}

$dime_id = (int)$_GET['dime_id'];

    // Conectar ao banco
require_once __DIR__ . '/../includes/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verificar se é POST (salvar dados) - REMOVIDO - salvamento feito via ajax_save_0405.php

    // Carregar dados do Quadro 09
    $stmt = $pdo->prepare("SELECT * FROM dime_quadro_09 WHERE dime_declaracao_id = ?");
    $stmt->execute([$dime_id]);
    $dados_quadro09 = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Carregar dados dos Quadros 04 e 05 para cálculos automáticos
    $stmt = $pdo->prepare("SELECT * FROM dime_quadro_04 WHERE dime_declaracao_id = ?");
    $stmt->execute([$dime_id]);
    $dados_quadro04 = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare("SELECT * FROM dime_quadro_05 WHERE dime_declaracao_id = ?");
    $stmt->execute([$dime_id]);
    $dados_quadro05 = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Se não há dados no Quadro 09, inicializar com cálculos automáticos
    if (empty($dados_quadro09)) {
        $dados_quadro09 = [
            // Totalização de Débitos - valores do Quadro 04
            'item_010_subtotal_debitos' => $dados_quadro04['item_990_subtotal_debitos'] ?? 0,
            'item_011_complemento_debito_regime' => 0.00,
            'item_020_saldos_devedores_consolidados' => 0.00,
            'item_030_debito_reserva_credito' => 0.00,
            'item_036_segregacao_credito_presumido' => 0.00,
            'item_037_segregacao_credito_pagamento' => 0.00,
            'item_038_segregacao_outros_creditos' => 0.00,
            'item_040_total_debitos' => $dados_quadro04['item_990_subtotal_debitos'] ?? 0,

            // Totalização de Créditos - valores do Quadro 05
            'item_050_subtotal_creditos' => $dados_quadro05['item_990_subtotal_creditos'] ?? 0,
            'item_051_nao_aplica' => 0.00,
            'item_052_nao_aplica' => 0.00,
            'item_060_saldos_credores_consolidados' => 0.00,
            'item_070_creditos_transferencia' => 0.00,
            'item_075_creditos_dcip' => 0.00,
            'item_076_segregacao_debitos_saidas' => 0.00,
            'item_080_total_creditos' => $dados_quadro05['item_990_subtotal_creditos'] ?? 0,

            // Ajustes
            'item_090_imposto_primeiro_decendio' => 0.00,
            'item_100_imposto_segundo_decendio' => 0.00,
            'item_105_antecipacoes_combustiveis' => 0.00,
            'item_110_total_ajustes' => 0.00,

            // Resultados (calculados automaticamente)
            'item_120_saldo_devedor' => 0.00,
            'item_130_saldo_devedor_transferido' => 0.00,
            'item_999_imposto_recolher' => 0.00,
            'item_140_saldo_credor' => 0.00,
            'item_150_saldo_credor_transferido' => 0.00,
            'item_998_saldo_credor_mes_seguinte' => 0.00,

            // Discriminação
            'item_160_saldo_exportacao' => 0.00,
            'item_170_saldo_isentas' => 0.00,
            'item_180_saldo_diferidas' => 0.00,
            'item_190_saldo_outros_creditos' => 0.00,

            'observacoes' => ''
        ];

        // Calcular resultado final automaticamente
        calcularResultadoQuadro09($dados_quadro09);
    } else {
        // Se já existem dados, verificar se precisam ser atualizados com dados dos Quadros 04/05
        $total_debitos_quadro04 = $dados_quadro04['item_990_subtotal_debitos'] ?? 0;
        $total_creditos_quadro05 = $dados_quadro05['item_990_subtotal_creditos'] ?? 0;

        // Atualizar automaticamente se os subtotais mudaram
        if (abs($dados_quadro09['item_010_subtotal_debitos'] - $total_debitos_quadro04) > 0.01) {
            $dados_quadro09['item_010_subtotal_debitos'] = $total_debitos_quadro04;
            $dados_quadro09['item_040_total_debitos'] = calcularTotalDebitos($dados_quadro09);
        }

        if (abs($dados_quadro09['item_050_subtotal_creditos'] - $total_creditos_quadro05) > 0.01) {
            $dados_quadro09['item_050_subtotal_creditos'] = $total_creditos_quadro05;
            $dados_quadro09['item_080_total_creditos'] = calcularTotalCreditos($dados_quadro09);
        }

        // Recalcular resultado se necessário
        calcularResultadoQuadro09($dados_quadro09);
    }

} catch (Exception $e) {
    $dados_quadro09 = [];
    $dados_quadro04 = [];
    $dados_quadro05 = [];
    $error_message = "Erro: " . $e->getMessage();
    echo "<div class='alert alert-danger'>Erro: " . $e->getMessage() . "</div>";
}

// Função helper para valores seguros
function safe_value($data, $key, $default = '') {
    return (isset($data[$key])) ? htmlspecialchars($data[$key]) : $default;
}

// Função helper para formatar valores monetários brasileiros
function format_money_br($value) {
    if (!is_numeric($value)) {
        return '0,00';
    }
    return number_format((float)$value, 2, ',', '.');
}

// Função helper para valores monetários seguros e formatados
function safe_money_value($data, $key, $default = 0) {
    $value = isset($data[$key]) ? $data[$key] : $default;
    return format_money_br($value);
}

// Funções de cálculo do Quadro 09
function calcularTotalDebitos($dados) {
    return ($dados['item_010_subtotal_debitos'] ?? 0) +
           ($dados['item_011_complemento_debito_regime'] ?? 0) +
           ($dados['item_020_saldos_devedores_consolidados'] ?? 0) +
           ($dados['item_030_debito_reserva_credito'] ?? 0) +
           ($dados['item_036_segregacao_credito_presumido'] ?? 0) +
           ($dados['item_037_segregacao_credito_pagamento'] ?? 0) +
           ($dados['item_038_segregacao_outros_creditos'] ?? 0);
}

function calcularTotalCreditos($dados) {
    return ($dados['item_050_subtotal_creditos'] ?? 0) +
           ($dados['item_051_nao_aplica'] ?? 0) +
           ($dados['item_052_nao_aplica'] ?? 0) +
           ($dados['item_060_saldos_credores_consolidados'] ?? 0) +
           ($dados['item_070_creditos_transferencia'] ?? 0) +
           ($dados['item_075_creditos_dcip'] ?? 0) +
           ($dados['item_076_segregacao_debitos_saidas'] ?? 0);
}

function calcularTotalAjustes($dados) {
    return ($dados['item_090_imposto_primeiro_decendio'] ?? 0) +
           ($dados['item_100_imposto_segundo_decendio'] ?? 0) +
           ($dados['item_105_antecipacoes_combustiveis'] ?? 0);
}

function calcularResultadoQuadro09(&$dados) {
    // Calcular totais
    $dados['item_040_total_debitos'] = calcularTotalDebitos($dados);
    $dados['item_080_total_creditos'] = calcularTotalCreditos($dados);
    $dados['item_110_total_ajustes'] = calcularTotalAjustes($dados);

    $total_debitos = $dados['item_040_total_debitos'];
    $total_creditos = $dados['item_080_total_creditos'];
    $total_ajustes = $dados['item_110_total_ajustes'];

    $creditos_mais_ajustes = $total_creditos + $total_ajustes;

    if ($total_debitos > $creditos_mais_ajustes) {
        // Saldo devedor
        $saldo_devedor = $total_debitos - $creditos_mais_ajustes;
        $dados['item_120_saldo_devedor'] = $saldo_devedor;
        $dados['item_140_saldo_credor'] = 0.00;
        $dados['item_998_saldo_credor_mes_seguinte'] = 0.00;

        // Imposto a recolher = saldo devedor - transferido
        $transferido = $dados['item_130_saldo_devedor_transferido'] ?? 0;
        $dados['item_999_imposto_recolher'] = max(0, $saldo_devedor - $transferido);

    } else {
        // Saldo credor
        $saldo_credor = $creditos_mais_ajustes - $total_debitos;
        $dados['item_140_saldo_credor'] = $saldo_credor;
        $dados['item_120_saldo_devedor'] = 0.00;
        $dados['item_999_imposto_recolher'] = 0.00;

        // Saldo credor para mês seguinte = saldo credor - transferido
        $transferido = $dados['item_150_saldo_credor_transferido'] ?? 0;
        $dados['item_998_saldo_credor_mes_seguinte'] = max(0, $saldo_credor - $transferido);
    }
}

?>

<style>
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
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        <i class="bi bi-calculator text-info me-2"></i>
        Quadro 09 - Cálculo do Imposto a Pagar ou Saldo Credor
    </h5>
    <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalAjudaQuadro09">
        <i class="bi bi-question-circle me-1"></i>Ajuda
    </button>
</div>

<?php if (isset($error_message)): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <?php echo $error_message; ?>
</div>
<?php endif; ?>

<!-- Nav tabs -->
<ul class="nav nav-tabs" id="quadro09Tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="debitos-tab" data-bs-toggle="tab" data-bs-target="#debitos" type="button" role="tab" aria-controls="debitos" aria-selected="true">
            <i class="bi bi-dash-circle me-2"></i>Débitos
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="creditos-tab" data-bs-toggle="tab" data-bs-target="#creditos" type="button" role="tab" aria-controls="creditos" aria-selected="false">
            <i class="bi bi-plus-circle me-2"></i>Créditos
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="outras-info-tab" data-bs-toggle="tab" data-bs-target="#outras-info" type="button" role="tab" aria-controls="outras-info" aria-selected="false">
            <i class="bi bi-info-circle me-2"></i>Outras Informações
        </button>
    </li>
</ul>

<!-- Tab content -->
<div class="tab-content mt-4" id="quadro09TabContent">
    <!-- Aba Débitos -->
    <div class="tab-pane fade show active" id="debitos" role="tabpanel" aria-labelledby="debitos-tab">
        <form id="form-quadro09">
            <input type="hidden" name="action" value="save_quadro09">

            <div class="row g-3">
                <div class="col-12">
                    <h6 class="text-danger fw-bold">TOTALIZAÇÃO DE DÉBITOS</h6>
                    <hr class="my-2">
                </div>

                <!-- Subtotal de Débitos -->
                <div class="col-md-6">
                    <label for="item_010_subtotal_debitos" class="form-label">
                        <strong>010 - Subtotal de débitos</strong>
                        <small class="text-muted d-block">Valor do Quadro 04 (item 990)</small>
                    </label>
                    <input type="text" class="form-control debito-field" id="item_010_subtotal_debitos"
                           name="item_010_subtotal_debitos"
                           value="<?php echo safe_money_value($dados_quadro04, 'item_990_subtotal_debitos'); ?>"
                           placeholder="0,00" readonly>
                </div>

                <div class="col-md-6">
                    <label for="item_011_complemento_debito_regime" class="form-label">
                        <strong>011 - Complemento de débito por mudança de regime</strong>
                        <small class="text-muted d-block">Não preencher a partir de janeiro/2022</small>
                    </label>
                    <input type="text" class="form-control debito-field" id="item_011_complemento_debito_regime"
                           name="item_011_complemento_debito_regime"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_011_complemento_debito_regime'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_020_saldos_devedores_consolidados" class="form-label">
                        <strong>020 - Saldos devedores recebidos de estabelecimentos consolidados</strong>
                    </label>
                    <input type="text" class="form-control debito-field" id="item_020_saldos_devedores_consolidados"
                           name="item_020_saldos_devedores_consolidados"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_020_saldos_devedores_consolidados'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_030_debito_reserva_credito" class="form-label">
                        <strong>030 - Débito por reserva de crédito acumulado</strong>
                    </label>
                    <input type="text" class="form-control debito-field" id="item_030_debito_reserva_credito"
                           name="item_030_debito_reserva_credito"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_030_debito_reserva_credito'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_036_segregacao_credito_presumido" class="form-label">
                        <strong>036 - Segregação do Crédito Presumido Utilizado em Substituição aos Créditos pelas Entradas</strong>
                    </label>
                    <input type="text" class="form-control debito-field" id="item_036_segregacao_credito_presumido"
                           name="item_036_segregacao_credito_presumido"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_036_segregacao_credito_presumido'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_037_segregacao_credito_pagamento" class="form-label">
                        <strong>037 - Segregação do Crédito Decorrente do Pagamento Antecipado</strong>
                    </label>
                    <input type="text" class="form-control debito-field" id="item_037_segregacao_credito_pagamento"
                           name="item_037_segregacao_credito_pagamento"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_037_segregacao_credito_pagamento'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_038_segregacao_outros_creditos" class="form-label">
                        <strong>038 - Segregação de outros créditos permitidos</strong>
                    </label>
                    <input type="text" class="form-control debito-field" id="item_038_segregacao_outros_creditos"
                           name="item_038_segregacao_outros_creditos"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_038_segregacao_outros_creditos'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_040_total_debitos" class="form-label fw-bold">
                        <strong>040 - TOTAL DE DÉBITOS</strong>
                        <small class="text-muted ms-1">(calculado automaticamente)</small>
                    </label>
                    <input type="text" class="form-control fw-bold bg-light" id="item_040_total_debitos"
                           name="item_040_total_debitos"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_040_total_debitos'); ?>"
                           placeholder="0,00" readonly>
                </div>
            </div>
        </form>
    </div>

    <!-- Aba Créditos -->
    <div class="tab-pane fade" id="creditos" role="tabpanel" aria-labelledby="creditos-tab">
        <form id="form-quadro09-creditos">
            <div class="row g-3">
                <div class="col-12">
                    <h6 class="text-success fw-bold">TOTALIZAÇÃO DE CRÉDITOS</h6>
                    <hr class="my-2">
                </div>

                <!-- Subtotal de Créditos -->
                <div class="col-md-6">
                    <label for="item_050_subtotal_creditos" class="form-label">
                        <strong>050 - Subtotal de créditos</strong>
                        <small class="text-muted d-block">Valor do Quadro 05 (item 990)</small>
                    </label>
                    <input type="text" class="form-control credito-field" id="item_050_subtotal_creditos"
                           name="item_050_subtotal_creditos"
                           value="<?php echo safe_money_value($dados_quadro05, 'item_990_subtotal_creditos'); ?>"
                           placeholder="0,00" readonly>
                </div>

                <div class="col-md-6">
                    <label for="item_051_nao_aplica" class="form-label">
                        <strong>051 - Não se aplica</strong>
                    </label>
                    <input type="text" class="form-control" id="item_051_nao_aplica"
                           name="item_051_nao_aplica"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_051_nao_aplica'); ?>"
                           placeholder="0,00" readonly>
                </div>

                <div class="col-md-6">
                    <label for="item_052_nao_aplica" class="form-label">
                        <strong>052 - Não se aplica</strong>
                    </label>
                    <input type="text" class="form-control" id="item_052_nao_aplica"
                           name="item_052_nao_aplica"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_052_nao_aplica'); ?>"
                           placeholder="0,00" readonly>
                </div>

                <div class="col-md-6">
                    <label for="item_060_saldos_credores_consolidados" class="form-label">
                        <strong>060 - Saldos credores recebidos de estabelecimentos consolidados</strong>
                    </label>
                    <input type="text" class="form-control credito-field" id="item_060_saldos_credores_consolidados"
                           name="item_060_saldos_credores_consolidados"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_060_saldos_credores_consolidados'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_070_creditos_transferencia" class="form-label">
                        <strong>070 - Créditos recebidos por transferência</strong>
                    </label>
                    <input type="text" class="form-control credito-field" id="item_070_creditos_transferencia"
                           name="item_070_creditos_transferencia"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_070_creditos_transferencia'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_075_creditos_dcip" class="form-label">
                        <strong>075 - Créditos declarados no DCIP</strong>
                    </label>
                    <input type="text" class="form-control credito-field" id="item_075_creditos_dcip"
                           name="item_075_creditos_dcip"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_075_creditos_dcip'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_076_segregacao_debitos_saidas" class="form-label">
                        <strong>076 - Segregação dos Débitos Relativos às Saídas</strong>
                    </label>
                    <input type="text" class="form-control credito-field" id="item_076_segregacao_debitos_saidas"
                           name="item_076_segregacao_debitos_saidas"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_076_segregacao_debitos_saidas'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_080_total_creditos" class="form-label fw-bold">
                        <strong>080 - TOTAL DE CRÉDITOS</strong>
                        <small class="text-muted ms-1">(calculado automaticamente)</small>
                    </label>
                    <input type="text" class="form-control fw-bold bg-light" id="item_080_total_creditos"
                           name="item_080_total_creditos"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_080_total_creditos'); ?>"
                           placeholder="0,00" readonly>
                </div>
            </div>
        </form>
    </div>

    <!-- Aba Outras Informações -->
    <div class="tab-pane fade" id="outras-info" role="tabpanel" aria-labelledby="outras-info-tab">
        <form id="form-quadro09-outras">
            <div class="row g-3">
                <div class="col-12">
                    <h6 class="text-warning fw-bold">AJUSTES DA APURAÇÃO DECENDIAL E ANTECIPAÇÕES</h6>
                    <hr class="my-2">
                </div>

                <div class="col-md-6">
                    <label for="item_090_imposto_primeiro_decendio" class="form-label">
                        <strong>090 - Imposto do 1º decêndio</strong>
                        <small class="text-muted d-block">Não preencher a partir de março/2023</small>
                    </label>
                    <input type="text" class="form-control ajuste-field" id="item_090_imposto_primeiro_decendio"
                           name="item_090_imposto_primeiro_decendio"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_090_imposto_primeiro_decendio'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_100_imposto_segundo_decendio" class="form-label">
                        <strong>100 - Imposto do 2º decêndio</strong>
                        <small class="text-muted d-block">Não preencher a partir de março/2023</small>
                    </label>
                    <input type="text" class="form-control ajuste-field" id="item_100_imposto_segundo_decendio"
                           name="item_100_imposto_segundo_decendio"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_100_imposto_segundo_decendio'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_105_antecipacoes_combustiveis" class="form-label">
                        <strong>105 - Antecipações Combustíveis</strong>
                        <small class="text-muted d-block">Não preencher a partir de março/2023</small>
                    </label>
                    <input type="text" class="form-control ajuste-field" id="item_105_antecipacoes_combustiveis"
                           name="item_105_antecipacoes_combustiveis"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_105_antecipacoes_combustiveis'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_110_total_ajustes" class="form-label fw-bold">
                        <strong>110 - TOTAL DE AJUSTES</strong>
                        <small class="text-muted ms-1">(calculado automaticamente)</small>
                    </label>
                    <input type="text" class="form-control fw-bold bg-light" id="item_110_total_ajustes"
                           name="item_110_total_ajustes"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_110_total_ajustes'); ?>"
                           placeholder="0,00" readonly>
                </div>

                <div class="col-12">
                    <h6 class="text-primary fw-bold">APURAÇÃO FINAL</h6>
                    <hr class="my-2">
                </div>

                <!-- Resultados condicionais -->
                <div class="col-md-6">
                    <label for="item_120_saldo_devedor" class="form-label fw-bold text-danger">
                        <strong>120 - SALDO DEVEDOR</strong>
                        <small class="text-muted d-block">Se débitos > créditos + ajustes</small>
                    </label>
                    <input type="text" class="form-control fw-bold bg-light" id="item_120_saldo_devedor"
                           name="item_120_saldo_devedor"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_120_saldo_devedor'); ?>"
                           placeholder="0,00" readonly>
                </div>

                <div class="col-md-6">
                    <label for="item_130_saldo_devedor_transferido" class="form-label">
                        <strong>130 - Saldo devedor transferido</strong>
                    </label>
                    <input type="text" class="form-control" id="item_130_saldo_devedor_transferido"
                           name="item_130_saldo_devedor_transferido"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_130_saldo_devedor_transferido'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_999_imposto_recolher" class="form-label fw-bold text-danger">
                        <strong>999 - IMPOSTO A RECOLHER</strong>
                    </label>
                    <input type="text" class="form-control fw-bold bg-danger text-white" id="item_999_imposto_recolher"
                           name="item_999_imposto_recolher"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_999_imposto_recolher'); ?>"
                           placeholder="0,00" readonly>
                </div>

                <div class="col-md-6">
                    <label for="item_140_saldo_credor" class="form-label fw-bold text-success">
                        <strong>140 - SALDO CREDOR</strong>
                        <small class="text-muted d-block">Se créditos + ajustes > débitos</small>
                    </label>
                    <input type="text" class="form-control fw-bold bg-light" id="item_140_saldo_credor"
                           name="item_140_saldo_credor"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_140_saldo_credor'); ?>"
                           placeholder="0,00" readonly>
                </div>

                <div class="col-md-6">
                    <label for="item_150_saldo_credor_transferido" class="form-label">
                        <strong>150 - Saldo credor transferido</strong>
                    </label>
                    <input type="text" class="form-control" id="item_150_saldo_credor_transferido"
                           name="item_150_saldo_credor_transferido"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_150_saldo_credor_transferido'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_998_saldo_credor_mes_seguinte" class="form-label fw-bold text-success">
                        <strong>998 - SALDO CREDOR PARA O MÊS SEGUINTE</strong>
                    </label>
                    <input type="text" class="form-control fw-bold bg-success text-white" id="item_998_saldo_credor_mes_seguinte"
                           name="item_998_saldo_credor_mes_seguinte"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_998_saldo_credor_mes_seguinte'); ?>"
                           placeholder="0,00" readonly>
                </div>

                <div class="col-12">
                    <h6 class="text-info fw-bold">DISCRIMINAÇÃO DO SALDO CREDOR PARA O MÊS SEGUINTE</h6>
                    <hr class="my-2">
                </div>

                <div class="col-md-6">
                    <label for="item_160_saldo_exportacao" class="form-label">
                        <strong>160 - Saldo credor relativo à exportação</strong>
                    </label>
                    <input type="text" class="form-control" id="item_160_saldo_exportacao"
                           name="item_160_saldo_exportacao"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_160_saldo_exportacao'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_170_saldo_isentas" class="form-label">
                        <strong>170 - Saldo credor relativo a saídas isentas</strong>
                    </label>
                    <input type="text" class="form-control" id="item_170_saldo_isentas"
                           name="item_170_saldo_isentas"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_170_saldo_isentas'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_180_saldo_diferidas" class="form-label">
                        <strong>180 - Saldo credor relativo a saídas diferidas</strong>
                    </label>
                    <input type="text" class="form-control" id="item_180_saldo_diferidas"
                           name="item_180_saldo_diferidas"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_180_saldo_diferidas'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_190_saldo_outros_creditos" class="form-label">
                        <strong>190 - Saldo credor relativo a outros créditos</strong>
                    </label>
                    <input type="text" class="form-control" id="item_190_saldo_outros_creditos"
                           name="item_190_saldo_outros_creditos"
                           value="<?php echo safe_money_value($dados_quadro09, 'item_190_saldo_outros_creditos'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-12">
                    <label for="observacoes" class="form-label">
                        <strong>Observações</strong>
                    </label>
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="3"
                              placeholder="Observações adicionais (opcional)"><?php echo safe_value($dados_quadro09, 'observacoes'); ?></textarea>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="d-flex justify-content-end mt-4">
    <button type="button" class="btn btn-info me-2" onclick="calcularTudo()">
        <i class="bi bi-calculator me-2"></i>Calcular Tudo
    </button>
    <button type="button" class="btn btn-primary" onclick="salvarQuadro09()">
        <i class="bi bi-save me-2"></i>Salvar Quadro 09
    </button>
</div>

<!-- Modal de Ajuda do Quadro 09 -->
<div class="modal fade" id="modalAjudaQuadro09" tabindex="-1" aria-labelledby="modalAjudaQuadro09Label" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="modalAjudaQuadro09Label">
                    <i class="bi bi-question-circle me-2"></i>
                    Ajuda - Quadro 09: Cálculo do Imposto a Pagar ou Saldo Credor
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">

                <!-- Visão Geral -->
                <div class="mb-4">
                    <h6 class="text-info mb-3">
                        <i class="bi bi-info-circle me-2"></i>Visão Geral
                    </h6>
                    <p>O Quadro 09 é o <strong>quadro principal</strong> da DIME, onde são realizadas todas as <strong>totalizações e cálculos finais</strong> do imposto. Este quadro consolida os dados dos Quadros 04 (Débitos) e 05 (Créditos), aplicando as regras de compensação para determinar se há imposto a recolher ou saldo credor.</p>
                    <div class="alert alert-info">
                        <strong>📊 Função Principal:</strong> Determinar o valor final do ICMS devido ou o saldo credor para o período de apuração.
                    </div>
                    <div class="alert alert-warning">
                        <strong>⚠️ Importante:</strong> Este quadro é obrigatório para todos os contribuintes no regime normal de apuração e produtores primários.
                    </div>
                </div>

                <!-- Fluxo de Cálculo -->
                <div class="mb-4">
                    <h6 class="text-primary mb-3">
                        <i class="bi bi-diagram-3 me-2"></i>Fluxo de Cálculo
                    </h6>
                    <div class="card">
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <div class="p-2 border rounded">
                                        <strong class="text-danger">Quadro 04</strong>
                                        <br><small>Total de Débitos</small>
                                        <br><code>item_040</code>
                                    </div>
                                </div>
                                <div class="col-md-1 text-center">
                                    <strong class="text-muted">-</strong>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-2 border rounded">
                                        <strong class="text-success">Quadro 05</strong>
                                        <br><small>Total de Créditos</small>
                                        <br><code>item_080</code>
                                    </div>
                                </div>
                                <div class="col-md-1 text-center">
                                    <strong class="text-muted">±</strong>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-2 border rounded">
                                        <strong class="text-warning">Ajustes</strong>
                                        <br><small>item_110</small>
                                        <br><small>(Decêndios/Ant.)</small>
                                    </div>
                                </div>
                            </div>
                            <div class="text-center mt-3">
                                <div class="p-2 bg-light rounded">
                                    <strong>= Resultado Final (item_999 ou item_998)</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Totalização de Débitos -->
                <div class="mb-4">
                    <h6 class="text-danger mb-3">
                        <i class="bi bi-dash-circle me-2"></i>Totalização de Débitos
                    </h6>
                    <div class="accordion" id="accordionDebitosQuadro09">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse010Quadro09">
                                    <strong>010 - Subtotal de débitos</strong> (Transportado do Quadro 04)
                                </button>
                            </h2>
                            <div id="collapse010Quadro09" class="accordion-collapse collapse show" data-bs-parent="#accordionDebitosQuadro09">
                                <div class="accordion-body">
                                    <p><strong>Origem:</strong> Item 990 do Quadro 04 (Total de Débitos).</p>
                                    <p><strong>Composição:</strong> Soma de todos os débitos incorridos no período.</p>
                                    <p><strong>Campo automático:</strong> Calculado automaticamente pelo sistema.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse011Quadro09">
                                    <strong>011 - Complemento de débito por mudança de regime</strong>
                                </button>
                            </h2>
                            <div id="collapse011Quadro09" class="accordion-collapse collapse" data-bs-parent="#accordionDebitosQuadro09">
                                <div class="accordion-body">
                                    <p><strong>Quando usar:</strong> Quando há mudança de regime de apuração no CCICMS.</p>
                                    <p><strong>Cálculo:</strong> Ajuste devedor decorrente da mudança de regime.</p>
                                    <div class="alert alert-danger small">
                                        <strong>❌ IMPORTANTE:</strong> Este campo não estará disponível para preenchimento a partir de janeiro/2022.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse020Quadro09">
                                    <strong>020 - Saldos devedores recebidos de estabelecimentos consolidados</strong>
                                </button>
                            </h2>
                            <div id="collapse020Quadro09" class="accordion-collapse collapse" data-bs-parent="#accordionDebitosQuadro09">
                                <div class="accordion-body">
                                    <p><strong>Quando usar:</strong> Para estabelecimentos consolidados que recebem saldos devedores de outros estabelecimentos do grupo.</p>
                                    <p><strong>Aplicação:</strong> Regime de apuração consolidada.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse030Quadro09">
                                    <strong>030 - Débito por reserva de crédito acumulado</strong>
                                </button>
                            </h2>
                            <div id="collapse030Quadro09" class="accordion-collapse collapse" data-bs-parent="#accordionDebitosQuadro09">
                                <div class="accordion-body">
                                    <p><strong>Origem:</strong> Quadro 42 - Débitos por Transferência de Créditos.</p>
                                    <p><strong>Informação:</strong> Total dos créditos acumulados reservados.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse036Quadro09">
                                    <strong>036 - Segregação do Crédito Presumido Utilizado em Substituição aos Créditos pelas Entradas</strong>
                                </button>
                            </h2>
                            <div id="collapse036Quadro09" class="accordion-collapse collapse" data-bs-parent="#accordionDebitosQuadro09">
                                <div class="accordion-body">
                                    <p><strong>Origem:</strong> DCIP de crédito presumido com subtipo específico.</p>
                                    <p><strong>Finalidade:</strong> Segregar operações beneficiadas por crédito presumido.</p>
                                    <p><strong>Transporte:</strong> Valor vai para o Quadro 14 para apuração separada.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse037Quadro09">
                                    <strong>037 - Segregação do Crédito Decorrente do Pagamento Antecipado</strong>
                                </button>
                            </h2>
                            <div id="collapse037Quadro09" class="accordion-collapse collapse" data-bs-parent="#accordionDebitosQuadro09">
                                <div class="accordion-body">
                                    <p><strong>Origem:</strong> DCIP de Outros Créditos (subtipo 34).</p>
                                    <p><strong>Informação:</strong> Valores de pagamentos antecipados do ICMS.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse038Quadro09">
                                    <strong>038 - Segregação de outros créditos permitidos</strong>
                                </button>
                            </h2>
                            <div id="collapse038Quadro09" class="accordion-collapse collapse" data-bs-parent="#accordionDebitosQuadro09">
                                <div class="accordion-body">
                                    <p><strong>Inclui:</strong> Créditos recebidos em transferência, devoluções de mercadorias, etc.</p>
                                    <p><strong>Limitação:</strong> Não pode exceder o valor dos débitos pelas saídas.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-danger mt-2">
                        <h6 class="mb-1">040 - TOTAL DE DÉBITOS</h6>
                        <p class="mb-0">Somatório automático de todos os itens de débito (010 a 038).</p>
                    </div>
                </div>

                <!-- Totalização de Créditos -->
                <div class="mb-4">
                    <h6 class="text-success mb-3">
                        <i class="bi bi-plus-circle me-2"></i>Totalização de Créditos
                    </h6>
                    <div class="accordion" id="accordionCreditosQuadro09">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse050Quadro09">
                                    <strong>050 - Subtotal de créditos</strong> (Transportado do Quadro 05)
                                </button>
                            </h2>
                            <div id="collapse050Quadro09" class="accordion-collapse collapse" data-bs-parent="#accordionCreditosQuadro09">
                                <div class="accordion-body">
                                    <p><strong>Origem:</strong> Item 990 do Quadro 05 (Total de Créditos).</p>
                                    <p><strong>Composição:</strong> Soma de todos os créditos utilizáveis no período.</p>
                                    <p><strong>Campo automático:</strong> Calculado automaticamente pelo sistema.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse051Quadro09">
                                    <strong>051-052 - Não se aplicam</strong>
                                </button>
                            </h2>
                            <div id="collapse051Quadro09" class="accordion-collapse collapse" data-bs-parent="#accordionCreditosQuadro09">
                                <div class="accordion-body">
                                    <p><strong>Status:</strong> Não se aplicam desde abril/2008.</p>
                                    <p><strong>Substituição:</strong> Valores devem ser informados via DCIP.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse060Quadro09">
                                    <strong>060 - Saldos credores recebidos de estabelecimentos consolidados</strong>
                                </button>
                            </h2>
                            <div id="collapse060Quadro09" class="accordion-collapse collapse" data-bs-parent="#accordionCreditosQuadro09">
                                <div class="accordion-body">
                                    <p><strong>Quando usar:</strong> Para estabelecimentos consolidados que recebem saldos credores de outros estabelecimentos do grupo.</p>
                                    <p><strong>Aplicação:</strong> Regime de apuração consolidada.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse070Quadro09">
                                    <strong>070 - Créditos recebidos por transferência</strong>
                                </button>
                            </h2>
                            <div id="collapse070Quadro09" class="accordion-collapse collapse" data-bs-parent="#accordionCreditosQuadro09">
                                <div class="accordion-body">
                                    <p><strong>Origem:</strong> AUC relativas aos créditos recebidos por transferência.</p>
                                    <p><strong>Inclui:</strong> Créditos de ressarcimento e restituição de ICMS-ST.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse075Quadro09">
                                    <strong>075 - Créditos declarados no DCIP</strong>
                                </button>
                            </h2>
                            <div id="collapse075Quadro09" class="accordion-collapse collapse" data-bs-parent="#accordionCreditosQuadro09">
                                <div class="accordion-body">
                                    <p><strong>Origem:</strong> Demonstrativo de Créditos Informados Previamente.</p>
                                    <p><strong>Informação:</strong> Créditos especiais informados no DCIP.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse076Quadro09">
                                    <strong>076 - Segregação dos Débitos Relativos às Saídas</strong>
                                </button>
                            </h2>
                            <div id="collapse076Quadro09" class="accordion-collapse collapse" data-bs-parent="#accordionCreditosQuadro09">
                                <div class="accordion-body">
                                    <p><strong>Origem:</strong> Item 010 do Quadro 04 (Débitos pelas saídas).</p>
                                    <p><strong>Finalidade:</strong> Segregar débitos de saídas beneficiadas por crédito presumido.</p>
                                    <p><strong>Transporte:</strong> Valor vai para o Quadro 14.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-success mt-2">
                        <h6 class="mb-1">080 - TOTAL DE CRÉDITOS</h6>
                        <p class="mb-0">Somatório automático de todos os itens de crédito (050 a 076).</p>
                    </div>
                </div>

                <!-- Ajustes da Apuração Decendial -->
                <div class="mb-4">
                    <h6 class="text-warning mb-3">
                        <i class="bi bi-sliders me-2"></i>Ajustes da Apuração Decendial e Antecipações
                    </h6>
                    <p>Campos aplicáveis apenas a contribuintes com regimes especiais de apuração.</p>
                    <div class="accordion" id="accordionAjustes">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse090Quadro09">
                                    <strong>090 - Imposto do 1º decêndio</strong>
                                </button>
                            </h2>
                            <div id="collapse090Quadro09" class="accordion-collapse collapse" data-bs-parent="#accordionAjustes">
                                <div class="accordion-body">
                                    <p><strong>Aplicação:</strong> Prestadoras de serviço de telecomunicação.</p>
                                    <p><strong>Valor:</strong> 1ª parcela da antecipação (RICMS-SC/01, art. 60, § 1º, X).</p>
                                    <div class="alert alert-danger small">
                                        <strong>❌ IMPORTANTE:</strong> A partir de março/2023, este campo não estará disponível.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse100Quadro09">
                                    <strong>100 - Imposto do 2º decêndio</strong>
                                </button>
                            </h2>
                            <div id="collapse100Quadro09" class="accordion-collapse collapse" data-bs-parent="#accordionAjustes">
                                <div class="accordion-body">
                                    <p><strong>Aplicação:</strong> Prestadoras de serviço de telecomunicação.</p>
                                    <p><strong>Valor:</strong> 2ª parcela da antecipação (RICMS-SC/01, art. 60, § 1º, X).</p>
                                    <div class="alert alert-danger small">
                                        <strong>❌ IMPORTANTE:</strong> A partir de março/2023, este campo não estará disponível.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse105Quadro09">
                                    <strong>105 - Antecipações Combustíveis</strong>
                                </button>
                            </h2>
                            <div id="collapse105Quadro09" class="accordion-collapse collapse" data-bs-parent="#accordionAjustes">
                                <div class="accordion-body">
                                    <p><strong>Aplicação:</strong> Industriais, distribuidores ou atacadistas de combustíveis.</p>
                                    <p><strong>Valor:</strong> Antecipação equivalente a 100% do montante devido no mês anterior.</p>
                                    <div class="alert alert-danger small">
                                        <strong>❌ IMPORTANTE:</strong> A partir de março/2023, este campo não estará disponível.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-warning mt-2">
                        <h6 class="mb-1">110 - TOTAL DE AJUSTES</h6>
                        <p class="mb-0">Somatório automático dos itens 090, 100 e 105.</p>
                    </div>
                </div>

                <!-- Apuração Final -->
                <div class="mb-4">
                    <h6 class="text-primary mb-3">
                        <i class="bi bi-calculator-fill me-2"></i>Apuração Final
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white">
                                    <h6 class="mb-0">Quando Débitos > Créditos + Ajustes</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <strong>Fórmula:</strong><br>
                                        <code>Saldo Devedor = Total Débitos - (Total Créditos + Total Ajustes)</code>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Resultado:</strong><br>
                                        <span class="badge bg-danger">120 - SALDO DEVEDOR</span>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Imposto a Recolher:</strong><br>
                                        <code>Imposto a Recolher = Saldo Devedor - Transferido</code>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Resultado Final:</strong><br>
                                        <span class="badge bg-danger">999 - IMPOSTO A RECOLHER</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">Quando Créditos + Ajustes > Débitos</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <strong>Fórmula:</strong><br>
                                        <code>Saldo Credor = (Total Créditos + Total Ajustes) - Total Débitos</code>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Resultado:</strong><br>
                                        <span class="badge bg-success">140 - SALDO CREDOR</span>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Saldo para Mês Seguinte:</strong><br>
                                        <code>Saldo Mês Seguinte = Saldo Credor - Transferido</code>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Resultado Final:</strong><br>
                                        <span class="badge bg-success">998 - SALDO CREDOR PARA O MÊS SEGUINTE</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Discriminação do Saldo Credor -->
                <div class="mb-4">
                    <h6 class="text-info mb-3">
                        <i class="bi bi-list-ol me-2"></i>Discriminação do Saldo Credor para o Mês Seguinte
                    </h6>
                    <p>Quando há saldo credor, deve-se discriminá-lo por categoria para controle futuro.</p>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="accordion" id="accordionDiscriminacao">
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse160Quadro09">
                                            <strong>160 - Saldo credor relativo à exportação</strong>
                                        </button>
                                    </h2>
                                    <div id="collapse160Quadro09" class="accordion-collapse collapse" data-bs-parent="#accordionDiscriminacao">
                                        <div class="accordion-body">
                                            <p>Parcela do saldo correspondente a créditos acumulados de operações destinadas ao exterior.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse170Quadro09">
                                            <strong>170 - Saldo credor relativo a saídas isentas</strong>
                                        </button>
                                    </h2>
                                    <div id="collapse170Quadro09" class="accordion-collapse collapse" data-bs-parent="#accordionDiscriminacao">
                                        <div class="accordion-body">
                                            <p>Parcela do saldo correspondente a operações ou prestações isentas ou não tributadas.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="accordion" id="accordionDiscriminacao2">
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse180Quadro09">
                                            <strong>180 - Saldo credor relativo a saídas diferidas</strong>
                                        </button>
                                    </h2>
                                    <div id="collapse180Quadro09" class="accordion-collapse collapse" data-bs-parent="#accordionDiscriminacao2">
                                        <div class="accordion-body">
                                            <p>Parcela do saldo correspondente a operações alcançadas pelo diferimento ou suspensão.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse190Quadro09">
                                            <strong>190 - Saldo credor relativo a outros créditos</strong>
                                        </button>
                                    </h2>
                                    <div id="collapse190Quadro09" class="accordion-collapse collapse" data-bs-parent="#accordionDiscriminacao2">
                                        <div class="accordion-body">
                                            <p>Valor do saldo credor que não corresponda às categorias específicas dos itens 160, 170 e 180.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Regras Importantes -->
                <div class="alert alert-warning">
                    <h6 class="alert-heading mb-2">
                        <i class="bi bi-exclamation-triangle me-2"></i>Regras Importantes
                    </h6>
                    <ul class="mb-0 small">
                        <li><strong>Obrigatório:</strong> Este quadro é obrigatório para todos os contribuintes no regime normal</li>
                        <li><strong>Cálculos automáticos:</strong> Muitos campos são calculados automaticamente pelo sistema</li>
                        <li><strong>Transporte automático:</strong> Valores dos Quadros 04 e 05 são transportados automaticamente</li>
                        <li><strong>Resultado condicional:</strong> Ou há imposto a recolher (item 999) ou saldo credor (item 998)</li>
                        <li><strong>DCIP:</strong> Muitos ajustes passaram a ser feitos via DCIP a partir de 2017</li>
                        <li><strong>Consolidação:</strong> Regras especiais para estabelecimentos consolidados</li>
                        <li><strong>Prazos:</strong> Vários campos deixaram de ser utilizados a partir de 2023</li>
                        <li><strong>Observações:</strong> Campo opcional para registrar informações relevantes</li>
                    </ul>
                </div>

                <!-- Exemplo Prático -->
                <div class="mb-4">
                    <h6 class="text-info mb-3">
                        <i class="bi bi-calculator me-2"></i>Exemplo Prático de Apuração
                    </h6>
                    <div class="card">
                        <div class="card-body">
                            <h6>Empresa com vendas de R$ 50.000,00 e compras de R$ 30.000,00 (ambas tributadas a 17%):</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Item</th>
                                            <th>Descrição</th>
                                            <th>Valor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>010</td>
                                            <td>Subtotal de débitos (saídas)</td>
                                            <td class="text-end">R$ 8.500,00</td>
                                        </tr>
                                        <tr>
                                            <td>050</td>
                                            <td>Subtotal de créditos (entradas)</td>
                                            <td class="text-end">R$ 5.100,00</td>
                                        </tr>
                                        <tr class="table-warning">
                                            <td>110</td>
                                            <td>Total de ajustes</td>
                                            <td class="text-end">R$ 0,00</td>
                                        </tr>
                                        <tr class="table-danger">
                                            <td>120</td>
                                            <td>Saldo devedor</td>
                                            <td class="text-end">R$ 3.400,00</td>
                                        </tr>
                                        <tr class="table-danger">
                                            <td>999</td>
                                            <td><strong>IMPOSTO A RECOLHER</strong></td>
                                            <td class="text-end"><strong>R$ 3.400,00</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <small class="text-muted">
                                <strong>Cálculo:</strong> Débitos (R$ 8.500) - Créditos (R$ 5.100) = Saldo Devedor (R$ 3.400).
                                Este valor deve ser recolhido aos cofres públicos.
                            </small>
                        </div>
                    </div>
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

<script>
// Executar imediatamente para conteúdo carregado via AJAX
(function() {
    // Definir dime_id globalmente para este quadro
    window.QUADRO09_CONFIG = {
        dimeId: <?php echo json_encode($dime_id); ?>
    };

    // Inicializar tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Máscaras para valores monetários
    setupMasks();

    // Calcular totais iniciais
    calcularTudo();
})();

function setupMasks() {
    // Máscaras para valores monetários
    const camposMonetarios = [
        // Débitos
        'item_011_complemento_debito_regime', 'item_020_saldos_devedores_consolidados',
        'item_030_debito_reserva_credito', 'item_036_segregacao_credito_presumido',
        'item_037_segregacao_credito_pagamento', 'item_038_segregacao_outros_creditos',
        // Créditos
        'item_060_saldos_credores_consolidados', 'item_070_creditos_transferencia',
        'item_075_creditos_dcip', 'item_076_segregacao_debitos_saidas',
        // Ajustes
        'item_090_imposto_primeiro_decendio', 'item_100_imposto_segundo_decendio',
        'item_105_antecipacoes_combustiveis',
        // Resultados
        'item_130_saldo_devedor_transferido', 'item_150_saldo_credor_transferido',
        // Discriminação
        'item_160_saldo_exportacao', 'item_170_saldo_isentas',
        'item_180_saldo_diferidas', 'item_190_saldo_outros_creditos'
    ];

    camposMonetarios.forEach(campoId => {
        const campo = document.getElementById(campoId);
        if (campo) {
            campo.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 2) {
                    value = value.slice(0, -2) + ',' + value.slice(-2);
                }
                if (value.length > 6) {
                    value = value.slice(0, -6) + '.' + value.slice(-6);
                }
                if (value.length > 10) {
                    value = value.slice(0, -10) + '.' + value.slice(-10);
                }
                e.target.value = value;

                // Recalcular tudo quando houver mudanças
                setTimeout(calcularTudo, 100);
            });

            // Formatar valor inicial se existir
            if (campo.value) {
                campo.value = parseFloat(campo.value.replace(',', '.')).toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
        }
    });
}

function calcularTudo() {
    // Calcular totais de débitos
    calcularTotalDebitos();

    // Calcular totais de créditos
    calcularTotalCreditos();

    // Calcular ajustes
    calcularTotalAjustes();

    // Calcular resultado final
    calcularResultadoFinal();
}

function calcularTotalDebitos() {
    const campos = [
        'item_010_subtotal_debitos', 'item_011_complemento_debito_regime',
        'item_020_saldos_devedores_consolidados', 'item_030_debito_reserva_credito',
        'item_036_segregacao_credito_presumido', 'item_037_segregacao_credito_pagamento',
        'item_038_segregacao_outros_creditos'
    ];

    let total = 0;
    campos.forEach(campoId => {
        const campo = document.getElementById(campoId);
        if (campo && campo.value) {
            let valorStr = campo.value.replace(/\./g, '').replace(',', '.');
            const valor = parseFloat(valorStr) || 0;
            total += valor;
        }
    });

    const campoTotal = document.getElementById('item_040_total_debitos');
    if (campoTotal) {
        campoTotal.value = total.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
}

function calcularTotalCreditos() {
    const campos = [
        'item_050_subtotal_creditos', 'item_051_nao_aplica', 'item_052_nao_aplica',
        'item_060_saldos_credores_consolidados', 'item_070_creditos_transferencia',
        'item_075_creditos_dcip', 'item_076_segregacao_debitos_saidas'
    ];

    let total = 0;
    campos.forEach(campoId => {
        const campo = document.getElementById(campoId);
        if (campo && campo.value) {
            let valorStr = campo.value.replace(/\./g, '').replace(',', '.');
            const valor = parseFloat(valorStr) || 0;
            total += valor;
        }
    });

    const campoTotal = document.getElementById('item_080_total_creditos');
    if (campoTotal) {
        campoTotal.value = total.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
}

function calcularTotalAjustes() {
    const campos = [
        'item_090_imposto_primeiro_decendio', 'item_100_imposto_segundo_decendio',
        'item_105_antecipacoes_combustiveis'
    ];

    let total = 0;
    campos.forEach(campoId => {
        const campo = document.getElementById(campoId);
        if (campo && campo.value) {
            let valorStr = campo.value.replace(/\./g, '').replace(',', '.');
            const valor = parseFloat(valorStr) || 0;
            total += valor;
        }
    });

    const campoTotal = document.getElementById('item_110_total_ajustes');
    if (campoTotal) {
        campoTotal.value = total.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
}

function calcularResultadoFinal() {
    // Obter valores totais
    const totalDebitos = getNumericValue('item_040_total_debitos');
    const totalCreditos = getNumericValue('item_080_total_creditos');
    const totalAjustes = getNumericValue('item_110_total_ajustes');

    const creditosMaisAjustes = totalCreditos + totalAjustes;

    if (totalDebitos > creditosMaisAjustes) {
        // Saldo devedor
        const saldoDevedor = totalDebitos - creditosMaisAjustes;
        document.getElementById('item_120_saldo_devedor').value = formatMoney(saldoDevedor);
        document.getElementById('item_140_saldo_credor').value = '0,00';
        document.getElementById('item_998_saldo_credor_mes_seguinte').value = '0,00';

        // Imposto a recolher = saldo devedor - transferido
        const transferido = getNumericValue('item_130_saldo_devedor_transferido');
        const impostoRecolher = Math.max(0, saldoDevedor - transferido);
        document.getElementById('item_999_imposto_recolher').value = formatMoney(impostoRecolher);

    } else {
        // Saldo credor
        const saldoCredor = creditosMaisAjustes - totalDebitos;
        document.getElementById('item_140_saldo_credor').value = formatMoney(saldoCredor);
        document.getElementById('item_120_saldo_devedor').value = '0,00';
        document.getElementById('item_999_imposto_recolher').value = '0,00';

        // Saldo credor para mês seguinte = saldo credor - transferido
        const transferido = getNumericValue('item_150_saldo_credor_transferido');
        const saldoMesSeguinte = Math.max(0, saldoCredor - transferido);
        document.getElementById('item_998_saldo_credor_mes_seguinte').value = formatMoney(saldoMesSeguinte);
    }
}

function getNumericValue(fieldId) {
    const campo = document.getElementById(fieldId);
    if (!campo || !campo.value) return 0;
    return parseFloat(campo.value.replace(/\./g, '').replace(',', '.')) || 0;
}

function formatMoney(value) {
    return value.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Definir função global para ser acessível do editar_dime.php
window.salvarQuadro09 = function() {
    // Combinar dados de todos os formulários
    const formData = new FormData();

    // Dados do formulário principal
    const form1 = document.getElementById('form-quadro09');
    if (form1) {
        new FormData(form1).forEach((value, key) => {
            formData.append(key, value);
        });
    }

    // Dados do formulário de créditos
    const form2 = document.getElementById('form-quadro09-creditos');
    if (form2) {
        new FormData(form2).forEach((value, key) => {
            if (!formData.has(key)) { // Evitar duplicatas
                formData.append(key, value);
            }
        });
    }

    // Dados do formulário de outras informações
    const form3 = document.getElementById('form-quadro09-outras');
    if (form3) {
        new FormData(form3).forEach((value, key) => {
            if (!formData.has(key)) { // Evitar duplicatas
                formData.append(key, value);
            }
        });
    }

    // Mostrar loading
    const btn = document.querySelector('button[onclick="salvarQuadro09()"]');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Salvando...';
    }

    // URL para salvar
    const postUrl = 'quadros/ajax_save_0405.php';

    // Adicionar dime_id
    const dimeId = window.QUADRO09_CONFIG?.dimeId || window.DIME_CONFIG?.dimeId || new URLSearchParams(window.location.search).get('dime_id') || new URLSearchParams(window.parent.location.search).get('id');
    formData.append('dime_id', dimeId);

    fetch(postUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
        } else {
            showMessage('❌ ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('💥 Erro na requisição:', error);
        showMessage('💥 Erro de conexão: ' + error.message, 'danger');
    })
    .finally(() => {
        // Restaurar botão
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-save me-2"></i>Salvar Quadro 09';
        }
    });
};

function showMessage(message, type = 'info') {
    // Criar alerta Bootstrap
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show position-fixed"
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    // Adicionar ao body
    document.body.insertAdjacentHTML('beforeend', alertHtml);

    // Auto-remover após 5 segundos
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (alert.textContent.includes(message.substring(0, 50))) {
                alert.remove();
            }
        });
    }, 5000);
}
</script>
