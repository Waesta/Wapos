<?php
/**
 * WAPOS System Restoration Script
 * Comprehensive system diagnosis and repair
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '256M');

echo "<!DOCTYPE html><html><head><title>WAPOS System Restore</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:#28a745;} .error{color:#dc3545;} .warning{color:#ffc107;} .info{color:#17a2b8;}</style>";
echo "</head><body>";

echo "<h1>🔧 WAPOS System Restoration</h1>";
echo "<p>Performing comprehensive system diagnosis and repair...</p>";

$fixes = [];
$errors = [];
$warnings = [];

// Step 1: Basic System Check
echo "<h2>Step 1: Basic System Check</h2>";
try {
    echo "<p class='success'>✅ PHP Version: " . phpversion() . "</p>";
    echo "<p class='success'>✅ Memory Limit: " . ini_get('memory_limit') . "</p>";
    echo "<p class='success'>✅ Max Execution Time: " . ini_get('max_execution_time') . "s</p>";
    $fixes[] = "Basic PHP environment verified";
} catch (Exception $e) {
    $errors[] = "Basic system check failed: " . $e->getMessage();
    echo "<p class='error'>❌ Basic system check failed</p>";
}

// Step 2: Database Connection
echo "<h2>Step 2: Database Connection Test</h2>";
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=wapos;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $result = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch();
    echo "<p class='success'>✅ Database connected successfully</p>";
    echo "<p class='success'>✅ Found " . $result['count'] . " users</p>";
    
    // Test critical tables
    $tables = ['users', 'products', 'sales', 'orders', 'settings'];
    foreach ($tables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) as count FROM $table")->fetch();
            echo "<p class='success'>✅ Table '$table': " . $count['count'] . " records</p>";
        } catch (Exception $e) {
            echo "<p class='error'>❌ Table '$table' error: " . $e->getMessage() . "</p>";
            $errors[] = "Table $table has issues";
        }
    }
    
    $fixes[] = "Database connection and tables verified";
} catch (Exception $e) {
    $errors[] = "Database connection failed: " . $e->getMessage();
    echo "<p class='error'>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

// Step 3: File System Check
echo "<h2>Step 3: File System Check</h2>";
$criticalFiles = [
    'config.php' => 'Configuration file',
    'includes/Database.php' => 'Database class',
    'includes/Auth.php' => 'Authentication class',
    'includes/bootstrap.php' => 'Bootstrap file',
    'login.php' => 'Login page',
    'index.php' => 'Dashboard',
    'pos.php' => 'POS system'
];

foreach ($criticalFiles as $file => $description) {
    if (file_exists($file)) {
        echo "<p class='success'>✅ $description ($file)</p>";
    } else {
        echo "<p class='error'>❌ Missing: $description ($file)</p>";
        $errors[] = "Missing file: $file";
    }
}

if (empty($errors)) {
    $fixes[] = "All critical files present";
}

// Step 4: Clean Bootstrap File
echo "<h2>Step 4: Restoring Clean Bootstrap</h2>";
$cleanBootstrap = '<?php
/**
 * WAPOS Bootstrap - Clean Version
 */

// Start session and error handling
session_start();
error_reporting(E_ALL);
ini_set("display_errors", 1);

// Load configuration
require_once __DIR__ . "/../config.php";

// Load core classes
require_once __DIR__ . "/Database.php";
require_once __DIR__ . "/Auth.php";

// Initialize core instances
try {
    $db = Database::getInstance();
    $auth = new Auth();
} catch (Exception $e) {
    die("System initialization failed: " . $e->getMessage());
}

// Helper Functions
function redirect($url) {
    if (!headers_sent()) {
        header("Location: " . $url);
        exit;
    }
    echo "<script>window.location.href=\"" . $url . "\";</script>";
    exit;
}

function formatMoney($amount, $showCurrency = true) {
    global $db;
    static $currency = null;
    
    if ($currency === null) {
        try {
            $setting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = \"currency\"");
            $currency = $setting["setting_value"] ?? "$";
        } catch (Exception $e) {
            $currency = "$";
        }
    }
    
    $formatted = number_format($amount, 2);
    return $showCurrency ? $currency . $formatted : $formatted;
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, "UTF-8");
}

