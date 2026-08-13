<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../src/app_classes.php';

use App\Models\Usuario;
use App\Models\Empresa;
use App\Models\Contabilista;
use App\Core\DBConnection;

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['user_role'];
$contabilista_id_session = $_SESSION['contabilista_id'];

// Get DCIP declarations based on user role
$pdo = DBConnection::getInstance()->getConnection();

if ($user_role === 'admin') {
    $sql = "SELECT dd.*, e.razao_social, e.cnpj, c.nome as contabilista_nome 
            FROM dcip_declaracoes dd
            LEFT JOIN empresas e ON dd.empresa_id = e.id
            LEFT JOIN contabilistas c ON e.contabilista_id = c.id
            ORDER BY dd.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
} else {
    $sql = "SELECT dd.*, e.razao_social, e.cnpj, c.nome as contabilista_nome 
            FROM dcip_declaracoes dd
            LEFT JOIN empresas e ON dd.empresa_id = e.id
            LEFT JOIN contabilistas c ON e.contabilista_id = c.id
            WHERE e.contabilista_id = ?
            ORDER BY dd.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$contabilista_id_session]);
}

$dcip_declarations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'DCIP - Declarações';
include '_header.php';
?>

<div class="col-12">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Declarações DCIP</h1>
            <p class="text-muted mb-0">Declaração de Crédito do ICMS Próprio</p>
        </div>
        <div>
            <a href="gerar_arquivo_dcip.php" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Nova DCIP
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total DCIPs</h6>
                            <h3 class="mb-0"><?php echo count($dcip_declarations); ?></h3>
                        </div>
                        <i class="bi bi-file-earmark-text fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Finalizadas</h6>
                            <h3 class="mb-0"><?php echo count(array_filter($dcip_declarations, fn($d) => $d['status'] === 'finalizada')); ?></h3>
                        </div>
                        <i class="bi bi-check-circle fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Em Andamento</h6>
                            <h3 class="mb-0"><?php echo count(array_filter($dcip_declarations, fn($d) => $d['status'] === 'em_andamento')); ?></h3>
                        </div>
                        <i class="bi bi-clock fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Ano Atual</h6>
                            <h3 class="mb-0"><?php echo count(array_filter($dcip_declarations, fn($d) => substr($d['periodo_referencia'], -4) === date('Y'))); ?></h3>
                        </div>
                        <i class="bi bi-calendar fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="filter-empresa" class="form-label">Empresa</label>
                    <select class="form-select" id="filter-empresa">
                        <option value="">Todas as empresas</option>
                        <?php 
                        $empresas_unique = array_unique(array_column($dcip_declarations, 'razao_social'));
                        foreach ($empresas_unique as $empresa): 
                        ?>
                            <option value="<?php echo htmlspecialchars($empresa); ?>"><?php echo htmlspecialchars($empresa); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="filter-periodo" class="form-label">Período</label>
                    <select class="form-select" id="filter-periodo">
                        <option value="">Todos os períodos</option>
                        <?php 
                        $periodos_unique = array_unique(array_column($dcip_declarations, 'periodo_referencia'));
                        rsort($periodos_unique);
                        foreach ($periodos_unique as $periodo): 
                            $periodo_formatted = substr($periodo, 0, 2) . '/' . substr($periodo, 2);
                        ?>
                            <option value="<?php echo $periodo; ?>"><?php echo $periodo_formatted; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="filter-status" class="form-label">Status</label>
                    <select class="form-select" id="filter-status">
                        <option value="">Todos os status</option>
                        <option value="em_andamento">Em Andamento</option>
                        <option value="finalizada">Finalizada</option>
                        <option value="cancelada">Cancelada</option>
                    </select>
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-secondary w-100" onclick="limparFiltros()">
                        <i class="bi bi-x-circle me-2"></i>Limpar Filtros
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- DCIP Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-list me-2"></i>Declarações DCIP
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($dcip_declarations)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-file-earmark-text text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">Nenhuma declaração DCIP encontrada</h5>
                    <p class="text-muted">Comece criando sua primeira declaração DCIP.</p>
                    <a href="gerar_arquivo_dcip.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Nova DCIP
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="dcip-table">
                        <thead class="table-light">
                            <tr>
                                <th>Empresa</th>
                                <th>CNPJ</th>
                                <th>Período</th>
                                <th>Status</th>
                                <th>Valor Total</th>
                                <th>Saldo Atual</th>
                                <th>Criado em</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dcip_declarations as $dcip): ?>
                                <tr data-empresa="<?php echo htmlspecialchars($dcip['razao_social']); ?>" 
                                    data-periodo="<?php echo $dcip['periodo_referencia']; ?>" 
                                    data-status="<?php echo $dcip['status']; ?>">
                                    <td>
                                        <div class="fw-medium"><?php echo htmlspecialchars($dcip['razao_social']); ?></div>
                                        <?php if ($user_role === 'admin' && $dcip['contabilista_nome']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($dcip['contabilista_nome']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code><?php echo $dcip['cnpj']; ?></code>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?php echo substr($dcip['periodo_referencia'], 0, 2) . '/' . substr($dcip['periodo_referencia'], 2); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = match($dcip['status']) {
                                            'finalizada' => 'bg-success',
                                            'em_andamento' => 'bg-warning',
                                            'cancelada' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                        $status_text = match($dcip['status']) {
                                            'finalizada' => 'Finalizada',
                                            'em_andamento' => 'Em Andamento',
                                            'cancelada' => 'Cancelada',
                                            default => 'Indefinido'
                                        };
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-medium">R$ <?php echo number_format($dcip['valor_total_creditos'] ?? 0, 2, ',', '.'); ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-medium">R$ <?php echo number_format($dcip['saldo_creditos_atual'] ?? 0, 2, ',', '.'); ?></span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('d/m/Y H:i', strtotime($dcip['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($dcip['status'] !== 'finalizada'): ?>
                                                <a href="editar_dcip.php?id=<?php echo $dcip['id']; ?>" 
                                                   class="btn btn-outline-primary" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($dcip['arquivo_hash']): ?>
                                                <a href="download_dcip.php?id=<?php echo $dcip['id']; ?>" 
                                                   class="btn btn-outline-success" title="Download">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <button type="button" class="btn btn-outline-info" 
                                                    onclick="visualizarDcip(<?php echo $dcip['id']; ?>)" title="Visualizar">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            
                                            <?php if ($dcip['status'] !== 'finalizada'): ?>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        onclick="excluirDcip(<?php echo $dcip['id']; ?>)" title="Excluir">
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
</div>

<!-- Modal for DCIP Details -->
<div class="modal fade" id="dcipModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes da DCIP</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="dcipModalBody">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
console.log('[ListDCIP]', 'Page loaded');

function aplicarFiltros() {
    const empresaFilter = document.getElementById('filter-empresa').value.toLowerCase();
    const periodoFilter = document.getElementById('filter-periodo').value;
    const statusFilter = document.getElementById('filter-status').value;
    
    const rows = document.querySelectorAll('#dcip-table tbody tr');
    
    rows.forEach(row => {
        const empresa = row.dataset.empresa.toLowerCase();
        const periodo = row.dataset.periodo;
        const status = row.dataset.status;
        
        const empresaMatch = !empresaFilter || empresa.includes(empresaFilter);
        const periodoMatch = !periodoFilter || periodo === periodoFilter;
        const statusMatch = !statusFilter || status === statusFilter;
        
        if (empresaMatch && periodoMatch && statusMatch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function limparFiltros() {
    document.getElementById('filter-empresa').value = '';
    document.getElementById('filter-periodo').value = '';
    document.getElementById('filter-status').value = '';
    aplicarFiltros();
}

function visualizarDcip(id) {
    fetch(`ajax_dcip_details.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('dcipModalBody').innerHTML = html;
            new bootstrap.Modal(document.getElementById('dcipModal')).show();
        })
        .catch(error => {
            console.error('Error loading DCIP details:', error);
            showAlert('Erro ao carregar detalhes da DCIP', 'danger');
        });
}

function excluirDcip(id) {
    if (confirm('Tem certeza que deseja excluir esta declaração DCIP? Esta ação não pode ser desfeita.')) {
        fetch('ajax_delete_dcip.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('DCIP excluída com sucesso!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message || 'Erro ao excluir DCIP', 'danger');
            }
        })
        .catch(error => {
            console.error('Error deleting DCIP:', error);
            showAlert('Erro ao excluir DCIP', 'danger');
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners for filters
    ['filter-empresa', 'filter-periodo', 'filter-status'].forEach(id => {
        document.getElementById(id).addEventListener('change', aplicarFiltros);
    });
});
</script>

<?php include '_footer.php'; ?>
