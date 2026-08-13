<?php
header('Content-Type: application/json');

try {
    // Log de depuração

    if (!isset($_POST['dime_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'dime_id obrigatório']);
        exit;
    }

    $dime_id = (int)$_POST['dime_id'];
    $action = $_POST['action'] ?? '';

    if ($action !== 'save_quadro11') {
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

    // Verificar se já existe registro
    $stmt = $conn->prepare("SELECT id FROM dime_quadro_11 WHERE dime_declaracao_id = ?");
    $stmt->bind_param("i", $dime_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();

    // Mapeamento correto dos campos baseado nos nomes usados no frontend
    $campos = [
        'dime_declaracao_id' => $dime_id,
        // Débitos ST - nomes conforme usados no frontend
        'item_070_total_imposto_retido' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_070_total_imposto_retido'] ?? '0')),
        'item_073_imposto_st_entrada_regime_especial' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_073_imposto_st_entrada_regime_especial'] ?? '0')),
        'item_074_outros_debitos' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_074_outros_debitos'] ?? '0')),
        'item_075_saldos_devedores_consolidados' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_075_saldos_devedores_consolidados'] ?? '0')),
        'item_080_total_debitos' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_080_total_debitos'] ?? '0')),

        // Créditos ST - nomes conforme usados no frontend
        'item_090_saldo_credor_anterior_st' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_090_saldo_credor_anterior_st'] ?? '0')),
        'item_105_creditos_dcip' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_105_creditos_dcip'] ?? '0')),
        'item_115_ressarcimento_icms_st_nfe' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_115_ressarcimento_icms_st_nfe'] ?? '0')),
        'item_116_devolucao_mercadorias_desfazimento_atual' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_116_devolucao_mercadorias_desfazimento_atual'] ?? '0')),
        'item_125_saldos_credores_consolidados' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_125_saldos_credores_consolidados'] ?? '0')),
        'item_130_total_creditos' => (float)str_replace(',', '.', str_replace('.', '', $_POST['item_130_total_creditos'] ?? '0')),

        // Ajustes
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

        // Campos textuais
        'observacoes' => trim($_POST['observacoes_quadro11'] ?? '')
    ];


    if ($existing) {
        // UPDATE - construir query dinamicamente
        $updates = [];
        $values = [];
        $types = '';

        foreach ($campos as $key => $value) {
            if ($key !== 'dime_declaracao_id') {
                $updates[] = "$key = ?";
                $values[] = $value;
                $types .= is_float($value) ? 'd' : 's';
            }
        }

        $sql = "UPDATE dime_quadro_11 SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE dime_declaracao_id = ?";
        $values[] = $dime_id;
        $types .= 'i';


        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();

    } else {
        // INSERT - construir query dinamicamente
        $columns = implode(', ', array_keys($campos));
        $placeholders = str_repeat('?, ', count($campos) - 1) . '?';
        $sql = "INSERT INTO dime_quadro_11 ($columns) VALUES ($placeholders)";

        $values = array_values($campos);
        $types = '';
        foreach ($values as $value) {
            $types .= is_float($value) ? 'd' : 's';
        }


        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();

    }

    $conn->close();

    echo json_encode([
        'success' => true,
        'message' => 'Quadro 11 salvo com sucesso!'
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
