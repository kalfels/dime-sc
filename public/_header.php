<?php
// _layout.php
// Este arquivo inclui a parte superior e inferior do HTML, o menu, e scripts JS globais.
// As páginas individuais (dashboard.php, gerenciar_empresas.php, etc.) incluirão este arquivo.

// Variáveis esperadas (podem ser definidas nas páginas que incluem este layout):
// $page_title: Título específico da página
// $page_specific_styles: CSS inline específico da página (opcional)

// Inclui as classes do projeto
require_once __DIR__ . '/../src/app_classes.php';
use App\Models\Usuario;

// Inclui o arquivo de autenticação
require_once __DIR__ . '/includes/auth.php';

// Define o título da página
$page_title = $page_title ?? 'Sistema DIME';

// Verifica se o usuário está logado
$usuarioLogado = null;
if (isset($_SESSION['user_id'])) {
    $usuarioLogado = Usuario::find((int)$_SESSION['user_id']);
}

if (!$usuarioLogado) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['user_name']);
$user_email = htmlspecialchars($_SESSION['user_email']);
$user_role = htmlspecialchars($_SESSION['user_role']);
$contabilista_id_session = $_SESSION['contabilista_id'];

$usuarioLogado = Usuario::find($user_id);
if (!$usuarioLogado) {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Sistema DIME-SC'; ?></title>
    
    <!-- Favicon usando Bootstrap Icon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><path fill='%23007bff' d='M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5v2z'/><path fill='%23007bff' d='M4.603 14.087a.81.81 0 0 1-.438-.42c-.195-.388-.13-.776.08-1.102.198-.307.526-.568.897-.787a7.68 7.68 0 0 1 1.482-.645 19.697 19.697 0 0 0 1.062-.33 2.465 2.465 0 0 0 .554-.357.78.78 0 0 0 .126-.719c-.1-.249-.349-.429-.515-.509a4.28 4.28 0 0 0-.617-.216l-.019-.005-.048-.014-.146-.04c-.446-.1-.804-.17-1.095-.27-.26-.09-.47-.216-.601-.403-.132-.188-.18-.407-.18-.64 0-.5.333-.809.8-.809.186 0 .358.04.522.12l.048.023.05.027c.284.157.463.347.51.612.025.14.02.285-.014.427a.81.81 0 0 0 .42.437c.388.196.776.13 1.102-.08.307-.198.568-.526.787-.897.219-.372.418-.77.645-1.482.227-.712.35-1.29.33-1.062.014-.184.086-.384.357-.554a.78.78 0 0 1 .719-.126c.249.1.429.349.509.515.08.166.143.36.216.617l.005.019.014.048.04.146c.1.446.17.804.27 1.095.09.26.216.47.403.601.188.132.407.18.64.18.5 0 .809-.333.809-.8 0-.186-.04-.358-.12-.522l-.023-.048-.027-.05c-.157-.284-.347-.463-.612-.51a1.202 1.202 0 0 0-.427.014z'/></svg>" type="image/svg+xml">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- jQuery Mask Plugin -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/styles.css">
    
    <!-- Estilos específicos da página atual podem ir aqui, se houver -->
    <style>
        <?php echo $page_specific_styles ?? ''; ?>
    </style>
</head>
<body class="bg-light">
    <!-- Header/Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i class="bi bi-file-earmark-text me-2"></i>Sistema DIME-SC
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-file-plus me-1"></i>DIME
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="inserir_dime.php">
                                <i class="bi bi-plus-circle me-2"></i>Nova DIME
                            </a></li>
                            <li><a class="dropdown-item" href="listar_dime.php">
                                <i class="bi bi-list-ul me-2"></i>Listar DIMEs
                            </a></li>
                        </ul>
                    </li>
                    <?php if (isset($usuarioLogado->acesso_dcip) && $usuarioLogado->acesso_dcip == 1): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-file-earmark-check me-1"></i>DCIP
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="gerar_arquivo_dcip.php">
                                <i class="bi bi-file-earmark-plus me-2"></i>Nova DCIP
                            </a></li>
                            <li><a class="dropdown-item" href="listar_dcip.php">
                                <i class="bi bi-list-ul me-2"></i>Listar DCIPs
                            </a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-lines-fill me-1"></i>Cadastros
                        </a>
                        <ul class="dropdown-menu">
                            <?php if ($user_role === 'admin'): ?>
                                <li><a class="dropdown-item" href="gerenciar_contabilistas.php">
                                    <i class="bi bi-person-badge me-2"></i>Contabilistas
                                </a></li>
                            <?php endif; ?>
                            <?php if ($user_role === 'operador'): ?>
                                <li><a class="dropdown-item" href="gerenciar_contabilistas.php">
                                    <i class="bi bi-person-badge me-2"></i>Meus Dados
                                </a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="gerenciar_empresas.php">
                                <i class="bi bi-building me-2"></i>Empresas
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="gerenciar_municipios.php">
                                <i class="bi bi-geo-alt me-2"></i>Municípios
                            </a></li>
                            <li><a class="dropdown-item" href="gerenciar_cfop.php">
                                <i class="bi bi-tags me-2"></i>CFOP
                            </a></li>
                            <li><a class="dropdown-item" href="gerenciar_receitas.php">
                                <i class="bi bi-cash-coin me-2"></i>Códigos de Receita
                            </a></li>
                            <li><a class="dropdown-item" href="gerenciar_classes_vencimento.php">
                                <i class="bi bi-calendar-event me-2"></i>Classes de Vencimento
                            </a></li>
                        </ul>
                    </li>
                    <?php if ($user_role === 'admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-people me-1"></i>Usuários
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                            <li><a class="dropdown-item" href="gerenciar_usuarios.php">
                                <i class="bi bi-list-ul me-2"></i>Listar Usuários
                            </a></li>
                            <li><a class="dropdown-item" href="novo_usuario.php">
                                <i class="bi bi-plus-circle me-2"></i>Novo Usuário
                            </a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <div class="navbar-nav">
                    <?php if (isset($usuarioLogado) && $usuarioLogado): ?>
                        <div class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-1"></i>
                                <span class="me-2"><?= htmlspecialchars($usuarioLogado->nome) ?></span>
                                <?php if ($usuarioLogado->isAdmin()): ?>
                                    <span class="badge bg-danger">Admin</span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="seguranca.php">
                                    <i class="bi bi-shield-lock me-2"></i>Segurança
                                </a></li>
                                <?php if ($usuarioLogado->isAdmin()): ?>
                                <li><a class="dropdown-item" href="gerenciar_usuarios.php">
                                    <i class="bi bi-people me-2"></i>Gerenciar Usuários
                                </a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php" onclick="return confirm('Tem certeza que deseja sair?')">
                                    <i class="bi bi-box-arrow-right me-2"></i>Sair
                                </a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="index.php" class="nav-link">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Entrar
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <div class="container-fluid mt-3">
        <!-- Exibe mensagens de notificação -->
        <div class="row">
            <div class="col-12">
                <?php 
                // Inclui o arquivo de mensagens
                require_once __DIR__ . '/includes/messages.php';
                
                // Exibe as mensagens flash
                display_flash_messages();
                ?>
            </div>
        </div>
        
        <div class="row">
