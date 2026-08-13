<?php
header('Content-Type: application/json');

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
        exit;
    }

    // Obter dados JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'JSON inválido: ' . json_last_error_msg()]);
        exit;
    }

    // Validação básica
    if (!isset($data['dime_declaracao_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID da declaração não informado']);
        exit;
    }

    // Conectar ao banco
    require_once __DIR__ . '/../includes/config.php';

    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 30
        ]);
    } catch (PDOException $e) {
        error_log("Erro PDO: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro de conexão com banco']);
        exit;
    }

    $dimeId = (int)$data['dime_declaracao_id'];

    // Verificar se declaração existe
    $stmt = $pdo->prepare("SELECT id FROM dime_declaracoes WHERE id = ?");
    $stmt->execute([$dimeId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Declaração não encontrada']);
        exit;
    }

    // Verificar se já existe registro
    $stmt = $pdo->prepare("SELECT id FROM dime_quadro_41 WHERE dime_declaracao_id = ?");
    $stmt->execute([$dimeId]);
    $existing = $stmt->fetch();

    // Preparar campos para inserção/atualização
    $campos = [
        'item_010_percentual_credito' => (float)($data['item_010_percentual_credito'] ?? 0),
        'item_017_produtos_exportados' => (float)($data['item_017_produtos_exportados'] ?? 0),
        'item_018_produtos_isentos' => (float)($data['item_018_produtos_isentos'] ?? 0),
        'item_019_produtos_diferidos' => (float)($data['item_019_produtos_diferidos'] ?? 0),
        'item_020_mercadorias_exportadas' => (float)($data['item_020_mercadorias_exportadas'] ?? 0),
        'item_030_mercadorias_isentas' => (float)($data['item_030_mercadorias_isentas'] ?? 0),
        'item_040_mercadorias_diferidas' => (float)($data['item_040_mercadorias_diferidas'] ?? 0),
        'item_120_creditos_exportacao' => (float)($data['item_120_creditos_exportacao'] ?? 0),
        'item_130_creditos_isentos' => (float)($data['item_130_creditos_isentos'] ?? 0),
        'item_140_creditos_diferidos' => (float)($data['item_140_creditos_diferidos'] ?? 0),
        'item_160_saldo_anterior_exportacao' => (float)($data['item_160_saldo_anterior_exportacao'] ?? 0),
        'item_170_saldo_anterior_isentos' => (float)($data['item_170_saldo_anterior_isentos'] ?? 0),
        'item_180_saldo_anterior_diferidos' => (float)($data['item_180_saldo_anterior_diferidos'] ?? 0),
        'item_190_saldo_outros_creditos' => (float)($data['item_190_saldo_outros_creditos'] ?? 0),
        'item_217_credito_autorizado_exportacao' => (float)($data['item_217_credito_autorizado_exportacao'] ?? 0),
        'item_218_credito_autorizado_isentos' => (float)($data['item_218_credito_autorizado_isentos'] ?? 0),
        'item_219_credito_autorizado_diferidos' => (float)($data['item_219_credito_autorizado_diferidos'] ?? 0),
        'item_960_saldo_acumulado_exportacao' => (float)($data['item_960_saldo_acumulado_exportacao'] ?? 0),
        'item_961_proporcao_exportacao' => (float)($data['item_961_proporcao_exportacao'] ?? 0),
        'item_970_saldo_acumulado_isentos' => (float)($data['item_970_saldo_acumulado_isentos'] ?? 0),
        'item_971_proporcao_isentos' => (float)($data['item_971_proporcao_isentos'] ?? 0),
        'item_980_saldo_acumulado_diferidos' => (float)($data['item_980_saldo_acumulado_diferidos'] ?? 0),
        'item_981_proporcao_diferidos' => (float)($data['item_981_proporcao_diferidos'] ?? 0),
        'item_991_saldos_consolidados' => (float)($data['item_991_saldos_consolidados'] ?? 0),
        'item_993_credito_transferencia' => (float)($data['item_993_credito_transferencia'] ?? 0),
        'item_997_outras_deducoes' => (float)($data['item_997_outras_deducoes'] ?? 0),
        'item_999_total_deducoes' => (float)($data['item_999_total_deducoes'] ?? 0),
    ];

    if ($existing) {
        // Update
        $sql = "UPDATE dime_quadro_41 SET ";
        $updates = [];
        foreach ($campos as $campo => $valor) {
            $updates[] = "$campo = ?";
        }
        $sql .= implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE dime_declaracao_id = ?";

        $stmt = $pdo->prepare($sql);
        $params = array_values($campos);
        $params[] = $dimeId;
        $stmt->execute($params);

        $message = "Quadro 41 atualizado com sucesso";
    } else {
        // Insert
        $campos['dime_declaracao_id'] = $dimeId;
        $columns = implode(', ', array_keys($campos));
        $placeholders = str_repeat('?, ', count($campos) - 1) . '?';

        $sql = "INSERT INTO dime_quadro_41 ($columns) VALUES ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($campos));

        $message = "Quadro 41 criado com sucesso";
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'id' => $existing ? $existing['id'] : $pdo->lastInsertId()
    ]);

} catch (Exception $e) {
    error_log("Erro no ajax_save_41.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>
