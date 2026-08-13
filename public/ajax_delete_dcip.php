<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../src/app_classes.php';

use App\Core\DBConnection;

header('Content-Type: application/json');

// Authentication check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_role = $_SESSION['user_role'];
$contabilista_id_session = $_SESSION['contabilista_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

$dcip_id = intval($_POST['id']);
$pdo = DBConnection::getInstance()->getConnection();

try {
    $pdo->beginTransaction();
    
    // Check if DCIP exists and user has permission
    if ($user_role === 'admin') {
        $sql = "SELECT dd.*, dd.status FROM dcip_declaracoes dd WHERE dd.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dcip_id]);
    } else {
        $sql = "SELECT dd.*, dd.status FROM dcip_declaracoes dd
                LEFT JOIN empresas e ON dd.empresa_id = e.id
                WHERE dd.id = ? AND e.contabilista_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dcip_id, $contabilista_id_session]);
    }
    
    $dcip = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dcip) {
        throw new Exception('DCIP não encontrada');
    }
    
    if ($dcip['status'] === 'finalizada') {
        throw new Exception('Não é possível excluir uma DCIP finalizada');
    }
    
    // Delete related credit records first
    $sql = "DELETE FROM dcip_creditos WHERE dcip_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dcip_id]);
    
    // Delete DCIP declaration
    $sql = "DELETE FROM dcip_declaracoes WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dcip_id]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'DCIP excluída com sucesso']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
