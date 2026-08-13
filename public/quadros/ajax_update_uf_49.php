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

// Validar valores numéricos
$valorContabil = isset($data['valor_contabil']) ? str_replace(['.', ','], ['', '.'], $data['valor_contabil']) : 0;
$baseCalculo = isset($data['base_calculo']) ? str_replace(['.', ','], ['', '.'], $data['base_calculo']) : 0;
$outras = isset($data['outras']) ? str_replace(['.', ','], ['', '.'], $data['outras']) : 0;
$stPetroleo = isset($data['icms_retido_st_petroleo_energia']) ? str_replace(['.', ','], ['', '.'], $data['icms_retido_st_petroleo_energia']) : 0;
$stOutros = isset($data['icms_retido_st_outros_produtos']) ? str_replace(['.', ','], ['', '.'], $data['icms_retido_st_outros_produtos']) : 0;

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

    // Verificar se UF existe
    $stmt = $pdo->prepare("SELECT id FROM dime_quadro_49 WHERE dime_declaracao_id = ? AND uf = ?");
    $stmt->execute([$dimeId, $uf]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'UF não encontrada']);
        exit;
    }

    // Atualizar UF
    $stmt = $pdo->prepare("UPDATE dime_quadro_49 SET valor_contabil = ?, base_calculo = ?, outras = ?, icms_retido_st_petroleo_energia = ?, icms_retido_st_outros_produtos = ?, updated_at = CURRENT_TIMESTAMP WHERE dime_declaracao_id = ? AND uf = ?");
    $stmt->execute([$valorContabil, $baseCalculo, $outras, $stPetroleo, $stOutros, $dimeId, $uf]);

    echo json_encode([
        'success' => true,
        'message' => 'UF atualizada com sucesso',
        'uf_atualizada' => $uf
    ]);

} catch (Exception $e) {
    error_log("Erro ao atualizar UF no Quadro 49: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor'
    ]);
}
?>
