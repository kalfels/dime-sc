<?php
// Verificar se está sendo chamado diretamente
if (!isset($_GET['dime_id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Parâmetro dime_id obrigatório';
    exit;
}

$dime_id = (int)$_GET['dime_id'];

// Conectar ao banco
require_once __DIR__ . '/../includes/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verificar se é POST (salvar dados)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'add_cfop') {
            // Adicionar novo CFOP de Saída
            $campos = [
                'dime_declaracao_id' => $dime_id,
                'cfop_id' => (int)$_POST['cfop_id'],
                'valor_contabil' => (float)str_replace(',', '.', str_replace('.', '', $_POST['valor_contabil'] ?? '0')),
                'base_calculo' => (float)str_replace(',', '.', str_replace('.', '', $_POST['base_calculo'] ?? '0')),
                'valor_imposto' => (float)str_replace(',', '.', str_replace('.', '', $_POST['valor_imposto'] ?? '0')),
                'valor_isento_nao_tributado' => (float)str_replace(',', '.', str_replace('.', '', $_POST['valor_isento_nao_tributado'] ?? '0')),
                'valor_outras' => (float)str_replace(',', '.', str_replace('.', '', $_POST['valor_outras'] ?? '0')),
                'base_calculo_imposto_retido' => (float)str_replace(',', '.', str_replace('.', '', $_POST['base_calculo_imposto_retido'] ?? '0')),
                'imposto_retido' => (float)str_replace(',', '.', str_replace('.', '', $_POST['imposto_retido'] ?? '0')),
                'debito_imposto_diferencial_aliquota' => (float)str_replace(',', '.', str_replace('.', '', $_POST['debito_imposto_diferencial_aliquota'] ?? '0')),
                'observacoes' => trim($_POST['observacoes'] ?? '')
            ];

            $sql = "INSERT INTO dime_quadro_02 (
                dime_declaracao_id, cfop_id, valor_contabil, base_calculo, valor_imposto,
                valor_isento_nao_tributado, valor_outras, base_calculo_imposto_retido,
                imposto_retido, debito_imposto_diferencial_aliquota, observacoes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($campos));

            echo "<div class='alert alert-success'>CFOP de saída adicionado com sucesso!</div>";
        } elseif ($action === 'delete_cfop') {
            // Remover CFOP de saída
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM dime_quadro_02 WHERE id = ? AND dime_declaracao_id = ?");
            $stmt->execute([$id, $dime_id]);

            echo "<div class='alert alert-success'>CFOP de saída excluido com sucesso!</div>";
        } elseif ($action === 'delete_all_cfops') {
            // Excluir todas as CFOPs de saída desta DIME
            $stmt = $pdo->prepare("DELETE FROM dime_quadro_02 WHERE dime_declaracao_id = ?");
            $stmt->execute([$dime_id]);

            echo "<div class='alert alert-success'>Todas as CFOPs de saída foram excluidas com sucesso!</div>";
        }
    }

    // Carregar CFOPs existentes e calcular totais
    $stmt = $pdo->prepare("
        SELECT q02.*, cf.descricao as cfop_descricao, cf.codigo as cfop_codigo
        FROM dime_quadro_02 q02
        JOIN t_cfops cf ON q02.cfop_id = cf.id
        WHERE q02.dime_declaracao_id = ?
        ORDER BY cf.codigo ASC
    ");
    $stmt->execute([$dime_id]);
    $cfops = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular totais
    $totais = [
        'valor_contabil' => 0,
        'base_calculo' => 0,
        'valor_imposto' => 0,
        'valor_isento_nao_tributado' => 0,
        'valor_outras' => 0,
        'imposto_retido' => 0,
        'debito_imposto_diferencial_aliquota' => 0
    ];

    foreach ($cfops as $cfop) {
        $totais['valor_contabil'] += $cfop['valor_contabil'];
        $totais['base_calculo'] += $cfop['base_calculo'];
        $totais['valor_imposto'] += $cfop['valor_imposto'];
        $totais['valor_isento_nao_tributado'] += $cfop['valor_isento_nao_tributado'];
        $totais['valor_outras'] += $cfop['valor_outras'];
        $totais['imposto_retido'] += $cfop['imposto_retido'];
        $totais['debito_imposto_diferencial_aliquota'] += $cfop['debito_imposto_diferencial_aliquota'];
    }

    // Carregar lista de CFOPs de saída disponíveis
    $stmt = $pdo->prepare("SELECT id, codigo, descricao FROM t_cfops WHERE tipo IN ('S') ORDER BY codigo ASC");
    $stmt->execute();
    $cfops_disponiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $cfops = [];
    $cfops_disponiveis = [];
    $error_message = "Erro: " . $e->getMessage();
    echo "<div class='alert alert-danger'>Erro: " . $e->getMessage() . "</div>";
}

