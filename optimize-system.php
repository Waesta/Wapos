<?php
/**
 * WAPOS System Optimization Script
 * Cleans cache, optimizes database, and improves performance
 */

require_once 'includes/bootstrap.php';
$auth->requireRole('admin');

$db = Database::getInstance();
$performance = PerformanceManager::getInstance();

$optimizations = [];
$errors = [];

echo "<h1>🚀 WAPOS System Optimization</h1>";
echo "<p>Running comprehensive system optimization...</p>";

// 1. Clean old cache files
echo "<h2>🧹 Cleaning Cache</h2>";
try {
    $performance->cleanCache(3600); // Clean files older than 1 hour
    $performance->invalidateCache('*');
    $db->clearCache();
    $optimizations[] = "✅ Cache cleaned successfully";
    echo "<p class='text-success'>✅ Cache cleaned successfully</p>";
} catch (Exception $e) {
    $errors[] = "❌ Cache cleaning failed: " . $e->getMessage();
    echo "<p class='text-danger'>❌ Cache cleaning failed: " . $e->getMessage() . "</p>";
}

// 2. Optimize database tables
echo "<h2>🗄️ Optimizing Database</h2>";
try {
    $tables = $db->fetchAll("SHOW TABLES");
    $optimizedTables = 0;
    
    foreach ($tables as $table) {
        $tableName = array_values($table)[0];
        $db->query("OPTIMIZE TABLE `$tableName`");
        $optimizedTables++;
    }
    
    $optimizations[] = "✅ Optimized $optimizedTables database tables";
    echo "<p class='text-success'>✅ Optimized $optimizedTables database tables</p>";
} catch (Exception $e) {
    $errors[] = "❌ Database optimization failed: " . $e->getMessage();
    echo "<p class='text-danger'>❌ Database optimization failed: " . $e->getMessage() . "</p>";
}

// 3. Clean old log files
echo "<h2>📝 Cleaning Logs</h2>";
try {
    $logDir = ROOT_PATH . '/logs';
    if (is_dir($logDir)) {
        $logFiles = glob($logDir . '/*.log');
        $cleanedLogs = 0;
        
        foreach ($logFiles as $logFile) {
            if (time() - filemtime($logFile) > 7 * 24 * 3600) { // 7 days old
                unlink($logFile);
                $cleanedLogs++;
            }
        }
        
        $optimizations[] = "✅ Cleaned $cleanedLogs old log files";
        echo "<p class='text-success'>✅ Cleaned $cleanedLogs old log files</p>";
    } else {
        echo "<p class='text-info'>ℹ️ No log directory found</p>";
    }
} catch (Exception $e) {
    $errors[] = "❌ Log cleaning failed: " . $e->getMessage();
    echo "<p class='text-danger'>❌ Log cleaning failed: " . $e->getMessage() . "</p>";
}

// 4. Update database statistics
echo "<h2>📊 Updating Database Statistics</h2>";
try {
    $db->query("ANALYZE TABLE products, sales, orders, customers");
    $optimizations[] = "✅ Database statistics updated";
    echo "<p class='text-success'>✅ Database statistics updated</p>";
} catch (Exception $e) {
    $errors[] = "❌ Statistics update failed: " . $e->getMessage();
    echo "<p class='text-danger'>❌ Statistics update failed: " . $e->getMessage() . "</p>";
}

// 5. Clean old sessions
echo "<h2>🔐 Cleaning Sessions</h2>";
try {
    $sessionPath = session_save_path();
    if (empty($sessionPath)) {
        $sessionPath = sys_get_temp_dir();
    }
    
    $sessionFiles = glob($sessionPath . '/sess_*');
    $cleanedSessions = 0;
    
    foreach ($sessionFiles as $sessionFile) {
        if (time() - filemtime($sessionFile) > SESSION_LIFETIME) {
            unlink($sessionFile);
            $cleanedSessions++;
        }
    }
    
    $optimizations[] = "✅ Cleaned $cleanedSessions expired sessions";
    echo "<p class='text-success'>✅ Cleaned $cleanedSessions expired sessions</p>";
} catch (Exception $e) {
    $errors[] = "❌ Session cleaning failed: " . $e->getMessage();
    echo "<p class='text-danger'>❌ Session cleaning failed: " . $e->getMessage() . "</p>";
}

