<?php
/**
 * Database Connection Class
 * Simple PDO wrapper for WAPOS
 */

class Database {
    private static $instance = null;
    private $pdo;
    private $queryCache = [];
    private $cacheEnabled = true;
    private $queryCount = 0;
    private $slowQueryThreshold = 1.0; // 1 second
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT => true, // Use persistent connections
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci",
                PDO::ATTR_TIMEOUT => 30,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Optimize MySQL settings for performance
            $this->pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
            $this->pdo->exec("SET SESSION innodb_lock_wait_timeout = 10");
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please check your configuration.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function query($sql, $params = []) {
        $startTime = microtime(true);
        $this->queryCount++;
        
        try {
            // Ensure connection is still alive
            $this->ensureConnection();
            
            // Check cache for SELECT queries (but don't cache everything to avoid memory issues)
            $cacheKey = $this->getCacheKey($sql, $params);
            if ($this->cacheEnabled && $this->isSelectQuery($sql) && isset($this->queryCache[$cacheKey]) && count($this->queryCache) < 100) {
                return $this->queryCache[$cacheKey];
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            // Cache SELECT results
            if ($this->cacheEnabled && $this->isSelectQuery($sql)) {
                $this->queryCache[$cacheKey] = $stmt;
            }
            
            // Log slow queries
            $executionTime = microtime(true) - $startTime;
            if ($executionTime > $this->slowQueryThreshold) {
                error_log("SLOW QUERY ({$executionTime}s): " . $sql . " | Params: " . json_encode($params));
            }
            
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage() . " SQL: " . $sql);
            
            // Try to reconnect on connection errors
            if ($this->isConnectionError($e)) {
                $this->reconnect();
                try {
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute($params);
                    return $stmt;
                } catch (PDOException $retryE) {
                    error_log("Database retry failed: " . $retryE->getMessage());
                }
            }
            
            return false;
        }
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }
    
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : null;
    }
    
    public function insert($table, $data) {
        $keys = array_keys($data);
        $fields = implode(', ', $keys);
        $placeholders = ':' . implode(', :', $keys);
        
        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";
        $stmt = $this->query($sql, $data);
        
        return $stmt ? $this->pdo->lastInsertId() : false;
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach (array_keys($data) as $key) {
            $set[] = "{$key} = :{$key}";
        }
        $setString = implode(', ', $set);
        
        $sql = "UPDATE {$table} SET {$setString} WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        return $this->query($sql, $params) !== false;
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params) !== false;
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollback() {
        return $this->pdo->rollBack();
    }
    
    /**
     * Ensure database connection is alive
     */
    private function ensureConnection() {
        try {
            $this->pdo->query('SELECT 1');
        } catch (PDOException $e) {
            $this->reconnect();
        }
    }
    
    /**
     * Check if error is connection-related
     */
    private function isConnectionError($e) {
        $connectionErrors = [
            'MySQL server has gone away',
            'Lost connection to MySQL server',
            'Connection timed out',
            'Can\'t connect to MySQL server'
        ];
        
        foreach ($connectionErrors as $error) {
            if (strpos($e->getMessage(), $error) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Reconnect to database
     */
    private function reconnect() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            error_log("Database reconnected successfully");
        } catch (PDOException $e) {
            error_log("Database reconnection failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Execute multiple queries safely
     */
    public function executeMultiple($queries) {
        $this->beginTransaction();
        
        try {
            foreach ($queries as $query) {
                if (is_array($query)) {
                    $this->query($query['sql'], $query['params'] ?? []);
                } else {
                    $this->query($query);
                }
            }
            
            $this->commit();
            return true;
        } catch (Exception $e) {
            $this->rollback();
            error_log("Multiple query execution failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate cache key for query
     */
    private function getCacheKey($sql, $params) {
        return md5($sql . serialize($params));
    }
    
    /**
     * Check if query is a SELECT statement
     */
    private function isSelectQuery($sql) {
        return stripos(trim($sql), 'SELECT') === 0;
    }
    
    /**
     * Clear query cache
     */
    public function clearCache() {
        $this->queryCache = [];
    }
    
    /**
     * Get performance statistics
     */
    public function getStats() {
        return [
            'query_count' => $this->queryCount,
            'cache_size' => count($this->queryCache),
            'cache_enabled' => $this->cacheEnabled
        ];
    }
    
    /**
     * Enable/disable query caching
     */
    public function setCacheEnabled($enabled) {
        $this->cacheEnabled = $enabled;
        if (!$enabled) {
            $this->clearCache();
        }
    }
}
