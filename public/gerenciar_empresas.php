<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../src/app_classes.php';

use App\Core\DBConnection;
use App\Models\Usuario;
use App\Models\Empresa;
use App\Models\Contabilista;
use App\Models\DimeDeclaracao;

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$contabilista_id_session = $_SESSION['contabilista_id'];

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
$empresaToEdit = null;
$empresas = [];
$contabilistas = []; // Para o select de contabilistas
$estados = [
    'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT',
    'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO'
];

// --- Carregar Lista de Empresas ---
if ($user_role === 'admin') {
    $empresas = Empresa::all();
} else {
    $empresas = Empresa::where('contabilista_id', $contabilista_id_session);
}

// --- Carregar PDO Connection ---
$pdo = \App\Core\DBConnection::getInstance()->getConnection();

// --- Processar Ações (Adicionar, Editar, Inativar, Excluir) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Build empresaData array according to new database schema
    $empresaData = [
        'cnpj' => $_POST['cnpj'] ?? '',
        'razao_social' => $_POST['razao_social'] ?? '',
        'inscricao_estadual' => $_POST['inscricao_estadual'] ?? '',
        'endereco' => $_POST['endereco'] ?? '',
        'numero' => $_POST['numero'] ?? '',
        'complemento' => $_POST['complemento'] ?? '',
        'bairro' => $_POST['bairro'] ?? '',
        'municipio_id' => $_POST['municipio_id'] ?? null,
        'cep' => $_POST['cep'] ?? '',
        'telefone' => $_POST['telefone'] ?? '',
        'email' => $_POST['email'] ?? ''
    ];

    if ($user_role === 'admin') {
        $empresaData['contabilista_id'] = $_POST['contabilista_id'] ?? null;
    } else {
        $empresaData['contabilista_id'] = $contabilista_id_session; // Fixa o contabilista para não-admins
    }

    // Remove validation here - let the static methods handle it
    if ($action === 'add') {
        $newId = Empresa::insertEmpresa($empresaData);
        if ($newId) {
            header('Location: gerenciar_empresas.php?message=success&text=' . urlencode('Empresa adicionada com sucesso!'));
            exit;
        } else {
            $message = 'Erro ao adicionar empresa.';
            $message_type = 'error';
        }
    } elseif ($action === 'update') {
        $id = deobfuscateId($_POST['id'] ?? '');
        if (Empresa::updateEmpresa($id, $empresaData)) {
            header('Location: gerenciar_empresas.php?message=success&text=' . urlencode('Empresa atualizada com sucesso!'));
            exit;
        } else {
            $message = 'Erro ao atualizar empresa.';
            $message_type = 'error';
        }
    } elseif ($action === 'delete_or_inactivate') {
        $id = deobfuscateId($_POST['id_to_delete'] ?? '');
        
        // Check permissions for non-admin users
        if ($user_role !== 'admin') {
            $empresa = Empresa::find($id);
            if (!$empresa || $empresa->contabilista_id != $contabilista_id_session) {
                $message = 'Você não tem permissão para gerenciar esta empresa.';
                $message_type = 'error';
            } else {
                if (Empresa::deleteEmpresa($id)) {
                    header('Location: gerenciar_empresas.php?message=success&text=' . urlencode('Empresa excluída com sucesso!'));
                    exit;
                } else {
                    $message = 'Erro ao excluir empresa.';
                    $message_type = 'error';
                }
            }
        } else {
            if (Empresa::deleteEmpresa($id)) {
                header('Location: gerenciar_empresas.php?message=success&text=' . urlencode('Empresa excluída com sucesso!'));
                exit;
            } else {
                $message = 'Erro ao excluir empresa.';
                $message_type = 'error';
            }
        }
    } elseif ($action === 'update_quadros') {
        $empresa_id = $_POST['empresa_id'] ?? null;
        $quadros = $_POST['quadros'] ?? [];

        if (!$empresa_id) {
            $message = 'Empresa não selecionada.';
            $message_type = 'error';
        } else {
            // Verificar permissões para salvar
            if ($user_role !== 'admin') {
                $empresa = Empresa::find($empresa_id);
                if (!$empresa || $empresa->contabilista_id != $contabilista_id_session) {
                    $message = 'Você não tem permissão para configurar quadros desta empresa.';
                    $message_type = 'error';
                } else {
                    salvarConfiguracaoQuadros($empresa_id, $quadros, $pdo);
                }
            } else {
                salvarConfiguracaoQuadros($empresa_id, $quadros, $pdo);
            }
        }
    }
}
// --- Carregar Empresa para Edição (GET) ---
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = deobfuscateId($_GET['id']);
    $empresaToEdit = Empresa::find($id);

    // Verifica permissão para editar
    if (!$empresaToEdit || ($user_role !== 'admin' && $empresaToEdit->contabilista_id != $contabilista_id_session)) {
        $message = 'Empresa não encontrada ou você não tem permissão para editá-la.';
        $message_type = 'error';
        $empresaToEdit = null; // Garante que o formulário não será preenchido
    }
}

