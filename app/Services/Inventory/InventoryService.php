<?php

namespace App\Services\Inventory;

use PDO;
use PDOException;
use Throwable;
use Exception;

class InventoryService
{
    private PDO $db;
    private bool $schemaEnsured = false;
    private array $recipeCache = [];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function ensureSchema(): void
    {
        if ($this->schemaEnsured) {
            return;
        }

        $this->createInventoryCategoriesTable();
        $this->createInventoryItemsTable();
        $this->createInventoryStockLedgerTable();
        $this->createInventoryConsumptionTable();
        $this->createProductRecipesTable();
        $this->ensureProductReorderColumns();
        $this->schemaEnsured = true;
    }

    public function syncAllProducts(): void
    {
        $this->ensureSchema();
        $stmt = $this->db->query("SELECT id FROM products WHERE is_active = 1");
        if (!$stmt) {
            return;
        }
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $productId) {
            $this->syncProductInventory((int) $productId);
        }
    }

    public function syncProductInventory(int $productId): array
    {
        $this->ensureSchema();

        $stmt = $this->db->prepare("SELECT * FROM inventory_items WHERE product_id = ? LIMIT 1");
        $stmt->execute([$productId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($item) {
            return $item;
        }

        $product = $this->fetchProduct($productId);
        if (!$product) {
            throw new Exception('Product not found for inventory sync.');
        }

        $categoryId = $this->ensureInventoryCategoryFromProduct($product);

        $insert = $this->db->prepare("INSERT INTO inventory_items (
                product_id,
                category_id,
                name,
                sku,
                module_scope,
                unit,
                current_stock,
                min_stock_level,
                reorder_level,
                reorder_quantity,
                track_expiry,
                track_wastage,
                cost_price,
                last_cost,
                is_active,
                created_at,
                updated_at
            ) VALUES (
                :product_id,
                :category_id,
                :name,
                :sku,
                :module_scope,
                :unit,
                :current_stock,
                :min_stock_level,
                :reorder_level,
                :reorder_quantity,
                :track_expiry,
                :track_wastage,
                :cost_price,
                :last_cost,
                :is_active,
                NOW(),
                NOW()
            )");

        $insert->execute([
            ':product_id' => $productId,
            ':category_id' => $categoryId,
            ':name' => $product['name'],
            ':sku' => $product['sku'] ?? null,
            ':module_scope' => $this->resolveModuleScope($product),
            ':unit' => $product['unit'] ?? 'pcs',
            ':current_stock' => $product['stock_quantity'] ?? 0,
            ':min_stock_level' => $product['min_stock_level'] ?? 0,
            ':reorder_level' => $product['reorder_level'] ?? ($product['min_stock_level'] ?? 0),
            ':reorder_quantity' => $product['reorder_quantity'] ?? 0,
            ':track_expiry' => $product['track_expiry'] ?? 0,
            ':track_wastage' => $product['track_wastage'] ?? 0,
            ':cost_price' => $product['cost_price'] ?? 0,
            ':last_cost' => $product['cost_price'] ?? 0,
            ':is_active' => $product['is_active'] ?? 1,
        ]);

        $stmt = $this->db->prepare("SELECT * FROM inventory_items WHERE product_id = ? LIMIT 1");
        $stmt->execute([$productId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function recordInboundMovement(int $productId, float $quantity, array $meta = []): void
    {
        if ($quantity == 0.0) {
            return;
        }

        $this->ensureSchema();
        $item = $this->syncProductInventory($productId);
        $inventoryItemId = (int) ($item['id'] ?? 0);
        if ($inventoryItemId <= 0) {
            throw new Exception('Failed to resolve inventory item for inbound movement.');
        }

        $unitCost = isset($meta['unit_cost']) ? (float) $meta['unit_cost'] : ($item['last_cost'] ?? 0);
        $referenceType = $meta['reference_type'] ?? 'grn';
        $referenceId = $meta['reference_id'] ?? null;
        $referenceNumber = $meta['reference_number'] ?? ($meta['reference'] ?? null);
        $notes = $meta['notes'] ?? null;
        $userId = $meta['user_id'] ?? null;
        $sourceModule = $meta['source_module'] ?? 'general';

        $this->db->beginTransaction();
        try {
            $this->updateProductStockQuantity($productId, $quantity);
            $this->applyInventoryStockDelta($inventoryItemId, $quantity, $unitCost, true);
            $this->insertLedgerEntry([
                'inventory_item_id' => $inventoryItemId,
                'product_id' => $productId,
                'movement_type' => $meta['movement_type'] ?? 'grn',
                'direction' => 'in',
                'quantity' => $quantity,
                'cost_price' => $unitCost,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_number' => $referenceNumber,
                'source_module' => $sourceModule,
                'notes' => $notes,
                'created_by' => $userId,
            ]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function recordOutboundMovement(int $productId, float $quantity, array $meta = []): void
    {
        if ($quantity == 0.0) {
            return;
        }

        $this->ensureSchema();
        $item = $this->syncProductInventory($productId);
        $inventoryItemId = (int) ($item['id'] ?? 0);
        if ($inventoryItemId <= 0) {
            throw new Exception('Failed to resolve inventory item for outbound movement.');
        }

        $unitCost = isset($meta['unit_cost']) ? (float) $meta['unit_cost'] : ($item['last_cost'] ?? $item['cost_price'] ?? 0);
        $referenceType = $meta['reference_type'] ?? 'sale';
        $referenceId = $meta['reference_id'] ?? null;
        $referenceNumber = $meta['reference_number'] ?? null;
        $notes = $meta['notes'] ?? null;
        $userId = $meta['user_id'] ?? null;
        $sourceModule = $meta['source_module'] ?? 'general';
        $allowNegative = !empty($meta['allow_negative']);

        $this->db->beginTransaction();
        try {
            $this->updateProductStockQuantity($productId, -$quantity, $allowNegative);
            $this->applyInventoryStockDelta($inventoryItemId, -$quantity, $unitCost, false, $allowNegative);
            $this->insertLedgerEntry([
                'inventory_item_id' => $inventoryItemId,
                'product_id' => $productId,
                'movement_type' => $meta['movement_type'] ?? 'sale',
                'direction' => 'out',
                'quantity' => $quantity,
                'cost_price' => $unitCost,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_number' => $referenceNumber,
                'source_module' => $sourceModule,
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            if (!empty($meta['log_consumption'])) {
                $this->insertConsumptionEvent([
                    'inventory_item_id' => $inventoryItemId,
                    'product_id' => $productId,
                    'source_module' => $sourceModule,
                    'source_reference' => $referenceNumber ?? (string) $referenceId,
                    'quantity' => $quantity,
                    'wastage_quantity' => $meta['wastage_quantity'] ?? 0,
                    'reason' => $meta['consumption_reason'] ?? 'sale',
                    'notes' => $notes,
                    'created_by' => $userId,
                ]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function recordConsumption(int $productId, float $quantity, array $meta = []): void
    {
        $meta['movement_type'] = $meta['movement_type'] ?? 'consumption';
        $meta['reference_type'] = $meta['reference_type'] ?? 'consumption';
        $meta['log_consumption'] = true;
        $meta['consumption_reason'] = $meta['consumption_reason'] ?? 'task';
        $this->recordOutboundMovement($productId, $quantity, $meta);
    }

    public function consumeRecipeItems(int $productId, float $multiplier, array $meta = []): void
    {
        $components = $this->getRecipeComponents($productId);
        if (empty($components) || $multiplier <= 0) {
            return;
        }

        foreach ($components as $component) {
            $qty = (float) $component['quantity'] * $multiplier;
            if ($qty <= 0) {
                continue;
            }

            $this->recordOutboundMovement((int) $component['ingredient_product_id'], $qty, [
                'movement_type' => 'recipe',
                'reference_type' => $meta['reference_type'] ?? 'recipe_sale',
                'reference_id' => $meta['reference_id'] ?? null,
                'reference_number' => $meta['reference_number'] ?? null,
                'source_module' => $meta['source_module'] ?? 'restaurant',
                'notes' => $meta['notes'] ?? ('Recipe consumption for product #' . $productId),
                'user_id' => $meta['user_id'] ?? null,
                'log_consumption' => true,
                'consumption_reason' => 'recipe',
            ]);
        }
    }

    public function getLowStockItems(int $limit = 20): array
    {
        $this->ensureSchema();
        $sql = "SELECT ii.*, ic.module_scope, p.sku AS product_sku
                FROM inventory_items ii
                JOIN inventory_categories ic ON ic.id = ii.category_id
                LEFT JOIN products p ON p.id = ii.product_id
                WHERE ii.is_active = 1
                  AND ii.reorder_level > 0
                  AND ii.current_stock <= ii.reorder_level
                ORDER BY ii.current_stock ASC, ii.reorder_level ASC
                LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function applyInventoryStockDelta(int $inventoryItemId, float $delta, float $unitCost, bool $isInbound, bool $allowNegative = false): void
    {
        $currentSql = "SELECT current_stock FROM inventory_items WHERE id = ? FOR UPDATE";
        $stmt = $this->db->prepare($currentSql);
        $stmt->execute([$inventoryItemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception('Inventory item missing during stock update.');
        }

        $currentStock = (float) $row['current_stock'];
        $newStock = $currentStock + $delta;
        if ($newStock < 0 && !$allowNegative) {
            throw new Exception('Insufficient inventory for requested movement.');
        }

        $update = $this->db->prepare("UPDATE inventory_items
            SET current_stock = :current_stock,
                last_cost = CASE WHEN :unit_cost_check > 0 THEN :unit_cost_value ELSE last_cost END,
                updated_at = NOW()
            WHERE id = :id");
        $update->execute([
            ':current_stock' => $newStock,
            ':unit_cost_check' => $unitCost,
            ':unit_cost_value' => $unitCost,
            ':id' => $inventoryItemId,
        ]);
    }

    private function updateProductStockQuantity(int $productId, float $delta, bool $allowNegative = false): void
    {
        $stmt = $this->db->prepare("SELECT stock_quantity FROM products WHERE id = ? FOR UPDATE");
        $stmt->execute([$productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception('Product not found for stock update.');
        }
        $current = (float) $row['stock_quantity'];
        $newStock = $current + $delta;
        if ($newStock < 0 && !$allowNegative) {
            throw new Exception('Insufficient product stock for movement.');
        }

        $update = $this->db->prepare("UPDATE products SET stock_quantity = :qty, updated_at = NOW() WHERE id = :id");
        $update->execute([':qty' => $newStock, ':id' => $productId]);
    }

    private function insertLedgerEntry(array $payload): void
    {
        $sql = "INSERT INTO inventory_stock_ledger (
                    inventory_item_id,
                    product_id,
                    movement_type,
                    direction,
                    quantity,
                    cost_price,
                    reference_type,
                    reference_id,
                    reference_number,
                    source_module,
                    notes,
                    created_by,
                    created_at
                ) VALUES (
                    :inventory_item_id,
                    :product_id,
                    :movement_type,
                    :direction,
                    :quantity,
                    :cost_price,
                    :reference_type,
                    :reference_id,
                    :reference_number,
                    :source_module,
                    :notes,
                    :created_by,
                    NOW()
                )";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inventory_item_id' => $payload['inventory_item_id'],
            ':product_id' => $payload['product_id'],
            ':movement_type' => $payload['movement_type'],
            ':direction' => $payload['direction'],
            ':quantity' => $payload['quantity'],
            ':cost_price' => $payload['cost_price'],
            ':reference_type' => $payload['reference_type'],
            ':reference_id' => $payload['reference_id'],
            ':reference_number' => $payload['reference_number'],
            ':source_module' => $payload['source_module'],
            ':notes' => $payload['notes'],
            ':created_by' => $payload['created_by'],
        ]);
    }

    private function insertConsumptionEvent(array $payload): void
    {
        $sql = "INSERT INTO inventory_consumption_events (
                    inventory_item_id,
                    product_id,
                    source_module,
                    source_reference,
                    quantity,
                    wastage_quantity,
                    reason,
                    notes,
                    created_by,
                    created_at
                ) VALUES (
                    :inventory_item_id,
                    :product_id,
                    :source_module,
                    :source_reference,
                    :quantity,
                    :wastage_quantity,
                    :reason,
                    :notes,
                    :created_by,
                    NOW()
                )";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inventory_item_id' => $payload['inventory_item_id'],
            ':product_id' => $payload['product_id'],
            ':source_module' => $payload['source_module'],
            ':source_reference' => $payload['source_reference'],
            ':quantity' => $payload['quantity'],
            ':wastage_quantity' => $payload['wastage_quantity'],
            ':reason' => $payload['reason'],
            ':notes' => $payload['notes'],
            ':created_by' => $payload['created_by'],
        ]);
    }

    private function ensureInventoryCategoryFromProduct(array $product): int
    {
        $legacyCategoryId = $product['category_id'] ?? null;
        if ($legacyCategoryId) {
            $stmt = $this->db->prepare("SELECT id FROM inventory_categories WHERE legacy_category_id = ? LIMIT 1");
            $stmt->execute([$legacyCategoryId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return (int) $row['id'];
            }
        }

        $name = $product['category_name'] ?? null;
        if (!$name && $legacyCategoryId) {
            $catStmt = $this->db->prepare("SELECT name FROM categories WHERE id = ? LIMIT 1");
            $catStmt->execute([$legacyCategoryId]);
            $catRow = $catStmt->fetch(PDO::FETCH_ASSOC);
            $name = $catRow['name'] ?? 'General';
        }
        $name = $name ?: 'General';

        $insert = $this->db->prepare("INSERT INTO inventory_categories (
                legacy_category_id,
                name,
                module_scope,
                is_consumable,
                track_expiry,
                track_wastage,
                is_active,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
        $insert->execute([
            $legacyCategoryId,
            $name,
            $this->resolveModuleScope($product),
            $product['is_consumable'] ?? 0,
            $product['track_expiry'] ?? 0,
            $product['track_wastage'] ?? 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function resolveModuleScope(array $product): string
    {
        $scope = $product['module_scope'] ?? null;
        if ($scope) {
            return $scope;
        }

        $tags = strtolower(($product['tags'] ?? '') . ' ' . ($product['category_name'] ?? ''));
        if (strpos($tags, 'restaurant') !== false || strpos($tags, 'kitchen') !== false) {
            return 'restaurant';
        }
        if (strpos($tags, 'housekeep') !== false) {
            return 'housekeeping';
        }
        if (strpos($tags, 'maint') !== false) {
            return 'maintenance';
        }
        return 'retail';
    }

    private function fetchProduct(int $productId): ?array
    {
        $stmt = $this->db->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ? LIMIT 1");
        $stmt->execute([$productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function createInventoryCategoriesTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS inventory_categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            legacy_category_id INT UNSIGNED NULL,
            name VARCHAR(120) NOT NULL,
            module_scope ENUM('retail','restaurant','housekeeping','maintenance','general') DEFAULT 'general',
            is_consumable TINYINT(1) DEFAULT 0,
            track_expiry TINYINT(1) DEFAULT 0,
            track_wastage TINYINT(1) DEFAULT 0,
            parent_id INT UNSIGNED NULL,
            reorder_level DECIMAL(18,4) DEFAULT 0,
            reorder_quantity DECIMAL(18,4) DEFAULT 0,
            gl_account VARCHAR(50) NULL,
            description TEXT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_legacy_category (legacy_category_id),
            INDEX idx_scope (module_scope),
            INDEX idx_parent (parent_id)
        ) ENGINE=InnoDB";
        $this->db->exec($sql);
    }

    private function createProductRecipesTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS product_recipes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id INT UNSIGNED NOT NULL,
            ingredient_product_id INT UNSIGNED NOT NULL,
            quantity DECIMAL(18,4) NOT NULL,
            notes VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_recipe_product (product_id),
            INDEX idx_recipe_ingredient (ingredient_product_id),
            CONSTRAINT fk_recipe_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            CONSTRAINT fk_recipe_ingredient FOREIGN KEY (ingredient_product_id) REFERENCES products(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB";
        $this->db->exec($sql);
    }

    private function createInventoryItemsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS inventory_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id INT UNSIGNED NULL,
            category_id INT UNSIGNED NOT NULL,
            name VARCHAR(200) NOT NULL,
            sku VARCHAR(100) NULL,
            module_scope ENUM('retail','restaurant','housekeeping','maintenance','general') DEFAULT 'general',
            unit VARCHAR(20) DEFAULT 'pcs',
            current_stock DECIMAL(18,4) DEFAULT 0,
            min_stock_level DECIMAL(18,4) DEFAULT 0,
            reorder_level DECIMAL(18,4) DEFAULT 0,
            reorder_quantity DECIMAL(18,4) DEFAULT 0,
            track_expiry TINYINT(1) DEFAULT 0,
            track_wastage TINYINT(1) DEFAULT 0,
            cost_price DECIMAL(15,4) DEFAULT 0,
            last_cost DECIMAL(15,4) DEFAULT 0,
            last_count_at DATETIME NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_product_id (product_id),
            INDEX idx_category (category_id),
            INDEX idx_scope (module_scope),
            CONSTRAINT fk_inventory_items_category FOREIGN KEY (category_id) REFERENCES inventory_categories(id) ON DELETE RESTRICT,
            CONSTRAINT fk_inventory_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
        ) ENGINE=InnoDB";
        $this->db->exec($sql);
    }

    private function createInventoryStockLedgerTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS inventory_stock_ledger (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            inventory_item_id INT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NULL,
            movement_type ENUM('grn','sale','adjustment','transfer','consumption','wastage','manual') NOT NULL,
            direction ENUM('in','out') NOT NULL,
            quantity DECIMAL(18,4) NOT NULL,
            cost_price DECIMAL(15,4) DEFAULT 0,
            reference_type VARCHAR(50) NULL,
            reference_id INT UNSIGNED NULL,
            reference_number VARCHAR(100) NULL,
            source_module ENUM('retail','restaurant','housekeeping','maintenance','general','api') DEFAULT 'general',
            notes VARCHAR(255) NULL,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_item (inventory_item_id),
            INDEX idx_reference (reference_type, reference_id),
            INDEX idx_created (created_at),
            CONSTRAINT fk_inventory_ledger_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
            CONSTRAINT fk_inventory_ledger_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
        ) ENGINE=InnoDB";
        $this->db->exec($sql);
    }

    private function createInventoryConsumptionTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS inventory_consumption_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            inventory_item_id INT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NULL,
            source_module ENUM('retail','restaurant','housekeeping','maintenance','general') DEFAULT 'general',
            source_reference VARCHAR(120) NULL,
            quantity DECIMAL(18,4) NOT NULL,
            wastage_quantity DECIMAL(18,4) DEFAULT 0,
            reason ENUM('sale','task','maintenance','wastage','adjustment') DEFAULT 'sale',
            notes VARCHAR(255) NULL,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_item (inventory_item_id),
            INDEX idx_source (source_module, source_reference),
            CONSTRAINT fk_inventory_consumption_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB";
        $this->db->exec($sql);
    }

    private function ensureProductReorderColumns(): void
    {
        $this->addColumnIfMissing('products', 'reorder_level', "DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER min_stock_level");
        $this->addColumnIfMissing('products', 'reorder_quantity', "DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER reorder_level");
        $this->addColumnIfMissing('products', 'track_expiry', "TINYINT(1) NOT NULL DEFAULT 0 AFTER unit");
        $this->addColumnIfMissing('products', 'track_wastage', "TINYINT(1) NOT NULL DEFAULT 0 AFTER track_expiry");
        $this->addColumnIfMissing('products', 'module_scope', "ENUM('retail','restaurant','housekeeping','maintenance','general') DEFAULT 'retail' AFTER category_id");
        $this->addColumnIfMissing('products', 'tags', "VARCHAR(255) NULL AFTER description");
        $this->addColumnIfMissing('products', 'is_consumable', "TINYINT(1) NOT NULL DEFAULT 0 AFTER module_scope");
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
            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }

    private function getRecipeComponents(int $productId): array
    {
        if (isset($this->recipeCache[$productId])) {
            return $this->recipeCache[$productId];
        }

        try {
            $stmt = $this->db->prepare("SELECT ingredient_product_id, quantity FROM product_recipes WHERE product_id = ?");
            $stmt->execute([$productId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $this->recipeCache[$productId] = $rows;
            return $rows;
        } catch (PDOException $e) {
            return [];
        }
    }
}
