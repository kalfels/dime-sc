<?php
/**
 * AJAX - Carregar Quadro
 * Endpoint para carregar conteúdo dos quadros via AJAX
 */

require_once '../includes/config.php';
session_start();

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "Não autorizado";
    exit;
}

// Verificar parâmetros
$quadro = $_GET['quadro'] ?? '';
$dime_id = (int)($_GET['dime_id'] ?? 0);

if (!$dime_id || !in_array($quadro, ['04', '05'])) {
    http_response_code(400);
    echo "Parâmetros inválidos";
    exit;
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verificar se a declaração existe
    $stmt = $pdo->prepare("SELECT id FROM dime_declaracoes WHERE id = ?");
    $stmt->execute([$dime_id]);
    if (!$stmt->fetch()) {
        throw new Exception("Declaração não encontrada");
    }

    // Carregar conteúdo conforme o quadro
    switch ($quadro) {
        case '04':
            echo renderQuadro04($pdo, $dime_id);
            break;
        case '05':
            echo renderQuadro05($pdo, $dime_id);
            break;
    }

} catch (Exception $e) {
    error_log("Erro ao carregar quadro $quadro: " . $e->getMessage());
    echo '<div class="alert alert-danger">Erro ao carregar quadro</div>';
}

function renderQuadro04($pdo, $dime_id) {
    // Carregar dados do Quadro 04
    $stmt = $pdo->prepare("SELECT * FROM dime_quadro_04 WHERE dime_declaracao_id = ?");
    $stmt->execute([$dime_id]);
    $dados = $stmt->fetch();

    ob_start();
    ?>
    <h5 class="mb-3">
        <i class="bi bi-calculator text-primary me-2"></i>
        Quadro 04 - Débitos
    </h5>

    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Quadro 04 - Débitos:</strong>
        Informe todos os valores de débitos conforme especificado no Manual DIME-SEF 153/12.
    </div>

    <form id="form-quadro04" novalidate>
        <input type="hidden" name="dime_id" value="<?php echo $dime_id; ?>">

        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Débitos do Período</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">010: Débito por Importação</label>
                        <input type="number" step="0.01" class="form-control"
                               name="item_010_debito_importacao"
                               value="<?php echo htmlspecialchars($dados['item_010_debito_importacao'] ?? ''); ?>"
                               placeholder="0.00">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">020: Débito por Entrada UF</label>
                        <input type="number" step="0.01" class="form-control"
                               name="item_020_debito_entrada_uf"
                               value="<?php echo htmlspecialchars($dados['item_020_debito_entrada_uf'] ?? ''); ?>"
                               placeholder="0.00">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">030: Débito por Responsabilidade</label>
                        <input type="number" step="0.01" class="form-control"
                               name="item_030_debito_responsabilidade"
                               value="<?php echo htmlspecialchars($dados['item_030_debito_responsabilidade'] ?? ''); ?>"
                               placeholder="0.00">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">040: Débito por Fato Gerador</label>
                        <input type="number" step="0.01" class="form-control"
                               name="item_040_debito_fato_gerador"
                               value="<?php echo htmlspecialchars($dados['item_040_debito_fato_gerador'] ?? ''); ?>"
                               placeholder="0.00">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">050: Outros Débitos</label>
                        <input type="number" step="0.01" class="form-control"
                               name="item_050_outros_debitos"
                               value="<?php echo htmlspecialchars($dados['item_050_outros_debitos'] ?? ''); ?>"
                               placeholder="0.00">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label"><strong>990: Total Débitos</strong></label>
                        <input type="number" step="0.01" class="form-control bg-light fw-bold"
                               name="item_990_total_debitos" id="item_990_total_debitos"
                               value="<?php echo htmlspecialchars($dados['item_990_total_debitos'] ?? ''); ?>"
                               readonly>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="button" class="btn btn-outline-primary" onclick="calcularTotalDebitos()">
                        <i class="bi bi-calculator me-1"></i>Calcular Total
                    </button>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end mt-4">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-check-circle me-2"></i>
                Salvar Quadro 04
            </button>
        </div>
    </form>

    <script>
    function calcularTotalDebitos() {
        const campos = [
            'item_010_debito_importacao',
            'item_020_debito_entrada_uf',
            'item_030_debito_responsabilidade',
            'item_040_debito_fato_gerador',
            'item_050_outros_debitos'
        ];

        let total = 0;
        campos.forEach(campo => {
            const elemento = document.querySelector(`[name="${campo}"]`);
            if (elemento) {
                total += parseFloat(elemento.value) || 0;
            }
        });

        document.getElementById('item_990_total_debitos').value = total.toFixed(2);
    }

    // Configurar evento para o formulário
    document.getElementById('form-quadro04').addEventListener('submit', function(e) {
        e.preventDefault();
        salvarQuadro('04', new FormData(this));
    });
    </script>
    <?php
    return ob_get_clean();
}

