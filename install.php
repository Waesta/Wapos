<?php
/**
 * WAPOS Installer
 * Run this file once to set up the database
 */

require_once 'config.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Connect without database first
        $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Read and execute SQL file
        $sql = file_get_contents(__DIR__ . '/database/schema.sql');
        
        // Split into individual statements and execute
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        $success = true;
        $message = 'Database installed successfully! You can now login.';
        
    } catch (PDOException $e) {
        $message = 'Installation failed: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 50px 0;
        }
        .install-card {
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card install-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-gear-fill text-primary" style="font-size: 4rem;"></i>
                            <h2 class="mt-3 fw-bold"><?= APP_NAME ?> Installation</h2>
                            <p class="text-muted">Set up your database in one click</p>
                        </div>

                        <?php if ($message): ?>
                            <div class="alert alert-<?= $success ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                                <i class="bi bi-<?= $success ? 'check-circle' : 'exclamation-triangle' ?>-fill me-2"></i>
                                <?= htmlspecialchars($message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="card bg-light mb-4">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-person-check text-success me-2"></i>Default Accounts Created</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Username:</strong> <code>admin</code><br>
                                            <strong>Password:</strong> <code>admin123</code><br>
                                            <strong>Role:</strong> Administrator
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Username:</strong> <code>developer</code><br>
                                            <strong>Password:</strong> <code>admin123</code><br>
                                            <strong>Role:</strong> Administrator
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid">
                                <a href="login.php" class="btn btn-primary btn-lg">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="card bg-light mb-4">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-info-circle text-info me-2"></i>What will be installed:</h5>
                                    <ul class="mb-0">
                                        <li>Database: <strong><?= DB_NAME ?></strong></li>
                                        <li>8 database tables (users, products, sales, etc.)</li>
                                        <li>2 admin accounts (admin & developer)</li>
                                        <li>Sample categories</li>
                                        <li>Default settings</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="card border-warning mb-4">
                                <div class="card-body">
                                    <h6 class="card-title text-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i>Before Installing:</h6>
                                    <ol class="mb-0 small">
                                        <li>Make sure XAMPP MySQL is running</li>
                                        <li>Check database credentials in config.php</li>
                                        <li>Backup any existing 'wapos' database</li>
                                    </ol>
                                </div>
                            </div>

                            <form method="POST">
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-lightning-fill me-2"></i>Install Now
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="text-center mt-3 text-white">
                    <small>&copy; <?= date('Y') ?> WAPOS - Professional POS System</small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
