<?php
/**
 * WAPOS - Access Denied Page
 * Displayed when user doesn't have permission to access a resource
 */

require_once 'includes/bootstrap.php';

$pageTitle = 'Access Denied';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= APP_NAME ?></title>
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
        .error-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 3rem;
            text-align: center;
            max-width: 450px;
        }
        .error-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }
        .error-code {
            font-size: 1.2rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        .error-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #212529;
            margin-bottom: 1rem;
        }
        .error-message {
            color: #6c757d;
            margin-bottom: 2rem;
        }
        .btn-group-custom {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">
            <i class="bi bi-shield-x"></i>
        </div>
        <div class="error-code">Error 403</div>
        <h1 class="error-title">Access Denied</h1>
        <p class="error-message">
            You don't have permission to access this page. 
            <?php if ($auth->isLoggedIn()): ?>
                Your current role (<strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $auth->getRole()))) ?></strong>) 
                doesn't have the required privileges.
            <?php else: ?>
                Please log in with an account that has the required permissions.
            <?php endif; ?>
        </p>
        <div class="btn-group-custom">
            <?php if ($auth->isLoggedIn()): ?>
                <?php
                // Determine safe redirect based on role
                $dashboardMap = [
                    'super_admin' => 'dashboards/admin.php',
                    'developer' => 'dashboards/admin.php',
                    'admin' => 'dashboards/admin.php',
                    'manager' => 'dashboards/manager.php',
                    'accountant' => 'dashboards/accountant.php',
                    'cashier' => 'dashboards/cashier.php',
                    'waiter' => 'dashboards/waiter.php',
                ];
                $role = $auth->getRole();
                $safeRedirect = $dashboardMap[$role] ?? 'pos.php';
                ?>
                <a href="<?= APP_URL ?>/<?= $safeRedirect ?>" class="btn btn-primary">
                    <i class="bi bi-house me-1"></i> Go to Dashboard
                </a>
                <a href="<?= APP_URL ?>/logout.php" class="btn btn-outline-secondary">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            <?php else: ?>
                <a href="<?= APP_URL ?>/login.php" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Login
                </a>
                <a href="<?= APP_URL ?>/" class="btn btn-outline-secondary">
                    <i class="bi bi-house me-1"></i> Home
                </a>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
