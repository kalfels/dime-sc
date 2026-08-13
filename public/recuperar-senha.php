<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/app_classes.php';

use App\Models\Usuario;
use App\Services\EmailService;

// Inicia a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usuário já está logado
if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

$mensagem = '';
$tipoMensagem = '';
$token = $_GET['token'] ?? '';
$email = $_POST['email'] ?? '';
$novaSenha = $_POST['nova_senha'] ?? '';
$confirmarSenha = $_POST['confirmar_senha'] ?? '';

// Verifica se é uma requisição POST
$isPostRequest = (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST');

// Processa o formulário de solicitação de redefinição
if ($isPostRequest && isset($_POST['solicitar_redefinicao'])) {
    if (empty($email)) {
        $mensagem = 'Por favor, informe o e-mail da conta.';
        $tipoMensagem = 'danger';
    } else {
        $usuario = Usuario::where('email', $email);
        $usuario = !empty($usuario) ? $usuario[0] : null;
        
        if ($usuario) {
            // Gera um token de redefinição usando o método da classe Usuario
            $token = $usuario->gerarTokenRedefinicao();
            
            // Salva o token no banco de dados
            $usuario->save();
            
            // Envia o e-mail de redefinição
            $emailService = new EmailService();
            $enviado = $emailService->enviarEmailRedefinicaoSenha(
                $usuario->email,
                $usuario->nome,
                $token
            );
            
            if ($enviado) {
                $mensagem = 'Um e-mail com as instruções para redefinir sua senha foi enviado para o endereço informado.';
                $tipoMensagem = 'success';
            } else {
                $mensagem = 'Ocorreu um erro ao enviar o e-mail de redefinição. Por favor, tente novamente.';
                $tipoMensagem = 'danger';
            }
        } else {
            // Por segurança, não informamos se o e-mail existe ou não
            $mensagem = 'Se o e-mail estiver cadastrado, você receberá um link para redefinir sua senha.';
            $tipoMensagem = 'info';
        }
    }
}

// Processa o formulário de redefinição de senha
if ($isPostRequest && isset($_POST['redefinir_senha'])) {
    if (empty($novaSenha) || empty($confirmarSenha)) {
        $mensagem = 'Por favor, preencha todos os campos.';
        $tipoMensagem = 'danger';
    } elseif ($novaSenha !== $confirmarSenha) {
        $mensagem = 'As senhas não coincidem.';
        $tipoMensagem = 'danger';
    } elseif (strlen($novaSenha) < 8) {
        $mensagem = 'A senha deve ter no mínimo 8 caracteres.';
        $tipoMensagem = 'danger';
    } else {
        // Busca o usuário pelo token de redefinição
        $usuarios = Usuario::where('token_redefinicao', $token);
        $usuario = !empty($usuarios) ? $usuarios[0] : null;
        
        if ($usuario && $usuario->verificarTokenRedefinicao($token)) {
            // Define a nova senha usando o método setPassword
            $usuario->setPassword($novaSenha);
            // Limpa o token de redefinição
            $usuario->limparTokenRedefinicao();
            // Salva as alterações no banco de dados
            $usuario->save();
            
            $mensagem = 'Sua senha foi redefinida com sucesso! Agora você pode fazer login com a nova senha.';
            $tipoMensagem = 'success';
            $token = ''; // Limpa o token para não mostrar o formulário de nova senha
        } else {
            $mensagem = 'O link de redefinição é inválido ou expirou. Por favor, solicite um novo.';
            $tipoMensagem = 'danger';
            $token = ''; // Limpa o token para não mostrar o formulário de nova senha
        }
    }
}

// Se houver um token na URL, mostra o formulário de nova senha
$mostrarFormularioNovaSenha = !empty($token);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - DIME-SC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fc;
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .card {
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .card-header {
            background-color: #4e73df;
            color: white;
            border-radius: 1rem 1rem 0 0 !important;
        }
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2653d4;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card">
                    <div class="card-header text-center py-4">
                        <h4><i class="bi bi-shield-lock me-2"></i> Recuperar Senha</h4>
                    </div>
                    <div class="card-body p-5">
                        <?php if (!empty($mensagem)): ?>
                            <div class="alert alert-<?= $tipoMensagem ?> alert-dismissible fade show" role="alert">
                                <?= $mensagem ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($mostrarFormularioNovaSenha): ?>
                            <!-- Formulário para definir nova senha -->
                            <form method="POST" action="">
                                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                                <div class="mb-3">
                                    <label for="nova_senha" class="form-label">Nova Senha</label>
                                    <input type="password" class="form-control" id="nova_senha" name="nova_senha" required>
                                    <div class="form-text">Mínimo de 8 caracteres.</div>
                                </div>
                                <div class="mb-4">
                                    <label for="confirmar_senha" class="form-label">Confirmar Nova Senha</label>
                                    <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required>
                                </div>
                                <button type="submit" name="redefinir_senha" class="btn btn-primary w-100 py-2">
                                    <i class="bi bi-key me-2"></i> Redefinir Senha
                                </button>
                            </form>
                        <?php else: ?>
                            <!-- Formulário para solicitar redefinição -->
                            <p class="text-muted mb-4">Digite o e-mail da sua conta para receber um link de redefinição de senha.</p>
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="email" class="form-label">E-mail</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                                </div>
                                <button type="submit" name="solicitar_redefinicao" class="btn btn-primary w-100 py-2">
                                    <i class="bi bi-envelope me-2"></i> Enviar Link de Redefinição
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <div class="text-center mt-4">
                            <a href="index.php" class="text-decoration-none">
                                <i class="bi bi-arrow-left me-1"></i> Voltar para o login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
