<?php

namespace App\Controllers;

use App\Services\SalesService;
use App\Services\AccountingService;
use App\Middlewares\CsrfMiddleware;
use PDO;

/**
 * Sales Controller
 * HTTP/session/validation only - delegates to services
 */
class SalesController
{
    private SalesService $salesService;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $accountingService = new AccountingService($db);
        $this->salesService = new SalesService($db, $accountingService);
    }

    /**
     * Create sale (idempotent endpoint)
     * POST /api/sales
     */
    public function create(): void
    {
        // Validate CSRF
        CsrfMiddleware::require();

        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Invalid JSON input'
            ], 400);
            return;
        }

        // Validate required fields
        $errors = $this->validate($input);
        if (!empty($errors)) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $errors
            ], 422);
            return;
        }

        try {
            $result = $this->salesService->createSale($input);
            
            $statusCode = $result['status_code'] ?? 201;
            unset($result['status_code']);
            
            // Set Location header for new resources
            if ($statusCode === 201) {
                header("Location: /api/sales/{$result['sale_id']}");
            }
            
            $this->jsonResponse($result, $statusCode);
            
        } catch (\Exception $e) {
            error_log("Sale creation error: " . $e->getMessage());
            
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to create sale',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sales with delta support
     * GET /api/sales?since=2025-10-31T07:30:00Z
     */
    public function index(): void
    {
        $since = $_GET['since'] ?? null;
        $afterId = $_GET['after_id'] ?? null;
        $locationId = $_GET['location_id'] ?? null;

        try {
            if ($since) {
                // Delta polling by timestamp
                $sales = $this->salesService->getSalesSince($since, $locationId);
            } else {
                // Regular pagination
                $limit = min((int)($_GET['limit'] ?? 50), 100);
                $offset = (int)($_GET['offset'] ?? 0);
                
                $sales = $this->getSalesPaginated($limit, $offset, $locationId);
            }

            // Generate ETag
            $etag = md5(json_encode($sales));
            
            // Check If-None-Match
            if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
                http_response_code(304);
                exit;
            }

            // Set headers
            header("ETag: {$etag}");
            if (!empty($sales)) {
                $lastModified = max(array_column($sales, 'updated_at'));
                header("Last-Modified: " . gmdate('D, d M Y H:i:s', strtotime($lastModified)) . ' GMT');
            }

            $this->jsonResponse([
                'success' => true,
                'data' => $sales,
                'count' => count($sales)
            ]);

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single sale
     * GET /api/sales/{id}
     */
    public function show(int $id): void
    {
        try {
            $sale = $this->getSaleById($id);
            
            if (!$sale) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Sale not found'
                ], 404);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'data' => $sale
            ]);

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate sale input
     */
    private function validate(array $input): array
    {
        $errors = [];

        if (empty($input['items']) || !is_array($input['items'])) {
            $errors['items'] = 'Items are required';
        }

        if (empty($input['totals'])) {
            $errors['totals'] = 'Totals are required';
        }

        foreach ($input['items'] ?? [] as $index => $item) {
            if (empty($item['product_id'])) {
                $errors["items.{$index}.product_id"] = 'Product ID is required';
            }
            if (empty($item['qty']) || $item['qty'] <= 0) {
                $errors["items.{$index}.qty"] = 'Quantity must be greater than 0';
            }
            if (!isset($item['price']) || $item['price'] < 0) {
                $errors["items.{$index}.price"] = 'Price is required';
            }
        }

        return $errors;
    }

    /**
     * Get sales paginated
     */
    private function getSalesPaginated(int $limit, int $offset, ?int $locationId): array
    {
        $sql = "SELECT * FROM sales";
        $params = [];

        if ($locationId) {
            $sql .= " WHERE location_id = ?";
            $params[] = $locationId;
        }

        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get sale by ID
     */
    private function getSaleById(int $id): ?array
    {
        $sql = "SELECT s.*, 
                GROUP_CONCAT(
                    JSON_OBJECT(
                        'id', si.id,
                        'product_id', si.product_id,
                        'product_name', si.product_name,
                        'quantity', si.quantity,
                        'unit_price', si.unit_price,
                        'total_price', si.total_price
                    )
                ) as items
                FROM sales s
                LEFT JOIN sale_items si ON s.id = si.sale_id
                WHERE s.id = ?
                GROUP BY s.id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['items']) {
            $result['items'] = json_decode('[' . $result['items'] . ']', true);
        }

        return $result ?: null;
    }

    /**
     * Send JSON response
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
}
