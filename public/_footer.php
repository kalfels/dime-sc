        </div> <!-- End row -->
    </div> <!-- End container-fluid -->

    <!-- Footer -->
    <footer class="bg-dark text-light py-3 mt-5">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> Sistema DIME-SC. Todos os direitos reservados.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <small class="text-muted">Versão 1.0 | Desenvolvido para conformidade com a legislação de SC</small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        console.log('[SystemDIME]', 'Sistema inicializado');
        
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight active navigation item
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.nav-link, .dropdown-item');
            
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href && href.includes(currentPage)) {
                    link.classList.add('active');
                    // If it's a dropdown item, also highlight the parent dropdown
                    const dropdown = link.closest('.dropdown');
                    if (dropdown) {
                        const dropdownToggle = dropdown.querySelector('.dropdown-toggle');
                        if (dropdownToggle) {
                            dropdownToggle.classList.add('active');
                        }
                    }
                }
            });

            // Display success/error messages from URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const messageType = urlParams.get('message');
            const messageText = urlParams.get('text');
            
            if (messageType && messageText) {
                console.log('[SystemDIME]', 'Displaying message:', messageType, messageText);
                
                const alertClass = messageType === 'success' ? 'alert-success' : 'alert-danger';
                const iconClass = messageType === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle';
                
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert ${alertClass} alert-dismissible fade show`;
                alertDiv.innerHTML = `
                    <i class="bi ${iconClass} me-2"></i>
                    ${decodeURIComponent(messageText)}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                // Insert at the beginning of the main content area
                const mainContent = document.querySelector('.container-fluid .row');
                if (mainContent) {
                    const alertContainer = document.createElement('div');
                    alertContainer.className = 'col-12';
                    alertContainer.appendChild(alertDiv);
                    mainContent.insertBefore(alertContainer, mainContent.firstChild);
                }

                // Clean URL
                history.replaceState({}, document.title, window.location.pathname);
            }

            // Form validation enhancement
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    console.log('[SystemDIME]', 'Form submission:', form.action || 'current page');
                    
                    // Add loading state to submit buttons
                    const submitBtns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
                    submitBtns.forEach(btn => {
                        btn.disabled = true;
                        if (btn.tagName === 'BUTTON') {
                            const originalText = btn.innerHTML;
                            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processando...';
                            
                            // Reset after 10 seconds as fallback
                            setTimeout(() => {
                                btn.disabled = false;
                                btn.innerHTML = originalText;
                            }, 10000);
                        }
                    });
                });
            });

            // Auto-hide alerts after 10 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 10000);

            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Initialize popovers
            const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
        });

        // Global utility functions
        window.DimeSystem = {
            showAlert: function(message, type = 'info') {
                console.log('[SystemDIME]', 'Showing alert:', type, message);
                
                const alertClass = {
                    'success': 'alert-success',
                    'error': 'alert-danger',
                    'warning': 'alert-warning',
                    'info': 'alert-info'
                }[type] || 'alert-info';
                
                const iconClass = {
                    'success': 'bi-check-circle',
                    'error': 'bi-exclamation-triangle',
                    'warning': 'bi-exclamation-triangle',
                    'info': 'bi-info-circle'
                }[type] || 'bi-info-circle';
                
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert ${alertClass} alert-dismissible fade show`;
                alertDiv.innerHTML = `
                    <i class="bi ${iconClass} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                const container = document.querySelector('.container-fluid .row');
                if (container) {
                    const alertContainer = document.createElement('div');
                    alertContainer.className = 'col-12';
                    alertContainer.appendChild(alertDiv);
                    container.insertBefore(alertContainer, container.firstChild);
                    
                    // Auto-hide after 10 seconds
                    setTimeout(() => {
                        const bsAlert = new bootstrap.Alert(alertDiv);
                        bsAlert.close();
                    }, 10000);
                }
            },
            
            confirmDelete: function(message = 'Tem certeza que deseja excluir este item?') {
                return confirm(message);
            }
        };
    </script>
</body>
</html>