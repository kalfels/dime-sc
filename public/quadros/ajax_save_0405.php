<?php
/**
 * AJAX Handler para salvar Quadros 04 e 05 - Débitos e Créditos
 * DIME-SC - Sistema de gestão de declarações DIME
 */

// Configurações de erro
error_reporting(E_ALL);
ini_set('display_errors', 0); // Desabilitar display para AJAX
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/ajax_errors.log');

// Includes necessários
require_once __DIR__ . '/../includes/config.php';

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verificar autenticação (simples)
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Obter dados
$dime_id = isset($_POST['dime_id']) ? (int)$_POST['dime_id'] : 0;
$action = $_POST['action'] ?? '';

if (!$dime_id || !$action) {
    echo json_encode(['success' => false, 'message' => 'Parâmetros obrigatórios faltando']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    switch ($action) {
        case 'save_quadro04':
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
                // Update - corrigido para manter ordem correta dos parâmetros
                $sql = "UPDATE dime_quadro_04 SET ";
                $updates = [];
                $values = [];
                foreach ($campos as $key => $value) {
                    if ($key !== 'dime_declaracao_id') {
                        $updates[] = "$key = ?";
                        $values[] = $value;
                    }
                }
                $sql .= implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE dime_declaracao_id = ?";
                $values[] = $dime_id;
                $stmt = $pdo->prepare($sql);
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
            break;

        case 'save_quadro05':
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
                // Update - corrigido para manter ordem correta dos parâmetros
                $sql = "UPDATE dime_quadro_05 SET ";
                $updates = [];
                $values = [];
                foreach ($campos as $key => $value) {
                    if ($key !== 'dime_declaracao_id') {
                        $updates[] = "$key = ?";
                        $values[] = $value;
                    }
                }
                $sql .= implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE dime_declaracao_id = ?";
                $values[] = $dime_id;
                $stmt = $pdo->prepare($sql);
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
            break;

        case 'save_quadro10':
            // Salvar dados do Quadro 10 (Débitos Específicos) conforme Manual DIME-SEF 153/12
            $campos = [
                'dime_declaracao_id' => $dime_id,
                // Débitos Específicos
                'item_010_debito_importacao' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_010_debito_importacao'] ?? '0')),
                'item_020_debito_entrada_uf' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_020_debito_entrada_uf'] ?? '0')),
                'item_030_debito_responsabilidade' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_030_debito_responsabilidade'] ?? '0')),
                'item_040_debito_fato_gerador' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_040_debito_fato_gerador'] ?? '0')),
                'item_050_outros_debitos' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_050_outros_debitos'] ?? '0')),
                // Total
                'item_990_total_debitos' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_990_total_debitos'] ?? '0')),
                'observacoes' => trim($_POST['observacoes'] ?? '')
            ];

            // Verificar se já existe registro para este dime_id
            $stmt = $pdo->prepare("SELECT id FROM dime_quadro_10 WHERE dime_declaracao_id = ?");
            $stmt->execute([$dime_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update - corrigido para manter ordem correta dos parâmetros
                $sql = "UPDATE dime_quadro_10 SET ";
                $updates = [];
                $values = [];
                foreach ($campos as $key => $value) {
                    if ($key !== 'dime_declaracao_id') {
                        $updates[] = "$key = ?";
                        $values[] = $value;
                    }
                }
                $sql .= implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE dime_declaracao_id = ?";
                $values[] = $dime_id;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
            } else {
                // Insert
                $sql = "INSERT INTO dime_quadro_10 (" . implode(', ', array_keys($campos)) . ") VALUES (" . str_repeat('?, ', count($campos) - 1) . "?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($campos));
            }

            echo json_encode([
                'success' => true,
                'message' => 'Quadro 10 (Débitos Específicos) salvo com sucesso!',
                'data' => $campos
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Ação desconhecida']);
            break;
    }

} catch (Exception $e) {
    error_log("Erro em ajax_save_0405.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>
