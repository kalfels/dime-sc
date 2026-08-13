<?php
// Exibe mensagens flash
function display_flash_messages() {
    $messages = [
        'success' => 'alert-success',
        'error' => 'alert-danger',
        'warning' => 'alert-warning',
        'info' => 'alert-info'
    ];
    
    $has_messages = false;
    
    foreach ($messages as $type => $class) {
        if (isset($_SESSION[$type]) && is_array($_SESSION[$type])) {
            foreach ($_SESSION[$type] as $message) {
                echo "<div class=\"alert {$class} alert-dismissible fade show\" role=\"alert\">";
                echo htmlspecialchars($message);
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>';
                echo "</div>";
                $has_messages = true;
            }
            unset($_SESSION[$type]);
        }
    }
    
    return $has_messages;
}

// Função auxiliar para adicionar mensagens
function add_flash_message($type, $message) {
    if (!isset($_SESSION[$type])) {
        $_SESSION[$type] = [];
    }
    $_SESSION[$type][] = $message;
}

// Funções específicas para tipos de mensagem
function add_success_message($message) {
    add_flash_message('success', $message);
}

function add_error_message($message) {
    add_flash_message('error', $message);
}

function add_warning_message($message) {
    add_flash_message('warning', $message);
}

function add_info_message($message) {
    add_flash_message('info', $message);
}
