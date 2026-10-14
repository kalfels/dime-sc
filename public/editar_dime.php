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

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = htmlspecialchars($_SESSION['user_role']);
$contabilista_id_session = $_SESSION['contabilista_id'];

// Get DIME ID (base64 decoded for obfuscation or direct integer)
$encoded_id = $_GET['id'] ?? '';
$dime_id = is_numeric($encoded_id) ? (int)$encoded_id : base64_decode($encoded_id);
if (!$dime_id || !is_numeric($dime_id)) {
    header('Location: dashboard.php?message=error&text=' . urlencode('ID da DIME inválido.'));
    exit;
}

// Load DIME declaration
$dime = DimeDeclaracao::find($dime_id);
if (!$dime) {
    header('Location: dashboard.php?message=error&text=' . urlencode('Declaração DIME não encontrada.'));
    exit;
}

// Check permissions
if ($user_role !== 'admin' && $dime->contabilista_id != $contabilista_id_session) {
    header('Location: dashboard.php?message=error&text=' . urlencode('Acesso negado a esta declaração.'));
    exit;
}

// Load related data
$empresa = $dime->getEmpresa();
$contabilista = $dime->getContabilista();
if (!$empresa) {
    header('Location: dashboard.php?message=error&text=' . urlencode('Empresa vinculada à declaração não encontrada.'));
    exit;
}

// Carregar quadros habilitados para esta empresa
$pdo = \App\Core\DBConnection::getInstance()->getConnection();
$stmt = $pdo->prepare("SELECT quadro_numero FROM dime_quadro_empresas WHERE empresa_id = ? AND ativo = 1");
$stmt->execute([$empresa->id]);
$quadrosHabilitados = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'quadro_numero');

// Se não há quadros configurados, usar todos (compatibilidade)
if (empty($quadrosHabilitados)) {
    $quadrosHabilitados = ['00', '0103', '0203', '0405', '09', '10', '11', '12', '14', '41', '46', '49', '50'];
}

// Função helper para verificar se quadro está habilitado
function quadroHabilitado($quadro, $quadrosHabilitados) {
    return in_array($quadro, $quadrosHabilitados);
}

$page_title = 'Editar DIME - ' . $empresa->razao_social;
include '_header.php';
?>

