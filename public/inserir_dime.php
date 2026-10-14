<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../src/app_classes.php';

use App\Models\Usuario;
use App\Models\Empresa;
use App\Models\Contabilista;
use App\Models\DimeDeclaracao;
use App\Models\DimeManualVersao;
use App\Core\DBConnection;

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['user_name']);
$user_email = htmlspecialchars($_SESSION['user_email']);
$user_role = htmlspecialchars($_SESSION['user_role']);
$contabilista_id_session = $_SESSION['contabilista_id'];

$usuarioLogado = Usuario::find($user_id);
if (!$usuarioLogado) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Check payment status for operators
if ($user_role === 'operador' && isset($_SESSION['has_payment_issues']) && $_SESSION['has_payment_issues']) {
    $error_message = 'Acesso à geração de DIME bloqueado devido a pendências financeiras. Entre em contato com o suporte.';
    $access_blocked = true;
} else {
    $access_blocked = false;
}

// Get companies based on user role
$empresas = [];
if ($user_role === 'admin') {
    $empresas = Empresa::all();
} else if ($contabilista_id_session) {
    $empresas = Empresa::where('contabilista_id', $contabilista_id_session);
}

// Get manual versions
$manuais = DimeManualVersao::where('ativo', 1);

$error_message = '';
$success_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$access_blocked) {
    try {
        $empresa_id = $_POST['empresa_id'] ?? '';
        $periodo_referencia = $_POST['periodo_referencia'] ?? '';
        $tipo_declaracao = $_POST['tipo_declaracao'] ?? 'normal';
        $regime_apuracao = $_POST['regime_apuracao'] ?? 'mensal';
        $porte_empresa = $_POST['porte_empresa'] ?? '';
        $movimento = isset($_POST['movimento']) ? 1 : 0;

        // Map string values to integers for database (conforme Manual DIME-SEF 153/12)
        $tipo_declaracao_map = [
            'normal' => 1,
            'encerramento' => 2,
            'simples' => 4
        ];
        $tipo_declaracao_int = $tipo_declaracao_map[$tipo_declaracao] ?? 1;

        $regime_apuracao_map = [
            'mensal' => 2,
            'trimestral' => 2  // Nota: Usando código 2 para ambos por enquanto (conforme manual atual)
        ];
        $regime_apuracao_int = $regime_apuracao_map[$regime_apuracao] ?? 2;

        $porte_empresa_map = [
            'micro' => 1,     // Nota: Manual original diz "não se aplica = 1", mas implementação usa portes específicos
            'pequena' => 2,
            'media' => 3,
            'grande' => 4
        ];
        $porte_empresa_int = $porte_empresa_map[$porte_empresa] ?? 1;

        // Validation
        if (empty($empresa_id) || empty($periodo_referencia) || empty($porte_empresa)) {
            throw new Exception('Por favor, preencha todos os campos obrigatórios.');
        }

        // Validate period format (MM/YYYY)
        if (!preg_match('/^\d{2}\/\d{4}$/', $periodo_referencia)) {
            throw new Exception('Período de referência deve estar no formato MM/AAAA.');
        }

        // Convert period to database format (MMYYYY)
        $periodo_parts = explode('/', $periodo_referencia);
        $periodo_db = $periodo_parts[0] . $periodo_parts[1];

        // Check if declaration already exists for this company and period
        $pdo = DBConnection::getInstance()->getConnection();
        $stmt = $pdo->prepare("SELECT id FROM dime_declaracoes WHERE empresa_id = ? AND periodo_referencia = ?");
        $stmt->execute([$empresa_id, $periodo_db]);
        if ($stmt->fetch()) {
            throw new Exception('Já existe uma declaração DIME para esta empresa no período informado.');
        }

        // Get company and accountant info
        $empresa = Empresa::find($empresa_id);
        if (!$empresa) {
            throw new Exception('Empresa não encontrada.');
        }

        $contabilista_id = ($user_role === 'admin') ? $empresa->contabilista_id : $contabilista_id_session;

        // Get default layout version (most recent active version)
        $versao_manual = DimeManualVersao::where('ativo', 1);
        $layout_versao = !empty($versao_manual) ? $versao_manual[0]->versao_completa : '153/12.29';

        // Create DIME declaration
        $dime = new DimeDeclaracao();
        $dime->empresa_id = $empresa_id;
        $dime->contabilista_id = $contabilista_id;
        $dime->periodo_referencia = $periodo_db;
        $dime->layout_versao = $layout_versao;
        $dime->data_geracao = date('Y-m-d H:i:s');
        $dime->tipo_declaracao = $tipo_declaracao_int;
        $dime->regime_apuracao = $regime_apuracao_int;
        $dime->porte_empresa = $porte_empresa_int;
        $dime->movimento = $movimento;
        $dime->substituto_tributario = isset($_POST['substituto_tributario']) ? 1 : 0;
        $dime->tem_escrita_contabil = isset($_POST['tem_escrita_contabil']) ? 1 : 0;
        $dime->apuracao_consolidada = isset($_POST['apuracao_consolidada']) ? 1 : 0;
        $dime->apuracao_centralizada = isset($_POST['apuracao_centralizada']) ? 1 : 0;
        $dime->transferencia_creditos_periodo = isset($_POST['transferencia_creditos']) ? 1 : 0;
        $dime->tem_creditos_presumidos = isset($_POST['tem_creditos_presumidos']) ? 1 : 0;
        $dime->tem_creditos_incentivos_fiscais = isset($_POST['tem_creditos_incentivos_fiscais']) ? 1 : 0;
        $dime->quantidade_trabalhadores = $_POST['quantidade_trabalhadores'] ?? null;
        $dime->status = 'rascunho';

        if ($dime->save()) {
            // Criar registro inicial no Quadro 00
            try {
                $endereco_completo = trim(($empresa->endereco ?? '') . ', ' . ($empresa->numero ?? ''));
                if (!empty($empresa->complemento)) $endereco_completo .= ', ' . $empresa->complemento;
                if (!empty($empresa->bairro)) $endereco_completo .= ', ' . $empresa->bairro;
                if (empty(trim($endereco_completo, ', '))) $endereco_completo = 'Endereço não informado';

                $municipio_codigo = '4205407';
                if (!empty($empresa->municipio_id)) {
                    $stmtM = $pdo->prepare("SELECT codigo_ibge FROM t_municipios WHERE id = ?");
                    $stmtM->execute([$empresa->municipio_id]);
                    $ibge = $stmtM->fetchColumn();
                    if ($ibge) $municipio_codigo = $ibge;
                }

                $cep_limpo = preg_replace('/\D/', '', $empresa->cep ?? '') ?: '88380000';
                $stmtQ00 = $pdo->prepare("
                    INSERT INTO dime_quadro_00 (
                        dime_declaracao_id, cnpj_empresa, inscricao_estadual, razao_social,
                        nome_fantasia, endereco_completo, municipio_codigo, cep, telefone,
                        email, cnae_principal, regime_tributario,
                        tipo_declaracao, regime_apuracao, porte_empresa, apuracao_consolidada,
                        apuracao_centralizada, transferencia_creditos, tem_creditos_presumidos,
                        tem_creditos_incentivos_fiscais, movimento, substituto_tributario,
                        tem_escrita_contabil, quantidade_trabalhadores
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtQ00->execute([
                    $dime->id,
                    $empresa->cnpj,
                    $empresa->inscricao_estadual,
                    $empresa->razao_social,
                    $empresa->razao_social,
                    $endereco_completo,
                    $municipio_codigo,
                    $cep_limpo,
                    $empresa->telefone ?? '',
                    $empresa->email ?? '',
                    '0081/0',
                    'real',
                    (string)$tipo_declaracao_int,
                    (string)$regime_apuracao_int,
                    (string)$porte_empresa_int,
                    $dime->apuracao_consolidada ? 2 : 1,
                    1,
                    $dime->transferencia_creditos_periodo ? 2 : 1,
                    1,
                    1,
                    $dime->movimento ? 3 : 1,
                    $dime->substituto_tributario ? 1 : 2,
                    $dime->tem_escrita_contabil ? 1 : 2,
                    (int)($dime->quantidade_trabalhadores ?? 0)
                ]);
            } catch (\Exception $e) {
                error_log('Erro ao auto-criar dime_quadro_00: ' . $e->getMessage());
            }

            $success_message = 'Declaração DIME criada com sucesso! Agora você pode preencher os quadros.';
            // Redirect to edit page to fill in the frames (com ID codificado em base64)
            header("Location: editar_dime.php?id=" . base64_encode($dime->id) . "&message=success&text=" . urlencode($success_message));
            exit;
        } else {
            throw new Exception('Erro ao salvar a declaração DIME.');
        }

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

$page_title = 'Nova Declaração DIME-SC';
include '_header.php';
?>

<div class="col-12">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Nova Declaração DIME</h1>
            <p class="text-muted mb-0">Criar uma nova declaração DIME-SC</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Voltar ao Dashboard
            </a>
        </div>
    </div>

    <!-- Error/Success Messages -->
    <?php if ($error_message): ?>
        <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <div><?php echo htmlspecialchars($error_message); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="alert alert-success d-flex align-items-center" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <div><?php echo htmlspecialchars($success_message); ?></div>
        </div>
    <?php endif; ?>

    <!-- DIME Form -->
    <form method="POST" class="needs-validation <?php echo $access_blocked ? 'form-disabled' : ''; ?>" novalidate <?php echo $access_blocked ? 'style="position: relative;"' : ''; ?>>

        <?php if ($access_blocked): ?>
        <!-- Access Blocked Overlay -->
        <div class="access-blocked-overlay">
            <div class="access-blocked-content">
                <i class="bi bi-lock-fill access-blocked-icon"></i>
                <h5 class="access-blocked-title">Acesso Restrito</h5>
                <p class="access-blocked-message">
                    Esta funcionalidade está temporariamente indisponível devido a pendências financeiras.
                </p>
                <small class="text-muted">
                    Entre em contato com o suporte para regularizar sua situação.
                </small>
            </div>
        </div>
        <?php endif; ?>

        <!-- Basic Information Section -->
        <div class="form-section">
            <h4 class="form-section-title">
                <i class="bi bi-info-circle me-2"></i>Informações Básicas
            </h4>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="empresa_id" class="form-label">Empresa *</label>
                    <select class="form-select" id="empresa_id" name="empresa_id" required>
                        <option value="">Selecione uma empresa</option>
                        <?php foreach ($empresas as $empresa): ?>
                            <option value="<?php echo $empresa->id; ?>" 
                                    data-cnpj="<?php echo $empresa->cnpj; ?>"
                                    data-ie="<?php echo $empresa->inscricao_estadual; ?>">
                                <?php echo htmlspecialchars($empresa->razao_social); ?> - <?php echo $empresa->cnpj; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">
                        Por favor, selecione uma empresa.
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="periodo_referencia" class="form-label">Período de Referência *</label>
                    <input type="text" 
                           class="form-control" 
                           id="periodo_referencia" 
                           name="periodo_referencia" 
                           placeholder="MM/AAAA"
                           pattern="\d{2}/\d{4}"
                           maxlength="7"
                           required>
                    <div class="form-text">Formato: MM/AAAA (ex: 12/2023)</div>
                    <div class="invalid-feedback">
                        Por favor, informe o período no formato MM/AAAA.
                    </div>
                </div>

                <div class="col-md-4">
                    <label for="tipo_declaracao" class="form-label">Tipo de Declaração</label>
                    <select class="form-select" id="tipo_declaracao" name="tipo_declaracao">
                        <option value="normal">Normal</option>
                        <option value="encerramento">Encerramento de Atividades</option>
                        <option value="simples">Enquadramento no Simples Nacional</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="regime_apuracao" class="form-label">Regime de Apuração</label>
                    <select class="form-select" id="regime_apuracao" name="regime_apuracao">
                        <option value="mensal">Mensal</option>
                        <option value="trimestral">Trimestral</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="porte_empresa" class="form-label">Porte da Empresa *</label>
                    <select class="form-select" id="porte_empresa" name="porte_empresa" required>
                        <option value="">Selecione o porte</option>
                        <option value="micro">Microempresa</option>
                        <option value="pequena">Pequena Empresa</option>
                        <option value="media">Média Empresa</option>
                        <option value="grande">Grande Empresa</option>
                    </select>
                    <div class="invalid-feedback">
                        Por favor, selecione o porte da empresa.
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="quantidade_trabalhadores" class="form-label">Quantidade de Trabalhadores</label>
                    <input type="number" 
                           class="form-control" 
                           id="quantidade_trabalhadores" 
                           name="quantidade_trabalhadores" 
                           min="0"
                           placeholder="Número de funcionários">
                </div>

                <div class="col-md-6">
                    <!-- Campo removido - versao_manual_id não existe na tabela -->
                </div>
            </div>
        </div>

        <!-- Additional Options Section -->
        <div class="form-section">
            <h4 class="form-section-title">
                <i class="bi bi-gear me-2"></i>Opções Adicionais
            </h4>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="movimento" name="movimento" checked>
                        <label class="form-check-label" for="movimento">
                            <strong>Com movimento no período</strong>
                        </label>
                        <div class="form-text">Desmarque se não houve movimento fiscal no período</div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="tem_escrita_contabil" name="tem_escrita_contabil" checked>
                        <label class="form-check-label" for="tem_escrita_contabil">
                            <strong>Possui escrita contábil</strong>
                        </label>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="substituto_tributario" name="substituto_tributario">
                        <label class="form-check-label" for="substituto_tributario">
                            <strong>Substituto tributário</strong>
                        </label>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="apuracao_consolidada" name="apuracao_consolidada">
                        <label class="form-check-label" for="apuracao_consolidada">
                            <strong>Apuração consolidada</strong>
                        </label>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="apuracao_centralizada" name="apuracao_centralizada">
                        <label class="form-check-label" for="apuracao_centralizada">
                            <strong>Apuração centralizada</strong>
                        </label>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="transferencia_creditos" name="transferencia_creditos">
                        <label class="form-check-label" for="transferencia_creditos">
                            <strong>Transferência de créditos</strong>
                        </label>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="tem_creditos_presumidos" name="tem_creditos_presumidos">
                        <label class="form-check-label" for="tem_creditos_presumidos">
                            <strong>Possui créditos presumidos</strong>
                        </label>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="tem_creditos_incentivos_fiscais" name="tem_creditos_incentivos_fiscais">
                        <label class="form-check-label" for="tem_creditos_incentivos_fiscais">
                            <strong>Possui créditos de incentivos fiscais</strong>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex justify-content-between">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="bi bi-x-circle me-2"></i>Cancelar
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle me-2"></i>Criar Declaração DIME
            </button>
        </div>
    </form>
</div>

<script>
console.log('[InsertDIME]', 'Page loaded');

document.addEventListener('DOMContentLoaded', function() {
    // Period input formatting
    const periodoInput = document.getElementById('periodo_referencia');
    
    periodoInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length >= 2) {
            value = value.substring(0, 2) + '/' + value.substring(2, 6);
        }
        e.target.value = value;
    });

    // Set default period to current month/year
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const year = now.getFullYear();
    periodoInput.value = `${month}/${year}`;

    // Form validation
    const form = document.querySelector('.needs-validation');
    
    form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        form.classList.add('was-validated');
    });

    // Company selection handler
    const empresaSelect = document.getElementById('empresa_id');
    empresaSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            console.log('[InsertDIME]', 'Company selected:', {
                id: selectedOption.value,
                cnpj: selectedOption.dataset.cnpj,
                ie: selectedOption.dataset.ie
            });
        }
    });

    // Movement checkbox handler
    const movimentoCheck = document.getElementById('movimento');
    movimentoCheck.addEventListener('change', function() {
        if (!this.checked) {
            // If no movement, disable some options that don't make sense
            const dependentChecks = [
                'substituto_tributario',
                'transferencia_creditos',
                'tem_creditos_presumidos',
                'tem_creditos_incentivos_fiscais'
            ];
            
            dependentChecks.forEach(id => {
                const checkbox = document.getElementById(id);
                if (checkbox) {
                    checkbox.checked = false;
                    checkbox.disabled = !this.checked;
                }
            });
        } else {
            // Re-enable options
            const dependentChecks = [
                'substituto_tributario',
                'transferencia_creditos',
                'tem_creditos_presumidos',
                'tem_creditos_incentivos_fiscais'
            ];
            
            dependentChecks.forEach(id => {
                const checkbox = document.getElementById(id);
                if (checkbox) {
                    checkbox.disabled = false;
                }
            });
        }
    });
});
</script>

<style>
<?php if ($access_blocked): ?>
/* Access Blocked Overlay Styles */
.access-blocked-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(2px);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: 2px solid #ffc107;
}

.access-blocked-content {
    text-align: center;
    padding: 2rem;
    max-width: 400px;
}

.access-blocked-icon {
    font-size: 3rem;
    color: #ffc107;
    margin-bottom: 1rem;
}

.access-blocked-title {
    color: #856404;
    margin-bottom: 1rem;
    font-weight: 600;
}

.access-blocked-message {
    color: #856404;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.form-disabled {
    pointer-events: none;
    opacity: 0.6;
}
<?php endif; ?>
</style>

<?php include '_footer.php'; ?>
