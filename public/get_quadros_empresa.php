<?php
// get_quadros_empresa.php - API para carregar quadros de uma empresa
session_start();
require_once '../src/app_classes.php';

header('Content-Type: application/json');

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$contabilista_id_session = $_SESSION['contabilista_id'];

$empresa_id = $_GET['empresa_id'] ?? null;

if (!$empresa_id) {
    echo json_encode(['success' => false, 'message' => 'ID da empresa não fornecido']);
    exit;
}

// Verificar permissões
if ($user_role !== 'admin') {
    // Verificar se o operador tem acesso a esta empresa
    $empresa = \App\Models\Empresa::find($empresa_id);
    if (!$empresa || $empresa->contabilista_id != $contabilista_id_session) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado a esta empresa']);
        exit;
    }
}

try {
    $pdo = \App\Core\DBConnection::getInstance()->getConnection();

    // Quadros disponíveis no sistema (padrão)
    $quadrosDisponiveis = [
        '00', '0103', '0203', '0405', '09', '10', '11', '12', '14', '41', '46', '49', '50'
    ];

    // Buscar quadros configurados para esta empresa
    $stmt = $pdo->prepare("SELECT quadro_numero, ativo FROM dime_quadro_empresas WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $empresaQuadros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Adicionar quadros não-padrão à lista de disponíveis
    foreach ($empresaQuadros as $eq) {
        if (!in_array($eq['quadro_numero'], $quadrosDisponiveis)) {
            $quadrosDisponiveis[] = $eq['quadro_numero'];
        }
    }

    echo json_encode([
        'success' => true,
        'quadros' => $quadrosDisponiveis,
        'empresa_quadros' => $empresaQuadros
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
?>
