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
    private function generateQuadro00() { return ''; }
    private function generateQuadro01() { return ''; }
    private function generateQuadro02() { return ''; }
    private function generateQuadro04() { return ''; }
    private function generateQuadro05() { return ''; }
    private function generateQuadro09() { return ''; }
    private function generateQuadro10() { return ''; }
    private function generateQuadro11() { return ''; }
    private function generateQuadro12() { return ''; }
    private function generateQuadro14() { return ''; }
    private function generateQuadro41() { return ''; }
    private function generateQuadro46() { return ''; }
    private function generateQuadro49() { return ''; }
    private function generateQuadro50() { return ''; }
    
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
        
        // Generate filename: DIME_CNPJ_PERIODO.txt
        $filename = 'DIME_' . preg_replace('/\D/', '', $empresa->cnpj) . '_' . $dime->periodo_referencia . '.txt';
        
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
