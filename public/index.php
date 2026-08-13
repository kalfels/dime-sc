<?php
// Start session at the very beginning
session_start();

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Log session and POST data for debugging
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('Login attempt - POST data: ' . print_r($_POST, true));
    error_log('Session data: ' . print_r($_SESSION, true));
}

require_once '../src/app_classes.php'; // Inclui as classes do projeto
use App\Models\Usuario;

// Se o usuário já estiver logado, redireciona para o dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error_message = '';

// Processa o formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = 'Por favor, preencha todos os campos.';
    } else {
        $usuario = Usuario::findByEmail($email);

        if ($usuario) {
            error_log('User found in database. Email: ' . $usuario->email);
            error_log('Password verification result: ' . ($usuario->verifyPassword($password) ? 'success' : 'failed'));
            
            if ($usuario->verifyPassword($password)) {
                error_log('Password verification successful');
                
                // Debug: Log user data
                error_log('User data: ' . print_r([
                    'id' => $usuario->id,
                    'role' => $usuario->role,
                    'contabilista_id' => $usuario->contabilista_id,
                    'is_active' => $usuario->isActive(),
                    'is_payment_active' => $usuario->isPaymentActive(),
                    'primeiro_acesso' => isset($usuario->primeiro_acesso) ? $usuario->primeiro_acesso : 'NOT_SET',
                    'has_access' => $usuario->hasAccess()
                ], true));
                
                // Check if user is active
                if (!$usuario->isActive()) {
                    $error_message = 'Sua conta está desativada. Entre em contato com o administrador.';
                    error_log('Account is not active');
                }
                // Removed: Payment status check that was blocking login for operators
                // The check is now handled in auth.php with warnings instead of blocking
                /*
                elseif (!$usuario->isAdmin() && !$usuario->isPaymentActive()) {
                    $error_message = 'Seu acesso está bloqueado devido a pendências financeiras. Entre em contato com o suporte.';
                    error_log('Payment is not active');
                }
                */
                // Check if it's first access for operators
                elseif ($usuario->role === Usuario::ROLE_OPERADOR &&
                        isset($usuario->primeiro_acesso) &&
                        $usuario->primeiro_acesso == 1) {
                    error_log('FIRST ACCESS FLOW: User ' . $usuario->email . ' detected as first access');
                    error_log('FIRST ACCESS FLOW: primeiro_acesso = ' . $usuario->primeiro_acesso);
                    error_log('FIRST ACCESS FLOW: role = ' . $usuario->role);
                    error_log('FIRST ACCESS FLOW: Redirecting to setup_conta.php');

                    // First access - redirect to setup page
                    $_SESSION['user_id'] = $usuario->id;
                    $_SESSION['user_name'] = $usuario->nome;
                    $_SESSION['user_email'] = $usuario->email;
                    $_SESSION['user_role'] = $usuario->role;
                    $_SESSION['contabilista_id'] = $usuario->contabilista_id;
                    $_SESSION['is_admin'] = $usuario->isAdmin();

                    header('Location: setup_conta.php');
                    exit;
                }
                elseif (!empty($error_message)) {
                    // Just continue to show the error message
                    error_log('Login blocked by error: ' . $error_message);
                }
                else {
                    error_log('All checks passed - redirecting to dashboard');
                    // Set session data
                    $_SESSION['user_id'] = $usuario->id;
                    $_SESSION['user_name'] = $usuario->nome;
                    $_SESSION['user_email'] = $usuario->email;
                    $_SESSION['user_role'] = $usuario->role;
                    $_SESSION['contabilista_id'] = $usuario->contabilista_id;
                    $_SESSION['is_admin'] = $usuario->isAdmin();
                    
                    // Update last login timestamp
                    $pdo = App\Core\DBConnection::getInstance()->getConnection();
                    $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_login_at = ? WHERE id = ?");
                    $stmt->execute([date('Y-m-d H:i:s'), $usuario->id]);
                    
                    // All checks passed - redirect to dashboard
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                $error_message = 'E-mail ou senha inválidos. Verifique suas credenciais e tente novamente.';
                error_log('Invalid password for user: ' . $email);
            }
        } else {
            $error_message = 'E-mail ou senha inválidos. Verifique suas credenciais e tente novamente.';
            error_log('User not found: ' . $email);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema DIME-SC</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Privacy Modal CSS -->
    <link href="css/privacy-modal.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .card-header {
            background: #4a6cf7;
            color: white;
            text-align: center;
            padding: 20px;
            border-bottom: none;
        }
        .card-body {
            padding: 30px;
        }
        .form-control:focus {
            border-color: #4a6cf7;
            box-shadow: 0 0 0 0.25rem rgba(74, 108, 247, 0.25);
        }
        .btn-primary {
            background-color: #4a6cf7;
            border: none;
            padding: 10px 20px;
            font-weight: 500;
        }
        .btn-primary:hover {
            background-color: #3a5bd9;
        }
        .invalid-feedback {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card login-card">
                    <div class="card-header">
                        <h4 class="mb-0">Acesso ao Sistema</h4>
                        <p class="mb-0">Informe suas credenciais para continuar</p>
                    </div>
                    <div class="card-body">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                        <?php endif; ?>
                        
                        <form id="loginForm" method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">E-mail</label>
                                <input type="email" class="form-control" id="email" name="email">
                                <div class="invalid-feedback" style="display: none;">Por favor, informe um e-mail válido.</div>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Senha</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback" style="display: none;">Por favor, informe sua senha.</div>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Entrar</button>
                            </div>
                            <div class="text-center mt-3">
                                <a href="recuperar-senha.php" class="text-decoration-none">
                                    <i class="bi bi-question-circle me-1"></i>Esqueceu sua senha?
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="text-center text-white mt-3">
                    <p class="mb-0"> DIME-SC. Todos os direitos reservados.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Consentimento LGPD -->
    <div class="modal fade" id="privacyModal" tabindex="-1" aria-labelledby="privacyModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="privacyModalLabel">
                        <i class="bi bi-shield-check me-2"></i>Política de Privacidade e Cookies
                    </h5>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-2 text-center">
                            <i class="bi bi-info-circle-fill text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <div class="col-md-10">
                            <h6 class="fw-bold mb-3">Proteção dos seus dados conforme a LGPD</h6>
                            <p class="mb-3">
                                O <strong>Sistema DIME-SC</strong> respeita sua privacidade e está em conformidade com a 
                                Lei Geral de Proteção de Dados (LGPD). Utilizamos apenas cookies essenciais para o 
                                funcionamento da aplicação.
                            </p>
                            
                            <div class="alert alert-info mb-3">
                                <h6 class="alert-heading">
                                    <i class="bi bi-cookie me-1"></i>Cookies Utilizados:
                                </h6>
                                <ul class="mb-0">
                                    <li><strong>PHPSESSID:</strong> Cookie de sessão essencial (não requer consentimento)</li>
                                    <li><strong>Finalidade:</strong> Manter você logado e garantir a segurança</li>
                                    <li><strong>Duração:</strong> Até o fechamento do navegador</li>
                                </ul>
                            </div>

                            <div class="alert alert-success mb-3">
                                <h6 class="alert-heading">
                                    <i class="bi bi-check-circle me-1"></i>Não utilizamos:
                                </h6>
                                <ul class="mb-0">
                                    <li>Cookies de análise ou rastreamento</li>
                                    <li>Cookies de marketing ou publicidade</li>
                                    <li>Compartilhamento de dados com terceiros</li>
                                </ul>
                            </div>

                            <p class="mb-0">
                                Ao continuar, você concorda com nossa política de privacidade e o uso de cookies essenciais.
                                <a href="politica-privacidade.html" target="_blank" class="text-decoration-none">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>Leia a política completa
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="declineBtn">
                        <i class="bi bi-x-circle me-1"></i>Não Aceito
                    </button>
                    <button type="button" class="btn btn-primary" id="acceptBtn">
                        <i class="bi bi-check-circle me-1"></i>Aceito e Continuo
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Recusa -->
    <div class="modal fade" id="declineModal" tabindex="-1" aria-labelledby="declineModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="declineModalLabel">
                        <i class="bi bi-exclamation-triangle me-2"></i>Acesso Não Autorizado
                    </h5>
                </div>
                <div class="modal-body text-center">
                    <i class="bi bi-lock-fill text-warning" style="font-size: 3rem;"></i>
                    <h6 class="mt-3 mb-3">Não é possível acessar o sistema</h6>
                    <p class="mb-3">
                        Os cookies essenciais são necessários para o funcionamento básico e segurança do sistema.
                        Sem eles, não conseguimos garantir a proteção dos seus dados.
                    </p>
                    <p class="text-muted small">
                        Você pode fechar esta aba ou revisar nossa política de privacidade.
                    </p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-outline-primary" onclick="window.location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i>Revisar Política
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="window.close()">
                        <i class="bi bi-x-lg me-1"></i>Fechar Aba
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Gerenciamento de Consentimento LGPD
        function checkPrivacyConsent() {
            const consent = localStorage.getItem('dime_privacy_consent');
            const consentDate = localStorage.getItem('dime_consent_date');
            const currentDate = new Date().getTime();
            const oneYear = 365 * 24 * 60 * 60 * 1000; // 1 ano em millisegundos
            
            // Verifica se o consentimento existe e não expirou (1 ano)
            if (!consent || !consentDate || (currentDate - parseInt(consentDate)) > oneYear) {
                showPrivacyModal();
                return false;
            }
            return consent === 'accepted';
        }
        
        function showPrivacyModal() {
            const privacyModal = new bootstrap.Modal(document.getElementById('privacyModal'));
            privacyModal.show();
        }
        
        function acceptPrivacy() {
            const currentDate = new Date().getTime();
            localStorage.setItem('dime_privacy_consent', 'accepted');
            localStorage.setItem('dime_consent_date', currentDate.toString());
            
            // Registra o consentimento no servidor (opcional)
            fetch('ajax_privacy_consent.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'accept',
                    timestamp: currentDate,
                    user_agent: navigator.userAgent
                })
            }).catch(console.error);
            
            const privacyModal = bootstrap.Modal.getInstance(document.getElementById('privacyModal'));
            privacyModal.hide();
        }
        
        function declinePrivacy() {
            localStorage.setItem('dime_privacy_consent', 'declined');
            
            const privacyModal = bootstrap.Modal.getInstance(document.getElementById('privacyModal'));
            privacyModal.hide();
            
            // Mostra modal de recusa
            const declineModal = new bootstrap.Modal(document.getElementById('declineModal'));
            declineModal.show();
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Verifica consentimento ao carregar a página
            checkPrivacyConsent();
            
            // Event listeners para os botões de consentimento
            document.getElementById('acceptBtn').addEventListener('click', acceptPrivacy);
            document.getElementById('declineBtn').addEventListener('click', declinePrivacy);
            
            const form = document.getElementById('loginForm');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const togglePassword = document.getElementById('togglePassword');
            const passwordIcon = togglePassword.querySelector('i');

            // Toggle password visibility
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.type === 'password' ? 'text' : 'password';
                passwordInput.type = type;
                passwordIcon.className = type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
            });

            // Form submission handler
            form.addEventListener('submit', function(event) {
                // Always prevent default to handle validation ourselves
                event.preventDefault();
                
                let isValid = true;
                
                // Reset previous validation states
                emailInput.classList.remove('is-invalid');
                emailInput.nextElementSibling.style.display = 'none';
                passwordInput.classList.remove('is-invalid');
                passwordInput.closest('.input-group').nextElementSibling.style.display = 'none';
                
                // Validate email
                if (!emailInput.value) {
                    emailInput.classList.add('is-invalid');
                    emailInput.nextElementSibling.style.display = 'block';
                    isValid = false;
                } else if (!/\S+@\S+\.\S+/.test(emailInput.value)) {
                    emailInput.classList.add('is-invalid');
                    emailInput.nextElementSibling.style.display = 'block';
                    isValid = false;
                }
                
                // Validate password
                if (!passwordInput.value) {
                    passwordInput.classList.add('is-invalid');
                    passwordInput.closest('.input-group').nextElementSibling.style.display = 'block';
                    isValid = false;
                }
                
                // If validation passes, submit the form
                if (isValid) {
                    // Submit the form normally
                    form.submit();
                }
            });

            // Focus on email field
            emailInput.focus();
        });
    </script>
</body>
</html>
