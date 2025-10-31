<?php
/**
 * Enhanced System Health Monitor
 * Monitors DB, disk, queue, errors, and performance
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/Auth.php';

// Require admin access
Auth::requireLogin();
Auth::requireRole(['admin']);

$db = Database::getInstance()->getConnection();

// Get system health metrics
$health = [
    'timestamp' => date('Y-m-d H:i:s'),
    'status' => 'healthy',
    'checks' => []
];

// 1. Database connectivity
try {
    $stmt = $db->query("SELECT 1");
    $health['checks']['database'] = [
        'status' => 'ok',
        'message' => 'Database connection successful',
        'response_time_ms' => 0
    ];
} catch (PDOException $e) {
    $health['checks']['database'] = [
        'status' => 'error',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ];
    $health['status'] = 'unhealthy';
}

// 2. Disk space
$diskFree = disk_free_space(__DIR__);
$diskTotal = disk_total_space(__DIR__);
$diskUsedPercent = (($diskTotal - $diskFree) / $diskTotal) * 100;

$health['checks']['disk'] = [
    'status' => $diskUsedPercent < 90 ? 'ok' : 'warning',
    'free_gb' => round($diskFree / 1024 / 1024 / 1024, 2),
    'total_gb' => round($diskTotal / 1024 / 1024 / 1024, 2),
    'used_percent' => round($diskUsedPercent, 2)
];

if ($diskUsedPercent >= 90) {
    $health['status'] = 'warning';
}

// 3. PHP version
$health['checks']['php'] = [
    'version' => PHP_VERSION,
    'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'ok' : 'warning'
];

// 4. Queue size (Outbox)
try {
    // Check if we can access IndexedDB stats via a tracking table
    $stmt = $db->query("SELECT COUNT(*) as count FROM sync_log WHERE sync_status = 'failed' AND sync_started_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $queueData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $health['checks']['queue'] = [
        'status' => $queueData['count'] < 100 ? 'ok' : 'warning',
        'failed_syncs_last_hour' => $queueData['count']
    ];
} catch (PDOException $e) {
    $health['checks']['queue'] = [
        'status' => 'unknown',
        'message' => 'Queue table not available'
    ];
}

// 5. Last error log
$logFile = __DIR__ . '/../logs/app.log';
if (file_exists($logFile)) {
    $lastLines = tail($logFile, 10);
    $errorCount = 0;
    
    foreach ($lastLines as $line) {
        if (stripos($line, 'ERROR') !== false || stripos($line, 'CRITICAL') !== false) {
            $errorCount++;
        }
    }
    
    $health['checks']['errors'] = [
        'status' => $errorCount === 0 ? 'ok' : 'warning',
        'recent_errors' => $errorCount,
        'log_file' => $logFile,
        'log_size_mb' => round(filesize($logFile) / 1024 / 1024, 2)
    ];
} else {
    $health['checks']['errors'] = [
        'status' => 'ok',
        'message' => 'No log file found'
    ];
}

// 6. Database performance
try {
    $start = microtime(true);
    $stmt = $db->query("SELECT COUNT(*) FROM sales WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)");
    $salesCount = $stmt->fetchColumn();
    $queryTime = (microtime(true) - $start) * 1000;
    
    $health['checks']['performance'] = [
        'status' => $queryTime < 100 ? 'ok' : 'warning',
        'sales_query_time_ms' => round($queryTime, 2),
        'sales_last_24h' => $salesCount
    ];
} catch (PDOException $e) {
    $health['checks']['performance'] = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}

// 7. Memory usage
$memoryUsage = memory_get_usage(true);
$memoryLimit = ini_get('memory_limit');
$memoryLimitBytes = convertToBytes($memoryLimit);
$memoryPercent = ($memoryUsage / $memoryLimitBytes) * 100;

$health['checks']['memory'] = [
    'status' => $memoryPercent < 80 ? 'ok' : 'warning',
    'used_mb' => round($memoryUsage / 1024 / 1024, 2),
    'limit' => $memoryLimit,
    'used_percent' => round($memoryPercent, 2)
];

// Helper functions
function tail($file, $lines = 10) {
    $handle = fopen($file, "r");
    $linecounter = $lines;
    $pos = -2;
    $beginning = false;
    $text = [];
    
    while ($linecounter > 0) {
        $t = " ";
        while ($t != "\n") {
            if (fseek($handle, $pos, SEEK_END) == -1) {
                $beginning = true;
                break;
            }
            $t = fgetc($handle);
            $pos--;
        }
        $linecounter--;
        if ($beginning) {
            rewind($handle);
        }
        $text[$lines - $linecounter - 1] = fgets($handle);
        if ($beginning) break;
    }
    fclose($handle);
    return array_reverse($text);
}

function convertToBytes($value) {
    $value = trim($value);
    $last = strtolower($value[strlen($value)-1]);
    $value = (int) $value;
    
    switch($last) {
        case 'g': $value *= 1024;
        case 'm': $value *= 1024;
        case 'k': $value *= 1024;
    }
    
    return $value;
}

// Output as JSON for API or HTML for browser
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode($health, JSON_PRETTY_PRINT);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health - WAPOS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .status-ok { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-error { color: #dc3545; }
        .health-card { margin-bottom: 1rem; }
        .metric { font-size: 1.5rem; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>System Health Monitor</h1>
        <p class="text-muted">Last updated: <?= $health['timestamp'] ?></p>
        
        <div class="alert alert-<?= $health['status'] === 'healthy' ? 'success' : ($health['status'] === 'warning' ? 'warning' : 'danger') ?>">
            <strong>Overall Status:</strong> <?= ucfirst($health['status']) ?>
        </div>

        <div class="row">
            <?php foreach ($health['checks'] as $name => $check): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card health-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?= ucfirst(str_replace('_', ' ', $name)) ?>
                            <span class="status-<?= $check['status'] ?? 'unknown' ?> float-end">
                                <?= $check['status'] === 'ok' ? '✓' : ($check['status'] === 'warning' ? '⚠' : '✗') ?>
                            </span>
                        </h5>
                        
                        <?php if (isset($check['message'])): ?>
                            <p class="card-text"><?= htmlspecialchars($check['message']) ?></p>
                        <?php endif; ?>
                        
                        <?php foreach ($check as $key => $value): ?>
                            <?php if ($key !== 'status' && $key !== 'message'): ?>
                                <div class="mb-1">
                                    <small class="text-muted"><?= ucfirst(str_replace('_', ' ', $key)) ?>:</small>
                                    <strong><?= is_numeric($value) ? number_format($value, 2) : htmlspecialchars($value) ?></strong>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4">
            <a href="?format=json" class="btn btn-secondary">View as JSON</a>
            <button onclick="location.reload()" class="btn btn-primary">Refresh</button>
        </div>
    </div>

    <script>
        // Auto-refresh every 30 seconds
        setTimeout(() => location.reload(), 30000);
    </script>
</body>
</html>
