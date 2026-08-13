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

$dime_id = intval($_GET['id']);
$pdo = DBConnection::getInstance()->getConnection();

// Get DIME details with permission check
if ($user_role === 'admin') {
    $sql = "SELECT dd.*, e.razao_social, e.cnpj, e.inscricao_estadual, c.nome as contabilista_nome
            FROM dime_declaracoes dd
            LEFT JOIN empresas e ON dd.empresa_id = e.id
            LEFT JOIN contabilistas c ON e.contabilista_id = c.id
            WHERE dd.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dime_id]);
} else {
    $sql = "SELECT dd.*, e.razao_social, e.cnpj, e.inscricao_estadual, c.nome as contabilista_nome
            FROM dime_declaracoes dd
            LEFT JOIN empresas e ON dd.empresa_id = e.id
            LEFT JOIN contabilistas c ON e.contabilista_id = c.id
            WHERE dd.id = ? AND e.contabilista_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dime_id, $contabilista_id_session]);
}

$dime = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dime) {
    http_response_code(404);
    exit('DIME not found');
}

$status_text = match($dime['status_declaracao']) {
    'transmitida' => 'Transmitida',
    'processada' => 'Processada',
    'validada' => 'Validada',
    'rascunho' => 'Rascunho',
    'rejeitada' => 'Rejeitada',
    default => 'Indefinido'
};

$status_class = match($dime['status_declaracao']) {
    'transmitida' => 'bg-success',
    'processada' => 'bg-success',
    'validada' => 'bg-info',
    'rascunho' => 'bg-warning',
    'rejeitada' => 'bg-danger',
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
                <span><?php echo htmlspecialchars($dime['razao_social']); ?></span>
            </div>
            <div class="col-md-6">
                <strong>CNPJ:</strong><br>
                <code><?php echo $dime['cnpj']; ?></code>
            </div>
            <div class="col-md-6">
                <strong>Inscrição Estadual:</strong><br>
                <span><?php echo $dime['inscricao_estadual']; ?></span>
            </div>
            <div class="col-md-6">
                <strong>Período de Referência:</strong><br>
                <span class="badge bg-light text-dark">
                    <?php echo substr($dime['periodo_referencia'], 5, 2) . '/' . substr($dime['periodo_referencia'], 0, 4); ?>
                </span>
            </div>
            <div class="col-md-6">
                <strong>Tipo de Declaração:</strong><br>
                <span class="badge bg-secondary"><?php echo ucfirst($dime['tipo_declaracao']); ?></span>
            </div>
            <div class="col-md-6">
                <strong>Status:</strong><br>
                <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
            </div>
            <div class="col-md-6">
                <strong>Regime de Apuração:</strong><br>
                <span><?php echo ucfirst($dime['regime_apuracao']); ?></span>
            </div>
            <div class="col-md-6">
                <strong>Porte da Empresa:</strong><br>
                <span><?php echo ucfirst($dime['porte_empresa']); ?></span>
            </div>
            <div class="col-md-6">
                <strong>Data de Criação:</strong><br>
                <span><?php echo date('d/m/Y H:i', strtotime($dime['created_at'])); ?></span>
            </div>
        </div>
    </div>

    <!-- Configuration Information -->
    <div class="col-12">
        <hr>
        <h6 class="fw-bold mb-3">Configurações da Declaração</h6>
        <div class="row g-3">
            <div class="col-md-6">
                <strong>Apuração Consolidada:</strong><br>
                <span><?php echo $dime['apuracao_consolidada'] ? 'Sim' : 'Não'; ?></span>
            </div>
            <div class="col-md-6">
                <strong>Apuração Centralizada:</strong><br>
                <span><?php echo $dime['apuracao_centralizada'] ? 'Sim' : 'Não'; ?></span>
            </div>
            <div class="col-md-6">
                <strong>Transferência de Créditos:</strong><br>
                <span><?php echo $dime['transferencia_creditos'] ? 'Sim' : 'Não'; ?></span>
            </div>
            <div class="col-md-6">
                <strong>Créditos Presumidos:</strong><br>
                <span><?php echo $dime['tem_creditos_presumidos'] ? 'Sim' : 'Não'; ?></span>
            </div>
            <div class="col-md-6">
                <strong>Créditos Incentivos Fiscais:</strong><br>
                <span><?php echo $dime['tem_creditos_incentivos_fiscais'] ? 'Sim' : 'Não'; ?></span>
            </div>
            <div class="col-md-6">
                <strong>Substituto Tributário:</strong><br>
                <span><?php echo $dime['substituto_tributario'] ? 'Sim' : 'Não'; ?></span>
            </div>
            <div class="col-md-6">
                <strong>Escrita Contábil:</strong><br>
                <span><?php echo $dime['tem_escrita_contabil'] ? 'Sim' : 'Não'; ?></span>
            </div>
            <div class="col-md-6">
                <strong>Movimento:</strong><br>
                <span><?php echo $dime['movimento'] ? 'Com Movimento' : 'Sem Movimento'; ?></span>
            </div>
            <?php if ($dime['quantidade_trabalhadores']): ?>
            <div class="col-md-6">
                <strong>Quantidade de Trabalhadores:</strong><br>
                <span><?php echo $dime['quantidade_trabalhadores']; ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- File Information -->
    <?php if ($dime['hash_arquivo']): ?>
    <div class="col-12">
        <hr>
        <h6 class="fw-bold mb-3">Arquivo Gerado</h6>
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>
            Arquivo DIME gerado com sucesso em <?php echo date('d/m/Y H:i', strtotime($dime['updated_at'])); ?>
            <br>
            <small>Hash: <code><?php echo $dime['hash_arquivo']; ?></code></small>
        </div>
    </div>
    <?php endif; ?>

    <!-- Transmission Information -->
    <?php if ($dime['data_transmissao']): ?>
    <div class="col-12">
        <hr>
        <h6 class="fw-bold mb-3">Informações de Transmissão</h6>
        <div class="row g-3">
            <div class="col-md-6">
                <strong>Data de Transmissão:</strong><br>
                <span><?php echo date('d/m/Y H:i', strtotime($dime['data_transmissao'])); ?></span>
            </div>
            <?php if ($dime['protocolo_receita']): ?>
            <div class="col-md-6">
                <strong>Protocolo da Receita:</strong><br>
                <code><?php echo $dime['protocolo_receita']; ?></code>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
