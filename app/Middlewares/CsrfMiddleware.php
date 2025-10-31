<?php

namespace App\Middlewares;

/**
 * CSRF Protection Middleware
 * Validates CSRF tokens on all state-changing requests
 */
class CsrfMiddleware
{
    private const TOKEN_NAME = 'csrf_token';
    private const HEADER_NAME = 'X-CSRF-Token';

    /**
     * Generate CSRF token
     */
    public static function generateToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[self::TOKEN_NAME])) {
            $_SESSION[self::TOKEN_NAME] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::TOKEN_NAME];
    }

    /**
     * Get current CSRF token
     */
    public static function getToken(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return $_SESSION[self::TOKEN_NAME] ?? null;
    }

    /**
     * Validate CSRF token
     */
    public static function validate(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Only validate on state-changing methods
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return true;
        }

        $token = null;

        // Check header first (for AJAX requests)
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        // Then check POST data
        elseif (isset($_POST[self::TOKEN_NAME])) {
            $token = $_POST[self::TOKEN_NAME];
        }
        // Then check JSON body
        elseif ($_SERVER['CONTENT_TYPE'] === 'application/json') {
            $input = json_decode(file_get_contents('php://input'), true);
            $token = $input[self::TOKEN_NAME] ?? null;
        }

        $sessionToken = self::getToken();

        if (!$token || !$sessionToken) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Require valid CSRF token or die
     */
    public static function require(): void
    {
        if (!self::validate()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Invalid or missing CSRF token'
            ]);
            exit;
        }
    }

    /**
     * Get token as meta tag HTML
     */
    public static function metaTag(): string
    {
        $token = self::generateToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
    }

    /**
     * Get token as hidden input HTML
     */
    public static function inputField(): string
    {
        $token = self::generateToken();
        return '<input type="hidden" name="' . self::TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
    }
}
