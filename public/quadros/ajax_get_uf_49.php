<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Validações
$dimeId = isset($_GET['dime_id']) ? (int)$_GET['dime_id'] : 0;
$uf = isset($_GET['uf']) ? trim(strtoupper($_GET['uf'])) : '';

if (!$dimeId || !$uf) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros incompletos']);
    exit;
}

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

    // Buscar dados da UF
    $stmt = $pdo->prepare("SELECT uf, valor_contabil, base_calculo, outras, icms_retido_st_petroleo_energia, icms_retido_st_outros_produtos FROM dime_quadro_49 WHERE dime_declaracao_id = ? AND uf = ?");
    $stmt->execute([$dimeId, $uf]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($dados) {
        echo json_encode([
            'success' => true,
            'dados' => $dados
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'UF não encontrada'
        ]);
    }

} catch (Exception $e) {
    error_log("Erro ao buscar UF no Quadro 49: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor'
    ]);
}
?>
