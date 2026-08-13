<?php
// Payment Warning Banner - Include this in pages that need to show payment warnings
if (isset($_SESSION['has_payment_issues']) && $_SESSION['has_payment_issues'] && !$_SESSION['is_admin']):
?>
<div class="alert alert-warning alert-dismissible fade show mb-4 border border-warning" role="alert">
    <div class="d-flex align-items-center">
        <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
        <div class="flex-grow-1">
            <h6 class="alert-heading mb-1">
                <strong>Pendência Financeira Detectada</strong>
            </h6>
            <p class="mb-2">
                Seu acesso possui restrições devido a pendências financeiras.
                Você pode navegar normalmente, mas algumas funcionalidades estão limitadas.
            </p>
            <div class="mb-0">
                <small class="text-muted">
                    <strong>Funcionalidades Restritas:</strong>
                    <span class="badge bg-warning text-dark me-1">Geração de DIME</span>
                    <span class="badge bg-warning text-dark">Geração de DCIP</span>
                </small>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
</div>

<style>
.alert-warning {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    border-color: #ffc107 !important;
}
.alert-warning .alert-heading {
    color: #856404;
}
.alert-warning .badge {
    font-size: 0.75em;
}
</style>

<?php endif; ?>
