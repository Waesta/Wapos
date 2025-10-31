<?php
// Force no caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

require_once 'includes/bootstrap.php';
$auth->requireRole('admin');

$db = Database::getInstance()->getConnection();

$pageTitle = 'System Health Check';
include 'includes/header.php';

// Get ALL tables that actually exist in the database
$allTables = [];
$result = $db->query("SHOW TABLES");
while ($row = $result->fetch(PDO::FETCH_NUM)) {
    $allTables[] = $row[0];
}

// Check which essential tables exist
$essentialTables = [
    'users',
    'products',
    'sales',
    'settings',
    'suppliers',
    'accounts',
    'journal_entries'
];

$tableStatus = [];
$allGood = true;

foreach ($essentialTables as $table) {
    $exists = in_array($table, $allTables);
    $tableStatus[$table] = $exists;
    if (!$exists) {
        $allGood = false;
    }
}

// Check database connection
$dbConnected = true;
try {
    $db->query("SELECT 1");
} catch (Exception $e) {
    $dbConnected = false;
    $allGood = false;
}

// Check files
$files = [
    'includes/bootstrap.php',
    'includes/Database.php',
    'includes/Auth.php'
];

$fileStatus = [];
foreach ($files as $file) {
    $exists = file_exists($file);
    $fileStatus[$file] = $exists;
    if (!$exists) {
        $allGood = false;
    }
}
?>

<style>
.health-good { color: #28a745; font-weight: bold; }
.health-bad { color: #dc3545; font-weight: bold; }
.table-list { font-family: monospace; }
</style>

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-<?= $allGood ? 'success' : 'danger' ?> text-white">
                <h4 class="mb-0">
                    <?= $allGood ? '✓ System Health: GOOD' : '✗ System Health: ISSUES DETECTED' ?>
                </h4>
            </div>
            <div class="card-body">
                <p class="mb-0">
                    <?= $allGood ? 'All systems operational!' : 'Some components need attention.' ?>
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Database Connection -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Database Connection</h5>
            </div>
            <div class="card-body">
                <div class="<?= $dbConnected ? 'health-good' : 'health-bad' ?>">
                    <?= $dbConnected ? '✓ Connected' : '✗ Failed' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Essential Tables -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Essential Tables</h5>
            </div>
            <div class="card-body">
                <div class="table-list">
                    <?php foreach ($essentialTables as $table): ?>
                        <div class="mb-2">
                            <span class="<?= $tableStatus[$table] ? 'health-good' : 'health-bad' ?>">
                                <?= $tableStatus[$table] ? '✓' : '✗' ?>
                            </span>
                            <code><?= $table ?></code>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Core Files -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Core Files</h5>
            </div>
            <div class="card-body">
                <div class="table-list">
                    <?php foreach ($files as $file): ?>
                        <div class="mb-2">
                            <span class="<?= $fileStatus[$file] ? 'health-good' : 'health-bad' ?>">
                                <?= $fileStatus[$file] ? '✓' : '✗' ?>
                            </span>
                            <code><?= $file ?></code>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- All Tables in Database -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">All Tables (<?= count($allTables) ?>)</h5>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <div class="table-list">
                    <?php foreach ($allTables as $table): ?>
                        <div class="mb-1">
                            <span class="health-good">✓</span>
                            <code><?= $table ?></code>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
    <button onclick="location.reload()" class="btn btn-primary">Refresh</button>
</div>

<?php include 'includes/footer.php'; ?>