// Função helper para valores seguros
function safe_value($data, $key, $default = '') {
    return (isset($data[$key])) ? htmlspecialchars($data[$key]) : $default;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        <i class="bi bi-arrow-up-circle text-danger me-2"></i>
        Quadros 02/03 - Saídas CFOPs
    </h5>
    <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalAjudaQuadro0203">
        <i class="bi bi-question-circle me-1"></i>Ajuda
    </button>
</div>

<?php if (isset($error_message)): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <?php echo $error_message; ?>
</div>
<?php endif; ?>

<!-- Formulário para adicionar novo CFOP -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="card-title">
            <i class="bi bi-arrow-up-circle me-2"></i>
            Adicionar CFOP de Saída
        </h6>
    </div>
    <div class="card-body">
        <form id="form-adicionar-cfop">
            <input type="hidden" name="action" value="add_cfop">

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="cfop_id" class="form-label">
                        <strong>CFOP</strong>
                    </label>
                    <select class="form-select" id="cfop_id" name="cfop_id" required>
                        <option value="">Selecione um CFOP</option>
                        <?php foreach ($cfops_disponiveis as $cfop): ?>
                        <option value="<?php echo $cfop['id']; ?>">
                            <?php echo $cfop['codigo'] . ' - ' . htmlspecialchars(substr($cfop['descricao'], 0, 50)); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">
                        CFOP é obrigatório
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="valor_contabil" class="form-label">
                        <strong>Valor Contábil (R$)</strong>
                    </label>
                    <input type="text" class="form-control" id="valor_contabil" name="valor_contabil"
                           placeholder="0,00" required>
                </div>

                <div class="col-md-6">
                    <label for="base_calculo" class="form-label">
                        <strong>Base de Cálculo (R$)</strong>
                    </label>
                    <input type="text" class="form-control" id="base_calculo" name="base_calculo"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="valor_imposto" class="form-label">
                        <strong>Valor ICMS (R$)</strong>
                    </label>
                    <input type="text" class="form-control" id="valor_imposto" name="valor_imposto"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="valor_isento_nao_tributado" class="form-label">
                        <strong>Isento/Não Tributado (R$)</strong>
                    </label>
                    <input type="text" class="form-control" id="valor_isento_nao_tributado" name="valor_isento_nao_tributado"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="valor_outras" class="form-label">
                        <strong>Outras (R$)</strong>
                    </label>
                    <input type="text" class="form-control" id="valor_outras" name="valor_outras"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="base_calculo_imposto_retido" class="form-label">
                        <strong>Base Cálculo Imposto Retido (R$)</strong>
                    </label>
                    <input type="text" class="form-control" id="base_calculo_imposto_retido" name="base_calculo_imposto_retido"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="imposto_retido" class="form-label">
                        <strong>Imposto Retido (R$)</strong>
                    </label>
                    <input type="text" class="form-control" id="imposto_retido" name="imposto_retido"
                           placeholder="0,00">
                </div>

                <div class="col-md-6">
                    <label for="debito_imposto_diferencial_aliquota" class="form-label">
                        <strong>Débito Diferencial Alíquota (R$)</strong>
                    </label>
                    <input type="text" class="form-control" id="debito_imposto_diferencial_aliquota" name="debito_imposto_diferencial_aliquota"
                           placeholder="0,00">
                </div>

                <div class="col-12">
                    <label for="observacoes" class="form-label">
                        <strong>Observações</strong>
                    </label>
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="3"
                              placeholder="Observações adicionais (opcional)"></textarea>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-plus-circle me-2"></i>
                    Adicionar CFOP
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Lista de CFOPs adicionados -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            <i class="bi bi-list-check text-info me-2"></i>
            CFOPs de Saída Cadastrados
        </h6>
        <span class="badge bg-info"><?php echo count($cfops); ?> CFOPs</span>
    </div>
    <div class="card-body">
        <?php if (empty($cfops)): ?>
        <div class="text-center text-muted py-4">
            <i class="bi bi-info-circle fs-1 mb-3"></i>
            <p class="mb-0">Nenhum CFOP de saída cadastrado ainda.</p>
            <small>Use o formulário acima para adicionar CFOPs de saída.</small>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>CFOP</th>
                        <th>Valor Contábil</th>
                        <th>Base de Cálculo</th>
                        <th>Imposto Debitado</th>
                        <th>Isentas/Não Trib.</th>
                        <th>Outras Sem Débito</th>
                        <th>Base Calc. Retido</th>
                        <th>Imposto Retido</th>
                        <th>Dif. Alíquota</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cfops as $cfop): ?>
                    <tr>
                        <td>
                            <strong><?php echo $cfop['cfop_codigo']; ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars(substr($cfop['cfop_descricao'], 0, 30)); ?>...</small>
                        </td>
                        <td>R$ <?php echo number_format($cfop['valor_contabil'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($cfop['base_calculo'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($cfop['valor_imposto'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($cfop['valor_isento_nao_tributado'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($cfop['valor_outras'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($cfop['base_calculo_imposto_retido'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($cfop['imposto_retido'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($cfop['debito_imposto_diferencial_aliquota'], 2, ',', '.'); ?></td>
                        <td class="text-nowrap">
                            <div class="d-flex gap-1 align-items-center">
                                <button class="btn btn-sm btn-outline-danger flex-shrink-0"
                                        onclick="parent.removerCFOPGlobal(<?php echo $cfop['id']; ?>)"
                                        title="Excluir este CFOP"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php if (!empty($cfop['observacoes'])): ?>
                                <button class="btn btn-sm btn-outline-info flex-shrink-0"
                                        onclick="parent.mostrarObservacoesGlobal('<?php echo addslashes($cfop['observacoes']); ?>')"
                                        title="Ver observações"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top">
                                    <i class="bi bi-chat-text"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (!empty($cfops)): ?>
                    <!-- Linha de Total -->
                    <tr class="table-primary fw-bold">
                        <td class="text-end">
                            <strong>TOTAL GERAL</strong>
                        </td>
                        <td>R$ <?php echo number_format($totais['valor_contabil'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($totais['base_calculo'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($totais['valor_imposto'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($totais['valor_isento_nao_tributado'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($totais['valor_outras'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($totais['base_calculo_imposto_retido'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($totais['imposto_retido'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($totais['debito_imposto_diferencial_aliquota'], 2, ',', '.'); ?></td>
                        <td class="text-nowrap"></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($cfops)): ?>
        <div class="d-flex justify-content-end mt-3">
            <button class="btn btn-danger"
                    onclick="parent.excluirTodasCFOPs()"
                    title="Excluir todas as CFOPs de saída desta declaração">
                <i class="bi bi-trash-fill me-1"></i>
                Excluir Todas as CFOPs
            </button>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal de Ajuda do Quadro 02/03 -->
<div class="modal fade" id="modalAjudaQuadro0203" tabindex="-1" aria-labelledby="modalAjudaQuadro0203Label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalAjudaQuadro0203Label">
                    <i class="bi bi-question-circle me-2"></i>
                    Ajuda - Quadros 02/03: Saídas CFOPs
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">

                <!-- Visão Geral -->
                <div class="mb-4">
                    <h6 class="text-danger mb-3">
                        <i class="bi bi-info-circle me-2"></i>Visão Geral
                    </h6>
                    <p>O Quadro 02/03 registra os valores fiscais das <strong>saídas</strong> (operações de saída) da empresa, totalizados por Código Fiscal de Operações e Prestações (CFOP). Estes dados são transportados diretamente do livro Registro de Saídas.</p>
                    <div class="alert alert-danger">
                        <strong>📤 Saídas incluem:</strong> Vendas, transferências de saída, devoluções de compras, etc.
                    </div>
                    <div class="alert alert-warning">
                        <strong>⚠️ Importante:</strong> As saídas geram <strong>DÉBITO</strong> do ICMS, ou seja, são operações que o estabelecimento deve recolher imposto.
                    </div>
                </div>

                <!-- Estrutura da Tabela -->
                <div class="mb-4">
                    <h6 class="text-primary mb-3">
                        <i class="bi bi-table me-2"></i>Estrutura da Tabela
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Coluna</th>
                                    <th>Descrição</th>
                                    <th>Tipo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>CFOP</strong></td>
                                    <td>Código Fiscal de Operações e Prestações</td>
                                    <td>Obrigatório</td>
                                </tr>
                                <tr>
                                    <td><strong>Valor Contábil</strong></td>
                                    <td>Valor total da operação/prestação</td>
                                    <td>Obrigatório</td>
                                </tr>
                                <tr>
                                    <td><strong>Base de Cálculo</strong></td>
                                    <td>Valor sobre o qual incide o ICMS</td>
                                    <td>Opcional</td>
                                </tr>
                                <tr>
                                    <td><strong>Imposto Debitado</strong></td>
                                    <td>Valor do imposto debitado (devido)</td>
                                    <td>Opcional</td>
                                </tr>
                                <tr>
                                    <td><strong>Isento/Não Tributado</strong></td>
                                    <td>Valor isento ou não tributado</td>
                                    <td>Opcional</td>
                                </tr>
                                <tr>
                                    <td><strong>Outras</strong></td>
                                    <td>Valor de outras classificações (sem débito)</td>
                                    <td>Opcional</td>
                                </tr>
                                <tr>
                                    <td><strong>Base Calc. Retido</strong></td>
                                    <td>Base de cálculo do imposto retido (ST)</td>
                                    <td>Opcional</td>
                                </tr>
                                <tr>
                                    <td><strong>Imposto Retido</strong></td>
                                    <td>ICMS retido nas notas fiscais emitidas</td>
                                    <td>Opcional</td>
                                </tr>
                                <tr>
                                    <td><strong>Dif. Alíquota</strong></td>
                                    <td>Débito por diferencial de alíquota</td>
                                    <td>Opcional</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Detalhes dos Campos -->
                <div class="mb-4">
                    <h6 class="text-primary mb-3">
                        <i class="bi bi-file-earmark-text me-2"></i>Detalhes dos Campos
                    </h6>
                    <div class="accordion" id="accordionCampos0203">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCFOPS">
                                    <strong>CFOP:</strong> Código Fiscal de Operações e Prestações
                                </button>
                            </h2>
                            <div id="collapseCFOPS" class="accordion-collapse collapse show" data-bs-parent="#accordionCampos0203">
                                <div class="accordion-body">
                                    <p><strong>Como preencher:</strong> Selecione o CFOP correspondente à operação de saída realizada.</p>
                                    <p><strong>Onde encontrar:</strong> No documento fiscal emitido (NF-e, NF, Cupom Fiscal, etc.)</p>
                                    <p><strong>CFOPs de saída comuns:</strong></p>
                                    <ul class="small">
                                        <li><strong>5.101</strong> - Venda de produção do estabelecimento</li>
                                        <li><strong>5.102</strong> - Venda de mercadoria adquirida</li>
                                        <li><strong>6.101</strong> - Devolução de compra</li>
                                        <li><strong>5.201</strong> - Venda de produção do estabelecimento (ST)</li>
                                        <li><strong>6.201</strong> - Devolução de compra (ST)</li>
                                        <li><strong>5.901</strong> - Venda de produção do estabelecimento (interestadual)</li>
                                    </ul>
                                    <div class="alert alert-danger small">
                                        <strong>⚠️ Importante:</strong> Apenas CFOPs de saída (tipo "S") estão disponíveis neste quadro.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseValorContabilS">
                                    <strong>Valor Contábil:</strong> Valor total da operação
                                </button>
                            </h2>
                            <div id="collapseValorContabilS" class="accordion-collapse collapse" data-bs-parent="#accordionCampos0203">
                                <div class="accordion-body">
                                    <p><strong>Informação necessária:</strong> Valor contábil total da operação ou prestação de saída.</p>
                                    <p><strong>Onde encontrar:</strong> Campo "Valor Total da Nota" na NF-e ou NF emitida.</p>
                                    <p><strong>Inclui:</strong> Valor dos produtos/serviços + frete + seguro + outras despesas acessórias.</p>
                                    <div class="alert alert-info small">
                                        <strong>💡 Dica:</strong> Este é o valor bruto da operação de saída, antes de qualquer dedução.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBaseCalculoS">
                                    <strong>Base de Cálculo:</strong> Valor sobre o qual incide o ICMS
                                </button>
                            </h2>
                            <div id="collapseBaseCalculoS" class="accordion-collapse collapse" data-bs-parent="#accordionCampos0203">
                                <div class="accordion-body">
                                    <p><strong>Informação necessária:</strong> Valor da base de cálculo do imposto sobre o qual será calculado o ICMS devido.</p>
                                    <p><strong>Cálculo:</strong> Geralmente é o valor contábil menos IPI (quando incidente) e outras deduções permitidas.</p>
                                    <p><strong>Onde encontrar:</strong> Campo "Base de Cálculo do ICMS" na NF-e emitida.</p>
                                    <div class="alert alert-warning small">
                                        <strong>⚠️ Atenção:</strong> Nem todas as operações de saída têm base de cálculo (ex: isentas, não tributadas, ST).
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseImpostoDebitado">
                                    <strong>Imposto Debitado:</strong> Valor do ICMS devido
                                </button>
                            </h2>
                            <div id="collapseImpostoDebitado" class="accordion-collapse collapse" data-bs-parent="#accordionCampos0203">
                                <div class="accordion-body">
                                    <p><strong>Informação necessária:</strong> Valor do imposto debitado (ICMS devido) na operação de saída.</p>
                                    <p><strong>Cálculo:</strong> Base de cálculo × Alíquota do ICMS</p>
                                    <p><strong>Onde encontrar:</strong> Campo "Valor do ICMS" na NF-e emitida.</p>
                                    <p><strong>Quando preencher:</strong> Apenas em operações de saída que geram débito de ICMS.</p>
                                    <div class="alert alert-danger small">
                                        <strong>⚠️ Importante:</strong> Este valor será debitado no livro fiscal e representa imposto a recolher.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseIsentoNaoTributadoS">
                                    <strong>Isento/Não Tributado:</strong> Operações isentas ou não tributadas
                                </button>
                            </h2>
                            <div id="collapseIsentoNaoTributadoS" class="accordion-collapse collapse" data-bs-parent="#accordionCampos0203">
                                <div class="accordion-body">
                                    <p><strong>Informação necessária:</strong> Valor da operação ou prestação de saída classificada como isenta ou não tributada.</p>
                                    <p><strong>Quando usar:</strong></p>
                                    <ul class="small">
                                        <li><strong>Isenta:</strong> Operação beneficiada por isenção de ICMS</li>
                                        <li><strong>Não tributada:</strong> Operação fora do campo de incidência do ICMS</li>
                                    </ul>
                                    <p><strong>CFOPs comuns:</strong> 5.102 (com isenção), 5.109 (não tributada)</p>
                                    <p><strong>Nota:</strong> Estas operações não geram débito de ICMS.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOutrasS">
                                    <strong>Outras:</strong> Outras classificações (sem débito)
                                </button>
                            </h2>
                            <div id="collapseOutrasS" class="accordion-collapse collapse" data-bs-parent="#accordionCampos0203">
                                <div class="accordion-body">
                                    <p><strong>Informação necessária:</strong> Valor da operação ou prestação de saída classificada em "outras" situações que não geram débito de ICMS.</p>
                                    <p><strong>Quando usar:</strong> Para operações de saída que não se encaixam nas outras categorias específicas, mas que não geram obrigação tributária.</p>
                                    <p><strong>Exemplos:</strong> Operações com suspensão, diferimento, redução de base de cálculo, etc.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBaseCalcRetidoS">
                                    <strong>Base Calc. Retido:</strong> Base de cálculo do imposto retido
                                </button>
                            </h2>
                            <div id="collapseBaseCalcRetidoS" class="accordion-collapse collapse" data-bs-parent="#accordionCampos0203">
                                <div class="accordion-body">
                                    <p><strong>Informação necessária:</strong> Valor da base de cálculo do imposto retido nas operações sujeitas ao regime de substituição tributária.</p>
                                    <p><strong>Quando usar:</strong> Em operações de saída sujeitas à ST onde o estabelecimento é o substituto tributário.</p>
                                    <p><strong>CFOPs comuns:</strong> 5.201, 5.202, 6.201, etc.</p>
                                    <div class="alert alert-info small">
                                        <strong>💡 Observação:</strong> Este valor é informado nas notas fiscais emitidas quando o estabelecimento calcula e retém o ICMS devido.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseImpostoRetidoS">
                                    <strong>Imposto Retido:</strong> ICMS retido nas notas fiscais emitidas
                                </button>
                            </h2>
                            <div id="collapseImpostoRetidoS" class="accordion-collapse collapse" data-bs-parent="#accordionCampos0203">
                                <div class="accordion-body">
                                    <p><strong>Informação necessária:</strong> Valor do imposto retido nas operações sujeitas ao regime de substituição tributária, informado nas notas fiscais emitidas.</p>
                                    <p><strong>Quando usar:</strong> Quando o estabelecimento calcula e informa o ICMS-ST nas NF-e emitidas.</p>
                                    <p><strong>Onde encontrar:</strong> Campo "Valor do ICMS-ST" na NF-e emitida.</p>
                                    <div class="alert alert-warning small">
                                        <strong>⚠️ Importante:</strong> Este valor representa ICMS que já foi calculado e informado ao destinatário, devendo ser recolhido.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDebitoDiferencialS">
                                    <strong>Débito Dif. Alíquota:</strong> Débito por diferencial de alíquota
                                </button>
                            </h2>
                            <div id="collapseDebitoDiferencialS" class="accordion-collapse collapse" data-bs-parent="#accordionCampos0203">
                                <div class="accordion-body">
                                    <p><strong>Informação necessária:</strong> Valor do débito do imposto devido por diferencial de alíquota nas operações de saída.</p>
                                    <p><strong>Quando ocorre:</strong> Quando o estabelecimento vende mercadorias para outro estado com alíquota inferior à interna.</p>
                                    <p><strong>Cálculo:</strong> (Valor da operação × Alíquota interna) - (Valor da operação × Alíquota interestadual)</p>
                                    <div class="alert alert-danger small">
                                        <strong>⚠️ Importante:</strong> Este débito aumenta o valor do ICMS a ser recolhido na operação interestadual.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Como Preencher -->
                <div class="mb-4">
                    <h6 class="text-danger mb-3">
                        <i class="bi bi-pencil-square me-2"></i>Como Preencher o Quadro 02/03
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white">
                                    <h6 class="mb-0"><i class="bi bi-check-circle me-1"></i>Passo a Passo</h6>
                                </div>
                                <div class="card-body">
                                    <ol class="small mb-0">
                                        <li>Identifique todas as notas fiscais de saída do período</li>
                                        <li>Agrupe por CFOP (mesmo CFOP pode ter múltiplas saídas)</li>
                                        <li>Some os valores de débito de ICMS de cada CFOP</li>
                                        <li>Adicione cada CFOP com seus totais no sistema</li>
                                        <li>Verifique se os débitos estão corretos</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-info">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0"><i class="bi bi-lightbulb me-1"></i>Dicas Práticas</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="small mb-0">
                                        <li>Use o livro Registro de Saídas como referência</li>
                                        <li>Confirme se os valores de débito estão corretos</li>
                                        <li>Não esqueça operações interestaduais que geram diferencial</li>
                                        <li>Verifique operações ST onde você é o substituto</li>
                                        <li>Mantenha os documentos fiscais organizados</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Regras e Validações -->
                <div class="alert alert-warning">
                    <h6 class="alert-heading mb-2">
                        <i class="bi bi-exclamation-triangle me-2"></i>Regras Importantes
                    </h6>
                    <ul class="mb-0 small">
                        <li><strong>CFOP obrigatório:</strong> Todo registro deve ter um CFOP válido de saída</li>
                        <li><strong>Valor contábil obrigatório:</strong> Não pode ser zero</li>
                        <li><strong>Débito vs. Outras:</strong> Valores em "Outras" não geram obrigação de pagamento</li>
                        <li><strong>ST nas saídas:</strong> Quando você é substituto, informa o valor retido</li>
                        <li><strong>Diferencial:</strong> Aplicável apenas em operações interestaduais</li>
                        <li><strong>Consistência:</strong> Base de cálculo deve ser compatível com o valor contábil</li>
                        <li><strong>Observações:</strong> Use para registrar informações relevantes sobre a operação</li>
                    </ul>
                </div>

                <!-- Exemplo Prático -->
                <div class="mb-4">
                    <h6 class="text-info mb-3">
                        <i class="bi bi-calculator me-2"></i>Exemplo Prático
                    </h6>
                    <div class="card">
                        <div class="card-body">
                            <p class="mb-2"><strong>Exemplo: Venda de mercadorias dentro do estado</strong></p>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>CFOP</th>
                                            <th>Valor Contábil</th>
                                            <th>Base Cálculo</th>
                                            <th>Imposto Debitado</th>
                                            <th>Isentas/Não Trib.</th>
                                            <th>Outras</th>
                                            <th>Base Calc. Retido</th>
                                            <th>Imposto Retido</th>
                                            <th>Dif. Alíquota</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="text-center"><strong>5.102</strong></td>
                                            <td class="text-end">R$ 15.000,00</td>
                                            <td class="text-end">R$ 15.000,00</td>
                                            <td class="text-end">R$ 2.550,00</td>
                                            <td class="text-end">R$ 0,00</td>
                                            <td class="text-end">R$ 0,00</td>
                                            <td class="text-end">R$ 0,00</td>
                                            <td class="text-end">R$ 0,00</td>
                                            <td class="text-end">R$ 0,00</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <small class="text-muted">
                                <strong>Explicação:</strong> Venda de mercadorias dentro do estado (CFOP 5.102), valor total R$ 15.000,00,
                                base de cálculo completa, ICMS debitado de 17% (R$ 2.550,00) - este valor será recolhido aos cofres públicos.
                            </small>
                        </div>
                    </div>
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
    const form = document.getElementById('form-adicionar-cfop');
    if (!form) return;

    // Listener para interceptar submit e usar AJAX
    form.onsubmit = function(e) {
        e.preventDefault();

        salvarCFOP();
        return false;
    };

    // Inicializar tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Máscaras para valores monetários
    setupMasks();
})();

function salvarCFOP() {
    const form = document.getElementById('form-adicionar-cfop');

    if (!form) {
        showMessage('❌ Erro: Formulário não encontrado!', 'danger');
        return;
    }

    const formData = new FormData(form);

    // Mostrar loading
    const submitBtn = form.querySelector('button[type="submit"]');

    if (!submitBtn) {
        showMessage('❌ Erro: Botão não encontrado!', 'danger');
        return;
    }

    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Salvando...';

    // URL para salvar - usando endpoint AJAX dedicado
    const postUrl = 'quadros/ajax_save_0203.php';
    
    // Adicionar dime_id ao FormData
    formData.append('dime_id', window.DIME_CONFIG?.dimeId || new URLSearchParams(window.location.search).get('dime_id') || new URLSearchParams(window.parent.location.search).get('id'));

    fetch(postUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {

        if (data.success) {
            // Mostrar mensagem de sucesso
            alert('✅ ' + data.message);

            // Recarregar quadro
            setTimeout(() => {
                if (window.parent && window.parent.loadQuadro) {
                    try {
                        window.parent.loadQuadro('0203');
                    } catch (error) {
                        location.reload();
                    }
                } else {
                    location.reload();
                }
            }, 1000);
        } else {
            showMessage('❌ ' + data.message, 'danger');
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

function setupMasks() {
    // Máscaras para valores monetários
    const camposMonetarios = [
        'valor_contabil', 'base_calculo', 'valor_imposto', 'valor_isento_nao_tributado',
        'valor_outras', 'base_calculo_imposto_retido', 'imposto_retido', 'debito_imposto_diferencial_aliquota'
    ];

    camposMonetarios.forEach(campoId => {
        const campo = document.getElementById(campoId);
        if (campo) {
            campo.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 2) {
                    value = value.slice(0, -2) + ',' + value.slice(-2);
                }
                if (value.length > 6) {
                    value = value.slice(0, -6) + '.' + value.slice(-6);
                }
                if (value.length > 10) {
                    value = value.slice(0, -10) + '.' + value.slice(-10);
                }
                e.target.value = value;
            });

            // Formatar valor inicial se existir
            if (campo.value) {
                campo.value = parseFloat(campo.value.replace(',', '.')).toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
        }
    });
}
</script>
