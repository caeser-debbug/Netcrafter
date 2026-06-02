</div>
                <!-- Content ends here -->
            </main>
            
            <!-- Footer -->
            <footer class="bg-white dark:bg-gray-800 shadow-sm py-4 px-6 mt-auto">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        &copy; <?php echo date('Y'); ?> Netcrafter. Tous droits réservés.
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-500 mt-2 md:mt-0">
                        Version 1.0.0
                    </div>
                </div>
            </footer>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Flatpickr for date picker -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>
    <!-- Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Common JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle functionality
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebarClose = document.getElementById('sidebar-close');
            const sidebar = document.getElementById('sidebar');
            
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('-translate-x-full');
                });
            }
            
            if (sidebarClose && sidebar) {
                sidebarClose.addEventListener('click', function() {
                    sidebar.classList.add('-translate-x-full');
                });
            }
            
            // Submenu toggles - CORRIGÉ
            const submenuButtons = document.querySelectorAll('[id$="-menu-button"]');
            submenuButtons.forEach(button => {
                // Modification ici: on obtient l'ID du sous-menu correctement
                const submenuId = button.id.replace('-menu-button', '-submenu');
                const submenu = document.getElementById(submenuId);
                
                if (submenu) {
                    button.addEventListener('click', function() {
                        submenu.classList.toggle('hidden');
                        const icon = button.querySelector('.fas.fa-chevron-down');
                        if (icon) {
                            icon.classList.toggle('transform');
                            icon.classList.toggle('rotate-180');
                        }
                    });
                }
            });
            
            // User dropdown toggle
            const userMenuButton = document.getElementById('user-menu-button');
            const userDropdown = document.getElementById('user-dropdown');
            
            if (userMenuButton && userDropdown) {
                userMenuButton.addEventListener('click', function() {
                    userDropdown.classList.toggle('hidden');
                });
            }
            
            // Close dropdowns when clicking outside
            document.addEventListener('click', function(event) {
                if (userMenuButton && userDropdown && !userMenuButton.contains(event.target) && !userDropdown.contains(event.target)) {
                    userDropdown.classList.add('hidden');
                }
                
                submenuButtons.forEach(button => {
                    // Correction ici aussi pour la cohérence
                    const submenuId = button.id.replace('-menu-button', '-submenu');
                    const submenu = document.getElementById(submenuId);
                    
                    if (submenu && !button.contains(event.target) && !submenu.contains(event.target)) {
                        // Ne pas cacher le sous-menu actif au chargement de la page
                        if (!button.classList.contains('bg-netblue-600')) {
                            submenu.classList.add('hidden');
                            const icon = button.querySelector('.fas.fa-chevron-down');
                            if (icon) {
                                icon.classList.remove('transform', 'rotate-180');
                            }
                        }
                    }
                });
            });
            
            // Dark mode toggle
            const darkModeToggle = document.getElementById('dark-mode-toggle');
            const htmlElement = document.documentElement;
            
            // Check for saved theme preference
            if (localStorage.getItem('darkMode') === 'enabled') {
                htmlElement.classList.add('dark');
            }
            
            // Toggle dark mode
            if (darkModeToggle) {
                darkModeToggle.addEventListener('click', function() {
                    if (htmlElement.classList.contains('dark')) {
                        htmlElement.classList.remove('dark');
                        localStorage.setItem('darkMode', 'disabled');
                    } else {
                        htmlElement.classList.add('dark');
                        localStorage.setItem('darkMode', 'enabled');
                    }
                });
            }
            
            // Initialize Select2
            if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
                $('.select2').select2({
                    theme: 'classic',
                    placeholder: 'Sélectionner une option',
                    allowClear: true
                });
            }
            
            // Initialize Flatpickr date pickers
            if (window.flatpickr) {
                flatpickr(".datepicker", {
                    locale: "fr",
                    dateFormat: "d/m/Y",
                    disableMobile: true
                });
                
                flatpickr(".datetimepicker", {
                    locale: "fr",
                    dateFormat: "d/m/Y H:i",
                    enableTime: true,
                    time_24hr: true,
                    disableMobile: true
                });
            }
            
            // Toast notification function
            window.showToast = function(message, type = 'success') {
                const toastContainer = document.getElementById('toast-container');
                if (!toastContainer) return;
                
                const toast = document.createElement('div');
                
                // Set toast class based on type
                let bgColor, textColor, icon;
                switch (type) {
                    case 'success':
                        bgColor = 'bg-green-500';
                        textColor = 'text-white';
                        icon = 'fa-check-circle';
                        break;
                    case 'error':
                        bgColor = 'bg-red-500';
                        textColor = 'text-white';
                        icon = 'fa-exclamation-circle';
                        break;
                    case 'warning':
                        bgColor = 'bg-yellow-500';
                        textColor = 'text-white';
                        icon = 'fa-exclamation-triangle';
                        break;
                    case 'info':
                        bgColor = 'bg-blue-500';
                        textColor = 'text-white';
                        icon = 'fa-info-circle';
                        break;
                    default:
                        bgColor = 'bg-gray-800';
                        textColor = 'text-white';
                        icon = 'fa-bell';
                }
                
                toast.className = `toast ${bgColor} ${textColor}`;
                toast.innerHTML = `
                    <div class="flex items-center">
                        <i class="fas ${icon} mr-2"></i>
                        <span>${message}</span>
                        <button class="ml-4 text-white hover:text-gray-200 focus:outline-none" onclick="this.parentElement.parentElement.remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>`;
                
                toastContainer.appendChild(toast);
                
                // Show toast
                setTimeout(() => {
                    toast.classList.add('show');
                }, 100);
                
                // Auto-hide after 5 seconds
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => {
                        toast.remove();
                    }, 300);
                }, 5000);
            }
            
            // Show toast if message exists in URL params
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
                showToast(decodeURIComponent(urlParams.get('success')), 'success');
            }
            if (urlParams.has('error')) {
                showToast(decodeURIComponent(urlParams.get('error')), 'error');
            }
            if (urlParams.has('warning')) {
                showToast(decodeURIComponent(urlParams.get('warning')), 'warning');
            }
            if (urlParams.has('info')) {
                showToast(decodeURIComponent(urlParams.get('info')), 'info');
            }

            // Initialiser les tooltips si présents
            const tooltips = document.querySelectorAll('[data-tooltip]');
            tooltips.forEach(tooltip => {
                tooltip.addEventListener('mouseenter', function() {
                    const text = this.getAttribute('data-tooltip');
                    const tooltipEl = document.createElement('div');
                    tooltipEl.className = 'absolute z-50 px-2 py-1 text-xs text-white bg-gray-800 rounded shadow-lg';
                    tooltipEl.innerHTML = text;
                    tooltipEl.style.bottom = '100%';
                    tooltipEl.style.left = '50%';
                    tooltipEl.style.transform = 'translateX(-50%) translateY(-5px)';
                    this.appendChild(tooltipEl);
                    
                    setTimeout(() => {
                        tooltipEl.style.opacity = '1';
                    }, 10);
                });
                
                tooltip.addEventListener('mouseleave', function() {
                    const tooltipEl = this.querySelector('.absolute');
                    if (tooltipEl) {
                        tooltipEl.style.opacity = '0';
                        setTimeout(() => {
                            tooltipEl.remove();
                        }, 300);
                    }
                });
            });

            // Gestion des modals génériques
            const modalTriggers = document.querySelectorAll('[data-modal-target]');
            modalTriggers.forEach(trigger => {
                trigger.addEventListener('click', function() {
                    const modalId = this.getAttribute('data-modal-target');
                    const modal = document.getElementById(modalId);
                    if (modal) {
                        modal.classList.remove('hidden');
                    }
                });
            });

            const modalCloseButtons = document.querySelectorAll('[data-modal-close]');
            modalCloseButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const modal = this.closest('.modal');
                    if (modal) {
                        modal.classList.add('hidden');
                    }
                });
            });

            // Fermez les modals en cliquant à l'extérieur
            document.addEventListener('click', function(event) {
                if (event.target.classList.contains('modal')) {
                    event.target.classList.add('hidden');
                }
            });
        });
    </script>
    <?php
// Vider le tampon de sortie et envoyer le contenu au navigateur
ob_end_flush();
?>
</body>
</html>