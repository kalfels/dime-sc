<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../src/app_classes.php';

use App\Models\Usuario;
use App\Models\DimeDeclaracao;
use App\Core\DBConnection;

header('Content-Type: application/json');

// Authentication check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$user_role = $_SESSION['user_role'];
$contabilista_id_session = $_SESSION['contabilista_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    $dime_id = $_POST['dime_id'] ?? 0;
    if (!$dime_id) {
        throw new Exception('ID da DIME não informado');
    }

    // Load and validate DIME
    $dime = DimeDeclaracao::find($dime_id);
    if (!$dime) {
        throw new Exception('Declaração DIME não encontrada');
    }

    // Check permissions
    if ($user_role !== 'admin' && $dime->contabilista_id != $contabilista_id_session) {
        throw new Exception('Acesso negado a esta declaração');
    }

    $pdo = DBConnection::getInstance()->getConnection();
    
    // Determine which quadro to save based on form fields
    if (isset($_POST['razao_social'])) {
        // Quadro 00 - Initial Information
        $empresa = $dime->getEmpresa();
        
        $stmt = $pdo->prepare("
            INSERT INTO dime_quadro_00 (
                dime_declaracao_id, cnpj_empresa, inscricao_estadual, razao_social,
                nome_fantasia, endereco_completo, municipio_codigo, cep, telefone,
                email, cnae_principal, regime_tributario,
                tipo_declaracao, regime_apuracao, porte_empresa, apuracao_consolidada,
                apuracao_centralizada, transferencia_creditos, tem_creditos_presumidos,
                tem_creditos_incentivos_fiscais, movimento, substituto_tributario,
                tem_escrita_contabil, quantidade_trabalhadores
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                inscricao_estadual = VALUES(inscricao_estadual),
                razao_social = VALUES(razao_social),
                nome_fantasia = VALUES(nome_fantasia),
                endereco_completo = VALUES(endereco_completo),
                municipio_codigo = VALUES(municipio_codigo),
                cep = VALUES(cep),
                telefone = VALUES(telefone),
                email = VALUES(email),
                cnae_principal = VALUES(cnae_principal),
                regime_tributario = VALUES(regime_tributario),
                tipo_declaracao = VALUES(tipo_declaracao),
                regime_apuracao = VALUES(regime_apuracao),
                porte_empresa = VALUES(porte_empresa),
                apuracao_consolidada = VALUES(apuracao_consolidada),
                apuracao_centralizada = VALUES(apuracao_centralizada),
                transferencia_creditos = VALUES(transferencia_creditos),
                tem_creditos_presumidos = VALUES(tem_creditos_presumidos),
                tem_creditos_incentivos_fiscais = VALUES(tem_creditos_incentivos_fiscais),
                movimento = VALUES(movimento),
                substituto_tributario = VALUES(substituto_tributario),
                tem_escrita_contabil = VALUES(tem_escrita_contabil),
                quantidade_trabalhadores = VALUES(quantidade_trabalhadores),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $endereco_completo = $empresa->endereco . ', ' . $empresa->numero;
        if ($empresa->complemento) {
            $endereco_completo .= ', ' . $empresa->complemento;
        }
        $endereco_completo .= ', ' . $empresa->bairro . ', ' . $empresa->cidade . ' - ' . $empresa->estado;
        
        $stmt->execute([
            $dime_id,
            $empresa->cnpj,
            $_POST['inscricao_estadual'],
            $_POST['razao_social'],
            $_POST['nome_fantasia'] ?? null,
            $_POST['endereco_completo'] ?? $endereco_completo,
            $_POST['municipio_codigo'] ?? '4205407',
            $_POST['cep'] ?? $empresa->cep,
            $_POST['telefone'] ?? $empresa->telefone,
            $_POST['email'] ?? $empresa->email,
            $_POST['cnae_principal'],
            $_POST['regime_tributario'] ?? 'real',
            $_POST['tipo_declaracao'],
            $_POST['regime_apuracao'],
            $_POST['porte_empresa'],
            $_POST['apuracao_consolidada'] ?? 0,
            $_POST['apuracao_centralizada'] ?? 0,
            $_POST['transferencia_creditos'] ?? 0,
            $_POST['tem_creditos_presumidos'] ?? 0,
            $_POST['tem_creditos_incentivos_fiscais'] ?? 0,
            $_POST['movimento'] ?? 1,
            $_POST['substituto_tributario'] ?? 0,
            $_POST['tem_escrita_contabil'] ?? 1,
            $_POST['quantidade_trabalhadores'] ?? null
        ]);
        
    } elseif (isset($_POST['imposto_devido_operacoes_saidas'])) {
        // Quadro 04 - Resumo da Apuração dos Débitos
        $stmt = $pdo->prepare("
            INSERT INTO dime_quadro_04 (
                dime_declaracao_id, imposto_devido_operacoes_saidas, estorno_creditos,
                debito_saidas_isentas_nao_tributadas, debito_saidas_outras, debito_importacao,
                debito_servicos_comunicacao, debito_energia_eletrica, debito_antecipacao_icms,
                debito_substituicao_tributaria, debito_diferencial_aliquota, multas_juros,
                outros_debitos, total_debitos, observacoes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                imposto_devido_operacoes_saidas = VALUES(imposto_devido_operacoes_saidas),
                estorno_creditos = VALUES(estorno_creditos),
                debito_saidas_isentas_nao_tributadas = VALUES(debito_saidas_isentas_nao_tributadas),
                debito_saidas_outras = VALUES(debito_saidas_outras),
                debito_importacao = VALUES(debito_importacao),
                debito_servicos_comunicacao = VALUES(debito_servicos_comunicacao),
                debito_energia_eletrica = VALUES(debito_energia_eletrica),
                debito_antecipacao_icms = VALUES(debito_antecipacao_icms),
                debito_substituicao_tributaria = VALUES(debito_substituicao_tributaria),
                debito_diferencial_aliquota = VALUES(debito_diferencial_aliquota),
                multas_juros = VALUES(multas_juros),
                outros_debitos = VALUES(outros_debitos),
                total_debitos = VALUES(total_debitos),
                observacoes = VALUES(observacoes),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            $dime_id,
            $_POST['imposto_devido_operacoes_saidas'] ?? 0,
            $_POST['estorno_creditos'] ?? 0,
            $_POST['debito_saidas_isentas_nao_tributadas'] ?? 0,
            $_POST['debito_saidas_outras'] ?? 0,
            $_POST['debito_importacao'] ?? 0,
            $_POST['debito_servicos_comunicacao'] ?? 0,
            $_POST['debito_energia_eletrica'] ?? 0,
            $_POST['debito_antecipacao_icms'] ?? 0,
            $_POST['debito_substituicao_tributaria'] ?? 0,
            $_POST['debito_diferencial_aliquota'] ?? 0,
            $_POST['multas_juros'] ?? 0,
            $_POST['outros_debitos'] ?? 0,
            $_POST['total_debitos'] ?? 0,
            $_POST['observacoes'] ?? null
        ]);
        
    } elseif (isset($_POST['item_010_saldo_credor_anterior'])) {
        // Quadro 05 - Resumo da Apuração dos Créditos - Nova estrutura completa
        $stmt = $pdo->prepare("
            INSERT INTO dime_quadro_05 (
                dime_declaracao_id, item_010_saldo_credor_anterior, item_020_credito_entradas,
                item_030_credito_ativo_permanente, item_040_credito_diferencial_aliquota,
                item_045_credito_diferenca_aliquota_operacao, item_050_credito_icms_retido_st,
                item_060_estorno_nao_aplica, item_070_nao_aplica, item_080_creditos_presumidos,
                item_090_creditos_incentivos, item_100_pagamentos_antecipados_credito,
                item_110_nao_aplica, item_120_nao_aplica, item_130_nao_aplica,
                item_140_creditos_regime_especial, item_150_outros_creditos,
                item_160_nao_aplica, item_990_subtotal_creditos, observacoes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                item_010_saldo_credor_anterior = VALUES(item_010_saldo_credor_anterior),
                item_020_credito_entradas = VALUES(item_020_credito_entradas),
                item_030_credito_ativo_permanente = VALUES(item_030_credito_ativo_permanente),
                item_040_credito_diferencial_aliquota = VALUES(item_040_credito_diferencial_aliquota),
                item_045_credito_diferenca_aliquota_operacao = VALUES(item_045_credito_diferenca_aliquota_operacao),
                item_050_credito_icms_retido_st = VALUES(item_050_credito_icms_retido_st),
                item_060_estorno_nao_aplica = VALUES(item_060_estorno_nao_aplica),
                item_070_nao_aplica = VALUES(item_070_nao_aplica),
                item_080_creditos_presumidos = VALUES(item_080_creditos_presumidos),
                item_090_creditos_incentivos = VALUES(item_090_creditos_incentivos),
                item_100_pagamentos_antecipados_credito = VALUES(item_100_pagamentos_antecipados_credito),
                item_110_nao_aplica = VALUES(item_110_nao_aplica),
                item_120_nao_aplica = VALUES(item_120_nao_aplica),
                item_130_nao_aplica = VALUES(item_130_nao_aplica),
                item_140_creditos_regime_especial = VALUES(item_140_creditos_regime_especial),
                item_150_outros_creditos = VALUES(item_150_outros_creditos),
                item_160_nao_aplica = VALUES(item_160_nao_aplica),
                item_990_subtotal_creditos = VALUES(item_990_subtotal_creditos),
                observacoes = VALUES(observacoes),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            $dime_id,
            $_POST['item_010_saldo_credor_anterior'] ?? 0,
            $_POST['item_020_credito_entradas'] ?? 0,
            $_POST['item_030_credito_ativo_permanente'] ?? 0,
            $_POST['item_040_credito_diferencial_aliquota'] ?? 0,
            $_POST['item_045_credito_diferenca_aliquota_operacao'] ?? 0,
            $_POST['item_050_credito_icms_retido_st'] ?? 0,
            $_POST['item_060_estorno_nao_aplica'] ?? 0,
            $_POST['item_070_nao_aplica'] ?? 0,
            $_POST['item_080_creditos_presumidos'] ?? 0,
            $_POST['item_090_creditos_incentivos'] ?? 0,
            $_POST['item_100_pagamentos_antecipados_credito'] ?? 0,
            $_POST['item_110_nao_aplica'] ?? 0,
            $_POST['item_120_nao_aplica'] ?? 0,
            $_POST['item_130_nao_aplica'] ?? 0,
            $_POST['item_140_creditos_regime_especial'] ?? 0,
            $_POST['item_150_outros_creditos'] ?? 0,
            $_POST['item_160_nao_aplica'] ?? 0,
            $_POST['item_990_subtotal_creditos'] ?? 0,
            $_POST['observacoes'] ?? null
        ]);
        
    } elseif (isset($_POST['cfop_ids'])) {
        // Quadro 01 - Entradas (CFOP data) - Nova estrutura
        // First, delete existing entries for this DIME
        $stmt = $pdo->prepare("DELETE FROM dime_quadro_01 WHERE dime_declaracao_id = ?");
        $stmt->execute([$dime_id]);

        // Insert new CFOP entries
        if (is_array($_POST['cfop_ids'])) {
            $stmt = $pdo->prepare("
                INSERT INTO dime_quadro_01 (
                    dime_declaracao_id, cfop_id, valor_contabil, base_calculo,
                    valor_imposto, valor_isento_nao_tributado, valor_outras,
                    base_calculo_imposto_retido, imposto_retido,
                    debito_imposto_diferencial_aliquota, observacoes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            for ($i = 0; $i < count($_POST['cfop_ids']); $i++) {
                if (!empty($_POST['cfop_ids'][$i])) {
                    $stmt->execute([
                        $dime_id,
                        $_POST['cfop_ids'][$i],
                        $_POST['valor_contabils'][$i] ?? 0,
                        $_POST['base_calculos'][$i] ?? 0,
                        $_POST['valor_impostos'][$i] ?? 0,
                        $_POST['valor_isentos'][$i] ?? 0,
                        $_POST['valor_outrass'][$i] ?? 0,
                        $_POST['base_calculo_imposto_retidos'][$i] ?? 0,
                        $_POST['imposto_retidos'][$i] ?? 0,
                        $_POST['debito_imposto_diferencial_aliquotas'][$i] ?? 0,
                        $_POST['observacoess'][$i] ?? null
                    ]);
                }
            }
        }
        
    } elseif (isset($_POST['cfops'])) {
        // Quadro 02 - Saídas (CFOP data) - Nova estrutura
        // First, delete existing entries for this DIME
        $stmt = $pdo->prepare("DELETE FROM dime_quadro_02 WHERE dime_declaracao_id = ?");
        $stmt->execute([$dime_id]);

        // Insert new CFOP entries
        if (is_array($_POST['cfops'])) {
            $stmt = $pdo->prepare("
                INSERT INTO dime_quadro_02 (
                    dime_declaracao_id, cfop, valor_contabil, base_calculo,
                    valor_imposto, valor_isento_nao_tributado, valor_outras,
                    base_calculo_imposto_retido, imposto_retido, observacoes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            for ($i = 0; $i < count($_POST['cfops']); $i++) {
                if (!empty($_POST['cfops'][$i])) {
                    $stmt->execute([
                        $dime_id,
                        $_POST['cfops'][$i],
                        $_POST['valor_contabils'][$i] ?? 0,
                        $_POST['base_calculos'][$i] ?? 0,
                        $_POST['valor_impostos'][$i] ?? 0,
                        $_POST['valor_isentos'][$i] ?? 0,
                        $_POST['valor_outrass'][$i] ?? 0,
                        $_POST['base_calculo_imposto_retidos'][$i] ?? 0,
                        $_POST['imposto_retidos'][$i] ?? 0,
                        $_POST['observacoess'][$i] ?? null
                    ]);
                }
            }
        }
        
    } elseif (isset($_POST['total_debitos_icms'])) {
        // Quadro 09 - Apuração do ICMS
        $stmt = $pdo->prepare("
            INSERT INTO dime_quadro_09 (
                dime_declaracao_id, total_debitos_icms, total_creditos_icms,
                saldo_devedor_icms, saldo_credor_icms, valor_pagar, valor_compensar,
                saldo_credor_anterior, saldo_credor_transportar, observacoes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                total_debitos_icms = VALUES(total_debitos_icms),
                total_creditos_icms = VALUES(total_creditos_icms),
                saldo_devedor_icms = VALUES(saldo_devedor_icms),
                saldo_credor_icms = VALUES(saldo_credor_icms),
                valor_pagar = VALUES(valor_pagar),
                valor_compensar = VALUES(valor_compensar),
                saldo_credor_anterior = VALUES(saldo_credor_anterior),
                saldo_credor_transportar = VALUES(saldo_credor_transportar),
                observacoes = VALUES(observacoes),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            $dime_id,
            $_POST['total_debitos_icms'] ?? 0,
            $_POST['total_creditos_icms'] ?? 0,
            $_POST['saldo_devedor_icms'] ?? 0,
            $_POST['saldo_credor_icms'] ?? 0,
            $_POST['valor_pagar'] ?? 0,
            $_POST['valor_compensar'] ?? 0,
            $_POST['saldo_credor_anterior'] ?? 0,
            $_POST['saldo_credor_transportar'] ?? 0,
    } elseif (isset($_POST["item_010_debito_importacao"]) || isset($_POST["item_020_debito_entrada_uf"]) ||
              isset($_POST["item_030_debito_responsabilidade"]) || isset($_POST["item_040_debito_fato_gerador"]) ||
              isset($_POST["item_050_outros_debitos"])) {
        // Quadro 10 - Débitos Específicos
        $stmt = $pdo->prepare("
            INSERT INTO dime_quadro_10 (
                dime_declaracao_id, item_010_debito_importacao, item_020_debito_entrada_uf,
                item_030_debito_responsabilidade, item_040_debito_fato_gerador,
                item_050_outros_debitos, item_990_total_debitos
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                item_010_debito_importacao = VALUES(item_010_debito_importacao),
                item_020_debito_entrada_uf = VALUES(item_020_debito_entrada_uf),
                item_030_debito_responsabilidade = VALUES(item_030_debito_responsabilidade),
                item_040_debito_fato_gerador = VALUES(item_040_debito_fato_gerador),
                item_050_outros_debitos = VALUES(item_050_outros_debitos),
                item_990_total_debitos = VALUES(item_990_total_debitos)
        ");

        // Calculate total
        $total_debitos = (
            ($_POST['item_010_debito_importacao'] ?? 0) +
            ($_POST['item_020_debito_entrada_uf'] ?? 0) +
            ($_POST['item_030_debito_responsabilidade'] ?? 0) +
            ($_POST['item_040_debito_fato_gerador'] ?? 0) +
            ($_POST['item_050_outros_debitos'] ?? 0)
        );

        $stmt->execute([
            $dime_id,
            $_POST['item_010_debito_importacao'] ?? 0,
            $_POST['item_020_debito_entrada_uf'] ?? 0,
            $_POST['item_030_debito_responsabilidade'] ?? 0,
            $_POST['item_040_debito_fato_gerador'] ?? 0,
            $_POST['item_050_outros_debitos'] ?? 0,
            $total_debitos
        ]);
    } else {
        $stmt->execute([
            $dime_id,
            $_POST['item_010_debito_importacao'] ?? 0,
            $_POST['item_020_debito_entrada_uf'] ?? 0,
            $_POST['item_030_debito_responsabilidade'] ?? 0,
            $_POST['item_040_debito_fato_gerador'] ?? 0,
            $_POST['item_050_outros_debitos'] ?? 0,
            $total_debitos
        ]);
    } elseif (isset($_POST["item_060_descricao_substituicao"]) || isset($_POST["item_070_debito_st_saidas"]) ||
              isset($_POST["item_090_credito_st_entradas"]) || isset($_POST["item_155_ajuste_debito_st"]) ||
              isset($_POST["item_170_icms_st_debitar"])) {
        // Quadro 11 - Informações sobre Substituição Tributária

        // Calculate totals
        $total_debitos_st = (
            ($_POST['item_070_debito_st_saidas'] ?? 0) +
            ($_POST['item_073_debito_st_antecipado'] ?? 0) +
            ($_POST['item_075_debito_st_retido'] ?? 0) +
            ($_POST['item_080_debito_st_outros'] ?? 0)
        );

        $total_creditos_st = (
            ($_POST['item_090_credito_st_entradas'] ?? 0) +
            ($_POST['item_105_credito_st_restituido'] ?? 0) +
            ($_POST['item_115_credito_st_anterior'] ?? 0) +
            ($_POST['item_116_credito_st_compensado'] ?? 0) +
            ($_POST['item_125_credito_st_transferido'] ?? 0) +
            ($_POST['item_130_credito_st_outros'] ?? 0)
        );

        $stmt = $pdo->prepare("
            INSERT INTO dime_quadro_11 (
                dime_declaracao_id,
                item_060_descricao_substituicao, item_065_informacoes_complementares,
                item_070_debito_st_saidas, item_073_debito_st_antecipado,
                item_075_debito_st_retido, item_080_debito_st_outros,
                item_090_credito_st_entradas, item_105_credito_st_restituido,
                item_115_credito_st_anterior, item_116_credito_st_compensado,
                item_125_credito_st_transferido, item_130_credito_st_outros,
                item_155_ajuste_debito_st, item_160_ajuste_credito_st,
                item_170_icms_st_debitar, item_180_icms_st_pagar,
                item_190_saldo_credor_st, item_200_saldo_devedor_st,
                item_998_total_debitos_st, item_999_total_creditos_st
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                item_060_descricao_substituicao = VALUES(item_060_descricao_substituicao),
                item_065_informacoes_complementares = VALUES(item_065_informacoes_complementares),
                item_070_debito_st_saidas = VALUES(item_070_debito_st_saidas),
                item_073_debito_st_antecipado = VALUES(item_073_debito_st_antecipado),
                item_075_debito_st_retido = VALUES(item_075_debito_st_retido),
                item_080_debito_st_outros = VALUES(item_080_debito_st_outros),
                item_090_credito_st_entradas = VALUES(item_090_credito_st_entradas),
                item_105_credito_st_restituido = VALUES(item_105_credito_st_restituido),
                item_115_credito_st_anterior = VALUES(item_115_credito_st_anterior),
                item_116_credito_st_compensado = VALUES(item_116_credito_st_compensado),
                item_125_credito_st_transferido = VALUES(item_125_credito_st_transferido),
                item_130_credito_st_outros = VALUES(item_130_credito_st_outros),
                item_155_ajuste_debito_st = VALUES(item_155_ajuste_debito_st),
                item_160_ajuste_credito_st = VALUES(item_160_ajuste_credito_st),
                item_170_icms_st_debitar = VALUES(item_170_icms_st_debitar),
                item_180_icms_st_pagar = VALUES(item_180_icms_st_pagar),
                item_190_saldo_credor_st = VALUES(item_190_saldo_credor_st),
                item_200_saldo_devedor_st = VALUES(item_200_saldo_devedor_st),
                item_998_total_debitos_st = VALUES(item_998_total_debitos_st),
                item_999_total_creditos_st = VALUES(item_999_total_creditos_st)
        ");

        $stmt->execute([
            $dime_id,
            $_POST['item_060_descricao_substituicao'] ?? null,
            $_POST['item_065_informacoes_complementares'] ?? null,
            $_POST['item_070_debito_st_saidas'] ?? 0,
            $_POST['item_073_debito_st_antecipado'] ?? 0,
            $_POST['item_075_debito_st_retido'] ?? 0,
            $_POST['item_080_debito_st_outros'] ?? 0,
            $_POST['item_090_credito_st_entradas'] ?? 0,
            $_POST['item_105_credito_st_restituido'] ?? 0,
            $_POST['item_115_credito_st_anterior'] ?? 0,
            $_POST['item_116_credito_st_compensado'] ?? 0,
            $_POST['item_125_credito_st_transferido'] ?? 0,
            $_POST['item_130_credito_st_outros'] ?? 0,
            $_POST['item_155_ajuste_debito_st'] ?? 0,
            $_POST['item_160_ajuste_credito_st'] ?? 0,
            $_POST['item_170_icms_st_debitar'] ?? 0,
            $_POST['item_180_icms_st_pagar'] ?? 0,
            $_POST['item_190_saldo_credor_st'] ?? 0,
            $_POST['item_200_saldo_devedor_st'] ?? 0,
            $total_debitos_st,
            $total_creditos_st
        ]);
        throw new Exception('Dados do quadro não identificados');
    }

    // Update DIME status and timestamp
    $dime->updated_at = date('Y-m-d H:i:s');
    $dime->save();

    echo json_encode(['success' => true, 'message' => 'Quadro salvo com sucesso']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
