<?php
/**
 * DIME-SC - Quadro 04/05 - Débitos e Créditos
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

        if ($action === 'save_quadro04') {
            // Salvar dados do Quadro 04 (Débitos) conforme Manual DIME-SEF 153/12
            $campos = [
                'dime_declaracao_id' => $dime_id,
                // Débitos Gerais
                'item_010_debito_saidas' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_010_debito_saidas'] ?? '0')),
                'item_020_debito_diferencial_ativo_permanente' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_020_debito_diferencial_ativo_permanente'] ?? '0')),
                'item_030_debito_diferencial_material_consumo' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_030_debito_diferencial_material_consumo'] ?? '0')),
                'item_040_debito_maquinas_equipamentos_importados' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_040_debito_maquinas_equipamentos_importados'] ?? '0')),
                'item_045_debito_diferenca_aliquota_operacao_consumidor' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_045_debito_diferenca_aliquota_operacao_consumidor'] ?? '0')),
                // Estornos
                'item_050_estorno_credito' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_050_estorno_credito'] ?? '0')),
                'item_060_outros_estornos_credito' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_060_outros_estornos_credito'] ?? '0')),
                'item_065_estorno_credito_entrada_credito_presumido' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_065_estorno_credito_entrada_credito_presumido'] ?? '0')),
                // Outros débitos
                'item_070_outros_debitos' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_070_outros_debitos'] ?? '0')),
                // Subtotal
                'item_990_subtotal_debitos' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_990_subtotal_debitos'] ?? '0')),
                'observacoes' => trim($_POST['observacoes_debitos'] ?? '')
            ];

            // Verificar se já existe registro para este dime_id
            $stmt = $pdo->prepare("SELECT id FROM dime_quadro_04 WHERE dime_declaracao_id = ?");
            $stmt->execute([$dime_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update
                $sql = "UPDATE dime_quadro_04 SET ";
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
                $sql = "INSERT INTO dime_quadro_04 (" . implode(', ', array_keys($campos)) . ") VALUES (" . str_repeat('?, ', count($campos) - 1) . "?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($campos));
            }

            echo json_encode([
                'success' => true,
                'message' => 'Quadro 04 (Débitos) salvo com sucesso!',
                'data' => $campos
            ]);
            exit;
        }

        if ($action === 'save_quadro05') {
            // Salvar dados do Quadro 05 (Créditos) - apenas campos utilizados conforme manual atualizado
            $campos = [
                'dime_declaracao_id' => $dime_id,
                // Créditos conforme Manual DIME-SEF 153/12
                'item_010_saldo_credor_anterior' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_010_saldo_credor_anterior'] ?? '0')),
                'item_020_credito_entradas' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_020_credito_entradas'] ?? '0')),
                'item_030_credito_ativo_permanente' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_030_credito_ativo_permanente'] ?? '0')),
                'item_040_credito_diferencial_aliquota' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_040_credito_diferencial_aliquota'] ?? '0')),
                'item_045_credito_diferenca_aliquota_operacao' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_045_credito_diferenca_aliquota_operacao'] ?? '0')),
                'item_050_credito_icms_retido_st' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_050_credito_icms_retido_st'] ?? '0')),
                // Subtotal
                'item_990_subtotal_creditos' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_990_subtotal_creditos'] ?? '0')),
                'observacoes' => trim($_POST['observacoes_creditos'] ?? '')
            ];

            // Verificar se já existe registro para este dime_id
            $stmt = $pdo->prepare("SELECT id FROM dime_quadro_05 WHERE dime_declaracao_id = ?");
            $stmt->execute([$dime_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update
                $sql = "UPDATE dime_quadro_05 SET ";
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
                $sql = "INSERT INTO dime_quadro_05 (" . implode(', ', array_keys($campos)) . ") VALUES (" . str_repeat('?, ', count($campos) - 1) . "?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($campos));
            }

            echo json_encode([
                'success' => true,
                'message' => 'Quadro 05 (Créditos) salvo com sucesso!',
                'data' => $campos
            ]);
            exit;
        }
    }

    // Carregar dados existentes do Quadro 04
    $stmt = $pdo->prepare("SELECT * FROM dime_quadro_04 WHERE dime_declaracao_id = ?");
    $stmt->execute([$dime_id]);
    $dados_quadro04 = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Calcular automaticamente o item 010 (Débito pelas saídas) - soma do valor_imposto das CFOPs de saída
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(dq2.valor_imposto), 0) as total_imposto_debitado_saidas,
               COUNT(*) as total_registros_encontrados
        FROM dime_quadro_02 dq2
        INNER JOIN t_cfops cf ON dq2.cfop_id = cf.id
        WHERE dq2.dime_declaracao_id = ? AND (cf.tipo = 'saida' OR cf.tipo = 'S')
    ");
    $stmt->execute([$dime_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_imposto_saidas = $result['total_imposto_debitado_saidas'];
    $total_registros = $result['total_registros_encontrados'];

    // Se não há dados no Quadro 04, inicializar com o valor calculado
    if (empty($dados_quadro04)) {
        $dados_quadro04 = [
            'item_010_debito_saidas' => $total_imposto_saidas,
            'item_020_debito_diferencial_ativo_permanente' => 0.00,
            'item_030_debito_diferencial_material_consumo' => 0.00,
            'item_040_debito_maquinas_equipamentos_importados' => 0.00,
            'item_045_debito_diferenca_aliquota_operacao_consumidor' => 0.00,
            'item_050_estorno_credito' => 0.00,
            'item_060_outros_estornos_credito' => 0.00,
            'item_065_estorno_credito_entrada_credito_presumido' => 0.00,
            'item_070_outros_debitos' => 0.00,
            'item_990_subtotal_debitos' => $total_imposto_saidas,
            'observacoes' => ''
        ];
    } else {
        // Se já existe dados, verificar se o item 010 precisa ser atualizado
        // (apenas se estiver vazio ou for diferente do calculado)
        if (empty($dados_quadro04['item_010_debito_saidas']) || $dados_quadro04['item_010_debito_saidas'] == 0) {
            $dados_quadro04['item_010_debito_saidas'] = $total_imposto_saidas;
            // Recalcular o subtotal
            $subtotal = $total_imposto_saidas +
                       ($dados_quadro04['item_020_debito_diferencial_ativo_permanente'] ?? 0) +
                       ($dados_quadro04['item_030_debito_diferencial_material_consumo'] ?? 0) +
                       ($dados_quadro04['item_040_debito_maquinas_equipamentos_importados'] ?? 0) +
                       ($dados_quadro04['item_045_debito_diferenca_aliquota_operacao_consumidor'] ?? 0) +
                       ($dados_quadro04['item_050_estorno_credito'] ?? 0) +
                       ($dados_quadro04['item_060_outros_estornos_credito'] ?? 0) +
                       ($dados_quadro04['item_065_estorno_credito_entrada_credito_presumido'] ?? 0) +
                       ($dados_quadro04['item_070_outros_debitos'] ?? 0);
            $dados_quadro04['item_990_subtotal_debitos'] = $subtotal;
        }
    }

    // Carregar dados existentes do Quadro 05
    $stmt = $pdo->prepare("SELECT * FROM dime_quadro_05 WHERE dime_declaracao_id = ?");
    $stmt->execute([$dime_id]);
    $dados_quadro05 = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Calcular automaticamente o item 020 (Crédito pelas entradas) - soma do valor_imposto das CFOPs de entrada
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(dq1.valor_imposto), 0) as total_imposto_creditado_entradas,
               COUNT(*) as total_registros_encontrados
        FROM dime_quadro_01 dq1
        INNER JOIN t_cfops cf ON dq1.cfop_id = cf.id
        WHERE dq1.dime_declaracao_id = ? AND (cf.tipo = 'entrada' OR cf.tipo = 'E')
    ");
    $stmt->execute([$dime_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_imposto_entradas = $result['total_imposto_creditado_entradas'];
    $total_registros_entradas = $result['total_registros_encontrados'];

    // Se não há dados no Quadro 05, inicializar com o valor calculado
    if (empty($dados_quadro05)) {
        $dados_quadro05 = [
            'item_010_saldo_credor_anterior' => 0.00,
            'item_020_credito_entradas' => $total_imposto_entradas,
            'item_030_credito_ativo_permanente' => 0.00,
            'item_040_credito_diferencial_aliquota' => 0.00,
            'item_045_credito_diferenca_aliquota_operacao' => 0.00,
            'item_050_credito_icms_retido_st' => 0.00,
            'item_990_subtotal_creditos' => $total_imposto_entradas,
            'observacoes' => ''
        ];
    } else {
        // Se já existe dados, verificar se o item 020 precisa ser atualizado
        // (apenas se estiver vazio ou for diferente do calculado)
        if (empty($dados_quadro05['item_020_credito_entradas']) || $dados_quadro05['item_020_credito_entradas'] == 0) {
            $dados_quadro05['item_020_credito_entradas'] = $total_imposto_entradas;
            // Recalcular o subtotal
            $subtotal_creditos = ($dados_quadro05['item_010_saldo_credor_anterior'] ?? 0) +
                                $total_imposto_entradas +
                                ($dados_quadro05['item_030_credito_ativo_permanente'] ?? 0) +
                                ($dados_quadro05['item_040_credito_diferencial_aliquota'] ?? 0) +
                                ($dados_quadro05['item_045_credito_diferenca_aliquota_operacao'] ?? 0) +
                                ($dados_quadro05['item_050_credito_icms_retido_st'] ?? 0);
            $dados_quadro05['item_990_subtotal_creditos'] = $subtotal_creditos;
        }
    }

} catch (Exception $e) {
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
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        <i class="bi bi-calculator text-primary me-2"></i>
        Quadros 04/05 - Débitos e Créditos
    </h5>
    <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalAjudaQuadro0405">
        <i class="bi bi-question-circle me-1"></i>Ajuda
    </button>
</div>

<?php if (isset($error_message)): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <?php echo $error_message; ?>
</div>
<?php endif; ?>

<div class="row">
    <!-- Quadro 04 - Débitos -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-danger text-white">
                <h6 class="card-title mb-0">
                    <i class="bi bi-dash-circle me-2"></i>
                    Quadro 04 - Débitos
                </h6>
            </div>
            <div class="card-body">
                <form id="form-quadro04">
                    <input type="hidden" name="action" value="save_quadro04">

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold text-danger">04 - RESUMO DA APURAÇÃO DOS DÉBITOS</label>
                        </div>

                        <!-- Débitos Gerais -->
                        <div class="col-12">
                            <label class="form-label fw-bold">Débitos Gerais</label>
                            <hr class="my-2">
                        </div>

                        <div class="col-md-6">
                            <label for="item_010_debito_saidas" class="form-label">
                                <strong>010 - Débito pelas saídas</strong>
                                <small class="text-muted d-block">Valor calculado automaticamente do Quadro 02 (saídas)</small>
                            </label>
                            <input type="text" class="form-control debito-field" id="item_010_debito_saidas"
                                   name="item_010_debito_saidas"
                                   value="<?php echo format_money_br($dados_quadro04['item_010_debito_saidas'] ?? 0); ?>"
                                   placeholder="0,00" readonly>
                        </div>

                        <div class="col-md-6">
                            <label for="item_020_debito_diferencial_ativo_permanente" class="form-label">
                                <strong>020 - Débito por diferencial de alíquota de ativo permanente</strong>
                            </label>
                            <input type="text" class="form-control debito-field" id="item_020_debito_diferencial_ativo_permanente"
                                   name="item_020_debito_diferencial_ativo_permanente"
                                   value="<?php echo safe_money_value($dados_quadro04, 'item_020_debito_diferencial_ativo_permanente'); ?>"
                                   placeholder="0,00">
                        </div>

                        <div class="col-md-6">
                            <label for="item_030_debito_diferencial_material_consumo" class="form-label">
                                <strong>030 - Débito por diferencial de alíquota de material de uso ou consumo</strong>
                            </label>
                            <input type="text" class="form-control debito-field" id="item_030_debito_diferencial_material_consumo"
                                   name="item_030_debito_diferencial_material_consumo"
                                   value="<?php echo safe_money_value($dados_quadro04, 'item_030_debito_diferencial_material_consumo'); ?>"
                                   placeholder="0,00">
                        </div>

                        <div class="col-md-6">
                            <label for="item_040_debito_maquinas_equipamentos_importados" class="form-label">
                                <strong>040 - Débito de máquinas/equipamentos importados para ativo permanente</strong>
                            </label>
                            <input type="text" class="form-control debito-field" id="item_040_debito_maquinas_equipamentos_importados"
                                   name="item_040_debito_maquinas_equipamentos_importados"
                                   value="<?php echo safe_money_value($dados_quadro04, 'item_040_debito_maquinas_equipamentos_importados'); ?>"
                                   placeholder="0,00">
                        </div>

                        <div class="col-md-6">
                            <label for="item_045_debito_diferenca_aliquota_operacao_consumidor" class="form-label">
                                <strong>045 - Débito da Diferença de Alíquota de Operação ou Prestação a Consumidor Final de Outro Estado</strong>
                                <small class="text-muted d-block">Não preencher a partir de março/2023</small>
                            </label>
                            <input type="text" class="form-control debito-field" id="item_045_debito_diferenca_aliquota_operacao_consumidor"
                                   name="item_045_debito_diferenca_aliquota_operacao_consumidor"
                                   value="<?php echo safe_money_value($dados_quadro04, 'item_045_debito_diferenca_aliquota_operacao_consumidor'); ?>"
                                   placeholder="0,00">
                        </div>

                        <!-- Estornos de Crédito -->
                        <div class="col-12">
                            <label class="form-label fw-bold">Estornos de Crédito</label>
                            <hr class="my-2">
                        </div>

                        <div class="col-md-6">
                            <label for="item_050_estorno_credito" class="form-label">
                                <strong>050 - Estorno de crédito</strong>
                            </label>
                            <input type="text" class="form-control debito-field" id="item_050_estorno_credito"
                                   name="item_050_estorno_credito"
                                   value="<?php echo safe_money_value($dados_quadro04, 'item_050_estorno_credito'); ?>"
                                   placeholder="0,00">
                        </div>

                        <div class="col-md-6">
                            <label for="item_060_outros_estornos_credito" class="form-label">
                                <strong>060 - Outros estornos de crédito</strong>
                            </label>
                            <input type="text" class="form-control debito-field" id="item_060_outros_estornos_credito"
                                   name="item_060_outros_estornos_credito"
                                   value="<?php echo safe_money_value($dados_quadro04, 'item_060_outros_estornos_credito'); ?>"
                                   placeholder="0,00">
                        </div>

                        <div class="col-md-6">
                            <label for="item_065_estorno_credito_entrada_credito_presumido" class="form-label">
                                <strong>065 - Estorno de Crédito da Entrada em Decorrência da Utilização de Crédito Presumido</strong>
                            </label>
                            <input type="text" class="form-control debito-field" id="item_065_estorno_credito_entrada_credito_presumido"
                                   name="item_065_estorno_credito_entrada_credito_presumido"
                                   value="<?php echo safe_money_value($dados_quadro04, 'item_065_estorno_credito_entrada_credito_presumido'); ?>"
                                   placeholder="0,00">
                        </div>

                        <!-- Outros Débitos -->
                        <div class="col-12">
                            <label class="form-label fw-bold">Outros Débitos</label>
                            <hr class="my-2">
                        </div>

                        <div class="col-md-6">
                            <label for="item_070_outros_debitos" class="form-label">
                                <strong>070 - Outros débitos</strong>
                            </label>
                            <input type="text" class="form-control debito-field" id="item_070_outros_debitos"
                                   name="item_070_outros_debitos"
                                   value="<?php echo safe_money_value($dados_quadro04, 'item_070_outros_debitos'); ?>"
                                   placeholder="0,00">
                        </div>

                        <!-- Subtotal -->
                        <div class="col-md-6">
                            <label for="item_990_subtotal_debitos" class="form-label fw-bold">
                                <strong>990 - SUBTOTAL DE DÉBITOS</strong>
                                <small class="text-muted ms-1">(calculado automaticamente)</small>
                            </label>
                            <input type="text" class="form-control fw-bold bg-light" id="item_990_subtotal_debitos"
                                   name="item_990_subtotal_debitos"
                                   value="<?php echo safe_money_value($dados_quadro04, 'item_990_subtotal_debitos'); ?>"
                                   placeholder="0,00" readonly>
                        </div>

                        <div class="col-12">
                            <label for="observacoes_debitos" class="form-label">
                                <strong>Observações</strong>
                            </label>
                            <textarea class="form-control" id="observacoes_debitos" name="observacoes_debitos" rows="3"
                                      placeholder="Observações adicionais (opcional)"><?php echo safe_value($dados_quadro04, 'observacoes'); ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-save me-2"></i>
                            Salvar Quadro 04
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Quadro 05 - Créditos -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <h6 class="card-title mb-0">
                    <i class="bi bi-plus-circle me-2"></i>
                    Quadro 05 - Créditos
                </h6>
            </div>
            <div class="card-body">
                <form id="form-quadro05">
                    <input type="hidden" name="action" value="save_quadro05">

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">Créditos Disponíveis</label>
                        </div>

                        <div class="col-md-6">
                            <label for="item_010_saldo_credor_anterior" class="form-label">
                                <strong>010 - Saldo credor anterior</strong>
                            </label>
                            <input type="text" class="form-control" id="item_010_saldo_credor_anterior"
                                   name="item_010_saldo_credor_anterior"
                                   value="<?php echo safe_money_value($dados_quadro05, 'item_010_saldo_credor_anterior'); ?>"
                                   placeholder="0,00">
                        </div>

                        <div class="col-md-6">
                            <label for="item_020_credito_entradas" class="form-label">
                                <strong>020 - Crédito pelas entradas</strong>
                                <small class="text-muted d-block">Valor calculado automaticamente do Quadro 01 (entradas)</small>
                            </label>
                            <input type="text" class="form-control" id="item_020_credito_entradas"
                                   name="item_020_credito_entradas"
                                   value="<?php echo safe_money_value($dados_quadro05, 'item_020_credito_entradas'); ?>"
                                   placeholder="0,00" readonly>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Créditos Específicos</label>
                        </div>

                        <div class="col-md-6">
                            <label for="item_030_credito_ativo_permanente" class="form-label">
                                <strong>030 - Crédito ativo permanente</strong>
                            </label>
                            <input type="text" class="form-control" id="item_030_credito_ativo_permanente"
                                   name="item_030_credito_ativo_permanente"
                                   value="<?php echo safe_money_value($dados_quadro05, 'item_030_credito_ativo_permanente'); ?>"
                                   placeholder="0,00">
                        </div>

                        <div class="col-md-6">
                            <label for="item_040_credito_diferencial_aliquota" class="form-label">
                                <strong>040 - Crédito diferencial de alíquota</strong>
                            </label>
                            <input type="text" class="form-control" id="item_040_credito_diferencial_aliquota"
                                   name="item_040_credito_diferencial_aliquota"
                                   value="<?php echo safe_money_value($dados_quadro05, 'item_040_credito_diferencial_aliquota'); ?>"
                                   placeholder="0,00">
                        </div>

                        <div class="col-md-6">
                            <label for="item_045_credito_diferenca_aliquota_operacao" class="form-label">
                                <strong>045 - Diferença de alíquota em operação</strong>
                            </label>
                            <input type="text" class="form-control" id="item_045_credito_diferenca_aliquota_operacao"
                                   name="item_045_credito_diferenca_aliquota_operacao"
                                   value="<?php echo safe_money_value($dados_quadro05, 'item_045_credito_diferenca_aliquota_operacao'); ?>"
                                   placeholder="0,00">
                        </div>

                        <div class="col-md-6">
                            <label for="item_050_credito_icms_retido_st" class="form-label">
                                <strong>050 - ICMS retido por ST</strong>
                            </label>
                            <input type="text" class="form-control" id="item_050_credito_icms_retido_st"
                                   name="item_050_credito_icms_retido_st"
                                   value="<?php echo safe_money_value($dados_quadro05, 'item_050_credito_icms_retido_st'); ?>"
                                   placeholder="0,00">
                        </div>

                        <div class="col-md-6">
                            <label for="item_990_subtotal_creditos" class="form-label fw-bold">
                                <strong>990 - SUBTOTAL DE CRÉDITOS</strong>
                                <small class="text-muted ms-1">(calculado automaticamente)</small>
                            </label>
                            <input type="text" class="form-control fw-bold bg-light" id="item_990_subtotal_creditos"
                                   name="item_990_subtotal_creditos"
                                   value="<?php echo safe_money_value($dados_quadro05, 'item_990_subtotal_creditos'); ?>"
                                   placeholder="0,00" readonly>
                        </div>

                        <div class="col-12">
                            <label for="observacoes_creditos" class="form-label">
                                <strong>Observações</strong>
                            </label>
                            <textarea class="form-control" id="observacoes_creditos" name="observacoes_creditos" rows="3"
                                      placeholder="Observações adicionais (opcional)"><?php echo safe_value($dados_quadro05, 'observacoes'); ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-save me-2"></i>
                            Salvar Quadro 05
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Ajuda do Quadro 04/05 -->
<div class="modal fade" id="modalAjudaQuadro0405" tabindex="-1" aria-labelledby="modalAjudaQuadro0405Label" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalAjudaQuadro0405Label">
                    <i class="bi bi-question-circle me-2"></i>
                    Ajuda - Quadros 04/05: Débitos e Créditos
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">

                <!-- Visão Geral -->
                <div class="mb-4">
                    <h6 class="text-primary mb-3">
                        <i class="bi bi-info-circle me-2"></i>Visão Geral
                    </h6>
                    <p>Os Quadros 04 e 05 realizam a apuração dos débitos e créditos do ICMS para o período. Estes quadros são fundamentais para determinar o saldo final do imposto a pagar ou a compensar.</p>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="alert alert-danger">
                                <strong>📊 Quadro 04 - Débitos:</strong> Registra todos os valores que o estabelecimento deve recolher de ICMS.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-success">
                                <strong>💰 Quadro 05 - Créditos:</strong> Registra todos os valores de ICMS que o estabelecimento pode utilizar como crédito.
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small">Os subtotais desses quadros são transportados para o Quadro 09 (Cálculo do Imposto).</p>
                </div>

                <!-- Quadro 04 - Débitos -->
                <div class="mb-4">
                    <h6 class="text-danger mb-3">
                        <i class="bi bi-dash-circle me-2"></i>Quadro 04 - Resumo da Apuração dos Débitos
                    </h6>
                    <p>Registra todos os débitos do ICMS incorridos no período. Estes valores representam obrigações tributárias a serem pagas.</p>

                    <!-- Débitos Gerais -->
                    <div class="mb-4">
                        <h6 class="text-danger mb-3">Débitos Gerais</h6>
                        <div class="accordion" id="accordionDebitosGerais">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse010Debito">
                                        <strong>010 - Débito pelas saídas</strong>
                                    </button>
                                </h2>
                                <div id="collapse010Debito" class="accordion-collapse collapse show" data-bs-parent="#accordionDebitosGerais">
                                    <div class="accordion-body">
                                        <p><strong>Como preencher:</strong> Valor calculado automaticamente do Quadro 02 (saídas).</p>
                                        <p><strong>Composição:</strong> Corresponde ao total do imposto debitado em todas as operações de saída do período.</p>
                                        <p><strong>Onde buscar:</strong> Item 080 do Quadro 02 (Imposto debitado), subtraídos os valores específicos de outros quadros.</p>
                                        <div class="alert alert-info small">
                                            <strong>💡 Importante:</strong> Este é o principal componente dos débitos, representando o ICMS devido pelas vendas.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse020Debito">
                                        <strong>020 - Débito por diferencial de alíquota de ativo permanente</strong>
                                    </button>
                                </h2>
                                <div id="collapse020Debito" class="accordion-collapse collapse" data-bs-parent="#accordionDebitosGerais">
                                    <div class="accordion-body">
                                        <p><strong>Quando usar:</strong> Quando adquirir mercadorias destinadas ao ativo permanente oriundas de outra UF.</p>
                                        <p><strong>Base legal:</strong> RICMS-SC/01, art. 53, § 6º.</p>
                                        <p><strong>Cálculo:</strong> (Valor da operação × Alíquota interna) - (Valor da operação × Alíquota interestadual)</p>
                                        <p><strong>Lançamento:</strong> Apenas o valor a compensar na conta gráfica.</p>
                                        <div class="alert alert-warning small">
                                            <strong>⚠️ Atenção:</strong> Aplicável apenas a aquisições interestaduais de ativos permanentes.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse030Debito">
                                        <strong>030 - Débito por diferencial de alíquota de material de uso ou consumo</strong>
                                    </button>
                                </h2>
                                <div id="collapse030Debito" class="accordion-collapse collapse" data-bs-parent="#accordionDebitosGerais">
                                    <div class="accordion-body">
                                        <p><strong>Quando usar:</strong> Quando adquirir mercadorias destinadas ao uso ou consumo oriundas de outra UF.</p>
                                        <p><strong>Base legal:</strong> RICMS-SC/01, art. 53, § 6º.</p>
                                        <p><strong>Cálculo:</strong> Mesmo cálculo do item 020.</p>
                                        <p><strong>Diferença:</strong> Aplicável a materiais de uso/consumo, não ativos permanentes.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse040Debito">
                                        <strong>040 - Débito de máquinas/equipamentos importados para ativo permanente</strong>
                                    </button>
                                </h2>
                                <div id="collapse040Debito" class="accordion-collapse collapse" data-bs-parent="#accordionDebitosGerais">
                                    <div class="accordion-body">
                                        <p><strong>Quando usar:</strong> Quando importar máquinas e equipamentos para ativo permanente.</p>
                                        <p><strong>Base legal:</strong> RICMS-SC/01, art. 53, § 7º, I.</p>
                                        <p><strong>Informação:</strong> Valor do ICMS devido sobre importações diretas do exterior.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse045Debito">
                                        <strong>045 - Débito da Diferença de Alíquota de Operação ou Prestação a Consumidor Final</strong>
                                    </button>
                                </h2>
                                <div id="collapse045Debito" class="accordion-collapse collapse" data-bs-parent="#accordionDebitosGerais">
                                    <div class="accordion-body">
                                        <p><strong>Quando usar:</strong> Nas operações destinadas a consumidor final não contribuinte de outra UF.</p>
                                        <p><strong>Base legal:</strong> Emenda Constitucional 87/2015.</p>
                                        <p><strong>Informação:</strong> Valor apurado no item 130 do Quadro 13.</p>
                                        <div class="alert alert-danger small">
                                            <strong>❌ IMPORTANTE:</strong> Este campo não estará disponível para preenchimento a partir de março/2023.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Estornos de Crédito -->
                    <div class="mb-4">
                        <h6 class="text-warning mb-3">Estornos de Crédito</h6>
                        <div class="accordion" id="accordionEstornos">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse050Estorno">
                                        <strong>050 - Estorno de crédito</strong>
                                    </button>
                                </h2>
                                <div id="collapse050Estorno" class="accordion-collapse collapse" data-bs-parent="#accordionEstornos">
                                    <div class="accordion-body">
                                        <p><strong>Quando usar:</strong> Quando há necessidade de estornar créditos do ICMS.</p>
                                        <p><strong>Base legal:</strong> RICMS-SC/01, art. 36 e Anexo 3, art. 165, § único, II.</p>
                                        <p><strong>Exemplos:</strong></p>
                                        <ul class="small">
                                            <li>Saída de mercadoria isenta ou não tributada</li>
                                            <li>Mercadoria consumida em processo de industrialização quando a saída é isenta</li>
                                            <li>Mercadorias que perecem, deterioram-se ou extraviam-se</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse060Estorno">
                                        <strong>060 - Outros estornos de crédito</strong>
                                    </button>
                                </h2>
                                <div id="collapse060Estorno" class="accordion-collapse collapse" data-bs-parent="#accordionEstornos">
                                    <div class="accordion-body">
                                        <p><strong>Quando usar:</strong> Para estornos de créditos não previstos no item 050.</p>
                                        <p><strong>Nota:</strong> Este campo complementa o item 050 para casos não específicos.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse065Estorno">
                                        <strong>065 - Estorno de Crédito da Entrada em Decorrência da Utilização de Crédito Presumido</strong>
                                    </button>
                                </h2>
                            </div>
                        </div>
                    </div>

                    <!-- Outros Débitos -->
                    <div class="mb-4">
                        <h6 class="text-secondary mb-3">Outros Débitos</h6>
                        <div class="accordion" id="accordionOutrosDebitos">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse070Outros">
                                        <strong>070 - Outros débitos</strong>
                                    </button>
                                </h2>
                                <div id="collapse070Outros" class="accordion-collapse collapse" data-bs-parent="#accordionOutrosDebitos">
                                    <div class="accordion-body">
                                        <p><strong>Quando usar:</strong> Para débitos do ICMS não previstos nos itens anteriores.</p>
                                        <p><strong>Critério:</strong> Débitos previstos no RICMS-SC/01 que não têm campo específico.</p>
                                        <p><strong>Exclusão:</strong> Não incluir valores que já tenham campos específicos em outros quadros.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Subtotal -->
                    <div class="alert alert-danger">
                        <h6 class="mb-2">990 - Subtotal de Débitos</h6>
                        <p class="mb-0">Somatório de todos os itens do quadro 04. Este valor é transportado automaticamente para o item 010 do Quadro 09.</p>
                    </div>
                </div>

                <!-- Quadro 05 - Créditos -->
                <div class="mb-4">
                    <h6 class="text-success mb-3">
                        <i class="bi bi-plus-circle me-2"></i>Quadro 05 - Resumo da Apuração dos Créditos
                    </h6>
                    <p>Registra todos os créditos do ICMS que podem ser utilizados para compensar débitos. Estes valores representam direitos creditórios do contribuinte.</p>

                    <!-- Saldo Credor Anterior -->
                    <div class="mb-4">
                        <h6 class="text-success mb-3">Saldo Credor Anterior</h6>
                        <div class="accordion" id="accordionSaldoAnterior">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse010Credito">
                                        <strong>010 - Saldo credor do mês anterior</strong>
                                    </button>
                                </h2>
                                <div id="collapse010Credito" class="accordion-collapse collapse" data-bs-parent="#accordionSaldoAnterior">
                                    <div class="accordion-body">
                                        <p><strong>Como preencher:</strong> Valor informado no item 998 do Quadro 09 da DIME do mês anterior.</p>
                                        <p><strong>Origem:</strong> Saldo credor apurado na declaração anterior.</p>
                                        <p><strong>Importância:</strong> Este saldo pode ser utilizado para compensar débitos do período atual.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Créditos Gerais -->
                    <div class="mb-4">
                        <h6 class="text-success mb-3">Créditos Gerais</h6>
                        <div class="accordion" id="accordionCreditosGerais">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse020Credito">
                                        <strong>020 - Crédito pelas entradas</strong>
                                    </button>
                                </h2>
                                <div id="collapse020Credito" class="accordion-collapse collapse" data-bs-parent="#accordionCreditosGerais">
                                    <div class="accordion-body">
                                        <p><strong>Como preencher:</strong> Valor calculado automaticamente do Quadro 01 (entradas).</p>
                                        <p><strong>Composição:</strong> Corresponde ao total do imposto creditado em todas as operações de entrada do período.</p>
                                        <p><strong>Onde buscar:</strong> Item 030 do Quadro 01 (Imposto creditado), subtraídos os valores específicos de outros quadros.</p>
                                        <div class="alert alert-info small">
                                            <strong>💡 Importante:</strong> Este é o principal componente dos créditos, representando o ICMS pago nas compras.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse030Credito">
                                        <strong>030 - Crédito de ativo permanente</strong>
                                    </button>
                                </h2>
                                <div id="collapse030Credito" class="accordion-collapse collapse" data-bs-parent="#accordionCreditosGerais">
                                    <div class="accordion-body">
                                        <p><strong>Como preencher:</strong> Valor apurado na ficha CIAP (Controle de Crédito de ICMS do Ativo Permanente).</p>
                                        <p><strong>Base legal:</strong> RICMS-SC/01, art. 37, § 2º.</p>
                                        <div class="alert alert-danger small">
                                            <strong>❌ IMPORTANTE:</strong> A partir de março/2023, este crédito passa a ser informado via DCIP específica.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse040Credito">
                                        <strong>040 - Crédito por diferencial de alíquota material de uso/consumo</strong>
                                    </button>
                                </h2>
                                <div id="collapse040Credito" class="accordion-collapse collapse" data-bs-parent="#accordionCreditosGerais">
                                    <div class="accordion-body">
                                        <p><strong>Quando usar:</strong> Crédito de diferencial de alíquota em materiais de uso/consumo.</p>
                                        <p><strong>Base legal:</strong> RICMS-SC/01, art. 53, § 6º.</p>
                                        <div class="alert alert-danger small">
                                            <strong>❌ IMPORTANTE:</strong> A partir de março/2023, este campo não estará disponível.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse045Credito">
                                        <strong>045 - Crédito da Diferença de Alíquota de Operação ou Prestação</strong>
                                    </button>
                                </h2>
                                <div id="collapse045Credito" class="accordion-collapse collapse" data-bs-parent="#accordionCreditosGerais">
                                    <div class="accordion-body">
                                        <p><strong>Como preencher:</strong> Valor apurado no item 160 do Quadro 13.</p>
                                        <p><strong>Base legal:</strong> Emenda Constitucional 87/2015.</p>
                                        <div class="alert alert-danger small">
                                            <strong>❌ IMPORTANTE:</strong> A partir de março/2023, este campo não estará disponível.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse050Credito">
                                        <strong>050 - Crédito de ICMS retido por substituição tributária</strong>
                                    </button>
                                </h2>
                                <div id="collapse050Credito" class="accordion-collapse collapse" data-bs-parent="#accordionCreditosGerais">
                                    <div class="accordion-body">
                                        <p><strong>Quando usar:</strong> Quando a mercadoria utilizada como insumo foi sujeita à ST.</p>
                                        <p><strong>Condições:</strong> Deve estar previsto na legislação ou Anexo 3, art. 22 do RICMS-SC/01.</p>
                                        <div class="alert alert-danger small">
                                            <strong>❌ IMPORTANTE:</strong> A partir de março/2023, este crédito passa a ser informado via DCIP específica.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Campos Não Aplicáveis -->
                    <div class="mb-4">
                        <h6 class="text-secondary mb-3">Campos Não Aplicáveis (conforme manual atualizado)</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="alert alert-light border">
                                    <strong>Estornos (060-070):</strong> Não se aplicam desde dezembro/2007 e abril/2008
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-light border">
                                    <strong>Créditos Presumidos (080-090):</strong> Não se aplicam desde setembro/2008
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="alert alert-light border">
                                    <strong>Incentivos Fiscais (100-130):</strong> Não se aplicam desde abril/2008
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-light border">
                                    <strong>Pagamentos Antecipados (140-160):</strong> Não se aplicam desde maio/2009
                                </div>
                            </div>
                        </div>
                        <p class="text-muted small">Estes valores devem ser informados via Demonstrativo de Créditos Informados Previamente - DCIP.</p>
                    </div>

                    <!-- Subtotal -->
                    <div class="alert alert-success">
                        <h6 class="mb-2">990 - Subtotal de Créditos</h6>
                        <p class="mb-0">Somatório de todos os itens do quadro 05. Este valor é transportado automaticamente para o item 050 do Quadro 09.</p>
                    </div>
                </div>

                <!-- Regras Gerais -->
                <div class="alert alert-info">
                    <h6 class="alert-heading mb-2">
                        <i class="bi bi-lightbulb me-2"></i>Regras Gerais para Preenchimento
                    </h6>
                    <ul class="mb-0 small">
                        <li><strong>Campo automático:</strong> Item 010 do Quadro 04 é calculado automaticamente do Quadro 02</li>
                        <li><strong>Campo automático:</strong> Item 020 do Quadro 05 é calculado automaticamente do Quadro 01</li>
                        <li><strong>Subtotais:</strong> Calculados automaticamente pela soma dos campos preenchidos</li>
                        <li><strong>DCIP:</strong> Muitos créditos especiais passaram a ser informados via DCIP a partir de 2023</li>
                        <li><strong>Consistência:</strong> Verifique se os valores estão coerentes com os quadros 01 e 02</li>
                        <li><strong>Observações:</strong> Use o campo de observações para registrar informações relevantes</li>
                    </ul>
                </div>

                <!-- Impacto no Resultado Final -->
                <div class="mb-4">
                    <h6 class="text-warning mb-3">
                        <i class="bi bi-graph-up me-2"></i>Impacto no Resultado Final
                    </h6>
                    <div class="card">
                        <div class="card-body">
                            <p class="mb-2"><strong>Fórmula básica do ICMS:</strong></p>
                            <div class="text-center mb-3">
                                <code class="bg-light px-3 py-2 rounded">
                                    ICMS a Pagar = (Débitos do Quad. 04) - (Créditos do Quad. 05)
                                </code>
                            </div>
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <div class="p-2 border rounded">
                                        <strong class="text-danger">Débitos ↑</strong>
                                        <br><small>ICMS devido aumenta</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-2 border rounded">
                                        <strong class="text-success">Créditos ↑</strong>
                                        <br><small>ICMS devido diminui</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-2 border rounded">
                                        <strong class="text-primary">Resultado</strong>
                                        <br><small>Transportado p/ Quad. 09</small>
                                    </div>
                                </div>
                            </div>
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
    // Inicializar tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Máscaras para valores monetários
    setupMasks();

    // Listener para formulário do Quadro 04
    const formQuadro04 = document.getElementById('form-quadro04');
    if (formQuadro04) {
        formQuadro04.onsubmit = function(e) {
            e.preventDefault();
            salvarQuadro04();
        };
    }

    // Listener para formulário do Quadro 05
    const formQuadro05 = document.getElementById('form-quadro05');
    if (formQuadro05) {
        formQuadro05.onsubmit = function(e) {
            e.preventDefault();
            salvarQuadro05();
        };
    }
})();

function salvarQuadro04() {
    const form = document.getElementById('form-quadro04');

    if (!form) {
        showMessage('❌ Erro: Formulário do Quadro 04 não encontrado!', 'danger');
        return;
    }

    const formData = new FormData(form);

    // Mostrar loading
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Salvando...';
    }

    // URL para salvar
    const postUrl = 'quadros/ajax_save_0405.php';

    // Adicionar dime_id ao FormData
    formData.append('dime_id', window.DIME_CONFIG?.dimeId || new URLSearchParams(window.location.search).get('dime_id') || new URLSearchParams(window.parent.location.search).get('id'));

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
        showMessage('💥 Erro de conexão: ' + error.message, 'danger');
    })
    .finally(() => {
        // Restaurar botão
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-save me-2"></i>Salvar Quadro 04';
        }
    });
}

function salvarQuadro05() {
    const form = document.getElementById('form-quadro05');

    if (!form) {
        showMessage('❌ Erro: Formulário do Quadro 05 não encontrado!', 'danger');
        return;
    }

    const formData = new FormData(form);

    // Mostrar loading
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Salvando...';
    }

    // URL para salvar
    const postUrl = 'quadros/ajax_save_0405.php';

    // Adicionar dime_id ao FormData
    formData.append('dime_id', window.DIME_CONFIG?.dimeId || new URLSearchParams(window.location.search).get('dime_id') || new URLSearchParams(window.parent.location.search).get('id'));

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
        showMessage('💥 Erro de conexão: ' + error.message, 'danger');
    })
    .finally(() => {
        // Restaurar botão
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-save me-2"></i>Salvar Quadro 05';
        }
    });
}

function setupMasks() {
    // Máscaras para valores monetários
    const camposMonetarios = [
        // Quadro 04 - Débitos conforme Manual DIME-SEF 153/12 (exceto item 010 que é automático)
        'item_020_debito_diferencial_ativo_permanente',
        'item_030_debito_diferencial_material_consumo', 'item_040_debito_maquinas_equipamentos_importados',
        'item_045_debito_diferenca_aliquota_operacao_consumidor', 'item_050_estorno_credito',
        'item_060_outros_estornos_credito', 'item_065_estorno_credito_entrada_credito_presumido',
        'item_070_outros_debitos', 'item_990_subtotal_debitos',
        // Quadro 05 - Apenas campos que podem ser preenchidos conforme manual atualizado
        'item_010_saldo_credor_anterior',        // 010 - Saldo credor anterior
        'item_030_credito_ativo_permanente',     // 030 - Crédito ativo permanente (histórico)
        'item_040_credito_diferencial_aliquota', // 040 - Crédito diferencial alíquota (histórico)
        'item_045_credito_diferenca_aliquota_operacao', // 045 - Diferença alíquota operação (histórico)
        'item_050_credito_icms_retido_st',       // 050 - ICMS retido ST (histórico)
        'item_990_subtotal_creditos'
        // NOTA: Item 020 é readonly (calculado automaticamente)
        // Items 060-160 são "Não se aplica" - não precisam de máscara
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

                // Calcular totais automaticamente
                calcularTotalDebitos();
                calcularTotalCreditos();
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

    // Calcular totais iniciais
    calcularTotalDebitos();
    calcularTotalCreditos();
}

function calcularTotalDebitos() {
    // Campos do Quadro 04 conforme Manual DIME-SEF 153/12
    // NOTA: item_010_debito_saidas não é incluído pois é calculado automaticamente
    const camposDebitos = [
        'item_020_debito_diferencial_ativo_permanente',     // 020 - Débito por diferencial de alíquota de ativo permanente
        'item_030_debito_diferencial_material_consumo',     // 030 - Débito por diferencial de alíquota de material de uso ou consumo
        'item_040_debito_maquinas_equipamentos_importados', // 040 - Débito de máquinas/equipamentos importados
        'item_045_debito_diferenca_aliquota_operacao_consumidor', // 045 - Diferença de alíquota operação consumidor
        'item_050_estorno_credito',                         // 050 - Estorno de crédito
        'item_060_outros_estornos_credito',                 // 060 - Outros estornos de crédito
        'item_065_estorno_credito_entrada_credito_presumido', // 065 - Estorno crédito entrada crédito presumido
        'item_070_outros_debitos'                           // 070 - Outros débitos
    ];

    let total = 0;
    camposDebitos.forEach(campoId => {
        const campo = document.getElementById(campoId);
        if (campo && campo.value) {
            // Converter valor brasileiro (X.XXX,YY) para número
            // Primeiro: substituir pontos por nada, depois vírgula por ponto
            let valorStr = campo.value.replace(/\./g, '').replace(',', '.');
            const valor = parseFloat(valorStr) || 0;
            total += valor;
        }
    });

    // Adicionar o valor do item 010 (débitos das saídas) ao subtotal
    const campoItem010 = document.getElementById('item_010_debito_saidas');
    if (campoItem010 && campoItem010.value) {
        let valorStr010 = campoItem010.value.replace(/\./g, '').replace(',', '.');
        const valor010 = parseFloat(valorStr010) || 0;
        total += valor010;
    }

    const campoTotal = document.getElementById('item_990_subtotal_debitos');
    if (campoTotal) {
        campoTotal.value = total.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
}

function calcularTotalCreditos() {
    // Campos do Quadro 05 conforme Manual DIME-SEF 153/12
    // NOTA: Apenas campos que podem ter valores são incluídos no cálculo
    const camposCreditos = [
        'item_010_saldo_credor_anterior',        // 010 - Saldo credor do mês anterior
        'item_020_credito_entradas',             // 020 - Crédito pelas entradas (calculado automaticamente)
        'item_030_credito_ativo_permanente',     // 030 - Crédito de ativo permanente (histórico)
        'item_040_credito_diferencial_aliquota', // 040 - Crédito por diferencial de alíquota (histórico)
        'item_045_credito_diferenca_aliquota_operacao', // 045 - Diferença de alíquota operação (histórico)
        'item_050_credito_icms_retido_st'        // 050 - ICMS retido por ST (histórico)
        // NOTA: Items 060-160 são "Não se aplica" segundo o manual atualizado
    ];

    let total = 0;
    camposCreditos.forEach(campoId => {
        const campo = document.getElementById(campoId);
        if (campo && campo.value) {
            // Converter valor brasileiro (X.XXX,YY) para número
            // Primeiro: substituir pontos por nada, depois vírgula por ponto
            let valorStr = campo.value.replace(/\./g, '').replace(',', '.');
            const valor = parseFloat(valorStr) || 0;
            total += valor;
        }
    });

    const campoTotal = document.getElementById('item_990_subtotal_creditos');
    if (campoTotal) {
        campoTotal.value = total.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
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
