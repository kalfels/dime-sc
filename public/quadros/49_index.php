<?php
$dime_id = isset($_GET['dime_id']) ? (int)$_GET['dime_id'] : 0;

if (!$dime_id) {
    die('DIME ID não fornecido');
}

// Carregar dados do Quadro 49
$dados_quadro_49 = [];
$ufs_validas = ['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO', 'EX'];

function formatar_valor($valor) {
    return number_format($valor, 2, ',', '.');
}

try {
    require_once __DIR__ . '/../includes/config.php';
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT * FROM dime_quadro_49 WHERE dime_declaracao_id = ? ORDER BY uf");
    $stmt->execute([$dime_id]);
    $dados_quadro_49 = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Erro ao carregar dados do Quadro 49: ' . $e->getMessage());
}

// Calcular totalizadores
$totalizadores = [
    'valor_contabil' => 0,
    'base_calculo' => 0,
    'outras' => 0,
    'icms_retido_st_petroleo_energia' => 0,
    'icms_retido_st_outros_produtos' => 0
];

foreach ($dados_quadro_49 as $linha) {
    if ($linha['uf'] !== 'TT') {
        $totalizadores['valor_contabil'] += $linha['valor_contabil'];
        $totalizadores['base_calculo'] += $linha['base_calculo'];
        $totalizadores['outras'] += $linha['outras'];
        $totalizadores['icms_retido_st_petroleo_energia'] += $linha['icms_retido_st_petroleo_energia'];
        $totalizadores['icms_retido_st_outros_produtos'] += $linha['icms_retido_st_outros_produtos'];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quadro 49 - Entradas por Unidade da Federação</title>
    <style>
        .table-responsive { max-height: 600px; overflow-y: auto; }
        .table { width: 100%; table-layout: fixed; }
        .total-row { background-color: #f8f9fa; font-weight: bold; border-top: 2px solid #dee2e6; }
        .uf-column { width: 100px; }
        .value-column { width: 140px; min-width: 140px; }
        .actions-column { width: 80px; }
        .modal-lg { max-width: 900px; }
        .currency-input { text-align: right; }
        .info-box {
            background-color: #e7f3ff;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">
            <i class="bi bi-map text-primary me-2"></i>
            Quadro 49 - Entradas por Unidade da Federação
        </h5>
        <div>
            <button type="button" class="btn btn-outline-info btn-sm" onclick="mostrarAjudaQuadro49()">
                <i class="bi bi-question-circle me-1"></i>Ajuda
            </button>
        </div>
    </div>

    <div class="info-box">
        <strong>💡 Sobre o Quadro 49:</strong> Este quadro deve ser preenchido por todos os estabelecimentos que realizaram operações de aquisição de serviços, mercadorias ou energia elétrica.
    </div>

    <button type="button" class="btn btn-success me-2 mb-3" onclick="adicionarUF()">
        <i class="bi bi-plus-circle me-2"></i>Adicionar UF
    </button>

    <div class="table-responsive">
        <table class="table table-bordered table-hover mb-0" id="quadro49Table">
            <thead class="table-light">
                <tr>
                    <th class="uf-column text-center">UF</th>
                    <th class="value-column text-center">Valor Contábil</th>
                    <th class="value-column text-center">Base de Cálculo</th>
                    <th class="value-column text-center">Outras</th>
                    <th class="value-column text-center">ST Petróleo/Energia</th>
                    <th class="value-column text-center">ST Outros Produtos</th>
                    <th class="actions-column text-center">Ações</th>
                </tr>
            </thead>
            <tbody id="quadro49TableBody">
                <?php if (empty($dados_quadro_49)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p class="mb-2">Nenhuma UF cadastrada ainda.</p>
                        <small>Clique no botão "Adicionar UF" acima para começar.</small>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($dados_quadro_49 as $linha): ?>
                        <?php if ($linha['uf'] !== 'TT'): ?>
                        <tr data-uf="<?php echo $linha['uf']; ?>">
                            <td class="text-center fw-bold"><?php echo $linha['uf']; ?></td>
                            <td class="text-end"><?php echo formatar_valor($linha['valor_contabil']); ?></td>
                            <td class="text-end"><?php echo formatar_valor($linha['base_calculo']); ?></td>
                            <td class="text-end"><?php echo formatar_valor($linha['outras']); ?></td>
                            <td class="text-end"><?php echo formatar_valor($linha['icms_retido_st_petroleo_energia']); ?></td>
                            <td class="text-end"><?php echo formatar_valor($linha['icms_retido_st_outros_produtos']); ?></td>
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
                    <td class="text-end fw-bold" id="total-valor-contabil"><?php echo formatar_valor($totalizadores['valor_contabil']); ?></td>
                    <td class="text-end fw-bold" id="total-base-calculo"><?php echo formatar_valor($totalizadores['base_calculo']); ?></td>
                    <td class="text-end fw-bold" id="total-outras"><?php echo formatar_valor($totalizadores['outras']); ?></td>
                    <td class="text-end fw-bold" id="total-st-petroleo"><?php echo formatar_valor($totalizadores['icms_retido_st_petroleo_energia']); ?></td>
                    <td class="text-end fw-bold" id="total-st-outros"><?php echo formatar_valor($totalizadores['icms_retido_st_outros_produtos']); ?></td>
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

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="valorContabil" class="form-label">Valor Contábil</label>
                                    <input type="text" class="form-control currency-input" id="valorContabil" placeholder="0,00">
                                </div>
                                <div class="mb-3">
                                    <label for="baseCalculo" class="form-label">Base de Cálculo</label>
                                    <input type="text" class="form-control currency-input" id="baseCalculo" placeholder="0,00">
                                </div>
                                <div class="mb-3">
                                    <label for="outras" class="form-label">Outras</label>
                                    <input type="text" class="form-control currency-input" id="outras" placeholder="0,00">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="stPetroleo" class="form-label">ST Petróleo/Energia Elétrica</label>
                                    <input type="text" class="form-control currency-input" id="stPetroleo" placeholder="0,00">
                                </div>
                                <div class="mb-3">
                                    <label for="stOutros" class="form-label">ST Outros Produtos</label>
                                    <input type="text" class="form-control currency-input" id="stOutros" placeholder="0,00">
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

        function adicionarUF(uf) {
            $('#modalMode').val('add');
            $('#modalTitle').text('Adicionar UF: ');
            //$('#selectUF').val(uf).prop('disabled', true);
            //$('#currentUF').val(uf);
            $('#formUF')[0].reset();
            $('#modalUF').modal('show');
        }

        function editarUF(uf) {
            $('#modalMode').val('edit');
            $('#modalTitle').text('Editar UF: ' + uf);
            $('#currentUF').val(uf);
            $('#selectUF').val(uf).prop('disabled', true);

            // Carregar dados atuais
            fetch(`../quadros/ajax_get_uf_49.php?dime_id=<?php echo $dime_id; ?>&uf=${uf}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        $('#valorContabil').val(formatarValorParaInput(data.dados.valor_contabil));
                        $('#baseCalculo').val(formatarValorParaInput(data.dados.base_calculo));
                        $('#outras').val(formatarValorParaInput(data.dados.outras));
                        $('#stPetroleo').val(formatarValorParaInput(data.dados.icms_retido_st_petroleo_energia));
                        $('#stOutros').val(formatarValorParaInput(data.dados.icms_retido_st_outros_produtos));
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
                valor_contabil: $('#valorContabil').val() || '0,00',
                base_calculo: $('#baseCalculo').val() || '0,00',
                outras: $('#outras').val() || '0,00',
                icms_retido_st_petroleo_energia: $('#stPetroleo').val() || '0,00',
                icms_retido_st_outros_produtos: $('#stOutros').val() || '0,00'
            };

            const url = mode === 'add' ? '../quadros/ajax_add_uf_49.php' : '../quadros/ajax_update_uf_49.php';

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
                    showToast('success', `Entrada por UF ${uf} ${action} com sucesso!`);
                    $('#modalUF').modal('hide');

                    // Aguardar o toast aparecer antes de recarregar
                    setTimeout(() => {
                        if (window.parent && window.parent.reloadQuadro49) {
                            window.parent.reloadQuadro49();
                        } else {
                            location.reload();
                        }
                    }, 1500); // 1000ms de delay
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
                fetch('../quadros/ajax_delete_49.php', {
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
                        showToast('success', `Entrada por UF ${uf} excluída com sucesso!`);

                        // Aguardar o toast aparecer antes de recarregar
                        setTimeout(() => {
                            if (window.parent && window.parent.reloadQuadro49) {
                                window.parent.reloadQuadro49();
                            } else {
                                location.reload();
                            }
                        }, 1500); // 1000ms de delay
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

        function mostrarAjudaQuadro49() {

            if (!document.getElementById('modalAjudaQuadro49')) {
                const modalHTML = `
                    <div class="modal fade" id="modalAjudaQuadro49" tabindex="-1" aria-labelledby="modalAjudaLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <div class="modal-header bg-info text-white">
                                    <h5 class="modal-title" id="modalAjudaLabel">
                                        <i class="fas fa-book-open me-2"></i>
                                        Manual Completo - Quadro 49: Entradas por Unidade da Federação
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="container-fluid">
                                            <!-- Introdução -->
                                            <div class="card border-primary mb-4">
                                                <div class="card-header bg-primary text-white">
                                                    <h6 class="card-title mb-0">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        3.2.21. Quadro 49 - Entradas por Unidade da Federação
                                                    </h6>
                                                </div>
                                                <div class="card-body">
                                                    <p class="card-text">
                                                        <strong>Objetivo:</strong> Este quadro deve ser preenchido por todos os estabelecimentos que realizaram operações de aquisição de serviços, mercadorias ou energia elétrica.
                                                    </p>
                                                    <p class="card-text">
                                                        <strong>Totalizador:</strong> O último registro desta sequência será o "Totalizador", para fins de fechamentos, devendo ser composto com o Código de Unidade da Federação igual a "TT" e como valor o somatório das respectivas colunas: Valor Contábil, Base de Cálculo, Outras, ICMS Retido ST Petróleo/Energia e ICMS Retido ST Outros Produtos.
                                                    </p>
                                                </div>
                                            </div>

                                            <!-- Estrutura da Tabela -->
                                            <div class="card mb-4">
                                                <div class="card-header">
                                                    <h6 class="card-title mb-0">
                                                        <i class="fas fa-table me-2"></i>
                                                        Estrutura da Tabela (conforme Manual DIME-SEF 153/12)
                                                    </h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="table-responsive">
                                                        <table class="table table-bordered table-sm">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th rowspan="2" class="text-center align-middle">Unidade da<br/>Federação</th>
                                                                    <th rowspan="2" class="text-center align-middle">Valor<br/>Contábil</th>
                                                                    <th rowspan="2" class="text-center align-middle">Base de<br/>Cálculo</th>
                                                                    <th rowspan="2" class="text-center align-middle">Outras</th>
                                                                    <th colspan="2" class="text-center">ICMS Retido por Substituição Tributária</th>
                                                                </tr>
                                                                <tr>
                                                                    <th class="text-center">Petróleo/<br/>Energia Elétrica</th>
                                                                    <th class="text-center">Outros<br/>Produtos</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr>
                                                                    <td class="text-center">AC</td>
                                                                    <td class="text-center">-</td>
                                                                    <td class="text-center">-</td>
                                                                    <td class="text-center">-</td>
                                                                    <td class="text-center">-</td>
                                                                    <td class="text-center">-</td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="text-center">AL</td>
                                                                    <td class="text-center">-</td>
                                                                    <td class="text-center">-</td>
                                                                    <td class="text-center">-</td>
                                                                    <td class="text-center">-</td>
                                                                    <td class="text-center">-</td>
                                                                </tr>
                                                                <tr class="table-light fw-bold">
                                                                    <td class="text-center">TT</td>
                                                                    <td class="text-center">Somatório</td>
                                                                    <td class="text-center">Somatório</td>
                                                                    <td class="text-center">Somatório</td>
                                                                    <td class="text-center">Somatório</td>
                                                                    <td class="text-center">Somatório</td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div class="alert alert-light mt-2">
                                                        <small><strong>Nota:</strong> A tabela deve conter uma linha para cada UF onde houve operações, finalizando com a linha TT (Totalizador). Os valores são calculados automaticamente pelo sistema.</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Campos Detalhados -->
                                            <div class="accordion" id="manualAccordion">
                                                <div class="accordion-item">
                                                    <h2 class="accordion-header">
                                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#uf">
                                                            <i class="fas fa-map-marker-alt me-2"></i>
                                                            3.2.21.2. Coluna Sigla da Unidade da Federação
                                                        </button>
                                                    </h2>
                                                    <div id="uf" class="accordion-collapse collapse show" data-bs-parent="#manualAccordion">
                                                        <div class="accordion-body">
                                                            <p>Informar a sigla da unidade da federação de origem da mercadoria ou energia elétrica ou da localização do prestador de serviço de comunicação ou transporte, conforme Tabela de Siglas das Unidades da Federação.</p>
                                                            <div class="alert alert-info">
                                                                <strong>Observação importante:</strong> Os valores das operações e prestações recebidas do exterior do país, classificadas no CFOP do grupo 3.000 - Entradas ou Aquisições de Serviços do Exterior, serão lançadas com a sigla "EX".
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="accordion-item">
                                                    <h2 class="accordion-header">
                                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#valorContabil">
                                                            <i class="fas fa-calculator me-2"></i>
                                                            3.2.21.3. Coluna Valor Contábil
                                                        </button>
                                                    </h2>
                                                    <div id="valorContabil" class="accordion-collapse collapse" data-bs-parent="#manualAccordion">
                                                        <div class="accordion-body">
                                                            <p>Informar o valor total lançado na coluna "Valor contábil" do Livro Registro de Entradas referente às notas fiscais de aquisição no período de referência.</p>
                                                            <p>Este valor representa o montante total das mercadorias/serviços adquiridos, independente de tributação.</p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="accordion-item">
                                                    <h2 class="accordion-header">
                                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#baseCalculo">
                                                            <i class="fas fa-chart-line me-2"></i>
                                                            3.2.21.4. Coluna Base de Cálculo
                                                        </button>
                                                    </h2>
                                                    <div id="baseCalculo" class="accordion-collapse collapse" data-bs-parent="#manualAccordion">
                                                        <div class="accordion-body">
                                                            <p>Informar o valor total lançado na coluna "Base de Cálculo" do Livro Registro de Entradas referente às notas fiscais de aquisição no período de referência.</p>
                                                            <p>Este valor serve de base para o cálculo do ICMS devido nas operações de entrada.</p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="accordion-item">
                                                    <h2 class="accordion-header">
                                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#outras">
                                                            <i class="fas fa-plus-circle me-2"></i>
                                                            3.2.21.5. Coluna Outras
                                                        </button>
                                                    </h2>
                                                    <div id="outras" class="accordion-collapse collapse" data-bs-parent="#manualAccordion">
                                                        <div class="accordion-body">
                                                            <p>Informar o somatório dos valores lançados nas colunas "Isentas ou Não Tributadas" e "Outras" do Livro Registro de Entradas referente às notas fiscais de aquisição no período de referência.</p>
                                                            <p>Inclui operações isentas, não tributadas e outras situações especiais de tributação.</p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="accordion-item">
                                                    <h2 class="accordion-header">
                                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#petroleo">
                                                            <i class="fas fa-gas-pump me-2"></i>
                                                            3.2.21.6. Coluna ICMS Retido por ST - Petróleo/Energia Elétrica
                                                        </button>
                                                    </h2>
                                                    <div id="petroleo" class="accordion-collapse collapse" data-bs-parent="#manualAccordion">
                                                        <div class="accordion-body">
                                                            <p>Informar o valor total da retenção lançado na coluna "Observações" do Livro Registro de Entradas referente às notas fiscais de aquisição de petróleo, inclusive lubrificantes, combustíveis líquidos e gasosos dele derivados, e energia elétrica, no período de referência.</p>
                                                            <p>Este campo é específico para operações sujeitas ao regime de Substituição Tributária envolvendo combustíveis e energia elétrica.</p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="accordion-item">
                                                    <h2 class="accordion-header">
                                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#outrosProdutos">
                                                            <i class="fas fa-boxes me-2"></i>
                                                            3.2.21.7. Coluna ICMS Retido por ST - Outros Produtos
                                                        </button>
                                                    </h2>
                                                    <div id="outrosProdutos" class="accordion-collapse collapse" data-bs-parent="#manualAccordion">
                                                        <div class="accordion-body">
                                                            <p>Informar o valor total da retenção lançado na coluna "Observações" do Livro Registro de Entradas referente às notas fiscais de aquisição de outros produtos, no período de referência.</p>
                                                            <p>Este campo abrange todos os demais produtos sujeitos ao regime de Substituição Tributária, exceto petróleo e energia elétrica.</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Orientações Finais -->
                                            <div class="alert alert-success mt-4">
                                                <h6 class="alert-heading">
                                                    <i class="fas fa-check-circle me-2"></i>
                                                    Orientações para Preenchimento Correto
                                                </h6>
                                                <ul class="mb-0">
                                                    <li><strong>Periodicidade:</strong> Dados do período de referência da declaração DIME</li>
                                                    <li><strong>Origem dos dados:</strong> Livro Registro de Entradas e documentos fiscais</li>
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
                    </div>
                `;

                document.body.insertAdjacentHTML('beforeend', modalHTML);
            }

            const modal = new bootstrap.Modal(document.getElementById('modalAjudaQuadro49'));
            modal.show();
        }

    </script>
</body>
</html>
