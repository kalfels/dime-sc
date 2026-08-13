<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../src/app_classes.php';

use App\Core\DBConnection;

// Authentication check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$user_role = $_SESSION['user_role'];
$contabilista_id_session = $_SESSION['contabilista_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit('Invalid ID');
}

$dcip_id = intval($_GET['id']);
$pdo = DBConnection::getInstance()->getConnection();

// Get DCIP details with permission check
if ($user_role === 'admin') {
    $sql = "SELECT dd.*, e.razao_social, e.cnpj, e.inscricao_estadual, c.nome as contabilista_nome 
            FROM dcip_declaracoes dd
            LEFT JOIN empresas e ON dd.empresa_id = e.id
            LEFT JOIN contabilistas c ON e.contabilista_id = c.id
            WHERE dd.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dcip_id]);
} else {
    $sql = "SELECT dd.*, e.razao_social, e.cnpj, e.inscricao_estadual, c.nome as contabilista_nome 
            FROM dcip_declaracoes dd
            LEFT JOIN empresas e ON dd.empresa_id = e.id
            LEFT JOIN contabilistas c ON e.contabilista_id = c.id
            WHERE dd.id = ? AND e.contabilista_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dcip_id, $contabilista_id_session]);
}

$dcip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dcip) {
    http_response_code(404);
    exit('DCIP not found');
}

// Get credit details
$sql = "SELECT * FROM dcip_creditos WHERE dcip_id = ? ORDER BY id";
$stmt = $pdo->prepare($sql);
$stmt->execute([$dcip_id]);
$creditos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$status_text = match($dcip['status']) {
    'finalizada' => 'Finalizada',
    'em_andamento' => 'Em Andamento',
    'cancelada' => 'Cancelada',
    default => 'Indefinido'
};

$status_class = match($dcip['status']) {
    'finalizada' => 'bg-success',
    'em_andamento' => 'bg-warning',
    'cancelada' => 'bg-danger',
    default => 'bg-secondary'
};
?>

<div class="row g-3">
    <!-- Basic Information -->
    <div class="col-12">
        <h6 class="fw-bold mb-3">Informações Básicas</h6>
        <div class="row g-3">
            <div class="col-md-6">
                <strong>Empresa:</strong><br>
                <span><?php echo htmlspecialchars($dcip['razao_social']); ?></span>
            </div>
            <div class="col-md-6">
                <strong>CNPJ:</strong><br>
                <code><?php echo $dcip['cnpj']; ?></code>
            </div>
            <div class="col-md-6">
                <strong>Inscrição Estadual:</strong><br>
                <span><?php echo $dcip['inscricao_estadual']; ?></span>
            </div>
            <div class="col-md-6">
                <strong>Período de Referência:</strong><br>
                <span class="badge bg-light text-dark">
                    <?php echo substr($dcip['periodo_referencia'], 0, 2) . '/' . substr($dcip['periodo_referencia'], 2); ?>
                </span>
            </div>
            <div class="col-md-6">
                <strong>Status:</strong><br>
                <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
            </div>
            <div class="col-md-6">
                <strong>Data de Criação:</strong><br>
                <span><?php echo date('d/m/Y H:i', strtotime($dcip['created_at'])); ?></span>
            </div>
        </div>
    </div>

    <!-- Credit Summary -->
    <div class="col-12">
        <hr>
        <h6 class="fw-bold mb-3">Resumo dos Créditos</h6>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h6 class="card-title">Saldo Anterior</h6>
                        <h4 class="text-primary">R$ <?php echo number_format($dcip['saldo_creditos_anterior'] ?? 0, 2, ',', '.'); ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h6 class="card-title">Total do Período</h6>
                        <h4 class="text-success">R$ <?php echo number_format($dcip['valor_total_creditos'] ?? 0, 2, ',', '.'); ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h6 class="card-title">Créditos Utilizados</h6>
                        <h4 class="text-warning">R$ <?php echo number_format($dcip['valor_creditos_utilizados'] ?? 0, 2, ',', '.'); ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h6 class="card-title">Saldo Atual</h6>
                        <h4 class="text-info">R$ <?php echo number_format($dcip['saldo_creditos_atual'] ?? 0, 2, ',', '.'); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Credit Details -->
    <?php if (!empty($creditos)): ?>
    <div class="col-12">
        <hr>
        <h6 class="fw-bold mb-3">Detalhamento dos Créditos</h6>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>CFOP</th>
                        <th>Valor</th>
                        <th>Origem</th>
                        <th>Descrição</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($creditos as $credito): ?>
                    <tr>
                        <td><code><?php echo $credito['cfop']; ?></code></td>
                        <td>R$ <?php echo number_format($credito['valor_credito'], 2, ',', '.'); ?></td>
                        <td>
                            <?php
                            $origem_text = match($credito['origem_credito']) {
                                '01' => 'Aquisição de Bens',
                                '02' => 'Energia e Comunicação',
                                '03' => 'Outros',
                                default => 'N/A'
                            };
                            echo $origem_text;
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($credito['descricao'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Additional Information -->
    <?php if ($dcip['informacoes_complementares'] || $dcip['observacoes']): ?>
    <div class="col-12">
        <hr>
        <h6 class="fw-bold mb-3">Informações Adicionais</h6>
        
        <?php if ($dcip['informacoes_complementares']): ?>
        <div class="mb-3">
            <strong>Informações Complementares:</strong><br>
            <div class="bg-light p-3 rounded">
                <?php echo nl2br(htmlspecialchars($dcip['informacoes_complementares'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($dcip['observacoes']): ?>
        <div class="mb-3">
            <strong>Observações:</strong><br>
            <div class="bg-light p-3 rounded">
                <?php echo nl2br(htmlspecialchars($dcip['observacoes'])); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- File Information -->
    <?php if ($dcip['arquivo_hash']): ?>
    <div class="col-12">
        <hr>
        <h6 class="fw-bold mb-3">Arquivo Gerado</h6>
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>
            Arquivo DCIP gerado com sucesso em <?php echo date('d/m/Y H:i', strtotime($dcip['updated_at'])); ?>
            <br>
            <small>Hash: <code><?php echo $dcip['arquivo_hash']; ?></code></small>
        </div>
    </div>
    <?php endif; ?>
</div>
