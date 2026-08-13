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
    if (!isset($data['origem']) || !isset($data['codigo_receita']) || !isset($data['valor'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Campos obrigatórios não informados']);
        exit;
    }

    // Conectar ao banco
    require_once __DIR__ . '/../includes/config.php';

    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ATTR_ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    } catch (PDOException $e) {
        error_log("Erro de conexão PDO: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro de conexão com banco de dados']);
        exit;
    }

    // Preparar dados
    $origem = $data['origem'];
    $codigoReceita = $data['codigo_receita'];
    $classeVencimento = isset($data['classe_vencimento']) ? $data['classe_vencimento'] : null;
    $dataVencimento = isset($data['data_vencimento']) && !empty($data['data_vencimento']) ? $data['data_vencimento'] : null;
    $valor = (float)$data['valor'];
    $numeroAcordo = isset($data['numero_acordo']) ? $data['numero_acordo'] : '00000000000000';

    // Obter dime_id
    $dimeId = isset($_GET['dime_id']) ? (int)$_GET['dime_id'] : 1;

    error_log("DEBUG: Tentando salvar pagamento - DIME ID: $dimeId, Origem: $origem, Codigo: $codigoReceita, Valor: $valor");

    // Verificar se declaração existe
    try {
        $stmt = $pdo->prepare("SELECT id FROM dime_declaracoes WHERE id = ?");
        $stmt->execute([$dimeId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Declaração não encontrada']);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Erro ao verificar declaração: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao verificar declaração']);
        exit;
    }

    // Buscar ID do código de receita
    try {
        $stmt = $pdo->prepare("SELECT id FROM t_codigos_receita WHERE codigo = ?");
        $stmt->execute([$codigoReceita]);
        $codigoReceitaId = $stmt->fetchColumn();

        if (!$codigoReceitaId) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Código de receita não encontrado: ' . $codigoReceita]);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Erro ao buscar código de receita: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar código de receita']);
        exit;
    }

    // Buscar ID da classe de vencimento (opcional)
    $classeVencimentoId = null;
    if ($classeVencimento) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM t_classes_vencimento WHERE codigo = ?");
            $stmt->execute([$classeVencimento]);
            $classeVencimentoId = $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Erro ao buscar classe de vencimento: " . $e->getMessage());
            // Não falhar se classe não for encontrada, apenas logar
        }
    }

    // Verificar se a tabela existe
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'dime_quadro_12'");
        if (!$stmt->fetch()) {
            error_log("Tabela dime_quadro_12 não existe");
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Estrutura do banco de dados não configurada']);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Erro ao verificar tabela: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao verificar estrutura do banco']);
        exit;
    }

    // Inserir pagamento
    try {
        $stmt = $pdo->prepare("
            INSERT INTO dime_quadro_12
            (dime_declaracao_id, origem, codigo_receita_id, classe_vencimento_id, data_vencimento, valor, numero_acordo)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([
            $dimeId,
            $origem,
            $codigoReceitaId,
            $classeVencimentoId,
            $dataVencimento,
            $valor,
            $numeroAcordo
        ]);

        if (!$result) {
            error_log("Erro ao executar INSERT: " . print_r($stmt->errorInfo(), true));
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar pagamento']);
            exit;
        }

        $pagamentoId = $pdo->lastInsertId();

        error_log("DEBUG: Pagamento salvo com sucesso - ID: $pagamentoId");

        echo json_encode([
            'success' => true,
            'message' => 'Pagamento salvo com sucesso',
            'id' => $pagamentoId
        ]);

    } catch (PDOException $e) {
        error_log("Erro PDO ao inserir pagamento: " . $e->getMessage());
        error_log("SQL State: " . $e->getCode());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar pagamento: ' . $e->getMessage()]);
        exit;
    }

} catch (Exception $e) {
    error_log("Erro geral no ajax_save_pagamento.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>
