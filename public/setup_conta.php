<?php
// Carrega o autoload do Composer
require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../src/app_classes.php';

use App\Models\Contabilista;
use App\Models\Usuario;
use App\Core\DBConnection;

// Inicia a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    error_log('SETUP_CONTA: User not logged in, redirecting to index.php');
    header('Location: index.php');
    exit;
}

// Carrega o usuário atual
$usuario = Usuario::find((int)$_SESSION['user_id']);
error_log('SETUP_CONTA: Loaded user ID ' . $_SESSION['user_id']);
error_log('SETUP_CONTA: User role: ' . ($usuario ? $usuario->role : 'NULL'));
error_log('SETUP_CONTA: User primeiro_acesso: ' . ($usuario && isset($usuario->primeiro_acesso) ? $usuario->primeiro_acesso : 'NULL'));

// Verifica se o usuário é um operador
if (!$usuario || $usuario->role !== 'operador') {
    error_log('SETUP_CONTA: User is not operator or not found, redirecting to dashboard');
    header('Location: dashboard.php');
    exit;
}

// Se o usuário já concluiu o primeiro acesso, redireciona para o dashboard
if (isset($usuario->primeiro_acesso) && $usuario->primeiro_acesso == 0 && !empty($usuario->contabilista_id)) {
    error_log('SETUP_CONTA: User already completed setup, redirecting to dashboard');
    header('Location: dashboard.php');
    exit;
}

error_log('SETUP_CONTA: User allowed to access setup page');

$erros = [];
$sucesso = '';
$dados = [
    'nome' => '',
    'cpf' => '',
    'crc' => '',
    'email' => $usuario->email, // Pré-preenche com o email do usuário
    'telefone' => '',
    'nova_senha' => '',
    'confirmar_senha' => ''
];

// Processa o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Limpa e valida os dados do contabilista
    $dados['nome'] = trim($_POST['nome'] ?? '');
    $dados['cpf'] = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
    $dados['crc'] = trim($_POST['crc'] ?? '');
    $dados['email'] = trim($_POST['email'] ?? '');
    $dados['telefone'] = preg_replace('/[^0-9]/', '', $_POST['telefone'] ?? '');

    // Dados da senha
    $dados['nova_senha'] = $_POST['nova_senha'] ?? '';
    $dados['confirmar_senha'] = $_POST['confirmar_senha'] ?? '';

    // Validações do contabilista
    if (empty($dados['nome'])) {
        $erros[] = 'O campo Nome é obrigatório.';
    }

    if (empty($dados['cpf']) || !validaCPF($dados['cpf'])) {
        $erros[] = 'CPF inválido.';
    }

    if (empty($dados['crc'])) {
        $erros[] = 'O campo CRC é obrigatório.';
    }

    if (empty($dados['email']) || !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'E-mail inválido.';
    }

    // Validações da senha
    if (empty($dados['nova_senha'])) {
        $erros[] = 'A nova senha é obrigatória.';
    } elseif (strlen($dados['nova_senha']) < 6) {
        $erros[] = 'A senha deve ter pelo menos 6 caracteres.';
    }

    if ($dados['nova_senha'] !== $dados['confirmar_senha']) {
        $erros[] = 'As senhas não coincidem.';
    }

    if (empty($erros)) {
        try {
            // Inicia uma transação
            $pdo = DBConnection::getInstance()->getConnection();
            $pdo->beginTransaction();

            // Cria o contabilista
            $contabilista = new Contabilista();
            $contabilista->nome = $dados['nome'];
            $contabilista->cpf = $dados['cpf'];
            $contabilista->crc = $dados['crc'];
            $contabilista->email = $dados['email'];
            $contabilista->telefone = $dados['telefone'];

            if ($contabilista->save()) {
                // Atualiza a senha do usuário
                $usuario->setPassword($dados['nova_senha']);

                // Associa o contabilista ao usuário e marca primeiro acesso como concluído
                $usuario->contabilista_id = $contabilista->id;
                $usuario->primeiro_acesso = 0;

                if ($usuario->save()) {
                    $pdo->commit();
                    $_SESSION['success'] = 'Configuração da conta realizada com sucesso! Bem-vindo ao sistema DIME-SC.';
                    $_SESSION['contabilista_id'] = $contabilista->id;
                    header('Location: dashboard.php');
                    exit;
                } else {
                    throw new Exception('Falha ao atualizar o usuário.');
                }
            } else {
                throw new Exception('Falha ao salvar o contabilista.');
            }
        } catch (Exception $e) {
            if (isset($pdo)) {
                $pdo->rollBack();
            }
            $erros[] = 'Erro ao configurar a conta: ' . $e->getMessage();
        }
    }
}

