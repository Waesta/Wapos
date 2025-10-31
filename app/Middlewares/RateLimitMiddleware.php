<?php

namespace App\Middlewares;

/**
 * Rate Limiting Middleware
 * Prevents brute force attacks and API abuse
 */
class RateLimitMiddleware
{
    private const CACHE_DIR = __DIR__ . '/../../cache/ratelimit';
    private const LOGIN_ATTEMPTS_LIMIT = 5;
    private const LOGIN_LOCKOUT_MINUTES = 15;
    private const API_REQUESTS_LIMIT = 100;
    private const API_WINDOW_MINUTES = 1;

    /**
     * Check login rate limit
     */
    public static function checkLoginAttempts(string $identifier): bool
    {
        self::ensureCacheDir();
        
        $key = 'login_' . md5($identifier);
        $file = self::CACHE_DIR . '/' . $key;
        
        $attempts = 0;
        $firstAttempt = time();
        
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            $attempts = $data['attempts'] ?? 0;
            $firstAttempt = $data['first_attempt'] ?? time();
            
            // Reset if window expired
            if (time() - $firstAttempt > (self::LOGIN_LOCKOUT_MINUTES * 60)) {
                $attempts = 0;
                $firstAttempt = time();
            }
        }
        
        if ($attempts >= self::LOGIN_ATTEMPTS_LIMIT) {
            return false;
        }
        
        return true;
    }

    /**
     * Record login attempt
     */
    public static function recordLoginAttempt(string $identifier): void
    {
        self::ensureCacheDir();
        
        $key = 'login_' . md5($identifier);
        $file = self::CACHE_DIR . '/' . $key;
        
        $attempts = 1;
        $firstAttempt = time();
        
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            $attempts = ($data['attempts'] ?? 0) + 1;
            $firstAttempt = $data['first_attempt'] ?? time();
            
            // Reset if window expired
            if (time() - $firstAttempt > (self::LOGIN_LOCKOUT_MINUTES * 60)) {
                $attempts = 1;
                $firstAttempt = time();
            }
        }
        
        file_put_contents($file, json_encode([
            'attempts' => $attempts,
            'first_attempt' => $firstAttempt,
            'last_attempt' => time()
        ]));
    }

    /**
     * Clear login attempts (on successful login)
     */
    public static function clearLoginAttempts(string $identifier): void
    {
        $key = 'login_' . md5($identifier);
        $file = self::CACHE_DIR . '/' . $key;
        
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Check API rate limit
     */
    public static function checkApiLimit(string $identifier): bool
    {
        self::ensureCacheDir();
        
        $key = 'api_' . md5($identifier);
        $file = self::CACHE_DIR . '/' . $key;
        
        $requests = 0;
        $windowStart = time();
        
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            $requests = $data['requests'] ?? 0;
            $windowStart = $data['window_start'] ?? time();
            
            // Reset if window expired
            if (time() - $windowStart > (self::API_WINDOW_MINUTES * 60)) {
                $requests = 0;
                $windowStart = time();
            }
        }
        
        if ($requests >= self::API_REQUESTS_LIMIT) {
            return false;
        }
        
        // Record request
        file_put_contents($file, json_encode([
            'requests' => $requests + 1,
            'window_start' => $windowStart,
            'last_request' => time()
        ]));
        
        return true;
    }

    /**
     * Get remaining attempts for login
     */
    public static function getRemainingLoginAttempts(string $identifier): int
    {
        $key = 'login_' . md5($identifier);
        $file = self::CACHE_DIR . '/' . $key;
        
        if (!file_exists($file)) {
            return self::LOGIN_ATTEMPTS_LIMIT;
        }
        
        $data = json_decode(file_get_contents($file), true);
        $attempts = $data['attempts'] ?? 0;
        
        return max(0, self::LOGIN_ATTEMPTS_LIMIT - $attempts);
    }

    /**
     * Ensure cache directory exists
     */
    private static function ensureCacheDir(): void
    {
        if (!is_dir(self::CACHE_DIR)) {
            mkdir(self::CACHE_DIR, 0755, true);
        }
    }

    /**
     * Cleanup old cache files
     */
    public static function cleanup(): void
    {
        if (!is_dir(self::CACHE_DIR)) {
            return;
        }
        
        $files = glob(self::CACHE_DIR . '/*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > 3600) {
                unlink($file);
            }
        }
    }
}
