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

$dime_id = intval($_POST['id']);
$pdo = DBConnection::getInstance()->getConnection();

try {
    $pdo->beginTransaction();

    // Check if DIME exists and user has permission
    if ($user_role === 'admin') {
        $sql = "SELECT dd.*, dd.status_declaracao FROM dime_declaracoes dd WHERE dd.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dime_id]);
    } else {
        $sql = "SELECT dd.*, dd.status_declaracao FROM dime_declaracoes dd
                LEFT JOIN empresas e ON dd.empresa_id = e.id
                WHERE dd.id = ? AND e.contabilista_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dime_id, $contabilista_id_session]);
    }

    $dime = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dime) {
        throw new Exception('DIME não encontrada');
    }

    if ($dime['status_declaracao'] !== 'rascunho') {
        throw new Exception('Não é possível excluir uma DIME que não esteja em rascunho');
    }

    // Delete related quadro records in order (from most dependent to least dependent)
    $quadro_tables = [
        'dime_quadro_12_pagamentos',
        'dime_quadro_48',
        'dime_quadro_11',
        'dime_quadro_05',
        'dime_quadro_04',
        'dime_quadro_02',
        'dime_quadro_01',
        'dime_quadro_00'
    ];

    foreach ($quadro_tables as $table) {
        $sql = "DELETE FROM {$table} WHERE dime_declaracao_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dime_id]);
    }

    // Delete DIME declaration
    $sql = "DELETE FROM dime_declaracoes WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dime_id]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'DIME excluída com sucesso']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
