<?php
/**
 * Funções utilitárias para o sistema DIME
 */

/**
 * Formatar valor monetário para exibição
 */
function formatar_valor($valor) {
    if ($valor === null || $valor === '') {
        return '0,00';
    }

    return number_format($valor, 2, ',', '.');
}

/**
 * Formatar período para exibição
 */
function format_periodo($periodo) {
    if ($periodo === null || $periodo === '' || strlen($periodo) !== 6) {
        return $periodo ?? 'Período não informado';
    }

    $mes = substr($periodo, 0, 2);
    $ano = substr($periodo, 2, 4);

    $meses = [
        '01' => 'Janeiro',
        '02' => 'Fevereiro',
        '03' => 'Março',
        '04' => 'Abril',
        '05' => 'Maio',
        '06' => 'Junho',
        '07' => 'Julho',
        '08' => 'Agosto',
        '09' => 'Setembro',
        '10' => 'Outubro',
        '11' => 'Novembro',
        '12' => 'Dezembro'
    ];

    return $meses[$mes] . '/' . $ano;
}

/**
 * Converter valor formatado para float
 */
function parseFloat($value) {
    if ($value === null || $value === '') {
        return 0.0;
    }

    // Remover pontos de milhares e substituir vírgula por ponto
    $cleaned = str_replace('.', '', $value);
    $cleaned = str_replace(',', '.', $cleaned);
    return floatval($cleaned);
}

/**
 * Validar se é uma UF válida
 */
function validar_uf($uf) {
    $ufs_validas = ['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO', 'EX'];
    return in_array(strtoupper($uf), $ufs_validas);
}

/**
 * Sanitizar string para evitar XSS
 */
function sanitize($string) {
    return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
}

/**
 * Gerar token CSRF
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validar token CSRF
 */
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Log de erro para debug
 */
function log_error($message, $context = []) {
    $log_message = date('Y-m-d H:i:s') . ' - ' . $message;
    if (!empty($context)) {
        $log_message .= ' - Context: ' . json_encode($context);
    }
    error_log($log_message);
}
