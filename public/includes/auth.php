<?php
// Inicia a sessão se ainda não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header('Location: /index.php');
    exit;
}

// Carrega o usuário atual
require_once __DIR__ . '/../../src/app_classes.php';
use App\Models\Usuario;

$usuario = Usuario::find((int)$_SESSION['user_id']);

// Se o usuário não for encontrado ou não tiver mais acesso
if (!$usuario) {
    $_SESSION['error'] = 'Sessão inválida. Por favor, faça login novamente.';
    session_destroy();
    header('Location: /index.php');
    exit;
}

// Check if user is active (only block if inactive)
if (!$usuario->isActive()) {
    $_SESSION['error'] = 'Sua conta está desativada. Entre em contato com o administrador.';
    session_destroy();
    header('Location: /index.php');
    exit;
}

// REMOVED: Payment status blocking - now handled with warnings instead
/*
if (!$usuario->isPaymentActive()) {
    $_SESSION['error'] = 'Seu acesso está bloqueado devido a pendências financeiras. Entre em contato com o suporte.';
    session_destroy();
    header('Location: /index.php');
    exit;
}
*/

// Atualiza a sessão com os dados mais recentes do usuário
$_SESSION['user_name'] = $usuario->nome;
$_SESSION['user_email'] = $usuario->email;
$_SESSION['user_role'] = $usuario->role;
$_SESSION['contabilista_id'] = $usuario->contabilista_id;
$_SESSION['is_admin'] = $usuario->isAdmin();

// Define se o usuário tem pendências financeiras (para mostrar avisos)
$_SESSION['has_payment_issues'] = $usuario->hasPaymentIssues();

// Verifica se é um operador em primeiro acesso
if ($usuario->role === Usuario::ROLE_OPERADOR &&
    isset($usuario->primeiro_acesso) &&
    $usuario->primeiro_acesso == 1 &&
    !in_array(basename($_SERVER['PHP_SELF']), ['setup_conta.php', 'index.php', 'gerenciar_contabilistas.php'])) {
    header('Location: /setup_conta.php');
    exit;
}

// REMOVED: Verificação antiga que redirecionava para primeiro_acesso.php
// if ($usuario->role === Usuario::ROLE_OPERADOR && empty($usuario->contabilista_id) && basename($_SERVER['PHP_SELF']) !== 'primeiro_acesso.php') {
//     header('Location: /primeiro_acesso.php');
//     exit;
// }

// REMOVED: Verificação antiga que redirecionava para perfil.php
// if ($usuario->primeiro_acesso && $usuario->role === Usuario::ROLE_OPERADOR && basename($_SERVER['PHP_SELF']) !== 'perfil.php') {
//     $_SESSION['first_access'] = true;
//     header('Location: /perfil.php');
//     exit;
// }

// REMOVED: Limpeza da flag antiga
// if (isset($_SESSION['first_access']) && basename($_SERVER['PHP_SELF']) === 'perfil.php') {
//     unset($_SESSION['first_access']);
// }

// Verifica permissões de administrador se necessário
$admin_pages = ['gerenciar_usuarios.php', 'novo_usuario.php'];
if (in_array(basename($_SERVER['PHP_SELF']), $admin_pages) && !$usuario->isAdmin()) {
    $_SESSION['error'] = 'Acesso restrito a administradores.';
    header('Location: /dashboard.php');
    exit;
}
