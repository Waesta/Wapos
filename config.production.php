<?php
/**
 * WAPOS - Production Configuration Template
 * 
 * INSTRUCTIONS:
 * 1. Copy this file to config.php on your production server
 * 2. Update all values marked with [CHANGE_ME]
 * 3. Ensure file permissions are set to 640 (owner read/write, group read)
 * 4. Never commit this file with real credentials to version control
 */

// ============================================
// DATABASE CONFIGURATION
// ============================================
define('DB_HOST', '127.0.0.1');                    // Database host (use IP for better performance)
define('DB_NAME', 'wapos_production');             // [CHANGE_ME] Production database name
define('DB_USER', 'wapos_user');                   // [CHANGE_ME] Database user (never use root!)
define('DB_PASS', 'STRONG_PASSWORD_HERE');         // [CHANGE_ME] Strong password (32+ chars recommended)
define('DB_CHARSET', 'utf8mb4');

// ============================================
// APPLICATION SETTINGS
// ============================================
define('APP_NAME', 'WAPOS');
define('APP_URL', 'https://your-domain.com/wapos'); // [CHANGE_ME] Your production URL (HTTPS required!)
define('TIMEZONE', 'Africa/Nairobi');               // [CHANGE_ME] Your timezone

// ============================================
// CURRENCY SETTINGS
// ============================================
define('CURRENCY_CODE', 'USD');        // [CHANGE_ME] ISO currency code
define('CURRENCY_SYMBOL', '$');        // [CHANGE_ME] Currency symbol
define('CURRENCY_POSITION', 'before'); // 'before' or 'after' the amount
define('DECIMAL_SEPARATOR', '.');
define('THOUSANDS_SEPARATOR', ',');

// ============================================
// SESSION SETTINGS (Security Hardened)
// ============================================
define('SESSION_LIFETIME', 3600);      // 1 hour (shorter for production)
define('SESSION_NAME', 'wapos_sess');  // Unique session name

// ============================================
// SECURITY SETTINGS
// ============================================
define('HASH_ALGO', PASSWORD_ARGON2ID);
define('HASH_OPTIONS', [
    'memory_cost' => 65536,  // 64MB
    'time_cost' => 4,
    'threads' => 1
]);

// CSRF Token Lifetime (seconds)
define('CSRF_TOKEN_LIFETIME', 3600);

// API Rate Limiting
define('API_RATE_LIMIT_ENABLED', true);
define('API_RATE_LIMIT_REQUESTS', 60);
define('API_RATE_LIMIT_WINDOW', 60);

// ============================================
// PATHS
// ============================================
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('LOG_PATH', ROOT_PATH . '/logs');
define('CACHE_PATH', ROOT_PATH . '/cache');
define('BACKUP_PATH', ROOT_PATH . '/backups');

// ============================================
// TIMEZONE
// ============================================
date_default_timezone_set(TIMEZONE);

// ============================================
// ERROR HANDLING (Production Settings)
// ============================================
error_reporting(0);                    // No error display in production
ini_set('display_errors', 0);          // Never show errors to users
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);              // Log all errors
ini_set('error_log', LOG_PATH . '/php_errors.log');

// ============================================
// PERFORMANCE SETTINGS
// ============================================
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 60);
ini_set('max_input_time', 60);

// ============================================
// EMAIL SETTINGS (Optional)
// ============================================
define('MAIL_HOST', 'smtp.your-provider.com');     // [CHANGE_ME]
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'your-email@domain.com');  // [CHANGE_ME]
define('MAIL_PASSWORD', 'email_password');         // [CHANGE_ME]
define('MAIL_FROM_ADDRESS', 'noreply@domain.com'); // [CHANGE_ME]
define('MAIL_FROM_NAME', APP_NAME);

// ============================================
// BACKUP SETTINGS
// ============================================
define('BACKUP_RETENTION_DAYS', 30);
define('BACKUP_ENCRYPTION_KEY', 'GENERATE_32_CHAR_KEY'); // [CHANGE_ME] openssl rand -hex 16

// ============================================
// EXTERNAL SERVICES (Optional)
// ============================================
// WhatsApp Integration
define('WHATSAPP_API_URL', '');        // [CHANGE_ME] If using WhatsApp
define('WHATSAPP_API_TOKEN', '');      // [CHANGE_ME]

// Payment Gateway
define('PAYMENT_GATEWAY_MODE', 'live'); // 'sandbox' or 'live'
define('PAYMENT_API_KEY', '');          // [CHANGE_ME]
define('PAYMENT_SECRET_KEY', '');       // [CHANGE_ME]

// ============================================
// FEATURE FLAGS
// ============================================
define('FEATURE_2FA_ENABLED', true);
define('FEATURE_AUDIT_LOG', true);
define('FEATURE_API_VERSIONING', true);

// ============================================
// SECURITY HEADERS (Applied in .htaccess too)
// ============================================
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
