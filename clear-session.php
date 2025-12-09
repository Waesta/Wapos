<?php
/**
 * Session Reset Utility
 * Use this to clear session data and fix redirect loops
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_name('wapos_session');
    session_start();
}

// Clear all session data
$_SESSION = [];

// Destroy the session
if (session_id()) {
    session_destroy();
}

// Clear session cookie
if (isset($_COOKIE['wapos_session'])) {
    setcookie('wapos_session', '', time() - 3600, '/');
}

// Clear any other WAPOS cookies
foreach ($_COOKIE as $name => $value) {
    if (strpos($name, 'wapos') !== false || strpos($name, 'PHPSESSID') !== false) {
        setcookie($name, '', time() - 3600, '/');
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Cleared</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body text-center py-5">
                        <div class="text-success mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                            </svg>
                        </div>
                        <h4 class="mb-3">Session Cleared Successfully</h4>
                        <p class="text-muted mb-4">Your session has been reset. You can now log in again.</p>
                        <a href="login.php" class="btn btn-primary btn-lg">
                            Go to Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
