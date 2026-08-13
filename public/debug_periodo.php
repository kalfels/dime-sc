<?php
require_once '../src/app_classes.php';
use App\Models\DimeDeclaracao;

// Pegar o ID da DIME da URL
$encoded_id = $_GET['id'] ?? '';
$dime_id = base64_decode($encoded_id);

if (!$dime_id || !is_numeric($dime_id)) {
    die("ID inválido");
}

// Carregar a declaração DIME
$dime = DimeDeclaracao::find($dime_id);
if (!$dime) {
    die("DIME não encontrada");
}

echo "<h2>Debug do Período de Referência</h2>";
echo "<p><strong>ID da DIME:</strong> " . $dime->id . "</p>";
echo "<p><strong>Período de Referência:</strong> '" . $dime->periodo_referencia . "'</p>";
echo "<p><strong>Tipo da variável:</strong> " . gettype($dime->periodo_referencia) . "</p>";
echo "<p><strong>Comprimento:</strong> " . strlen($dime->periodo_referencia) . "</p>";

// Testar extração de mês
$periodo = $dime->periodo_referencia ?? '';
echo "<p><strong>Período (com ?? ''):</strong> '" . $periodo . "'</p>";
echo "<p><strong>É numérico:</strong> " . (is_numeric($periodo) ? 'SIM' : 'NÃO') . "</p>";
echo "<p><strong>Contém hífen:</strong> " . (strpos($periodo, '-') !== false ? 'SIM' : 'NÃO') . "</p>";
echo "<p><strong>Contém barra:</strong> " . (strpos($periodo, '/') !== false ? 'SIM' : 'NÃO') . "</p>";

$meses = [
    '01' => 'JANEIRO', '02' => 'FEVEREIRO', '03' => 'MARÇO',
    '04' => 'ABRIL', '05' => 'MAIO', '06' => 'JUNHO',
    '07' => 'JULHO', '08' => 'AGOSTO', '09' => 'SETEMBRO',
    '10' => 'OUTUBRO', '11' => 'NOVEMBRO', '12' => 'DEZEMBRO'
];

$mes = '';

// Usar a mesma lógica do arquivo principal
if (strpos($periodo, '-') !== false) {
    echo "<p>Formato detectado: YYYY-MM ou YYYY-M</p>";
    $periodoPartes = explode('-', $periodo);
    $mes = isset($periodoPartes[1]) ? str_pad($periodoPartes[1], 2, '0', STR_PAD_LEFT) : '';
    echo "<p>Partes: " . print_r($periodoPartes, true) . "</p>";
    echo "<p>Mês antes do str_pad: '" . ($periodoPartes[1] ?? '') . "'</p>";
} elseif (strpos($periodo, '/') !== false) {
    echo "<p>Formato detectado: YYYY/MM ou YYYY/M</p>";
    $periodoPartes = explode('/', $periodo);
    $mes = isset($periodoPartes[1]) ? str_pad($periodoPartes[1], 2, '0', STR_PAD_LEFT) : '';
    echo "<p>Partes: " . print_r($periodoPartes, true) . "</p>";
} elseif (strlen($periodo) == 6 && is_numeric($periodo)) {
    echo "<p>Formato detectado: YYYYMM</p>";
    $mes = substr($periodo, 4, 2);
} elseif (strlen($periodo) == 5 && is_numeric($periodo)) {
    echo "<p>Formato detectado: YYYYM</p>";
    $mes = '0' . substr($periodo, 4, 1);
} elseif (preg_match('/(\d{4})[\-\/]?(\d{1,2})/', $periodo, $matches)) {
    echo "<p>Formato detectado: Regex YYYY + dígitos</p>";
    echo "<p>Matches: " . print_r($matches, true) . "</p>";
    $mes = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
} else {
    echo "<p>Formato não reconhecido</p>";
}

echo "<p><strong>Mês extraído:</strong> '" . $mes . "'</p>";
echo "<p><strong>Mês existe no array:</strong> " . (isset($meses[$mes]) ? 'SIM' : 'NÃO') . "</p>";

if (isset($meses[$mes])) {
    echo "<p><strong>Mês por extenso:</strong> " . $meses[$mes] . "</p>";
} else {
    echo "<p><strong>Fallback para mês atual:</strong> " . date('m') . " (" . $meses[date('m')] . ")</p>";
}

echo "<hr>";
echo "<p><a href='javascript:history.back()'>← Voltar</a></p>";
?>
