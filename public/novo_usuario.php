<?php
// Carrega o autoload do Composer
require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../src/app_classes.php';

use App\Models\Usuario;
use App\Models\Contabilista;
use App\Services\EmailService;

// Inicia a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usuário está logado e é administrador
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Carrega o usuário atual
$usuario = Usuario::find((int)$_SESSION['user_id']);

// Verifica se o usuário é administrador
if (!$usuario || !$usuario->isAdmin()) {
    $_SESSION['error'] = 'Acesso não autorizado. Apenas administradores podem acessar esta página.';
    header('Location: dashboard.php');
    exit;
}

$action = $_POST['action'] ?? '';

// Processa o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'operador';
    $status = $_POST['status'] ?? 'pendente';
    $status_pagamento = $_POST['status_pagamento'] ?? 'bloqueado';
    
    // Validações
    $erros = [];
    
    if (empty($nome)) {
        $erros[] = 'O campo Nome é obrigatório.';
    }
    
    if (empty($email)) {
        $erros[] = 'O campo E-mail é obrigatório.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'O e-mail informado não é válido.';
    } elseif (Usuario::findByEmail($email)) {
        $erros[] = 'Este e-mail já está em uso.';
    }
    
    if (empty($password)) {
        $erros[] = 'O campo Senha é obrigatório.';
    } elseif (strlen($password) < 6) {
        $erros[] = 'A senha deve ter no mínimo 6 caracteres.';
    }
    
    if (empty($erros)) {
        try {
            $novoUsuario = new Usuario();
            $novoUsuario->nome = $nome;
            $novoUsuario->email = $email;
            $novoUsuario->senha_hash = password_hash($password, PASSWORD_DEFAULT);
            $novoUsuario->role = $role;
            $novoUsuario->status = $status;
            $novoUsuario->status_pagamento = $status_pagamento;
            
            if ($novoUsuario->save()) {
                // Envia e-mail de boas-vindas
                $emailEnviado = false;
                try {
                    if (!class_exists('App\Services\EmailService')) {
                        throw new Exception('A classe EmailService não foi encontrada.');
                    }
                    
                    $emailService = new App\Services\EmailService();
                    $resultado = $emailService->enviarEmailBoasVindas(
                        $novoUsuario->email,
                        $novoUsuario->nome,
                        $novoUsuario->email,
                        $password // Senha em texto puro (apenas neste momento)
                    );
                    
                    if ($resultado) {
                        $emailEnviado = true;
                        $_SESSION['success'] = 'Usuário criado com sucesso! Um e-mail com as instruções de acesso foi enviado para o endereço informado.';
                    } else {
                        throw new Exception('O método enviarEmailBoasVindas retornou falso.');
                    }
                } catch (Exception $e) {
                    // Se houver erro no envio do e-mail, apenas continua
                    $_SESSION['success'] = 'Usuário criado com sucesso! No entanto, não foi possível enviar o e-mail de boas-vindas.';
                }
            
                header('Location: gerenciar_usuarios.php');
                exit;
            } else {
                throw new Exception('Falha ao salvar o usuário no banco de dados.');
            }
        } catch (Exception $e) {
            $erros[] = 'Erro ao salvar o usuário: ' . $e->getMessage();
        }
    }
}

// Inclui o cabeçalho
require_once '_header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Novo Usuário</h1>
        <a href="gerenciar_usuarios.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Voltar
        </a>
    </div>
    
    <?php if (!empty($erros)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($erros as $erro): ?>
                    <li><?= htmlspecialchars($erro) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="POST" action="novo_usuario.php">
                <input type="hidden" name="action" value="create">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="nome" class="form-label">Nome Completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nome" name="nome" required 
                                   value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="password" class="form-label">Senha <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required
                                       minlength="6">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">Mínimo de 6 caracteres</small>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="role" class="form-label">Função</label>
                            <select class="form-select" id="role" name="role">
                                <option value="admin" <?= (($_POST['role'] ?? '') === 'admin' ? 'selected' : '') ?>>Administrador</option>
                                <option value="operador" <?= (!isset($_POST['role']) || ($_POST['role'] ?? '') === 'operador' ? 'selected' : '') ?>>Operador</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="ativo" <?= (($_POST['status'] ?? '') === 'ativo' ? 'selected' : '') ?>>Ativo</option>
                                <option value="inativo" <?= (($_POST['status'] ?? '') === 'inativo' ? 'selected' : '') ?>>Inativo</option>
                                <option value="pendente" <?= (!isset($_POST['status']) || ($_POST['status'] ?? '') === 'pendente' ? 'selected' : '') ?>>Pendente</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="status_pagamento" class="form-label">Status de Pagamento</label>
                            <select class="form-select" id="status_pagamento" name="status_pagamento">
                                <option value="ativo" <?= (($_POST['status_pagamento'] ?? '') === 'ativo' ? 'selected' : '') ?>>Ativo</option>
                                <option value="pendente" <?= (($_POST['status_pagamento'] ?? '') === 'pendente' ? 'selected' : '') ?>>Pendente</option>
                                <option value="bloqueado" <?= (!isset($_POST['status_pagamento']) || ($_POST['status_pagamento'] ?? '') === 'bloqueado' ? 'selected' : '') ?>>Bloqueado</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="gerenciar_usuarios.php" class="btn btn-secondary me-md-2">
                        <i class="bi bi-x-circle me-1"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Salvar Usuário
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Mostrar/ocultar senha
document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const icon = this.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
});
</script>

<?php
// Inclui o rodapé
require_once '_footer.php';
?>
