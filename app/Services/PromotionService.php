<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use PDO;
use PDOException;

class PromotionService
{
    private PDO $db;
    private bool $schemaEnsured = false;
    /** @var array<int, string>|null */
    private ?array $tableColumns = null;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function ensureSchema(): void
    {
        if ($this->schemaEnsured) {
            return;
        }

        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS pos_promotions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    product_id INT UNSIGNED NOT NULL,
    promotion_type ENUM('bundle_price','percent','fixed') NOT NULL DEFAULT 'bundle_price',
    min_quantity INT UNSIGNED NOT NULL DEFAULT 1,
    bundle_price DECIMAL(15,2) NULL,
    discount_value DECIMAL(15,2) NULL,
    days_of_week JSON NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pos_promotions_product (product_id),
    INDEX idx_pos_promotions_active (is_active, start_date, end_date)
) ENGINE=InnoDB
SQL;

        try {
            $this->db->exec($sql);
        } catch (PDOException $e) {
            // Fallback for MySQL without JSON support
            if (stripos($e->getMessage(), 'Unknown data type') !== false) {
                $sql = str_replace('days_of_week JSON NULL', 'days_of_week TEXT NULL', $sql);
                $this->db->exec($sql);
            } else {
                throw $e;
            }
        }

        $this->ensureAdditionalColumns();

