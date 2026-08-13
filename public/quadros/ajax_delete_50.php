<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

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
    echo json_encode(['success' => false, 'message' => 'JSON inválido']);
    exit;
}

// Validações
if (!isset($data['dime_id']) || !isset($data['uf'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

$dimeId = (int)$data['dime_id'];
$uf = trim(strtoupper($data['uf']));

// Validar UF
$ufs_validas = ['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO', 'EX'];

if (!in_array($uf, $ufs_validas)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'UF inválida']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 30
    ]);

    // Verificar se a declaração existe
    $stmt = $pdo->prepare("SELECT id FROM dime_declaracoes WHERE id = ?");
    $stmt->execute([$dimeId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Declaração não encontrada']);
        exit;
    }

    // Remover a UF específica
    $stmt = $pdo->prepare("DELETE FROM dime_quadro_50 WHERE dime_declaracao_id = ? AND uf = ?");
    $stmt->execute([$dimeId, $uf]);

    // Verificar se foi removido
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'UF removida com sucesso',
            'uf_removida' => $uf
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'UF não encontrada ou já removida'
        ]);
    }

} catch (Exception $e) {
    error_log("Erro ao remover UF no Quadro 50: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor'
    ]);
}
?>
