<?php

namespace App\Services;

use PDO;
use PDOException;

/**
 * Sales Service
 * Business logic for sales transactions with idempotency support
 */
class SalesService
{
    private PDO $db;
    private AccountingService $accountingService;

    public function __construct(PDO $db, AccountingService $accountingService)
    {
        $this->db = $db;
        $this->accountingService = $accountingService;
    }

    /**
     * Create sale with idempotency support
     * If external_id exists, returns existing sale
     */
    public function createSale(array $data): array
    {
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

        try {
            $this->db->beginTransaction();

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

            // Insert sale
            $sql = "INSERT INTO sales 
                    (external_id, sale_number, user_id, location_id, device_id, 
                     customer_name, customer_phone, subtotal, tax_amount, 
                     discount_amount, total_amount, amount_paid, change_amount, 
                     payment_method, notes, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $externalId,
                $saleNumber,
                $data['user_id'] ?? $_SESSION['user_id'] ?? 1,
                $data['location_id'] ?? 1,
                $data['device_id'] ?? null,
                $data['customer_name'] ?? null,
                $data['customer_phone'] ?? null,
                $subtotal,
                $taxAmount,
                $discountAmount,
                $grandTotal,
                $data['amount_paid'] ?? $grandTotal,
                $data['change_amount'] ?? 0,
                $data['payment_method'] ?? 'cash',
                $data['notes'] ?? null,
                $data['created_at'] ?? date('Y-m-d H:i:s')
            ]);

            $saleId = (int) $this->db->lastInsertId();

            // Insert sale items
            foreach ($data['items'] as $item) {
                $this->addSaleItem($saleId, $item);
            }

            // Update inventory
            $this->updateInventory($data['items']);

            // Post to accounting
            $this->accountingService->postSale($saleId, [
                'subtotal' => $subtotal,
                'tax' => $taxAmount,
                'discount' => $discountAmount,
                'total' => $grandTotal,
                'payment_method' => $data['payment_method'] ?? 'cash'
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'sale_id' => $saleId,
                'sale_number' => $saleNumber,
                'message' => 'Sale created successfully',
                'status_code' => 201,
                'is_duplicate' => false
            ];

        } catch (PDOException $e) {
            $this->db->rollBack();
            
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
    private function updateInventory(array $items): void
    {
        foreach ($items as $item) {
            $sql = "UPDATE products 
                    SET stock_quantity = stock_quantity - ? 
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$item['qty'], $item['product_id']]);
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
}