function renderQuadro05($pdo, $dime_id) {
    // Carregar dados do Quadro 05
    $stmt = $pdo->prepare("SELECT * FROM dime_quadro_05 WHERE dime_declaracao_id = ?");
    $stmt->execute([$dime_id]);
    $dados = $stmt->fetch();

    ob_start();
    ?>
    <h5 class="mb-3">
        <i class="bi bi-graph-up text-primary me-2"></i>
        Quadro 05 - Créditos
    </h5>

    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Quadro 05 - Créditos:</strong>
        Informe todos os valores de créditos conforme especificado no Manual DIME-SEF 153/12.
    </div>

    <form id="form-quadro05" novalidate>
        <input type="hidden" name="dime_id" value="<?php echo $dime_id; ?>">

        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Créditos do Período</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">010: Crédito por Importação</label>
                        <input type="number" step="0.01" class="form-control"
                               name="item_010_credito_importacao"
                               value="<?php echo htmlspecialchars($dados['item_010_credito_importacao'] ?? ''); ?>"
                               placeholder="0.00">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">020: Crédito por Entrada UF</label>
                        <input type="number" step="0.01" class="form-control"
                               name="item_020_credito_entrada_uf"
                               value="<?php echo htmlspecialchars($dados['item_020_credito_entrada_uf'] ?? ''); ?>"
                               placeholder="0.00">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">030: Crédito por Responsabilidade</label>
                        <input type="number" step="0.01" class="form-control"
                               name="item_030_credito_responsabilidade"
                               value="<?php echo htmlspecialchars($dados['item_030_credito_responsabilidade'] ?? ''); ?>"
                               placeholder="0.00">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">040: Crédito por Fato Gerador</label>
                        <input type="number" step="0.01" class="form-control"
                               name="item_040_credito_fato_gerador"
                               value="<?php echo htmlspecialchars($dados['item_040_credito_fato_gerador'] ?? ''); ?>"
                               placeholder="0.00">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">050: Outros Créditos</label>
                        <input type="number" step="0.01" class="form-control"
                               name="item_050_outros_creditos"
                               value="<?php echo htmlspecialchars($dados['item_050_outros_creditos'] ?? ''); ?>"
                               placeholder="0.00">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label"><strong>990: Total Créditos</strong></label>
                        <input type="number" step="0.01" class="form-control bg-light fw-bold"
                               name="item_990_total_creditos" id="item_990_total_creditos"
                               value="<?php echo htmlspecialchars($dados['item_990_total_creditos'] ?? ''); ?>"
                               readonly>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="button" class="btn btn-outline-primary" onclick="calcularTotalCreditos()">
                        <i class="bi bi-calculator me-1"></i>Calcular Total
                    </button>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end mt-4">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-check-circle me-2"></i>
                Salvar Quadro 05
            </button>
        </div>
    </form>

    <script>
    function calcularTotalCreditos() {
        const campos = [
            'item_010_credito_importacao',
            'item_020_credito_entrada_uf',
            'item_030_credito_responsabilidade',
            'item_040_credito_fato_gerador',
            'item_050_outros_creditos'
        ];

        let total = 0;
        campos.forEach(campo => {
            const elemento = document.querySelector(`[name="${campo}"]`);
            if (elemento) {
                total += parseFloat(elemento.value) || 0;
            }
        });

        document.getElementById('item_990_total_creditos').value = total.toFixed(2);
    }

    // Configurar evento para o formulário
    document.getElementById('form-quadro05').addEventListener('submit', function(e) {
        e.preventDefault();
        salvarQuadro('05', new FormData(this));
    });
    </script>
    <?php
    return ob_get_clean();
}
?>
