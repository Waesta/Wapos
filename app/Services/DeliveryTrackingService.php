<?php

namespace App\Services;

use DateTime;
use Exception;
use PDO;
use PDOException;

class DeliveryTrackingService
{
    private PDO $db;

    private const STATUS_HISTORY_TABLE = 'delivery_status_history';
    private const RIDER_HISTORY_TABLE = 'rider_location_history';
    private const ROUTES_TABLE = 'delivery_routes';
    private const ROUTE_WAYPOINTS_TABLE = 'route_waypoints';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function ensureSchema(): void
    {
        $this->createStatusHistoryTable();
        $this->syncStatusHistoryTable();
        $this->createRiderLocationHistoryTable();
        $this->syncRiderLocationHistoryTable();
        $this->createRoutesTable();
        $this->createRouteWaypointsTable();
    }

    /**
     * Persist a status transition for the delivery timeline.
     */
    public function recordStatusHistory(int $deliveryId, string $status, array $context = []): void
    {
        $this->ensureSchema();

        $status = strtolower(trim($status));
        if ($status === '') {
            throw new Exception('Delivery status is required for history records.');
        }

        $notes = isset($context['notes']) ? trim((string)$context['notes']) : null;
        $photoUrl = isset($context['photo_url']) ? trim((string)$context['photo_url']) : null;
        $latitude = isset($context['latitude']) ? $this->coerceFloat($context['latitude']) : null;
        $longitude = isset($context['longitude']) ? $this->coerceFloat($context['longitude']) : null;
        $userId = isset($context['user_id']) ? (int)$context['user_id'] : null;

        $stmt = $this->db->prepare(
            "INSERT INTO " . self::STATUS_HISTORY_TABLE . " (
                delivery_id,
                status,
                notes,
                photo_url,
                latitude,
                longitude,
                user_id,
                created_at
            ) VALUES (
                :delivery_id,
                :status,
                :notes,
                :photo_url,
                :latitude,
                :longitude,
                :user_id,
                NOW()
            )"
        );

