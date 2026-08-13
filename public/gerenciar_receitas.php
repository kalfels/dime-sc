<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../src/app_classes.php';

use App\Core\DBConnection;
use App\Models\Usuario;

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

$message = '';
$message_type = '';
$receitas = [];

// Handle messages from GET parameters
if (isset($_GET['message']) && isset($_GET['text'])) {
    $message = htmlspecialchars($_GET['text']);
    $message_type = htmlspecialchars($_GET['message']);
}

// Handle search
$searchTerm = $_GET['search'] ?? '';

// Load Receitas list with search filter
$pdo = \App\Core\DBConnection::getInstance()->getConnection();
if (!empty($searchTerm)) {
    $stmt = $pdo->prepare("SELECT * FROM t_codigos_receita WHERE codigo LIKE ? OR descricao LIKE ? ORDER BY codigo");
    $searchPattern = '%' . $searchTerm . '%';
    $stmt->execute([$searchPattern, $searchPattern]);
} else {
    $stmt = $pdo->query("SELECT * FROM t_codigos_receita ORDER BY codigo");
}

while ($row = $stmt->fetch()) {
    $receitas[] = (object) $row;
}

$page_title = 'Tabela de Códigos de Receita';
include '_header.php';
?>

<div class="col-12">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Tabela de Códigos de Receita</h1>
            <p class="text-muted mb-0">Pesquise e visualize os códigos de receita cadastrados</p>
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
            <div><?php echo $message; ?></div>
        </div>
    <?php endif; ?>

    <!-- Search Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label for="search" class="form-label">Buscar Código de Receita</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($searchTerm); ?>" 
                           placeholder="Digite o código ou descrição...">
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-2"></i>Buscar
                        </button>
                        <?php if (!empty($searchTerm)): ?>
                            <a href="gerenciar_receitas.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-lg me-2"></i>Limpar
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Receitas List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-cash-coin me-2"></i>Códigos de Receita Cadastrados
                <?php if (!empty($searchTerm)): ?>
                    <span class="badge bg-primary ms-2">
                        <?php echo count($receitas); ?> resultado(s) encontrado(s)
                    </span>
                <?php endif; ?>
            </h5>
            <div class="text-muted small">
                Total: <?php echo count($receitas); ?>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($receitas)): ?>
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="bi bi-cash-coin text-muted" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-2">Nenhum código de receita encontrado</h5>
                    <p class="text-muted">
                        <?php echo !empty($searchTerm) ? 'Nenhum código corresponde à sua pesquisa.' : 'Nenhum código de receita cadastrado no sistema.'; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 100px;">Código</th>
                                <th>Descrição</th>
                                <th style="width: 180px;">
                                    <div class="d-flex align-items-center">
                                        <span>Origens Válidas</span>
                                        <i class="bi bi-question-circle ms-1" 
                                           data-bs-toggle="tooltip" 
                                           data-bs-placement="top" 
                                           title="Indica as origens que este código de receita pode ter na DIME:
• 1 = Operação Interna
• 2 = Operação Interestadual
• 3 = Operação com Exterior
• 4 = Operação Interna - ST

Consulte o manual da DIME para mais informações."></i>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($receitas as $receita): ?>
                                <tr>
                                    <td>
                                        <code><?php echo htmlspecialchars($receita->codigo); ?></code>
                                    </td>
                                    <td>
                                        <div class="fw-medium"><?php echo htmlspecialchars($receita->descricao); ?></div>
                                    </td>
                                    <td>
                                        <?php if (!empty($receita->origem_dime_valida)): ?>
                                            <?php 
                                                $origens = str_split($receita->origem_dime_valida);
                                                foreach ($origens as $origem): 
                                            ?>
                                                <span class="badge bg-info me-1"><?php echo htmlspecialchars($origem); ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
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

<!-- Initialize tooltips -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            trigger: 'hover'
        });
    });
});
</script>

<?php include '_footer.php'; ?>
