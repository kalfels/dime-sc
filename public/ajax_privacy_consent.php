<?php
/**
 * AJAX Privacy Consent Handler
 * Registra consentimentos de privacidade conforme LGPD
 */

// Inicia sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Verifica se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Lê o JSON da requisição
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos']);
    exit;
}

// Validação básica
$action = $data['action'] ?? '';
$timestamp = $data['timestamp'] ?? 0;
$user_agent = $data['user_agent'] ?? '';

if (!in_array($action, ['accept', 'decline'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Ação inválida']);
    exit;
}

// Dados para log
$consent_data = [
    'action' => $action,
    'timestamp' => $timestamp,
    'date' => date('Y-m-d H:i:s', $timestamp / 1000),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $user_agent,
    'session_id' => session_id() ?: 'no_session'
];

// Log do consentimento (obrigatório para auditoria LGPD)
$log_entry = date('Y-m-d H:i:s') . " - " . 
             "Action: {$action}, " .
             "IP: {$consent_data['ip']}, " .
             "Session: {$consent_data['session_id']}, " .
             "UserAgent: " . substr($user_agent, 0, 100) . 
             PHP_EOL;

// Salva no arquivo de log (crie a pasta logs se necessário)
$log_dir = __DIR__ . '/../logs';
$log_success = false;
$log_error = '';

try {
    // Cria diretório se não existir
    if (!is_dir($log_dir)) {
        if (!mkdir($log_dir, 0755, true)) {
            throw new Exception("Não foi possível criar o diretório de logs: {$log_dir}");
        }
    }
    
    // Verifica se o diretório é gravável
    if (!is_writable($log_dir)) {
        throw new Exception("Diretório de logs não é gravável: {$log_dir}");
    }
    
    $log_file = $log_dir . '/privacy_consent.log';
    
    // Tenta gravar o log
    $bytes_written = file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    if ($bytes_written === false) {
        throw new Exception("Falha ao escrever no arquivo de log: {$log_file}");
    }
    
    $log_success = true;
    
} catch (Exception $e) {
    $log_error = $e->getMessage();
    // Log alternativo em caso de falha
    error_log("Privacy Consent Log Error: " . $log_error);
    error_log("Privacy Consent Data: " . $log_entry);
}

// Opcional: Salvar no banco de dados para auditoria mais robusta
/*
try {
    require_once '../src/app_classes.php';
    use App\Core\DBConnection;
    
    $pdo = DBConnection::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("
        INSERT INTO privacy_consents (
            action, timestamp, ip_address, user_agent, session_id, created_at
        ) VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $action,
        $consent_data['date'],
        $consent_data['ip'],
        $user_agent,
        $consent_data['session_id']
    ]);
    
} catch (Exception $e) {
    // Log error but don't fail the consent process
    error_log("Privacy consent DB error: " . $e->getMessage());
}
*/

// Resposta de sucesso
$response = [
    'success' => true,
    'message' => 'Consentimento registrado com sucesso',
    'action' => $action,
    'timestamp' => $consent_data['date'],
    'log_saved' => $log_success
];

// Adiciona informação de erro se houver
if (!$log_success && !empty($log_error)) {
    $response['log_error'] = $log_error;
    $response['message'] .= ' (Aviso: Log não foi salvo)';
}

echo json_encode($response);
?>
