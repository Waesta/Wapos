<?php
/**
 * Soft Delete Helper Functions
 * Provides soft delete functionality for database operations
 */

class SoftDelete {
    private static $db;
    
    private static function getDb() {
        if (self::$db === null) {
            self::$db = Database::getInstance();
        }
        return self::$db;
    }
    
    /**
     * Soft delete a record by setting deleted_at timestamp
     */
    public static function delete($table, $id, $idColumn = 'id') {
        $db = self::getDb();
        return $db->update($table, 
            ['deleted_at' => date('Y-m-d H:i:s')],
            "$idColumn = :id",
            ['id' => $id]
        );
    }
    
    /**
     * Restore a soft-deleted record
     */
    public static function restore($table, $id, $idColumn = 'id') {
        $db = self::getDb();
        return $db->update($table,
            ['deleted_at' => null],
            "$idColumn = :id",
            ['id' => $id]
        );
    }
    
    /**
     * Permanently delete a record
     */
    public static function forceDelete($table, $id, $idColumn = 'id') {
        $db = self::getDb();
        return $db->execute("DELETE FROM $table WHERE $idColumn = ?", [$id]);
    }
    
    /**
     * Check if a record is soft deleted
     */
    public static function isTrashed($table, $id, $idColumn = 'id') {
        $db = self::getDb();
        $result = $db->fetchOne(
            "SELECT deleted_at FROM $table WHERE $idColumn = ?",
            [$id]
        );
        return $result && $result['deleted_at'] !== null;
    }
    
    /**
     * Get only non-deleted records
     */
    public static function whereNotDeleted($baseQuery) {
        // Add soft delete condition to query
        if (stripos($baseQuery, 'WHERE') !== false) {
            return $baseQuery . ' AND deleted_at IS NULL';
        }
        return $baseQuery . ' WHERE deleted_at IS NULL';
    }
    
    /**
     * Get only deleted records
     */
    public static function whereDeleted($baseQuery) {
        if (stripos($baseQuery, 'WHERE') !== false) {
            return $baseQuery . ' AND deleted_at IS NOT NULL';
        }
        return $baseQuery . ' WHERE deleted_at IS NOT NULL';
    }
    
    /**
     * Get all records including deleted
     */
    public static function withTrashed($baseQuery) {
        return $baseQuery; // No modification needed
    }
    
    /**
     * Purge old soft-deleted records (cleanup)
     * @param string $table Table name
     * @param int $daysOld Delete records older than this many days
     */
    public static function purgeOld($table, $daysOld = 90) {
        $db = self::getDb();
        $cutoff = date('Y-m-d H:i:s', strtotime("-$daysOld days"));
        return $db->execute(
            "DELETE FROM $table WHERE deleted_at IS NOT NULL AND deleted_at < ?",
            [$cutoff]
        );
    }
}

/**
 * Helper functions for soft delete operations
 */
function softDelete($table, $id) {
    return SoftDelete::delete($table, $id);
}

function restoreDeleted($table, $id) {
    return SoftDelete::restore($table, $id);
}

function forceDelete($table, $id) {
    return SoftDelete::forceDelete($table, $id);
}
