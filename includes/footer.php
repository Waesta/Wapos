<?php if (isset($auth) && $auth->isLoggedIn()): ?>
        </div>
    </div>
<?php else: ?>
    </div>
<?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Sidebar Interaction & Service Worker Registration -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('appSidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
            const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
            const mainContent = document.getElementById('mainContent');
            const navGroups = Array.from(document.querySelectorAll('.nav-group'));

            function openSidebar() {
                sidebar?.classList.add('show');
                sidebarOverlay?.classList.add('show');
                document.body.classList.add('sidebar-open');
            }

            function closeSidebar() {
                sidebar?.classList.remove('show');
                sidebarOverlay?.classList.remove('show');
                document.body.classList.remove('sidebar-open');
            }

            sidebarToggleBtn?.addEventListener('click', openSidebar);
            sidebarCloseBtn?.addEventListener('click', closeSidebar);
            sidebarOverlay?.addEventListener('click', closeSidebar);

            mainContent?.addEventListener('click', event => {
                if (window.innerWidth <= 768 && sidebar?.classList.contains('show') && !sidebar.contains(event.target)) {
                    closeSidebar();
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
