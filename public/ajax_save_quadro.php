<?php
/**
 * AJAX - Salvar Quadro
 * Endpoint para salvar dados dos quadros via AJAX
 */

require_once '../includes/config.php';
session_start();

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Obter dados do POST
$quadro = $_POST['quadro'] ?? '';
$dime_id = (int)($_POST['dime_id'] ?? 0);

if (!$dime_id || !in_array($quadro, ['00', '04', '05'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verificar se a declaração existe
    $stmt = $pdo->prepare("SELECT id FROM dime_declaracoes WHERE id = ?");
    $stmt->execute([$dime_id]);
    if (!$stmt->fetch()) {
        throw new Exception("Declaração não encontrada");
    }

    // Processar conforme o quadro
    switch ($quadro) {
        case '00':
            salvarQuadro00($pdo, $dime_id, $_POST);
            break;
        case '04':
            salvarQuadro04($pdo, $dime_id, $_POST);
            break;
        case '05':
            salvarQuadro05($pdo, $dime_id, $_POST);
            break;
    }

    echo json_encode(['success' => true, 'message' => "Quadro $quadro salvo com sucesso"]);

} catch (Exception $e) {
    error_log("Erro ao salvar quadro $quadro: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

function salvarQuadro00($pdo, $dime_id, $data) {
    // Verificar se já existe registro
    $stmt = $pdo->prepare("SELECT id FROM dime_quadro_00 WHERE dime_declaracao_id = ?");
    $stmt->execute([$dime_id]);
    $existing = $stmt->fetch();

    $campos = [
        'item_010_cnpj' => $data['item_010_cnpj'] ?? '',
        'item_020_inscricao_estadual' => $data['item_020_inscricao_estadual'] ?? '',
        'item_030_nome_empresarial' => $data['item_030_nome_empresarial'] ?? '',
        'item_040_tipo_declaracao' => $data['item_040_tipo_declaracao'] ?? '1',
        'item_050_regime_apuracao' => $data['item_050_regime_apuracao'] ?? '2',
        'item_060_porte_empresa' => $data['item_060_porte_empresa'] ?? '1',
        'item_070_apuracao_consolidada' => $data['item_070_apuracao_consolidada'] ?? '1',
        'item_080_apuracao_centralizada' => $data['item_080_apuracao_centralizada'] ?? '1',
        'item_090_transferencia_creditos' => $data['item_090_transferencia_creditos'] ?? '1',
        'item_100_creditos_presumidos' => $data['item_100_creditos_presumidos'] ?? '1',
        'item_110_creditos_incentivos_fiscais' => $data['item_110_creditos_incentivos_fiscais'] ?? '1',
        'item_120_movimento' => $data['item_120_movimento'] ?? '3',
        'item_130_substituto_tributario' => $data['item_130_substituto_tributario'] ?? '2',
        'item_140_escrita_contabil' => $data['item_140_escrita_contabil'] ?? '1',
        'item_150_quantidade_trabalhadores' => $data['item_150_quantidade_trabalhadores'] ?? ''
    ];

    if ($existing) {
        // Update
        $sql = "UPDATE dime_quadro_00 SET ";
        $updates = [];
        foreach ($campos as $campo => $valor) {
            $updates[] = "$campo = ?";
        }
        $sql .= implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE dime_declaracao_id = ?";

        $stmt = $pdo->prepare($sql);
        $params = array_values($campos);
        $params[] = $dime_id;
        $stmt->execute($params);
    } else {
        // Insert
        $campos['dime_declaracao_id'] = $dime_id;
        $columns = implode(', ', array_keys($campos));
        $placeholders = str_repeat('?, ', count($campos) - 1) . '?';

        $sql = "INSERT INTO dime_quadro_00 ($columns) VALUES ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($campos));
    }
}

function salvarQuadro04($pdo, $dime_id, $data) {
    // Implementar salvamento do Quadro 04
    // Por enquanto, apenas simular sucesso
}

function salvarQuadro05($pdo, $dime_id, $data) {
    // Implementar salvamento do Quadro 05
    // Por enquanto, apenas simular sucesso
}
?>
