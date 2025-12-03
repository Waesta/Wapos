<?php
// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

require_once 'includes/bootstrap.php';
require_once 'includes/RateLimiter.php';

// If already logged in, redirect to dashboard
if ($auth->isLoggedIn()) {
    redirect(APP_URL . '/index.php');
}

$error = '';
$rateLimited = false;

// Rate limiting: 5 attempts per 15 minutes per IP
$rateLimiter = new RateLimiter(5, 15);
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitKey = 'login:' . $clientIP;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check rate limit before processing
    if ($rateLimiter->tooManyAttempts($rateLimitKey)) {
        $waitTime = $rateLimiter->availableIn($rateLimitKey);
        $error = "Too many login attempts. Please try again in " . ceil($waitTime / 60) . " minutes.";
        $rateLimited = true;
    }
    // CSRF validation
    elseif (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password';
        } else {
            if ($auth->login($username, $password)) {
                // Clear rate limit on successful login
                $rateLimiter->clear($rateLimitKey);
                redirect(APP_URL . '/index.php');
            } else {
                // Increment rate limit counter on failed attempt
                $rateLimiter->hit($rateLimitKey);
                $remaining = $rateLimiter->remainingAttempts($rateLimitKey);
                $error = 'Invalid username or password';
                if ($remaining > 0 && $remaining <= 3) {
                    $error .= " ($remaining attempts remaining)";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .brand-logo {
            font-size: 3rem;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card login-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-shop brand-logo"></i>
                            <h2 class="mt-2 fw-bold"><?= APP_NAME ?></h2>
                            <p class="text-muted">Waesta Point of Sale System</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="username" 
                                           name="username" 
                                           value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                                           required 
                                           autofocus>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" 
                                           class="form-control" 
                                           id="password" 
                                           name="password" 
                                           required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                            </button>
                        </form>

                        <div class="card bg-light">
                            <div class="card-body py-2">
                                <small class="text-muted d-block mb-1"><strong>Default Login:</strong></small>
                                <small class="text-muted">Username: <code>admin</code> or <code>developer</code></small><br>
                                <small class="text-muted">Password: <code>admin123</code></small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-3 text-white">
                    <small>&copy; <?= date('Y') ?> WAPOS - Waesta POS System</small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
