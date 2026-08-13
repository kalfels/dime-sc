<?php
/**
 * DIME-SC - Quadro 10 - Débitos Específicos
 * Sistema de gestão de declarações DIME para Santa Catarina
 */

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

    // Verificar se é POST (salvar dados) - REMOVIDO - salvamento feito via ajax_save_0405.php

    // Carregar dados do Quadro 10
    $stmt = $pdo->prepare("SELECT * FROM dime_quadro_10 WHERE dime_declaracao_id = ?");
    $stmt->execute([$dime_id]);
    $dados_quadro10 = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Se não há dados no Quadro 10, inicializar com valores vazios
    if (empty($dados_quadro10)) {
        $dados_quadro10 = [
            'item_010_debito_importacao' => 0.00,
            'item_020_debito_entrada_uf' => 0.00,
            'item_030_debito_responsabilidade' => 0.00,
            'item_040_debito_fato_gerador' => 0.00,
            'item_050_outros_debitos' => 0.00,
            'item_990_total_debitos' => 0.00,
            'observacoes' => ''
        ];
    }

} catch (Exception $e) {
    $dados_quadro10 = [];
    $error_message = "Erro: " . $e->getMessage();
    echo "<div class='alert alert-danger'>Erro: " . $e->getMessage() . "</div>";
}

// Função helper para valores seguros
function safe_value($data, $key, $default = '') {
    return (isset($data[$key])) ? htmlspecialchars($data[$key]) : $default;
}

// Função helper para formatar valores monetários brasileiros
function format_money_br($value) {
    if (!is_numeric($value)) {
        return '0,00';
    }
    return number_format((float)$value, 2, ',', '.');
}

// Função helper para valores monetários seguros e formatados
function safe_money_value($data, $key, $default = 0) {
    $value = isset($data[$key]) ? $data[$key] : $default;
    return format_money_br($value);
}

?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        <i class="bi bi-exclamation-triangle text-warning me-2"></i>
        Quadro 10 - Débitos Específicos (compensáveis ou não após o recolhimento)
    </h5>
    <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalAjudaQuadro10">
        <i class="bi bi-question-circle me-1"></i>Ajuda
    </button>
</div>

<?php if (isset($error_message)): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <?php echo $error_message; ?>
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-12">
        <h6 class="text-warning fw-bold">DÉBITOS ESPECÍFICOS (compensáveis ou não após o recolhimento)</h6>
        <hr class="my-2">
    </div>

    <form id="form-quadro10">
        <input type="hidden" name="action" value="save_quadro10">

        <!-- Débito relativo a operações de importação -->
        <div class="col-md-6">
            <label for="item_010_debito_importacao" class="form-label">
                <strong>010 - Débito relativo a operações de importação</strong>
                <small class="text-muted d-block">Valor incidente sobre operações de importação</small>
            </label>
            <input type="text" class="form-control debito10-field" id="item_010_debito_importacao"
                   name="item_010_debito_importacao"
                   value="<?php echo safe_money_value($dados_quadro10, 'item_010_debito_importacao'); ?>"
                   placeholder="0,00">
        </div>

        <!-- Débito Relativo à Entrada de Mercadorias de Outras Unidades da Federação -->
        <div class="col-md-6">
            <label for="item_020_debito_entrada_uf" class="form-label">
                <strong>020 - Débito Relativo à Entrada de Mercadorias de Outras Unidades da Federação</strong>
                <small class="text-muted d-block">Valor incidente sobre bens e mercadorias de outra UF</small>
            </label>
            <input type="text" class="form-control debito10-field" id="item_020_debito_entrada_uf"
                   name="item_020_debito_entrada_uf"
                   value="<?php echo safe_money_value($dados_quadro10, 'item_020_debito_entrada_uf'); ?>"
                   placeholder="0,00">
        </div>

        <!-- Débito por responsabilidade tributária -->
        <div class="col-md-6">
            <label for="item_030_debito_responsabilidade" class="form-label">
                <strong>030 - Débito por responsabilidade tributária</strong>
                <small class="text-muted d-block">Exceto casos de substituição tributária</small>
            </label>
            <input type="text" class="form-control debito10-field" id="item_030_debito_responsabilidade"
                   name="item_030_debito_responsabilidade"
                   value="<?php echo safe_money_value($dados_quadro10, 'item_030_debito_responsabilidade'); ?>"
                   placeholder="0,00">
        </div>

        <!-- Débito por Ocasião do Fato Gerador Relativo a Saída da Mercadoria ou na Prestação de Serviço -->
        <div class="col-md-6">
            <label for="item_040_debito_fato_gerador" class="form-label">
                <strong>040 - Débito por Ocasião do Fato Gerador Relativo a Saída da Mercadoria ou na Prestação de Serviço</strong>
                <small class="text-muted d-block">Valor incidente na saída/prestação com recolhimento por ocasião do fato gerador</small>
            </label>
            <input type="text" class="form-control debito10-field" id="item_040_debito_fato_gerador"
                   name="item_040_debito_fato_gerador"
                   value="<?php echo safe_money_value($dados_quadro10, 'item_040_debito_fato_gerador'); ?>"
                   placeholder="0,00">
        </div>

        <!-- Outros débitos eventuais -->
        <div class="col-md-6">
            <label for="item_050_outros_debitos" class="form-label">
                <strong>050 - Outros débitos eventuais</strong>
                <small class="text-muted d-block">Quaisquer outros débitos com recolhimento em separado</small>
            </label>
            <input type="text" class="form-control debito10-field" id="item_050_outros_debitos"
                   name="item_050_outros_debitos"
                   value="<?php echo safe_money_value($dados_quadro10, 'item_050_outros_debitos'); ?>"
                   placeholder="0,00">
        </div>

        <!-- Total de débitos -->
        <div class="col-md-6">
            <label for="item_990_total_debitos" class="form-label fw-bold">
                <strong>990 - TOTAL DE DÉBITOS</strong>
                <small class="text-muted ms-1">(calculado automaticamente)</small>
            </label>
            <input type="text" class="form-control fw-bold bg-light" id="item_990_total_debitos"
                   name="item_990_total_debitos"
                   value="<?php echo safe_money_value($dados_quadro10, 'item_990_total_debitos'); ?>"
                   placeholder="0,00" readonly>
        </div>

        <!-- Observações -->
        <div class="col-12">
            <label for="observacoes" class="form-label">
                <strong>Observações</strong>
            </label>
            <textarea class="form-control" id="observacoes" name="observacoes" rows="3"
                      placeholder="Observações adicionais (opcional)"><?php echo safe_value($dados_quadro10, 'observacoes'); ?></textarea>
        </div>
    </form>
