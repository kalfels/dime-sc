<?php
/**
 * DIME-SC - Quadro 11 - Informações sobre Substituição Tributária
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

    // Verificar se é POST (salvar dados)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'save_quadro11') {
            // Salvar dados do Quadro 11 (Substituição Tributária)
            $campos = [
                'dime_declaracao_id' => $dime_id,
                // Campos conforme Manual DIME-SEF atualizado
                'item_010_valor_produtos' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_010_valor_produtos'] ?? '0')),
                'item_020_valor_ipi' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_020_valor_ipi'] ?? '0')),
                'item_030_despesas_acessorias' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_030_despesas_acessorias'] ?? '0')),
                'item_040_base_calculo_icms_proprio' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_040_base_calculo_icms_proprio'] ?? '0')),
                'item_050_icms_proprio' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_050_icms_proprio'] ?? '0')),
                'item_060_base_calculo_imposto_retido' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_060_base_calculo_imposto_retido'] ?? '0')),
                'item_065_imposto_retido_apurado' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_065_imposto_retido_apurado'] ?? '0')),

                // Débitos
                'item_070_total_imposto_retido' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_070_total_imposto_retido'] ?? '0')),
                'item_073_imposto_st_entrada_regime_especial' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_073_imposto_st_entrada_regime_especial'] ?? '0')),
                'item_074_outros_debitos' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_074_outros_debitos'] ?? '0')),
                'item_075_saldos_devedores_consolidados' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_075_saldos_devedores_consolidados'] ?? '0')),
                'item_080_total_debitos' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_080_total_debitos'] ?? '0')),

                // Créditos
                'item_090_saldo_credor_anterior_st' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_090_saldo_credor_anterior_st'] ?? '0')),
                'item_100_devolucao_mercadorias_desfazimento' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_100_devolucao_mercadorias_desfazimento'] ?? '0')),
                'item_105_creditos_dcip' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_105_creditos_dcip'] ?? '0')),
                'item_110_ressarcimento_icms_st' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_110_ressarcimento_icms_st'] ?? '0')),
                'item_115_ressarcimento_icms_st_nfe' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_115_ressarcimento_icms_st_nfe'] ?? '0')),
                'item_116_devolucao_mercadorias_desfazimento_atual' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_116_devolucao_mercadorias_desfazimento_atual'] ?? '0')),
                'item_120_outros_creditos' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_120_outros_creditos'] ?? '0')),
                'item_125_saldos_credores_consolidados' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_125_saldos_credores_consolidados'] ?? '0')),
                'item_130_total_creditos' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_130_total_creditos'] ?? '0')),

                // Ajustes
                'item_140_imposto_primeiro_decendio' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_140_imposto_primeiro_decendio'] ?? '0')),
                'item_150_imposto_segundo_decendio' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_150_imposto_segundo_decendio'] ?? '0')),
                'item_155_antecipacoes_combustiveis' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_155_antecipacoes_combustiveis'] ?? '0')),
                'item_160_total_ajustes' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_160_total_ajustes'] ?? '0')),

                // Resultados
                'item_170_saldo_devedor' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_170_saldo_devedor'] ?? '0')),
                'item_180_saldo_devedor_transferido' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_180_saldo_devedor_transferido'] ?? '0')),
                'item_190_saldo_credor' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_190_saldo_credor'] ?? '0')),
                'item_200_saldo_credor_transferido' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_200_saldo_credor_transferido'] ?? '0')),
                'item_899_debitos_especiais_st' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_899_debitos_especiais_st'] ?? '0')),
                'item_998_saldo_credor_mes_seguinte' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_998_saldo_credor_mes_seguinte'] ?? '0')),
                'item_999_imposto_recolher_st' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_999_imposto_recolher_st'] ?? '0')),

                'observacoes' => trim($_POST['observacoes_quadro11'] ?? '')
            ];

            // Verificar se já existe registro para este dime_id
            $stmt = $pdo->prepare("SELECT id FROM dime_quadro_11 WHERE dime_declaracao_id = ?");
            $stmt->execute([$dime_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update
                $sql = "UPDATE dime_quadro_11 SET ";
                $updates = [];
                foreach ($campos as $key => $value) {
                    if ($key !== 'dime_declaracao_id') {
                        $updates[] = "$key = ?";
                    }
                }
                $sql .= implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE dime_declaracao_id = ?";
                $stmt = $pdo->prepare($sql);
                $values = array_values($campos);
                array_pop($values); // Remove dime_declaracao_id
                $values[] = $dime_id;
                $stmt->execute($values);
            } else {
                // Insert
                $sql = "INSERT INTO dime_quadro_11 (" . implode(', ', array_keys($campos)) . ") VALUES (" . str_repeat('?, ', count($campos) - 1) . "?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($campos));
            }

            echo json_encode([
                'success' => true,
                'message' => 'Quadro 11 (Substituição Tributária) salvo com sucesso!',
                'data' => $campos
            ]);
            exit;
        }
    }

    // Carregar dados do Quadro 11
    $stmt = $pdo->prepare("SELECT * FROM dime_quadro_11 WHERE dime_declaracao_id = ?");
    $stmt->execute([$dime_id]);
    $dados_quadro11 = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Se não há dados no Quadro 11, inicializar com valores vazios
    if (empty($dados_quadro11)) {
        $dados_quadro11 = [
            'item_010_valor_produtos' => 0.00,
            'item_020_valor_ipi' => 0.00,
            'item_030_despesas_acessorias' => 0.00,
            'item_040_base_calculo_icms_proprio' => 0.00,
            'item_050_icms_proprio' => 0.00,
            'item_060_base_calculo_imposto_retido' => 0.00,
            'item_065_imposto_retido_apurado' => 0.00,
            'item_070_total_imposto_retido' => 0.00,
            'item_073_imposto_st_entrada_regime_especial' => 0.00,
            'item_074_outros_debitos' => 0.00,
            'item_075_saldos_devedores_consolidados' => 0.00,
            'item_080_total_debitos' => 0.00,
            'item_090_saldo_credor_anterior_st' => 0.00,
            'item_100_devolucao_mercadorias_desfazimento' => 0.00,
            'item_105_creditos_dcip' => 0.00,
            'item_110_ressarcimento_icms_st' => 0.00,
            'item_115_ressarcimento_icms_st_nfe' => 0.00,
            'item_116_devolucao_mercadorias_desfazimento_atual' => 0.00,
            'item_120_outros_creditos' => 0.00,
            'item_125_saldos_credores_consolidados' => 0.00,
            'item_130_total_creditos' => 0.00,
            'item_140_imposto_primeiro_decendio' => 0.00,
            'item_150_imposto_segundo_decendio' => 0.00,
            'item_155_antecipacoes_combustiveis' => 0.00,
            'item_160_total_ajustes' => 0.00,
            'item_170_saldo_devedor' => 0.00,
            'item_180_saldo_devedor_transferido' => 0.00,
            'item_190_saldo_credor' => 0.00,
            'item_200_saldo_credor_transferido' => 0.00,
            'item_899_debitos_especiais_st' => 0.00,
            'item_998_saldo_credor_mes_seguinte' => 0.00,
            'item_999_imposto_recolher_st' => 0.00,
            'observacoes' => ''
        ];
    }

} catch (Exception $e) {
    $dados_quadro11 = [];
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
        <i class="bi bi-arrow-repeat text-orange me-2"></i>
        Quadro 11 - Informações sobre Substituição Tributária
    </h5>
    <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalAjudaQuadro11">
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
<ul class="nav nav-tabs" id="quadro11Tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="dados-basicos-tab" data-bs-toggle="tab" data-bs-target="#dados-basicos" type="button" role="tab" aria-controls="dados-basicos" aria-selected="true">
            <i class="bi bi-info-circle me-2"></i>Dados Básicos
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="debitos-st-tab" data-bs-toggle="tab" data-bs-target="#debitos-st" type="button" role="tab" aria-controls="debitos-st" aria-selected="false">
            <i class="bi bi-dash-circle me-2"></i>Débitos ST
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="creditos-st-tab" data-bs-toggle="tab" data-bs-target="#creditos-st" type="button" role="tab" aria-controls="creditos-st" aria-selected="false">
            <i class="bi bi-plus-circle me-2"></i>Créditos ST
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="apuracao-st-tab" data-bs-toggle="tab" data-bs-target="#apuracao-st" type="button" role="tab" aria-controls="apuracao-st" aria-selected="false">
            <i class="bi bi-calculator me-2"></i>Apuração ST
        </button>
    </li>
</ul>

<!-- Tab content -->
<div class="tab-content mt-4" id="quadro11TabContent">

    <!-- Aba Dados Básicos -->
    <div class="tab-pane fade show active" id="dados-basicos" role="tabpanel" aria-labelledby="dados-basicos-tab">
        <form id="form-quadro11-basicos">
            <div class="row g-3">
                <div class="col-12">
                    <h6 class="text-orange fw-bold">INFORMAÇÕES SOBRE SUBSTITUIÇÃO TRIBUTÁRIA</h6>
                    <hr class="my-2">
                    <div class="alert alert-info small">
                        <strong>💡 Campos marcados com <span class="text-danger">*</span> são obrigatórios</strong> quando houver atividade no quadro (valores diferentes de zero).
                        O campo Imposto a Recolher (999) é obrigatório apenas quando há saldo devedor.
                        Certifique-se de clicar em "Calcular Tudo" antes de salvar.
                    </div>
                </div>

                <!-- Campos não preenchidos a partir de agosto/2013 -->
                <div class="col-12">
                    <div class="alert alert-warning">
                        <strong>⚠️ Campos não preenchidos a partir de agosto/2013:</strong> Itens 010, 020, 030, 040, 050, 100, 110, 120
                    </div>
                </div>

                <!-- Campos não preenchidos a partir de maio/2024 -->
                <div class="col-12">
                    <div class="alert alert-danger">
                        <strong>❌ Campos não preenchidos a partir de maio/2024:</strong> Itens 060, 065
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="item_060_base_calculo_imposto_retido" class="form-label">
                        <strong>060 - Base de Cálculo do Imposto Retido</strong>
                        <small class="text-muted d-block">Não preencher a partir de maio/2024</small>
                    </label>
                    <input type="text" class="form-control debito11-field" id="item_060_base_calculo_imposto_retido"
                           name="item_060_base_calculo_imposto_retido"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_060_base_calculo_imposto_retido'); ?>"
                           placeholder="0,00" readonly>
                </div>

                <div class="col-md-6">
                    <label for="item_065_imposto_retido_apurado" class="form-label">
                        <strong>065 - Imposto Retido apurado por mercadoria e recolhido por operação</strong>
                        <small class="text-muted d-block">Não preencher a partir de maio/2024</small>
                    </label>
                    <input type="text" class="form-control debito11-field" id="item_065_imposto_retido_apurado"
                           name="item_065_imposto_retido_apurado"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_065_imposto_retido_apurado'); ?>"
                           placeholder="0,00" readonly>
                </div>

                <div class="col-12">
                    <label for="observacoes_quadro11" class="form-label">
                        <strong>Observações</strong>
                    </label>
                    <textarea class="form-control" id="observacoes_quadro11" name="observacoes_quadro11" rows="3"
                              placeholder="Observações adicionais (opcional)"><?php echo safe_value($dados_quadro11, 'observacoes'); ?></textarea>
                </div>
            </div>
        </form>
    </div>

    <!-- Aba Débitos ST -->
    <div class="tab-pane fade" id="debitos-st" role="tabpanel" aria-labelledby="debitos-st-tab">
        <form id="form-quadro11-debitos">
            <div class="row g-3">
                <div class="col-12">
                    <h6 class="text-danger fw-bold">DÉBITOS - SUBSTITUIÇÃO TRIBUTÁRIA</h6>
                    <hr class="my-2">
                </div>

                <div class="col-md-6">
                    <label for="item_070_total_imposto_retido" class="form-label">
                        <strong>070 - Total do Imposto Retido</strong>
                        <small class="text-muted d-block">ICMS retido por substituição tributária</small>
                    </label>
                    <input type="text" class="form-control debito11-field" id="item_070_total_imposto_retido"
                           name="item_070_total_imposto_retido"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_070_total_imposto_retido'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_073_imposto_st_entrada_regime_especial" class="form-label">
                        <strong>073 - Imposto ST devido na entrada com regime especial para apuração mensal</strong>
                        <small class="text-muted d-block">Regime especial de apuração</small>
                    </label>
                    <input type="text" class="form-control debito11-field" id="item_073_imposto_st_entrada_regime_especial"
                           name="item_073_imposto_st_entrada_regime_especial"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_073_imposto_st_entrada_regime_especial'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_074_outros_debitos" class="form-label">
                        <strong>074 - Outros Débitos</strong>
                        <small class="text-muted d-block">Débitos não previstos nos itens 070, 073, 075 e 200</small>
                    </label>
                    <input type="text" class="form-control debito11-field" id="item_074_outros_debitos"
                           name="item_074_outros_debitos"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_074_outros_debitos'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_075_saldos_devedores_consolidados" class="form-label">
                        <strong>075 - Saldos devedores recebidos de estabelecimentos consolidados</strong>
                        <small class="text-muted d-block">Regime de apuração consolidada</small>
                    </label>
                    <input type="text" class="form-control debito11-field" id="item_075_saldos_devedores_consolidados"
                           name="item_075_saldos_devedores_consolidados"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_075_saldos_devedores_consolidados'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_080_total_debitos" class="form-label fw-bold">
                        <strong>080 - TOTAL DE DÉBITOS</strong> <span class="text-danger">*</span>
                        <small class="text-muted ms-1">(calculado automaticamente)</small>
                    </label>
                    <input type="text" class="form-control fw-bold bg-light" id="item_080_total_debitos"
                           name="item_080_total_debitos"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_080_total_debitos'); ?>"
                           placeholder="0,00" readonly>
                </div>

                <div class="col-md-6">
                    <label for="item_899_debitos_especiais_st" class="form-label fw-bold text-primary">
                        <strong>899 - Débitos Especiais de Substituição Tributária</strong>
                        <small class="text-muted d-block">Recolhimento por mercadoria/por operação</small>
                    </label>
                    <input type="text" class="form-control debito11-field" id="item_899_debitos_especiais_st"
                           name="item_899_debitos_especiais_st"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_899_debitos_especiais_st'); ?>"
                           placeholder="0,00">
                </div>
            </div>
        </form>
    </div>

    <!-- Aba Créditos ST -->
    <div class="tab-pane fade" id="creditos-st" role="tabpanel" aria-labelledby="creditos-st-tab">
        <form id="form-quadro11-creditos">
            <div class="row g-3">
                <div class="col-12">
                    <h6 class="text-success fw-bold">CRÉDITOS - SUBSTITUIÇÃO TRIBUTÁRIA</h6>
                    <hr class="my-2">
                </div>

                <div class="col-md-6">
                    <label for="item_090_saldo_credor_anterior_st" class="form-label">
                        <strong>090 - Saldo credor do período anterior sobre a substituição tributária</strong>
                        <small class="text-muted d-block">Valor do item 998 da DIME anterior</small>
                    </label>
                    <input type="text" class="form-control credito11-field" id="item_090_saldo_credor_anterior_st"
                           name="item_090_saldo_credor_anterior_st"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_090_saldo_credor_anterior_st'); ?>"
                           placeholder="0,00">
                </div>

                <!-- Campos não preenchidos a partir de agosto/2013 -->
                <div class="col-md-6">
                    <label for="item_100_devolucao_mercadorias_desfazimento" class="form-label">
                        <strong>100 - Devolução de Mercadorias e Desfazimento de Venda</strong>
                        <small class="text-muted d-block">Não preencher a partir de agosto/2013</small>
                    </label>
                    <input type="text" class="form-control" id="item_100_devolucao_mercadorias_desfazimento"
                           name="item_100_devolucao_mercadorias_desfazimento"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_100_devolucao_mercadorias_desfazimento'); ?>"
                           placeholder="0,00" readonly>
                </div>

                <div class="col-md-6">
                    <label for="item_105_creditos_dcip" class="form-label">
                        <strong>105 - Créditos declarados no DCIP</strong>
                        <small class="text-muted d-block">Demonstrativo de Créditos Informados Previamente</small>
                    </label>
                    <input type="text" class="form-control credito11-field" id="item_105_creditos_dcip"
                           name="item_105_creditos_dcip"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_105_creditos_dcip'); ?>"
                           placeholder="0,00">
                </div>

                <!-- Campos não preenchidos a partir de agosto/2013 -->
                <div class="col-md-6">
                    <label for="item_110_ressarcimento_icms_st" class="form-label">
                        <strong>110 - Ressarcimento de ICMS Substituição Tributária</strong>
                        <small class="text-muted d-block">Não preencher a partir de agosto/2013</small>
                    </label>
                    <input type="text" class="form-control" id="item_110_ressarcimento_icms_st"
                           name="item_110_ressarcimento_icms_st"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_110_ressarcimento_icms_st'); ?>"
                           placeholder="0,00" readonly>
                </div>

                <div class="col-md-6">
                    <label for="item_115_ressarcimento_icms_st_nfe" class="form-label">
                        <strong>115 - Ressarcimento de ICMS substituição tributária acobertado por NF-e</strong>
                        <small class="text-muted d-block">Com indicação no bloco Z da NF-e</small>
                    </label>
                    <input type="text" class="form-control credito11-field" id="item_115_ressarcimento_icms_st_nfe"
                           name="item_115_ressarcimento_icms_st_nfe"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_115_ressarcimento_icms_st_nfe'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_116_devolucao_mercadorias_desfazimento_atual" class="form-label">
                        <strong>116 - Devolução de Mercadorias e Desfazimento de Venda</strong>
                        <small class="text-muted d-block">Campo atual para devoluções</small>
                    </label>
                    <input type="text" class="form-control credito11-field" id="item_116_devolucao_mercadorias_desfazimento_atual"
                           name="item_116_devolucao_mercadorias_desfazimento_atual"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_116_devolucao_mercadorias_desfazimento_atual'); ?>"
                           placeholder="0,00">
                </div>

                <!-- Campos não preenchidos a partir de agosto/2013 -->
                <div class="col-md-6">
                    <label for="item_120_outros_creditos" class="form-label">
                        <strong>120 - Outros créditos</strong>
                        <small class="text-muted d-block">Não preencher a partir de agosto/2013</small>
                    </label>
                    <input type="text" class="form-control" id="item_120_outros_creditos"
                           name="item_120_outros_creditos"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_120_outros_creditos'); ?>"
                           placeholder="0,00" readonly>
                </div>

                <div class="col-md-6">
                    <label for="item_125_saldos_credores_consolidados" class="form-label">
                        <strong>125 - Saldos credores recebidos de estabelecimentos consolidados</strong>
                        <small class="text-muted d-block">Regime de apuração consolidada</small>
                    </label>
                    <input type="text" class="form-control credito11-field" id="item_125_saldos_credores_consolidados"
                           name="item_125_saldos_credores_consolidados"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_125_saldos_credores_consolidados'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_130_total_creditos" class="form-label fw-bold">
                        <strong>130 - TOTAL DE CRÉDITOS</strong> <span class="text-danger">*</span>
                        <small class="text-muted ms-1">(calculado automaticamente)</small>
                    </label>
                    <input type="text" class="form-control fw-bold bg-light" id="item_130_total_creditos"
                           name="item_130_total_creditos"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_130_total_creditos'); ?>"
                           placeholder="0,00" readonly>
                </div>
            </div>
        </form>
    </div>

    <!-- Aba Apuração ST -->
    <div class="tab-pane fade" id="apuracao-st" role="tabpanel" aria-labelledby="apuracao-st-tab">
        <form id="form-quadro11-apuracao">
            <div class="row g-3">
                <div class="col-12">
                    <h6 class="text-primary fw-bold">APURAÇÃO DA SUBSTITUIÇÃO TRIBUTÁRIA</h6>
                    <hr class="my-2">
                </div>

                <!-- Ajustes -->
                <div class="col-12">
                    <h6 class="text-warning fw-bold">AJUSTES DA APURAÇÃO DECENDIAL E ANTECIPAÇÕES</h6>
                    <hr class="my-2">
                </div>

                <!-- Campos não aplicáveis -->
                <div class="col-md-6">
                    <label for="item_140_imposto_primeiro_decendio" class="form-label">
                        <strong>140 - Imposto do primeiro decêndio</strong>
                        <small class="text-muted d-block">Não se aplica desde fevereiro/2010</small>
                    </label>
                    <input type="text" class="form-control" id="item_140_imposto_primeiro_decendio"
                           name="item_140_imposto_primeiro_decendio"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_140_imposto_primeiro_decendio'); ?>"
                           placeholder="0,00" readonly>
                </div>

                <div class="col-md-6">
                    <label for="item_150_imposto_segundo_decendio" class="form-label">
                        <strong>150 - Imposto do Segundo Decêndio</strong>
                        <small class="text-muted d-block">Não se aplica desde fevereiro/2010</small>
                    </label>
                    <input type="text" class="form-control" id="item_150_imposto_segundo_decendio"
                           name="item_150_imposto_segundo_decendio"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_150_imposto_segundo_decendio'); ?>"
                           placeholder="0,00" readonly>
                </div>

                <div class="col-md-6">
                    <label for="item_155_antecipacoes_combustiveis" class="form-label">
                        <strong>155 - Antecipações Combustíveis Líquidos e Gasosos</strong>
                        <small class="text-muted d-block">Não preencher a partir de março/2023</small>
                    </label>
                    <input type="text" class="form-control ajuste11-field" id="item_155_antecipacoes_combustiveis"
                           name="item_155_antecipacoes_combustiveis"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_155_antecipacoes_combustiveis'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_160_total_ajustes" class="form-label fw-bold">
                        <strong>160 - TOTAL DE AJUSTES</strong>
                        <small class="text-muted ms-1">(calculado automaticamente)</small>
                    </label>
                    <input type="text" class="form-control fw-bold bg-light" id="item_160_total_ajustes"
                           name="item_160_total_ajustes"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_160_total_ajustes'); ?>"
                           placeholder="0,00" readonly>
                </div>

                <!-- Resultados -->
                <div class="col-12">
                    <h6 class="text-primary fw-bold">RESULTADOS DA APURAÇÃO</h6>
                    <hr class="my-2">
                </div>

                <!-- Resultado condicional -->
                <div class="col-md-6">
                    <label for="item_170_saldo_devedor" class="form-label fw-bold text-danger">
                        <strong>170 - SALDO DEVEDOR</strong>
                        <small class="text-muted d-block">Se débitos > créditos + ajustes</small>
                    </label>
                    <input type="text" class="form-control fw-bold bg-light" id="item_170_saldo_devedor"
                           name="item_170_saldo_devedor"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_170_saldo_devedor'); ?>"
                           placeholder="0,00" readonly>
                </div>

                <div class="col-md-6">
                    <label for="item_180_saldo_devedor_transferido" class="form-label">
                        <strong>180 - Saldo devedor transferido ao estabelecimento consolidador</strong>
                        <small class="text-muted d-block">Regime de apuração consolidada</small>
                    </label>
                    <input type="text" class="form-control" id="item_180_saldo_devedor_transferido"
                           name="item_180_saldo_devedor_transferido"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_180_saldo_devedor_transferido'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_999_imposto_recolher_st" class="form-label fw-bold text-danger">
                        <strong>999 - IMPOSTO A RECOLHER SOBRE A SUBSTITUIÇÃO TRIBUTÁRIA</strong> <span class="text-danger">*</span>
                        <small class="text-muted d-block">Valor final a recolher</small>
                    </label>
                    <input type="text" class="form-control fw-bold bg-danger text-white" id="item_999_imposto_recolher_st"
                           name="item_999_imposto_recolher_st"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_999_imposto_recolher_st'); ?>"
                           placeholder="0,00" readonly>
                </div>

                <div class="col-md-6">
                    <label for="item_190_saldo_credor" class="form-label fw-bold text-success">
                        <strong>190 - SALDO CREDOR</strong>
                        <small class="text-muted d-block">Se créditos + ajustes > débitos</small>
                    </label>
                    <input type="text" class="form-control fw-bold bg-light" id="item_190_saldo_credor"
                           name="item_190_saldo_credor"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_190_saldo_credor'); ?>"
                           placeholder="0,00" readonly>
                </div>

                <div class="col-md-6">
                    <label for="item_200_saldo_credor_transferido" class="form-label">
                        <strong>200 - Saldo credor transferido ao estabelecimento consolidador</strong>
                        <small class="text-muted d-block">Regime de apuração consolidada</small>
                    </label>
                    <input type="text" class="form-control" id="item_200_saldo_credor_transferido"
                           name="item_200_saldo_credor_transferido"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_200_saldo_credor_transferido'); ?>"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="item_998_saldo_credor_mes_seguinte" class="form-label fw-bold text-success">
                        <strong>998 - SALDO CREDOR PARA O MÊS SEGUINTE</strong>
                        <small class="text-muted d-block">Valor transportado para o próximo período</small>
                    </label>
                    <input type="text" class="form-control fw-bold bg-success text-white" id="item_998_saldo_credor_mes_seguinte"
                           name="item_998_saldo_credor_mes_seguinte"
                           value="<?php echo safe_money_value($dados_quadro11, 'item_998_saldo_credor_mes_seguinte'); ?>"
                           placeholder="0,00" readonly>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="d-flex justify-content-end mt-4">
    <button type="button" class="btn btn-info me-2" onclick="calcularTudoQuadro11()">
        <i class="bi bi-calculator me-2"></i>Calcular Tudo
    </button>
    <button type="button" class="btn btn-primary" onclick="salvarQuadro11()">
        <i class="bi bi-save me-2"></i>Salvar Quadro 11
    </button>
</div>

<!-- Modal de Ajuda do Quadro 11 -->
<div class="modal fade" id="modalAjudaQuadro11" tabindex="-1" aria-labelledby="modalAjudaQuadro11Label" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-orange text-dark">
                <h5 class="modal-title" id="modalAjudaQuadro11Label">
                    <i class="bi bi-question-circle me-2"></i>
                    Ajuda - Quadro 11: Informações sobre Substituição Tributária
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">

                <!-- Visão Geral -->
                <div class="mb-4">
                    <h6 class="text-orange mb-3">
                        <i class="bi bi-info-circle me-2"></i>Visão Geral
                    </h6>
                    <p>O Quadro 11 registra as informações sobre <strong>Substituição Tributária (ST)</strong> do ICMS. Este quadro é específico para contribuintes que comercializam produtos sujeitos à ST, atuando como substituto tributário.</p>
                    <div class="alert alert-orange">
                        <strong>🔄 Substituição Tributária:</strong> Regime onde o estabelecimento calcula e recolhe antecipadamente o ICMS devido nas operações subsequentes.
                    </div>
                    <div class="alert alert-warning">
                        <strong>⚠️ Obrigatório para:</strong> Estabelecimentos que emitem NF-e com retenção de ST ou recebem mercadorias sem retenção devida.
                    </div>
                </div>

                <!-- Estrutura do Quadro -->
                <div class="mb-4">
                    <h6 class="text-primary mb-3">
                        <i class="bi bi-table me-2"></i>Estrutura do Quadro
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Seção</th>
                                    <th>Campos</th>
                                    <th>Descrição</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Dados Básicos</strong></td>
                                    <td>010-065</td>
                                    <td>Informações sobre produtos sujeitos à ST</td>
                                </tr>
                                <tr>
                                    <td><strong>Débitos ST</strong></td>
                                    <td>070-080, 899</td>
                                    <td>Obrigações de ST a recolher</td>
                                </tr>
                                <tr>
                                    <td><strong>Créditos ST</strong></td>
                                    <td>090-130</td>
                                    <td>Direitos creditórios de ST</td>
                                </tr>
                                <tr>
                                    <td><strong>Apuração ST</strong></td>
                                    <td>140-200, 998-999</td>
                                    <td>Cálculo final e resultados</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Campos por Status -->
                <div class="mb-4">
                    <h6 class="text-warning mb-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>Status dos Campos por Período
                    </h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card border-warning mb-3">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0">Não preenchidos desde ago/2013</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="small mb-0">
                                        <li>010 - Valor dos produtos</li>
                                        <li>020 - Valor do IPI</li>
                                        <li>030 - Despesas acessórias</li>
                                        <li>040 - Base cálculo ICMS próprio</li>
                                        <li>050 - ICMS próprio</li>
                                        <li>100 - Devolução mercadorias</li>
                                        <li>110 - Ressarcimento ICMS ST</li>
                                        <li>120 - Outros créditos</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-danger mb-3">
                                <div class="card-header bg-danger text-white">
                                    <h6 class="mb-0">Não preenchidos desde mai/2024</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="small mb-0">
                                        <li>060 - Base cálculo imposto retido</li>
                                        <li>065 - Imposto retido apurado</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-secondary mb-3">
                                <div class="card-header bg-secondary text-white">
                                    <h6 class="mb-0">Não aplicáveis</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="small mb-0">
                                        <li>140 - Imposto 1º decêndio</li>
                                        <li>150 - Imposto 2º decêndio</li>
                                        <li>155 - Antecipações (desde mar/2023)</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detalhes dos Campos Ativos -->
                <div class="mb-4">
                    <h6 class="text-primary mb-3">
                        <i class="bi bi-file-earmark-text me-2"></i>Campos Ativos - Detalhes
                    </h6>
                    <div class="accordion" id="accordionCamposQuadro11">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse070Quadro11">
                                    <strong>070 - Total do Imposto Retido</strong>
                                </button>
                            </h2>
                            <div id="collapse070Quadro11" class="accordion-collapse collapse show" data-bs-parent="#accordionCamposQuadro11">
                                <div class="accordion-body">
                                    <p><strong>Somatório:</strong> ICMS retido por ST em todas as NF-e emitidas no período.</p>
                                    <p><strong>Origem:</strong> Campo VL_RETENÇAO_ST do registro E210 da EFD.</p>
                                    <p><strong>Quando usar:</strong> Sempre que o estabelecimento atuou como substituto tributário.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse073Quadro11">
                                    <strong>073 - Imposto ST devido na entrada com regime especial</strong>
                                </button>
                            </h2>
                            <div id="collapse073Quadro11" class="accordion-collapse collapse" data-bs-parent="#accordionCamposQuadro11">
                                <div class="accordion-body">
                                    <p><strong>Aplicação:</strong> Operações de entrada com regime especial de apuração mensal.</p>
                                    <p><strong>Base legal:</strong> RICMS-SC/01, art. 17, § 12º.</p>
                                    <p><strong>Exemplo:</strong> Quando o imposto é apurado na entrada mas recolhido mensalmente.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse090Quadro11">
                                    <strong>090 - Saldo credor do período anterior</strong>
                                </button>
                            </h2>
                            <div id="collapse090Quadro11" class="accordion-collapse collapse" data-bs-parent="#accordionCamposQuadro11">
                                <div class="accordion-body">
                                    <p><strong>Origem:</strong> Item 998 da DIME do mês anterior.</p>
                                    <p><strong>Transporte automático:</strong> Saldo credor de ST transferido do período anterior.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse105Quadro11">
                                    <strong>105 - Créditos declarados no DCIP</strong>
                                </button>
                            </h2>
                            <div id="collapse105Quadro11" class="accordion-collapse collapse" data-bs-parent="#accordionCamposQuadro11">
                                <div class="accordion-body">
                                    <p><strong>Origem:</strong> Demonstrativo de Créditos Informados Previamente.</p>
                                    <p><strong>Códigos:</strong> SC12, SC13, SC14, SC11, SC21, SC61 da Tabela 5.1.1 B.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse115Quadro11">
                                    <strong>115 - Ressarcimento de ICMS ST acobertado por NF-e</strong>
                                </button>
                            </h2>
                            <div id="collapse115Quadro11" class="accordion-collapse collapse" data-bs-parent="#accordionCamposQuadro11">
                                <div class="accordion-body">
                                    <p><strong>Requisito:</strong> NF-e com indicação no bloco Z, campo obsFisco.</p>
                                    <p><strong>Valor:</strong> Ressarcimento apropriado conforme § 3º do art. 25 do Anexo 3.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse899Quadro11">
                                    <strong>899 - Débitos Especiais de Substituição Tributária</strong>
                                </button>
                            </h2>
                            <div id="collapse899Quadro11" class="accordion-collapse collapse" data-bs-parent="#accordionCamposQuadro11">
                                <div class="accordion-body">
                                    <p><strong>Aplicação:</strong> Imposto recolhido por mercadoria e por operação.</p>
                                    <p><strong>Base legal:</strong> RICMS/SC-01, Anexo 3, arts. 21 e 22.</p>
                                    <p><strong>Origem EFD:</strong> Campo DEB_ESP_ST do registro E210.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Regras de Apuração -->
                <div class="mb-4">
                    <h6 class="text-success mb-3">
                        <i class="bi bi-calculator me-2"></i>Regras de Apuração
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white">
                                    <h6 class="mb-0">Saldo Devedor (170)</h6>
                                </div>
                                <div class="card-body">
                                    <p class="small mb-1">Quando: <strong>Total Débitos > Total Créditos</strong></p>
                                    <p class="small mb-1"><strong>Fórmula:</strong></p>
                                    <code class="small">170 = 080 - (130 + 160)</code>
                                    <p class="small mb-1"><strong>Imposto a Recolher:</strong></p>
                                    <code class="small">999 = 170 - 180</code>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">Saldo Credor (190)</h6>
                                </div>
                                <div class="card-body">
                                    <p class="small mb-1">Quando: <strong>Total Créditos > Total Débitos</strong></p>
                                    <p class="small mb-1"><strong>Fórmula:</strong></p>
                                    <code class="small">190 = (130 + 160) - 080</code>
                                    <p class="small mb-1"><strong>Saldo Mês Seguinte:</strong></p>
                                    <code class="small">998 = 190 - 200</code>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quando Preencher o Quadro -->
                <div class="mb-4">
                    <h6 class="text-info mb-3">
                        <i class="bi bi-check-circle me-2"></i>Quando Preencher o Quadro 11
                    </h6>
                    <div class="alert alert-info">
                        <strong>O Quadro 11 deve ser preenchido por:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Contribuintes que comercializam produtos sujeitos à ST</li>
                            <li>Estabelecimentos que atuam como substituto tributário</li>
                            <li>Empresas que emitem NF-e com retenção de ST</li>
                            <li>Contribuintes que recebem mercadorias sem retenção devida</li>
                            <li>Estabelecimentos com regime especial de ST</li>
                        </ul>
                    </div>
                </div>

                <!-- Equivalência com EFD -->
                <div class="mb-4">
                    <h6 class="text-secondary mb-3">
                        <i class="bi bi-link-45deg me-2"></i>Equivalência com Registro E210 da EFD
                    </h6>
                    <p class="small">O Quadro 11 possui equivalência direta com campos do registro E210 da Escrituração Fiscal Digital:</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Campo Quadro 11</th>
                                    <th>Campo Registro E210</th>
                                    <th>Códigos Ajuste</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>070</td>
                                    <td>VL_RETENÇAO_ST</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td>073</td>
                                    <td>VL_AJ_DEBITOS_ST</td>
                                    <td>SC41000002, SC41000003, SC41000004, SC41000099</td>
                                </tr>
                                <tr>
                                    <td>090</td>
                                    <td>VL_SLD_CRED_ANT_ST</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td>105</td>
                                    <td>VL_OUT_CRED_ST</td>
                                    <td>SC12, SC13, SC14, SC11, SC21, SC61</td>
                                </tr>
                                <tr>
                                    <td>115</td>
                                    <td>VL_RESSARC_ST</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td>116</td>
                                    <td>VL_DEVOL_ST</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td>998</td>
                                    <td>VL_SLD_CRED_ST_TRANSPORTAR</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td>999</td>
                                    <td>VL_ICMS_RECOL_ST</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td>899</td>
                                    <td>DEB_ESP_ST</td>
                                    <td>SC15, SC71</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Regras Importantes -->
                <div class="alert alert-warning">
                    <h6 class="alert-heading mb-2">
                        <i class="bi bi-exclamation-triangle me-2"></i>Regras Importantes
                    </h6>
                    <ul class="mb-0 small">
                        <li><strong>Classe 19992:</strong> Débitos especiais informados no Quadro 12</li>
                        <li><strong>Atualização:</strong> Valores devem estar atualizados quando aplicável</li>
                        <li><strong>DCIP:</strong> Muitos créditos passaram para o Demonstrativo de Créditos</li>
                        <li><strong>EFD:</strong> Valores devem corresponder ao registro E210</li>
                        <li><strong>Transporte:</strong> Saldos credores são transferidos automaticamente</li>
                        <li><strong>Regime especial:</strong> Regras específicas para diferentes regimes</li>
                        <li><strong>Consolidação:</strong> Tratamento especial para empresas consolidadas</li>
                        <li><strong>Período:</strong> Vários campos deixaram de ser utilizados ao longo do tempo</li>
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

<script>
// Executar imediatamente para conteúdo carregado via AJAX

// Definir dime_id globalmente para este quadro
window.QUADRO11_CONFIG = <?php echo json_encode(['dimeId' => $dime_id]); ?>;

// Inicializar tooltips
const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Máscaras para valores monetários
setupMasksQuadro11();

// Definir função global para ser acessível do editar_dime.php
window.calcularTudoQuadro11 = function() {

    // Calcular total de débitos
    calcularTotalDebitosQuadro11();

    // Calcular total de créditos
    calcularTotalCreditosQuadro11();

    // Calcular total de ajustes
    calcularTotalAjustesQuadro11();

    // Calcular resultado final
    calcularResultadoFinalQuadro11();

};

// Calcular totais iniciais
calcularTudoQuadro11();

function setupMasksQuadro11() {
    // Máscaras para valores monetários
    const camposMonetarios = [
        // Débitos
        'item_010_debito_importacao', 'item_020_debito_entrada_uf',
        'item_030_debito_responsabilidade', 'item_040_debito_fato_gerador',
        'item_070_total_imposto_retido', 'item_073_imposto_st_entrada_regime_especial',
        'item_074_outros_debitos', 'item_075_saldos_devedores_consolidados',
        'item_080_total_debitos', 'item_899_debitos_especiais_st',
        // Créditos
        'item_090_saldo_credor_anterior_st', 'item_105_creditos_dcip',
        'item_115_ressarcimento_icms_st_nfe', 'item_116_devolucao_mercadorias_desfazimento_atual',
        'item_125_saldos_credores_consolidados', 'item_130_total_creditos',
        // Ajustes
        'item_155_antecipacoes_combustiveis', 'item_160_total_ajustes',
        // Resultados
        'item_170_saldo_devedor', 'item_180_saldo_devedor_transferido',
        'item_190_saldo_credor', 'item_200_saldo_credor_transferido',
        'item_998_saldo_credor_mes_seguinte', 'item_999_imposto_recolher_st'
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
                if (value.length > 14) {
                    value = value.slice(0, -14) + '.' + value.slice(-14);
                }
                e.target.value = value;

                // Recalcular tudo quando houver mudanças
                setTimeout(calcularTudoQuadro11, 100);
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

// Definir função global para ser acessível do editar_dime.php
window.calcularTudoQuadro11 = function() {

    // Calcular total de débitos
    calcularTotalDebitosQuadro11();

    // Calcular total de créditos
    calcularTotalCreditosQuadro11();

    // Calcular total de ajustes
    calcularTotalAjustesQuadro11();

    // Calcular resultado final
    calcularResultadoFinalQuadro11();

};

function calcularTotalDebitosQuadro11() {
    const campos = [
        'item_070_total_imposto_retido', 'item_073_imposto_st_entrada_regime_especial',
        'item_074_outros_debitos', 'item_075_saldos_devedores_consolidados'
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

    const campoTotal = document.getElementById('item_080_total_debitos');
    if (campoTotal) {
        campoTotal.value = total.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
}

function calcularTotalCreditosQuadro11() {
    const campos = [
        'item_090_saldo_credor_anterior_st', 'item_105_creditos_dcip',
        'item_115_ressarcimento_icms_st_nfe', 'item_116_devolucao_mercadorias_desfazimento_atual',
        'item_125_saldos_credores_consolidados'
    ];


    let total = 0;
    campos.forEach(campoId => {
        const campo = document.getElementById(campoId);
        if (campo && campo.value) {
            let valorStr = campo.value.replace(/\./g, '').replace(',', '.');
            const valor = parseFloat(valorStr) || 0;
            total += valor;
        } else {
        }
    });


    const campoTotal = document.getElementById('item_130_total_creditos');
    if (campoTotal) {
        const valorFormatado = total.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        campoTotal.value = valorFormatado;
    } else {
    }
}

function calcularTotalAjustesQuadro11() {
    const campos = [
        'item_155_antecipacoes_combustiveis'
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

    const campoTotal = document.getElementById('item_160_total_ajustes');
    if (campoTotal) {
        campoTotal.value = total.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
}

function calcularResultadoFinalQuadro11() {
    // Obter valores totais
    const totalDebitos = getNumericValue('item_080_total_debitos');
    const totalCreditos = getNumericValue('item_130_total_creditos');
    const totalAjustes = getNumericValue('item_160_total_ajustes');

    const creditosMaisAjustes = totalCreditos + totalAjustes;

    if (totalDebitos > creditosMaisAjustes) {
        // Saldo devedor
        const saldoDevedor = totalDebitos - creditosMaisAjustes;
        document.getElementById('item_170_saldo_devedor').value = formatMoney(saldoDevedor);
        document.getElementById('item_190_saldo_credor').value = '0,00';
        document.getElementById('item_998_saldo_credor_mes_seguinte').value = '0,00';

        // Imposto a recolher = saldo devedor - transferido
        const transferido = getNumericValue('item_180_saldo_devedor_transferido');
        const impostoRecolher = Math.max(0, saldoDevedor - transferido);
        document.getElementById('item_999_imposto_recolher_st').value = formatMoney(impostoRecolher);

    } else {
        // Saldo credor
        const saldoCredor = creditosMaisAjustes - totalDebitos;
        document.getElementById('item_190_saldo_credor').value = formatMoney(saldoCredor);
        document.getElementById('item_170_saldo_devedor').value = '0,00';
        document.getElementById('item_999_imposto_recolher_st').value = '0,00';

        // Saldo credor para mês seguinte = saldo credor - transferido
        const transferido = getNumericValue('item_200_saldo_credor_transferido');
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
window.salvarQuadro11 = function() {

    // Validação de campos obrigatórios
    if (!validarCamposObrigatoriosQuadro11()) {
        return;
    }

    // Combinar dados de todas as abas
    const formData = new FormData();

    // Dados das diferentes abas
    const forms = [
        'form-quadro11-basicos',
        'form-quadro11-debitos',
        'form-quadro11-creditos',
        'form-quadro11-apuracao'
    ];

    forms.forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            new FormData(form).forEach((value, key) => {
                if (!formData.has(key)) { // Evitar duplicatas
                    formData.append(key, value);
                }
            });
        } else {
        }
    });

    // Mostrar loading
    const btn = document.querySelector('button[onclick="salvarQuadro11()"]');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Salvando...';
    }

    // URL para salvar
    const postUrl = '/quadros/ajax_save_11.php';

    // Adicionar dime_id
    const dimeId = window.QUADRO11_CONFIG?.dimeId || window.DIME_CONFIG?.dimeId || new URLSearchParams(window.location.search).get('dime_id') || new URLSearchParams(window.parent.location.search).get('id');
    formData.append('dime_id', dimeId);
    formData.append('action', 'save_quadro11');

    for (let [key, value] of formData.entries()) {
    }

    fetch(postUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        for (let [key, value] of response.headers.entries()) {
        }

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
        showMessage('💥 Erro de conexão: ' + error.message, 'danger');
    })
    .finally(() => {
        // Restaurar botão
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-save me-2"></i>Salvar Quadro 11';
        }
    });
};

function validarCamposObrigatoriosQuadro11() {
    let camposFaltando = [];
    let temAtividade = false;

    // Verificar se há alguma atividade no quadro
    const camposAtividade = [
        'item_070_total_imposto_retido', 'item_073_imposto_st_entrada_regime_especial',
        'item_074_outros_debitos', 'item_075_saldos_devedores_consolidados',
        'item_090_saldo_credor_anterior_st', 'item_105_creditos_dcip',
        'item_115_ressarcimento_icms_st_nfe', 'item_116_devolucao_mercadorias_desfazimento_atual',
        'item_125_saldos_credores_consolidados', 'item_899_debitos_especiais_st'
    ];

    camposAtividade.forEach(campoId => {
        const campo = document.getElementById(campoId);
        if (campo && campo.value && campo.value !== '0,00' && campo.value !== '') {
            temAtividade = true;
        }
    });

    // Se há atividade, validar campos obrigatórios
    if (temAtividade) {
        // Totais sempre devem estar calculados
        const totalDebitos = document.getElementById('item_080_total_debitos');
        const totalCreditos = document.getElementById('item_130_total_creditos');

        if (!totalDebitos || !totalDebitos.value || totalDebitos.value === '0,00' || totalDebitos.value === '') {
            camposFaltando.push('Total de Débitos (080)');
            if (totalDebitos) totalDebitos.classList.add('is-invalid');
        } else {
            if (totalDebitos) totalDebitos.classList.remove('is-invalid');
        }

        if (!totalCreditos || !totalCreditos.value || totalCreditos.value === '0,00' || totalCreditos.value === '') {
            camposFaltando.push('Total de Créditos (130)');
            if (totalCreditos) totalCreditos.classList.add('is-invalid');
        } else {
            if (totalCreditos) totalCreditos.classList.remove('is-invalid');
        }

        // Validação condicional do imposto a recolher baseada nos saldos
        const saldoDevedor = document.getElementById('item_170_saldo_devedor');
        const saldoCredor = document.getElementById('item_190_saldo_credor');
        const impostoRecolher = document.getElementById('item_999_imposto_recolher_st');

        // Verificar se os saldos foram calculados
        const saldoDevedorValor = saldoDevedor && saldoDevedor.value ? parseFloat(saldoDevedor.value.replace(/\./g, '').replace(',', '.')) : 0;
        const saldoCredorValor = saldoCredor && saldoCredor.value ? parseFloat(saldoCredor.value.replace(/\./g, '').replace(',', '.')) : 0;
        const impostoRecolherValor = impostoRecolher && impostoRecolher.value ? parseFloat(impostoRecolher.value.replace(/\./g, '').replace(',', '.')) : 0;

        if (saldoDevedorValor > 0) {
            // Se há saldo devedor, deve haver imposto a recolher
            if (!impostoRecolher || impostoRecolherValor <= 0) {
                camposFaltando.push('Imposto a Recolher (999) - obrigatório quando há saldo devedor');
                if (impostoRecolher) impostoRecolher.classList.add('is-invalid');
            } else {
                if (impostoRecolher) impostoRecolher.classList.remove('is-invalid');
            }
        } else if (saldoCredorValor > 0) {
            // Se há saldo credor, o imposto deve ser zero
            if (!impostoRecolher || impostoRecolherValor !== 0) {
                camposFaltando.push('Imposto a Recolher (999) - deve ser zero quando há saldo credor');
                if (impostoRecolher) impostoRecolher.classList.add('is-invalid');
            } else {
                if (impostoRecolher) impostoRecolher.classList.remove('is-invalid');
            }
        } else {
            // Se não há saldos, verificar se o imposto está zerado
            if (impostoRecolher && impostoRecolherValor !== 0) {
                camposFaltando.push('Imposto a Recolher (999) - deve ser zero quando não há saldos');
                if (impostoRecolher) impostoRecolher.classList.add('is-invalid');
            } else {
                if (impostoRecolher) impostoRecolher.classList.remove('is-invalid');
            }
        }
    }

    if (camposFaltando.length > 0) {
        const mensagem = `⚠️ Campos obrigatórios não preenchidos:\n\n${camposFaltando.join('\n')}\n\nCalcule os totais automaticamente clicando em "Calcular Tudo" antes de salvar.`;
        showMessage(mensagem, 'warning');
        return false;
    }

    return true;
}

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
