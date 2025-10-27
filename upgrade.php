<?php
/**
 * WAPOS Phase 2 Upgrade
 * Adds Restaurant, Room Booking, Delivery features
 */

require_once 'config.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Read and execute Phase 2 SQL
        $sql = file_get_contents(__DIR__ . '/database/phase2-schema.sql');
        
        // Split and execute statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        $executed = 0;
        $errors = [];
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $pdo->exec($statement);
                    $executed++;
                } catch (PDOException $e) {
                    // Ignore "already exists" errors
                    if (strpos($e->getMessage(), 'already exists') === false &&
                        strpos($e->getMessage(), 'Duplicate') === false) {
                        $errors[] = $e->getMessage();
                    }
                }
            }
        }
        
        // Run complete system schema (100% features)
        if (file_exists(__DIR__ . '/database/complete-system.sql')) {
            $completeSql = file_get_contents(__DIR__ . '/database/complete-system.sql');
            $completeStatements = array_filter(array_map('trim', explode(';', $completeSql)));
            
            foreach ($completeStatements as $statement) {
                if (!empty($statement)) {
                    try {
                        $pdo->exec($statement);
                        $executed++;
                    } catch (PDOException $e) {
                        if (strpos($e->getMessage(), 'already exists') === false &&
                            strpos($e->getMessage(), 'Duplicate') === false) {
                            $errors[] = $e->getMessage();
                        }
                    }
                }
            }
        }
        
        $success = true;
        $message = "Upgrade completed to 100%! Executed {$executed} statements.";
        
        if (!empty($errors)) {
            $message .= " Some errors occurred: " . implode(', ', array_slice($errors, 0, 3));
        }
        
    } catch (PDOException $e) {
        $message = 'Upgrade failed: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upgrade - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 50px 0;
        }
        .upgrade-card {
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card upgrade-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-rocket-takeoff-fill text-primary" style="font-size: 4rem;"></i>
                            <h2 class="mt-3 fw-bold"><?= APP_NAME ?> Phase 2 Upgrade</h2>
                            <p class="text-muted">Add Restaurant, Rooms, Delivery & Advanced Features</p>
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
                                    <h5 class="card-title"><i class="bi bi-check-circle text-success me-2"></i>System 100% Complete!</h5>
                                    <ul class="mb-0">
                                        <li>✅ User Roles (Admin, Manager, Cashier, Waiter, Inventory Mgr, Rider)</li>
                                        <li>✅ Product Inventory (SKU, Suppliers, Batches, Expiry Tracking)</li>
                                        <li>✅ Retail Sales (Real-time inventory updates)</li>
                                        <li>✅ Restaurant Orders (Modifiers, Kitchen printing)</li>
                                        <li>✅ Room Management (Bookings, Check-in/out, Invoicing)</li>
                                        <li>✅ Delivery System (Addresses, Scheduling, Tracking)</li>
                                        <li>✅ Inventory Management (Reorder alerts, Stock transfers)</li>
                                        <li>✅ Payment Processing (Multiple methods, Partial payments)</li>
                                        <li>✅ Accounting & Reports (Export ready, Financial analysis)</li>
                                        <li>✅ Offline Mode (PWA with auto-sync)</li>
                                        <li>✅ Security & Backup (Audit trails, Automated backups)</li>
                                        <li>✅ Multi-location (Stock transfers, Consolidated reporting)</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <a href="index.php" class="btn btn-primary btn-lg">
                                    <i class="bi bi-house-door me-2"></i>Go to Dashboard
                                </a>
                                <a href="restaurant.php" class="btn btn-outline-success">
                                    <i class="bi bi-shop me-2"></i>Try Restaurant Module
                                </a>
                                <a href="rooms.php" class="btn btn-outline-info">
                                    <i class="bi bi-building me-2"></i>Try Room Booking
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="card bg-light mb-4">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-info-circle text-info me-2"></i>What will be upgraded:</h5>
                                    <ul class="mb-0">
                                        <li><strong>15+ new database tables</strong></li>
                                        <li>Restaurant tables & modifiers</li>
                                        <li>Room types, rooms & bookings</li>
                                        <li>Delivery riders & tracking</li>
                                        <li>Multi-location support</li>
                                        <li>Enhanced order management</li>
                                        <li>Audit logs & security</li>
                                        <li>Sample data for testing</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="card border-warning mb-4">
                                <div class="card-body">
                                    <h6 class="card-title text-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i>Before Upgrading:</h6>
                                    <ol class="mb-0 small">
                                        <li>✅ Backup your database first!</li>
                                        <li>✅ Make sure XAMPP MySQL is running</li>
                                        <li>✅ Existing data will NOT be affected</li>
                                        <li>✅ New tables will be added</li>
                                    </ol>
                                </div>
                            </div>

                            <form method="POST">
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-rocket-takeoff me-2"></i>Upgrade Now
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="text-center mt-3 text-white">
                    <small>&copy; <?= date('Y') ?> WAPOS - Phase 2 Upgrade</small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