<div class="col-12">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Editar Declaração DIME</h1>
            <p class="text-muted mb-0"><?php echo htmlspecialchars($empresa->razao_social); ?> - 
                Período: <?php echo substr($dime->periodo_referencia, 0, 2) . '/' . substr($dime->periodo_referencia, 2, 4); ?>
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-secondary me-2">
                <i class="bi bi-arrow-left me-2"></i>Voltar
            </a>
            <button type="button" class="btn btn-success" onclick="gerarArquivo()">
                <i class="bi bi-download me-2"></i>Gerar Arquivo
            </button>
        </div>

    </div>

    <!-- Quadros Navigation -->
    <div class="card">
        <div class="card-header bg-light">
            <ul class="nav nav-tabs card-header-tabs" id="quadrosTab" role="tablist">
                <?php if (quadroHabilitado('00', $quadrosHabilitados)): ?>
                <li class="nav-item me-2 mb-2" role="presentation">
                    <button class="btn btn-primary active" id="quadro00-tab" data-bs-toggle="tab" data-bs-target="#quadro00" type="button" role="tab">
                        Quadro 00 - Info Inicial
                    </button>
                </li>
                <?php endif; ?>
                <?php if (quadroHabilitado('0103', $quadrosHabilitados)): ?>
                <li class="nav-item me-2 mb-2" role="presentation">
                    <button class="btn btn-outline-primary" id="quadro01-tab" data-bs-toggle="tab" data-bs-target="#quadro01" type="button" role="tab">
                        Quadros 01/03 - Entradas CFOPs
                    </button>
                </li>
                <?php endif; ?>
                <?php if (quadroHabilitado('0203', $quadrosHabilitados)): ?>
                <li class="nav-item me-2 mb-2" role="presentation">
                    <button class="btn btn-outline-primary" id="quadro02-tab" data-bs-toggle="tab" data-bs-target="#quadro02" type="button" role="tab">
                        Quadros 02/03 - Saídas CFOPs
                    </button>
                </li>
                <?php endif; ?>
                <?php if (quadroHabilitado('0405', $quadrosHabilitados)): ?>
                <li class="nav-item me-2 mb-2" role="presentation">
                    <button class="btn btn-outline-primary" id="quadro04-tab" data-bs-toggle="tab" data-bs-target="#quadro04" type="button" role="tab">
                        Quadro 04 - Débitos
                    </button>
                </li>
                <?php endif; ?>
                <?php if (quadroHabilitado('09', $quadrosHabilitados)): ?>
                <li class="nav-item me-2 mb-2" role="presentation">
                    <button class="btn btn-outline-primary" id="quadro05-tab" data-bs-toggle="tab" data-bs-target="#quadro05" type="button" role="tab">
                        Quadro 05 - Créditos
                    </button>
                </li>
                <li class="nav-item me-2 mb-2" role="presentation">
                    <button class="btn btn-outline-primary" id="quadro09-tab" data-bs-toggle="tab" data-bs-target="#quadro09" type="button" role="tab">
                        Quadro 09 - Apuração ICMS
                    </button>
                </li>
                <?php endif; ?>
                <?php if (quadroHabilitado('10', $quadrosHabilitados)): ?>
                <li class="nav-item me-2 mb-2" role="presentation">
                    <button class="btn btn-outline-primary" id="quadro10-tab" data-bs-toggle="tab" data-bs-target="#quadro10" type="button" role="tab">
                        Quadro 10 - Débitos Específicos
                    </button>
                </li>
                <?php endif; ?>
                <?php if (quadroHabilitado('11', $quadrosHabilitados)): ?>
                <li class="nav-item me-2 mb-2" role="presentation">
                    <button class="btn btn-outline-primary" id="quadro11-tab" data-bs-toggle="tab" data-bs-target="#quadro11" type="button" role="tab">
                        Quadro 11 - Créditos Específicos
                    </button>
                </li>
                <?php endif; ?>
                <?php if (quadroHabilitado('12', $quadrosHabilitados)): ?>
                <li class="nav-item me-2 mb-2" role="presentation">
                    <button class="btn btn-outline-primary" id="quadro12-tab" data-bs-toggle="tab" data-bs-target="#quadro12" type="button" role="tab">
                        Quadro 12 - Pagamentos ICMS
                    </button>
                </li>
                <?php endif; ?>
                <?php if (quadroHabilitado('14', $quadrosHabilitados)): ?>
                <li class="nav-item me-2 mb-2" role="presentation">
                    <button class="btn btn-outline-primary" id="quadro14-tab" data-bs-toggle="tab" data-bs-target="#quadro14" type="button" role="tab">
                        Quadro 14 - Crédito Presumido
                    </button>
                </li>
                <?php endif; ?>
                <?php if (quadroHabilitado('41', $quadrosHabilitados)): ?>
                <li class="nav-item me-2 mb-2" role="presentation">
                    <button class="btn btn-outline-primary" id="quadro41-tab" data-bs-toggle="tab" data-bs-target="#quadro41" type="button" role="tab">
                        Quadro 41 - Créditos Acumulados
                    </button>
                </li>
                <?php endif; ?>
                <?php if (quadroHabilitado('46', $quadrosHabilitados)): ?>
                <li class="nav-item me-2 mb-2" role="presentation">
                    <button class="btn btn-outline-primary" id="quadro46-tab" data-bs-toggle="tab" data-bs-target="#quadro46" type="button" role="tab">
                        Quadro 46 - Créditos por Regimes
                    </button>
                </li>
                <?php endif; ?>
                <?php if (quadroHabilitado('49', $quadrosHabilitados)): ?>
                <li class="nav-item me-2 mb-2" role="presentation">
                    <button class="btn btn-outline-primary" id="quadro49-tab" data-bs-toggle="tab" data-bs-target="#quadro49" type="button" role="tab">
                        Quadro 49 - Entradas por UF
                    </button>
                </li>
                <?php endif; ?>
                <?php if (quadroHabilitado('50', $quadrosHabilitados)): ?>
                <li class="nav-item me-2 mb-2" role="presentation">
                    <button class="btn btn-outline-primary" id="quadro50-tab" data-bs-toggle="tab" data-bs-target="#quadro50" type="button" role="tab">
                        Quadro 50 - Saídas por UF
                    </button>
                </li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="card-body">
            <div class="tab-content" id="quadrosTabContent">
                <!-- Conteúdo será carregado via AJAX -->
            </div>
        </div>
    </div>

    <!-- JavaScript para carregamento AJAX -->
    <script>
    // Configuração global para os quadros
    window.DIME_CONFIG = {
        dimeId: <?php echo (int)$dime_id; ?>
    };

    // Quadros habilitados para esta empresa
    const quadrosHabilitados = <?php echo json_encode($quadrosHabilitados); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        // Carregar automaticamente o primeiro quadro habilitado
        const primeiroQuadro = quadrosHabilitados.length > 0 ? quadrosHabilitados[0] : '00';
        loadQuadroContent(primeiroQuadro, <?php echo (int)$dime_id; ?>);
    });

    function loadQuadroContent(quadroId, dimeId) {
        // Mapear IDs dos quadros para nomes dos arquivos
        const quadroMap = {
            'quadro00': '00',
            'quadro01': '0103',
            'quadro02': '0203',
            'quadro04': '0405',
            'quadro05': '09',  // Quadro 05 (Créditos) está dentro do 09_index.php
            'quadro09': '09',  // Quadro 09 (Débitos/Créditos) está no 09_index.php
            'quadro10': '10',
            'quadro11': '11',
            'quadro12': '12',
            'quadro14': '14',  // Quadro 14 - Crédito Presumido
            'quadro41': '41',  // Quadro 41 - Créditos Acumulados
            'quadro46': '46',  // Quadro 46 - Créditos por Regimes e Autorizações Especiais
            'quadro49': '49',  // Quadro 49 - Entradas por Unidade da Federação
            'quadro50': '50'   // Quadro 50 - Saídas por Unidade da Federação
        };

        const fileName = quadroMap[quadroId] || quadroId;

        // Verificar se o quadro está habilitado
        if (!quadrosHabilitados.includes(fileName) && !quadrosHabilitados.includes(quadroId)) {
            const tabContent = document.getElementById('quadrosTabContent');
            tabContent.innerHTML = `
                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Este quadro não está habilitado para esta empresa.
                </div>
            `;
            return;
        }

        // Mostrar loading
        const tabContent = document.getElementById('quadrosTabContent');
        tabContent.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
                <p class="mt-3 text-muted">Carregando conteúdo...</p>
            </div>
        `;

        const filePath = `quadros/${fileName}_index.php?dime_id=${dimeId}`;

        // Fazer requisição AJAX
        fetch(filePath + '&t=' + Date.now()) // Adicionar timestamp para forçar recarregamento
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Erro HTTP: ${response.status} - ${response.statusText}`);
                }
                return response.text();
            })
            .then(data => {
                // Inserir conteúdo na div
                tabContent.innerHTML = data;

                // Executar scripts que podem estar no conteúdo carregado
                const scripts = tabContent.querySelectorAll('script');

                scripts.forEach(script => {
                    if (script.textContent) {
                        // Scripts inline - executar no contexto global
                        try {
                            // Executar o script no contexto global para que as funções fiquem disponíveis
                            (0, eval)(script.textContent);
                        } catch (e) {
                            console.error('Erro ao executar script inline:', e);
                        }
                    } else if (script.src) {
                        // Scripts externos - recarregar
                        const newScript = document.createElement('script');
                        newScript.src = script.src;
                        document.head.appendChild(newScript);
                    }
                });

                // Inicializar componentes Bootstrap se necessário
                if (typeof bootstrap !== 'undefined') {
                    // Re-inicializar tooltips, popovers, etc se necessário
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.map(function (tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                }
            })
            .catch(error => {
                tabContent.innerHTML = `
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Erro ao carregar o conteúdo: ${error.message}
                        <br>
                        <small class="text-muted">Verifique se o arquivo quadros/${fileName}_index.php existe e está acessível.</small>
                        <br>
                        <small class="text-muted">Caminho tentado: ${filePath}</small>
                        <br>
                        <button class="btn btn-sm btn-warning mt-2" onclick="loadQuadroContent('${quadroId}', ${dimeId})">
                            <i class="bi bi-arrow-clockwise me-1"></i>Tentar novamente
                        </button>
                    </div>
                `;
            });
    }

    // Função para recarregar o Quadro 49 (chamada pelos iframes)
    function reloadQuadro49() {
        // Ativar a aba do quadro 49
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(btn => {
            btn.classList.remove('active');
            if (btn.classList.contains('btn-primary')) {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-outline-primary');
            }
        });

        const quadro49Tab = document.getElementById('quadro49-tab');
        if (quadro49Tab) {
            quadro49Tab.classList.add('active');
            if (quadro49Tab.classList.contains('btn-outline-primary')) {
                quadro49Tab.classList.remove('btn-outline-primary');
                quadro49Tab.classList.add('btn-primary');
            }
        }

        // Carregar conteúdo
        loadQuadroContent('quadro49', <?php echo $dime_id; ?>);
    }

    // Função para recarregar o Quadro 50 (chamada pelos iframes)
    function reloadQuadro50() {
        // Ativar a aba do quadro 50
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(btn => {
            btn.classList.remove('active');
            if (btn.classList.contains('btn-primary')) {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-outline-primary');
            }
        });

        const quadro50Tab = document.getElementById('quadro50-tab');
        if (quadro50Tab) {
            quadro50Tab.classList.add('active');
            if (quadro50Tab.classList.contains('btn-outline-primary')) {
                quadro50Tab.classList.remove('btn-outline-primary');
                quadro50Tab.classList.add('btn-primary');
            }
        }

        // Carregar conteúdo
        loadQuadroContent('quadro50', <?php echo $dime_id; ?>);
    }

    // Função para gerar arquivo DIME
    function gerarArquivo() {
        const dimeId = '<?php echo base64_encode($dime_id); ?>';
        window.location.href = `gerar_arquivo_dime.php?id=${dimeId}`;
    }
    document.addEventListener('click', function(e) {
        if (e.target.matches('[data-bs-toggle="tab"]')) {
            e.preventDefault();

            // Remover classe active de todos os botões
            document.querySelectorAll('[data-bs-toggle="tab"]').forEach(btn => {
                btn.classList.remove('active');
                // Se o botão era primary, tornar outline
                if (btn.classList.contains('btn-primary')) {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-outline-primary');
                }
            });

            // Adicionar classe active na aba clicada
            e.target.classList.add('active');
            // Se o botão era outline, tornar primary
            if (e.target.classList.contains('btn-outline-primary')) {
                e.target.classList.remove('btn-outline-primary');
                e.target.classList.add('btn-primary');
            }

            // Carregar conteúdo do quadro
            const targetId = e.target.getAttribute('data-bs-target').substring(1); // Remove o #

            // Verificar se é uma aba interna (não precisa carregar AJAX)
            const abasInternas = ['debitos', 'creditos', 'outras-info', 'dados-basicos', 'debitos-st', 'creditos-st', 'apuracao-st', 'abaA', 'abaB', 'abaC'];
            if (abasInternas.includes(targetId)) {
                // Deixar o Bootstrap Tab funcionar normalmente
                return;
            }

            // Se não é aba interna, carregar conteúdo via AJAX
            loadQuadroContent(targetId, <?php echo $dime_id; ?>);
        }
    });
    </script>

<?php include '_footer.php'; ?>
