<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../src/app_classes.php'; // Inclui as classes do projeto

use App\Core\DBConnection;
use App\Models\Usuario;
use App\Models\Empresa;
use App\Models\DimeDeclaracao;
use App\Models\Contabilista;

// Verifica se o usuário está logado, caso contrário, redireciona para o login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['user_name']);
$user_email = htmlspecialchars($_SESSION['user_email']);
$user_role = htmlspecialchars($_SESSION['user_role']);
$contabilista_id_session = $_SESSION['contabilista_id']; // ID do contabilista logado, se houver

$usuarioLogado = Usuario::find($user_id);
if (!$usuarioLogado) {
    // Usuário não encontrado no DB, logout por segurança
    session_destroy();
    header('Location: index.php');
    exit;
}

// Verifica se o usuário tem acesso ao sistema
if (!$usuarioLogado->hasAccess()) {
    if ($usuarioLogado->role === Usuario::ROLE_OPERADOR && (isset($usuarioLogado->primeiro_acesso) && $usuarioLogado->primeiro_acesso == 1)) {
        // Primeiro acesso - redireciona para configuração da conta
        header('Location: setup_conta.php');
        exit;
    } else {
        // Outro motivo de acesso negado - redireciona para o login
        session_destroy();
        header('Location: index.php?error=access_denied');
        exit;
    }
}

// Lógica para obter as opções dos filtros
$empresasParaFiltro = [];
$inscricoesEstaduais = [];
if ($user_role === 'admin') {
    // Admin vê todas as empresas
    $empresasParaFiltro = Empresa::all();
    $inscricoesEstaduais = Empresa::fetchDistinct('inscricao_estadual');
} else if ($contabilista_id_session) {
    // Contabilista vê apenas suas empresas
    $empresasParaFiltro = Empresa::where('contabilista_id', $contabilista_id_session);
    $inscricoesEstaduais = Empresa::fetchDistinct('inscricao_estadual', [['contabilista_id', '=', $contabilista_id_session]]);
}

$meses = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
    '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
    '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
];

// Obter anos dinamicamente das declarações DIME existentes
$anosDeclaracoes = DimeDeclaracao::fetchDistinct("SUBSTRING(periodo_referencia, 3, 4)");
if (empty($anosDeclaracoes)) {
    // Se não houver declarações, sugere um range padrão
    $currentYear = date('Y');
    $anosDeclaracoes = range($currentYear - 5, $currentYear + 1);
} else {
    // Garante que os anos sejam inteiros e ordenados
    $anosDeclaracoes = array_map('intval', $anosDeclaracoes);
    sort($anosDeclaracoes);
}

// Valores de filtro selecionados (GET)
$filtro_inscricao = $_GET['inscricao_estadual'] ?? '';
$filtro_mes = $_GET['mes'] ?? '';
$filtro_ano = $_GET['ano'] ?? '';

// Construção das condições WHERE para a consulta das DIMEs
$whereConditions = [];
if (!empty($filtro_inscricao)) {
    // Precisamos da empresa_id a partir da inscricao_estadual
    $empresaFiltro = Empresa::where('inscricao_estadual', $filtro_inscricao);
    if (!empty($empresaFiltro)) {
        $whereConditions[] = ['t1.empresa_id', '=', $empresaFiltro[0]->id];
    }
}
if (!empty($filtro_mes)) {
    $whereConditions[] = ["SUBSTRING(t1.periodo_referencia, 1, 2)", '=', $filtro_mes];
}
if (!empty($filtro_ano)) {
    $whereConditions[] = ["SUBSTRING(t1.periodo_referencia, 3, 4)", '=', $filtro_ano];
}

// Se o usuário não for admin, restringe as declarações ao seu contabilista_id
if ($user_role !== 'admin' && $contabilista_id_session) {
    $whereConditions[] = ['t1.contabilista_id', '=', $contabilista_id_session];
}

