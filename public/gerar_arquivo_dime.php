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
use App\Core\DBConnection;

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['user_role'];
$contabilista_id_session = $_SESSION['contabilista_id'];

// Get DIME ID (base64 decoded for obfuscation)
$encoded_id = $_GET['id'] ?? '';
$dime_id = base64_decode($encoded_id);
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
    header('Location: dashboard.php?message=error&text=' . urlencode('Acesso negado.'));
    exit;
}

// Load related data
$empresa = $dime->getEmpresa();
$contabilista = $dime->getContabilista();

/**
 * DIME-SC File Generator Class
 * Generates DIME-SC files according to Anexo II specifications
 */
class DimeFileGenerator {
    private $dime;
    private $empresa;
    private $contabilista;
    private $pdo;
    
    public function __construct($dime, $empresa, $contabilista) {
        $this->dime = $dime;
        $this->empresa = $empresa;
        $this->contabilista = $contabilista;
        $this->pdo = DBConnection::getInstance()->getConnection();
    }
    
    /**
     * Generate complete DIME-SC file content according to manual specifications
     */
    public function generateFile() {
        $content = '';
        $recordCount = 0;
        
        // Record type 20 - Dados do Contabilista
        $content .= $this->generateRecord20();
        $recordCount++;
        
        // Record type 21 - Início da Declaração
        $content .= $this->generateRecord21();
        $recordCount++;
        
        // Generate quadro records based on enabled quadros
        $quadroContent = $this->generateQuadros();
        $content .= $quadroContent;
        $recordCount += substr_count($quadroContent, "\r\n");
        
        // Record type 98 - Fim da Declaração
        $content .= $this->generateRecord98($recordCount);
        
        return $content;
    }
    
    /**
     * Get enabled quadros for the company from dime_quadros_empresas table
     */
    private function getEnabledQuadros() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT quadro_numero 
                FROM dime_quadro_empresas 
                WHERE empresa_id = ? AND ativo = 1
            ");
            $stmt->execute([$this->empresa->id]);
            $quadros = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // If no quadros configured, return all available quadros for compatibility
            if (empty($quadros)) {
                return ['00', '0103', '0203', '0405', '09', '10', '11', '12', '14', '41', '46', '49', '50'];
            }
            
