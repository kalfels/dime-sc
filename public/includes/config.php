<?php
// Configurações de sessão - DEVEM VIR ANTES DE QUALQUER session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.gc_maxlifetime', 3600); // 1 hora
ini_set('session.cookie_lifetime', 0); // Até o navegador ser fechado

require_once __DIR__ . '/../../src/app_classes.php';

// Iniciar sessão APÓS as configurações
session_start();

// Configurações gerais do sistema
define('APP_NAME', 'Sistema DIME');
define('APP_URL', 'http://' . $_SERVER['HTTP_HOST']);

// Configurações do banco de dados
define('DB_HOST', \App\Core\Env::get('DB_HOST', 'localhost'));
define('DB_NAME', \App\Core\Env::get('DB_NAME', 'dime'));
define('DB_USER', \App\Core\Env::get('DB_USER', 'root'));
define('DB_PASS', \App\Core\Env::get('DB_PASS', ''));

// Níveis de acesso
define('ROLE_ADMIN', 'admin');
define('ROLE_OPERADOR', 'operador');

// Status de usuário
define('STATUS_ATIVO', 'ativo');
define('STATUS_INATIVO', 'inativo');
define('STATUS_PENDENTE', 'pendente');

// Status de pagamento
define('PAGAMENTO_ATIVO', 'ativo');
define('PAGAMENTO_BLOQUEADO', 'bloqueado');
define('PAGAMENTO_PENDENTE', 'pendente');

// Configurações de upload
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx']);

// Função para redirecionamento
function redirect($url) {
    header("Location: " . APP_URL . "/" . ltrim($url, "/"));
    exit;
}

// Função para exibir mensagens de flash
function flash($type = '', $message = '') {
    static $messages = [];

    if ($type === '') {
        $msgs = $messages;
        $messages = [];
        return $msgs;
    }

    if ($message === '') {
        return $messages[$type] ?? [];
    }

    if (!isset($messages[$type])) {
        $messages[$type] = [];
    }

    $messages[$type][] = $message;
}