// Consulta principal para obter as declarações DIME com informações da empresa e do contabilista
$dimes = DimeDeclaracao::fetchWithJoin(
    [
        't1.id AS dime_id',
        'e.razao_social',
        'e.inscricao_estadual',
        'SUBSTRING(t1.periodo_referencia, 1, 2) AS mes_referencia',
        'SUBSTRING(t1.periodo_referencia, 3, 4) AS ano_referencia',
        'c.cpf AS cpf_contador',
        't1.created_at AS data_preenchimento',
        't1.updated_at AS data_geracao'
    ],
    [
        ['INNER JOIN', Empresa::getTableName() . ' e', 't1.empresa_id = e.id'],
        ['INNER JOIN', Contabilista::getTableName() . ' c', 't1.contabilista_id = c.id']
    ],
    $whereConditions,
    'ano_referencia DESC, mes_referencia DESC'
);

// Função para logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Variáveis para o layout
$page_title = 'Dashboard - Sistema DIME-SC';

// Inclui o template do layout
include '_header.php';

// Initialize PDO connection
$pdo = DBConnection::getInstance()->getConnection();

// Get DCIP statistics
$dcipSql = "SELECT COUNT(*) as total_dcips, 
                   COUNT(CASE WHEN dd.status = 'finalizada' THEN 1 END) as dcips_finalizadas,
                   COUNT(CASE WHEN RIGHT(dd.periodo_referencia, 4) = ? THEN 1 END) as dcips_este_ano
            FROM dcip_declaracoes dd";

if ($user_role !== 'admin') {
    $dcipSql .= " INNER JOIN empresas e ON dd.empresa_id = e.id WHERE e.contabilista_id = ?";
    $dcipStmt = $pdo->prepare($dcipSql);
    $dcipStmt->execute([date('Y'), $contabilista_id_session]);
} else {
    $dcipStmt = $pdo->prepare($dcipSql);
    $dcipStmt->execute([date('Y')]);
}

$dcipStats = $dcipStmt->fetch(PDO::FETCH_ASSOC);