// 6. Check for database indexes
echo "<h2>🔍 Checking Database Indexes</h2>";
try {
    $missingIndexes = [];
    
    // Check for common missing indexes
    $indexChecks = [
        'sales' => ['created_at', 'user_id', 'customer_id'],
        'orders' => ['created_at', 'status', 'table_id'],
        'products' => ['is_active', 'category_id'],
        'audit_log' => ['created_at', 'user_id'],
        'deliveries' => ['status', 'rider_id']
    ];
    
    foreach ($indexChecks as $table => $columns) {
        $indexes = $db->fetchAll("SHOW INDEX FROM `$table`");
        $existingIndexes = array_column($indexes, 'Column_name');
        
        foreach ($columns as $column) {
            if (!in_array($column, $existingIndexes)) {
                $missingIndexes[] = "$table.$column";
            }
        }
    }
    
    if (empty($missingIndexes)) {
        $optimizations[] = "✅ All recommended indexes are present";
        echo "<p class='text-success'>✅ All recommended indexes are present</p>";
    } else {
        echo "<p class='text-warning'>⚠️ Missing indexes: " . implode(', ', $missingIndexes) . "</p>";
        echo "<p class='text-info'>Consider adding these indexes for better performance</p>";
    }
} catch (Exception $e) {
    $errors[] = "❌ Index check failed: " . $e->getMessage();
    echo "<p class='text-danger'>❌ Index check failed: " . $e->getMessage() . "</p>";
}

// 7. Update system version to force cache refresh
echo "<h2>🔄 Updating System Version</h2>";
try {
    $currentVersion = trim(file_get_contents(ROOT_PATH . '/version.txt'));
    $versionParts = explode('.', $currentVersion);
    $versionParts[2] = (int)$versionParts[2] + 1; // Increment patch version
    $newVersion = implode('.', $versionParts);
    
    file_put_contents(ROOT_PATH . '/version.txt', $newVersion);
    $optimizations[] = "✅ System version updated to $newVersion";
    echo "<p class='text-success'>✅ System version updated to $newVersion</p>";
} catch (Exception $e) {
    $errors[] = "❌ Version update failed: " . $e->getMessage();
    echo "<p class='text-danger'>❌ Version update failed: " . $e->getMessage() . "</p>";
}

// 8. Performance recommendations
echo "<h2>💡 Performance Recommendations</h2>";
$recommendations = [
    "Enable PHP OPcache for better performance",
    "Set up MySQL query cache if not already enabled",
    "Consider using Redis for session storage in production",
    "Implement CDN for static assets in production",
    "Enable gzip compression on web server",
    "Set proper cache headers for static assets"
];

foreach ($recommendations as $recommendation) {
    echo "<p class='text-info'>💡 $recommendation</p>";
}

// Summary
echo "<hr><h2>📋 Optimization Summary</h2>";

if (!empty($optimizations)) {
    echo "<h3 class='text-success'>✅ Successful Optimizations:</h3>";
    echo "<ul>";
    foreach ($optimizations as $optimization) {
        echo "<li>$optimization</li>";
    }
    echo "</ul>";
}

if (!empty($errors)) {
    echo "<h3 class='text-danger'>❌ Errors:</h3>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
}

// Performance stats
$dbStats = $db->getStats();
echo "<h3>📊 Current Performance Stats:</h3>";
echo "<ul>";
echo "<li>Database queries executed: " . $dbStats['query_count'] . "</li>";
echo "<li>Query cache size: " . $dbStats['cache_size'] . "</li>";
echo "<li>Query caching: " . ($dbStats['cache_enabled'] ? 'Enabled' : 'Disabled') . "</li>";
echo "<li>Memory usage: " . number_format(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB</li>";
echo "</ul>";

echo "<hr>";
echo "<p class='text-success'><strong>🎉 System optimization complete!</strong></p>";
echo "<p>Your WAPOS system should now run faster and more efficiently.</p>";
echo "<p><a href='index.php' class='btn btn-primary'>Return to Dashboard</a></p>";

// Log optimization
error_log("WAPOS System Optimization completed. Optimizations: " . count($optimizations) . ", Errors: " . count($errors));
?>
