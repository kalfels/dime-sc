<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../src/app_classes.php';

use App\Core\DBConnection;
use App\Models\Usuario;
use App\Models\Contabilista;

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Check user role and permissions
$isAdmin = ($user_role === 'admin');
$contabilista_id_session = $_SESSION['contabilista_id'] ?? null;

// Debug logs
error_log('=== GERENCIAR_CONTABILISTAS PAGE LOAD ===');
error_log('User ID: ' . $user_id);
error_log('User Role: ' . $user_role);
error_log('Is Admin: ' . ($isAdmin ? 'true' : 'false'));
error_log('Contabilista ID Session: ' . ($contabilista_id_session ?? 'NULL'));
error_log('GET params: ' . print_r($_GET, true));
error_log('======================================');

// Simple ID obfuscation functions for security
function obfuscateId($id) {
    return base64_encode(($id * 7) + 123);
}

function deobfuscateId($encoded) {
    $decoded = base64_decode($encoded);
    return is_numeric($decoded) ? (($decoded - 123) / 7) : 0;
}

$message = '';
$message_type = ''; // 'success' or 'error'
$contabilistaToEdit = null;
$contabilistas = [];
$estados = [
    'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT',
    'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO'
];

// --- Processar Ações (Adicionar, Editar, Excluir) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Verificar permissões
    if ($action === 'add' && !$isAdmin) {
        $message = 'Você não tem permissão para adicionar contabilistas.';
        $message_type = 'error';
        $action = ''; // Cancela a ação
    } elseif ($action === 'delete' && !$isAdmin) {
        $message = 'Você não tem permissão para excluir contabilistas.';
        $message_type = 'error';
        $action = ''; // Cancela a ação
    } elseif ($action === 'update') {
        // Verifica se o usuário tem permissão para editar este contabilista
        $id = deobfuscateId($_POST['id'] ?? '');
        $contabilista = Contabilista::find($id);
        
        if (!$isAdmin && (!$contabilista || $contabilista->id != $contabilista_id_session)) {
            $message = 'Você só pode editar seus próprios dados de contabilista.';
            $message_type = 'error';
            $action = ''; // Cancela a ação
        }
    }
    
    // Build data array - exclude CPF for updates
    $contabilistaData = [
        'nome' => $_POST['nome'] ?? '',
        'crc' => $_POST['crc'] ?? '',
        'endereco' => $_POST['endereco'] ?? '',
        'numero' => $_POST['numero'] ?? '',
        'complemento' => $_POST['complemento'] ?? '',
        'bairro' => $_POST['bairro'] ?? '',
        'cidade' => $_POST['cidade'] ?? '',
        'estado' => $_POST['estado'] ?? '',
        'cep' => $_POST['cep'] ?? '',
        'telefone' => $_POST['telefone'] ?? '',
        'email' => $_POST['email'] ?? ''
    ];
    
    // Only include CPF for new records (add action)
    if ($action === 'add') {
        $contabilistaData['cpf'] = $_POST['cpf'] ?? '';
    }

    // Convert "SN" to "0" for address number field
    if (strtoupper(trim($contabilistaData['numero'])) === 'SN') {
        $contabilistaData['numero'] = '0';
    }

    // Validação básica
    $requiredFieldsEmpty = empty($contabilistaData['nome']) || empty($contabilistaData['email']);
    if ($action === 'add') {
        $requiredFieldsEmpty = $requiredFieldsEmpty || empty($contabilistaData['cpf']);
    }
    
    if ($requiredFieldsEmpty) {
        $message = 'Campos obrigatórios (Nome, ' . ($action === 'add' ? 'CPF, ' : '') . 'Email) não preenchidos.';
        $message_type = 'error';
    } else {
        // Check for duplicate CPF/email
        $duplicateError = false;
        $pdo = DBConnection::getInstance()->getConnection();
        
        if ($action === 'add') {
            // Check for existing CPF
            $stmt = $pdo->prepare("SELECT id FROM contabilistas WHERE cpf = ?");
            $stmt->execute([$contabilistaData['cpf']]);
            if ($stmt->fetch()) {
                $message = 'Já existe um contabilista cadastrado com este CPF.';
                $message_type = 'error';
                $duplicateError = true;
            }
            
            // Check for existing email
            if (!$duplicateError) {
                $stmt = $pdo->prepare("SELECT id FROM contabilistas WHERE email = ?");
                $stmt->execute([$contabilistaData['email']]);
                if ($stmt->fetch()) {
                    $message = 'Já existe um contabilista cadastrado com este email.';
                    $message_type = 'error';
                    $duplicateError = true;
                }
            }
        } elseif ($action === 'update') {
            $id = deobfuscateId($_POST['id'] ?? '');
            // Only check email for duplicates during update (CPF is not changeable)
            $stmt = $pdo->prepare("SELECT id FROM contabilistas WHERE email = ? AND id != ?");
            $stmt->execute([$contabilistaData['email'], $id]);
            if ($stmt->fetch()) {
                $message = 'Já existe outro contabilista cadastrado com este email.';
                $message_type = 'error';
                $duplicateError = true;
            }
        }

        if (!$duplicateError) {
            try {
                if ($action === 'add') {
                    $newId = Contabilista::insertContabilista($contabilistaData);
                    if ($newId) {
                        // Redireciona para evitar re-submissão do formulário
                        header('Location: gerenciar_contabilistas.php?message=success&text=' . urlencode('Contabilista adicionado com sucesso!'));
                        exit;
                    } else {
                        $message = 'Erro ao adicionar contabilista.';
                        $message_type = 'error';
                    }
                } elseif ($action === 'update') {
                    if (Contabilista::updateContabilista($id, $contabilistaData)) {
                        // Redireciona para evitar re-submissão do formulário
                        header('Location: gerenciar_contabilistas.php?message=success&text=' . urlencode('Contabilista atualizado com sucesso!'));
                        exit;
                    } else {
                        $message = 'Erro ao atualizar contabilista.';
                        $message_type = 'error';
                    }
                }
            } catch (Exception $e) {
                $message = 'Erro: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    if ($action === 'delete') {
        try {
            $id = deobfuscateId($_POST['id_to_delete'] ?? '');
            
            // Verifica se o contabilista existe
            $contabilista = Contabilista::find($id);
            if (!$contabilista) {
                throw new Exception('Contabilista não encontrado.');
            }
            
            // Verifica se o usuário é administrador
            if (!$isAdmin) {
                throw new Exception('Você não tem permissão para excluir contabilistas.');
            }
            
            if (Contabilista::deleteContabilista($id)) {
                $message = 'Contabilista excluído com sucesso!';
                $message_type = 'success';
            } else {
                throw new Exception('Erro ao excluir contabilista.');
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $message_type = 'error';
        }
    }
}

// --- Carregar Contabilistas ---
$contabilistas = [];
try {
    $pdo = DBConnection::getInstance()->getConnection();
    
    if ($isAdmin) {
        // Admin pode ver todos os contabilistas
        $sql = "SELECT * FROM contabilistas ORDER BY nome";
        $stmt = $pdo->query($sql);
    } else {
        // Operador só pode ver seu próprio contabilista
        $sql = "SELECT * FROM contabilistas WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $contabilista_id_session, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    $contabilistas = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    $message = 'Erro ao carregar contabilistas: ' . $e->getMessage();
    $message_type = 'error';
}

// --- Carregar Contabilista para Edição (GET ou automático para operadores) ---
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = deobfuscateId($_GET['id']);
    $contabilistaToEdit = Contabilista::find($id);

    if (!$contabilistaToEdit) {
        $message = 'Contabilista não encontrado.';
        $message_type = 'error';
    } elseif (!$isAdmin && $contabilistaToEdit->id != $contabilista_id_session) {
        // Operador só pode editar seu próprio contabilista
        $message = 'Você não tem permissão para editar este contabilista.';
        $message_type = 'error';
        $contabilistaToEdit = null;
    }
} elseif (!$isAdmin && !$contabilistaToEdit) {
    // Para operadores sem parâmetros específicos, carregar automaticamente seus dados
    if ($contabilista_id_session) {
        $contabilistaToEdit = Contabilista::find($contabilista_id_session);
        if (!$contabilistaToEdit) {
            $message = 'Seus dados de contabilista não foram encontrados.';
            $message_type = 'error';
        }
    } else {
        // Operador sem contabilista - mostrar formulário de adição
        // $contabilistaToEdit permanece null, será tratado na interface
    }
}

$page_title = $isAdmin ? 'Gerenciar Contabilistas' : 'Meus Dados de Contabilista';
include '_header.php';
?>

<div class="col-12">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">
                <?php echo $isAdmin ? 'Gerenciar Contabilistas' : 'Meus Dados de Contabilista'; ?>
            </h1>
            <p class="text-muted mb-0">
                <?php echo $isAdmin ? 'Cadastro e gerenciamento de contabilistas' : 'Atualize suas informações de contabilista'; ?>
            </p>
        </div>
        <?php if ($isAdmin): ?>
        <a href="gerenciar_contabilistas.php?action=add" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Adicionar Novo
        </a>
        <?php endif; ?>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> d-flex align-items-center" role="alert">
            <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?> me-2"></i>
            <div><?php echo htmlspecialchars($message); ?></div>
        </div>
    <?php endif; ?>

    <!-- Info Message for Operators -->
    <?php if (!$isAdmin): ?>
    <div class="alert alert-info d-flex align-items-center" role="alert">
        <i class="bi bi-info-circle-fill me-2"></i>
        <div>
            <?php if (!$contabilista_id_session): ?>
                <strong>Primeiro Acesso:</strong> Complete o cadastro dos seus dados de contabilista para continuar usando o sistema.
            <?php else: ?>
                <strong>Meus Dados:</strong> Aqui você pode atualizar suas informações de contabilista. Todas as alterações são salvas automaticamente.
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Form Section -->
    <?php if ($isAdmin || (!$isAdmin && ($contabilistaToEdit || (!isset($_GET['action']) || $_GET['action'] !== 'edit')))): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-<?php echo $contabilistaToEdit ? 'pencil' : 'plus-circle'; ?> me-2"></i>
                <?php
                if ($isAdmin) {
                    echo $contabilistaToEdit ? 'Editar Contabilista' : 'Adicionar Novo Contabilista';
                } else {
                    echo $contabilistaToEdit ? 'Editar Meus Dados' : 'Cadastrar Meus Dados';
                }
                ?>
            </h5>
        </div>
        <div class="card-body">
            <form action="gerenciar_contabilistas.php" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="<?php echo $contabilistaToEdit ? 'update' : 'add'; ?>">
                <?php if ($contabilistaToEdit): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars(obfuscateId($contabilistaToEdit->id)); ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nome" class="form-label">Nome *</label>
                        <input type="text" class="form-control" id="nome" name="nome" 
                               value="<?php echo htmlspecialchars($contabilistaToEdit->nome ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="cpf" class="form-label">CPF *</label>
                        <input type="text" class="form-control" id="cpf" 
                               <?php echo $contabilistaToEdit ? '' : 'name="cpf"'; ?>
                               value="<?php echo htmlspecialchars($contabilistaToEdit->cpf ?? ''); ?>" 
                               placeholder="000.000.000-00" maxlength="14" 
                               <?php echo $contabilistaToEdit ? 'readonly' : 'required'; ?>>
                        <?php if ($contabilistaToEdit): ?>
                            <div class="form-text">CPF não pode ser alterado após o cadastro</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label for="crc" class="form-label">CRC</label>
                        <input type="text" class="form-control" id="crc" name="crc" 
                               value="<?php echo htmlspecialchars($contabilistaToEdit->crc ?? ''); ?>" 
                               placeholder="SC-000000/O-0">
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">E-mail *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($contabilistaToEdit->email ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="telefone" class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="telefone" name="telefone" 
                               value="<?php echo htmlspecialchars($contabilistaToEdit->telefone ?? ''); ?>" 
                               placeholder="(00) 00000-0000">
                    </div>
                    <div class="col-md-6">
                        <label for="endereco" class="form-label">Endereço</label>
                        <input type="text" class="form-control" id="endereco" name="endereco" 
                               value="<?php echo htmlspecialchars($contabilistaToEdit->endereco ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="numero" class="form-label">Número</label>
                        <input type="text" class="form-control" id="numero" name="numero" 
                               value="<?php echo htmlspecialchars($contabilistaToEdit->numero ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="complemento" class="form-label">Complemento</label>
                        <input type="text" class="form-control" id="complemento" name="complemento" 
                               value="<?php echo htmlspecialchars($contabilistaToEdit->complemento ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="bairro" class="form-label">Bairro</label>
                        <input type="text" class="form-control" id="bairro" name="bairro" 
                               value="<?php echo htmlspecialchars($contabilistaToEdit->bairro ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="cidade" class="form-label">Cidade</label>
                        <input type="text" class="form-control" id="cidade" name="cidade" 
                               value="<?php echo htmlspecialchars($contabilistaToEdit->cidade ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="">Selecione um estado</option>
                            <?php foreach ($estados as $estado): ?>
                                <option value="<?php echo htmlspecialchars($estado); ?>"
                                    <?php echo ($contabilistaToEdit && $contabilistaToEdit->estado == $estado) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($estado); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="cep" class="form-label">CEP</label>
                        <input type="text" class="form-control" id="cep" name="cep" 
                               value="<?php echo htmlspecialchars($contabilistaToEdit->cep ?? ''); ?>" 
                               placeholder="00000-000" maxlength="9">
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <?php if ($contabilistaToEdit): ?>
                        <a href="gerenciar_contabilistas.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-2"></i>Cancelar
                        </a>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-<?php echo $contabilistaToEdit ? 'check' : 'plus-circle'; ?> me-2"></i>
                        <?php
                        if ($isAdmin) {
                            echo $contabilistaToEdit ? 'Atualizar Contabilista' : 'Adicionar Contabilista';
                        } else {
                            echo $contabilistaToEdit ? 'Atualizar Meus Dados' : 'Cadastrar Meus Dados';
                        }
                        ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Contabilistas Table -->
    <?php if ($isAdmin): ?>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-people me-2"></i>Contabilistas Cadastrados
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($contabilistas)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-person-badge text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">Nenhum contabilista cadastrado</h5>
                    <p class="text-muted">Comece adicionando o primeiro contabilista.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nome</th>
                                <th>CPF</th>
                                <th>CRC</th>
                                <th>E-mail</th>
                                <th>Telefone</th>
                                <th>Cidade/UF</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contabilistas as $contabilista): ?>
                                <tr>
                                    <td>
                                        <div class="fw-medium"><?php echo htmlspecialchars($contabilista->nome); ?></div>
                                    </td>
                                    <td>
                                        <code><?php echo htmlspecialchars($contabilista->cpf); ?></code>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($contabilista->crc ?? 'N/A'); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($contabilista->email): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($contabilista->email); ?>">
                                                <?php echo htmlspecialchars($contabilista->email); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($contabilista->telefone): ?>
                                            <a href="tel:<?php echo preg_replace('/\D/', '', $contabilista->telefone); ?>">
                                                <?php echo htmlspecialchars($contabilista->telefone); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($contabilista->cidade ?? 'N/A'); ?>/<?php echo htmlspecialchars($contabilista->estado ?? 'N/A'); ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($isAdmin || $contabilista->id == $contabilista_id_session): ?>
                                            <a href="gerenciar_contabilistas.php?action=edit&id=<?php echo htmlspecialchars(obfuscateId($contabilista->id)); ?>" 
                                               class="btn btn-outline-primary" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($isAdmin): ?>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="excluirContabilista('<?php echo htmlspecialchars(obfuscateId($contabilista->id)); ?>')" 
                                                    title="Excluir" <?php echo ($contabilista->id == $contabilista_id_session) ? 'disabled' : ''; ?>>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Info Message for Operators -->
    <?php if (!$isAdmin): ?>
    <div class="alert alert-info d-flex align-items-center" role="alert">
        <i class="bi bi-info-circle-fill me-2"></i>
        <div>
            <strong>Informação:</strong> Você foi redirecionado automaticamente para editar seus dados de contabilista.
            Caso precise atualizar suas informações, utilize o formulário acima.
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
console.log('[ManageAccountants]', 'Page loaded');

function excluirContabilista(id) {
    if (confirm('Tem certeza que deseja excluir este contabilista?\n\nEsta ação é irreversível e pode afetar empresas e usuários vinculados.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'gerenciar_contabilistas.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id_to_delete';
        idInput.value = id;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Form validation and formatting
document.addEventListener('DOMContentLoaded', function() {
    // CPF formatting
    const cpfInput = document.getElementById('cpf');
    if (cpfInput) {
        cpfInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            }
            e.target.value = value;
        });
    }
    
    // Phone formatting
    const phoneInput = document.getElementById('telefone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
            }
            e.target.value = value;
        });
    }
    
    // CEP formatting
    const cepInput = document.getElementById('cep');
    if (cepInput) {
        cepInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 8) {
                value = value.replace(/(\d{5})(\d{3})/, '$1-$2');
            }
            e.target.value = value;
        });
    }
    
    // Form validation
    const form = document.querySelector('.needs-validation');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    }
});
</script>

<?php include '_footer.php'; ?>