<?php
/**
 * Bootstrap File
 * Include this file at the start of every page
 */

// Load configuration
require_once __DIR__ . '/../config.php';

// Load core classes
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/SystemManager.php';
require_once __DIR__ . '/Auth.php';

// Helper Functions
function redirect($url) {
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    }
    echo '<script>window.location.href="' . $url . '";</script>';
    exit;
}

function formatMoney($amount, $showCurrency = true) {
    global $db;
    static $currency = null;
    
    if ($currency === null) {
        try {
            $db = Database::getInstance();
            $setting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'currency'");
            $currency = $setting['setting_value'] ?? '$';
        } catch (Exception $e) {
            $currency = '$';
        }
    }
    
    $formatted = number_format($amount, 2);
    return $showCurrency ? $currency . ' ' . $formatted : $formatted;
}

function formatDate($date, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($date));
}

function generateSaleNumber() {
    return 'SALE-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
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

// Initialize System Manager (lightweight)
$systemManager = SystemManager::getInstance();

// Initialize Auth
$auth = new Auth();

// Set error handler
function errorHandler($errno, $errstr, $errfile, $errline) {
    $message = "Error [{$errno}]: {$errstr} in {$errfile} on line {$errline}";
    error_log($message);
    
    if (error_reporting() & $errno) {
        echo "<div class='alert alert-danger'><strong>System Error:</strong> " . htmlspecialchars($errstr) . "</div>";
    }
}

set_error_handler('errorHandler');

// System initialization is now handled by SystemManager
// This ensures consistent data without blocking page loads
