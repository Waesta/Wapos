<?php
/**
 * WAPOS Bootstrap - Clean Version
 */

// Define ROOT_PATH if not already defined
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// Start session and error handling
// Security: set cookie flags BEFORE session_start (Auth will start session)
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
error_reporting(E_ALL);
ini_set("display_errors", 1);

// Load configuration
require_once ROOT_PATH . '/config.php';

// Load currency helper functions
require_once ROOT_PATH . '/includes/currency-helper.php';

// Load core classes
require_once __DIR__ . "/Database.php";
require_once __DIR__ . "/Auth.php";
require_once __DIR__ . "/currency-config.php";
require_once __DIR__ . "/accounting-helpers.php";

// Autoload application namespaces if vendor autoloader exists
$composerAutoload = ROOT_PATH . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// Fallback autoloader for App namespace if composer autoload not available
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = ROOT_PATH . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

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

function formatMoney($amount, $showCurrency = false) {
    return formatCurrency($amount, $showCurrency);
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

function formatDate($date, $format = 'Y-m-d H:i:s') {
    if (empty($date)) {
        return '';
    }
    
    try {
        $dateObj = new DateTime($date);
        return $dateObj->format($format);
    } catch (Exception $e) {
        return $date; // Return original if formatting fails
    }
}

function generateSaleNumber() {
    return 'SALE-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function showAlert($message, $type = 'info') {
    $alertClass = [
        'success' => 'alert-success',
        'error' => 'alert-danger',
        'warning' => 'alert-warning',
        'info' => 'alert-info'
    ];
    
    $class = $alertClass[$type] ?? $alertClass['info'];
    
    echo '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">';
    echo htmlspecialchars($message);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    echo '</div>';
}
?>