// --- Carregar Municípios ---
$stmt = $pdo->query("SELECT id, nome FROM t_municipios ORDER BY nome");
$municipios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Carregar Lista de Empresas ---
if ($user_role === 'admin') {
    $empresas = Empresa::all();
} else {
    $empresas = Empresa::where('contabilista_id', $contabilista_id_session);
}

// Função para logout (agora no layout, mas manter para o redirecionamento imediato)
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Load contabilistas list for admin users
if ($user_role === 'admin') {
    $contabilistas = Contabilista::all();
} else {
    $contabilistas = [];
}

$page_title = 'Gerenciar Empresas';
include '_header.php';

// Função para salvar configuração de quadros
function salvarConfiguracaoQuadros($empresa_id, $quadros, $pdo) {
    global $message, $message_type;
    
    try {
        // Primeiro, desativar todos os quadros para esta empresa
        $pdo->prepare("UPDATE dime_quadro_empresas SET ativo = 0 WHERE empresa_id = ?")->execute([$empresa_id]);

        // Depois, ativar apenas os quadros selecionados
        foreach ($quadros as $quadro_numero) {
            // Verificar se já existe
            $stmt = $pdo->prepare("SELECT id FROM dime_quadro_empresas WHERE empresa_id = ? AND quadro_numero = ?");
            $stmt->execute([$empresa_id, $quadro_numero]);
            
            if ($stmt->rowCount() > 0) {
                // Atualizar
                $pdo->prepare("UPDATE dime_quadro_empresas SET ativo = 1 WHERE empresa_id = ? AND quadro_numero = ?")
                     ->execute([$empresa_id, $quadro_numero]);
            } else {
                // Inserir novo
                $pdo->prepare("INSERT INTO dime_quadro_empresas (empresa_id, quadro_numero, ativo) VALUES (?, ?, 1)")
                     ->execute([$empresa_id, $quadro_numero]);
            }
        }

        $message = 'Configuração de quadros atualizada com sucesso!';
        $message_type = 'success';
    } catch (Exception $e) {
        $message = 'Erro ao atualizar configuração de quadros: ' . $e->getMessage();
        $message_type = 'error';
    }
}
?>

