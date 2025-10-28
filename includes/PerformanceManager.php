<?php
/**
 * WAPOS Performance Manager
 * Handles caching, optimization, and real-time updates
 */

class PerformanceManager {
    private static $instance = null;
    private $db;
    private $cacheDir;
    private $version;
    
    private function __construct() {
        $this->cacheDir = ROOT_PATH . '/cache';
        $this->version = $this->getSystemVersion();
        
        // Ensure cache directory exists
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        
        // Set proper cache headers
        $this->setCacheHeaders();
        
        // Initialize database connection later to avoid circular dependency
        $this->db = null;
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize database connection when needed
     */
    private function initDatabase() {
        if ($this->db === null) {
            try {
                $this->db = Database::getInstance();
            } catch (Exception $e) {
                // Database not available, continue without it
                error_log("PerformanceManager: Database not available - " . $e->getMessage());
            }
        }
    }
    
    /**
     * Set proper cache headers to prevent unwanted caching
     */
    private function setCacheHeaders() {
        // Only set cache headers for dynamic PHP pages, not for assets
        if (!headers_sent() && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '.php') !== false) {
            header('Cache-Control: no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        }
    }
    
    /**
     * Get system version for cache busting
     */
    private function getSystemVersion() {
        $versionFile = ROOT_PATH . '/version.txt';
        if (file_exists($versionFile)) {
            return trim(file_get_contents($versionFile));
        }
        return date('YmdHis'); // Fallback to timestamp
    }
    
    /**
     * Get versioned asset URL for cache busting
     */
    public function getAssetUrl($path) {
        return $path . '?v=' . $this->version;
    }
    
    /**
     * Cache database query results
     */
    public function cacheQuery($key, $query, $params = [], $ttl = 300) {
        $cacheKey = 'query_' . md5($key . serialize($params));
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.cache';
        
        // Check if cache exists and is valid
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
            return unserialize(file_get_contents($cacheFile));
        }
        
        // Execute query and cache result
        try {
            if (empty($params)) {
                $result = $this->db->fetchAll($query);
            } else {
                $result = $this->db->fetchAll($query, $params);
            }
            
            file_put_contents($cacheFile, serialize($result));
            return $result;
        } catch (Exception $e) {
            error_log("Query cache error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Invalidate cache by pattern
     */
    public function invalidateCache($pattern = '*') {
        $files = glob($this->cacheDir . '/' . $pattern . '.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
    
    /**
     * Get cached data or execute callback
     */
    public function remember($key, $callback, $ttl = 300) {
        $cacheFile = $this->cacheDir . '/' . md5($key) . '.cache';
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
            return unserialize(file_get_contents($cacheFile));
        }
        
        $result = $callback();
        file_put_contents($cacheFile, serialize($result));
        return $result;
    }
    
    /**
     * Optimize database queries
     */
    public function optimizeQuery($query) {
        // Add LIMIT if not present for large datasets
        if (stripos($query, 'LIMIT') === false && 
            (stripos($query, 'SELECT') !== false || stripos($query, 'UPDATE') !== false)) {
            // Add reasonable limits for common queries
            if (stripos($query, 'sales') !== false || stripos($query, 'orders') !== false) {
                $query .= ' LIMIT 1000';
            }
        }
        
        return $query;
    }
    
    /**
     * Compress output for faster delivery
     */
    public function enableCompression() {
        if (!headers_sent() && extension_loaded('zlib')) {
            ob_start('ob_gzhandler');
        }
    }
    
    /**
     * Minify HTML output
     */
    public function minifyHtml($html) {
        // Remove comments
        $html = preg_replace('/<!--(?!<!)[^\[>].*?-->/s', '', $html);
        
        // Remove extra whitespace
        $html = preg_replace('/\s+/', ' ', $html);
        
        // Remove whitespace around tags
        $html = preg_replace('/>\s+</', '><', $html);
        
        return trim($html);
    }
    
    /**
     * Get real-time data endpoint
     */
    public function getRealTimeEndpoint($type) {
        $endpoints = [
            'orders' => APP_URL . '/api/realtime/orders.php',
            'sales' => APP_URL . '/api/realtime/sales.php',
            'kitchen' => APP_URL . '/api/realtime/kitchen.php',
            'delivery' => APP_URL . '/api/realtime/delivery.php'
        ];
        
        return $endpoints[$type] ?? null;
    }
    
    /**
     * Start performance monitoring
     */
    public function startMonitoring() {
        if (!defined('WAPOS_START_TIME')) {
            define('WAPOS_START_TIME', microtime(true));
        }
    }
    
    /**
     * End performance monitoring and log
     */
    public function endMonitoring($page = '') {
        if (defined('WAPOS_START_TIME')) {
            $executionTime = microtime(true) - WAPOS_START_TIME;
            $memoryUsage = memory_get_peak_usage(true);
            
            // Log slow pages (> 2 seconds)
            if ($executionTime > 2) {
                error_log("SLOW PAGE: $page took {$executionTime}s, Memory: " . 
                         number_format($memoryUsage / 1024 / 1024, 2) . "MB");
            }
            
            // Add performance info to HTML comment
            if (!headers_sent()) {
                echo "<!-- Performance: {$executionTime}s, Memory: " . 
                     number_format($memoryUsage / 1024 / 1024, 2) . "MB -->";
            }
        }
    }
    
    /**
     * Clean old cache files
     */
    public function cleanCache($maxAge = 3600) {
        $files = glob($this->cacheDir . '/*.cache');
        foreach ($files as $file) {
            if (time() - filemtime($file) > $maxAge) {
                unlink($file);
            }
        }
    }
}
?>
