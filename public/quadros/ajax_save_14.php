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
    $stmt = $pdo->prepare("SELECT id FROM dime_quadro_14 WHERE dime_declaracao_id = ?");
    $stmt->execute([$dimeId]);
    $existing = $stmt->fetch();

    // Preparar campos para inserção/atualização
    $campos = [
        'item_010_base_calculo' => (float)($data['item_010_base_calculo'] ?? 0),
        'item_020_debitos_saidas' => (float)($data['item_020_debitos_saidas'] ?? 0),
        'item_030_credito_presumido' => (float)($data['item_030_credito_presumido'] ?? 0),
        'item_031_creditos_compensar' => (float)($data['item_031_creditos_compensar'] ?? 0),
        'item_040_saldo_devedor' => (float)($data['item_040_saldo_devedor'] ?? 0),
        'item_045_debito_consolidado' => (float)($data['item_045_debito_consolidado'] ?? 0),
        'item_050_debito_extemporaneo' => (float)($data['item_050_debito_extemporaneo'] ?? 0),
        'item_110_saldo_credor_anterior' => (float)($data['item_110_saldo_credor_anterior'] ?? 0),
        'item_120_credito_antecipado' => (float)($data['item_120_credito_antecipado'] ?? 0),
        'item_130_total_antecipacoes' => (float)($data['item_130_total_antecipacoes'] ?? 0),
        'item_150_debito_transferido' => (float)($data['item_150_debito_transferido'] ?? 0),
        'item_199_imposto_recolher' => (float)($data['item_199_imposto_recolher'] ?? 0),
        'item_198_saldo_credor_seguinte' => (float)($data['item_198_saldo_credor_seguinte'] ?? 0),
        'observacoes' => $data['observacoes'] ?? null
    ];

    if ($existing) {
        // Update
        $sql = "UPDATE dime_quadro_14 SET ";
        $updates = [];
        foreach ($campos as $campo => $valor) {
            $updates[] = "$campo = ?";
        }
        $sql .= implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE dime_declaracao_id = ?";

        $stmt = $pdo->prepare($sql);
        $params = array_values($campos);
        $params[] = $dimeId;
        $stmt->execute($params);

        $message = "Quadro 14 atualizado com sucesso";
    } else {
        // Insert
        $campos['dime_declaracao_id'] = $dimeId;
        $columns = implode(', ', array_keys($campos));
        $placeholders = str_repeat('?, ', count($campos) - 1) . '?';

        $sql = "INSERT INTO dime_quadro_14 ($columns) VALUES ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($campos));

        $message = "Quadro 14 criado com sucesso";
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'id' => $existing ? $existing['id'] : $pdo->lastInsertId()
    ]);

} catch (Exception $e) {
    error_log("Erro no ajax_save_14.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>