        $stmt->execute([
            ':delivery_id' => $deliveryId,
            ':status' => $status,
            ':notes' => $notes,
            ':photo_url' => $photoUrl,
            ':latitude' => $latitude,
            ':longitude' => $longitude,
            ':user_id' => $userId,
        ]);
    }

    /**
     * Fetch chronological status timeline for an order.
     */
    public function getTimelineForOrder(int $orderId): array
    {
        $this->ensureSchema();

        $sql = "
            SELECT h.id,
                   h.delivery_id,
                   h.status,
                   h.notes,
                   h.photo_url,
                   h.latitude,
                   h.longitude,
                   h.created_at,
                   h.user_id,
                   CONCAT_WS(' ', u.full_name, u.username) AS recorded_by
            FROM " . self::STATUS_HISTORY_TABLE . " h
            JOIN deliveries d ON h.delivery_id = d.id
            LEFT JOIN users u ON h.user_id = u.id
            WHERE d.order_id = :order_id
            ORDER BY h.created_at ASC, h.id ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows ?: [];
    }

    /**
     * Update rider coordinates and append entry to location history. Returns
     * the list of active deliveries that were recalculated.
     */
    public function updateRiderLocation(int $riderId, float $latitude, float $longitude, ?float $accuracy = null): array
    {
        $this->ensureSchema();

        $accuracy = $accuracy !== null ? max(0.0, $accuracy) : null;

        $updateStmt = $this->db->prepare(
            "UPDATE riders
             SET current_latitude = :lat,
                 current_longitude = :lng,
                 location_accuracy = :accuracy,
                 last_location_update = NOW()
             WHERE id = :id"
        );
        $updateStmt->execute([
            ':lat' => $latitude,
            ':lng' => $longitude,
            ':accuracy' => $accuracy,
            ':id' => $riderId,
        ]);

        $historyStmt = $this->db->prepare(
            "INSERT INTO " . self::RIDER_HISTORY_TABLE . " (rider_id, latitude, longitude, accuracy, recorded_at)
             VALUES (:rider_id, :lat, :lng, :accuracy, NOW())"
        );
        $historyStmt->execute([
            ':rider_id' => $riderId,
            ':lat' => $latitude,
            ':lng' => $longitude,
            ':accuracy' => $accuracy,
        ]);

        $deliveries = $this->db->fetchAll(
            "SELECT d.id, d.order_id, o.delivery_address
             FROM deliveries d
             JOIN orders o ON d.order_id = o.id
             WHERE d.rider_id = ? AND d.status IN ('assigned','picked-up','in-transit')",
            [$riderId]
        );

        $updated = [];
        foreach ($deliveries as $delivery) {
            $eta = $this->calculateDeliveryEta($latitude, $longitude, (string)($delivery['delivery_address'] ?? ''));
            $this->db->update('deliveries', [
                'estimated_delivery_time' => $eta,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $delivery['id']]);

            $updated[] = [
                'delivery_id' => (int)$delivery['id'],
                'order_id' => (int)$delivery['order_id'],
                'estimated_delivery_time' => $eta,
            ];
        }

        return $updated;
    }

    private function calculateDeliveryEta(float $riderLat, float $riderLng, string $deliveryAddress): string
    {
        // Placeholder estimation logic. A production-ready implementation
        // would integrate with a mapping / distance matrix provider.
        $baseMinutes = 15;
        $randomFactor = random_int(0, 30);
        $eta = new DateTime();
        $eta->modify("+{$baseMinutes} minutes");
        $eta->modify("+{$randomFactor} minutes");
        return $eta->format('Y-m-d H:i:s');
    }

    private function createStatusHistoryTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS " . self::STATUS_HISTORY_TABLE . " (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            delivery_id INT UNSIGNED NOT NULL,
            status ENUM('pending','assigned','picked-up','in-transit','delivered','failed','cancelled') NOT NULL,
            notes TEXT NULL,
            photo_url VARCHAR(255) NULL,
            latitude DECIMAL(10,8) NULL,
            longitude DECIMAL(11,8) NULL,
            user_id INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_delivery_status (delivery_id, status),
            INDEX idx_created_at (created_at),
            CONSTRAINT fk_delivery_status_history_delivery FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
            CONSTRAINT fk_delivery_status_history_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB";

        $this->db->exec($sql);
    }

    private function syncStatusHistoryTable(): void
    {
        $this->addColumnIfMissing(self::STATUS_HISTORY_TABLE, 'photo_url', 'VARCHAR(255) NULL AFTER notes');
        $this->addColumnIfMissing(self::STATUS_HISTORY_TABLE, 'latitude', 'DECIMAL(10,8) NULL AFTER photo_url');
        $this->addColumnIfMissing(self::STATUS_HISTORY_TABLE, 'longitude', 'DECIMAL(11,8) NULL AFTER latitude');
    }

    private function createRiderLocationHistoryTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS " . self::RIDER_HISTORY_TABLE . " (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            rider_id INT UNSIGNED NOT NULL,
            latitude DECIMAL(10,8) NOT NULL,
            longitude DECIMAL(11,8) NOT NULL,
            accuracy DECIMAL(6,2) NULL,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_rider (rider_id),
            INDEX idx_recorded_at (recorded_at),
            CONSTRAINT fk_rider_location_history FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB";

        $this->db->exec($sql);
    }

    private function syncRiderLocationHistoryTable(): void
    {
        $this->addColumnIfMissing(self::RIDER_HISTORY_TABLE, 'accuracy', 'DECIMAL(6,2) NULL AFTER longitude');
    }

    private function createRoutesTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS " . self::ROUTES_TABLE . " (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            rider_id INT UNSIGNED NOT NULL,
            route_name VARCHAR(100) NULL,
            start_latitude DECIMAL(10,8) NULL,
            start_longitude DECIMAL(11,8) NULL,
            end_latitude DECIMAL(10,8) NULL,
            end_longitude DECIMAL(11,8) NULL,
            total_distance_km FLOAT NULL,
            estimated_duration_minutes INT NULL,
            actual_duration_minutes INT NULL,
            delivery_count INT DEFAULT 0,
            route_date DATE NULL,
            status ENUM('planned','active','completed','cancelled') DEFAULT 'planned',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            INDEX idx_rider_date (rider_id, route_date),
            INDEX idx_status (status),
            CONSTRAINT fk_delivery_routes_rider FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB";

        $this->db->exec($sql);
    }

    private function createRouteWaypointsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS " . self::ROUTE_WAYPOINTS_TABLE . " (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            route_id INT UNSIGNED NOT NULL,
            delivery_id INT UNSIGNED NOT NULL,
            sequence_order INT NOT NULL,
            latitude DECIMAL(10,8) NOT NULL,
            longitude DECIMAL(11,8) NOT NULL,
            estimated_arrival DATETIME NULL,
            actual_arrival DATETIME NULL,
            time_spent_minutes INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_route_sequence (route_id, sequence_order),
            CONSTRAINT fk_route_waypoints_route FOREIGN KEY (route_id) REFERENCES " . self::ROUTES_TABLE . "(id) ON DELETE CASCADE,
            CONSTRAINT fk_route_waypoints_delivery FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE
        ) ENGINE=InnoDB";

        $this->db->exec($sql);
    }

    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        $columns = $this->getTableColumns($table);
        if (!in_array($column, $columns, true)) {
            $this->db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }

    private function getTableColumns(string $table): array
    {
        try {
            $stmt = $this->db->prepare('SHOW COLUMNS FROM ' . $table);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }

    private function coerceFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float)$value;
        }
        return null;
    }
}
