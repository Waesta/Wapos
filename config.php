<?php
/**
 * WAPOS - Waesta Point of Sale System
 * Configuration File
 */

// Database Configuration
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'wapos');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('APP_NAME', 'WAPOS');
define('APP_URL', 'http://localhost/wapos');
define('TIMEZONE', 'Africa/Nairobi');

// Currency Settings (Configure for your region)
define('CURRENCY_CODE', 'USD');        // ISO currency code (USD, EUR, GBP, KES, etc.)
define('CURRENCY_SYMBOL', '$');        // Currency symbol to display
define('CURRENCY_POSITION', 'before'); // 'before' or 'after' the amount
define('DECIMAL_SEPARATOR', '.');      // Decimal separator (. or ,)
define('THOUSANDS_SEPARATOR', ',');    // Thousands separator (, or . or space)

// Session Settings
define('SESSION_LIFETIME', 7200); // 2 hours
define('SESSION_NAME', 'wapos_session');

// Security
define('HASH_ALGO', PASSWORD_ARGON2ID);
define('HASH_OPTIONS', ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]);

// Paths
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('LOG_PATH', ROOT_PATH . '/logs');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error Reporting (set to 0 for production)
// PRODUCTION: error_reporting(0); ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', LOG_PATH . '/php_errors.log');