</div>

<div class="d-flex justify-content-end mt-4">
    <button type="button" class="btn btn-info me-2" onclick="calcularTotalQuadro10()">
        <i class="bi bi-calculator me-2"></i>Calcular Total
    </button>
    <button type="button" class="btn btn-primary" onclick="salvarQuadro10()">
        <i class="bi bi-save me-2"></i>Salvar Quadro 10
    </button>
</div>

<!-- Modal de Ajuda do Quadro 10 -->
<div class="modal fade" id="modalAjudaQuadro10" tabindex="-1" aria-labelledby="modalAjudaQuadro10Label" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="modalAjudaQuadro10Label">
                    <i class="bi bi-question-circle me-2"></i>
                    Ajuda - Quadro 10: Débitos Específicos
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">

                <!-- Visão Geral -->
                <div class="mb-4">
                    <h6 class="text-warning mb-3">
                        <i class="bi bi-info-circle me-2"></i>Visão Geral
                    </h6>
                    <p>O Quadro 10 registra os <strong>débitos específicos</strong> do ICMS que podem ser compensáveis ou não após o recolhimento. Estes são débitos especiais que não se enquadram nos padrões normais dos outros quadros.</p>
                    <div class="alert alert-warning">
                        <strong>⚠️ Características especiais:</strong>
                        <ul class="mb-0">
                            <li>Débitos que podem ser compensados após comprovação de recolhimento</li>
                            <li>Inclui operações especiais (importação, responsabilidade tributária, etc.)</li>
                            <li>Valores atualizados com multas e juros quando aplicável</li>
                            <li>Classe de vencimento 19992 no Quadro 12</li>
                        </ul>
                    </div>
                </div>

                <!-- Detalhes dos Campos -->
                <div class="mb-4">
                    <h6 class="text-primary mb-3">
                        <i class="bi bi-file-earmark-text me-2"></i>Detalhes dos Campos
                    </h6>
                    <div class="accordion" id="accordionCamposQuadro10">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse010Quadro10">
                                    <strong>010 - Débito relativo a operações de importação</strong>
                                </button>
                            </h2>
                            <div id="collapse010Quadro10" class="accordion-collapse collapse show" data-bs-parent="#accordionCamposQuadro10">
                                <div class="accordion-body">
                                    <p><strong>Quando usar:</strong> Para operações de importação onde não há dispositivo legal desobrigando o recolhimento no desembaraço aduaneiro.</p>
                                    <p><strong>Compreende:</strong></p>
                                    <ul class="small">
                                        <li>ICMS incidente sobre operações de importação do período</li>
                                        <li>Imposto recolhido dentro do período</li>
                                        <li>Imposto a recolher em período seguinte</li>
                                    </ul>
                                    <p><strong>Importante:</strong> Não incluir valores já lançados no item 040 do Quadro 04 (compensados).</p>
                                    <div class="alert alert-info small">
                                        <strong>💡 Observação:</strong> O imposto deve ser informado na referência em que ocorreu o recolhimento, mesmo que o fato gerador tenha sido em período anterior.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse020Quadro10">
                                    <strong>020 - Débito Relativo à Entrada de Mercadorias de Outras Unidades da Federação</strong>
                                </button>
                            </h2>
                            <div id="collapse020Quadro10" class="accordion-collapse collapse" data-bs-parent="#accordionCamposQuadro10">
                                <div class="accordion-body">
                                    <p><strong>Quando usar:</strong> Para bens e mercadorias oriundas de outra UF, referente a fatos geradores ocorridos no período.</p>
                                    <p><strong>Compreende:</strong></p>
                                    <ul class="small">
                                        <li>ICMS incidente sobre entradas interestaduais</li>
                                        <li>Imposto recolhido dentro do período</li>
                                        <li>Imposto a recolher em período seguinte</li>
                                    </ul>
                                    <p><strong>Exclusão importante:</strong> Não incluir débitos de diferencial de alíquota já lançados nos itens 020 e 030 do Quadro 04.</p>
                                    <div class="alert alert-warning small">
                                        <strong>⚠️ Atenção:</strong> Aplicável apenas quando não há dispositivo legal de compensação automática.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse030Quadro10">
                                    <strong>030 - Débito por responsabilidade tributária</strong>
                                </button>
                            </h2>
                            <div id="collapse030Quadro10" class="accordion-collapse collapse" data-bs-parent="#accordionCamposQuadro10">
                                <div class="accordion-body">
                                    <p><strong>Quando usar:</strong> Para operações ou prestações de serviço com previsão legal de responsabilidade tributária.</p>
                                    <p><strong>Exclusão:</strong> Não inclui casos de substituição tributária.</p>
                                    <p><strong>Compreende:</strong></p>
                                    <ul class="small">
                                        <li>ICMS devido por responsabilidade tributária</li>
                                        <li>Imposto recolhido dentro do período</li>
                                        <li>Imposto a recolher em período seguinte</li>
                                    </ul>
                                    <p><strong>Exemplos:</strong> Transportadores, armazéns gerais, etc.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse040Quadro10">
                                    <strong>040 - Débito por Ocasião do Fato Gerador</strong>
                                </button>
                            </h2>
                            <div id="collapse040Quadro10" class="accordion-collapse collapse" data-bs-parent="#accordionCamposQuadro10">
                                <div class="accordion-body">
                                    <p><strong>Quando usar:</strong> Para saídas de mercadorias ou prestações de serviço com recolhimento por ocasião do fato gerador.</p>
                                    <p><strong>Compreende:</strong></p>
                                    <ul class="small">
                                        <li>ICMS incidente na saída/prestação</li>
                                        <li>Imposto recolhido dentro do período</li>
                                        <li>Imposto a recolher em período seguinte</li>
                                    </ul>
                                    <p><strong>Inclui especificamente:</strong></p>
                                    <ul class="small">
                                        <li>ICMS próprio recolhido por operação (AEH)</li>
                                        <li>Saídas de produção própria por estabelecimentos comerciais</li>
                                        <li>Operações com Termo de Enquadramento ou Ato Declaratório</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse050Quadro10">
                                    <strong>050 - Outros débitos eventuais</strong>
                                </button>
                            </h2>
                            <div id="collapse050Quadro10" class="accordion-collapse collapse" data-bs-parent="#accordionCamposQuadro10">
                                <div class="accordion-body">
                                    <p><strong>Quando usar:</strong> Para quaisquer outros débitos com determinação expressa de recolhimento em separado.</p>
                                    <p><strong>Inclui especificamente:</strong></p>
                                    <ul class="small">
                                        <li>ICMS decorrente de Nota Fiscal Complementar</li>
                                        <li>Diferenças de preço ou quantidade</li>
                                        <li>Correção de valores do imposto destacado</li>
                                    </ul>
                                    <p><strong>Compreende:</strong></p>
                                    <ul class="small">
                                        <li>Imposto recolhido dentro do período</li>
                                        <li>Imposto a recolher em períodos seguintes</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-warning mt-2">
                        <h6 class="mb-1">990 - TOTAL DE DÉBITOS</h6>
                        <p class="mb-0">Somatório automático de todos os itens (010 a 050).</p>
                    </div>
                </div>

                <!-- Regras Importantes -->
                <div class="alert alert-warning">
                    <h6 class="alert-heading mb-2">
                        <i class="bi bi-exclamation-triangle me-2"></i>Regras Importantes
                    </h6>
                    <ul class="mb-0 small">
                        <li><strong>Classe de vencimento:</strong> Débitos com classe 19992 no Quadro 12</li>
                        <li><strong>Atualização:</strong> Valores devem ser informados atualizados com multas e juros</li>
                        <li><strong>Compensação:</strong> Alguns débitos podem ser compensados após comprovação de recolhimento</li>
                        <li><strong>Período:</strong> Incluir débitos do período atual e ajustes de períodos anteriores</li>
                        <li><strong>Exclusões:</strong> Não incluir valores já lançados em outros quadros específicos</li>
                        <li><strong>Recolhimento:</strong> Imposto deve ser informado na referência em que ocorreu o recolhimento</li>
                        <li><strong>Observações:</strong> Campo opcional para registrar informações relevantes</li>
                    </ul>
                </div>

                <!-- Diferenças dos Outros Quadros -->
                <div class="mb-4">
                    <h6 class="text-info mb-3">
                        <i class="bi bi-arrow-left-right me-2"></i>Diferenças dos Outros Quadros
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Característica</th>
                                    <th>Quadros 04/05</th>
                                    <th>Quadro 10</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Tipo de débito</strong></td>
                                    <td>Débitos normais do período</td>
                                    <td>Débitos especiais/específicos</td>
                                </tr>
                                <tr>
                                    <td><strong>Compensação</strong></td>
                                    <td>Compensação automática</td>
                                    <td>Compensável após recolhimento</td>
                                </tr>
                                <tr>
                                    <td><strong>Classe de vencimento</strong></td>
                                    <td>Normal</td>
                                    <td>19992 (especial)</td>
                                </tr>
                                <tr>
                                    <td><strong>Atualização</strong></td>
                                    <td>Sem multas/juros</td>
                                    <td>Com multas/juros quando aplicável</td>
                                </tr>
                                <tr>
                                    <td><strong>Período</strong></td>
                                    <td>Período atual</td>
                                    <td>Atual + ajustes anteriores</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Quando Usar Cada Campo -->
                <div class="mb-4">
                    <h6 class="text-success mb-3">
                        <i class="bi bi-checklist me-2"></i>Quando Usar Cada Campo
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-primary mb-3">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Campo 010 - Importação</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="small mb-0">
                                        <li>✓ Importações sem desobrigação</li>
                                        <li>✓ Fatos geradores no período</li>
                                        <li>✓ Excluindo valores do Quadro 04</li>
                                        <li>✗ Valores já compensados</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-secondary mb-3">
                                <div class="card-header bg-secondary text-white">
                                    <h6 class="mb-0">Campo 020 - Entradas UF</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="small mb-0">
                                        <li>✓ Entradas interestaduais</li>
                                        <li>✓ Fatos geradores no período</li>
                                        <li>✗ Diferenças de alíquota (Quadro 04)</li>
                                        <li>✗ Valores já compensados</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-warning mb-3">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0">Campo 030 - Responsabilidade</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="small mb-0">
                                        <li>✓ Transportadores</li>
                                        <li>✓ Armazéns gerais</li>
                                        <li>✗ Substituição tributária</li>
                                        <li>✓ Outros responsáveis legais</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-info mb-3">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">Campo 040 - Fato Gerador</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="small mb-0">
                                        <li>✓ AEH (Atacado de Energia)</li>
                                        <li>✓ Termo de Enquadramento</li>
                                        <li>✓ Ato Declaratório</li>
                                        <li>✓ Recolhimento por operação</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">Campo 050 - Outros Débitos</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="small mb-0">
                                        <li>✓ Notas Fiscais Complementares</li>
                                        <li>✓ Diferenças de preço/quantidade</li>
                                        <li>✓ Correção de imposto destacado</li>
                                        <li>✓ Outros débitos com recolhimento separado</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Exemplo Prático -->
                <div class="mb-4">
                    <h6 class="text-info mb-3">
                        <i class="bi bi-calculator me-2"></i>Exemplo Prático
                    </h6>
                    <div class="card">
                        <div class="card-body">
                            <h6>Empresa importadora com responsabilidade tributária:</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Item</th>
                                            <th>Descrição</th>
                                            <th>Valor</th>
                                            <th>Justificativa</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>010</td>
                                            <td>Débito importação</td>
                                            <td class="text-end">R$ 15.000,00</td>
                                            <td>ICMS de importação sem desobrigação</td>
                                        </tr>
                                        <tr>
                                            <td>020</td>
                                            <td>Entrada outra UF</td>
                                            <td class="text-end">R$ 8.500,00</td>
                                            <td>Entrada interestadual sem compensação</td>
                                        </tr>
                                        <tr>
                                            <td>030</td>
                                            <td>Responsabilidade tributária</td>
                                            <td class="text-end">R$ 3.200,00</td>
                                            <td>Transportador responsável</td>
                                        </tr>
                                        <tr>
                                            <td>040</td>
                                            <td>Fato gerador</td>
                                            <td class="text-end">R$ 12.000,00</td>
                                            <td>AEH recolhido por operação</td>
                                        </tr>
                                        <tr>
                                            <td>050</td>
                                            <td>Outros débitos</td>
                                            <td class="text-end">R$ 2.500,00</td>
                                            <td>Nota Fiscal Complementar</td>
                                        </tr>
                                        <tr class="table-warning">
                                            <td>990</td>
                                            <td><strong>TOTAL</strong></td>
                                            <td class="text-end"><strong>R$ 41.200,00</strong></td>
                                            <td>Somatório automático</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <small class="text-muted">
                                <strong>Nota:</strong> Estes valores representam obrigações especiais que podem ser compensadas após comprovação de recolhimento, conforme legislação específica.
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
    // Definir dime_id globalmente para este quadro
    window.QUADRO10_CONFIG = {
        dimeId: <?php echo json_encode($dime_id); ?>
    };

    // Inicializar tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Máscaras para valores monetários
    setupMasksQuadro10();

    // Calcular totais iniciais
    calcularTotalQuadro10();
})();

