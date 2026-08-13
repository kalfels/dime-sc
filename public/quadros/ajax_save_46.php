<?php
header('Content-Type: application/json');

try {
    // Debug: Log do início da execução
    error_log("[DEBUG ajax_save_46.php] Início da execução");

    // Verificar sessão
    if (!isset($_SESSION['user_id'])) {
        error_log("[DEBUG ajax_save_46.php] Sessão não encontrada - user_id: " . ($_SESSION['user_id'] ?? 'null'));
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
        exit;
    }

    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        error_log("[DEBUG ajax_save_46.php] Método não permitido: " . $_SERVER['REQUEST_METHOD']);
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
        exit;
    }

    // Debug: Log dos headers recebidos
    error_log("[DEBUG ajax_save_46.php] Headers: " . json_encode(getallheaders()));

    // Obter dados JSON
    $input = file_get_contents('php://input');
    error_log("[DEBUG ajax_save_46.php] Raw input: " . $input);

    $data = json_decode($input, true);
    error_log("[DEBUG ajax_save_46.php] Decoded data: " . json_encode($data));

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("[DEBUG ajax_save_46.php] JSON decode error: " . json_last_error_msg());
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'JSON inválido: ' . json_last_error_msg()]);
        exit;
    }

    // Validação básica
    if (!isset($data['dime_declaracao_id'])) {
        error_log("[DEBUG ajax_save_46.php] dime_declaracao_id não encontrado");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID da declaração não informado']);
        exit;
    }

    error_log("[DEBUG ajax_save_46.php] Processando DIME ID: " . $data['dime_declaracao_id']);

    // Conectar ao banco
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/functions.php';

    error_log("[DEBUG ajax_save_46.php] Includes carregados");

    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 30
        ]);
        error_log("[DEBUG ajax_save_46.php] Conexão PDO estabelecida");
    } catch (PDOException $e) {
        error_log("[DEBUG ajax_save_46.php] Erro PDO: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro de conexão com banco']);
        exit;
    }

    $dimeId = (int)$data['dime_declaracao_id'];
    error_log("[DEBUG ajax_save_46.php] DIME ID convertido: " . $dimeId);

    // Verificar se declaração existe
    $stmt = $pdo->prepare("SELECT id FROM dime_declaracoes WHERE id = ?");
    $stmt->execute([$dimeId]);
    if (!$stmt->fetch()) {
        error_log("[DEBUG ajax_save_46.php] Declaração não encontrada: " . $dimeId);
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Declaração não encontrada']);
        exit;
    }
    error_log("[DEBUG ajax_save_46.php] Declaração encontrada");

    // Iniciar transação
    $pdo->beginTransaction();
    error_log("[DEBUG ajax_save_46.php] Transação iniciada");

    try {
        // Remover registros existentes
        $stmt = $pdo->prepare("DELETE FROM dime_quadro_46 WHERE dime_declaracao_id = ?");
        $stmt->execute([$dimeId]);
        error_log("[DEBUG ajax_save_46.php] Registros existentes removidos");

        // Inserir novos registros
        if (isset($data['creditos']) && is_array($data['creditos'])) {
            error_log("[DEBUG ajax_save_46.php] Iniciando inserção de créditos. Total: " . count($data['creditos']));

            $stmt = $pdo->prepare("
                INSERT INTO dime_quadro_46 (
                    dime_declaracao_id,
                    sequencia,
                    identificacao_regime,
                    valor,
                    origem,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");

            foreach ($data['creditos'] as $credito) {
                // Validar dados
                $sequencia = trim($credito['sequencia'] ?? '');
                $identificacao = trim($credito['identificacao_regime'] ?? '');
                $valor = (float)($credito['valor'] ?? 0);
                $origem = trim($credito['origem'] ?? '');

                // Debug: Log dos dados recebidos
                error_log("[DEBUG ajax_save_46.php] Processando crédito - Sequencia: '{$sequencia}', Identificacao: '{$identificacao}', Valor: {$valor}, Origem: '{$origem}'");

                // Validações
                if (empty($sequencia)) {
                    throw new Exception('Sequência é obrigatória');
                }

                if ($sequencia !== '990' && empty($identificacao)) {
                    throw new Exception('Identificação do regime é obrigatória para sequência ' . $sequencia);
                }

                if ($valor < 0) {
                    throw new Exception('Valor não pode ser negativo');
                }

                if ($sequencia !== '990' && empty($origem)) {
                    throw new Exception('Origem é obrigatória para sequência ' . $sequencia);
                }

                // Validação adicional: se valor for 0 e sequência não for 990, permitir sem origem
                if ($sequencia !== '990' && $valor == 0 && empty($origem)) {
                    // Pular este crédito vazio
                    continue;
                }

                // Validação adicional: verificar se a sequência é numérica
                if (!is_numeric($sequencia)) {
                    throw new Exception("Sequência deve ser numérica, recebido: '{$sequencia}'");
                }

                // Validação adicional: verificar comprimento da sequência
                if (strlen($sequencia) > 3) {
                    throw new Exception("Sequência muito longa: '{$sequencia}' (máximo 3 caracteres)");
                }

                // Validação adicional: verificar se a origem é válida
                $origens_validas = ['1', '3', '13', '14', '16', '17', '18', '19', '00'];
                if (!in_array($origem, $origens_validas) && $sequencia !== '990') {
                    throw new Exception("Origem inválida: '{$origem}'. Valores válidos: " . implode(', ', $origens_validas));
                }

                // Para item 990, forçar valores específicos
                if ($sequencia === '990') {
                    $identificacao = '0000000000000000000000000000000000000000';
                    $origem = '00';
                }

                // Executar insert
                $stmt->execute([
                    $dimeId,
                    $sequencia,
                    $identificacao,
                    $valor,
                    $origem
                ]);
                error_log("[DEBUG ajax_save_46.php] Crédito inserido com sucesso");
            }
        }

        // REMOVIDO: Lógica de criação do item 990 no banco de dados
        // O item 990 será calculado dinamicamente durante a geração do arquivo

        // Commit da transação
        $pdo->commit();
        error_log("[DEBUG ajax_save_46.php] Transação comitada com sucesso");

        // Executar integrações com outros quadros
        integrarQuadro46($pdo, $dimeId);
        error_log("[DEBUG ajax_save_46.php] Integração executada");

        echo json_encode([
            'success' => true,
            'message' => 'Quadro 46 salvo com sucesso',
            'id' => $dimeId
        ]);
        error_log("[DEBUG ajax_save_46.php] Resposta JSON enviada com sucesso");

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("[DEBUG ajax_save_46.php] Erro ao salvar Quadro 46: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao salvar: ' . $e->getMessage()
        ]);
        error_log("[DEBUG ajax_save_46.php] Resposta de erro enviada");
    }

} catch (Exception $e) {
    error_log("[DEBUG ajax_save_46.php] Erro no ajax_save_46.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
    error_log("[DEBUG ajax_save_46.php] Erro final enviado");
}

function integrarQuadro46($pdo, $dimeId) {
    try {
        // Calcular total dos créditos válidos (sem item 990)
        $stmt = $pdo->prepare("
            SELECT origem, SUM(valor) as total
            FROM dime_quadro_46
            WHERE dime_declaracao_id = ? AND sequencia != '990' AND valor > 0
            GROUP BY origem
        ");
        $stmt->execute([$dimeId]);
        $creditos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Quadro 09 - Item 070 (Origens 1 e 3)
        $total_070 = 0;
        foreach ($creditos as $credito) {
            if (in_array($credito['origem'], ['1', '3'])) {
                $total_070 += $credito['total'];
            }
        }

        if ($total_070 > 0) {
            // Verificar se existe registro no Quadro 09
            $stmt = $pdo->prepare("SELECT id FROM dime_quadro_09 WHERE dime_declaracao_id = ?");
            $stmt->execute([$dimeId]);
            $quadro09 = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($quadro09) {
                // Atualizar item 070 do Quadro 09
                $stmt = $pdo->prepare("UPDATE dime_quadro_09 SET item_070_creditos_transferencia = ? WHERE dime_declaracao_id = ?");
                $stmt->execute([$total_070, $dimeId]);
            }
        }

        // Quadro 09 - Item 075 (Origem 14)
        $total_075 = 0;
        foreach ($creditos as $credito) {
            if ($credito['origem'] === '14') {
                $total_075 += $credito['total'];
            }
        }

        if ($total_075 > 0) {
            $stmt = $pdo->prepare("SELECT id FROM dime_quadro_09 WHERE dime_declaracao_id = ?");
            $stmt->execute([$dimeId]);
            $quadro09 = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($quadro09) {
                $stmt = $pdo->prepare("UPDATE dime_quadro_09 SET item_075_creditos_dcip = ? WHERE dime_declaracao_id = ?");
                $stmt->execute([$total_075, $dimeId]);
            }
        }

        // Quadro 11 - Item 105 (Origem 16)
        $total_105 = 0;
        foreach ($creditos as $credito) {
            if ($credito['origem'] === '16') {
                $total_105 += $credito['total'];
            }
        }

        if ($total_105 > 0) {
            $stmt = $pdo->prepare("SELECT id FROM dime_quadro_11 WHERE dime_declaracao_id = ?");
            $stmt->execute([$dimeId]);
            $quadro11 = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($quadro11) {
                $stmt = $pdo->prepare("UPDATE dime_quadro_11 SET item_105_creditos_dcip = ? WHERE dime_declaracao_id = ?");
                $stmt->execute([$total_105, $dimeId]);
            }
        }

        // Quadro 41 - Itens 217-219 (Origens 17-19)
        $total_217 = 0; // Exportação
        $total_218 = 0; // Isenta
        $total_219 = 0; // Diferida

        foreach ($creditos as $credito) {
            switch ($credito['origem']) {
                case '17':
                    $total_217 += $credito['total'];
                    break;
                case '18':
                    $total_218 += $credito['total'];
                    break;
                case '19':
                    $total_219 += $credito['total'];
                    break;
            }
        }

        if ($total_217 > 0 || $total_218 > 0 || $total_219 > 0) {
            $stmt = $pdo->prepare("SELECT id FROM dime_quadro_41 WHERE dime_declaracao_id = ?");
            $stmt->execute([$dimeId]);
            $quadro41 = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($quadro41) {
                if ($total_217 > 0) {
                    $stmt = $pdo->prepare("UPDATE dime_quadro_41 SET item_217_credito_autorizado_exportacao = ? WHERE dime_declaracao_id = ?");
                    $stmt->execute([$total_217, $dimeId]);
                }

                if ($total_218 > 0) {
                    $stmt = $pdo->prepare("UPDATE dime_quadro_41 SET item_218_credito_autorizado_isentos = ? WHERE dime_declaracao_id = ?");
                    $stmt->execute([$total_218, $dimeId]);
                }

                if ($total_219 > 0) {
                    $stmt = $pdo->prepare("UPDATE dime_quadro_41 SET item_219_credito_autorizado_diferidos = ? WHERE dime_declaracao_id = ?");
                    $stmt->execute([$total_219, $dimeId]);
                }
            }
        }

        // Verificação especial para Origem 13 (deve ser igual ao CFOP 1.603 do Quadro 01)
        $total_13 = 0;
        foreach ($creditos as $credito) {
            if ($credito['origem'] === '13') {
                $total_13 += $credito['total'];
            }
        }

        if ($total_13 > 0) {
            // Buscar valor do CFOP 1.603 no Quadro 01
            $stmt = $pdo->prepare("
                SELECT valor_contabil
                FROM dime_quadro_01
                WHERE dime_declaracao_id = ? AND cfop = '1.603'
            ");
            $stmt->execute([$dimeId]);
            $cfop1603 = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($cfop1603 && abs($cfop1603['valor_contabil'] - $total_13) > 0.01) {
                // Registrar aviso no log
                error_log("Aviso: Origem 13 (R$ " . number_format($total_13, 2) . ") difere do CFOP 1.603 (R$ " . number_format($cfop1603['valor_contabil'], 2) . ") na declaração " . $dimeId);
            }
        }

    } catch (Exception $e) {
        error_log("Erro na integração do Quadro 46: " . $e->getMessage());
        // Não falhar a operação principal por causa de erro na integração
    }
}
?>
