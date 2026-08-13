<?php
/**
 * Quadro 04 - Débitos
 * DIME-SC - Sistema de Declarações
 */

// Verificar se está sendo chamado diretamente
if (!isset($_GET['dime_id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Parâmetro dime_id obrigatório';
    exit;
}

$dime_id = (int)$_GET['dime_id'];
?>

<h5 class="mb-3">
    <i class="bi bi-calculator text-primary me-2"></i>
    Quadro 04 - Débitos
</h5>

<div class="alert alert-warning">
    <i class="bi bi-construction me-2"></i>
    <strong>Quadro 04 - Em Desenvolvimento:</strong>
    Este quadro ainda não foi implementado.
</div>

<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-tools text-warning" style="font-size: 3rem;"></i>
        <h5 class="mt-3">Em Desenvolvimento</h5>
        <p class="text-muted">O Quadro 04 - Débitos será implementado em breve.</p>
    </div>
</div>
