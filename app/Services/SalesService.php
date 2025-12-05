<?php

namespace App\Services;

use App\Services\Inventory\InventoryService;
use PDO;
use PDOException;
use Throwable;

/**
 * Sales Service
 * Business logic for sales transactions with idempotency support.
 */
class SalesService
{
    private PDO $db;
    private AccountingService $accountingService;
    private InventoryService $inventoryService;
    private bool $roomChargePaymentEnsured = false;
    private bool $salePromotionsTableEnsured = false;
    private bool $salesIdempotencyEnsured = false;
    private bool $salesMobileMoneyColumnsEnsured = false;
    private ?RoomBookingService $roomBookingService = null;

    public function __construct(PDO $db, AccountingService $accountingService, ?InventoryService $inventoryService = null)
    {
        $this->db = $db;
        $this->accountingService = $accountingService;
        $this->inventoryService = $inventoryService ?? new InventoryService($db);
    }

    private function ensureRoomChargePaymentMethod(): void
    {
        if ($this->roomChargePaymentEnsured) {
            return;
        }

        if ($this->db->inTransaction()) {
            // Avoid running DDL inside active transaction; assume schema already prepared.
            $this->roomChargePaymentEnsured = true;
            return;
        }

        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM sales LIKE 'payment_method'");
            $column = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

            if ($column && isset($column['Type']) && strpos($column['Type'], 'room_charge') === false) {
                $this->db->exec("ALTER TABLE sales MODIFY payment_method ENUM('cash','card','mobile_money','bank_transfer','room_charge') DEFAULT 'cash'");
            }
        } catch (PDOException $e) {
            // Ignore errors; insertion will fail later if schema truly incompatible
        }

        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM sales LIKE 'room_booking_id'");
            $column = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