// Calculate statistics
$totalDimes = count($dimes);
$totalEmpresas = count($empresasParaFiltro);
$dimesEsteAno = array_filter($dimes, function($dime) {
    return $dime['ano_referencia'] == date('Y');
});
$totalDimesEsteAno = count($dimesEsteAno);
?>
<div class="col-12">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Dashboard</h1>
            <p class="text-muted mb-0">Visão geral do sistema DIME-SC</p>
        </div>
        <div>
            <div class="btn-group">
                <?php if (!isset($_SESSION['has_payment_issues']) || !$_SESSION['has_payment_issues'] || $_SESSION['is_admin']): ?>
                <a href="inserir_dime.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Nova DIME
                </a>
                <a href="gerar_arquivo_dcip.php" class="btn btn-success">
                    <i class="bi bi-file-earmark-plus me-2"></i>Nova DCIP
                </a>
                <?php else: ?>
                <button class="btn btn-primary" disabled title="Funcionalidade indisponível devido a pendências financeiras">
                    <i class="bi bi-plus-circle me-2"></i>Nova DIME <i class="bi bi-lock-fill ms-1"></i>
                </button>
                <button class="btn btn-success" disabled title="Funcionalidade indisponível devido a pendências financeiras">
                    <i class="bi bi-file-earmark-plus me-2"></i>Nova DCIP <i class="bi bi-lock-fill ms-1"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payment Warning Alert -->
    <?php include 'includes/payment_warning.php'; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card dashboard-card text-white">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <i class="bi bi-file-earmark-text card-icon"></i>
                    </div>
                    <div>
                        <div class="card-title">Total DIMEs</div>
                        <div class="card-value"><?php echo $totalDimes; ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card dashboard-card text-white" style="background: linear-gradient(135deg, #198754 0%, #157347 100%);">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <i class="bi bi-building card-icon"></i>
                    </div>
                    <div>
                        <div class="card-title">Empresas</div>
                        <div class="card-value"><?php echo $totalEmpresas; ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card dashboard-card text-white" style="background: linear-gradient(135deg, #fd7e14 0%, #e55a00 100%);">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <i class="bi bi-calendar-event card-icon"></i>
                    </div>
                    <div>
                        <div class="card-title">DIMEs <?php echo date('Y'); ?></div>
                        <div class="card-value"><?php echo $totalDimesEsteAno; ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card dashboard-card text-white" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <i class="bi bi-file-earmark-check card-icon"></i>
                    </div>
                    <div>
                        <div class="card-title">DCIPs Total</div>
                        <div class="card-value"><?php echo $dcipStats['total_dcips'] ?? 0; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightning-charge me-2"></i>Ações Rápidas</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <?php if (!isset($_SESSION['has_payment_issues']) || !$_SESSION['has_payment_issues'] || $_SESSION['is_admin']): ?>
                                <a href="inserir_dime.php" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-plus-circle me-2"></i>Nova DIME
                                </a>
                            <?php else: ?>
                                <button class="btn btn-outline-primary w-100" disabled title="Funcionalidade indisponível devido a pendências financeiras">
                                    <i class="bi bi-plus-circle me-2"></i>Nova DIME <i class="bi bi-lock-fill ms-1"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <?php if (!isset($_SESSION['has_payment_issues']) || !$_SESSION['has_payment_issues'] || $_SESSION['is_admin']): ?>
                                <a href="gerar_arquivo_dime.php" class="btn btn-outline-success w-100">
                                    <i class="bi bi-download me-2"></i>Gerar Arquivo DIME
                                </a>
                            <?php else: ?>
                                <button class="btn btn-outline-success w-100" disabled title="Funcionalidade indisponível devido a pendências financeiras">
                                    <i class="bi bi-download me-2"></i>Gerar Arquivo DIME <i class="bi bi-lock-fill ms-1"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                        <?php if (isset($usuarioLogado->acesso_dcip) && $usuarioLogado->acesso_dcip == 1): ?>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="gerar_arquivo_dcip.php" class="btn btn-outline-info w-100">
                                <i class="bi bi-file-earmark-plus me-2"></i>Nova DCIP
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="listar_dcip.php" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-list-ul me-2"></i>Listar DCIPs
                            </a>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="gerenciar_empresas.php" class="btn btn-outline-warning w-100">
                                <i class="bi bi-building me-2"></i>Gerenciar Empresas
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filtros</h5>
        </div>
        <div class="card-body">
            <form action="dashboard.php" method="GET">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="inscricao_estadual" class="form-label">Inscrição Estadual</label>
                        <select id="inscricao_estadual" name="inscricao_estadual" class="form-select">
                            <option value="">Todas</option>
                            <?php foreach ($inscricoesEstaduais as $ie): ?>
                                <option value="<?php echo htmlspecialchars($ie); ?>" <?php echo ($filtro_inscricao === $ie) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ie); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="mes" class="form-label">Mês</label>
                        <select id="mes" name="mes" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach ($meses as $num => $nome): ?>
                                <option value="<?php echo htmlspecialchars($num); ?>" <?php echo ($filtro_mes === $num) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($nome); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="ano" class="form-label">Ano</label>
                        <select id="ano" name="ano" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach ($anosDeclaracoes as $ano): ?>
                                <option value="<?php echo htmlspecialchars($ano); ?>" <?php echo ($filtro_ano == $ano) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ano); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-search me-2"></i>Filtrar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- DIME Declarations Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-table me-2"></i>Declarações DIME</h5>
            <span class="badge bg-primary"><?php echo $totalDimes; ?> registros</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Razão Social</th>
                            <th>Inscrição Estadual</th>
                            <th>Período</th>
                            <th>CPF Contador</th>
                            <th>Preenchimento</th>
                            <th>Última Atualização</th>
                            <th width="200">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dimes)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                        Nenhuma declaração DIME encontrada com os filtros aplicados.
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($dimes as $dime): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($dime['razao_social'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($dime['inscricao_estadual'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold"><?php echo htmlspecialchars($meses[$dime['mes_referencia']] ?? $dime['mes_referencia']); ?>/<?php echo htmlspecialchars($dime['ano_referencia'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo htmlspecialchars($dime['cpf_contador'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($dime['data_preenchimento']))); ?></small>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($dime['data_geracao']))); ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="visualizar_dime.php?id=<?php echo $dime['dime_id']; ?>" 
                                               class="btn btn-outline-primary btn-action"
                                               data-bs-toggle="tooltip" title="Visualizar">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="editar_dime.php?id=<?php echo base64_encode($dime['dime_id']); ?>" 
                                               class="btn btn-outline-warning btn-action"
                                               data-bs-toggle="tooltip" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="gerar_arquivo_dime.php?id=<?php echo base64_encode($dime['dime_id']); ?>" 
                                               class="btn btn-outline-success btn-action"
                                               data-bs-toggle="tooltip" title="Gerar TXT">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
include '_footer.php';
?>