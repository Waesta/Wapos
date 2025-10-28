<?php if (isset($auth) && $auth->isLoggedIn()): ?>
        </div>
    </div>
<?php else: ?>
    </div>
<?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Service Worker Registration -->
    <script>
        if ("serviceWorker" in navigator) {
            navigator.serviceWorker.register("service-worker.js")
                .then(registration => console.log("SW registered"))
                .catch(error => console.log("SW registration failed"));
        }
    </script>
</body>
</html>