            return $quadros;
        } catch (Exception $e) {
            error_log("Error getting enabled quadros: " . $e->getMessage());
            // Return all quadros as fallback
            return ['00', '0103', '0203', '0405', '09', '10', '11', '12', '14', '41', '46', '49', '50'];
        }
    }
    
    /**
     * Generate quadro records based on enabled quadros for the company
     */
    private function generateQuadros() {
        $content = '';
        $enabledQuadros = $this->getEnabledQuadros();
        
        // Generate quadro 00 (identificação) if enabled
        if (in_array('00', $enabledQuadros)) {
            $content .= $this->generateQuadro00();
        }
        
        // Generate quadro 01/03 (entradas) if enabled
        if (in_array('0103', $enabledQuadros)) {
            $content .= $this->generateQuadro01();
        }
        
        // Generate quadro 02/03 (saídas) if enabled
        if (in_array('0203', $enabledQuadros)) {
            $content .= $this->generateQuadro02();
        }
        
        // Generate quadro 04/05 (apuração débitos/créditos) if enabled
        if (in_array('0405', $enabledQuadros)) {
            $content .= $this->generateQuadro04();
            $content .= $this->generateQuadro05();
        }
        
        // Generate quadro 09 (cálculo do imposto) if enabled
        if (in_array('09', $enabledQuadros)) {
            $content .= $this->generateQuadro09();
        }
        
        // Generate quadro 10 (débitos específicos) if enabled
        if (in_array('10', $enabledQuadros)) {
            $content .= $this->generateQuadro10();
        }
        
        // Generate quadro 11 (substituição tributária) if enabled
        if (in_array('11', $enabledQuadros)) {
            $content .= $this->generateQuadro11();
        }
        
        // Generate quadro 12 (pagamentos) if enabled
        if (in_array('12', $enabledQuadros)) {
            $content .= $this->generateQuadro12();
        }
        
        // Generate quadro 14 (crédito presumido) if enabled
        if (in_array('14', $enabledQuadros)) {
            $content .= $this->generateQuadro14();
        }
        
        // Generate quadro 41 (créditos acumulados) if enabled
        if (in_array('41', $enabledQuadros)) {
            $content .= $this->generateQuadro41();
        }
        
        // Generate quadro 46 (créditos por regimes especiais) if enabled
        if (in_array('46', $enabledQuadros)) {
            $content .= $this->generateQuadro46();
        }
        
        // Generate quadro 49 (entradas por UF) if enabled
        if (in_array('49', $enabledQuadros)) {
            $content .= $this->generateQuadro49();
        }
        
        // Generate quadro 50 (saídas por UF) if enabled
        if (in_array('50', $enabledQuadros)) {
            $content .= $this->generateQuadro50();
        }
        
        return $content;
    }

    /**
     * Generate record type 20 - Dados do Contabilista
     */
    private function generateRecord20() {
        $record = '20'; // Tipo de Registro
        $record .= str_repeat(' ', 2); // Quadro (brancos)
        $record .= str_pad(preg_replace('/\D/', '', $this->contabilista->cpf), 11, '0', STR_PAD_LEFT); // CPF
        $record .= str_pad(substr($this->contabilista->nome, 0, 50), 50, ' '); // Nome do Contabilista
        $record .= date('YmdHis'); // Data e Hora (AAAAMMDDHHMISS)
        
        return $record . "\r\n";
    }
    
    /**
     * Generate record type 21 - Início da Declaração
     */
    private function generateRecord21() {
        $record = '21'; // Tipo de Registro
        $record .= str_repeat(' ', 2); // Quadro (brancos)
        $record .= str_pad(preg_replace('/\D/', '', $this->empresa->cnpj), 14, '0', STR_PAD_LEFT); // CNPJ
        $record .= str_pad($this->empresa->inscricao_estadual, 20, ' '); // Inscrição Estadual
        $record .= str_pad(substr($this->empresa->razao_social, 0, 60), 60, ' '); // Razão Social
        $record .= $this->dime->periodo_referencia; // Período de Referência (AAAAMM)
        $record .= $this->getPorteCode($this->empresa->porte); // Porte da Empresa
        
        return $record . "\r\n";
    }

    /**
     * Generate record type 98 - Fim da Declaração
     */
    private function generateRecord98($recordCount) {
        $record = '98'; // Tipo de Registro
        $record .= str_repeat(' ', 2); // Quadro (brancos)
        $record .= str_pad($recordCount, 5, '0', STR_PAD_LEFT); // Quantidade de registros
        
        return $record . "\r\n";
    }

    // Placeholder methods for all quadros - implement based on your database structure
    /**
     * Generate Quadro 00 record
     */
    private function generateQuadro00() {
        $stmt = $this->pdo->prepare("SELECT * FROM dime_quadro_00 WHERE dime_declaracao_id = ?");
        $stmt->execute([$this->dime->id]);
        $quadro00 = $stmt->fetch();
        
        if (!$quadro00) {
            return '';
        }
        
        $record = '300'; // Tipo + Quadro
        $record .= str_pad(preg_replace('/\D/', '', ($quadro00['cnpj_empresa'] ?? '')), 14, '0', STR_PAD_LEFT);
        $record .= str_pad(($quadro00['inscricao_estadual'] ?? ''), 20, ' ');
        $record .= str_pad(substr(($quadro00['razao_social'] ?? ''), 0, 60), 60, ' ');
        $record .= str_pad(substr(($quadro00['nome_fantasia'] ?? ''), 0, 60), 60, ' ');
        $record .= str_pad(substr(($quadro00['endereco_completo'] ?? ''), 0, 100), 100, ' ');
        $record .= str_pad(($quadro00['municipio_codigo'] ?? ''), 7, '0', STR_PAD_LEFT);
        $record .= str_pad(preg_replace('/\D/', '', ($quadro00['cep'] ?? '')), 8, '0', STR_PAD_LEFT);
        $record .= str_pad(($quadro00['cnae_principal'] ?? ''), 10, ' ');
        
        return $record . "
";
    }
    /**
     * Generate Quadro 01 records (Entradas)
     */
    private function generateQuadro01() {
        $stmt = $this->pdo->prepare("
            SELECT q01.*, c.codigo as cfop_codigo 
            FROM dime_quadro_01 q01 
            JOIN t_cfops c ON q01.cfop_id = c.id 
            WHERE q01.dime_declaracao_id = ?
        ");
        $stmt->execute([$this->dime->id]);
        $quadros01 = $stmt->fetchAll();
        
        $content = '';
        foreach ($quadros01 as $q01) {
            $record = '301'; // Tipo + Quadro
            $record .= str_pad(($q01['cfop_codigo'] ?? ''), 4, '0', STR_PAD_LEFT);
            $record .= str_pad(number_format(($q01['valor_contabil'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT);
            $record .= str_pad(number_format(($q01['base_calculo'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT);
            $record .= str_pad(number_format(($q01['valor_imposto'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT);
            $record .= str_pad(number_format(($q01['valor_isento_nao_tributado'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT);
            $record .= str_pad(number_format(($q01['valor_outras'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT);
            
            $content .= $record . "
";
        }
        
        return $content;
    }
    /**
     * Generate Quadro 02 records (Saídas)
     */
    private function generateQuadro02() {
        $stmt = $this->pdo->prepare("
            SELECT q02.*, c.codigo as cfop_codigo 
            FROM dime_quadro_02 q02 
            JOIN t_cfops c ON q02.cfop_id = c.id 
            WHERE q02.dime_declaracao_id = ?
        ");
        $stmt->execute([$this->dime->id]);
        $quadros02 = $stmt->fetchAll();
        
        $content = '';
        foreach ($quadros02 as $q02) {
            $record = '302'; // Tipo + Quadro
            $record .= str_pad(($q02['cfop_codigo'] ?? ''), 4, '0', STR_PAD_LEFT);
            $record .= str_pad(number_format(($q02['valor_contabil'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT);
            $record .= str_pad(number_format(($q02['base_calculo'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT);
            $record .= str_pad(number_format(($q02['valor_imposto'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT);
            $record .= str_pad(number_format(($q02['valor_isento_nao_tributado'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT);
            $record .= str_pad(number_format(($q02['valor_outras'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT);
            
            $content .= $record . "
";
        }
        
        return $content;
    }
    /**
     * Generate Quadro 04 record (Débitos)
     */
    private function generateQuadro04() {
        $stmt = $this->pdo->prepare("SELECT * FROM dime_quadro_04 WHERE dime_declaracao_id = ?");
        $stmt->execute([$this->dime->id]);
        $quadro04 = $stmt->fetch();
        
        if (!$quadro04) {
            return '';
        }
        
        $record = '304'; // Tipo + Quadro
        $record .= str_pad(number_format(($quadro04['item_010_debito_saidas'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT);
        $record .= str_pad(number_format(($quadro04['item_050_estorno_credito'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT);
        $record .= str_pad(number_format(($quadro04['item_070_outros_debitos'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT);
        $record .= str_pad(number_format(($quadro04['item_990_subtotal_debitos'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT);
        
        return $record . "
";
    }
    /**
     * Generate Quadro 05 records (Créditos)
     */
    private function generateQuadro05() {
        $stmt = $this->pdo->prepare("SELECT * FROM dime_quadro_05 WHERE dime_declaracao_id = ?");
        $stmt->execute([$this->dime->id]);
        $quadro05 = $stmt->fetch();

        if (!$quadro05) {
            return '';
        }

        $content = '';
        $items = [
            '010' => ['field' => 'item_010_saldo_credor_anterior', 'desc' => 'Saldo credor do mês anterior'],
            '020' => ['field' => 'item_020_credito_entradas', 'desc' => 'Crédito pelas entradas'],
            '030' => ['field' => 'item_030_credito_ativo_permanente', 'desc' => 'Crédito de ativo permanente'],
            '990' => ['field' => 'item_990_subtotal_creditos', 'desc' => 'Subtotal de créditos']
        ];

        foreach ($items as $itemCode => $itemInfo) {
            $fieldName = $itemInfo['field'];
            $value = isset($quadro05[$fieldName]) ? (float)$quadro05[$fieldName] : 0;

            if ($value > 0 || $itemCode === '990') {
                $record = '305'; // Tipo + Quadro
                $record .= str_pad($itemCode, 3, '0', STR_PAD_LEFT);
                $record .= str_pad(number_format($value * 100, 0, '', ''), 17, '0', STR_PAD_LEFT);
                $record .= str_pad(substr($itemInfo['desc'], 0, 60), 60, ' ');

                $content .= $record . "
";
            }
        }

        return $content;
    }
    /**
     * Generate Quadro 09 records (Apuração do ICMS)
     */
    private function generateQuadro09() {
        $stmt = $this->pdo->prepare("SELECT * FROM dime_quadro_09 WHERE dime_declaracao_id = ?");
        $stmt->execute([$this->dime->id]);
        $quadro09 = $stmt->fetch();

        if (!$quadro09) {
            return '';
        }

        $content = '';
        $items = [
            '990' => ['field' => 'total_debitos_icms', 'desc' => 'Total de débitos do ICMS'],
            '991' => ['field' => 'total_creditos_icms', 'desc' => 'Total de créditos do ICMS'],
            '992' => ['field' => 'saldo_devedor_icms', 'desc' => 'Saldo devedor do ICMS'],
            '993' => ['field' => 'saldo_credor_icms', 'desc' => 'Saldo credor do ICMS'],
            '994' => ['field' => 'valor_pagar', 'desc' => 'Valor a pagar']
        ];

        foreach ($items as $itemCode => $itemInfo) {
            $fieldName = $itemInfo['field'];
            $value = isset($quadro09[$fieldName]) ? (float)$quadro09[$fieldName] : 0;

            if ($value > 0 || in_array($itemCode, ['990', '991'])) {
                $record = '309'; // Tipo + Quadro
                $record .= str_pad($itemCode, 3, '0', STR_PAD_LEFT);
                $record .= str_pad(number_format($value * 100, 0, '', ''), 17, '0', STR_PAD_LEFT);
                $record .= str_pad(substr($itemInfo['desc'], 0, 60), 60, ' ');

                $content .= $record . "
";
            }
        }

        return $content;
    }
    private function generateQuadro10() { return ''; }
    private function generateQuadro11() { return ''; }
    private function generateQuadro12() { return ''; }
    private function generateQuadro14() { return ''; }
    private function generateQuadro41() { return ''; }
    /**
     * Generate Quadro 46 records (Créditos por Regimes Especiais)
     */
    private function generateQuadro46() {
        $stmt = $this->pdo->prepare("SELECT * FROM dime_quadro_46 WHERE dime_declaracao_id = ? AND sequencia != '990' ORDER BY CAST(sequencia AS UNSIGNED)");
        $stmt->execute([$this->dime->id]);
        $creditos = $stmt->fetchAll();

        $content = '';
        $totalGeral = 0;

        foreach ($creditos as $credito) {
            // Registro 346 - Quadro 46
            $record = '346'; // Tipo de Registro
            $record .= str_pad(($credito['sequencia'] ?? ''), 3, '0', STR_PAD_LEFT); // Sequência
            $record .= str_pad(substr(($credito['identificacao_regime'] ?? ''), 0, 40), 40, ' '); // Identificação do regime
            $record .= str_pad(number_format(($credito['valor'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT); // Valor em centavos
            $record .= str_pad(($credito['origem'] ?? '00'), 2, '0', STR_PAD_LEFT); // Origem

            $content .= $record . "
";

            // Acumular total para o item 990
            if ($credito['valor'] > 0) {
                $totalGeral += $credito['valor'];
            }
        }

        // Adicionar item 990 (totalizador) calculado dinamicamente
        if ($totalGeral > 0) {
            $record = '346'; // Tipo de Registro
            $record .= '990'; // Sequência 990 (totalizador)
            $record .= str_pad('Total Geral', 40, ' '); // Identificação padrão para totalizador
            $record .= str_pad(number_format($totalGeral * 100, 0, '', ''), 17, '0', STR_PAD_LEFT); // Total em centavos
            $record .= '00'; // Origem 00 para totalizador

            $content .= $record . "
";
        }

        return $content;
    }
    /**
     * Generate Quadro 49 records (Entradas por UF)
     */
    private function generateQuadro49() {
        $stmt = $this->pdo->prepare("SELECT * FROM dime_quadro_49 WHERE dime_declaracao_id = ? ORDER BY uf");
        $stmt->execute([$this->dime->id]);
        $entradas = $stmt->fetchAll();

        $content = '';

        foreach ($entradas as $entrada) {
            // Registro 349 - Quadro 49
            $record = '349'; // Tipo de Registro
            $record .= str_pad(($entrada['uf'] ?? ''), 2, ' ', STR_PAD_RIGHT); // UF
            $record .= str_pad(number_format(($entrada['valor_contabil'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT); // Valor Contábil em centavos
            $record .= str_pad(number_format(($entrada['base_calculo'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT); // Base de Cálculo em centavos
            $record .= str_pad(number_format(($entrada['outras'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT); // Outras em centavos
            $record .= str_pad(number_format(($entrada['icms_retido_st_petroleo_energia'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT); // ST Petróleo/Energia em centavos
            $record .= str_pad(number_format(($entrada['icms_retido_st_outros_produtos'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT); // ST Outros Produtos em centavos

            $content .= $record . "
";
        }

        return $content;
    }
    /**
     * Generate Quadro 50 records (Saídas por UF)
     */
    private function generateQuadro50() {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM dime_quadro_50 WHERE dime_declaracao_id = ? ORDER BY uf");
            $stmt->execute([$this->dime->id]);
            $saidas = $stmt->fetchAll();
        } catch (Exception $e) {
            // Tabela não existe, retorna vazio
            return '';
        }

        $content = '';

        foreach ($saidas as $saida) {
            $record = '350'; // Tipo de Registro
            $record .= str_pad(($saida['uf'] ?? ''), 2, ' ', STR_PAD_RIGHT); // UF
            $record .= str_pad(number_format(($saida['valor_contabil'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT); // Valor Contábil
            $record .= str_pad(number_format(($saida['base_calculo'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT); // Base de Cálculo
            $record .= str_pad(number_format(($saida['outras'] ?? 0) * 100, 0, '', ''), 17, '0', STR_PAD_LEFT); // Outras

            $content .= $record . "
";
        }

        return $content;
    }
    /**
     * Get company size code
     */
    private function getPorteCode($porte) {
        switch ($porte) {
            case 'micro': return '1';
            case 'pequena': return '2';
            case 'media': return '3';
            case 'grande': return '4';
            default: return '1';
        }
    }
}

// Handle file generation - Auto download when ID is provided
if (true) {
    try {
        $generator = new DimeFileGenerator($dime, $empresa, $contabilista);
        $fileContent = $generator->generateFile();
        
        // Generate filename: DIME-MES (por extenso)
        $meses = [
            '01' => 'JANEIRO',
            '02' => 'FEVEREIRO', 
            '03' => 'MARÇO',
            '04' => 'ABRIL',
            '05' => 'MAIO',
            '06' => 'JUNHO',
            '07' => 'JULHO',
            '08' => 'AGOSTO',
            '09' => 'SETEMBRO',
            '10' => 'OUTUBRO',
            '11' => 'NOVEMBRO',
            '12' => 'DEZEMBRO'
        ];
        
        // Extrair mês do período de referência
        $periodo = $dime->periodo_referencia ?? '';
        $mes = '';
        
        // Tentar diferentes formatos de período
        if (strpos($periodo, '-') !== false) {
            // Formato YYYY-MM ou YYYY-M
            $periodoPartes = explode('-', $periodo);
            $mes = isset($periodoPartes[1]) ? str_pad($periodoPartes[1], 2, '0', STR_PAD_LEFT) : '';
        } elseif (strpos($periodo, '/') !== false) {
            // Formato YYYY/MM ou YYYY/M
            $periodoPartes = explode('/', $periodo);
            $mes = isset($periodoPartes[1]) ? str_pad($periodoPartes[1], 2, '0', STR_PAD_LEFT) : '';
        } elseif (strlen($periodo) == 6 && is_numeric($periodo)) {
            // Formato YYYYMM
            $mes = substr($periodo, 4, 2);
        } elseif (strlen($periodo) == 5 && is_numeric($periodo)) {
            // Formato YYYYM
            $mes = '0' . substr($periodo, 4, 1);
        } elseif (preg_match('/(\d{4})[\-\/]?(\d{1,2})/', $periodo, $matches)) {
            // Regex para capturar qualquer formato YYYY seguido de 1-2 dígitos
            $mes = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        }
        
        // Se ainda não conseguiu extrair, tentar usar a data atual
        if (empty($mes) || !isset($meses[$mes])) {
            $mes = date('m'); // Mês atual como fallback
        }
        
        $mesExtenso = $meses[$mes] ?? 'INDEFINIDO';
        
        // Debug temporário - sempre logar para identificar o problema
        error_log("DIME DEBUG - Período original: '$periodo' (length: " . strlen($periodo) . ")");
        error_log("DIME DEBUG - Mês extraído: '$mes'");
        error_log("DIME DEBUG - Mês por extenso: '$mesExtenso'");
        
        $filename = 'DIME-' . $mesExtenso . '.txt';
        
        // Update DIME status
        $dime->status_declaracao = 'validada';
        $dime->hash_arquivo = hash('sha256', $fileContent);
        $dime->save();
        
        // Send file
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($fileContent));
        
        echo $fileContent;
        exit;
        
    } catch (Exception $e) {
        echo "Erro ao gerar arquivo DIME: " . $e->getMessage();
        echo "<br><br>Debug info:<br>";
        echo "DIME ID: " . $dime_id . "<br>";
        echo "Empresa: " . ($empresa ? $empresa->razao_social : "Não encontrada") . "<br>";
        echo "Contabilista: " . ($contabilista ? $contabilista->nome : "Não encontrado") . "<br>";
        exit;
    }
}
?>
