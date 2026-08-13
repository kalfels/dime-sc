<?php
/**
 * AJAX Handler para salvar CFOPs de entrada (Quadro 01/03)
 * DIME-SC - Sistema de gestão de declarações DIME
 */

// Configurações de erro
error_reporting(E_ALL);
ini_set('display_errors', 0); // Desabilitar display para AJAX
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/ajax_errors.log');

// Includes necessários
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Verificar sessão
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
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
        case 'add_cfop':
            // Adicionar novo CFOP de Entrada
            $campos = [
                'dime_declaracao_id' => $dime_id,
                'cfop_id' => (int)$_POST['cfop_id'],
                'valor_contabil' => (float)str_replace(',', '.', str_replace('.', '', $_POST['valor_contabil'] ?? '0')),
                'base_calculo' => (float)str_replace(',', '.', str_replace('.', '', $_POST['base_calculo'] ?? '0')),
                'valor_imposto' => (float)str_replace(',', '.', str_replace('.', '', $_POST['valor_imposto'] ?? '0')),
                'valor_isento_nao_tributado' => (float)str_replace(',', '.', str_replace('.', '', $_POST['valor_isento_nao_tributado'] ?? '0')),
                'valor_outras' => (float)str_replace(',', '.', str_replace('.', '', $_POST['valor_outras'] ?? '0')),
                'base_calculo_imposto_retido' => (float)str_replace(',', '.', str_replace('.', '', $_POST['base_calculo_imposto_retido'] ?? '0')),
                'imposto_retido' => (float)str_replace(',', '.', str_replace('.', '', $_POST['imposto_retido'] ?? '0')),
                'debito_imposto_diferencial_aliquota' => (float)str_replace(',', '.', str_replace('.', '', $_POST['debito_imposto_diferencial_aliquota'] ?? '0')),
                'observacoes' => trim($_POST['observacoes'] ?? '')
            ];

            $sql = "INSERT INTO dime_quadro_01 (
                dime_declaracao_id, cfop_id, valor_contabil, base_calculo, valor_imposto,
                valor_isento_nao_tributado, valor_outras, base_calculo_imposto_retido,
                imposto_retido, debito_imposto_diferencial_aliquota, observacoes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($campos));

            echo json_encode([
                'success' => true,
                'message' => 'CFOP de entrada adicionado com sucesso!',
                'data' => $campos
            ]);
            break;

        case 'delete_cfop':
            // Remover CFOP de Entrada
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM dime_quadro_01 WHERE id = ? AND dime_declaracao_id = ?");
            $stmt->execute([$id, $dime_id]);

            echo json_encode([
                'success' => true,
                'message' => 'CFOP de entrada excluído com sucesso!'
            ]);
            break;

        case 'delete_all_cfops':
            // Excluir todas as CFOPs de entrada desta DIME
            $stmt = $pdo->prepare("DELETE FROM dime_quadro_01 WHERE dime_declaracao_id = ?");
            $stmt->execute([$dime_id]);

            echo json_encode([
                'success' => true,
                'message' => 'Todas as CFOPs de entrada foram excluídas com sucesso!'
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Ação desconhecida']);
            break;
    }

} catch (Exception $e) {
    error_log("Erro em ajax_save_0103.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>
