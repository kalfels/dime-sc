<?php
// Inicia a sessão
session_start();

// Destrói todas as variáveis de sessão
$_SESSION = array();

// Se desejar destruir o cookie de sessão também
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destrói a sessão
session_destroy();

// Redireciona para a página de login com mensagem de sucesso
header("Location: /index.php?logout=1");
exit;