function setupMasksQuadro10() {
    // Máscaras para valores monetários
    const camposMonetarios = [
        'item_010_debito_importacao', 'item_020_debito_entrada_uf',
        'item_030_debito_responsabilidade', 'item_040_debito_fato_gerador',
        'item_050_outros_debitos'
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
                if (value.length > 14) {
                    value = value.slice(0, -14) + '.' + value.slice(-14);
                }
                e.target.value = value;

                // Recalcular total quando houver mudanças
                setTimeout(calcularTotalQuadro10, 100);
            });

            // NÃO formatar valor inicial - deixar como está do PHP
            // O valor já vem formatado corretamente do servidor
        }
    });
}

function calcularTotalQuadro10() {
    const campos = [
        'item_010_debito_importacao', 'item_020_debito_entrada_uf',
        'item_030_debito_responsabilidade', 'item_040_debito_fato_gerador',
        'item_050_outros_debitos'
    ];

    let total = 0;
    campos.forEach(campoId => {
        const campo = document.getElementById(campoId);
        if (campo && campo.value) {
            let valorStr = campo.value.replace(/\./g, '').replace(',', '.');
            const valor = parseFloat(valorStr) || 0;
            total += valor;
        }
    });

    const campoTotal = document.getElementById('item_990_total_debitos');
    if (campoTotal) {
        campoTotal.value = total.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
}

// Definir função global para ser acessível do editar_dime.php
window.salvarQuadro10 = function() {
    // Coletar dados do formulário
    const formData = new FormData();

    // Dados do formulário
    const form = document.getElementById('form-quadro10');
    if (form) {
        new FormData(form).forEach((value, key) => {
            formData.append(key, value);
        });
    }

    // Mostrar loading
    const btn = document.querySelector('button[onclick="salvarQuadro10()"]');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Salvando...';
    }

    // URL para salvar
    const postUrl = 'quadros/ajax_save_0405.php';

    // Adicionar dime_id
    const dimeId = window.QUADRO10_CONFIG?.dimeId || window.DIME_CONFIG?.dimeId || new URLSearchParams(window.location.search).get('dime_id') || new URLSearchParams(window.parent.location.search).get('id');
    formData.append('dime_id', dimeId);

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
            alert('✅ ' + data.message);
        } else {
            showMessage('❌ ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('💥 Erro na requisição:', error);
        showMessage('💥 Erro de conexão: ' + error.message, 'danger');
    })
    .finally(() => {
        // Restaurar botão
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-save me-2"></i>Salvar Quadro 10';
        }
    });
};

function showMessage(message, type = 'info') {
    // Criar alerta Bootstrap
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show position-fixed"
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    // Adicionar ao body
    document.body.insertAdjacentHTML('beforeend', alertHtml);

    // Auto-remover após 5 segundos
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (alert.textContent.includes(message.substring(0, 50))) {
                alert.remove();
            }
        });
    }, 5000);
}
</script>
