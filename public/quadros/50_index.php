<?php
$dime_id = isset($_GET['dime_id']) ? (int)$_GET['dime_id'] : 0;

if (!$dime_id) {
    die('DIME ID não fornecido');
}

// Carregar dados do Quadro 50
$dados_quadro_50 = [];
$ufs_validas = ['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO', 'EX'];

function formatar_valor($valor) {
    return number_format($valor, 2, ',', '.');
}

try {
    require_once __DIR__ . '/../includes/config.php';
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT * FROM dime_quadro_50 WHERE dime_declaracao_id = ? ORDER BY uf");
    $stmt->execute([$dime_id]);
    $dados_quadro_50 = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Erro ao carregar dados do Quadro 50: ' . $e->getMessage());
}

// Calcular totalizadores
$totalizadores = [
    'valor_contabil_nao_contribuinte' => 0,
    'valor_contabil_contribuinte' => 0,
    'base_calculo_nao_contribuinte' => 0,
    'base_calculo_contribuinte' => 0,
    'outras' => 0,
    'icms_retido_st' => 0
];

foreach ($dados_quadro_50 as $linha) {
    if ($linha['uf'] !== 'TT') {
        $totalizadores['valor_contabil_nao_contribuinte'] += $linha['valor_contabil_nao_contribuinte'];
        $totalizadores['valor_contabil_contribuinte'] += $linha['valor_contabil_contribuinte'];
        $totalizadores['base_calculo_nao_contribuinte'] += $linha['base_calculo_nao_contribuinte'];
        $totalizadores['base_calculo_contribuinte'] += $linha['base_calculo_contribuinte'];
        $totalizadores['outras'] += $linha['outras'];
        $totalizadores['icms_retido_st'] += $linha['icms_retido_st'];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quadro 50 - Saídas por Unidade da Federação</title>
    <style>
        .table-responsive { max-height: 600px; overflow-y: auto; }
        .table { width: 100%; table-layout: fixed; }
        .total-row { background-color: #f8f9fa; font-weight: bold; border-top: 2px solid #dee2e6; }
        .uf-column { width: 120px; }
        .value-column { width: 120px; min-width: 120px; }
        .sub-column { width: 100px; min-width: 100px; }
        .actions-column { width: 120px; }
        .modal-lg { max-width: 1000px; }
        .currency-input { text-align: right; }
        .info-box {
            background-color: #e7f3ff;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            margin-bottom: 20px;
        }
        .table-header-group {
            background-color: #f8f9fa;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">
            <i class="bi bi-geo-alt-fill text-primary me-2"></i>
            Quadro 50 - Saídas por Unidade da Federação
        </h5>
        <div>
            <button type="button" class="btn btn-outline-info btn-sm" onclick="mostrarAjudaQuadro50()">
                <i class="bi bi-question-circle me-1"></i>Ajuda
            </button>
        </div>
    </div>

    <div class="info-box">
        <strong>💡 Sobre o Quadro 50:</strong> Este quadro deve ser preenchido por todos os estabelecimentos que realizaram operações com mercadorias ou fornecimento de energia elétrica e prestações de serviços.
    </div>

    <button type="button" class="btn btn-success me-2 mb-3" onclick="adicionarUF()">
        <i class="bi bi-plus-circle me-2"></i>Adicionar UF
    </button>

    <div class="table-responsive">
        <table class="table table-bordered table-hover mb-0" id="quadro50Table">
            <thead class="table-light">
                <tr>
                    <th rowspan="2" class="uf-column text-center align-middle">UF</th>
                    <th colspan="2" class="text-center table-header-group">Valor Contábil</th>
                    <th colspan="2" class="text-center table-header-group">Base de Cálculo</th>
                    <th rowspan="2" class="value-column text-center align-middle">Outras</th>
                    <th rowspan="2" class="value-column text-center align-middle">ICMS Retido ST</th>
                    <th rowspan="2" class="actions-column text-center align-middle">Ações</th>
                </tr>
                <tr>
                    <th class="sub-column text-center">Não Contribuinte</th>
                    <th class="sub-column text-center">Contribuinte</th>
                    <th class="sub-column text-center">Não Contribuinte</th>
                    <th class="sub-column text-center">Contribuinte</th>
                </tr>
            </thead>
            <tbody id="quadro50TableBody">
                <?php if (empty($dados_quadro_50)): ?>
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p class="mb-2">Nenhuma UF cadastrada ainda.</p>
                        <small>Clique no botão "Adicionar UF" acima para começar.</small>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($dados_quadro_50 as $linha): ?>
                        <?php if ($linha['uf'] !== 'TT'): ?>
                        <tr data-uf="<?php echo $linha['uf']; ?>">
                            <td class="text-center fw-bold"><?php echo $linha['uf']; ?></td>
                            <td class="text-end"><?php echo formatar_valor($linha['valor_contabil_nao_contribuinte']); ?></td>
                            <td class="text-end"><?php echo formatar_valor($linha['valor_contabil_contribuinte']); ?></td>
                            <td class="text-end"><?php echo formatar_valor($linha['base_calculo_nao_contribuinte']); ?></td>
                            <td class="text-end"><?php echo formatar_valor($linha['base_calculo_contribuinte']); ?></td>
                            <td class="text-end"><?php echo formatar_valor($linha['outras']); ?></td>
                            <td class="text-end"><?php echo formatar_valor($linha['icms_retido_st']); ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="editarUF('<?php echo $linha['uf']; ?>')" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removerUF('<?php echo $linha['uf']; ?>')" title="Remover">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Linha de Totalizadores -->
                <tr class="total-row">
                    <td class="text-center">
                        <span class="badge bg-secondary">TT</span>
                    </td>
                    <td class="text-center fw-bold">TOTAL</td>
                    <td class="text-center fw-bold">TOTAL</td>
                    <td class="text-center fw-bold">TOTAL</td>
                    <td class="text-center fw-bold">TOTAL</td>
                    <td class="text-end fw-bold" id="total-outras"><?php echo formatar_valor($totalizadores['outras']); ?></td>
                    <td class="text-end fw-bold" id="total-st"><?php echo formatar_valor($totalizadores['icms_retido_st']); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Modal para Adicionar/Editar UF -->
    <div class="modal fade" id="modalUF" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Adicionar UF</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formUF">
                        <input type="hidden" id="modalMode" value="add">
                        <input type="hidden" id="currentUF" value="">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="selectUF" class="form-label">Unidade da Federação</label>
                                <select class="form-select" id="selectUF" required>
                                    <option value="">Selecione uma UF...</option>
                                    <?php foreach ($ufs_validas as $uf): ?>
                                        <option value="<?php echo $uf; ?>"><?php echo $uf; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Valor Contábil -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <h6 class="text-primary"><i class="bi bi-calculator me-2"></i>Valor Contábil</h6>
                            </div>
                            <div class="col-md-6">
                                <label for="valorContabilNaoContribuinte" class="form-label">Não Contribuinte</label>
                                <input type="text" class="form-control currency-input" id="valorContabilNaoContribuinte" placeholder="0,00">
                            </div>
                            <div class="col-md-6">
                                <label for="valorContabilContribuinte" class="form-label">Contribuinte</label>
                                <input type="text" class="form-control currency-input" id="valorContabilContribuinte" placeholder="0,00">
                            </div>
                        </div>

                        <!-- Base de Cálculo -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <h6 class="text-primary"><i class="bi bi-graph-up me-2"></i>Base de Cálculo</h6>
                            </div>
                            <div class="col-md-6">
                                <label for="baseCalculoNaoContribuinte" class="form-label">Não Contribuinte</label>
                                <input type="text" class="form-control currency-input" id="baseCalculoNaoContribuinte" placeholder="0,00">
                            </div>
                            <div class="col-md-6">
                                <label for="baseCalculoContribuinte" class="form-label">Contribuinte</label>
                                <input type="text" class="form-control currency-input" id="baseCalculoContribuinte" placeholder="0,00">
                            </div>
                        </div>

                        <!-- Outras e ST -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="outras" class="form-label">Outras</label>
                                    <input type="text" class="form-control currency-input" id="outras" placeholder="0,00">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="icmsRetidoST" class="form-label">ICMS Retido por ST</label>
                                    <input type="text" class="form-control currency-input" id="icmsRetidoST" placeholder="0,00">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarUF()">Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <span id="successToastMessage"></span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>

        <div id="errorToast" class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <span id="errorToastMessage"></span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

    <script>
        $(document).ready(function() {
            // Aplicar máscaras monetárias
            $('.currency-input').mask('000.000.000.000.000,00', {reverse: true});
        });

        // Função para mostrar toast
        function showToast(type, message) {
            const toastId = type === 'success' ? 'successToast' : 'errorToast';
            const messageId = type === 'success' ? 'successToastMessage' : 'errorToastMessage';

            document.getElementById(messageId).textContent = message;

            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, {
                delay: 4000 // 4 segundos
            });
            toast.show();
        }

        function adicionarUF() {
            $('#modalMode').val('add');
            $('#modalTitle').text('Adicionar UF');
            $('#formUF')[0].reset();
            $('#modalUF').modal('show');
        }

        function editarUF(uf) {
            $('#modalMode').val('edit');
            $('#modalTitle').text('Editar UF: ' + uf);
            $('#currentUF').val(uf);
            $('#selectUF').val(uf).prop('disabled', true);

            // Carregar dados atuais
            fetch(`../quadros/ajax_get_uf_50.php?dime_id=<?php echo $dime_id; ?>&uf=${uf}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        $('#valorContabilNaoContribuinte').val(formatarValorParaInput(data.dados.valor_contabil_nao_contribuinte));
                        $('#valorContabilContribuinte').val(formatarValorParaInput(data.dados.valor_contabil_contribuinte));
                        $('#baseCalculoNaoContribuinte').val(formatarValorParaInput(data.dados.base_calculo_nao_contribuinte));
                        $('#baseCalculoContribuinte').val(formatarValorParaInput(data.dados.base_calculo_contribuinte));
                        $('#outras').val(formatarValorParaInput(data.dados.outras));
                        $('#icmsRetidoST').val(formatarValorParaInput(data.dados.icms_retido_st));
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar dados:', error);
                });

            $('#modalUF').modal('show');
        }

        function salvarUF() {
            const uf = $('#selectUF').val();
            if (!uf) {
                showToast('error', 'Selecione uma UF');
                return;
            }

            const mode = $('#modalMode').val();
            const dados = {
                uf: uf,
                valor_contabil_nao_contribuinte: $('#valorContabilNaoContribuinte').val() || '0,00',
                valor_contabil_contribuinte: $('#valorContabilContribuinte').val() || '0,00',
                base_calculo_nao_contribuinte: $('#baseCalculoNaoContribuinte').val() || '0,00',
                base_calculo_contribuinte: $('#baseCalculoContribuinte').val() || '0,00',
                outras: $('#outras').val() || '0,00',
                icms_retido_st: $('#icmsRetidoST').val() || '0,00'
            };

            const url = mode === 'add' ? '../quadros/ajax_add_uf_50.php' : '../quadros/ajax_update_uf_50.php';

            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    dime_id: '<?php echo $dime_id; ?>',
                    ...dados
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const action = mode === 'add' ? 'incluída' : 'atualizada';
                    showToast('success', `Saída por UF ${uf} ${action} com sucesso!`);
                    $('#modalUF').modal('hide');

                    // Aguardar o toast aparecer antes de recarregar
                    setTimeout(() => {
                        if (window.parent && window.parent.reloadQuadro50) {
                            window.parent.reloadQuadro50();
                        } else {
                            location.reload();
                        }
                    }, 1500); // 1500ms de delay
                } else {
                    showToast('error', 'Erro: ' + data.message);
                }
            })
            .catch(error => {
                showToast('error', 'Erro na requisição: ' + error.message);
            });
        }

        function removerUF(uf) {
            if (confirm(`Deseja remover a UF ${uf}?`)) {
                fetch('../quadros/ajax_delete_50.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        dime_id: '<?php echo $dime_id; ?>',
                        uf: uf
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('success', `Saída por UF ${uf} excluída com sucesso!`);

                        // Aguardar o toast aparecer antes de recarregar
                        setTimeout(() => {
                            if (window.parent && window.parent.reloadQuadro50) {
                                window.parent.reloadQuadro50();
                            } else {
                                location.reload();
                            }
                        }, 1500); // 1500ms de delay
                    } else {
                        showToast('error', 'Erro: ' + data.message);
                    }
                })
                .catch(error => {
                    showToast('error', 'Erro na requisição: ' + error.message);
                });
            }
        }

        function formatarValorParaInput(valor) {
            return parseFloat(valor).toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function mostrarAjudaQuadro50() {
            if (!document.getElementById('modalAjudaQuadro50')) {
                const modalHTML = `
                    <div class="modal fade" id="modalAjudaQuadro50" tabindex="-1" aria-labelledby="modalAjudaLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <div class="modal-header bg-info text-white">
                                    <h5 class="modal-title" id="modalAjudaLabel">
                                        <i class="fas fa-book-open me-2"></i>
                                        Manual Completo - Quadro 50: Saídas por Unidade da Federação
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="container-fluid">
                                        <div class="card border-primary mb-4">
                                            <div class="card-header bg-primary text-white">
                                                <h6 class="card-title mb-0">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    3.2.22. Quadro 50 - Saídas por Unidade da Federação
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                <p class="card-text">
                                                    <strong>Objetivo:</strong> Este quadro deve ser preenchido por todos os estabelecimentos que realizaram operações com mercadorias ou fornecimento de energia elétrica e prestações de serviços.
                                                </p>
                                                <p class="card-text">
                                                    <strong>Totalizador:</strong> O último registro desta seqüência será o "Totalizador", para fins de fechamentos, devendo ser composto com o Código de UF igual a "TT" e como valor o somatório das respectivas colunas: Valor Contábil, Base de Cálculo, Outras, ICMS Retido por Substituição Tributária.
                                                </p>
                                            </div>
                                        </div>

                                        <div class="accordion" id="manualAccordion">
                                            <div class="accordion-item">
                                                <h2 class="accordion-header">
                                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#uf">
                                                        <i class="fas fa-map-marker-alt me-2"></i>
                                                        3.2.22.2. Coluna Sigla da Unidade da Federação
                                                    </button>
                                                </h2>
                                                <div id="uf" class="accordion-collapse collapse show" data-bs-parent="#manualAccordion">
                                                    <div class="accordion-body">
                                                        <p>Informar a sigla da unidade da federação de destino da mercadoria ou energia elétrica, ou da localização do contratante de serviço de comunicação ou transporte, conforme Tabela de Siglas das Unidades da Federação.</p>
                                                        <div class="alert alert-info">
                                                            <strong>Observação importante:</strong> Os valores das operações e prestações destinadas ao exterior do país, classificadas no CFOP do grupo 7.000 - Saídas ou Prestações de Serviços para o Exterior, serão lançadas com a sigla "EX".
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="accordion-item">
                                                <h2 class="accordion-header">
                                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#valorContabil">
                                                        <i class="fas fa-calculator me-2"></i>
                                                        Colunas Valor Contábil - Não Contribuinte e Contribuinte
                                                    </button>
                                                </h2>
                                                <div id="valorContabil" class="accordion-collapse collapse" data-bs-parent="#manualAccordion">
                                                    <div class="accordion-body">
                                                        <p><strong>Não Contribuinte:</strong> Informar o valor total lançado na coluna "Valor Contábil" do Livro Registro de Saídas referente às notas fiscais de operações ou prestações para não contribuintes (pessoas físicas ou jurídicas não cadastradas no Estado de destino) no período de referência.</p>
                                                        <p><strong>Contribuinte:</strong> Informar o valor total lançado na coluna "Valor Contábil" do Livro Registro de Saídas referente às notas fiscais de operações ou prestações para contribuintes (pessoas físicas ou jurídicas cadastradas no Estado de destino) no período de referência.</p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="accordion-item">
                                                <h2 class="accordion-header">
                                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#baseCalculo">
                                                        <i class="fas fa-chart-line me-2"></i>
                                                        Colunas Base de Cálculo - Não Contribuinte e Contribuinte
                                                    </button>
                                                </h2>
                                                <div id="baseCalculo" class="accordion-collapse collapse" data-bs-parent="#manualAccordion">
                                                    <div class="accordion-body">
                                                        <p><strong>Não Contribuinte:</strong> Informar o valor total lançado na coluna "Base de Cálculo" do Livro Registro de Saídas referente às notas fiscais de operações ou prestações para não contribuintes (pessoas físicas ou jurídicas não cadastradas no Estado de destino) no período de referência.</p>
                                                        <p><strong>Contribuinte:</strong> Informar o valor total lançado na coluna "Base de Cálculo" do Livro Registro de Saídas referente às notas fiscais de operações ou prestações para contribuintes (pessoas físicas ou jurídicas cadastradas no Estado de destino) no período de referência.</p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="accordion-item">
                                                <h2 class="accordion-header">
                                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#outras">
                                                        <i class="fas fa-plus-circle me-2"></i>
                                                        3.2.22.7. Coluna Outras
                                                    </button>
                                                </h2>
                                                <div id="outras" class="accordion-collapse collapse" data-bs-parent="#manualAccordion">
                                                    <div class="accordion-body">
                                                        <p>Informar o somatório dos valores lançados nas colunas Isentas ou Não Tributadas e Outras do Livro Registro de Saídas referente às notas fiscais de fornecimento no período de referência.</p>
                                                        <p>Inclui operações isentas, não tributadas e outras situações especiais de tributação.</p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="accordion-item">
                                                <h2 class="accordion-header">
                                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#st">
                                                        <i class="fas fa-coins me-2"></i>
                                                        3.2.22.8. Coluna ICMS Retido por Substituição Tributária
                                                    </button>
                                                </h2>
                                                <div id="st" class="accordion-collapse collapse" data-bs-parent="#manualAccordion">
                                                    <div class="accordion-body">
                                                        <p>Informar o valor total da retenção lançado na coluna "Observações" do Livro Registro de Saídas referente às notas fiscais de operações ou prestações com produtos ou serviços sujeitos à substituição tributária no período de referência.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="alert alert-success mt-4">
                                            <h6 class="alert-heading">
                                                <i class="fas fa-check-circle me-2"></i>
                                                Orientações para Preenchimento Correto
                                            </h6>
                                            <ul class="mb-0">
                                                <li><strong>Periodicidade:</strong> Dados do período de referência da declaração DIME</li>
                                                <li><strong>Origem dos dados:</strong> Livro Registro de Saídas e documentos fiscais</li>
                                                <li><strong>Exterior:</strong> Utilizar código "EX" para operações internacionais</li>
                                                <li><strong>Valores zerados:</strong> Permitidos quando não houver operações específicas</li>
                                                <li><strong>Totalização:</strong> A linha TT é calculada automaticamente pelo sistema</li>
                                                <li><strong>Conferência:</strong> Os valores devem corresponder exatamente aos registros contábeis</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="fas fa-times me-1"></i>
                                        Fechar Manual
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                document.body.insertAdjacentHTML('beforeend', modalHTML);
            }

            const modal = new bootstrap.Modal(document.getElementById('modalAjudaQuadro50'));
            modal.show();
        }

    </script>
</body>
</html>