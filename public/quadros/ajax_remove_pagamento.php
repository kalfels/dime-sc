<?php
header('Content-Type: application/json');

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
        exit;
    }

    // Obter dados JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'JSON inválido']);
        exit;
    }

    // Validação
    if (!isset($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID do pagamento não informado']);
        exit;
    }

    $pagamentoId = (int)$data['id'];

    // Conectar ao banco
    require_once __DIR__ . '/../includes/config.php';
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verificar se o pagamento existe
    $stmt = $pdo->prepare("SELECT id FROM dime_quadro_12 WHERE id = ?");
    $stmt->execute([$pagamentoId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Pagamento não encontrado']);
        exit;
    }

    // Remover pagamento
    $stmt = $pdo->prepare("DELETE FROM dime_quadro_12 WHERE id = ?");
    $stmt->execute([$pagamentoId]);

    echo json_encode([
        'success' => true,
        'message' => 'Pagamento removido com sucesso'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>