            if (!$column) {
                $this->db->exec("ALTER TABLE sales ADD COLUMN room_booking_id INT NULL AFTER payment_method");
                $this->db->exec("ALTER TABLE sales ADD INDEX idx_sales_room_booking (room_booking_id)");
            }
        } catch (PDOException $e) {
            // Ignore errors; insertion will fail later if schema truly incompatible
        }

        $this->roomChargePaymentEnsured = true;
    }

    private function ensureSalesIdempotencySchema(): void
    {
        if ($this->salesIdempotencyEnsured) {
            return;
        }

        if ($this->db->inTransaction()) {
            $this->salesIdempotencyEnsured = true;
            return;
        }

        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM sales LIKE 'external_id'");
            $column = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if (!$column) {
                $this->db->exec("ALTER TABLE sales ADD COLUMN external_id VARCHAR(100) NULL AFTER id");
            }
        } catch (PDOException $e) {
            // ignore
        }

        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM sales LIKE 'device_id'");
            $column = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if (!$column) {
                $this->db->exec("ALTER TABLE sales ADD COLUMN device_id VARCHAR(100) NULL AFTER external_id");
            }
        } catch (PDOException $e) {
            // ignore
        }

        try {
            $stmt = $this->db->query("SHOW INDEX FROM sales WHERE Key_name = 'ux_sales_external_id'");
            $index = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if (!$index) {
                $this->db->exec("ALTER TABLE sales ADD UNIQUE KEY ux_sales_external_id (external_id)");
            }
        } catch (PDOException $e) {
            // ignore
        }

        $this->salesIdempotencyEnsured = true;
    }

    private function ensureSalesMobileMoneyColumns(): void
    {
        if ($this->salesMobileMoneyColumnsEnsured) {
            return;
        }

        if ($this->db->inTransaction()) {
            $this->salesMobileMoneyColumnsEnsured = true;
            return;
        }

        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM sales LIKE 'mobile_money_phone'");
            $column = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if (!$column) {
                $this->db->exec("ALTER TABLE sales ADD COLUMN mobile_money_phone VARCHAR(30) NULL AFTER customer_phone");
            }
        } catch (PDOException $e) {
            // ignore
        }

        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM sales LIKE 'mobile_money_reference'");
            $column = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if (!$column) {
                $this->db->exec("ALTER TABLE sales ADD COLUMN mobile_money_reference VARCHAR(100) NULL AFTER mobile_money_phone");
                $this->db->exec("ALTER TABLE sales ADD INDEX idx_sales_mobile_reference (mobile_money_reference)");
            }
        } catch (PDOException $e) {
            // ignore
        }

        $this->salesMobileMoneyColumnsEnsured = true;
    }

    /**
     * Create sale with idempotency support
     * If external_id exists, returns existing sale
     */
    public function createSale(array $data): array
    {
        $this->ensureRoomChargePaymentMethod();
        $this->ensureSalesIdempotencySchema();
        $this->ensureSalesMobileMoneyColumns();

        $externalId = $data['external_id'] ?? null;
        
        // Check if sale already exists by external_id (idempotent)
        if ($externalId) {
            $existing = $this->findByExternalId($externalId);
            if ($existing) {
                return [
                    'success' => true,
                    'sale_id' => $existing['id'],
                    'sale_number' => $existing['sale_number'],
                    'message' => 'Sale already exists',
                    'status_code' => 200,
                    'is_duplicate' => true
                ];
            }
        }

        $manageTransaction = !$this->db->inTransaction();

        try {
            if ($manageTransaction) {
                $this->db->beginTransaction();
            }

            // Generate sale number
            $saleNumber = $this->generateSaleNumber();

            // Calculate totals
            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $subtotal += $item['qty'] * $item['price'];
            }

            $taxAmount = $data['totals']['tax'] ?? 0;
            $discountAmount = $data['totals']['discount'] ?? 0;
            $grandTotal = $data['totals']['grand'] ?? $subtotal + $taxAmount - $discountAmount;

            $paymentMethod = $data['payment_method'] ?? 'cash';
            $roomBookingId = isset($data['room_booking_id']) ? (int)$data['room_booking_id'] : null;
            if ($roomBookingId !== null && $roomBookingId <= 0) {
                $roomBookingId = null;
            }

            if ($paymentMethod === 'room_charge') {
                if (!$roomBookingId) {
                    throw new PDOException('Room booking is required when charging to room.');
                }

                $booking = $this->getRoomBookingService()->getRoomBooking($roomBookingId);
                if (!$booking || !in_array($booking['status'], ['checked_in'], true)) {
                    throw new PDOException('Room booking must be checked in before posting room charges.');
                }

                $amountPaid = isset($data['amount_paid']) ? (float)$data['amount_paid'] : 0.0;
                $changeAmount = 0.0;
            } else {
                if ($roomBookingId) {
                    throw new PDOException('Room booking can only be provided for room charge payments.');
                }

                $amountPaid = isset($data['amount_paid']) ? (float)$data['amount_paid'] : $grandTotal;
                $changeAmount = isset($data['change_amount']) ? (float)$data['change_amount'] : max(0, $amountPaid - $grandTotal);
                $roomBookingId = null;
            }

            if ($amountPaid < 0) {
                throw new PDOException('Amount paid cannot be negative.');
            }

            // Get register and session from user's session if available
            $registerId = $data['register_id'] ?? $_SESSION['register_id'] ?? null;
            $sessionId = $data['session_id'] ?? $_SESSION['register_session_id'] ?? null;

            // Insert sale
            $sql = "INSERT INTO sales 
                    (external_id, sale_number, user_id, location_id, register_id, session_id, device_id, 
                     customer_name, customer_phone, subtotal, tax_amount, 
                     discount_amount, total_amount, amount_paid, change_amount, 
                     payment_method, room_booking_id, notes, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $includeMobileMoney = $paymentMethod === 'mobile_money';
            $this->ensureSalesMobileMoneyColumns();

            if ($includeMobileMoney) {
                $sql = "INSERT INTO sales 
                    (external_id, sale_number, user_id, location_id, register_id, session_id, device_id, 
                     customer_name, customer_phone, mobile_money_phone, mobile_money_reference,
                     subtotal, tax_amount, 
                     discount_amount, total_amount, amount_paid, change_amount, 
                     payment_method, room_booking_id, notes, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            }

            $stmt = $this->db->prepare($sql);

            $userId = $data['user_id'] ?? $_SESSION['user_id'] ?? 1;

            $locationId = $this->resolveLocationId($data['location_id'] ?? null, $userId);

            $params = [
                $externalId,
                $saleNumber,
                $userId,
                $locationId,
                $registerId,
                $sessionId,
                $data['device_id'] ?? null,
                $data['customer_name'] ?? null,
                $data['customer_phone'] ?? null,
            ];

            if ($includeMobileMoney) {
                $params[] = $data['mobile_money_phone'] ?? null;
                $params[] = $data['mobile_money_reference'] ?? null;
            }

            $params = array_merge($params, [
                $subtotal,
                $taxAmount,
                $discountAmount,
                $grandTotal,
                $amountPaid,
                $changeAmount,
                $paymentMethod,
                $roomBookingId,
                $data['notes'] ?? null,
                $data['created_at'] ?? date('Y-m-d H:i:s')
            ]);

            $stmt->execute($params);

            $saleId = (int) $this->db->lastInsertId();

            // Insert sale items
            $moduleScope = $data['module_scope'] ?? 'retail';

            foreach ($data['items'] as $item) {
                $this->addSaleItem($saleId, $item);
            }

            $this->saveSalePromotions($saleId, $data['promotions'] ?? []);

            // Update inventory via unified inventory service
            $this->updateInventory(
                $data['items'],
                $saleNumber,
                $saleId,
                (int) $userId,
                $moduleScope
            );

            if ($moduleScope === 'restaurant') {
                foreach ($data['items'] as $item) {
                    try {
                        $this->inventoryService->consumeRecipeItems((int) $item['product_id'], (float) $item['qty'], [
                            'reference_type' => 'sale',
                            'reference_id' => $saleId,
                            'reference_number' => $saleNumber,
                            'source_module' => 'restaurant',
                            'user_id' => (int) $userId,
                            'notes' => 'Recipe consumption for sale #' . $saleNumber,
                        ]);
                    } catch (Throwable $inventoryError) {
                        error_log('Recipe consumption failed for sale ' . $saleNumber . ': ' . $inventoryError->getMessage());
                    }
                }
            }

            $accountingWarning = null;
            try {
                $this->accountingService->postSale($saleId, [
                    'subtotal' => $subtotal,
                    'tax' => $taxAmount,
                    'discount' => $discountAmount,
                    'total' => $grandTotal,
                    'payment_method' => $paymentMethod,
                    'amount_paid' => $amountPaid,
                    'change_amount' => $changeAmount,
                ]);
            } catch (Throwable $accountingException) {
                $accountingWarning = $accountingException->getMessage();
                error_log(sprintf('Accounting post failed for sale %s: %s', $saleNumber, $accountingWarning));
            }

            if ($paymentMethod === 'room_charge' && $roomBookingId) {
                $description = trim((string)($data['room_charge_description'] ?? ''));
                if ($description === '') {
                    $description = 'Room service order ' . $saleNumber;
                }

                $this->getRoomBookingService()->addFolioEntry($roomBookingId, [
                    'item_type' => 'service',
                    'description' => $description,
                    'amount' => $grandTotal,
                    'quantity' => 1,
                    'date_charged' => date('Y-m-d'),
                    'reference_id' => $saleId,
                    'reference_source' => 'sales',
                ], $userId ? (int)$userId : null);
            }

            // Update register session totals if session is active
            if ($sessionId) {
                $this->updateRegisterSessionTotals($sessionId, $grandTotal, $paymentMethod);
            }

            if ($manageTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }

            return [
                'success' => true,
                'sale_id' => $saleId,
                'sale_number' => $saleNumber,
                'message' => 'Sale created successfully',
                'status_code' => 201,
                'is_duplicate' => false,
                'accounting_warning' => $accountingWarning,
            ];

        } catch (PDOException $e) {
            if ($manageTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            // Check if it's a duplicate key error
            if ($e->getCode() == 23000) {
                // Try to find by external_id again
                if ($externalId) {
                    $existing = $this->findByExternalId($externalId);
                    if ($existing) {
                        return [
                            'success' => true,
                            'sale_id' => $existing['id'],
                            'sale_number' => $existing['sale_number'],
                            'message' => 'Sale already exists',
                            'status_code' => 200,
                            'is_duplicate' => true
                        ];
                    }
                }
            }

            throw $e;
        }
    }

    private function ensureSalePromotionsTable(): void
    {
        if ($this->salePromotionsTableEnsured) {
            return;
        }

        $this->db->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS sale_promotions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                sale_id INT UNSIGNED NOT NULL,
                promotion_id INT UNSIGNED NULL,
                promotion_name VARCHAR(150) NULL,
                product_id INT UNSIGNED NULL,
                product_name VARCHAR(150) NULL,
                discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                details VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sale_promotions_sale (sale_id),
                INDEX idx_sale_promotions_promo (promotion_id),
                CONSTRAINT fk_sale_promotions_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
            ) ENGINE=InnoDB
        SQL);

        $this->salePromotionsTableEnsured = true;
    }

    private function saveSalePromotions(int $saleId, array $promotions): void
    {
        if ($saleId <= 0) {
            return;
        }

        $this->ensureSalePromotionsTable();

        $this->db->prepare('DELETE FROM sale_promotions WHERE sale_id = ?')->execute([$saleId]);

        if (empty($promotions)) {
            return;
        }

        $insert = $this->db->prepare(
            'INSERT INTO sale_promotions (sale_id, promotion_id, promotion_name, product_id, product_name, discount_amount, details)
             VALUES (:sale_id, :promotion_id, :promotion_name, :product_id, :product_name, :discount_amount, :details)'
        );

        foreach ($promotions as $promotion) {
            if (!is_array($promotion)) {
                continue;
            }

            $discount = isset($promotion['discount']) ? (float)$promotion['discount'] : 0.0;
            if ($discount <= 0) {
                continue;
            }

            $insert->execute([
                ':sale_id' => $saleId,
                ':promotion_id' => isset($promotion['promotion_id']) ? (int)$promotion['promotion_id'] ?: null : null,
                ':promotion_name' => isset($promotion['promotion_name']) ? (string)$promotion['promotion_name'] : null,
                ':product_id' => isset($promotion['product_id']) ? (int)$promotion['product_id'] ?: null : null,
                ':product_name' => isset($promotion['product_name']) ? (string)$promotion['product_name'] : null,
                ':discount_amount' => $discount,
                ':details' => isset($promotion['details']) ? (string)$promotion['details'] : null,
            ]);
        }
    }

    private function getRoomBookingService(): RoomBookingService
    {
        if ($this->roomBookingService === null) {
            $this->roomBookingService = new RoomBookingService($this->db);
        }

        return $this->roomBookingService;
    }

    /**
     * Find sale by external_id
     */
    private function findByExternalId(string $externalId): ?array
    {
        $sql = "SELECT * FROM sales WHERE external_id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$externalId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    private function resolveLocationId($preferredLocationId, ?int $userId): ?int
    {
        $preferred = $this->validateLocationId($preferredLocationId);
        if ($preferred !== null) {
            return $preferred;
        }

        if ($userId !== null) {
            $stmt = $this->db->prepare('SELECT location_id FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $userLocation = $stmt->fetchColumn();
            $validated = $this->validateLocationId($userLocation);
            if ($validated !== null) {
                return $validated;
            }
        }

        $stmt = $this->db->query('SELECT id FROM locations ORDER BY id ASC LIMIT 1');
        $fallback = $stmt ? $stmt->fetchColumn() : false;
        $validatedFallback = $this->validateLocationId($fallback);
        return $validatedFallback;
    }

    private function validateLocationId($locationId): ?int
    {
        $id = is_numeric($locationId) ? (int) $locationId : null;
        if ($id === null || $id <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT id FROM locations WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $exists = $stmt->fetchColumn();
        return $exists ? $id : null;
    }

    /**
     * Add sale item
     */
    private function addSaleItem(int $saleId, array $item): void
    {
        // Get product details
        $product = $this->getProduct($item['product_id']);
        
        $lineTotal = $item['qty'] * $item['price'];
        $discount = $item['discount'] ?? 0;
        $taxRate = $item['tax_rate'] ?? $product['tax_rate'] ?? 0;

        $sql = "INSERT INTO sale_items 
                (sale_id, product_id, product_name, quantity, unit_price, 
                 tax_rate, discount_amount, total_price) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                quantity = quantity + VALUES(quantity),
                total_price = total_price + VALUES(total_price)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $saleId,
            $item['product_id'],
            $product['name'] ?? 'Unknown Product',
            $item['qty'],
            $item['price'],
            $taxRate,
            $discount,
            $lineTotal - $discount
        ]);
    }

    /**
     * Update inventory after sale
     */
    private function updateInventory(array $items, string $saleNumber, int $saleId, int $userId, string $moduleScope): void
    {
        foreach ($items as $item) {
            $this->inventoryService->recordOutboundMovement((int) $item['product_id'], (float) $item['qty'], [
                'movement_type' => 'sale',
                'reference_type' => 'sale',
                'reference_id' => $saleId,
                'reference_number' => $saleNumber,
                'source_module' => $moduleScope,
                'notes' => 'Sale #' . $saleNumber,
                'user_id' => $userId,
                'log_consumption' => in_array($moduleScope, ['restaurant', 'housekeeping', 'maintenance'], true),
                'consumption_reason' => $moduleScope === 'restaurant' ? 'sale' : 'task',
            ]);
        }
    }

    /**
     * Get product by ID
     */
    private function getProduct(int $productId): ?array
    {
        $sql = "SELECT * FROM products WHERE id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$productId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Generate unique sale number
     */
    private function generateSaleNumber(): string
    {
        $prefix = 'SAL';
        $date = date('Ymd');
        
        // Get last sale number for today
        $sql = "SELECT sale_number FROM sales 
                WHERE sale_number LIKE ? 
                ORDER BY id DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(["{$prefix}-{$date}-%"]);
        $last = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($last) {
            $parts = explode('-', $last['sale_number']);
            $sequence = (int) end($parts) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $date, $sequence);
    }

    /**
     * Get sales with delta support (for polling)
     */
    public function getSalesSince(string $since, int $locationId = null): array
    {
        $sql = "SELECT * FROM sales WHERE updated_at > ?";
        $params = [$since];

        if ($locationId) {
            $sql .= " AND location_id = ?";
            $params[] = $locationId;
        }

        $sql .= " ORDER BY updated_at ASC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update register session totals after a sale
     */
    private function updateRegisterSessionTotals(int $sessionId, float $amount, string $paymentMethod): void
    {
        try {
            // Map payment method to column
            $paymentColumn = match($paymentMethod) {
                'cash' => 'cash_sales',
                'card', 'credit_card', 'debit_card' => 'card_sales',
                'mobile_money', 'mpesa' => 'mobile_sales',
                default => 'cash_sales'
            };

            $sql = "UPDATE register_sessions SET 
                    {$paymentColumn} = {$paymentColumn} + ?,
                    total_sales = total_sales + ?,
                    transaction_count = transaction_count + 1
                    WHERE id = ? AND status = 'open'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$amount, $amount, $sessionId]);

            // Also update register current balance for cash payments
            if ($paymentMethod === 'cash') {
                $updateRegister = $this->db->prepare("
                    UPDATE registers r
                    JOIN register_sessions rs ON r.id = rs.register_id
                    SET r.current_balance = r.current_balance + ?
                    WHERE rs.id = ?
                ");
                $updateRegister->execute([$amount, $sessionId]);
            }
        } catch (Throwable $e) {
            error_log('Failed to update register session totals: ' . $e->getMessage());
        }
    }
}