<div class="col-12">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Gerenciar Empresas</h1>
            <p class="text-muted mb-0">Cadastro e gerenciamento de empresas</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Voltar ao Dashboard
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

    <!-- Information Card -->
    <?php if (!$empresaToEdit): ?>
    <div class="alert alert-info d-flex align-items-start" role="alert">
        <i class="bi bi-info-circle-fill me-3 mt-1"></i>
        <div>
            <h6 class="alert-heading mb-2">Configuração de Quadros DIME</h6>
            <p class="mb-1">Para visualizar e configurar quais quadros estarão disponíveis para geração de declarações DIME de uma empresa, você deve:</p>
            <ol class="mb-1">
                <li>Clicar no botão <strong>"Editar"</strong> da empresa desejada</li>
                <li>No formulário de edição, clicar no botão <strong>"Configurar Quadros"</strong></li>
                <li>Marcar/desmarcar os quadros desejados ou adicionar novos</li>
                <li>Salvar as configurações</li>
            </ol>
            <small class="text-muted">Esta configuração permite personalizar a experiência de cada empresa no sistema DIME.</small>
        </div>
    </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4" id="empresaTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="empresas-tab" data-bs-toggle="tab" data-bs-target="#empresas" type="button" role="tab">
                <i class="bi bi-building me-2"></i>Gerenciar Empresas
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="empresaTabsContent">
        <!-- Empresas Tab -->
        <div class="tab-pane fade show active" id="empresas" role="tabpanel">
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-<?php echo $empresaToEdit ? 'pencil' : 'plus-circle'; ?> me-2"></i>
                <?php echo $empresaToEdit ? 'Editar Empresa' : 'Adicionar Nova Empresa'; ?>
            </h5>
        </div>
        <div class="card-body">
            <form action="gerenciar_empresas.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="<?php echo $empresaToEdit ? 'update' : 'add'; ?>">
                    <?php if ($empresaToEdit): ?>
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars(obfuscateId($empresaToEdit->id)); ?>">
                    <?php endif; ?>

                <div class="row g-3">
                    <?php if ($user_role === 'admin'): ?>
                        <div class="col-12">
                            <label for="contabilista_id" class="form-label">Contabilista *</label>
                            <div class="input-group">
                                <select class="form-select" id="contabilista_id" name="contabilista_id" required>
                                    <option value="">Selecione um contabilista</option>
                                    <?php foreach ($contabilistas as $contabilista): ?>
                                        <option value="<?php echo htmlspecialchars($contabilista->id); ?>"
                                            <?php echo ($empresaToEdit && $empresaToEdit->contabilista_id == $contabilista->id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($contabilista->nome); ?> (<?php echo htmlspecialchars($contabilista->cpf); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="gerenciar_contabilistas.php" class="btn btn-outline-primary" title="Cadastrar novo contabilista" target="_blank">
                                    <i class="bi bi-plus-circle me-1"></i>Novo Contabilista
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="contabilista_id" value="<?php echo htmlspecialchars($contabilista_id_session); ?>">
                    <?php endif; ?>

                    <div class="col-md-6">
                        <label for="razao_social" class="form-label">Razão Social *</label>
                        <input type="text" class="form-control" id="razao_social" name="razao_social" 
                               value="<?php echo htmlspecialchars($empresaToEdit->razao_social ?? ''); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="cnpj" class="form-label">CNPJ *</label>
                        <input type="text" class="form-control" id="cnpj" name="cnpj" 
                               value="<?php echo htmlspecialchars($empresaToEdit->cnpj ?? ''); ?>" 
                               placeholder="00.000.000/0000-00" maxlength="18" required>
                    </div>
                    <div class="col-md-2">
                        <label for="inscricao_estadual" class="form-label">Inscrição Estadual *</label>
                        <input type="text" class="form-control" id="inscricao_estadual" name="inscricao_estadual" 
                               value="<?php echo htmlspecialchars($empresaToEdit->inscricao_estadual ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="endereco" class="form-label">Endereço</label>
                        <input type="text" class="form-control" id="endereco" name="endereco" 
                               value="<?php echo htmlspecialchars($empresaToEdit->endereco ?? ''); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="numero" class="form-label">Número</label>
                        <input type="text" class="form-control" id="numero" name="numero" 
                               value="<?php echo htmlspecialchars($empresaToEdit->numero ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="complemento" class="form-label">Complemento</label>
                        <input type="text" class="form-control" id="complemento" name="complemento" 
                               value="<?php echo htmlspecialchars($empresaToEdit->complemento ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="bairro" class="form-label">Bairro</label>
                        <input type="text" class="form-control" id="bairro" name="bairro" 
                               value="<?php echo htmlspecialchars($empresaToEdit->bairro ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="municipio_id" class="form-label">Município *</label>
                        <select class="form-select" id="municipio_id" name="municipio_id" required>
                            <option value="">Selecione um município</option>
                            <?php foreach ($municipios as $municipio): ?>
                                <option value="<?php echo $municipio['id']; ?>" 
                                        <?php echo ($empresaToEdit && $empresaToEdit->municipio_id == $municipio['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($municipio['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="cep" class="form-label">CEP</label>
                        <input type="text" class="form-control" id="cep" name="cep" 
                               value="<?php echo htmlspecialchars($empresaToEdit->cep ?? ''); ?>" 
                               placeholder="00000-000" maxlength="9">
                    </div>
                    <div class="col-md-4">
                        <label for="telefone" class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="telefone" name="telefone" 
                               value="<?php echo htmlspecialchars($empresaToEdit->telefone ?? ''); ?>" 
                               placeholder="(00) 00000-0000">
                    </div>
                    <div class="col-md-4">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($empresaToEdit->email ?? ''); ?>">
                    </div>
                    <?php if ($empresaToEdit): ?>
                        <div class="col-md-4">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="ativo" <?php echo ($empresaToEdit->status == 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                                <option value="inativo" <?php echo ($empresaToEdit->status == 'inativo') ? 'selected' : ''; ?>>Inativo</option>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <?php if ($empresaToEdit): ?>
                        <div class="d-flex gap-2">
                            <a href="gerenciar_empresas.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle me-2"></i>Cancelar
                            </a>
                            <a href="configurar_quadros_empresa.php?empresa_id=<?php echo urlencode(obfuscateId($empresaToEdit->id)); ?>" 
                               class="btn btn-outline-info">
                                <i class="bi bi-gear me-1"></i>Configurar Quadros
                            </a>
                        </div>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-<?php echo $empresaToEdit ? 'check' : 'plus-circle'; ?> me-2"></i>
                        <?php echo $empresaToEdit ? 'Atualizar Empresa' : 'Adicionar Empresa'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Companies Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-building me-2"></i>Empresas Cadastradas
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($empresas)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-building text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">Nenhuma empresa cadastrada</h5>
                    <p class="text-muted">Comece adicionando a primeira empresa.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Empresa</th>
                                <th>CNPJ</th>
                                <th>Inscrição Estadual</th>
                                <?php if ($user_role === 'admin'): ?>
                                    <th>Contabilista</th>
                                <?php endif; ?>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($empresas as $empresa): ?>
                                <tr>
                                    <td>
                                        <div class="fw-medium"><?php echo htmlspecialchars($empresa->razao_social); ?></div>
                                        <?php if ($empresa->nome_fantasia): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($empresa->nome_fantasia); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code><?php echo htmlspecialchars($empresa->cnpj); ?></code>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($empresa->inscricao_estadual); ?></span>
                                    </td>
                                    <?php if ($user_role === 'admin'): ?>
                                        <td>
                                            <?php 
                                            $contabilista = $empresa->getContabilista();
                                            echo $contabilista ? htmlspecialchars($contabilista->nome) : '<span class="text-muted">N/A</span>';
                                            ?>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <?php
                                        $status = $empresa->status ?? 'inativo';
                                        $status_class = $status === 'ativo' ? 'bg-success' : 'bg-secondary';
                                        $status_text = ucfirst($status);
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="gerenciar_empresas.php?action=edit&id=<?php echo htmlspecialchars(obfuscateId($empresa->id)); ?>" 
                                               class="btn btn-outline-primary" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-<?php echo $empresa->hasDimeDeclarations() ? 'warning' : 'danger'; ?>" 
                                                    onclick="<?php echo $empresa->hasDimeDeclarations() ? 'inativarEmpresa' : 'excluirEmpresa'; ?>('<?php echo htmlspecialchars(obfuscateId($empresa->id)); ?>')" 
                                                    title="<?php echo $empresa->hasDimeDeclarations() ? 'Inativar' : 'Excluir'; ?>">
                                                <i class="bi bi-<?php echo $empresa->hasDimeDeclarations() ? 'pause-circle' : 'trash'; ?>"></i>
                                            </button>
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
</div>

<script>
function excluirEmpresa(id) {
    if (confirm('Tem certeza que deseja excluir esta empresa?\n\nEsta ação é irreversível.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'gerenciar_empresas.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_or_inactivate';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function inativarEmpresa(id) {
    if (confirm('Esta empresa possui declarações DIME.\n\nEla será INATIVADA em vez de excluída. Deseja continuar?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'gerenciar_empresas.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_or_inactivate';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Form validation and formatting
document.addEventListener('DOMContentLoaded', function() {
    // CNPJ formatting
    const cnpjInput = document.getElementById('cnpj');
    if (cnpjInput) {
        cnpjInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/^(\d{2})(\d)/, '$1.$2');
            value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
            value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
            value = value.replace(/(\d{4})(\d)/, '$1-$2');
            e.target.value = value;
        });
    }
    
    // CEP formatting
    const cepInput = document.getElementById('cep');
    if (cepInput) {
        cepInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/^(\d{5})(\d)/, '$1-$2');
            e.target.value = value;
        });
    }
    
    // Phone formatting
    const telefoneInput = document.getElementById('telefone');
    if (telefoneInput) {
        telefoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 10) {
                value = value.replace(/^(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
            } else {
                value = value.replace(/^(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{5})(\d)/, '$1-$2');
            }
            e.target.value = value;
        });
    }
});

</script>

<?php include '_footer.php'; ?>
