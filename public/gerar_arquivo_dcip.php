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

// Check payment status for operators
if ($user_role === 'operador' && isset($_SESSION['has_payment_issues']) && $_SESSION['has_payment_issues']) {
    $error_message = 'Acesso à geração de DCIP bloqueado devido a pendências financeiras. Entre em contato com o suporte.';
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

$error_message = '';
$success_message = '';

/**
 * DCIP File Generator Class
 * Generates DCIP files according to Anexo III specifications
 */
class DCIPFileGenerator {
    private $empresa;
    private $contabilista;
    private $periodo;
    private $dados;
    
    public function __construct($empresa, $contabilista, $periodo, $dados) {
        $this->empresa = $empresa;
        $this->contabilista = $contabilista;
        $this->periodo = $periodo;
        $this->dados = $dados;
    }
    
    /**
     * Generate complete DCIP file content
     */
    public function generateFile() {
        $content = '';
        
        // Registro 040 - Identificação do Contribuinte
        $content .= $this->generateRegistro040();
        
        // Registro 050 - Período de Apuração
        $content .= $this->generateRegistro050();
        
        // Registro 060 - Informações do Contabilista
        $content .= $this->generateRegistro060();
        
        // Registro 070 - Créditos do ICMS Próprio
        $content .= $this->generateRegistro070();
        
        // Registro 080 - Detalhamento dos Créditos
        foreach ($this->dados['detalhes_creditos'] as $detalhe) {
            $content .= $this->generateRegistro080($detalhe);
        }
        
        // Registro 090 - Utilização dos Créditos
        if (!empty($this->dados['utilizacao_creditos'])) {
            foreach ($this->dados['utilizacao_creditos'] as $utilizacao) {
                $content .= $this->generateRegistro090($utilizacao);
            }
        }
        
        // Registro 100 - Saldo de Créditos
        $content .= $this->generateRegistro100();
        
        // Registro 110 - Informações Complementares
        if (!empty($this->dados['informacoes_complementares'])) {
            $content .= $this->generateRegistro110();
        }
        
        // Registro 140 - Totalizadores
        $content .= $this->generateRegistro140();
        
        // Registro 150 - Observações
        if (!empty($this->dados['observacoes'])) {
            $content .= $this->generateRegistro150();
        }
        
        // Registro 900 - Fim do Arquivo
        $content .= $this->generateRegistro900();
        
        return $content;
    }
    
    private function generateRegistro040() {
        $record = '040'; // Tipo de registro
        $record .= str_pad(preg_replace('/\D/', '', $this->empresa->cnpj), 14, '0', STR_PAD_LEFT);
        $record .= str_pad($this->empresa->inscricao_estadual, 20, ' ');
        $record .= str_pad(substr($this->empresa->razao_social, 0, 60), 60, ' ');
        $record .= str_pad(substr($this->empresa->nome_fantasia ?? '', 0, 60), 60, ' ');
        
        $endereco = $this->empresa->endereco . ', ' . $this->empresa->numero;
        if ($this->empresa->complemento) {
            $endereco .= ', ' . $this->empresa->complemento;
        }
        $record .= str_pad(substr($endereco, 0, 60), 60, ' ');
        $record .= str_pad(substr($this->empresa->bairro, 0, 30), 30, ' ');
        $record .= str_pad(substr($this->empresa->cidade, 0, 30), 30, ' ');
        $record .= $this->empresa->estado;
        $record .= str_pad(preg_replace('/\D/', '', $this->empresa->cep), 8, '0', STR_PAD_LEFT);
        
        return $record . "\r\n";
    }
    
    private function generateRegistro050() {
        $record = '050';
        $record .= $this->periodo; // MMAAAA
        $record .= '1'; // Tipo de período (1=mensal)
        $record .= date('dmY'); // Data de geração
        
        return $record . "\r\n";
    }
    
    private function generateRegistro060() {
        $record = '060';
        $record .= str_pad($this->contabilista->cpf, 11, '0', STR_PAD_LEFT);
        $record .= str_pad(substr($this->contabilista->nome, 0, 60), 60, ' ');
        $record .= str_pad($this->contabilista->crc, 15, ' ');
        
        return $record . "\r\n";
    }
    
    private function generateRegistro070() {
        $record = '070';
        $record .= str_pad(number_format($this->dados['valor_total_creditos'] * 100, 0, '', ''), 15, '0', STR_PAD_LEFT);
        $record .= str_pad(number_format($this->dados['saldo_creditos_anterior'] * 100, 0, '', ''), 15, '0', STR_PAD_LEFT);
        $record .= str_pad(number_format($this->dados['valor_creditos_utilizados'] * 100, 0, '', ''), 15, '0', STR_PAD_LEFT);
        $record .= str_pad(number_format($this->dados['saldo_creditos_atual'] * 100, 0, '', ''), 15, '0', STR_PAD_LEFT);
        
        return $record . "\r\n";
    }
    
    private function generateRegistro080($detalhe) {
        $record = '080';
        $record .= str_pad($detalhe['cfop'], 4, '0', STR_PAD_LEFT);
        $record .= str_pad(number_format($detalhe['valor_credito'] * 100, 0, '', ''), 15, '0', STR_PAD_LEFT);
        $record .= str_pad($detalhe['origem_credito'], 2, '0', STR_PAD_LEFT);
        $record .= str_pad(substr($detalhe['descricao'] ?? '', 0, 100), 100, ' ');
        
        return $record . "\r\n";
    }
    
    private function generateRegistro090($utilizacao) {
        $record = '090';
        $record .= str_pad($utilizacao['periodo_utilizacao'], 6, '0', STR_PAD_LEFT); // MMAAAA
        $record .= str_pad(number_format($utilizacao['valor_utilizado'] * 100, 0, '', ''), 15, '0', STR_PAD_LEFT);
        $record .= str_pad($utilizacao['tipo_utilizacao'], 2, '0', STR_PAD_LEFT);
        
        return $record . "\r\n";
    }
    
    private function generateRegistro100() {
        $record = '100';
        $record .= str_pad(number_format($this->dados['saldo_creditos_atual'] * 100, 0, '', ''), 15, '0', STR_PAD_LEFT);
        $record .= str_pad($this->periodo, 6, '0', STR_PAD_LEFT);
        
        return $record . "\r\n";
    }
    
    private function generateRegistro110() {
        $record = '110';
        $record .= str_pad(substr($this->dados['informacoes_complementares'], 0, 500), 500, ' ');
        
        return $record . "\r\n";
    }
    
    private function generateRegistro140() {
        $record = '140';
        $record .= str_pad(count($this->dados['detalhes_creditos']), 10, '0', STR_PAD_LEFT);
        $record .= str_pad(number_format($this->dados['valor_total_creditos'] * 100, 0, '', ''), 15, '0', STR_PAD_LEFT);
        
        return $record . "\r\n";
    }
    
    private function generateRegistro150() {
        $record = '150';
        $record .= str_pad(substr($this->dados['observacoes'], 0, 500), 500, ' ');
        
        return $record . "\r\n";
    }
    
    private function generateRegistro900() {
        $record = '900';
        $record .= str_pad(preg_replace('/\D/', '', $this->empresa->cnpj), 14, '0', STR_PAD_LEFT);
        $record .= $this->periodo;
        
        return $record . "\r\n";
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$access_blocked) {
    try {
        $empresa_id = $_POST['empresa_id'] ?? '';
        $periodo_referencia = $_POST['periodo_referencia'] ?? '';
        
        if (empty($empresa_id) || empty($periodo_referencia)) {
            throw new Exception('Por favor, preencha todos os campos obrigatórios.');
        }
        
        // Validate period format
        if (!preg_match('/^\d{2}\/\d{4}$/', $periodo_referencia)) {
            throw new Exception('Período deve estar no formato MM/AAAA.');
        }
        
        $periodo_parts = explode('/', $periodo_referencia);
        $periodo_db = $periodo_parts[0] . $periodo_parts[1];
        
        $empresa = Empresa::find($empresa_id);
        if (!$empresa) {
            throw new Exception('Empresa não encontrada.');
        }
        
        $contabilista_id = ($user_role === 'admin') ? $empresa->contabilista_id : $contabilista_id_session;
        $contabilista = Contabilista::find($contabilista_id);
        
        // Collect DCIP data from form
        $dados = [
            'valor_total_creditos' => floatval($_POST['valor_total_creditos'] ?? 0),
            'saldo_creditos_anterior' => floatval($_POST['saldo_creditos_anterior'] ?? 0),
            'valor_creditos_utilizados' => floatval($_POST['valor_creditos_utilizados'] ?? 0),
            'saldo_creditos_atual' => floatval($_POST['saldo_creditos_atual'] ?? 0),
            'detalhes_creditos' => [],
            'utilizacao_creditos' => [],
            'informacoes_complementares' => $_POST['informacoes_complementares'] ?? '',
            'observacoes' => $_POST['observacoes'] ?? ''
        ];
        
        // Process credit details
        if (!empty($_POST['cfop']) && is_array($_POST['cfop'])) {
            for ($i = 0; $i < count($_POST['cfop']); $i++) {
                if (!empty($_POST['cfop'][$i]) && !empty($_POST['valor_credito'][$i])) {
                    $dados['detalhes_creditos'][] = [
                        'cfop' => $_POST['cfop'][$i],
                        'valor_credito' => floatval($_POST['valor_credito'][$i]),
                        'origem_credito' => $_POST['origem_credito'][$i] ?? '01',
                        'descricao' => $_POST['descricao_credito'][$i] ?? ''
                    ];
                }
            }
        }
        
        // Calculate actual current balance
        $dados['saldo_creditos_atual'] = $dados['saldo_creditos_anterior'] + $dados['valor_total_creditos'] - $dados['valor_creditos_utilizados'];
        
        // Generate file
        $generator = new DCIPFileGenerator($empresa, $contabilista, $periodo_db, $dados);
        $fileContent = $generator->generateFile();
        
        // Create ZIP file as required by DCIP specifications
        $tempDir = sys_get_temp_dir();
        $txtFileName = 'DCIP_' . preg_replace('/\D/', '', $empresa->cnpj) . '_' . $periodo_db . '.txt';
        $zipFileName = 'DCIP_' . preg_replace('/\D/', '', $empresa->cnpj) . '_' . $periodo_db . '.zip';
        $txtFilePath = $tempDir . '/' . $txtFileName;
        $zipFilePath = $tempDir . '/' . $zipFileName;
        
        // Write TXT file
        file_put_contents($txtFilePath, $fileContent);
        
        // Create ZIP file
        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($txtFilePath, $txtFileName);
            $zip->close();
            
            // Send ZIP file
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
            header('Content-Length: ' . filesize($zipFilePath));
            
            readfile($zipFilePath);
            
            // Clean up temp files
            unlink($txtFilePath);
            unlink($zipFilePath);
            
            exit;
        } else {
            throw new Exception('Erro ao criar arquivo ZIP.');
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

$page_title = 'Gerar Arquivo DCIP';
include '_header.php';
?>

<div class="col-12">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Gerar Arquivo DCIP</h1>
            <p class="text-muted mb-0">Declaração de Crédito do ICMS Próprio</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Voltar ao Dashboard
            </a>
        </div>
    </div>

    <!-- Error Messages -->
    <?php if ($error_message): ?>
        <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <div><?php echo htmlspecialchars($error_message); ?></div>
        </div>
    <?php endif; ?>

    <!-- DCIP Form -->
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

        <!-- Basic Information -->
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
                            <option value="<?php echo $empresa->id; ?>">
                                <?php echo htmlspecialchars($empresa->razao_social); ?> - <?php echo $empresa->cnpj; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="periodo_referencia" class="form-label">Período de Referência *</label>
                    <input type="text" class="form-control" id="periodo_referencia" name="periodo_referencia" 
                           placeholder="MM/AAAA" pattern="\d{2}/\d{4}" maxlength="7" required>
                </div>
            </div>
        </div>

        <!-- Credit Summary -->
        <div class="form-section">
            <h4 class="form-section-title">
                <i class="bi bi-cash-coin me-2"></i>Resumo dos Créditos
            </h4>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="saldo_creditos_anterior" class="form-label">Saldo de Créditos do Período Anterior</label>
                    <input type="number" step="0.01" class="form-control" id="saldo_creditos_anterior" 
                           name="saldo_creditos_anterior" placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="valor_total_creditos" class="form-label">Valor Total dos Créditos do Período</label>
                    <input type="number" step="0.01" class="form-control" id="valor_total_creditos" 
                           name="valor_total_creditos" placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="valor_creditos_utilizados" class="form-label">Valor dos Créditos Utilizados</label>
                    <input type="number" step="0.01" class="form-control" id="valor_creditos_utilizados" 
                           name="valor_creditos_utilizados" placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="saldo_creditos_atual" class="form-label">Saldo Atual de Créditos</label>
                    <input type="number" step="0.01" class="form-control" id="saldo_creditos_atual" 
                           name="saldo_creditos_atual" placeholder="0,00" readonly>
                </div>
            </div>
        </div>

        <!-- Credit Details -->
        <div class="form-section">
            <h4 class="form-section-title">
                <i class="bi bi-list-ul me-2"></i>Detalhamento dos Créditos
            </h4>
            
            <button type="button" class="btn btn-success mb-3" onclick="adicionarCredito()">
                <i class="bi bi-plus-circle me-2"></i>Adicionar Crédito
            </button>

            <div id="creditos-container">
                <!-- Dynamic credit rows will be added here -->
            </div>
        </div>

        <!-- Additional Information -->
        <div class="form-section">
            <h4 class="form-section-title">
                <i class="bi bi-file-text me-2"></i>Informações Adicionais
            </h4>
            
            <div class="row g-3">
                <div class="col-12">
                    <label for="informacoes_complementares" class="form-label">Informações Complementares</label>
                    <textarea class="form-control" id="informacoes_complementares" name="informacoes_complementares" 
                              rows="3" maxlength="500"></textarea>
                </div>

                <div class="col-12">
                    <label for="observacoes" class="form-label">Observações</label>
                    <textarea class="form-control" id="observacoes" name="observacoes" 
                              rows="3" maxlength="500"></textarea>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex justify-content-between">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="bi bi-x-circle me-2"></i>Cancelar
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-download me-2"></i>Gerar Arquivo DCIP
            </button>
        </div>
    </form>
</div>

<script>
console.log('[GenerateDCIP]', 'Page loaded');

let creditoCounter = 0;

function adicionarCredito() {
    const container = document.getElementById('creditos-container');
    const creditoHtml = `
        <div class="card mb-3" id="credito-${creditoCounter}">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Crédito ${creditoCounter + 1}</h6>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removerCredito(${creditoCounter})">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">CFOP</label>
                        <input type="text" class="form-control" name="cfop[]" placeholder="0000" maxlength="4">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Valor do Crédito</label>
                        <input type="number" step="0.01" class="form-control" name="valor_credito[]" placeholder="0,00">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Origem do Crédito</label>
                        <select class="form-select" name="origem_credito[]">
                            <option value="01">Aquisição de Bens</option>
                            <option value="02">Energia e Comunicação</option>
                            <option value="03">Outros</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Descrição</label>
                        <input type="text" class="form-control" name="descricao_credito[]" maxlength="100">
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', creditoHtml);
    creditoCounter++;
}

function removerCredito(id) {
    const elemento = document.getElementById(`credito-${id}`);
    if (elemento) {
        elemento.remove();
    }
}

function calcularSaldoAtual() {
    const anterior = parseFloat(document.getElementById('saldo_creditos_anterior').value) || 0;
    const total = parseFloat(document.getElementById('valor_total_creditos').value) || 0;
    const utilizados = parseFloat(document.getElementById('valor_creditos_utilizados').value) || 0;
    
    const saldoAtual = anterior + total - utilizados;
    document.getElementById('saldo_creditos_atual').value = saldoAtual.toFixed(2);
}

document.addEventListener('DOMContentLoaded', function() {
    // Set default period
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const year = now.getFullYear();
    document.getElementById('periodo_referencia').value = `${month}/${year}`;

    // Period formatting
    document.getElementById('periodo_referencia').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length >= 2) {
            value = value.substring(0, 2) + '/' + value.substring(2, 6);
        }
        e.target.value = value;
    });

    // Auto-calculate current balance
    ['saldo_creditos_anterior', 'valor_total_creditos', 'valor_creditos_utilizados'].forEach(id => {
        document.getElementById(id).addEventListener('input', calcularSaldoAtual);
    });

    // Form validation
    document.querySelector('.needs-validation').addEventListener('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        this.classList.add('was-validated');
    });

    // Add initial credit row
    adicionarCredito();
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
