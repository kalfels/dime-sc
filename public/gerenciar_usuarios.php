<?php
require_once __DIR__ . '/../src/app_classes.php';

use App\Models\Usuario;

// Inicia a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Carrega o usuário atual
try {
    $usuario = new Usuario();
    $usuario = $usuario->find((int)$_SESSION['user_id']);
    
    // Verifica se o usuário existe e é admin
    if (!$usuario || !$usuario->isAdmin()) {
        $_SESSION['error'] = 'Acesso não autorizado. Apenas administradores podem acessar esta página.';
        error_log("Tentativa de acesso negado - Usuário ID: " . ($usuario ? $usuario->id : 'não encontrado') . 
                 ", Role: " . ($usuario ? $usuario->role : 'não definido') .
                 ", isAdmin: " . ($usuario && $usuario->isAdmin() ? 'sim' : 'não'));
        header('Location: dashboard.php');
        exit;
    }
} catch (Exception $e) {
    // Log do erro
    error_log("Erro ao verificar permissões do usuário: " . $e->getMessage());
    $_SESSION['error'] = 'Ocorreu um erro ao verificar suas permissões. Por favor, tente novamente.';
    header('Location: dashboard.php');
    exit;
}

// Processa a ação solicitada
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Lida com as diferentes ações
switch ($action) {
    case 'new':
        // Redireciona para a nova página de cadastro de usuário
        header('Location: novo_usuario.php');
        exit;
        
    case 'edit':
        if (!$id) {
            $_SESSION['error'] = 'ID do usuário não fornecido.';
            header('Location: gerenciar_usuarios.php');
            exit;
        }
        
        $usuarioModel = new Usuario();
        $userToEdit = $usuarioModel->find((int)$id);
        if (!$userToEdit) {
            $_SESSION['error'] = 'Usuário não encontrado.';
            header('Location: gerenciar_usuarios.php');
            exit;
        }
        
        // Processa o formulário se for uma requisição POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'operador';
            $status = $_POST['status'] ?? 'pendente';
            $status_pagamento = $_POST['status_pagamento'] ?? 'bloqueado';
            $acesso_dcip = isset($_POST['acesso_dcip']) ? 1 : 0;
            $errors = [];
            
            // Validações
            if (empty($nome)) $errors[] = 'O nome é obrigatório.';
            if (empty($email)) {
                $errors[] = 'O e-mail é obrigatório.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'O e-mail informado não é válido.';
            }
            
            if (empty($errors)) {
                // Busca usuário com o mesmo email e ID diferente
                $usuarioModel = new Usuario();
                $existingUsers = $usuarioModel->where('email', $email);
                $existingUser = null;
                if (is_array($existingUsers)) {
                    foreach ($existingUsers as $user) {
                        if ($user->id != $id) {
                            $existingUser = $user;
                            break;
                        }
                    }
                }
                if ($existingUser) {
                    $errors[] = 'Este e-mail já está em uso por outro usuário.';
                } else {
                    $userToEdit->nome = $nome;
                    $userToEdit->email = $email;
                    if (!empty($password)) {
                        $userToEdit->password = password_hash($password, PASSWORD_DEFAULT);
                    }
                    $userToEdit->role = $role;
                    $userToEdit->status = $status;
                    $userToEdit->status_pagamento = $status_pagamento;
                    $userToEdit->acesso_dcip = $acesso_dcip;
                    
                    if ($userToEdit->save()) {
                        $_SESSION['success'] = 'Usuário atualizado com sucesso!';
                        header('Location: gerenciar_usuarios.php');
                        exit;
                    } else {
                        $errors[] = 'Erro ao atualizar usuário. Por favor, tente novamente.';
                    }
                }
            }
            
            if (!empty($errors)) {
                $_SESSION['error'] = implode('<br>', $errors);
            }
        }
                
        // Exibe o formulário de edição
        require_once '_header.php';
        ?>
        <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Editar Usuário</h1>
                <a href="gerenciar_usuarios.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                    <i class="fas fa-arrow-left fa-sm text-white-50"></i> Voltar
                </a>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $_SESSION['error'] ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="POST" action="?action=edit&id=<?= $userToEdit->id ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="nome" class="form-label">Nome Completo <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nome" name="nome" 
                                           value="<?= htmlspecialchars($userToEdit->nome) ?>" required>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($userToEdit->email) ?>" required>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="password" class="form-label">Nova Senha</label>
                                    <input type="password" class="form-control" id="password" name="password"
                                           placeholder="Deixe em branco para manter a senha atual">
                                    <small class="form-text text-muted">Mínimo de 8 caracteres</small>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="role" class="form-label">Função</label>
                                    <select class="form-select" id="role" name="role">
                                        <option value="admin" <?= $userToEdit->role === 'admin' ? 'selected' : '' ?>>Administrador</option>
                                        <option value="operador" <?= $userToEdit->role !== 'admin' ? 'selected' : '' ?>>Operador</option>
                                    </select>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="ativo" <?= $userToEdit->status === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                                        <option value="inativo" <?= $userToEdit->status === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                                        <option value="pendente" <?= $userToEdit->status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                    </select>
                                </div>
                                
                                <div class="form-group mb-4">
                                    <label for="status_pagamento" class="form-label">Status de Pagamento</label>
                                    <select class="form-select" id="status_pagamento" name="status_pagamento">
                                        <option value="ativo" <?= $userToEdit->status_pagamento === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                                        <option value="pendente" <?= $userToEdit->status_pagamento === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                        <option value="bloqueado" <?= $userToEdit->status_pagamento === 'bloqueado' ? 'selected' : '' ?>>Bloqueado</option>
                                    </select>
                                </div>

                                <div class="form-group mb-4">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="acesso_dcip" name="acesso_dcip"
                                               value="1" <?= isset($userToEdit->acesso_dcip) && $userToEdit->acesso_dcip == 1 ? 'checked' : '' ?>>
                                        <label for="acesso_dcip" class="form-check-label">
                                            Permitir acesso ao menu DCIP
                                        </label>
                                        <small class="form-text text-muted d-block">Se marcado, o usuário poderá acessar as funcionalidades de Declaração de Crédito do ICMS Próprio</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between pt-3 border-top">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Salvar Alterações
                            </button>
                            <a href="gerenciar_usuarios.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
        require_once '_footer.php';
        exit;
        
    case 'delete':
        if (!$id) {
            $_SESSION['error'] = 'ID do usuário não fornecido.';
            header('Location: gerenciar_usuarios.php');
            exit;
        }
        
        $usuarioModel = new Usuario();
        $userToDelete = $usuarioModel->find((int)$id);
        if (!$userToDelete) {
            $_SESSION['error'] = 'Usuário não encontrado.';
            header('Location: gerenciar_usuarios.php');
            exit;
        }
        
        // Impede que o próprio usuário se delete
        if ($userToDelete->id === (int)$_SESSION['user_id']) {
            $_SESSION['error'] = 'Você não pode excluir seu próprio usuário.';
            header('Location: gerenciar_usuarios.php');
            exit;
        }
        
        if ($userToDelete->delete()) {
            $_SESSION['success'] = 'Usuário excluído com sucesso!';
        } else {
            $_SESSION['error'] = 'Erro ao excluir usuário. Por favor, tente novamente.';
        }
        
        header('Location: gerenciar_usuarios.php');
        exit;
        
    case 'toggle_status':
        if (!$id) {
            $_SESSION['error'] = 'ID do usuário não fornecido.';
            header('Location: gerenciar_usuarios.php');
            exit;
        }
        
        $usuarioModel = new Usuario();
        $user = $usuarioModel->find((int)$id);
        if ($user) {
            $user->status = $user->status === 'ativo' ? 'inativo' : 'ativo';
            if ($user->save()) {
                $_SESSION['success'] = 'Status do usuário atualizado com sucesso!';
            } else {
                $_SESSION['error'] = 'Erro ao atualizar status do usuário.';
            }
        } else {
            $_SESSION['error'] = 'Usuário não encontrado.';
        }
        
        header('Location: gerenciar_usuarios.php');
        exit;
        
    case 'toggle_pagamento':
        if (!$id) {
            $_SESSION['error'] = 'ID do usuário não fornecido.';
            header('Location: gerenciar_usuarios.php');
            exit;
        }
        
        $usuarioModel = new Usuario();
        $user = $usuarioModel->find((int)$id);
        if ($user) {
            $user->status_pagamento = $user->status_pagamento === 'ativo' ? 'bloqueado' : 'ativo';
            if ($user->save()) {
                $_SESSION['success'] = 'Status de pagamento atualizado com sucesso!';
            } else {
                $_SESSION['error'] = 'Erro ao atualizar status de pagamento.';
            }
        } else {
            $_SESSION['error'] = 'Usuário não encontrado.';
        }
        
        header('Location: gerenciar_usuarios.php');
        exit;
        
    case 'toggle_dcip':
        if (!$id) {
            $_SESSION['error'] = 'ID do usuário não fornecido.';
            header('Location: gerenciar_usuarios.php');
            exit;
        }
        
        $usuarioModel = new Usuario();
        $user = $usuarioModel->find((int)$id);
        if ($user) {
            $user->acesso_dcip = isset($user->acesso_dcip) && $user->acesso_dcip == 1 ? 0 : 1;
            if ($user->save()) {
                $_SESSION['success'] = 'Acesso DCIP do usuário atualizado com sucesso!';
            } else {
                $_SESSION['error'] = 'Erro ao atualizar acesso DCIP do usuário.';
            }
        } else {
            $_SESSION['error'] = 'Usuário não encontrado.';
        }
        
        header('Location: gerenciar_usuarios.php');
        exit;
        
    case 'list':
    default:
        // Busca todos os usuários ordenados por ID
        $usuarioModel = new Usuario();
        $usuarios = $usuarioModel->orderBy('id');
        
        // Inclui o cabeçalho
        require_once '_header.php';
        ?>
        <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Gerenciar Usuários</h1>
                <a href="?action=new" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                    <i class="fas fa-plus fa-sm text-white-50"></i> Novo Usuário
                </a>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $_SESSION['success'] ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $_SESSION['error'] ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <div class="card shadow mb-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>E-mail</th>
                                    <th>Função</th>
                                    <th>Status</th>
                                    <th>Pagamento</th>
                                    <th>Acesso DCIP</th>
                                    <th>Data Cadastro</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($usuario->id) ?></td>
                                        <td><?= htmlspecialchars($usuario->nome) ?></td>
                                        <td><?= htmlspecialchars($usuario->email) ?></td>
                                        <td>
                                            <span class="badge <?= $usuario->role === 'admin' ? 'bg-primary' : 'bg-secondary' ?>">
                                                <?= ucfirst($usuario->role) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $usuario->status === 'ativo' ? 'bg-success' : ($usuario->status === 'pendente' ? 'bg-warning' : 'bg-danger') ?>">
                                                <?= ucfirst($usuario->status) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $usuario->status_pagamento === 'ativo' ? 'bg-success' : ($usuario->status_pagamento === 'pendente' ? 'bg-warning' : 'bg-danger') ?>">
                                                <?= ucfirst($usuario->status_pagamento) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= isset($usuario->acesso_dcip) && $usuario->acesso_dcip == 1 ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= isset($usuario->acesso_dcip) && $usuario->acesso_dcip == 1 ? 'Permitido' : 'Negado' ?>
                                            </span>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($usuario->created_at)) ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <!-- Botão Editar -->
                                                <a href="?action=edit&id=<?= $usuario->id ?>" 
                                                   class="btn btn-outline-primary" 
                                                   title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                
                                                <?php if ($usuario->id !== (int)$_SESSION['user_id']): ?>
                                                    <!-- Botão Ativar/Desativar -->
                                                    <a href="?action=toggle_status&id=<?= $usuario->id ?>" 
                                                       class="btn btn-outline-<?= $usuario->status === 'ativo' ? 'warning' : 'success' ?>" 
                                                       title="<?= $usuario->status === 'ativo' ? 'Desativar' : 'Ativar' ?> Usuário"
                                                       onclick="return confirm('Tem certeza que deseja <?= $usuario->status === 'ativo' ? 'desativar' : 'ativar' ?> este usuário?')">
                                                        <i class="bi bi-<?= $usuario->status === 'ativo' ? 'person-x' : 'person-check' ?>"></i>
                                                    </a>
                                                    
                                                    <!-- Botão Bloquear/Liberar Pagamento -->
                                                    <a href="?action=toggle_pagamento&id=<?= $usuario->id ?>" 
                                                       class="btn btn-outline-<?= $usuario->status_pagamento === 'ativo' ? 'danger' : 'success' ?>" 
                                                       title="<?= $usuario->status_pagamento === 'ativo' ? 'Bloquear' : 'Liberar' ?> Pagamento"
                                                       onclick="return confirm('Tem certeza que deseja <?= $usuario->status_pagamento === 'ativo' ? 'bloquear' : 'liberar' ?> o pagamento deste usuário?')">
                                                        <i class="bi bi-<?= $usuario->status_pagamento === 'ativo' ? 'lock' : 'unlock' ?>"></i>
                                                    </a>

                                                    <!-- Botão Permitir/Negar Acesso DCIP -->
                                                    <a href="?action=toggle_dcip&id=<?= $usuario->id ?>" 
                                                       class="btn btn-outline-<?= isset($usuario->acesso_dcip) && $usuario->acesso_dcip == 1 ? 'warning' : 'info' ?>" 
                                                       title="<?= isset($usuario->acesso_dcip) && $usuario->acesso_dcip == 1 ? 'Negar' : 'Permitir' ?> Acesso DCIP"
                                                       onclick="return confirm('Tem certeza que deseja <?= isset($usuario->acesso_dcip) && $usuario->acesso_dcip == 1 ? 'negar' : 'permitir' ?> o acesso ao menu DCIP para este usuário?')">
                                                        <i class="bi bi-<?= isset($usuario->acesso_dcip) && $usuario->acesso_dcip == 1 ? 'shield-x' : 'shield-check' ?>"></i>
                                                    </a>
                                                    
                                                    <!-- Botão Excluir -->
                                                    <a href="?action=delete&id=<?= $usuario->id ?>" 
                                                       class="btn btn-outline-danger" 
                                                       title="Excluir"
                                                       onclick="return confirm('Tem certeza que deseja excluir este usuário? Esta ação não pode ser desfeita.')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <!-- Botão desabilitado para o próprio usuário -->
                                                    <button class="btn btn-outline-secondary" disabled title="Ações não disponíveis para seu próprio usuário">
                                                        <i class="bi bi-person"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        // Inicializa o DataTable
        $(document).ready(function() {
            $('#dataTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Portuguese-Brasil.json"
                },
                "order": [[0, "asc"]],
                "responsive": true,
                "pageLength": 25
            });
        });
        </script>
        <?php
        // Inclui o rodapé
        require_once '_footer.php';
        break;
}
?>
