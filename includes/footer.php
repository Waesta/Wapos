<?php if (isset($auth) && $auth->isLoggedIn()): ?>
        </div>
    </div>
<?php else: ?>
    </div>
<?php endif; ?>

    <!-- Bootstrap JS with local fallback -->
    <script>
        window.__loadLocalBootstrap = window.__loadLocalBootstrap || function () {
            if (window.__localBootstrapLoaded) {
                return;
            }
            window.__localBootstrapLoaded = true;
            var fallback = document.createElement('script');
            fallback.src = '<?= APP_URL ?>/assets/js/bootstrap.bundle.min.js?v=1';
            fallback.defer = false;
            document.head.appendChild(fallback);
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
            crossorigin="anonymous"
            onerror="window.__loadLocalBootstrap && window.__loadLocalBootstrap();"></script>
    <script>
        setTimeout(function () {
            if (!(window.bootstrap && window.bootstrap.Modal)) {
                window.__loadLocalBootstrap && window.__loadLocalBootstrap();
            }
        }, 1500);
    </script>
    
    <!-- Sidebar Interaction & Service Worker Registration -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('appSidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
            const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
            const mainContent = document.getElementById('mainContent');
            const navGroups = Array.from(document.querySelectorAll('.nav-group'));
            
            // Check if we're on mobile/tablet
            const isMobile = () => window.innerWidth <= 1024;

            function openSidebar() {
                sidebar?.classList.add('show');
                sidebarOverlay?.classList.add('show');
                document.body.classList.add('sidebar-open');
                // Remember sidebar is open
                localStorage.setItem('sidebarOpen', 'true');
            }

            function closeSidebar() {
                sidebar?.classList.remove('show');
                sidebarOverlay?.classList.remove('show');
                document.body.classList.remove('sidebar-open');
                // Remember sidebar is closed
                localStorage.setItem('sidebarOpen', 'false');
            }
            
            // Restore sidebar state on mobile/tablet
            if (isMobile()) {
                // On mobile, restore previous state
                if (localStorage.getItem('sidebarOpen') === 'true') {
                    openSidebar();
                }
            } else {
                // On desktop, sidebar is always visible, clear mobile state
                localStorage.removeItem('sidebarOpen');
            }

            sidebarToggleBtn?.addEventListener('click', openSidebar);
            sidebarCloseBtn?.addEventListener('click', closeSidebar);
            sidebarOverlay?.addEventListener('click', closeSidebar);
            
            // Handle window resize - if going from mobile to desktop, ensure proper state
            window.addEventListener('resize', () => {
                if (!isMobile()) {
                    // Desktop - sidebar always visible via CSS, remove mobile classes
                    sidebar?.classList.remove('show');
                    sidebarOverlay?.classList.remove('show');
                    document.body.classList.remove('sidebar-open');
                    localStorage.removeItem('sidebarOpen');
                }
            });

            navGroups.forEach(group => {
                const toggle = group.querySelector('.nav-group-toggle');
                const targetId = toggle?.getAttribute('data-target');
                const target = targetId ? document.querySelector(targetId) : null;

                if (!toggle || !target) {
                    return;
                }

                const defaultOpen = group.classList.contains('open');
                if (defaultOpen) {
                    target.style.maxHeight = target.scrollHeight + 'px';
                }

                toggle.addEventListener('click', () => {
                    const isOpen = group.classList.toggle('open');
                    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                    target.style.maxHeight = isOpen ? target.scrollHeight + 'px' : '0';
                });
            });

            const dropdownToggles = document.querySelectorAll('[data-bs-toggle="dropdown"]');
            if (dropdownToggles.length) {
                const closeAllDropdowns = () => {
                    document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                        menu.classList.remove('show');
                        menu.style.display = '';
                    });
                };

                dropdownToggles.forEach(toggle => {
                    toggle.addEventListener('click', event => {
                        if (window.bootstrap && bootstrap.Dropdown) {
                            return;
                        }
                        event.preventDefault();
                        event.stopPropagation();

                        const menu = toggle.nextElementSibling && toggle.nextElementSibling.classList.contains('dropdown-menu')
                            ? toggle.nextElementSibling
                            : null;
                        if (!menu) {
                            return;
                        }

                        const isShown = menu.classList.contains('show');
                        closeAllDropdowns();
                        if (!isShown) {
                            menu.classList.add('show');
                            menu.style.display = 'block';
                        }
                    });
                });

                document.addEventListener('click', event => {
                    if (window.bootstrap && bootstrap.Dropdown) {
                        return;
                    }
                    if (!event.target.closest('.dropdown-menu') && !event.target.closest('[data-bs-toggle="dropdown"]')) {
                        closeAllDropdowns();
                    }
                });
            }
        });

        if ("serviceWorker" in navigator) {
            // Register service worker
            navigator.serviceWorker.register("/wapos/service-worker.js", {
                scope: '/wapos/'
            })
            .then(registration => {
                console.log("[SW] Registered successfully");
                
                // Check for updates on page load
                registration.update();
                
                // Handle updates
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // New service worker available, prompt user to refresh
                            if (confirm('New version available! Click OK to update.')) {
                                newWorker.postMessage({ type: 'SKIP_WAITING' });
                                window.location.reload();
                            }
                        }
                    });
                });
            })
            .catch(error => {
                console.error("[SW] Registration failed:", error);
                // If SW fails, continue without it - don't break the app
            });
            
            // Handle controller change (new SW activated)
            navigator.serviceWorker.addEventListener('controllerchange', () => {
                console.log("[SW] Controller changed, reloading page");
                window.location.reload();
            });
        }
    </script>
</body>
</html>
