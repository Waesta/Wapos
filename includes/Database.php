<?php
/**
 * Database Connection Class
 * Simple PDO wrapper for WAPOS
 */

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
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
        try {
            // Ensure connection is still alive
            $this->ensureConnection();
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
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
}
