<?php
session_start();
require_once '../src/app_classes.php';

use App\Core\DBConnection;
use App\Models\Empresa;

// Simple ID obfuscation functions for security
function obfuscateId($id) {
    return base64_encode(($id * 7) + 123);
}

function deobfuscateId($encoded) {
    $decoded = base64_decode($encoded);
    return is_numeric($decoded) ? (($decoded - 123) / 7) : 0;
}

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$contabilista_id_session = $_SESSION['contabilista_id'];

// Obter empresa_id da URL (usando obfuscate)
$empresa_id_param = $_GET['empresa_id'] ?? null;
$empresa_id = $empresa_id_param ? deobfuscateId($empresa_id_param) : null;

if (!$empresa_id || !is_numeric($empresa_id)) {
    header('Location: gerenciar_empresas.php?message=error&text=Empresa não especificada ou inválida');
    exit;
}

// Verificar permissões (contabilistas podem configurar quadros)
if ($user_role !== 'admin') {
    $empresa = Empresa::find($empresa_id);
    if (!$empresa || $empresa->contabilista_id != $contabilista_id_session) {
        header('Location: gerenciar_empresas.php?message=error&text=Acesso negado a esta empresa');
        exit;
    }
}

// Carregar dados da empresa
$empresa = Empresa::find($empresa_id);
if (!$empresa) {
    header('Location: gerenciar_empresas.php?message=error&text=Empresa não encontrada');
    exit;
}

$message = '';
$message_type = '';

$page_title = 'Configurar Quadros - ' . htmlspecialchars($empresa->razao_social);
include '_header.php';

// Função para salvar configuração de quadros
function salvarConfiguracaoQuadros($empresa_id, $quadros, $pdo) {
    global $message, $message_type;
    
    try {
        // Primeiro, desativar todos os quadros para esta empresa
        $pdo->prepare("UPDATE dime_quadro_empresas SET ativo = 0 WHERE empresa_id = ?")->execute([$empresa_id]);

        // Depois, ativar apenas os quadros selecionados
        foreach ($quadros as $quadro_numero) {
            // Verificar se já existe
            $stmt = $pdo->prepare("SELECT id FROM dime_quadro_empresas WHERE empresa_id = ? AND quadro_numero = ?");
            $stmt->execute([$empresa_id, $quadro_numero]);
            
            if ($stmt->rowCount() > 0) {
                // Atualizar
                $pdo->prepare("UPDATE dime_quadro_empresas SET ativo = 1 WHERE empresa_id = ? AND quadro_numero = ?")
                     ->execute([$empresa_id, $quadro_numero]);
            } else {
                // Inserir novo
                $pdo->prepare("INSERT INTO dime_quadro_empresas (empresa_id, quadro_numero, ativo) VALUES (?, ?, 1)")
                     ->execute([$empresa_id, $quadro_numero]);
            }
        }

        $message = 'Configuração de quadros atualizada com sucesso!';
        $message_type = 'success';
    } catch (Exception $e) {
        $message = 'Erro ao atualizar configuração de quadros: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Processar salvamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_quadros') {
    $quadros = $_POST['quadros'] ?? [];
    $pdo = \App\Core\DBConnection::getInstance()->getConnection();
    salvarConfiguracaoQuadros($empresa_id, $quadros, $pdo);
}

// Carregar quadros atuais da empresa
$pdo = \App\Core\DBConnection::getInstance()->getConnection();
$stmt = $pdo->prepare("SELECT quadro_numero, ativo FROM dime_quadro_empresas WHERE empresa_id = ?");
$stmt->execute([$empresa_id]);
$empresaQuadros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtrar apenas quadros ativos
$empresaQuadrosAtivos = array_filter($empresaQuadros, function($quadro) {
    return $quadro['ativo'] == 1;
});
?>
<div class="col-12">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="text-muted mb-0">
                <strong>Empresa:</strong> <?php echo htmlspecialchars($empresa->razao_social); ?> (<?php echo htmlspecialchars($empresa->cnpj); ?>)
            </p>
        </div>
        <div>
            <a href="gerenciar_empresas.php?action=edit&id=<?php echo urlencode(obfuscateId($empresa->id)); ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Voltar para Edição da Empresa
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

    <!-- Configuration Form -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-file-earmark-spreadsheet me-2"></i>Quadros Disponíveis para a Empresa
            </h5>
            <small class="text-muted">Marque os quadros que esta empresa poderá utilizar na geração de DIME</small>
        </div>
        <div class="card-body">
            <form action="configurar_quadros_empresa.php?empresa_id=<?php echo urlencode(obfuscateId($empresa_id)); ?>" method="POST">
                <input type="hidden" name="action" value="update_quadros">

                <!-- Quadros Padrão -->
                <h6 class="mb-3 text-primary">Quadros Padrão do Sistema</h6>
                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Quadros padrão:</strong> 00, 0103, 0203, 0405, 09, 10, 11, 12, 14, 41, 46, 49, 50
                </div>

                <div class="row" id="quadrosList">
                    <!-- Quadros serão carregados via JavaScript -->
                </div>

                <!-- Adicionar Novo Quadro -->
                <div class="mt-4 pt-3 border-top">
                    <h6 class="mb-3 text-success">Adicionar Novo Quadro</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="input-group">
                                <input type="text" class="form-control" id="novoQuadroInput" placeholder="Número do quadro (ex: 51)" maxlength="4">
                                <button class="btn btn-outline-success" type="button" onclick="adicionarNovoQuadro()">
                                    <i class="bi bi-plus-circle me-1"></i>Adicionar
                                </button>
                            </div>
                            <small class="text-muted">Digite apenas números ou letras (máximo 4 caracteres)</small>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>Salvar Configuração
                    </button>
                    <button type="button" class="btn btn-secondary ms-2" onclick="limparSelecao()">
                        <i class="bi bi-arrow-counterclockwise me-2"></i>Limpar Seleção
                    </button>
                    <a href="gerenciar_empresas.php?action=edit&id=<?php echo htmlspecialchars(obfuscateId($empresa->id)); ?>" class="btn btn-outline-secondary ms-2">
                        <i class="bi bi-x-circle me-2"></i>Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Quadros padrão do sistema
const quadrosPadrao = {
    '00': 'Quadro 00 - Identificação',
    '0103': 'Quadro 01/03 - Operações',
    '0203': 'Quadro 02/03 - Operações',
    '0405': 'Quadro 04/05 - Operações',
    '09': 'Quadro 09 - Débitos/Créditos',
    '10': 'Quadro 10 - Créditos',
    '11': 'Quadro 11 - Créditos',
    '12': 'Quadro 12 - Créditos',
    '14': 'Quadro 14 - Crédito Presumido',
    '41': 'Quadro 41 - Créditos Acumulados',
    '46': 'Quadro 46 - Créditos por Regimes',
    '49': 'Quadro 49 - Entradas por UF',
    '50': 'Quadro 50 - Saídas por UF'
};

// Quadros ativos da empresa (passados do PHP)
const quadrosAtivos = <?php echo json_encode(array_column($empresaQuadrosAtivos, 'quadro_numero', 'quadro_numero')); ?>;

// Inicializar quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    console.log('[ConfigurarQuadros] Inicializando...');
    console.log('[ConfigurarQuadros] Empresa ID:', <?php echo $empresa_id; ?>);
    console.log('[ConfigurarQuadros] Quadros ativos:', quadrosAtivos);
    renderizarQuadros();
});

