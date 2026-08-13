<?php
require_once __DIR__ . '/includes/auth.php';

$page_title = 'Acesso Negado';
require_once '_header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 text-center">
            <div class="card shadow">
                <div class="card-header bg-danger text-white">
                    <h4 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Acesso Negado</h4>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <i class="bi bi-shield-lock text-danger" style="font-size: 5rem;"></i>
                    </div>
                    <h3 class="text-danger">Você não tem permissão para acessar esta página.</h3>
                    <p class="lead">Entre em contato com o administrador do sistema se acredita que isso é um erro.</p>
                    
                    <div class="mt-4">
                        <a href="dashboard.php" class="btn btn-primary me-2">
                            <i class="bi bi-house-door me-1"></i> Ir para o Início
                        </a>
                        <a href="logout.php" class="btn btn-outline-secondary">
                            <i class="bi bi-box-arrow-right me-1"></i> Sair
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '_footer.php'; ?>
