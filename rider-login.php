<?php
/**
 * Rider Login Page - Mobile-optimized login for delivery riders
 */

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

require_once 'includes/bootstrap.php';
require_once 'includes/RateLimiter.php';

// If already logged in, redirect
if ($auth->isLoggedIn()) {
    $role = $auth->getRole();
    if ($role === 'rider') {
        redirect('rider-portal.php');
    } else {
        redirectToDashboard($auth);
    }
}

$error = '';
$rateLimited = false;

// Rate limiting: 5 attempts per 15 minutes per IP
$rateLimiter = new RateLimiter(5, 15);
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitKey = 'rider_login:' . $clientIP;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check rate limit
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
                // Verify user is a rider
                $role = $auth->getRole();
                if ($role === 'rider') {
                    $rateLimiter->clear($rateLimitKey);
                    redirect('rider-portal.php');
                } else {
                    // Not a rider, log them out and show error
                    $auth->logout();
                    $error = 'This login is for riders only. Please use the main login page.';
                }
            } else {
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#667eea">
    <title>Rider Login - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        * {
            -webkit-tap-highlight-color: transparent;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        
        .rider-login-container {
            width: 100%;
            max-width: 400px;
        }
        
        .login-card {
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            border: none;
            overflow: hidden;
        }
        
        .login-header {
            background: white;
            padding: 40px 30px 30px;
            text-align: center;
            border-bottom: 1px solid #e9ecef;
        }
        
        .rider-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .rider-icon i {
            font-size: 40px;
            color: white;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .form-control {
            height: 50px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            font-size: 16px;
            padding: 12px 20px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }
        
        .input-group-text {
            border-radius: 8px 0 0 8px;
            border: 1px solid #dee2e6;
            border-right: none;
            background: #f8f9fa;
            padding: 0 15px;
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 8px 8px 0;
        }
        
        .btn-login {
            height: 50px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        .login-title {
            color: #212529;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .login-subtitle {
            color: #6c757d;
            font-size: 14px;
        }
        
        .footer-link {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .footer-link:hover {
            color: white;
            transform: translateX(-5px);
        }
        
        .version-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            display: inline-block;
            margin-top: 10px;
            font-weight: 600;
        }
        
        @media (max-width: 576px) {
            .login-header {
                padding: 30px 20px 20px;
            }
            
            .rider-icon {
                width: 70px;
                height: 70px;
            }
            
            .rider-icon i {
                font-size: 35px;
            }
            
            .login-body {
                padding: 25px 20px;
            }
        }
        
        /* PWA Install prompt */
        .install-prompt {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
            color: white;
            text-align: center;
            display: none;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .install-prompt.show {
            display: block;
        }
        
        .brand-logo {
            max-width: 120px;
            height: auto;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="rider-login-container">
        <div class="card login-card">
            <div class="login-header">
                <img src="<?= APP_URL ?>/assets/images/system/wapos-logo.svg" alt="<?= APP_NAME ?>" class="brand-logo" onerror="this.style.display='none'">
                <div class="rider-icon">
                    <i class="bi bi-bicycle"></i>
                </div>
                <h2 class="login-title">Rider Portal</h2>
                <p class="login-subtitle mb-0">Sign in to start deliveries</p>
                <span class="version-badge">DELIVERY</span>
            </div>

            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 flex-shrink-0"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" autocomplete="on">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label fw-semibold">Username</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-person text-primary"></i>
                            </span>
                            <input type="text" 
                                   class="form-control" 
                                   id="username" 
                                   name="username" 
                                   value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                                   placeholder="Enter your username"
                                   autocomplete="username"
                                   required 
                                   autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label fw-semibold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-lock text-primary"></i>
                            </span>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password"
                                   placeholder="Enter your password"
                                   autocomplete="current-password"
                                   required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-login w-100 mb-3" <?= $rateLimited ? 'disabled' : '' ?>>
                        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In to Portal
                    </button>
                </form>

                <div class="text-center">
                    <small class="text-muted">
                        <i class="bi bi-shield-check me-1"></i>
                        Secure rider authentication
                    </small>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="login.php" class="footer-link">
                <i class="bi bi-arrow-left"></i>
                Back to main login
            </a>
        </div>

        <div class="text-center mt-3">
            <small class="text-white opacity-75">
                &copy; <?= date('Y') ?> <?= APP_NAME ?> - Rider Portal
            </small>
        </div>

        <!-- PWA Install Prompt -->
        <div class="install-prompt" id="installPrompt">
            <i class="bi bi-phone fs-3 mb-2 d-block"></i>
            <p class="mb-2 small">Install this app on your phone for quick access</p>
            <button class="btn btn-light btn-sm" id="installButton">
                <i class="bi bi-download me-1"></i>Install App
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // PWA Install prompt
        let deferredPrompt;
        const installPrompt = document.getElementById('installPrompt');
        const installButton = document.getElementById('installButton');

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            installPrompt.classList.add('show');
        });

        installButton.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                console.log(`User response: ${outcome}`);
                deferredPrompt = null;
                installPrompt.classList.remove('show');
            }
        });

        // Auto-focus on username field
        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.getElementById('username');
            if (usernameField && !usernameField.value) {
                setTimeout(() => usernameField.focus(), 100);
            }
        });

        // Prevent zoom on input focus (iOS)
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('focus', function() {
                this.style.fontSize = '16px';
            });
        });
    </script>
</body>
</html>
