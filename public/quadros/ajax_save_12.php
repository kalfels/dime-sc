<?php
header('Content-Type: application/json');

try {
    if (!isset($_POST['dime_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'dime_id obrigatório']);
        exit;
    }

    $dime_id = (int)$_POST['dime_id'];
    $action = $_POST['action'] ?? '';

    if ($action !== 'save_quadro12') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action inválida']);
        exit;
    }

    // Conectar ao banco usando mysqli
    require_once __DIR__ . '/../includes/config.php';
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        throw new Exception('Erro de conexão: ' . $conn->connect_error);
    }

    // Processar dados dos pagamentos
    $pagamentos = [];
    if (isset($_POST['pagamentos'])) {
        if (is_string($_POST['pagamentos'])) {
            $pagamentos = json_decode($_POST['pagamentos'], true);
        } elseif (is_array($_POST['pagamentos'])) {
            $pagamentos = $_POST['pagamentos'];
        }
    }

    // Limpar pagamentos existentes
    $stmt = $conn->prepare("DELETE FROM dime_quadro_12 WHERE dime_declaracao_id = ?");
    $stmt->bind_param("i", $dime_id);
    $stmt->execute();
    $stmt->close();

    // Inserir novos pagamentos
    if (!empty($pagamentos)) {
        $stmt = $conn->prepare("
            INSERT INTO dime_quadro_12 
            (dime_declaracao_id, origem, codigo_receita, classe_vencimento, data_vencimento, valor, numero_acordo) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($pagamentos as $pagamento) {
            $origem = (int)($pagamento['origem'] ?? 1);
            $codigoReceita = trim($pagamento['codigo_receita'] ?? '');
            $classeVencimento = trim($pagamento['classe_vencimento'] ?? '');
            $dataVencimento = !empty($pagamento['data_vencimento']) ? $pagamento['data_vencimento'] : null;
            $valor = (float)str_replace(',', '.', str_replace('.', '', $pagamento['valor'] ?? '0'));
            $numeroAcordo = trim($pagamento['numero_acordo'] ?? '');

            $stmt->bind_param("iisssds", 
                $dime_id,
                $origem,
                $codigoReceita,
                $classeVencimento,
                $dataVencimento,
                $valor,
                $numeroAcordo
            );
            $stmt->execute();
        }
        $stmt->close();
    }

    $conn->close();

    echo json_encode([
        'success' => true,
        'message' => 'Quadro 12 salvo com sucesso!'
    ]);

} catch (Exception $e) {
    if (isset($conn)) $conn->close();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>