function generateCSRFToken() {
    if (!isset($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function validateCSRFToken($token) {
    return isset($_SESSION["csrf_token"]) && hash_equals($_SESSION["csrf_token"], $token);
}
?>';

file_put_contents('includes/bootstrap.php', $cleanBootstrap);
echo "<p class='success'>✅ Clean bootstrap file restored</p>";
$fixes[] = "Bootstrap file cleaned and restored";

// Step 5: Clean Header File
echo "<h2>Step 5: Restoring Clean Header</h2>";
$cleanHeader = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . " - " : "" ?><?= APP_NAME ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="<?= APP_URL ?>/manifest.json">
    <meta name="theme-color" content="#2c3e50">
    
    <style>
        :root {
            --sidebar-width: 250px;
        }
        body {
            font-size: 0.9rem;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 0;
            overflow-y: auto;
            z-index: 1000;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            background: #f8f9fa;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 0;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .top-bar {
            background: white;
            padding: 15px 30px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>';

if (isset($auth) && $auth->isLoggedIn()) {
    $cleanHeader .= '
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-3 border-bottom">
            <h5 class="mb-0"><i class="bi bi-shop me-2"></i>' . APP_NAME . '</h5>
            <small class="text-light opacity-75">Point of Sale System</small>
        </div>
        
        <nav class="nav flex-column">
            <a class="nav-link' . (basename($_SERVER['PHP_SELF']) == 'index.php' ? ' active' : '') . '" href="index.php">
                <i class="bi bi-speedometer2 me-2"></i>Dashboard
            </a>
            <a class="nav-link' . (basename($_SERVER['PHP_SELF']) == 'pos.php' ? ' active' : '') . '" href="pos.php">
                <i class="bi bi-cash-register me-2"></i>POS System
            </a>
            <a class="nav-link' . (basename($_SERVER['PHP_SELF']) == 'restaurant.php' ? ' active' : '') . '" href="restaurant.php">
                <i class="bi bi-cup-hot me-2"></i>Restaurant
            </a>
            <a class="nav-link' . (basename($_SERVER['PHP_SELF']) == 'products.php' ? ' active' : '') . '" href="products.php">
                <i class="bi bi-box me-2"></i>Products
            </a>
            <a class="nav-link' . (basename($_SERVER['PHP_SELF']) == 'customers.php' ? ' active' : '') . '" href="customers.php">
                <i class="bi bi-people me-2"></i>Customers
            </a>
            <a class="nav-link' . (basename($_SERVER['PHP_SELF']) == 'sales.php' ? ' active' : '') . '" href="sales.php">
                <i class="bi bi-graph-up me-2"></i>Sales
            </a>
            <a class="nav-link' . (basename($_SERVER['PHP_SELF']) == 'reports.php' ? ' active' : '') . '" href="reports.php">
                <i class="bi bi-file-earmark-text me-2"></i>Reports
            </a>';
    
    if (isset($auth) && $auth->hasRole(['admin', 'manager'])) {
        $cleanHeader .= '
            <hr class="my-2 opacity-25">
            <a class="nav-link' . (basename($_SERVER['PHP_SELF']) == 'users.php' ? ' active' : '') . '" href="users.php">
                <i class="bi bi-person-gear me-2"></i>Users
            </a>
            <a class="nav-link' . (basename($_SERVER['PHP_SELF']) == 'settings.php' ? ' active' : '') . '" href="settings.php">
                <i class="bi bi-gear me-2"></i>Settings
            </a>';
    }
    
    $cleanHeader .= '
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <div>
                <h4 class="mb-0">' . (isset($pageTitle) ? $pageTitle : 'Dashboard') . '</h4>
                <small class="text-muted">' . date('l, F j, Y') . '</small>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-2"></i>' . ($auth->getUser()['username'] ?? 'User') . '
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
        <div class="p-4">';
}

file_put_contents('includes/header.php', $cleanHeader);
echo "<p class='success'>✅ Clean header file restored</p>";
$fixes[] = "Header file cleaned and restored";

// Step 6: Clean Footer File
echo "<h2>Step 6: Restoring Clean Footer</h2>";
$cleanFooter = '';
if (isset($auth) && $auth->isLoggedIn()) {
    $cleanFooter .= '
        </div>
    </div>';
}

$cleanFooter .= '
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Service Worker Registration -->
    <script>
        if ("serviceWorker" in navigator) {
            navigator.serviceWorker.register("service-worker.js")
                .then(registration => console.log("SW registered"))
                .catch(error => console.log("SW registration failed"));
        }
    </script>
</body>
</html>';

file_put_contents('includes/footer.php', $cleanFooter);
echo "<p class='success'>✅ Clean footer file restored</p>";
$fixes[] = "Footer file cleaned and restored";

// Step 7: Reset Admin Password
echo "<h2>Step 7: Resetting Admin Credentials</h2>";
try {
    $hashedPassword = password_hash('admin', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $stmt->execute([$hashedPassword]);
    
    if ($stmt->rowCount() > 0) {
        echo "<p class='success'>✅ Admin password reset to 'admin'</p>";
        $fixes[] = "Admin password reset";
    } else {
        // Create admin user if doesn't exist
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, is_active) VALUES (?, ?, 'admin', 1)");
        $stmt->execute(['admin', $hashedPassword]);
        echo "<p class='success'>✅ Admin user created with password 'admin'</p>";
        $fixes[] = "Admin user created";
    }
} catch (Exception $e) {
    $errors[] = "Admin password reset failed: " . $e->getMessage();
    echo "<p class='error'>❌ Admin password reset failed</p>";
}

// Step 8: Clear Cache and Temporary Files
echo "<h2>Step 8: Cleaning System Cache</h2>";
$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
    echo "<p class='success'>✅ Created cache directory</p>";
}

$files = glob($cacheDir . '/*');
$cleared = 0;
foreach ($files as $file) {
    if (is_file($file)) {
        unlink($file);
        $cleared++;
    }
}
echo "<p class='success'>✅ Cleared $cleared cache files</p>";
$fixes[] = "Cache cleaned";

// Step 9: System Version Reset
echo "<h2>Step 9: System Version Reset</h2>";
file_put_contents('version.txt', '2.2.0');
echo "<p class='success'>✅ System version set to 2.2.0</p>";
$fixes[] = "System version reset";

// Final Summary
echo "<hr><h2>🎯 System Restoration Summary</h2>";

if (!empty($fixes)) {
    echo "<h3 class='success'>✅ Successful Repairs (" . count($fixes) . "):</h3>";
    echo "<ul>";
    foreach ($fixes as $fix) {
        echo "<li class='success'>$fix</li>";
    }
    echo "</ul>";
}

if (!empty($errors)) {
    echo "<h3 class='error'>❌ Issues Found (" . count($errors) . "):</h3>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li class='error'>$error</li>";
    }
    echo "</ul>";
}

if (empty($errors)) {
    echo "<div style='background: #d4edda; color: #155724; padding: 20px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>🎉 System Restoration Complete!</h3>";
    echo "<p>Your WAPOS system has been fully restored and should now work properly.</p>";
    echo "<p><strong>Login Credentials:</strong></p>";
    echo "<ul><li>Username: <strong>admin</strong></li><li>Password: <strong>admin</strong></li></ul>";
    echo "</div>";
} else {
    echo "<div style='background: #fff3cd; color: #856404; padding: 20px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>⚠️ Partial Restoration</h3>";
    echo "<p>Some issues remain. Please review the errors above.</p>";
    echo "</div>";
}

echo "<hr>";
echo "<h2>🚀 Test Your System</h2>";
echo "<p><a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Test Login</a>";
echo "<a href='index.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Test Dashboard</a>";
echo "<a href='pos.php' style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test POS</a></p>";

echo "</body></html>";
?>
