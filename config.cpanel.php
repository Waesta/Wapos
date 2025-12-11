<?php
/**
 * WAPOS - Waesta Point of Sale System
 * Production Configuration for cPanel
 * 
 * Upload this file to cPanel and rename it to config.php
 */

// Database Configuration - UPDATE THESE WITH YOUR CPANEL DETAILS
define('DB_HOST', 'localhost');
define('DB_NAME', 'zxgivemy_wapos');           // Change to your cPanel database name
define('DB_USER', 'zxgivemy_waposuser');       // Change to your cPanel database user
define('DB_PASS', 'YOUR_DATABASE_PASSWORD');   // Change to your database password
define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('APP_NAME', 'WAPOS');
define('APP_URL', 'https://wapos.scholarza.com');
define('TIMEZONE', 'Africa/Nairobi');

// Currency Settings (Configure in Settings page or set defaults here)
if (!defined('CURRENCY_CODE')) define('CURRENCY_CODE', '');
if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', '');
if (!defined('CURRENCY_POSITION')) define('CURRENCY_POSITION', 'before');
if (!defined('DECIMAL_SEPARATOR')) define('DECIMAL_SEPARATOR', '.');
if (!defined('THOUSANDS_SEPARATOR')) define('THOUSANDS_SEPARATOR', ',');

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

// Error Reporting - PRODUCTION (errors logged, not displayed)
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', LOG_PATH . '/php_errors.log');
