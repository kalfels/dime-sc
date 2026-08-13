<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../src/app_classes.php';

use App\Models\Usuario;
use App\Models\DimeDeclaracao;
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

// Helper function to count DIMEs by status
function countDimeByStatus($dime_declarations, $status) {
    return count(array_filter($dime_declarations, fn($d) => ($d['status'] ?? '') === $status));
}

// Get DIME declarations based on user role
$pdo = DBConnection::getInstance()->getConnection();

if ($user_role === 'admin') {
    $sql = "SELECT dd.*, e.razao_social, e.cnpj, c.nome as contabilista_nome
            FROM dime_declaracoes dd
            LEFT JOIN empresas e ON dd.empresa_id = e.id
            LEFT JOIN contabilistas c ON e.contabilista_id = c.id
            ORDER BY dd.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
} else {
    $sql = "SELECT dd.*, e.razao_social, e.cnpj, c.nome as contabilista_nome
            FROM dime_declaracoes dd
            LEFT JOIN empresas e ON dd.empresa_id = e.id
            LEFT JOIN contabilistas c ON e.contabilista_id = c.id
            WHERE e.contabilista_id = ?
            ORDER BY dd.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$contabilista_id_session]);
}

$dime_declarations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'DIME - Declarações';
include '_header.php';
?>

