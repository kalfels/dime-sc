<?php
// Verificar se está sendo chamado diretamente ou via AJAX
$dime_id_raw = $_GET['dime_id'] ?? '';
$dime_id = is_numeric($dime_id_raw) ? (int)$dime_id_raw : (int)base64_decode($dime_id_raw);

if (!$dime_id) {
    header('HTTP/1.1 400 Bad Request');
    echo '<div class="alert alert-danger">Parâmetro dime_id obrigatório e deve ser válido.</div>';
    exit;
}

// Conectar ao banco
require_once __DIR__ . '/../includes/config.php';

// Função helper para formatar CNPJ
function formatar_cnpj($cnpj) {
    $cnpj = preg_replace('/\D/', '', (string)$cnpj);
    if (strlen($cnpj) === 14) {
        return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $cnpj);
    }
    return $cnpj;
}

// Função helper para valores seguros
function safe_value($data, $key, $default = '') {
    return ($data && isset($data[$key]) && $data[$key] !== '') ? htmlspecialchars($data[$key]) : $default;
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Carregar declaração DIME e dados da empresa associada
    $stmtDecl = $pdo->prepare("
        SELECT d.*, 
               e.cnpj, e.razao_social, e.inscricao_estadual, e.endereco, e.numero, 
               e.complemento, e.bairro, e.cep, e.telefone, e.email, e.municipio_id
        FROM dime_declaracoes d
        JOIN empresas e ON e.id = d.empresa_id
        WHERE d.id = ?
    ");
    $stmtDecl->execute([$dime_id]);
    $dime_declaracao = $stmtDecl->fetch(PDO::FETCH_ASSOC);

    if (!$dime_declaracao) {
        echo '<div class="alert alert-danger">Declaração DIME #' . htmlspecialchars($dime_id) . ' ou empresa associada não encontrada.</div>';
        exit;
    }

    // Carregar dados existentes do Quadro 00
    $stmt = $pdo->prepare("SELECT * FROM dime_quadro_00 WHERE dime_declaracao_id = ?");
    $stmt->execute([$dime_id]);
    $quadro00_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Se o Quadro 00 não existir no banco, inicializá-lo com dados da empresa e declaração
    if (!$quadro00_data) {
        $endereco_completo = trim(($dime_declaracao['endereco'] ?? '') . ', ' . ($dime_declaracao['numero'] ?? ''));
        if (!empty($dime_declaracao['complemento'])) $endereco_completo .= ', ' . $dime_declaracao['complemento'];
        if (!empty($dime_declaracao['bairro'])) $endereco_completo .= ', ' . $dime_declaracao['bairro'];
        if (empty(trim($endereco_completo, ', '))) $endereco_completo = 'Endereço não informado';

        $municipio_codigo = '4205407';
        if (!empty($dime_declaracao['municipio_id'])) {
            $stmtM = $pdo->prepare("SELECT codigo_ibge FROM t_municipios WHERE id = ?");
            $stmtM->execute([$dime_declaracao['municipio_id']]);
            $ibge = $stmtM->fetchColumn();
            if ($ibge) $municipio_codigo = $ibge;
        }

        $cep_limpo = preg_replace('/\D/', '', $dime_declaracao['cep'] ?? '') ?: '88380000';
        $tipo_dec = (string)($dime_declaracao['tipo_declaracao'] ?? '1');
        $regime_ap = (string)($dime_declaracao['regime_apuracao'] ?? '2');
        $porte_emp = (string)($dime_declaracao['porte_empresa'] ?? '1');
        $apur_cons = $dime_declaracao['apuracao_consolidada'] ? 2 : 1;
        $apur_cent = 1;
        $transf_cred = $dime_declaracao['transferencia_creditos_periodo'] ? 2 : 1;
        $cred_pres = 1;
        $cred_inc = 1;
        $mov = $dime_declaracao['movimento'] ? 3 : 1;
        $subst_trib = $dime_declaracao['substituto_tributario'] ? 1 : 2;
        $escr_cont = $dime_declaracao['tem_escrita_contabil'] ? 1 : 2;
        $qtd_trab = (int)($dime_declaracao['quantidade_trabalhadores'] ?? 0);

        $sqlInit = "INSERT INTO dime_quadro_00 (
            dime_declaracao_id, cnpj_empresa, inscricao_estadual, razao_social,
            nome_fantasia, endereco_completo, municipio_codigo, cep, telefone,
            email, cnae_principal, regime_tributario,
            tipo_declaracao, regime_apuracao, porte_empresa, apuracao_consolidada,
            apuracao_centralizada, transferencia_creditos, tem_creditos_presumidos,
            tem_creditos_incentivos_fiscais, movimento, substituto_tributario,
            tem_escrita_contabil, quantidade_trabalhadores
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmtInit = $pdo->prepare($sqlInit);
        $stmtInit->execute([
            $dime_id,
            $dime_declaracao['cnpj'],
            $dime_declaracao['inscricao_estadual'],
            $dime_declaracao['razao_social'],
            $dime_declaracao['razao_social'],
            $endereco_completo,
            $municipio_codigo,
            $cep_limpo,
            $dime_declaracao['telefone'] ?? '',
            $dime_declaracao['email'] ?? '',
            '0081/0',
            'real',
            $tipo_dec,
            $regime_ap,
            $porte_emp,
            $apur_cons,
            $apur_cent,
            $transf_cred,
            $cred_pres,
            $cred_inc,
            $mov,
            $subst_trib,
            $escr_cont,
            $qtd_trab
        ]);

        $stmt = $pdo->prepare("SELECT * FROM dime_quadro_00 WHERE dime_declaracao_id = ?");
        $stmt->execute([$dime_id]);
        $quadro00_data = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Verificar se é POST (salvar dados)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_submitted'])) {
        $cnpj_salvar = !empty($_POST['cnpj_empresa']) ? preg_replace('/\D/', '', $_POST['cnpj_empresa']) : $dime_declaracao['cnpj'];
        $ie_salvar = !empty($_POST['inscricao_estadual']) ? $_POST['inscricao_estadual'] : $dime_declaracao['inscricao_estadual'];
        $razao_salvar = !empty($_POST['razao_social']) ? $_POST['razao_social'] : $dime_declaracao['razao_social'];

        $campos = [
            'cnpj_empresa' => $cnpj_salvar,
            'inscricao_estadual' => $ie_salvar,
            'razao_social' => $razao_salvar,
            'tipo_declaracao' => $_POST['tipo_declaracao'] ?? '1',
            'regime_apuracao' => $_POST['regime_apuracao'] ?? '2',
            'porte_empresa' => $_POST['porte_empresa'] ?? '1',
            'apuracao_consolidada' => $_POST['apuracao_consolidada'] ?? '1',
            'apuracao_centralizada' => $_POST['apuracao_centralizada'] ?? '1',
            'transferencia_creditos' => $_POST['transferencia_creditos'] ?? '1',
            'tem_creditos_presumidos' => $_POST['tem_creditos_presumidos'] ?? '1',
            'tem_creditos_incentivos_fiscais' => $_POST['tem_creditos_incentivos_fiscais'] ?? '1',
            'movimento' => $_POST['movimento'] ?? '3',
            'substituto_tributario' => $_POST['substituto_tributario'] ?? '2',
            'tem_escrita_contabil' => $_POST['tem_escrita_contabil'] ?? '1',
            'quantidade_trabalhadores' => $_POST['quantidade_trabalhadores'] ?? '0'
        ];

        // Verificar se já existe registro
        $stmtEx = $pdo->prepare("SELECT id FROM dime_quadro_00 WHERE dime_declaracao_id = ?");
        $stmtEx->execute([$dime_id]);
        $existing = $stmtEx->fetch();

        if ($existing) {
            // Update
            $sql = "UPDATE dime_quadro_00 SET ";
            $updates = [];
            foreach ($campos as $campo => $valor) {
                $updates[] = "$campo = ?";
            }
            $sql .= implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE dime_declaracao_id = ?";

            $stmt = $pdo->prepare($sql);
            $params = array_values($campos);
            $params[] = $dime_id;
            $stmt->execute($params);
        } else {
            // Insert completo com campos obrigatórios
            $endereco_completo = trim(($dime_declaracao['endereco'] ?? '') . ', ' . ($dime_declaracao['numero'] ?? ''));
            if (!empty($dime_declaracao['complemento'])) $endereco_completo .= ', ' . $dime_declaracao['complemento'];
            if (!empty($dime_declaracao['bairro'])) $endereco_completo .= ', ' . $dime_declaracao['bairro'];
            if (empty(trim($endereco_completo, ', '))) $endereco_completo = 'Endereço não informado';

            $municipio_codigo = '4205407';
            if (!empty($dime_declaracao['municipio_id'])) {
                $stmtM = $pdo->prepare("SELECT codigo_ibge FROM t_municipios WHERE id = ?");
                $stmtM->execute([$dime_declaracao['municipio_id']]);
                $ibge = $stmtM->fetchColumn();
                if ($ibge) $municipio_codigo = $ibge;
            }

            $campos['nome_fantasia'] = $dime_declaracao['razao_social'];
            $campos['endereco_completo'] = $endereco_completo;
            $campos['municipio_codigo'] = $municipio_codigo;
            $campos['cep'] = preg_replace('/\D/', '', $dime_declaracao['cep'] ?? '') ?: '88380000';
            $campos['telefone'] = $dime_declaracao['telefone'] ?? '';
            $campos['email'] = $dime_declaracao['email'] ?? '';
            $campos['cnae_principal'] = '0081/0';
            $campos['regime_tributario'] = 'real';
            $campos['dime_declaracao_id'] = $dime_id;

            $columns = implode(', ', array_keys($campos));
            $placeholders = str_repeat('?, ', count($campos) - 1) . '?';

            $sql = "INSERT INTO dime_quadro_00 ($columns) VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($campos));
        }

        // Sincronizar dados principais na tabela dime_declaracoes
        $stmtSync = $pdo->prepare("
            UPDATE dime_declaracoes SET 
                tipo_declaracao = ?,
                regime_apuracao = ?,
                porte_empresa = ?,
                apuracao_consolidada = ?,
                apuracao_centralizada = ?,
                transferencia_creditos_periodo = ?,
                tem_creditos_presumidos = ?,
                tem_creditos_incentivos_fiscais = ?,
                movimento = ?,
                substituto_tributario = ?,
                tem_escrita_contabil = ?,
                quantidade_trabalhadores = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmtSync->execute([
            $campos['tipo_declaracao'],
            $campos['regime_apuracao'],
            $campos['porte_empresa'],
            ($campos['apuracao_consolidada'] > 1 ? 1 : 0),
            ($campos['apuracao_centralizada'] > 1 ? 1 : 0),
            ($campos['transferencia_creditos'] > 1 ? 1 : 0),
            ($campos['tem_creditos_presumidos'] > 1 ? 1 : 0),
            ($campos['tem_creditos_incentivos_fiscais'] > 1 ? 1 : 0),
            ($campos['movimento'] == 3 ? 1 : 0),
            ($campos['substituto_tributario'] == 1 ? 1 : 0),
            ($campos['tem_escrita_contabil'] == 2 ? 0 : 1),
            $campos['quantidade_trabalhadores'],
            $dime_id
        ]);

        // Mensagem de sucesso
        $success_message = "Quadro 00 salvo com sucesso!";
        
        // Recarregar dados
        $stmt = $pdo->prepare("SELECT * FROM dime_quadro_00 WHERE dime_declaracao_id = ?");
        $stmt->execute([$dime_id]);
        $quadro00_data = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $error_message = "Erro: " . $e->getMessage();
}

// Garantir que os dados da empresa estejam preenchidos mesmo se vazios em dime_quadro_00
$cnpj_empresa_display = !empty($quadro00_data['cnpj_empresa']) ? $quadro00_data['cnpj_empresa'] : ($dime_declaracao['cnpj'] ?? '');
$inscricao_estadual_display = !empty($quadro00_data['inscricao_estadual']) ? $quadro00_data['inscricao_estadual'] : ($dime_declaracao['inscricao_estadual'] ?? '');
$razao_social_display = !empty($quadro00_data['razao_social']) ? $quadro00_data['razao_social'] : ($dime_declaracao['razao_social'] ?? '');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        <i class="bi bi-info-circle text-primary me-2"></i>
        Quadro 00 - Informações Iniciais
    </h5>
    <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalAjudaQuadro00">
        <i class="bi bi-question-circle me-1"></i>Ajuda
    </button>
</div>

<?php if (isset($success_message)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>
    <?php echo $success_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <?php echo $error_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST" novalidate>
    <input type="hidden" name="form_submitted" value="1">
    
    <!-- Dados Básicos da Empresa -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="bi bi-building me-2"></i>
                010-030: Dados Básicos da Empresa
            </h6>
        </div>
        <div class="card-body">
            <div class="row g-3">

                <div class="col-md-6">
                    <label for="cnpj_empresa" class="form-label">
                        <strong>010:</strong> CNPJ
                    </label>
                    <input type="text" class="form-control" id="cnpj_empresa" 
                           name="cnpj_empresa" placeholder="00.000.000/0000-00" 
                           value="<?php echo htmlspecialchars(formatar_cnpj($cnpj_empresa_display)); ?>" 
                           readonly >
                    <div class="invalid-feedback">
                        CNPJ é obrigatório
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="inscricao_estadual" class="form-label">
                        <strong>020:</strong> Inscrição Estadual
                    </label>
                    <input type="text" class="form-control" id="inscricao_estadual" 
                           name="inscricao_estadual" placeholder="000.000.000.000" 
                           value="<?php echo htmlspecialchars($inscricao_estadual_display); ?>" 
                           readonly>
                    <div class="invalid-feedback">
                        Inscrição Estadual é obrigatória
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="razao_social" class="form-label">
                        <strong>030:</strong> Nome Empresarial
                    </label>
                    <input type="text" class="form-control" id="razao_social" 
                           name="razao_social" placeholder="Razão Social da Empresa" 
                           value="<?php echo htmlspecialchars($razao_social_display); ?>" 
                           readonly>
                    <div class="invalid-feedback">
                        Nome Empresarial é obrigatório
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Características da Declaração -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="bi bi-gear me-2"></i>
                040-060: Características da Declaração
            </h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="tipo_declaracao" class="form-label">
                        <strong>040:</strong> Tipo de Declaração
                    </label>
                    <select class="form-control" id="tipo_declaracao" 
                            name="tipo_declaracao">
                        <option value="1" <?php echo (safe_value($quadro00_data, 'tipo_declaracao', '1') == '1') ? 'selected' : ''; ?>>1 - Normal</option>
                        <option value="2" <?php echo (safe_value($quadro00_data, 'tipo_declaracao') == '2') ? 'selected' : ''; ?>>2 - Encerramento</option>
                        <option value="4" <?php echo (safe_value($quadro00_data, 'tipo_declaracao') == '4') ? 'selected' : ''; ?>>4 - Simples Nacional</option>
                    </select>
                    <div class="invalid-feedback">
                        Tipo de Declaração é obrigatório
                    </div>
                </div>

                <div class="col-md-4">
                    <label for="regime_apuracao" class="form-label">
                        <strong>050:</strong> Regime de Apuração
                    </label>
                    <select class="form-control" id="regime_apuracao" 
                            name="regime_apuracao">
                        <option value="2" <?php echo (safe_value($quadro00_data, 'regime_apuracao', '2') == '2') ? 'selected' : ''; ?>>2 - Normal/Mensal</option>
                        <option value="9" <?php echo (safe_value($quadro00_data, 'regime_apuracao') == '9') ? 'selected' : ''; ?>>9 - Produtor Primário</option>
                    </select>
                    <div class="invalid-feedback">
                        Regime de Apuração é obrigatório
                    </div>
                </div>

                <div class="col-md-4">
                    <label for="porte_empresa" class="form-label">
                        <strong>060:</strong> Porte da Empresa
                    </label>
                    <select class="form-control" id="porte_empresa" 
                            name="porte_empresa">
                        <option value="1" <?php echo (safe_value($quadro00_data, 'porte_empresa', '1') == '1') ? 'selected' : ''; ?>>1 - Micro Empresa</option>
                        <option value="2" <?php echo (safe_value($quadro00_data, 'porte_empresa') == '2') ? 'selected' : ''; ?>>2 - Pequena Empresa</option>
                        <option value="3" <?php echo (safe_value($quadro00_data, 'porte_empresa') == '3') ? 'selected' : ''; ?>>3 - Média Empresa</option>
                        <option value="4" <?php echo (safe_value($quadro00_data, 'porte_empresa') == '4') ? 'selected' : ''; ?>>4 - Grande Empresa</option>
                    </select>
                    <div class="invalid-feedback">
                        Porte da Empresa é obrigatório
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="bi bi-cash-coin me-2"></i>
                070-090: Configurações Fiscais
            </h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="apuracao_consolidada" class="form-label">
                        <strong>070:</strong> Apuração Consolidada
                    </label>
                    <select class="form-control" id="apuracao_consolidada" 
                            name="apuracao_consolidada">
                        <option value="1" <?php echo (safe_value($quadro00_data, 'apuracao_consolidada', '1') == '1') ? 'selected' : ''; ?>>1 - Não Consolidada</option>
                        <option value="2" <?php echo (safe_value($quadro00_data, 'apuracao_consolidada') == '2') ? 'selected' : ''; ?>>2 - Consolidador</option>
                        <option value="3" <?php echo (safe_value($quadro00_data, 'apuracao_consolidada') == '3') ? 'selected' : ''; ?>>3 - Consolidado</option>
                    </select>
                    <div class="invalid-feedback">
                        Apuração Consolidada é obrigatória
                    </div>
                </div>

                <div class="col-md-4">
                    <label for="apuracao_centralizada" class="form-label">
                        <strong>080:</strong> Apuração Centralizada
                    </label>
                    <select class="form-control" id="apuracao_centralizada" 
                            name="apuracao_centralizada">
                        <option value="1" <?php echo (safe_value($quadro00_data, 'apuracao_centralizada', '1') == '1') ? 'selected' : ''; ?>>1 - Não se Aplica</option>
                    </select>
                    <div class="invalid-feedback">
                        Apuração Centralizada é obrigatória
                    </div>
                </div>

                <div class="col-md-4">
                    <label for="transferencia_creditos" class="form-label">
                        <strong>090:</strong> Transferência de Créditos
                    </label>
                    <select class="form-control" id="transferencia_creditos" 
                            name="transferencia_creditos">
                        <option value="1" <?php echo (safe_value($quadro00_data, 'transferencia_creditos', '1') == '1') ? 'selected' : ''; ?>>1 - Não</option>
                        <option value="2" <?php echo (safe_value($quadro00_data, 'transferencia_creditos') == '2') ? 'selected' : ''; ?>>2 - Apurou</option>
                        <option value="3" <?php echo (safe_value($quadro00_data, 'transferencia_creditos') == '3') ? 'selected' : ''; ?>>3 - Recebeu</option>
                        <option value="4" <?php echo (safe_value($quadro00_data, 'transferencia_creditos') == '4') ? 'selected' : ''; ?>>4 - Apurou + Recebeu</option>
                        <option value="5" <?php echo (safe_value($quadro00_data, 'transferencia_creditos') == '5') ? 'selected' : ''; ?>>5 - Cooperativo</option>
                    </select>
                    <div class="invalid-feedback">
                        Transferência de Créditos é obrigatória
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Créditos Especiais -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="bi bi-piggy-bank me-2"></i>
                100-110: Créditos Especiais
            </h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="tem_creditos_presumidos" class="form-label">
                        <strong>100:</strong> Créditos Presumidos
                    </label>
                    <select class="form-control" id="tem_creditos_presumidos" 
                            name="tem_creditos_presumidos">
                        <option value="1" <?php echo (safe_value($quadro00_data, 'tem_creditos_presumidos', '1') == '1') ? 'selected' : ''; ?>>1 - Não se Aplica</option>
                    </select>
                    <div class="invalid-feedback">
                        Créditos Presumidos é obrigatório
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="tem_creditos_incentivos_fiscais" class="form-label">
                        <strong>110:</strong> Créditos por Incentivos Fiscais
                    </label>
                    <select class="form-control" id="tem_creditos_incentivos_fiscais" 
                            name="tem_creditos_incentivos_fiscais">
                        <option value="1" <?php echo (safe_value($quadro00_data, 'tem_creditos_incentivos_fiscais', '1') == '1') ? 'selected' : ''; ?>>1 - Não se Aplica</option>
                    </select>
                    <div class="invalid-feedback">
                        Créditos por Incentivos Fiscais é obrigatório
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Situação Fiscal -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="bi bi-bar-chart-line me-2"></i>
                120-140: Situação Fiscal
            </h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="movimento" class="form-label">
                        <strong>120:</strong> Movimento
                    </label>
                    <select class="form-control" id="movimento" 
                            name="movimento">
                        <option value="1" <?php echo (safe_value($quadro00_data, 'movimento') == '1') ? 'selected' : ''; ?>>1 - Sem movimento/sem saldos</option>
                        <option value="2" <?php echo (safe_value($quadro00_data, 'movimento') == '2') ? 'selected' : ''; ?>>2 - Sem movimento/com saldos</option>
                        <option value="3" <?php echo (safe_value($quadro00_data, 'movimento', '3') == '3') ? 'selected' : ''; ?>>3 - Com movimento</option>
                    </select>
                    <div class="invalid-feedback">
                        Movimento é obrigatório
                    </div>
                </div>

                <div class="col-md-4">
                    <label for="substituto_tributario" class="form-label">
                        <strong>130:</strong> Substituto Tributário
                    </label>
                    <select class="form-control" id="substituto_tributario" 
                            name="substituto_tributario">
                        <option value="1" <?php echo (safe_value($quadro00_data, 'substituto_tributario') == '1') ? 'selected' : ''; ?>>1 - Sim</option>
                        <option value="2" <?php echo (safe_value($quadro00_data, 'substituto_tributario', '2') == '2') ? 'selected' : ''; ?>>2 - Não</option>
                        <option value="3" <?php echo (safe_value($quadro00_data, 'substituto_tributario') == '3') ? 'selected' : ''; ?>>3 - Substituído Solidário</option>
                    </select>
                    <div class="invalid-feedback">
                        Substituto Tributário é obrigatório
                    </div>
                </div>

                <div class="col-md-4">
                    <label for="tem_escrita_contabil" class="form-label">
                        <strong>140:</strong> Escrita Contábil
                    </label>
                    <select class="form-control" id="tem_escrita_contabil" 
                            name="tem_escrita_contabil">
                        <option value="1" <?php echo (safe_value($quadro00_data, 'tem_escrita_contabil', '1') == '1') ? 'selected' : ''; ?>>1 - Sim (Principal)</option>
                        <option value="2" <?php echo (safe_value($quadro00_data, 'tem_escrita_contabil') == '2') ? 'selected' : ''; ?>>2 - Não</option>
                        <option value="3" <?php echo (safe_value($quadro00_data, 'tem_escrita_contabil') == '3') ? 'selected' : ''; ?>>3 - Sim (Consolidado)</option>
                    </select>
                    <div class="invalid-feedback">
                        Escrita Contábil é obrigatória
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quantidade de Trabalhadores -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="bi bi-people me-2"></i>
                150: Quantidade de Trabalhadores
            </h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="quantidade_trabalhadores" class="form-label">
                        <strong>150:</strong> Quantidade de Trabalhadores
                    </label>
                    <input type="number" class="form-control" id="quantidade_trabalhadores" 
                           name="quantidade_trabalhadores" placeholder="0" min="0"
                           value="<?php echo safe_value($quadro00_data, 'quantidade_trabalhadores', '0'); ?>">
                    <div class="form-text">
                        Informe a quantidade de pessoas ocupadas na atividade (independente de vínculo trabalhista)
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Botão Salvar -->
    <div class="d-flex justify-content-end mt-4">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-check-circle me-2"></i>
            Salvar Quadro 00
        </button>
    </div>
</form>

<!-- Modal de Ajuda do Quadro 00 -->
<div class="modal fade" id="modalAjudaQuadro00" tabindex="-1" aria-labelledby="modalAjudaQuadro00Label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="modalAjudaQuadro00Label">
                    <i class="bi bi-question-circle me-2"></i>
                    Ajuda - Quadro 00: Informações Iniciais da Declaração
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">

                <!-- Visão Geral -->
                <div class="mb-4">
                    <h6 class="text-info mb-3">
                        <i class="bi bi-info-circle me-2"></i>Visão Geral
                    </h6>
                    <p>O Quadro 00 contém as informações básicas e características da declaração DIME. Estes dados são fundamentais para a correta identificação e processamento da declaração fiscal.</p>
                </div>

                <!-- 010-030: Dados Básicos da Empresa -->
                <div class="mb-4">
                    <h6 class="text-primary mb-3">
                        <i class="bi bi-building me-2"></i>010-030: Dados Básicos da Empresa
                    </h6>
                    <div class="accordion" id="accordionDadosBasicos">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse010">
                                    <strong>010:</strong> Número da Inscrição Estadual
                                </button>
                            </h2>
                            <div id="collapse010" class="accordion-collapse collapse show" data-bs-parent="#accordionDadosBasicos">
                                <div class="accordion-body">
                                    <p><strong>Informação necessária:</strong> Número de inscrição no CCICMS ou CPP (Cadastro de Produtor Primário).</p>
                                    <p><strong>Onde encontrar:</strong> No certificado de inscrição estadual ou no cadastro do contribuinte.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse020">
                                    <strong>020:</strong> Nome ou Razão Social
                                </button>
                            </h2>
                            <div id="collapse020" class="accordion-collapse collapse" data-bs-parent="#accordionDadosBasicos">
                                <div class="accordion-body">
                                    <p><strong>Informação necessária:</strong> Nome empresarial completo conforme registrado na inscrição estadual.</p>
                                    <p><strong>Onde encontrar:</strong> No contrato social ou certificado de inscrição.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse030">
                                    <strong>030:</strong> Período de Referência
                                </button>
                            </h2>
                            <div id="collapse030" class="accordion-collapse collapse" data-bs-parent="#accordionDadosBasicos">
                                <div class="accordion-body">
                                    <p><strong>Formato:</strong> MMAAAA (mês e ano)</p>
                                    <p><strong>Exemplo:</strong> Para janeiro de 2024, informar: 012024</p>
                                    <p><strong>Nota:</strong> Este campo é preenchido automaticamente pelo sistema.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 040-060: Características da Declaração -->
                <div class="mb-4">
                    <h6 class="text-primary mb-3">
                        <i class="bi bi-gear me-2"></i>040-060: Características da Declaração
                    </h6>
                    <div class="accordion" id="accordionCaracteristicas">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse040">
                                    <strong>040:</strong> Tipo de Declaração
                                </button>
                            </h2>
                            <div id="collapse040" class="accordion-collapse collapse" data-bs-parent="#accordionCaracteristicas">
                                <div class="accordion-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong class="text-success">1 - Normal</strong>
                                            <p class="small">Para estabelecimento em atividade normal</p>
                                        </div>
                                        <div class="col-md-4">
                                            <strong class="text-warning">2 - Encerramento</strong>
                                            <p class="small">Quando houver baixa da inscrição estadual</p>
                                        </div>
                                        <div class="col-md-4">
                                            <strong class="text-info">4 - Simples Nacional</strong>
                                            <p class="small">Enquadramento no Simples Nacional</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse050">
                                    <strong>050:</strong> Regime de Apuração
                                </button>
                            </h2>
                            <div id="collapse050" class="accordion-collapse collapse" data-bs-parent="#accordionCaracteristicas">
                                <div class="accordion-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong class="text-primary">2 - Normal</strong>
                                            <p class="small">Regime normal de apuração mensal</p>
                                        </div>
                                        <div class="col-md-6">
                                            <strong class="text-success">9 - Produtor Primário</strong>
                                            <p class="small">Para produtores primários com inscrição no CPP e regime especial</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse060">
                                    <strong>060:</strong> Porte da Empresa
                                </button>
                            </h2>
                            <div id="collapse060" class="accordion-collapse collapse" data-bs-parent="#accordionCaracteristicas">
                                <div class="accordion-body">
                                    <p><strong>Valor padrão:</strong> 1 - Não se aplica</p>
                                    <p><strong>Nota:</strong> Este campo é padronizado e não requer alteração na maioria dos casos.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 070-090: Configurações Fiscais -->
                <div class="mb-4">
                    <h6 class="text-primary mb-3">
                        <i class="bi bi-cash-coin me-2"></i>070-090: Configurações Fiscais
                    </h6>
                    <div class="accordion" id="accordionConfiguracoes">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse070">
                                    <strong>070:</strong> Apuração Consolidada
                                </button>
                            </h2>
                            <div id="collapse070" class="accordion-collapse collapse" data-bs-parent="#accordionConfiguracoes">
                                <div class="accordion-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong class="text-primary">1 - Não Consolidada</strong>
                                            <p class="small">Estabelecimento não adota apuração consolidada</p>
                                        </div>
                                        <div class="col-md-4">
                                            <strong class="text-warning">2 - Consolidador</strong>
                                            <p class="small">Estabelecimento consolidador</p>
                                        </div>
                                        <div class="col-md-4">
                                            <strong class="text-info">3 - Consolidado</strong>
                                            <p class="small">Estabelecimento consolidado</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse080">
                                    <strong>080:</strong> Apuração Centralizada
                                </button>
                            </h2>
                            <div id="collapse080" class="accordion-collapse collapse" data-bs-parent="#accordionConfiguracoes">
                                <div class="accordion-body">
                                    <p><strong>Valor padrão:</strong> 1 - Não se aplica</p>
                                    <p><strong>Nota:</strong> Campo padronizado para a maioria das declarações.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse090">
                                    <strong>090:</strong> Transferência de Créditos
                                </button>
                            </h2>
                            <div id="collapse090" class="accordion-collapse collapse" data-bs-parent="#accordionConfiguracoes">
                                <div class="accordion-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <strong class="text-primary">1 - Não</strong>
                                            <p class="small">Não apurou/reservou nem recebeu créditos</p>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <strong class="text-success">2 - Apurou</strong>
                                            <p class="small">Só apurou/reservou créditos acumulados</p>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <strong class="text-warning">3 - Recebeu</strong>
                                            <p class="small">Só recebeu créditos em transferência</p>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <strong class="text-info">4 - Apurou + Recebeu</strong>
                                            <p class="small">Apurou/reservou E recebeu créditos</p>
                                        </div>
                                        <div class="col-md-12">
                                            <strong class="text-secondary">5 - Cooperativo</strong>
                                            <p class="small">Sistema cooperativo agropecuário</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 100-110: Créditos Especiais -->
                <div class="mb-4">
                    <h6 class="text-primary mb-3">
                        <i class="bi bi-piggy-bank me-2"></i>100-110: Créditos Especiais
                    </h6>
                    <div class="accordion" id="accordionCreditos">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse100">
                                    <strong>100:</strong> Créditos Presumidos
                                </button>
                            </h2>
                            <div id="collapse100" class="accordion-collapse collapse" data-bs-parent="#accordionCreditos">
                                <div class="accordion-body">
                                    <p><strong>Valor padrão:</strong> 1 - Não se aplica</p>
                                    <p><strong>Quando usar:</strong> Apenas em casos específicos de créditos presumidos.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse110">
                                    <strong>110:</strong> Créditos por Incentivos Fiscais
                                </button>
                            </h2>
                            <div id="collapse110" class="accordion-collapse collapse" data-bs-parent="#accordionCreditos">
                                <div class="accordion-body">
                                    <p><strong>Valor padrão:</strong> 1 - Não se aplica</p>
                                    <p><strong>Quando usar:</strong> Quando houver benefícios fiscais específicos.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 120-140: Situação Fiscal -->
                <div class="mb-4">
                    <h6 class="text-primary mb-3">
                        <i class="bi bi-bar-chart-line me-2"></i>120-140: Situação Fiscal
                    </h6>
                    <div class="accordion" id="accordionSituacao">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse120">
                                    <strong>120:</strong> Movimento
                                </button>
                            </h2>
                            <div id="collapse120" class="accordion-collapse collapse" data-bs-parent="#accordionSituacao">
                                <div class="accordion-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong class="text-danger">1 - Sem movimento/sem saldos</strong>
                                            <p class="small">Não houve operações nem saldos no período</p>
                                        </div>
                                        <div class="col-md-4">
                                            <strong class="text-warning">2 - Sem movimento/com saldos</strong>
                                            <p class="small">Não houve operações mas há saldos</p>
                                        </div>
                                        <div class="col-md-4">
                                            <strong class="text-success">3 - Com movimento</strong>
                                            <p class="small">Houve operações no período</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse130">
                                    <strong>130:</strong> Substituto Tributário
                                </button>
                            </h2>
                            <div id="collapse130" class="accordion-collapse collapse" data-bs-parent="#accordionSituacao">
                                <div class="accordion-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong class="text-success">1 - Sim</strong>
                                            <p class="small">É substituto tributário</p>
                                        </div>
                                        <div class="col-md-4">
                                            <strong class="text-primary">2 - Não</strong>
                                            <p class="small">Não é substituto tributário</p>
                                        </div>
                                        <div class="col-md-4">
                                            <strong class="text-warning">3 - Substituído Solidário</strong>
                                            <p class="small">Recebe mercadorias sem retenção do imposto</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse140">
                                    <strong>140:</strong> Escrita Contábil
                                </button>
                            </h2>
                            <div id="collapse140" class="accordion-collapse collapse" data-bs-parent="#accordionSituacao">
                                <div class="accordion-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong class="text-success">1 - Sim (Principal)</strong>
                                            <p class="small">Possui escrita contábil e é o estabelecimento principal</p>
                                        </div>
                                        <div class="col-md-4">
                                            <strong class="text-danger">2 - Não</strong>
                                            <p class="small">Não possui escrita contábil</p>
                                        </div>
                                        <div class="col-md-4">
                                            <strong class="text-info">3 - Sim (Consolidado)</strong>
                                            <p class="small">Possui escrita contábil consolidada no estabelecimento principal</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 150: Quantidade de Trabalhadores -->
                <div class="mb-4">
                    <h6 class="text-primary mb-3">
                        <i class="bi bi-people me-2"></i>150: Quantidade de Trabalhadores
                    </h6>
                    <div class="accordion" id="accordionTrabalhadores">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse150">
                                    <strong>150:</strong> Quantidade de Trabalhadores na Atividade
                                </button>
                            </h2>
                            <div id="collapse150" class="accordion-collapse collapse" data-bs-parent="#accordionTrabalhadores">
                                <div class="accordion-body">
                                    <p><strong>Informação necessária:</strong> Número total de pessoas ocupadas na atividade econômica do estabelecimento.</p>
                                    <p><strong>Inclui:</strong> Todos os trabalhadores, independente do vínculo trabalhista (CLT, terceirizados, autônomos, etc.).</p>
                                    <p><strong>Exclui:</strong> Não inclui familiares não remunerados ou pessoas não relacionadas à atividade econômica.</p>
                                    <p><strong>Exemplo:</strong> Se o estabelecimento tem 5 funcionários CLT + 2 terceirizados + 1 autônomo = informar 8.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dicas Gerais -->
                <div class="alert alert-info">
                    <h6 class="alert-heading mb-2">
                        <i class="bi bi-lightbulb me-2"></i>Dicas Gerais para Preenchimento
                    </h6>
                    <ul class="mb-0 small">
                        <li>Os campos 010, 020 e 030 são preenchidos automaticamente pelo sistema com base nos dados da empresa.</li>
                        <li>Mantenha os valores padrão (1 - Não se aplica) para campos que não se aplicam ao seu estabelecimento.</li>
                        <li>O campo "Movimento" deve refletir a atividade real do estabelecimento no período.</li>
                        <li>Em caso de dúvidas sobre regime tributário, consulte o contador responsável.</li>
                        <li>Todos os campos são obrigatórios - não deixe nenhum em branco.</li>
                    </ul>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Executar imediatamente para conteúdo carregado via AJAX
(function() {
    const form = document.querySelector('form');
    if (!form) return;
    
    // Listener para interceptar submit e usar AJAX
    form.onsubmit = function(e) {
        e.preventDefault();
        
        salvarAjax();
        
        return false;
    };
})();

function salvarAjax() {
    const form = document.querySelector('form');
    const formData = new FormData(form);
    
    // Mostrar loading no botão
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Salvando...';
    
    // URL correta para o quadro
    const postUrl = 'quadros/00_index.php?dime_id=<?php echo (int)$dime_id; ?>';
    
    fetch(postUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Verificar se foi sucesso
        if (data.includes('alert-success') || data.includes('sucesso') || data.includes('Sucesso') || data.includes('SUCESSO')) {
            // Mostrar mensagem de sucesso
            showMessage('✅ Dados salvos com sucesso!', 'success');
            
            // Recarregar quadro
            setTimeout(() => {
                if (typeof loadQuadroContent === 'function') {
                    loadQuadroContent('00', <?php echo (int)$dime_id; ?>);
                } else if (window.parent && typeof window.parent.loadQuadroContent === 'function') {
                    window.parent.loadQuadroContent('00', <?php echo (int)$dime_id; ?>);
                }
            }, 500);
        } else {
            // Verificar erro
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = data;
            const errorMsg = tempDiv.querySelector('.alert-danger, .alert');
            
            if (errorMsg) {
                showMessage('❌ ' + errorMsg.textContent.trim(), 'danger');
            } else {
                showMessage('❌ Erro ao salvar. Verifique os dados.', 'danger');
            }
        }
    })
    .catch(error => {
        showMessage('💥 Erro de conexão: ' + error.message, 'danger');
    })
    .finally(() => {
        // Restaurar botão
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

function showMessage(message, type) {
    // Criar alert flutuante
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 500px;';
    alertDiv.innerHTML = `
        <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto-remover após 5 segundos
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

</script>