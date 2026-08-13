<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../src/app_classes.php';

use App\Core\DBConnection;
use App\Models\Usuario;
use App\Models\Municipio;

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Simple ID obfuscation functions for security
function obfuscateId($id) {
    return base64_encode(($id * 7) + 123);
}

function deobfuscateId($encoded) {
    $decoded = base64_decode($encoded);
    return is_numeric($decoded) ? (($decoded - 123) / 7) : 0;
}

$message = '';
$message_type = '';
$municipioToEdit = null;
$municipios = [];

// Form submissions are disabled

// Handle messages from GET parameters first
if (isset($_GET['message']) && isset($_GET['text'])) {
    $message = htmlspecialchars($_GET['text']);
    $message_type = htmlspecialchars($_GET['message']);
}

// Edit functionality is disabled

// Handle search
$searchTerm = $_GET['search'] ?? '';

// Load municipalities list with search filter
if (!empty($searchTerm)) {
    $pdo = \App\Core\DBConnection::getInstance()->getConnection();
    $stmt = $pdo->prepare("SELECT * FROM t_municipios WHERE nome LIKE ? OR codigo_ibge LIKE ? ORDER BY nome");
    $searchPattern = '%' . $searchTerm . '%';
    $stmt->execute([$searchPattern, $searchPattern]);
    $municipios = [];
    
    while ($row = $stmt->fetch()) {
        $municipios[] = (object) $row;
    }
} else {
    $pdo = \App\Core\DBConnection::getInstance()->getConnection();
    $stmt = $pdo->query("SELECT * FROM t_municipios ORDER BY nome");
    $municipios = [];
    
    while ($row = $stmt->fetch()) {
        $municipios[] = (object) $row;
    }
}

$page_title = 'Consultar Municípios';
include '_header.php';
?>

<div class="col-12">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Tabela de Municípios</h1>
            <p class="text-muted mb-0">Pesquise e visualize os municípios cadastrados</p>
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

    <!-- Search Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label for="search" class="form-label">Buscar Município</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($searchTerm); ?>" 
                           placeholder="Digite o nome do município ou código IBGE...">
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-2"></i>Buscar
                        </button>
                        <?php if (!empty($searchTerm)): ?>
                            <a href="gerenciar_municipios.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-2"></i>Limpar
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Municipalities Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-geo-alt me-2"></i>Municípios Cadastrados
                <?php if (!empty($searchTerm)): ?>
                    <small class="text-muted">(Filtrados por: "<?php echo htmlspecialchars($searchTerm); ?>")</small>
                <?php endif; ?>
            </h5>
            <span class="badge bg-secondary"><?php echo count($municipios); ?> município(s)</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($municipios)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-geo-alt text-muted" style="font-size: 3rem;"></i>
                    <?php if (!empty($searchTerm)): ?>
                        <h5 class="text-muted mt-3">Nenhum município encontrado</h5>
                        <p class="text-muted">Não foram encontrados municípios com o termo "<?php echo htmlspecialchars($searchTerm); ?>".</p>
                        <a href="gerenciar_municipios.php" class="btn btn-outline-primary">Ver todos os municípios</a>
                    <?php else: ?>
                        <h5 class="text-muted mt-3">Nenhum município cadastrado</h5>
                        <p class="text-muted">Comece adicionando o primeiro município.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Código IBGE</th>
                                <th>Município</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($municipios as $municipio): ?>
                                <tr>
                                    <td>
                                        <code><?php echo htmlspecialchars($municipio->codigo_ibge); ?></code>
                                    </td>
                                    <td>
                                        <div class="fw-medium"><?php echo htmlspecialchars($municipio->nome); ?></div>
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

</script>

<?php include '_footer.php'; ?>