        $this->schemaEnsured = true;
    }

    private function ensureAdditionalColumns(): void
    {
        $columns = $this->getPromotionColumns();

        if (!in_array('name', $columns, true)) {
            if (in_array('promotion_name', $columns, true)) {
                $this->db->exec('ALTER TABLE pos_promotions CHANGE COLUMN promotion_name name VARCHAR(150) NOT NULL');
            } else {
                $this->db->exec('ALTER TABLE pos_promotions ADD COLUMN name VARCHAR(150) NOT NULL AFTER id');
            }
            $columns = $this->refreshPromotionColumns();
        }

        if (!in_array('promotion_type', $columns, true)) {
            $this->db->exec("ALTER TABLE pos_promotions ADD COLUMN promotion_type ENUM('bundle_price','percent','fixed') NOT NULL DEFAULT 'bundle_price' AFTER name");
            $columns = $this->refreshPromotionColumns();

            if (in_array('discount_type', $columns, true)) {
                $this->db->exec("UPDATE pos_promotions SET promotion_type = CASE WHEN discount_type = 'percent' THEN 'percent' ELSE 'fixed' END");
            }
        }

        if (!in_array('min_quantity', $columns, true)) {
            $this->db->exec('ALTER TABLE pos_promotions ADD COLUMN min_quantity INT UNSIGNED NOT NULL DEFAULT 1 AFTER promotion_type');
            $columns = $this->refreshPromotionColumns();
        }

        if (!in_array('bundle_price', $columns, true)) {
            $this->db->exec('ALTER TABLE pos_promotions ADD COLUMN bundle_price DECIMAL(15,2) NULL AFTER min_quantity');
            $columns = $this->refreshPromotionColumns();
        }

        if (!in_array('discount_value', $columns, true)) {
            $this->db->exec('ALTER TABLE pos_promotions ADD COLUMN discount_value DECIMAL(15,2) NULL AFTER bundle_price');
        } else {
            $this->db->exec('ALTER TABLE pos_promotions MODIFY COLUMN discount_value DECIMAL(15,2) NULL');
        }

        if (!in_array('applicable_modules', $columns, true)) {
            $this->addColumnFallback(
                "ALTER TABLE pos_promotions ADD COLUMN applicable_modules JSON NULL AFTER product_id",
                "ALTER TABLE pos_promotions ADD COLUMN applicable_modules TEXT NULL AFTER product_id"
            );
            $columns = $this->refreshPromotionColumns();
        }

        if (!in_array('days_of_week', $columns, true)) {
            $this->addColumnFallback(
                "ALTER TABLE pos_promotions ADD COLUMN days_of_week JSON NULL AFTER applicable_modules",
                "ALTER TABLE pos_promotions ADD COLUMN days_of_week TEXT NULL AFTER applicable_modules"
            );
            $columns = $this->refreshPromotionColumns();
        }

        if (!in_array('start_time', $columns, true)) {
            $this->db->exec('ALTER TABLE pos_promotions ADD COLUMN start_time TIME NULL AFTER days_of_week');
            $columns = $this->refreshPromotionColumns();
        }

        if (!in_array('end_time', $columns, true)) {
            $this->db->exec('ALTER TABLE pos_promotions ADD COLUMN end_time TIME NULL AFTER start_time');
            $columns = $this->refreshPromotionColumns();
        }

        if (in_array('start_date', $columns, true)) {
            $this->db->exec('ALTER TABLE pos_promotions MODIFY COLUMN start_date DATE NULL');
        } else {
            $this->db->exec('ALTER TABLE pos_promotions ADD COLUMN start_date DATE NULL AFTER end_time');
            $columns = $this->refreshPromotionColumns();
        }

        if (in_array('end_date', $columns, true)) {
            $this->db->exec('ALTER TABLE pos_promotions MODIFY COLUMN end_date DATE NULL');
        } else {
            $this->db->exec('ALTER TABLE pos_promotions ADD COLUMN end_date DATE NULL AFTER start_date');
            $columns = $this->refreshPromotionColumns();
        }

        if (!in_array('priority', $columns, true)) {
            $this->db->exec('ALTER TABLE pos_promotions ADD COLUMN priority INT NOT NULL DEFAULT 100 AFTER is_active');
            $columns = $this->refreshPromotionColumns();
        }

        if (!in_array('created_by', $columns, true)) {
            $this->db->exec('ALTER TABLE pos_promotions ADD COLUMN created_by INT UNSIGNED NULL AFTER is_active');
            $columns = $this->refreshPromotionColumns();
        }
    }

    private function refreshPromotionColumns(): array
    {
        $this->tableColumns = null;
        return $this->getPromotionColumns();
    }

    /**
     * @return array<int, string>
     */
    private function getPromotionColumns(): array
    {
        if ($this->tableColumns !== null) {
            return $this->tableColumns;
        }

        $stmt = $this->db->query("SHOW COLUMNS FROM pos_promotions");
        $this->tableColumns = $stmt ? array_map(static fn($row) => $row['Field'], $stmt->fetchAll(PDO::FETCH_ASSOC)) : [];
        return $this->tableColumns;
    }

    private function addColumnFallback(string $primarySql, string $fallbackSql): void
    {
        try {
            $this->db->exec($primarySql);
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'Unknown data type') !== false) {
                $this->db->exec($fallbackSql);
            } else {
                throw $e;
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPromotions(): array
    {
        $this->ensureSchema();
        $rows = $this->db->query(
            'SELECT p.*, pr.name AS product_name
             FROM pos_promotions p
             LEFT JOIN products pr ON pr.id = p.product_id
             ORDER BY p.created_at DESC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([$this, 'hydrateRow'], $rows);
    }

    public function findById(int $id): ?array
    {
        $this->ensureSchema();
        $stmt = $this->db->prepare(
            'SELECT p.*, pr.name AS product_name
             FROM pos_promotions p
             LEFT JOIN products pr ON pr.id = p.product_id
             WHERE p.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrateRow($row) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getActivePromotions(DateTimeInterface $at = null): array
    {
        $this->ensureSchema();
        $now = $at ? DateTimeImmutable::createFromInterface($at) : new DateTimeImmutable('now');
        $date = $now->format('Y-m-d');
        $time = $now->format('H:i:s');

        $stmt = $this->db->prepare(
            'SELECT p.*, pr.name AS product_name
             FROM pos_promotions p
             LEFT JOIN products pr ON pr.id = p.product_id
             WHERE p.is_active = 1
               AND (p.start_date IS NULL OR p.start_date <= ?)
               AND (p.end_date IS NULL OR p.end_date >= ?)' .
             ' ORDER BY p.created_at DESC'
        );
        $stmt->execute([$date, $date]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $dayOfWeek = (int)$now->format('w'); // 0 (Sun) - 6 (Sat)

        $active = [];
        foreach ($rows as $row) {
            $hydrated = $this->hydrateRow($row);
            if (!$this->isWithinDailyWindow($hydrated, $time)) {
                continue;
            }
            if (!$this->isWithinDayFilter($hydrated, $dayOfWeek)) {
                continue;
            }
            $active[] = $hydrated;
        }

        return $active;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function savePromotion(array $data, ?int $promotionId, ?int $userId = null): array
    {
        $this->ensureSchema();

        $payload = [
            'name' => trim((string)($data['name'] ?? '')),
            'description' => trim((string)($data['description'] ?? '')) ?: null,
            'product_id' => (int)($data['product_id'] ?? 0),
            'promotion_type' => in_array(($data['promotion_type'] ?? 'bundle_price'), ['bundle_price', 'percent', 'fixed'], true)
                ? $data['promotion_type']
                : 'bundle_price',
            'min_quantity' => max(1, (int)($data['min_quantity'] ?? 1)),
            'bundle_price' => isset($data['bundle_price']) ? (float)$data['bundle_price'] : null,
            'discount_value' => isset($data['discount_value']) ? (float)$data['discount_value'] : null,
            'applicable_modules' => $this->encodeModules($data['applicable_modules'] ?? null),
            'days_of_week' => $this->encodeDaysOfWeek($data['days_of_week'] ?? null),
            'start_time' => $this->normalizeTime($data['start_time'] ?? null),
            'end_time' => $this->normalizeTime($data['end_time'] ?? null),
            'start_date' => $this->normalizeDate($data['start_date'] ?? null),
            'end_date' => $this->normalizeDate($data['end_date'] ?? null),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ];

        if ($payload['name'] === '' || $payload['product_id'] <= 0) {
            throw new Exception('Promotion name and product are required');
        }

        if ($payload['promotion_type'] === 'bundle_price') {
            if ($payload['bundle_price'] === null) {
                throw new Exception('Bundle price is required for bundle promotions');
            }
        } elseif ($payload['discount_value'] === null) {
            throw new Exception('Discount value is required for this promotion type');
        }

        if ($payload['start_date'] && $payload['end_date'] && $payload['end_date'] < $payload['start_date']) {
            throw new Exception('End date cannot be before start date');
        }

        if ($promotionId) {
            $sets = [];
            $params = [];
            foreach ($payload as $column => $value) {
                $sets[] = "$column = ?";
                $params[] = $value;
            }
            $params[] = $promotionId;

            $sql = 'UPDATE pos_promotions SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $result = $this->findById($promotionId);
        } else {
            $columns = implode(', ', array_keys($payload));
            $placeholders = rtrim(str_repeat('?,', count($payload)), ',');
            $sql = "INSERT INTO pos_promotions ($columns, created_by) VALUES ($placeholders, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([...array_values($payload), $userId]);

            $result = $this->findById((int)$this->db->lastInsertId());
        }

        return $result ?? [];
    }

    public function deletePromotion(int $id): void
    {
        $this->ensureSchema();
        $stmt = $this->db->prepare('DELETE FROM pos_promotions WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function togglePromotion(int $id, bool $isActive): void
    {
        $this->ensureSchema();
        $stmt = $this->db->prepare('UPDATE pos_promotions SET is_active = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$isActive ? 1 : 0, $id]);
    }

    /**
     * Check if promotion is active at given time window.
     *
     * @param array<string, mixed> $promotion
     */
    private function isWithinDailyWindow(array $promotion, string $time): bool
    {
        $start = $promotion['start_time'] ?? null;
        $end = $promotion['end_time'] ?? null;

        if (!$start && !$end) {
            return true;
        }

        if ($start && $end) {
            if ($start <= $end) {
                return $time >= $start && $time <= $end;
            }
            // Overnight wrap (e.g., 22:00 - 02:00)
            return $time >= $start || $time <= $end;
        }

        if ($start) {
            return $time >= $start;
        }

        if ($end) {
            return $time <= $end;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $promotion
     */
    private function isWithinDayFilter(array $promotion, int $dayOfWeek): bool
    {
        $days = $promotion['days_of_week'] ?? null;
        if (!$days || !is_array($days) || empty($days)) {
            return true;
        }
        return in_array($dayOfWeek, $days, true);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrateRow(array $row): array
    {
        if (isset($row['days_of_week']) && $row['days_of_week'] !== null && $row['days_of_week'] !== '') {
            $decoded = json_decode($row['days_of_week'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $row['days_of_week'] = $decoded;
            }
        } else {
            $row['days_of_week'] = [];
        }

        if (isset($row['bundle_price'])) {
            $row['bundle_price'] = $row['bundle_price'] !== null ? (float)$row['bundle_price'] : null;
        }
        if (isset($row['discount_value'])) {
            $row['discount_value'] = $row['discount_value'] !== null ? (float)$row['discount_value'] : null;
        }
        if (isset($row['min_quantity'])) {
            $row['min_quantity'] = (int)$row['min_quantity'];
        }
        if (isset($row['product_id'])) {
            $row['product_id'] = (int)$row['product_id'];
        }
        if (isset($row['is_active'])) {
            $row['is_active'] = (int)$row['is_active'];
        }
        if (isset($row['priority'])) {
            $row['priority'] = (int)$row['priority'];
        }

        if (isset($row['applicable_modules']) && $row['applicable_modules'] !== null && $row['applicable_modules'] !== '') {
            $decoded = json_decode($row['applicable_modules'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $row['applicable_modules'] = array_values(array_filter(array_map('strval', (array)$decoded)));
            } else {
                $row['applicable_modules'] = [];
            }
        } else {
            $row['applicable_modules'] = [];
        }

        return $row;
    }

    private function encodeDaysOfWeek($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $parts = array_filter(array_map('trim', explode(',', $value)), 'strlen');
            $ints = [];
            foreach ($parts as $part) {
                if (is_numeric($part)) {
                    $ints[] = (int)$part;
                }
            }
            return json_encode(array_values(array_unique($ints)));
        }

        if (is_array($value)) {
            $ints = [];
            foreach ($value as $part) {
                if (is_numeric($part)) {
                    $ints[] = (int)$part;
                }
            }
            return !empty($ints) ? json_encode(array_values(array_unique($ints))) : null;
        }

        return null;
    }

    private function normalizeTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
            return strlen($value) === 5 ? $value . ':00' : $value;
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('H:i:s', $timestamp) : null;
    }

    private function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private function encodeModules($value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $modules = [];
        if (is_string($value)) {
            $parts = array_filter(array_map('trim', explode(',', $value)), 'strlen');
            foreach ($parts as $part) {
                $modules[] = strtolower($part);
            }
        } elseif (is_array($value)) {
            foreach ($value as $part) {
                if ($part === null) {
                    continue;
                }
                $modules[] = strtolower(trim((string)$part));
            }
        }

        $modules = array_values(array_unique(array_filter($modules, 'strlen')));

        return !empty($modules) ? json_encode($modules) : null;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{items: array<int, array<string, mixed>>, total_discount: float, applied: array<int, array<string, mixed>>}
     */
    public function applyPromotionsToItems(array $items, string $moduleScope = 'pos'): array
    {
        $moduleScope = strtolower(trim($moduleScope)) ?: 'pos';
        $promotions = $this->getActivePromotionsForModule($moduleScope);
        if (empty($promotions) || empty($items)) {
            return [
                'items' => $items,
                'total_discount' => 0.0,
                'applied' => [],
            ];
        }

        $byProduct = [];
        foreach ($promotions as $promotion) {
            $byProduct[$promotion['product_id']][] = $promotion;
        }

        $quantities = [];
        $unitPrices = [];
        foreach ($items as $item) {
            if (!isset($item['product_id'], $item['qty'], $item['price'])) {
                continue;
            }
            $productId = (int)$item['product_id'];
            $qty = (float)$item['qty'];
            if ($qty <= 0) {
                continue;
            }
            $quantities[$productId] = ($quantities[$productId] ?? 0.0) + $qty;
            // prefer latest unit price
            $unitPrices[$productId] = (float)$item['price'];
        }

        $allocations = [];
        $applied = [];

        foreach ($quantities as $productId => $quantity) {
            if (empty($byProduct[$productId])) {
                continue;
            }
            $unitPrice = $unitPrices[$productId] ?? 0.0;
            $best = null;
            foreach ($byProduct[$productId] as $promotion) {
                $calc = $this->calculatePromotionDiscount($promotion, $unitPrice, $quantity);
                if ($calc === null) {
                    continue;
                }
                if ($best === null || $calc['discount'] > $best['discount']) {
                    $best = $calc;
                }
            }

            if ($best === null || $best['discount'] <= 0) {
                continue;
            }

            $allocations[$productId] = [
                'remaining_units' => $best['discount_units'],
                'per_unit_discount' => $best['per_unit_discount'],
                'discount' => $best['discount'],
            ];
            $applied[] = [
                'promotion_id' => $best['promotion']['id'],
                'product_id' => $productId,
                'discount' => $best['discount'],
                'details' => $best['details'],
            ];
        }

        if (empty($allocations)) {
            return [
                'items' => $items,
                'total_discount' => 0.0,
                'applied' => [],
            ];
        }

        $totalDiscount = 0.0;
        foreach ($items as $index => $item) {
            $productId = isset($item['product_id']) ? (int)$item['product_id'] : null;
            if ($productId === null || empty($allocations[$productId])) {
                continue;
            }
            $lineQty = (float)($item['qty'] ?? 0);
            if ($lineQty <= 0) {
                continue;
            }

            $allocation = &$allocations[$productId];
            if ($allocation['remaining_units'] <= 0) {
                continue;
            }

            $discountableQty = min($lineQty, $allocation['remaining_units']);
            if ($discountableQty <= 0) {
                continue;
            }

            $lineDiscount = $discountableQty * $allocation['per_unit_discount'];
            $allocation['remaining_units'] -= $discountableQty;
            $allocation['discount'] -= $lineDiscount;

            $items[$index]['discount'] = round((isset($item['discount']) ? (float)$item['discount'] : 0.0) + $lineDiscount, 2);
            $totalDiscount += $lineDiscount;

            if ($allocation['remaining_units'] <= 0 || $allocation['discount'] <= 0.0001) {
                unset($allocations[$productId]);
            }
        }

        return [
            'items' => $items,
            'total_discount' => round($totalDiscount, 2),
            'applied' => $applied,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getActivePromotionsForModule(?string $module = null): array
    {
        $moduleKey = $module ? strtolower(trim($module)) : null;
        $promotions = $this->getActivePromotions();
        if ($moduleKey === null) {
            return $promotions;
        }

        return array_values(array_filter($promotions, static function ($promotion) use ($moduleKey) {
            $modules = $promotion['applicable_modules'] ?? [];
            if (empty($modules)) {
                return true;
            }
            return in_array($moduleKey, $modules, true);
        }));
    }

    private function calculatePromotionDiscount(array $promotion, float $unitPrice, float $quantity): ?array
    {
        $minQty = max(1, (int)($promotion['min_quantity'] ?? 1));
        if ($quantity < $minQty || $unitPrice <= 0) {
            return null;
        }

        $type = $promotion['promotion_type'];
        $discount = 0.0;
        $discountUnits = 0.0;
        $perUnitDiscount = 0.0;
        $details = '';

        if ($type === 'bundle_price') {
            if (!isset($promotion['bundle_price'])) {
                return null;
            }
            $bundlePrice = (float)$promotion['bundle_price'];
            $groups = (int) floor($quantity / $minQty);
            if ($groups <= 0) {
                return null;
            }
            $regular = $unitPrice * $minQty;
            $savingsPerBundle = max(0.0, $regular - $bundlePrice);
            if ($savingsPerBundle <= 0) {
                return null;
            }
            $discount = $savingsPerBundle * $groups;
            $discountUnits = $groups * $minQty;
            $perUnitDiscount = $savingsPerBundle / $minQty;
            $details = sprintf('%d bundle(s) applied', $groups);
        } elseif ($type === 'percent') {
            $percent = max(0.0, min(100.0, (float)($promotion['discount_value'] ?? 0)));
            if ($percent <= 0) {
                return null;
            }
            $discountUnits = $quantity;
            $perUnitDiscount = $unitPrice * ($percent / 100);
            $discount = $perUnitDiscount * $discountUnits;
            $details = sprintf('%.1f%% off', $percent);
        } else { // fixed
            $value = max(0.0, (float)($promotion['discount_value'] ?? 0));
            if ($value <= 0) {
                return null;
            }
            $perUnitDiscount = min($value, $unitPrice);
            $discountUnits = $quantity;
            $discount = $perUnitDiscount * $discountUnits;
            $details = sprintf('%s off per unit', number_format($perUnitDiscount, 2));
        }

        if ($discount <= 0 || $discountUnits <= 0 || $perUnitDiscount <= 0) {
            return null;
        }

        return [
            'discount' => $discount,
            'discount_units' => $discountUnits,
            'per_unit_discount' => $perUnitDiscount,
            'promotion' => $promotion,
            'details' => $details,
        ];
    }
}
