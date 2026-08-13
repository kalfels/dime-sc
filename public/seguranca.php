<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../src/app_classes.php';

use App\Models\Usuario;

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Get current user data
error_log('=== LOADING USER FOR SECURITY ===');
error_log('Session user_id: ' . ($_SESSION['user_id'] ?? 'not set'));

// Force a fresh load of the user from the database
$usuario = Usuario::find((int)$_SESSION['user_id']);
if (!$usuario) {
    error_log('User not found in database');
    header('Location: logout.php');
    exit;
}

// Ensure the ID is properly set using the model's setter
if (!isset($usuario->id)) {
    $usuario->id = (int)$_SESSION['user_id'];
}

error_log('Loaded user ID: ' . ($usuario->id ?? 'not set'));
error_log('User email: ' . ($usuario->email ?? 'not set'));

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        // Change password
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password)) {
            $message = 'Por favor, informe sua senha atual.';
            $message_type = 'danger';
        } elseif (empty($new_password) || empty($confirm_password)) {
            $message = 'Por favor, preencha todos os campos de senha.';
            $message_type = 'danger';
        } elseif ($new_password !== $confirm_password) {
            $message = 'As novas senhas não coincidem.';
            $message_type = 'danger';
        } elseif (strlen($new_password) < 6) {
            $message = 'A nova senha deve ter pelo menos 6 caracteres.';
            $message_type = 'danger';
        } elseif (!$usuario->verifyPassword($current_password)) {
            $message = 'A senha atual está incorreta.';
            $message_type = 'danger';
        } else {
            // Update password
            $usuario->setPassword($new_password);
            if ($usuario->save()) {
                $message = 'Senha alterada com sucesso!';
                $message_type = 'success';

                // Log the password change
                error_log('Password changed for user: ' . $usuario->email . ' at ' . date('Y-m-d H:i:s'));
            } else {
                $message = 'Erro ao alterar a senha. Por favor, tente novamente.';
                $message_type = 'danger';
            }
        }
    }
}

$page_title = 'Segurança';
include '_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">Segurança</h1>
                    <p class="text-muted mb-0">Gerencie sua senha e configurações de segurança</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Voltar ao Dashboard
                    </a>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> d-flex align-items-center" role="alert">
                    <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?> me-2"></i>
                    <div><?php echo htmlspecialchars($message); ?></div>
                </div>
            <?php endif; ?>

            <!-- Password Change Section -->
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-key-fill me-2"></i>Alterar Senha
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="seguranca.php" id="passwordForm">
                                <input type="hidden" name="action" value="change_password">

                                <!-- Current Password -->
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">
                                        <i class="bi bi-lock me-1"></i>Senha Atual <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="current_password" name="current_password" required
                                               placeholder="Digite sua senha atual">
                                        <button class="btn btn-outline-secondary" type="button" id="toggleCurrentPassword">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- New Password -->
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">
                                        <i class="bi bi-shield-lock me-1"></i>Nova Senha <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="new_password" name="new_password" required
                                               minlength="6" placeholder="Mínimo de 6 caracteres">
                                        <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">
                                        Use uma combinação de letras maiúsculas, minúsculas, números e caracteres especiais.
                                    </div>
                                </div>

                                <!-- Confirm New Password -->
                                <div class="mb-4">
                                    <label for="confirm_password" class="form-label">
                                        <i class="bi bi-shield-check me-1"></i>Confirmar Nova Senha <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required
                                               minlength="6" placeholder="Digite a nova senha novamente">
                                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-key me-2"></i>Alterar Senha
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Security Tips -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-shield-check me-2"></i>Dicas de Segurança
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="list-unstyled">
                                        <li class="mb-2">
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                            Use senhas fortes e únicas
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                            Não compartilhe sua senha
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                            Alterne sua senha periodicamente
                                        </li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="list-unstyled">
                                        <li class="mb-2">
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                            Use combinação de caracteres
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                            Evite senhas óbvias
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                            Mantenha-se logado apenas quando necessário
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Account Info -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-info-circle me-2"></i>Informações da Conta
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm-6">
                                    <strong>Usuário:</strong><br>
                                    <?php echo htmlspecialchars($usuario->nome); ?>
                                </div>
                                <div class="col-sm-6">
                                    <strong>E-mail:</strong><br>
                                    <?php echo htmlspecialchars($usuario->email); ?>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-sm-6">
                                    <strong>Tipo de Conta:</strong><br>
                                    <span class="badge bg-<?php echo $usuario->isAdmin() ? 'danger' : 'primary'; ?>">
                                        <?php echo $usuario->isAdmin() ? 'Administrador' : 'Operador'; ?>
                                    </span>
                                </div>
                                <div class="col-sm-6">
                                    <strong>Último Acesso:</strong><br>
                                    <?php echo $usuario->ultimo_login_at ? date('d/m/Y H:i', strtotime($usuario->ultimo_login_at)) : 'Nunca'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password visibility toggles
    const toggleButtons = ['toggleCurrentPassword', 'toggleNewPassword', 'toggleConfirmPassword'];
    const passwordFields = ['current_password', 'new_password', 'confirm_password'];

    toggleButtons.forEach((buttonId, index) => {
        const button = document.getElementById(buttonId);
        const field = document.getElementById(passwordFields[index]);
        const icon = button.querySelector('i');

        button.addEventListener('click', function() {
            const type = field.type === 'password' ? 'text' : 'password';
            field.type = type;
            icon.className = type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
        });
    });

    // Form validation
    const form = document.getElementById('passwordForm');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');

    form.addEventListener('submit', function(e) {
        // Check if passwords match
        if (newPassword.value !== confirmPassword.value) {
            e.preventDefault();
            confirmPassword.setCustomValidity('As senhas não coincidem');
            confirmPassword.reportValidity();
        } else {
            confirmPassword.setCustomValidity('');
        }
    });

    // Real-time password matching validation
    confirmPassword.addEventListener('input', function() {
        if (newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('As senhas não coincidem');
        } else {
            confirmPassword.setCustomValidity('');
        }
    });

    // Password strength indicator (optional enhancement)
    newPassword.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;

        if (password.length >= 6) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;

        // Remove previous strength classes
        this.classList.remove('password-weak', 'password-medium', 'password-strong');

        // Add strength class
        if (strength <= 2) {
            this.classList.add('password-weak');
        } else if (strength <= 4) {
            this.classList.add('password-medium');
        } else {
            this.classList.add('password-strong');
        }
    });
});
</script>

<style>
.password-weak {
    border-color: #dc3545 !important;
}

.password-medium {
    border-color: #ffc107 !important;
}

.password-strong {
    border-color: #198754 !important;
}

.card-header.bg-primary {
    background: linear-gradient(135deg, #0d6efd 0%, #0056b3 100%) !important;
}

.btn-outline-secondary:hover {
    background-color: #6c757d;
    border-color: #6c757d;
    color: white;
}
</style>

<?php include '_footer.php'; ?>
