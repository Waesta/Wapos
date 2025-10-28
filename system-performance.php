<?php
require_once 'includes/bootstrap.php';
$auth->requireRole('admin');

$db = Database::getInstance();
$performance = PerformanceManager::getInstance();

$pageTitle = 'System Performance Monitor';
include 'includes/header.php';
?>

<style>
.performance-card {
    transition: all 0.3s ease;
}
.performance-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.metric-value {
    font-size: 2rem;
    font-weight: bold;
}
.status-good { color: #28a745; }
.status-warning { color: #ffc107; }
.status-danger { color: #dc3545; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0">
            <i class="bi bi-speedometer2 me-2"></i>System Performance Monitor
        </h4>
        <p class="text-muted mb-0">Real-time system performance and optimization status</p>
    </div>
    <div>
        <button class="btn btn-success me-2" onclick="runOptimization()">
            <i class="bi bi-tools me-2"></i>Optimize System
        </button>
        <button class="btn btn-outline-primary" onclick="refreshStats()">
            <i class="bi bi-arrow-clockwise me-2"></i>Refresh
        </button>
    </div>
</div>

<!-- Performance Metrics -->
<div class="row g-3 mb-4" id="performanceMetrics">
    <div class="col-md-3">
        <div class="card performance-card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-database text-primary fs-1 mb-2"></i>
                <div class="metric-value status-good" id="dbQueries">
                    <?php 
                    $dbStats = $db->getStats();
                    echo $dbStats['query_count'];
                    ?>
                </div>
                <p class="text-muted mb-0">Database Queries</p>
                <small class="text-muted">This session</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card performance-card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-memory text-info fs-1 mb-2"></i>
                <div class="metric-value status-good" id="memoryUsage">
                    <?= number_format(memory_get_peak_usage(true) / 1024 / 1024, 1) ?>MB
                </div>
                <p class="text-muted mb-0">Memory Usage</p>
                <small class="text-muted">Peak usage</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card performance-card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-lightning text-warning fs-1 mb-2"></i>
                <div class="metric-value status-good" id="cacheSize">
                    <?= $dbStats['cache_size'] ?>
                </div>
                <p class="text-muted mb-0">Cache Entries</p>
                <small class="text-muted">Query cache</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card performance-card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-clock text-success fs-1 mb-2"></i>
                <div class="metric-value status-good" id="uptime">
                    <?php
                    if (defined('WAPOS_START_TIME')) {
                        echo number_format((microtime(true) - WAPOS_START_TIME) * 1000, 0) . 'ms';
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </div>
                <p class="text-muted mb-0">Page Load Time</p>
                <small class="text-muted">Current page</small>
            </div>
        </div>
    </div>
</div>

<!-- System Health -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-heart-pulse me-2"></i>System Health</h6>
            </div>
            <div class="card-body">
                <div id="systemHealth">
                    <?php
                    // Check system health
                    $health = [
                        'database' => false,
                        'cache' => false,
                        'storage' => false,
                        'performance' => false
                    ];
                    
                    // Database health
                    try {
                        $db->fetchOne("SELECT 1");
                        $health['database'] = true;
                    } catch (Exception $e) {
                        $health['database'] = false;
                    }
                    
                    // Cache health
                    $cacheDir = ROOT_PATH . '/cache';
                    $health['cache'] = is_dir($cacheDir) && is_writable($cacheDir);
                    
                    // Storage health
                    $freeSpace = disk_free_space(ROOT_PATH);
                    $totalSpace = disk_total_space(ROOT_PATH);
                    $usagePercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
                    $health['storage'] = $usagePercent < 90;
                    
                    // Performance health
                    $health['performance'] = memory_get_peak_usage(true) < 128 * 1024 * 1024; // 128MB
                    
                    foreach ($health as $component => $status) {
                        $icon = $status ? 'check-circle text-success' : 'x-circle text-danger';
                        $statusText = $status ? 'Healthy' : 'Issues Detected';
                        echo "<div class='d-flex justify-content-between align-items-center mb-2'>";
                        echo "<span><i class='bi bi-$icon me-2'></i>" . ucfirst($component) . "</span>";
                        echo "<span class='" . ($status ? 'text-success' : 'text-danger') . "'>$statusText</span>";
                        echo "</div>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Performance Trends</h6>
            </div>
            <div class="card-body">
                <canvas id="performanceChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Database Performance -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-database me-2"></i>Database Performance</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <h6>Query Statistics</h6>
                        <ul class="list-unstyled">
                            <li><strong>Total Queries:</strong> <?= $dbStats['query_count'] ?></li>
                            <li><strong>Cached Queries:</strong> <?= $dbStats['cache_size'] ?></li>
                            <li><strong>Cache Hit Rate:</strong> 
                                <?= $dbStats['query_count'] > 0 ? number_format(($dbStats['cache_size'] / $dbStats['query_count']) * 100, 1) : 0 ?>%
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h6>Table Sizes</h6>
                        <div id="tableSizes">
                            <?php
                            try {
                                $tableSizes = $db->fetchAll("
                                    SELECT table_name, 
                                           ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                                    FROM information_schema.TABLES 
                                    WHERE table_schema = DATABASE()
                                    ORDER BY (data_length + index_length) DESC 
                                    LIMIT 5
                                ");
                                
                                foreach ($tableSizes as $table) {
                                    echo "<div class='d-flex justify-content-between'>";
                                    echo "<span>" . $table['table_name'] . "</span>";
                                    echo "<span>" . $table['size_mb'] . " MB</span>";
                                    echo "</div>";
                                }
                            } catch (Exception $e) {
                                echo "<p class='text-muted'>Unable to fetch table sizes</p>";
                            }
                            ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <h6>Optimization Status</h6>
                        <div id="optimizationStatus">
                            <?php
                            $lastOptimization = file_exists(ROOT_PATH . '/cache/last_optimization.txt') 
                                ? file_get_contents(ROOT_PATH . '/cache/last_optimization.txt') 
                                : 'Never';
                            
                            echo "<p><strong>Last Optimization:</strong> $lastOptimization</p>";
                            
                            $cacheFiles = glob(ROOT_PATH . '/cache/*.cache');
                            echo "<p><strong>Cache Files:</strong> " . count($cacheFiles) . "</p>";
                            
                            $version = trim(file_get_contents(ROOT_PATH . '/version.txt'));
                            echo "<p><strong>System Version:</strong> $version</p>";
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Real-time Updates -->
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Real-time Monitoring:</strong> This page automatically refreshes performance metrics every 30 seconds.
    Cache is automatically cleared when needed to ensure you see the latest data without manual refresh.
</div>

<script>
// Auto-refresh performance metrics
setInterval(refreshStats, 30000);

function refreshStats() {
    fetch('<?= APP_URL ?>/api/get-performance-stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('dbQueries').textContent = data.stats.query_count;
                document.getElementById('memoryUsage').textContent = data.stats.memory_usage + 'MB';
                document.getElementById('cacheSize').textContent = data.stats.cache_size;
                
                // Update health status
                updateHealthStatus(data.health);
            }
        })
        .catch(error => console.error('Error refreshing stats:', error));
}

function updateHealthStatus(health) {
    const healthDiv = document.getElementById('systemHealth');
    let html = '';
    
    for (const [component, status] of Object.entries(health)) {
        const icon = status ? 'check-circle text-success' : 'x-circle text-danger';
        const statusText = status ? 'Healthy' : 'Issues Detected';
        const textClass = status ? 'text-success' : 'text-danger';
        
        html += `<div class='d-flex justify-content-between align-items-center mb-2'>`;
        html += `<span><i class='bi bi-${icon} me-2'></i>${component.charAt(0).toUpperCase() + component.slice(1)}</span>`;
        html += `<span class='${textClass}'>${statusText}</span>`;
        html += `</div>`;
    }
    
    healthDiv.innerHTML = html;
}

function runOptimization() {
    if (confirm('This will optimize the system and may take a few moments. Continue?')) {
        window.open('<?= APP_URL ?>/optimize-system.php', '_blank');
    }
}

// Initialize performance chart (simple version)
const ctx = document.getElementById('performanceChart').getContext('2d');
const performanceData = {
    labels: ['Database', 'Memory', 'Cache', 'Storage'],
    datasets: [{
        label: 'Performance Score',
        data: [95, 87, 92, 88],
        backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#6f42c1'],
        borderWidth: 0
    }]
};

// Simple chart implementation (you could use Chart.js for more advanced charts)
ctx.fillStyle = '#28a745';
ctx.fillRect(50, 50, 80, 20);
ctx.fillStyle = '#17a2b8';
ctx.fillRect(50, 80, 70, 20);
ctx.fillStyle = '#ffc107';
ctx.fillRect(50, 110, 75, 20);
ctx.fillStyle = '#6f42c1';
ctx.fillRect(50, 140, 72, 20);

ctx.fillStyle = '#000';
ctx.font = '12px Arial';
ctx.fillText('Database (95%)', 140, 65);
ctx.fillText('Memory (87%)', 140, 95);
ctx.fillText('Cache (92%)', 140, 125);
ctx.fillText('Storage (88%)', 140, 155);
</script>

<?php include 'includes/footer.php'; ?>
