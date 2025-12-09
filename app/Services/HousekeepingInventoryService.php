<?php

namespace App\Services;

use PDO;
use Exception;

/**
 * Housekeeping Inventory Service
 * 
 * Manages inventory for housekeeping sections:
 * - Laundry (linens, towels, uniforms)
 * - Linen (bed sheets, pillowcases, blankets)
 * - Public Area Supplies (cleaning supplies, amenities)
 * - Room Amenities/Minibar
 */
class HousekeepingInventoryService
{
    private PDO $db;

    /**
     * Inventory sections
     */
    public const SECTIONS = [
        'laundry' => [
            'label' => 'Laundry',
            'icon' => 'bi-basket3',
            'description' => 'Linens, towels, and uniforms for washing'
        ],
        'linen' => [
            'label' => 'Linen Store',
            'icon' => 'bi-stack',
            'description' => 'Bed sheets, pillowcases, blankets, and bedding'
        ],
        'public_area' => [
            'label' => 'Public Area Supplies',
            'icon' => 'bi-building',
            'description' => 'Cleaning supplies for lobbies, corridors, and common areas'
        ],
        'room_amenities' => [
            'label' => 'Room Amenities',
            'icon' => 'bi-gift',
            'description' => 'Guest amenities, toiletries, and welcome items'
        ],
        'minibar' => [
            'label' => 'Minibar',
            'icon' => 'bi-cup-straw',
            'description' => 'Minibar items for guest rooms'
        ],
        'cleaning' => [
            'label' => 'Cleaning Supplies',
            'icon' => 'bi-droplet',
            'description' => 'Detergents, disinfectants, and cleaning equipment'
        ]
    ];

    /**
     * Linen statuses for tracking
     */
    public const LINEN_STATUSES = [
        'clean' => 'Clean & Available',
        'in_use' => 'In Use (Room)',
        'dirty' => 'Dirty (Awaiting Laundry)',
        'washing' => 'In Laundry',
        'damaged' => 'Damaged',
        'discarded' => 'Discarded'
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Ensure database schema exists
     */
    public function ensureSchema(): void
    {
        // Housekeeping inventory items table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS housekeeping_inventory (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                section VARCHAR(50) NOT NULL,
                item_name VARCHAR(200) NOT NULL,
                item_code VARCHAR(50),
                description TEXT,
                unit VARCHAR(50) DEFAULT 'pcs',
                quantity_on_hand DECIMAL(15,4) DEFAULT 0,
                reorder_level DECIMAL(15,4) DEFAULT 0,
                cost_price DECIMAL(15,2) DEFAULT 0,
                supplier VARCHAR(200),
                location VARCHAR(100),
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_section (section),
                INDEX idx_item_code (item_code),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB
        ");

        // Linen tracking table (for individual linen items)
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS housekeeping_linen (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                inventory_id INT UNSIGNED NOT NULL,
                linen_code VARCHAR(50) UNIQUE,
                status ENUM('clean', 'in_use', 'dirty', 'washing', 'damaged', 'discarded') DEFAULT 'clean',
                room_id INT UNSIGNED NULL,
                last_washed_at TIMESTAMP NULL,
                wash_count INT DEFAULT 0,
                condition_notes TEXT,
                acquired_date DATE,
                discarded_date DATE NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_inventory (inventory_id),
                INDEX idx_status (status),
                INDEX idx_room (room_id),
                INDEX idx_code (linen_code)
            ) ENGINE=InnoDB
        ");

        // Laundry batches table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS housekeeping_laundry_batches (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                batch_number VARCHAR(50) NOT NULL,
                status ENUM('pending', 'washing', 'drying', 'folding', 'completed', 'cancelled') DEFAULT 'pending',
                item_count INT DEFAULT 0,
                started_at TIMESTAMP NULL,
                completed_at TIMESTAMP NULL,
                notes TEXT,
                created_by INT UNSIGNED,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_batch (batch_number),
                INDEX idx_status (status)
            ) ENGINE=InnoDB
        ");

        // Laundry batch items
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS housekeeping_laundry_items (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                batch_id INT UNSIGNED NOT NULL,
                linen_id INT UNSIGNED NULL,
                inventory_id INT UNSIGNED NULL,
                quantity INT DEFAULT 1,
                notes VARCHAR(255),
                FOREIGN KEY (batch_id) REFERENCES housekeeping_laundry_batches(id) ON DELETE CASCADE,
                INDEX idx_batch (batch_id),
                INDEX idx_linen (linen_id)
            ) ENGINE=InnoDB
        ");

        // Minibar par levels per room type
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS housekeeping_minibar_par (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                room_type_id INT UNSIGNED NULL,
                inventory_id INT UNSIGNED NOT NULL,
                par_level INT DEFAULT 1,
                selling_price DECIMAL(15,2) DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                UNIQUE KEY uk_room_item (room_type_id, inventory_id),
                INDEX idx_room_type (room_type_id)
            ) ENGINE=InnoDB
        ");

        // Minibar consumption log
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS housekeeping_minibar_log (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                room_id INT UNSIGNED NOT NULL,
                booking_id INT UNSIGNED NULL,
                inventory_id INT UNSIGNED NOT NULL,
                quantity INT NOT NULL,
                unit_price DECIMAL(15,2) NOT NULL,
                total_amount DECIMAL(15,2) NOT NULL,
                charged_to_folio TINYINT(1) DEFAULT 0,
                folio_charge_id INT UNSIGNED NULL,
                recorded_by INT UNSIGNED,
                recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                notes VARCHAR(255),
                INDEX idx_room (room_id),
                INDEX idx_booking (booking_id),
                INDEX idx_date (recorded_at)
            ) ENGINE=InnoDB
        ");

        // Inventory transactions log
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS housekeeping_inventory_log (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                inventory_id INT UNSIGNED NOT NULL,
                transaction_type ENUM('receipt', 'issue', 'adjustment', 'transfer', 'damage', 'return') NOT NULL,
                quantity DECIMAL(15,4) NOT NULL,
                reference_type VARCHAR(50),
                reference_id INT UNSIGNED,
                from_location VARCHAR(100),
                to_location VARCHAR(100),
                notes TEXT,
                user_id INT UNSIGNED,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_inventory (inventory_id),
                INDEX idx_type (transaction_type),
                INDEX idx_date (created_at)
            ) ENGINE=InnoDB
        ");
    }