// Renderizar todos os quadros
function renderizarQuadros() {
    const quadrosList = document.getElementById('quadrosList');
    quadrosList.innerHTML = '';

    // Renderizar quadros padrão primeiro
    Object.keys(quadrosPadrao).forEach(codigo => {
        const nome = quadrosPadrao[codigo];
        const isAtivo = quadrosAtivos.hasOwnProperty(codigo) && quadrosAtivos[codigo];

        const colDiv = document.createElement('div');
        colDiv.className = 'col-md-4 mb-3';

        colDiv.innerHTML = `
            <div class="form-check">
                <input class="form-check-input" type="checkbox"
                       id="quadro_${codigo}" name="quadros[]"
                       value="${codigo}" ${isAtivo ? 'checked' : ''}>
                <label class="form-check-label" for="quadro_${codigo}">
                    <strong>${codigo}</strong> - ${nome}
                </label>
            </div>
        `;

        quadrosList.appendChild(colDiv);
    });

    // Renderizar quadros adicionais (não padrão)
    Object.keys(quadrosAtivos).forEach(codigo => {
        if (!quadrosPadrao.hasOwnProperty(codigo)) {
            const nome = `Quadro ${codigo}`;
            const isAtivo = quadrosAtivos[codigo];

            const colDiv = document.createElement('div');
            colDiv.className = 'col-md-4 mb-3';

            colDiv.innerHTML = `
                <div class="form-check">
                    <input class="form-check-input" type="checkbox"
                           id="quadro_${codigo}" name="quadros[]"
                           value="${codigo}" ${isAtivo ? 'checked' : ''}>
                    <label class="form-check-label" for="quadro_${codigo}">
                        <strong>${codigo}</strong> - ${nome}
                        <small class="text-success">(personalizado)</small>
                    </label>
                </div>
            `;

            quadrosList.appendChild(colDiv);
        }
    });

    console.log('[ConfigurarQuadros] Quadros renderizados');
}

// Adicionar novo quadro
function adicionarNovoQuadro() {
    const novoQuadroInput = document.getElementById('novoQuadroInput');
    const numeroQuadro = novoQuadroInput.value.trim();

    if (!numeroQuadro) {
        alert('Por favor, informe o número do quadro.');
        return;
    }

    // Validar formato
    if (!/^[A-Za-z0-9]{1,4}$/.test(numeroQuadro)) {
        alert('Número do quadro deve conter apenas letras e números (máximo 4 caracteres).');
        return;
    }

    // Verificar se já existe
    const existingCheckboxes = document.querySelectorAll('#quadrosList input[type="checkbox"]');
    const exists = Array.from(existingCheckboxes).some(cb => cb.value === numeroQuadro);

    if (exists) {
        alert('Este quadro já está na lista.');
        return;
    }

    // Adicionar à lista
    const quadrosList = document.getElementById('quadrosList');
    const colDiv = document.createElement('div');
    colDiv.className = 'col-md-4 mb-3';

    const nomeQuadro = `Quadro ${numeroQuadro}`;

    colDiv.innerHTML = `
        <div class="form-check">
            <input class="form-check-input" type="checkbox"
                   id="quadro_${numeroQuadro}" name="quadros[]"
                   value="${numeroQuadro}" checked>
            <label class="form-check-label" for="quadro_${numeroQuadro}">
                <strong>${numeroQuadro}</strong> - ${nomeQuadro}
                <small class="text-success">(novo)</small>
            </label>
        </div>
    `;

    quadrosList.appendChild(colDiv);

    // Limpar campo
    novoQuadroInput.value = '';

    console.log(`[ConfigurarQuadros] Novo quadro ${numeroQuadro} adicionado`);
}

// Limpar seleção
function limparSelecao() {
    const checkboxes = document.querySelectorAll('#quadrosList input[type="checkbox"]');
    checkboxes.forEach(cb => cb.checked = false);
    console.log('[ConfigurarQuadros] Seleção limpa');
}
</script>

<?php include '_footer.php'; ?>