<div class="col-12">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Declarações DIME</h1>
            <p class="text-muted mb-0">Declaração de Informações Mensais do ICMS</p>
        </div>
        <div>
            <?php if (!isset($_SESSION['has_payment_issues']) || !$_SESSION['has_payment_issues'] || $_SESSION['is_admin']): ?>
                <a href="inserir_dime.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Nova DIME
                </a>
            <?php else: ?>
                <button class="btn btn-primary" disabled title="Funcionalidade indisponível devido a pendências financeiras">
                    <i class="bi bi-plus-circle me-2"></i>Nova DIME <i class="bi bi-lock-fill ms-1"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total DIMEs</h6>
                            <h3 class="mb-0"><?php echo count($dime_declarations); ?></h3>
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
                            <h6 class="card-title mb-0">Transmitidas</h6>
                            <h3 class="mb-0"><?php echo countDimeByStatus($dime_declarations, 'transmitida'); ?></h3>
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
                            <h6 class="card-title mb-0">Rascunhos</h6>
                            <h3 class="mb-0"><?php echo countDimeByStatus($dime_declarations, 'rascunho'); ?></h3>
                        </div>
                        <i class="bi bi-pencil fs-1 opacity-50"></i>
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
                            <h3 class="mb-0"><?php echo count(array_filter($dime_declarations, fn($d) => substr(($d['periodo_referencia'] ?? ''), 0, 4) === date('Y'))); ?></h3>
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
                        $empresas_unique = array_unique(array_column($dime_declarations, 'razao_social'));
                        $empresas_unique = array_filter($empresas_unique); // Remove valores vazios
                        sort($empresas_unique);
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
                        $periodos_unique = array_unique(array_column($dime_declarations, 'periodo_referencia'));
                        $periodos_unique = array_filter($periodos_unique, fn($p) => !empty($p)); // Remove valores vazios
                        rsort($periodos_unique);
                        foreach ($periodos_unique as $periodo):
                            if (!empty($periodo) && strlen($periodo) >= 7) {
                                $periodo_formatted = substr($periodo, 5, 2) . '/' . substr($periodo, 0, 4);
                            } else {
                                $periodo_formatted = 'N/A';
                            }
                        ?>
                            <option value="<?php echo $periodo; ?>"><?php echo $periodo_formatted; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="filter-status" class="form-label">Status</label>
                    <select class="form-select" id="filter-status">
                        <option value="">Todos os status</option>
                        <option value="rascunho">Rascunho</option>
                        <option value="validada">Validada</option>
                        <option value="transmitida">Transmitida</option>
                        <option value="processada">Processada</option>
                        <option value="rejeitada">Rejeitada</option>
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

    <!-- DIME Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-list me-2"></i>Declarações DIME
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($dime_declarations)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-file-earmark-text text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">Nenhuma declaração DIME encontrada</h5>
                    <p class="text-muted">Comece criando sua primeira declaração DIME.</p>
                    <?php if (!isset($_SESSION['has_payment_issues']) || !$_SESSION['has_payment_issues'] || $_SESSION['is_admin']): ?>
                        <a href="inserir_dime.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Nova DIME
                        </a>
                    <?php else: ?>
                        <button class="btn btn-primary" disabled title="Funcionalidade indisponível devido a pendências financeiras">
                            <i class="bi bi-plus-circle me-2"></i>Nova DIME <i class="bi bi-lock-fill ms-1"></i>
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="dime-table">
                        <thead class="table-light">
                            <tr>
                                <th>Empresa</th>
                                <th>CNPJ</th>
                                <th>Período</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Regime</th>
                                <th>Criado em</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dime_declarations as $dime): ?>
                                <tr data-empresa="<?php echo htmlspecialchars($dime['razao_social'] ?? ''); ?>"
                                    data-periodo="<?php echo htmlspecialchars($dime['periodo_referencia'] ?? ''); ?>"
                                    data-status="<?php echo htmlspecialchars($dime['status'] ?? ''); ?>">
                                    <td>
                                        <div class="fw-medium"><?php echo htmlspecialchars($dime['razao_social'] ?? 'N/A'); ?></div>
                                        <?php if ($user_role === 'admin' && !empty($dime['contabilista_nome'])): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($dime['contabilista_nome']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code><?php echo htmlspecialchars($dime['cnpj'] ?? 'N/A'); ?></code>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?php 
                                            $periodo = $dime['periodo_referencia'] ?? '';
                                            if (!empty($periodo) && strlen($periodo) >= 7) {
                                                echo substr($periodo, 5, 2) . '/' . substr($periodo, 0, 4);
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo htmlspecialchars(ucfirst($dime['tipo_declaracao'] ?? 'N/A')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_config = [
                                            'transmitida' => ['class' => 'bg-success', 'text' => 'Transmitida'],
                                            'processada' => ['class' => 'bg-success', 'text' => 'Processada'],
                                            'validada' => ['class' => 'bg-info', 'text' => 'Validada'],
                                            'rascunho' => ['class' => 'bg-warning', 'text' => 'Rascunho'],
                                            'rejeitada' => ['class' => 'bg-danger', 'text' => 'Rejeitada']
                                        ];

                                        $status_info = $status_config[($dime['status'] ?? '')] ?? ['class' => 'bg-secondary', 'text' => 'Indefinido'];
                                        ?>
                                        <span class="badge <?php echo $status_info['class']; ?>">
                                            <?php echo $status_info['text']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars(ucfirst($dime['regime_apuracao'] ?? 'N/A')); ?></small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php 
                                            if (!empty($dime['created_at'])) {
                                                echo date('d/m/Y H:i', strtotime($dime['created_at']));
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if (($dime['status'] ?? '') === 'rascunho'): ?>
                                                <a href="editar_dime.php?id=<?php echo base64_encode($dime['id'] ?? ''); ?>"
                                                   class="btn btn-outline-primary" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            <?php endif; ?>

                                            <?php if (!empty($dime['arquivo_gerado_path'])): ?>
                                                <?php if (!isset($_SESSION['has_payment_issues']) || !$_SESSION['has_payment_issues'] || $_SESSION['is_admin']): ?>
                                                    <a href="gerar_arquivo_dime.php?id=<?php echo base64_encode($dime['id'] ?? ''); ?>&download=1"
                                                       class="btn btn-outline-success" title="Download">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-success" disabled title="Funcionalidade indisponível devido a pendências financeiras">
                                                        <i class="bi bi-download"></i>
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <button type="button" class="btn btn-outline-info"
                                                    onclick="visualizarDime(<?php echo htmlspecialchars($dime['id'] ?? '0'); ?>)" title="Visualizar">
                                                <i class="bi bi-eye"></i>
                                            </button>

                                            <?php if (($dime['status'] ?? '') === 'rascunho'): ?>
                                                <button type="button" class="btn btn-outline-danger"
                                                        onclick="excluirDime(<?php echo htmlspecialchars($dime['id'] ?? '0'); ?>)" title="Excluir">
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

<!-- Modal for DIME Details -->
<div class="modal fade" id="dimeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes da DIME</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="dimeModalBody">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
console.log('[ListDIME]', 'Page loaded');

// Função para mostrar alertas
function showAlert(message, type = 'info') {
    // Remove alertas existentes
    const existingAlerts = document.querySelectorAll('.alert-dismissible');
    existingAlerts.forEach(alert => alert.remove());

    // Cria novo alerta
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    `;

    // Adiciona ao body
    document.body.appendChild(alertDiv);

    // Auto-remove após 5 segundos
    setTimeout(() => {
        if (alertDiv && alertDiv.parentElement) {
            alertDiv.remove();
        }
    }, 5000);
}

function aplicarFiltros() {
    const empresaFilter = document.getElementById('filter-empresa')?.value?.toLowerCase() || '';
    const periodoFilter = document.getElementById('filter-periodo')?.value || '';
    const statusFilter = document.getElementById('filter-status')?.value || '';

    const rows = document.querySelectorAll('#dime-table tbody tr');

    rows.forEach(row => {
        const empresa = row.dataset.empresa?.toLowerCase() || '';
        const periodo = row.dataset.periodo || '';
        const status = row.dataset.status || '';

        const empresaMatch = !empresaFilter || empresa.includes(empresaFilter);
        const periodoMatch = !periodoFilter || periodo === periodoFilter;
        const statusMatch = !statusFilter || status === statusFilter;

        if (empresaMatch && periodoMatch && statusMatch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });

    // Atualizar contador de resultados visíveis
    const visibleRows = document.querySelectorAll('#dime-table tbody tr[style=""], #dime-table tbody tr:not([style*="none"])');
    console.log(`[ListDIME] Mostrando ${visibleRows.length} de ${rows.length} registros`);
}

function limparFiltros() {
    const empresaSelect = document.getElementById('filter-empresa');
    const periodoSelect = document.getElementById('filter-periodo');
    const statusSelect = document.getElementById('filter-status');

    if (empresaSelect) empresaSelect.value = '';
    if (periodoSelect) periodoSelect.value = '';
    if (statusSelect) statusSelect.value = '';

    aplicarFiltros();
}

function visualizarDime(id) {
    fetch(`ajax_dime_details.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('dimeModalBody').innerHTML = html;
            new bootstrap.Modal(document.getElementById('dimeModal')).show();
        })
        .catch(error => {
            console.error('Error loading DIME details:', error);
            showAlert('Erro ao carregar detalhes da DIME', 'danger');
        });
}

function excluirDime(id) {
    if (confirm('Tem certeza que deseja excluir esta declaração DIME? Esta ação não pode ser desfeita.')) {
        fetch('ajax_delete_dime.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('DIME excluída com sucesso!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message || 'Erro ao excluir DIME', 'danger');
            }
        })
        .catch(error => {
            console.error('Error deleting DIME:', error);
            showAlert('Erro ao excluir DIME', 'danger');
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