    /**
     * Get sections list
     */
    public function getSections(): array
    {
        return self::SECTIONS;
    }

    /**
     * Get inventory items by section
     */
    public function getInventoryBySection(string $section): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM housekeeping_inventory 
            WHERE section = ? AND is_active = 1
            ORDER BY item_name
        ");
        $stmt->execute([$section]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all inventory items
     */
    public function getAllInventory(): array
    {
        $stmt = $this->db->query("
            SELECT hi.*, 
                   (SELECT COUNT(*) FROM housekeeping_linen hl WHERE hl.inventory_id = hi.id AND hl.status != 'discarded') as linen_count
            FROM housekeeping_inventory hi
            WHERE hi.is_active = 1
            ORDER BY hi.section, hi.item_name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get low stock items
     */
    public function getLowStockItems(): array
    {
        $stmt = $this->db->query("
            SELECT * FROM housekeeping_inventory 
            WHERE is_active = 1 AND quantity_on_hand <= reorder_level
            ORDER BY section, item_name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add inventory item
     */
    public function addInventoryItem(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO housekeeping_inventory 
            (section, item_name, item_code, description, unit, quantity_on_hand, reorder_level, cost_price, supplier, location)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['section'],
            $data['item_name'],
            $data['item_code'] ?? null,
            $data['description'] ?? null,
            $data['unit'] ?? 'pcs',
            $data['quantity_on_hand'] ?? 0,
            $data['reorder_level'] ?? 0,
            $data['cost_price'] ?? 0,
            $data['supplier'] ?? null,
            $data['location'] ?? null
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update inventory item
     */
    public function updateInventoryItem(int $id, array $data): bool
    {
        $fields = [];
        $values = [];
        
        $allowedFields = ['item_name', 'item_code', 'description', 'unit', 'reorder_level', 'cost_price', 'supplier', 'location', 'section'];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $id;
        $stmt = $this->db->prepare("UPDATE housekeeping_inventory SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($values);
    }

    /**
     * Adjust inventory quantity
     */
    public function adjustInventory(int $inventoryId, float $quantity, string $type, int $userId, ?string $notes = null, ?string $refType = null, ?int $refId = null): bool
    {
        $this->db->beginTransaction();
        try {
            // Update quantity
            $operator = in_array($type, ['receipt', 'return']) ? '+' : '-';
            $stmt = $this->db->prepare("
                UPDATE housekeeping_inventory 
                SET quantity_on_hand = quantity_on_hand $operator ?
                WHERE id = ?
            ");
            $stmt->execute([abs($quantity), $inventoryId]);

            // Log transaction
            $stmt = $this->db->prepare("
                INSERT INTO housekeeping_inventory_log 
                (inventory_id, transaction_type, quantity, reference_type, reference_id, notes, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $inventoryId,
                $type,
                $quantity,
                $refType,
                $refId,
                $notes,
                $userId
            ]);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ==================== LINEN MANAGEMENT ====================

    /**
     * Get linen items by status
     */
    public function getLinenByStatus(string $status): array
    {
        $stmt = $this->db->prepare("
            SELECT hl.*, hi.item_name, hi.item_code as inventory_code, r.room_number
            FROM housekeeping_linen hl
            JOIN housekeeping_inventory hi ON hl.inventory_id = hi.id
            LEFT JOIN rooms r ON hl.room_id = r.id
            WHERE hl.status = ?
            ORDER BY hi.item_name, hl.linen_code
        ");
        $stmt->execute([$status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get linen summary counts
     */
    public function getLinenSummary(): array
    {
        $stmt = $this->db->query("
            SELECT status, COUNT(*) as count
            FROM housekeeping_linen
            WHERE status != 'discarded'
            GROUP BY status
        ");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $summary = [];
        foreach (self::LINEN_STATUSES as $status => $label) {
            $summary[$status] = ['label' => $label, 'count' => 0];
        }
        foreach ($results as $row) {
            if (isset($summary[$row['status']])) {
                $summary[$row['status']]['count'] = (int) $row['count'];
            }
        }
        return $summary;
    }

    /**
     * Add linen item
     */
    public function addLinenItem(int $inventoryId, ?string $linenCode = null, ?string $acquiredDate = null): int
    {
        if (!$linenCode) {
            $linenCode = 'LN-' . strtoupper(substr(md5(uniqid()), 0, 8));
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO housekeeping_linen (inventory_id, linen_code, acquired_date)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$inventoryId, $linenCode, $acquiredDate ?? date('Y-m-d')]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update linen status
     */
    public function updateLinenStatus(int $linenId, string $status, ?int $roomId = null, ?string $notes = null): bool
    {
        $updates = ['status = ?'];
        $values = [$status];
        
        if ($status === 'in_use' && $roomId) {
            $updates[] = 'room_id = ?';
            $values[] = $roomId;
        } elseif ($status === 'clean') {
            $updates[] = 'room_id = NULL';
            $updates[] = 'last_washed_at = NOW()';
            $updates[] = 'wash_count = wash_count + 1';
        }
        
        if ($notes) {
            $updates[] = 'condition_notes = ?';
            $values[] = $notes;
        }
        
        if ($status === 'discarded') {
            $updates[] = 'discarded_date = CURDATE()';
        }
        
        $values[] = $linenId;
        $stmt = $this->db->prepare("UPDATE housekeeping_linen SET " . implode(', ', $updates) . " WHERE id = ?");
        return $stmt->execute($values);
    }

    // ==================== LAUNDRY MANAGEMENT ====================

    /**
     * Create laundry batch
     */
    public function createLaundryBatch(array $linenIds, int $userId, ?string $notes = null): int
    {
        $batchNumber = 'LB-' . date('ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO housekeeping_laundry_batches (batch_number, item_count, notes, created_by)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$batchNumber, count($linenIds), $notes, $userId]);
            $batchId = (int) $this->db->lastInsertId();

            // Add items to batch
            $itemStmt = $this->db->prepare("
                INSERT INTO housekeeping_laundry_items (batch_id, linen_id) VALUES (?, ?)
            ");
            foreach ($linenIds as $linenId) {
                $itemStmt->execute([$batchId, $linenId]);
                $this->updateLinenStatus($linenId, 'washing');
            }

            $this->db->commit();
            return $batchId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update laundry batch status
     */
    public function updateLaundryBatchStatus(int $batchId, string $status): bool
    {
        $updates = ['status = ?'];
        $values = [$status];
        
        if ($status === 'washing') {
            $updates[] = 'started_at = NOW()';
        } elseif ($status === 'completed') {
            $updates[] = 'completed_at = NOW()';
            
            // Mark all linens as clean
            $stmt = $this->db->prepare("SELECT linen_id FROM housekeeping_laundry_items WHERE batch_id = ?");
            $stmt->execute([$batchId]);
            $items = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($items as $linenId) {
                if ($linenId) {
                    $this->updateLinenStatus($linenId, 'clean');
                }
            }
        }
        
        $values[] = $batchId;
        $stmt = $this->db->prepare("UPDATE housekeeping_laundry_batches SET " . implode(', ', $updates) . " WHERE id = ?");
        return $stmt->execute($values);
    }

    /**
     * Get laundry batches
     */
    public function getLaundryBatches(?string $status = null): array
    {
        $sql = "
            SELECT lb.*, u.full_name as created_by_name
            FROM housekeeping_laundry_batches lb
            LEFT JOIN users u ON lb.created_by = u.id
        ";
        $params = [];
        
        if ($status) {
            $sql .= " WHERE lb.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY lb.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==================== MINIBAR MANAGEMENT ====================

    /**
     * Get minibar items for a room
     */
    public function getMinibarItems(?int $roomTypeId = null): array
    {
        $sql = "
            SELECT mp.*, hi.item_name, hi.item_code, hi.quantity_on_hand as stock_available
            FROM housekeeping_minibar_par mp
            JOIN housekeeping_inventory hi ON mp.inventory_id = hi.id
            WHERE mp.is_active = 1
        ";
        $params = [];
        
        if ($roomTypeId) {
            $sql .= " AND (mp.room_type_id = ? OR mp.room_type_id IS NULL)";
            $params[] = $roomTypeId;
        }
        
        $sql .= " ORDER BY hi.item_name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record minibar consumption
     */
    public function recordMinibarConsumption(int $roomId, int $inventoryId, int $quantity, float $unitPrice, int $userId, ?int $bookingId = null, ?string $notes = null): int
    {
        $totalAmount = $quantity * $unitPrice;
        
        $this->db->beginTransaction();
        try {
            // Log consumption
            $stmt = $this->db->prepare("
                INSERT INTO housekeeping_minibar_log 
                (room_id, booking_id, inventory_id, quantity, unit_price, total_amount, recorded_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$roomId, $bookingId, $inventoryId, $quantity, $unitPrice, $totalAmount, $userId, $notes]);
            $logId = (int) $this->db->lastInsertId();

            // Deduct from inventory
            $this->adjustInventory($inventoryId, $quantity, 'issue', $userId, "Minibar consumption - Room", 'minibar_log', $logId);

            $this->db->commit();
            return $logId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get minibar consumption for a booking
     */
    public function getMinibarConsumption(int $bookingId): array
    {
        $stmt = $this->db->prepare("
            SELECT ml.*, hi.item_name, hi.item_code, r.room_number
            FROM housekeeping_minibar_log ml
            JOIN housekeeping_inventory hi ON ml.inventory_id = hi.id
            LEFT JOIN rooms r ON ml.room_id = r.id
            WHERE ml.booking_id = ?
            ORDER BY ml.recorded_at
        ");
        $stmt->execute([$bookingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Charge minibar to folio
     */
    public function chargeMinibarToFolio(int $logId, int $folioChargeId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE housekeeping_minibar_log 
            SET charged_to_folio = 1, folio_charge_id = ?
            WHERE id = ?
        ");
        return $stmt->execute([$folioChargeId, $logId]);
    }

    /**
     * Get dashboard summary
     */
    public function getDashboardSummary(): array
    {
        $summary = [
            'sections' => [],
            'low_stock_count' => 0,
            'linen_summary' => $this->getLinenSummary(),
            'pending_laundry' => 0,
            'today_minibar_revenue' => 0
        ];

        // Section counts
        $stmt = $this->db->query("
            SELECT section, COUNT(*) as item_count, SUM(quantity_on_hand) as total_quantity
            FROM housekeeping_inventory
            WHERE is_active = 1
            GROUP BY section
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $summary['sections'][$row['section']] = [
                'item_count' => (int) $row['item_count'],
                'total_quantity' => (float) $row['total_quantity']
            ];
        }

        // Low stock count
        $stmt = $this->db->query("SELECT COUNT(*) FROM housekeeping_inventory WHERE is_active = 1 AND quantity_on_hand <= reorder_level");
        $summary['low_stock_count'] = (int) $stmt->fetchColumn();

        // Pending laundry
        $stmt = $this->db->query("SELECT COUNT(*) FROM housekeeping_laundry_batches WHERE status IN ('pending', 'washing', 'drying', 'folding')");
        $summary['pending_laundry'] = (int) $stmt->fetchColumn();

        // Today's minibar revenue
        $stmt = $this->db->query("SELECT COALESCE(SUM(total_amount), 0) FROM housekeeping_minibar_log WHERE DATE(recorded_at) = CURDATE()");
        $summary['today_minibar_revenue'] = (float) $stmt->fetchColumn();

        return $summary;
    }
}
