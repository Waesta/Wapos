<?php
/**
 * Rate Limiter Class
 * Prevents brute force attacks and API abuse
 * Uses file-based storage for simplicity (can be upgraded to Redis)
 */

class RateLimiter {
    private $storageDir;
    private $maxAttempts;
    private $decayMinutes;
    
    public function __construct($maxAttempts = 60, $decayMinutes = 1) {
        $this->storageDir = ROOT_PATH . '/cache/rate_limits';
        $this->maxAttempts = $maxAttempts;
        $this->decayMinutes = $decayMinutes;
        
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }
    }
    
    /**
     * Check if the given key has too many attempts
     */
    public function tooManyAttempts($key) {
        $attempts = $this->getAttempts($key);
        return $attempts >= $this->maxAttempts;
    }
    
    /**
     * Increment the counter for the given key
     */
    public function hit($key) {
        $file = $this->getFilePath($key);
        $data = $this->getData($file);
        
        // Clean old attempts
        $cutoff = time() - ($this->decayMinutes * 60);
        $data['attempts'] = array_filter($data['attempts'], function($time) use ($cutoff) {
            return $time > $cutoff;
        });
        
        // Add new attempt
        $data['attempts'][] = time();
        
        file_put_contents($file, json_encode($data), LOCK_EX);
        
        return count($data['attempts']);
    }
    
    /**
     * Get the number of attempts for the given key
     */
    public function getAttempts($key) {
        $file = $this->getFilePath($key);
        $data = $this->getData($file);
        
        // Clean old attempts
        $cutoff = time() - ($this->decayMinutes * 60);
        $data['attempts'] = array_filter($data['attempts'], function($time) use ($cutoff) {
            return $time > $cutoff;
        });
        
        return count($data['attempts']);
    }
    
    /**
     * Clear attempts for the given key
     */
    public function clear($key) {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    /**
     * Get remaining attempts
     */
    public function remainingAttempts($key) {
        return max(0, $this->maxAttempts - $this->getAttempts($key));
    }
    
    /**
     * Get seconds until rate limit resets
     */
    public function availableIn($key) {
        $file = $this->getFilePath($key);
        $data = $this->getData($file);
        
        if (empty($data['attempts'])) {
            return 0;
        }
        
        $oldestAttempt = min($data['attempts']);
        $resetTime = $oldestAttempt + ($this->decayMinutes * 60);
        
        return max(0, $resetTime - time());
    }
    
    private function getFilePath($key) {
        return $this->storageDir . '/' . md5($key) . '.json';
    }
    
    private function getData($file) {
        if (!file_exists($file)) {
            return ['attempts' => []];
        }
        
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        
        return is_array($data) ? $data : ['attempts' => []];
    }
    
    /**
     * Clean up old rate limit files
     */
    public function cleanup() {
        $files = glob($this->storageDir . '/*.json');
        $cutoff = time() - ($this->decayMinutes * 60 * 2);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }
}
