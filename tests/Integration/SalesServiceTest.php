<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Services\SalesService;
use App\Services\AccountingService;
use PDO;

class SalesServiceTest extends TestCase
{
    private PDO $db;
    private SalesService $salesService;

    protected function setUp(): void
    {
        // Setup test database connection
        $this->db = new PDO(
            'mysql:host=127.0.0.1;dbname=wapos_test',
            'root',
            '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $accountingService = new AccountingService($this->db);
        $this->salesService = new SalesService($this->db, $accountingService);

        // Clean tables
        $this->db->exec("TRUNCATE TABLE sales");
        $this->db->exec("TRUNCATE TABLE sale_items");
        $this->db->exec("TRUNCATE TABLE journal_entries");
        $this->db->exec("TRUNCATE TABLE journal_entry_lines");
    }

    public function testCreateSale()
    {
        $data = [
            'external_id' => 'test-uuid-001',
            'user_id' => 1,
            'location_id' => 1,
            'items' => [
                [
                    'product_id' => 1,
                    'qty' => 2,
                    'price' => 10.00
                ]
            ],
            'totals' => [
                'sub' => 20.00,
                'tax' => 3.20,
                'discount' => 0,
                'grand' => 23.20
            ],
            'payment_method' => 'cash'
        ];

        $result = $this->salesService->createSale($data);

        $this->assertTrue($result['success']);
        $this->assertEquals(201, $result['status_code']);
        $this->assertArrayHasKey('sale_id', $result);
        $this->assertArrayHasKey('sale_number', $result);
    }

    public function testIdempotentSaleCreation()
    {
        $data = [
            'external_id' => 'test-uuid-002',
            'user_id' => 1,
            'items' => [
                ['product_id' => 1, 'qty' => 1, 'price' => 5.00]
            ],
            'totals' => [
                'sub' => 5.00,
                'tax' => 0.80,
                'grand' => 5.80
            ]
        ];

        // First call - creates sale
        $result1 = $this->salesService->createSale($data);
        $this->assertEquals(201, $result1['status_code']);
        $saleId1 = $result1['sale_id'];

        // Second call - returns existing sale
        $result2 = $this->salesService->createSale($data);
        $this->assertEquals(200, $result2['status_code']);
        $this->assertTrue($result2['is_duplicate']);
        $this->assertEquals($saleId1, $result2['sale_id']);
    }

    public function testSaleCreatesJournalEntries()
    {
        $data = [
            'external_id' => 'test-uuid-003',
            'user_id' => 1,
            'items' => [
                ['product_id' => 1, 'qty' => 1, 'price' => 100.00]
            ],
            'totals' => [
                'sub' => 100.00,
                'tax' => 16.00,
                'grand' => 116.00
            ]
        ];

        $result = $this->salesService->createSale($data);
        $saleId = $result['sale_id'];

        // Check journal entries created
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM journal_entries WHERE source = 'sale' AND source_id = ?");
        $stmt->execute([$saleId]);
        $count = $stmt->fetchColumn();

        $this->assertGreaterThan(0, $count);
    }

    public function testSaleUpdatesInventory()
    {
        // Get initial stock
        $stmt = $this->db->prepare("SELECT stock_quantity FROM products WHERE id = 1");
        $stmt->execute();
        $initialStock = $stmt->fetchColumn();

        $data = [
            'external_id' => 'test-uuid-004',
            'user_id' => 1,
            'items' => [
                ['product_id' => 1, 'qty' => 5, 'price' => 10.00]
            ],
            'totals' => [
                'sub' => 50.00,
                'tax' => 8.00,
                'grand' => 58.00
            ]
        ];

        $this->salesService->createSale($data);

        // Check stock decreased
        $stmt->execute();
        $newStock = $stmt->fetchColumn();

        $this->assertEquals($initialStock - 5, $newStock);
    }

    protected function tearDown(): void
    {
        $this->db = null;
    }
}
