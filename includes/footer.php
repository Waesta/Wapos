<?php if (isset($auth) && $auth->isLoggedIn()): ?>
        </div>
    </div>
<?php else: ?>
    </div>
<?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Service Worker Registration with Update Handling -->
    <script>
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