// Função para validar CPF
function validaCPF($cpf) {
    // Elimina possível máscara
    $cpf = preg_replace('/[^0-9]/', '', $cpf);

    // Verifica se o número de dígitos informados é igual a 11
    if (strlen($cpf) != 11) {
        return false;
    }

    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }

    // Faz o cálculo para validar o CPF
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }

    return true;
}

// Inclui o cabeçalho
$page_title = 'Configuração da Conta - Sistema DIME-SC';
require_once '_header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-xl-10 col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h1 class="h4 mb-0 text-gray-800">Configuração da Conta</h1>
                    <p class="mb-0 text-muted">Complete seu cadastro para acessar o sistema</p>
                </div>
                <div class="card-body">
                    <?php if (!empty($erros)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($erros as $erro): ?>
                                    <li><?= htmlspecialchars($erro) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($sucesso)): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($sucesso) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="setup_conta.php">
                        <!-- Dados do Contabilista -->
                        <div class="mb-4">
                            <h5 class="mb-3">
                                <i class="bi bi-person-badge me-2"></i>Dados do Contabilista
                            </h5>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="nome" class="form-label">Nome Completo <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nome" name="nome" required
                                           value="<?= htmlspecialchars($dados['nome']) ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="cpf" class="form-label">CPF <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control cpf" id="cpf" name="cpf" required
                                           value="<?= htmlspecialchars($dados['cpf']) ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="crc" class="form-label">CRC <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="crc" name="crc" required
                                           value="<?= htmlspecialchars($dados['crc']) ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" required
                                           value="<?= htmlspecialchars($dados['email']) ?>" readonly>
                                    <div class="form-text">Este e-mail está vinculado à sua conta</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="telefone" class="form-label">Telefone</label>
                                    <input type="tel" class="form-control telefone" id="telefone" name="telefone"
                                           value="<?= htmlspecialchars($dados['telefone']) ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Troca de Senha -->
                        <div class="mb-4">
                            <h5 class="mb-3">
                                <i class="bi bi-key me-2"></i>Definição de Senha
                            </h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nova_senha" class="form-label">Nova Senha <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="nova_senha" name="nova_senha" required
                                           minlength="6">
                                    <div class="form-text">Mínimo de 6 caracteres</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirmar_senha" class="form-label">Confirmar Senha <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required
                                           minlength="6">
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-circle me-2"></i>Concluir Configuração
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script>
$(document).ready(function() {
    // Máscaras
    $('.cpf').mask('000.000.000-00', {reverse: true});
    $('.telefone').mask('(00) 00000-0000');

    // Validação visual das senhas
    $('#confirmar_senha').on('input', function() {
        var senha = $('#nova_senha').val();
        var confirmar = $(this).val();

        if (confirmar.length > 0) {
            if (senha === confirmar) {
                $(this).removeClass('is-invalid').addClass('is-valid');
            } else {
                $(this).removeClass('is-valid').addClass('is-invalid');
            }
        } else {
            $(this).removeClass('is-valid is-invalid');
        }
    });
});
</script>

<?php
// Inclui o rodapé
require_once '_footer.php';
?>
