<?php

namespace App\Services;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use PDO;
use PDOException;

/**
 * RegisterReportService
 * Provides X/Y/Z snapshot reporting for POS cash registers
 * and manages register session lifecycle.
 */
class RegisterReportService
{
    private PDO $db;
    private bool $schemaEnsured = false;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Ensure supporting tables exist.
     * Uses the unified register_sessions table from migration 003.
     */
    public function ensureSchema(): void
    {
        if ($this->schemaEnsured) {
            return;
        }

        // The register_sessions table is created by migration 003_add_registers_tills.sql
        // We only need to ensure the closures table exists for X/Y/Z reports
        $closureSqlJson = <<<SQL
CREATE TABLE IF NOT EXISTS pos_register_closures (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    closure_type ENUM('X','Y','Z') NOT NULL,
    session_id INT UNSIGNED NULL,
    register_id INT UNSIGNED NULL,
    location_id INT UNSIGNED NULL,
    range_start DATETIME NOT NULL,
    range_end DATETIME NOT NULL,
    totals_json JSON NULL,
    generated_by_user_id INT UNSIGNED NOT NULL,
    generated_at DATETIME NOT NULL,
    reset_applied TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_closure_type (closure_type),
    INDEX idx_closure_generated (generated_at)
) ENGINE=InnoDB
SQL;

        try {
            $this->db->exec($closureSqlJson);
        } catch (PDOException $e) {
            // Fallback for MySQL builds without JSON support
            if (stripos($e->getMessage(), 'Unknown data type') !== false) {
                $closureSqlText = str_replace('totals_json JSON NULL', 'totals_json LONGTEXT NULL', $closureSqlJson);
                $this->db->exec($closureSqlText);
            } else {
                throw $e;
            }
        }

        $this->schemaEnsured = true;
    }

    /**
     * Open a new register session.
     * Uses the unified register_sessions table.
     */
    public function openSession(int $userId, float $openingAmount = 0.0, ?string $note = null, ?int $locationId = null, ?int $registerId = null): array
    {
        $this->ensureSchema();

        // Generate session number
        $sessionNumber = 'SES-' . date('Ymd') . '-' . str_pad($registerId ?? 0, 3, '0', STR_PAD_LEFT) . '-' . substr(uniqid(), -4);

        $sql = "INSERT INTO register_sessions (register_id, user_id, session_number, opening_balance, opening_notes, opened_at, status) VALUES (?, ?, ?, ?, ?, NOW(), 'open')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $registerId,
            $userId,
            $sessionNumber,
            $openingAmount,
            $note,
        ]);

        $sessionId = (int)$this->db->lastInsertId();
        
        // Store in PHP session
        $_SESSION['register_id'] = $registerId;
        $_SESSION['register_session_id'] = $sessionId;
        
        return $this->getSessionById($sessionId);
    }

    /**
     * Close an active register session.
     * Uses the unified register_sessions table.
     */
    public function closeSession(int $sessionId, int $userId, ?float $closingAmount = null, ?string $note = null): array
    {
        $this->ensureSchema();

        // Get session to calculate variance
        $session = $this->getSessionById($sessionId);
        $expectedBalance = ($session['opening_balance'] ?? 0) + ($session['cash_sales'] ?? 0);
        $variance = $closingAmount !== null ? $closingAmount - $expectedBalance : null;

        $sql = "UPDATE register_sessions SET 
                status = 'closed', 
                closed_by = ?, 
                closing_balance = ?, 
                expected_balance = ?,
                variance = ?,
                closing_notes = ?, 
                closed_at = NOW() 
                WHERE id = ? AND status = 'open'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $userId,
            $closingAmount,
            $expectedBalance,
            $variance,
            $note,
            $sessionId,
        ]);

        // Clear PHP session
        unset($_SESSION['register_id']);
        unset($_SESSION['register_session_id']);

        return $this->getSessionById($sessionId);
    }

    /**
     * Fetch session by id.
     * Uses the unified register_sessions table.
     */
    public function getSessionById(int $sessionId): array
    {
        $this->ensureSchema();
        $stmt = $this->db->prepare("
            SELECT rs.*, r.name as register_name, r.register_number, r.location_id,
                   rs.opening_balance as opening_amount, rs.closing_balance as closing_amount
            FROM register_sessions rs
            LEFT JOIN registers r ON rs.register_id = r.id
            WHERE rs.id = ?
        ");
        $stmt->execute([$sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }

    /**
     * Get the latest open session, optionally filtered by location.
     * Uses the unified register_sessions table.
     */
    public function getActiveSession(?int $locationId = null): ?array
    {
        $this->ensureSchema();
        $sql = "SELECT rs.*, r.name as register_name, r.register_number, r.location_id,
                       rs.opening_balance as opening_amount, rs.closing_balance as closing_amount
                FROM register_sessions rs
                LEFT JOIN registers r ON rs.register_id = r.id
                WHERE rs.status = 'open'";
        $params = [];
        if ($locationId !== null) {
            $sql .= " AND r.location_id = ?";
            $params[] = $locationId;
        }
        $sql .= " ORDER BY rs.opened_at DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * List sessions, optionally filtered by status/location.
     * Uses the unified register_sessions table.
     */
    public function listSessions(?string $status = null, ?int $locationId = null, int $limit = 25): array
    {
        $this->ensureSchema();

        $sql = "SELECT rs.*, r.name as register_name, r.register_number, r.location_id,
                       rs.opening_balance as opening_amount, rs.closing_balance as closing_amount
                FROM register_sessions rs
                LEFT JOIN registers r ON rs.register_id = r.id
                WHERE 1=1";
        $params = [];

        if ($status !== null) {
            $sql .= " AND rs.status = ?";
            $params[] = $status;
        }

        if ($locationId !== null) {
            $sql .= " AND r.location_id = ?";
            $params[] = $locationId;
        }

        $sql .= " ORDER BY rs.opened_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Generate X report (snapshot, no reset).
     */
    public function generateXReport(?int $locationId = null): array
    {
        $range = $this->deriveRangeFromLastClosure('Z', $locationId);
        return $this->buildReportPayload('X', $range['start'], $range['end'], null, $locationId);
    }

    /**
     * Generate Y report (shift/session specific).
     * Uses session_id from unified register_sessions table.
     */
    public function generateYReport(int $sessionId): array
    {
        $session = $this->getSessionById($sessionId);
        if (empty($session)) {
            throw new Exception('Session not found');
        }

        $start = new DateTimeImmutable($session['opened_at']);
        $end = $session['closed_at'] ? new DateTimeImmutable($session['closed_at']) : new DateTimeImmutable('now');
        $locationId = $session['location_id'] !== null ? (int)$session['location_id'] : null;

        // Build report with session-specific sales data
        $payload = $this->buildReportPayload('Y', $start, $end, $session, $locationId);
        
        // Add session-specific totals from register_sessions
        $payload['session_totals'] = [
            'cash_sales' => (float)($session['cash_sales'] ?? 0),
            'card_sales' => (float)($session['card_sales'] ?? 0),
            'mobile_sales' => (float)($session['mobile_sales'] ?? 0),
            'total_sales' => (float)($session['total_sales'] ?? 0),
            'transaction_count' => (int)($session['transaction_count'] ?? 0),
        ];

        return $payload;
    }

    /**
     * Generate Z report (end of day). Optionally finalize and record closure.
     */
    public function generateZReport(int $userId, ?int $locationId = null, bool $finalize = false): array
    {
        $range = $this->deriveRangeFromLastClosure('Z', $locationId);
        $payload = $this->buildReportPayload('Z', $range['start'], $range['end'], null, $locationId);

        if ($finalize) {
            $closureId = $this->recordClosure('Z', $payload, null, $locationId, $userId, true);
            $payload['closure_id'] = $closureId;
        }

        return $payload;
    }

    /**
     * Persist a closure snapshot.
     */
    public function recordClosure(string $type, array $payload, ?array $session, ?int $locationId, int $userId, bool $reset): int
    {
        $this->ensureSchema();
        $totals = $payload['totals'] ?? [];
        $ranges = $payload['range'] ?? [];
        $sessionId = $session['id'] ?? null;

        $totalsJson = json_encode($totals, JSON_PRETTY_PRINT);

        $rangeStart = $this->normalizeDateTimeForStorage($ranges['start'] ?? ($ranges['start_iso'] ?? null));
        $rangeEnd = $this->normalizeDateTimeForStorage($ranges['end'] ?? ($ranges['end_iso'] ?? null));

        $sql = "INSERT INTO pos_register_closures (closure_type, session_id, location_id, range_start, range_end, totals_json, generated_by_user_id, generated_at, reset_applied) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            strtoupper($type),
            $sessionId,
            $locationId,
            $rangeStart,
            $rangeEnd,
            $totalsJson,
            $userId,
            $reset ? 1 : 0,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * List recent closures.
     */
    public function getRecentClosures(int $limit = 25, ?int $locationId = null): array
    {
        $this->ensureSchema();
        $sql = "SELECT * FROM pos_register_closures";
        $params = [];
        if ($locationId !== null) {
            $sql .= " WHERE (location_id = ? OR location_id IS NULL)";
            $params[] = $locationId;
        }
        $sql .= " ORDER BY generated_at DESC LIMIT ?";
        $params[] = $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            if (isset($row['totals_json']) && $row['totals_json'] !== null) {
                $decoded = json_decode($row['totals_json'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $row['totals'] = $decoded;
                }
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Determine range start based on last closure type (defaults to 24h window if none).
     */
    private function deriveRangeFromLastClosure(string $type, ?int $locationId): array
    {
        $this->ensureSchema();

        $sql = "SELECT range_end FROM pos_register_closures WHERE closure_type = 'Z'";
        $params = [];
        if ($locationId !== null) {
            $sql .= " AND (location_id = ? OR location_id IS NULL)";
            $params[] = $locationId;
        }
        $sql .= " ORDER BY generated_at DESC LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['range_end'])) {
            $start = new DateTimeImmutable($row['range_end']);
        } else {
            // default to start of the current day
            $start = (new DateTimeImmutable('now'))->setTime(0, 0, 0);
        }

        $end = new DateTimeImmutable('now');

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * Build report payload with metrics.
     */
    private function buildReportPayload(string $type, DateTimeInterface $start, DateTimeInterface $end, ?array $session, ?int $locationId): array
    {
        $metrics = $this->collectMetrics($start, $end, $locationId);

        $payload = [
            'type' => strtoupper($type),
            'generated_at' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
            'range' => [
                'start_iso' => $start->format(DateTimeInterface::ATOM),
                'end_iso' => $end->format(DateTimeInterface::ATOM),
                'start' => $start->format('Y-m-d H:i:s'),
                'end' => $end->format('Y-m-d H:i:s'),
            ],
            'totals' => $metrics,
        ];

        if ($session) {
            $payload['session'] = $session;
        }

        if ($locationId !== null) {
            $payload['location_id'] = $locationId;
        }

        return $payload;
    }

    /**
     * Aggregate sales/payment/void metrics between timestamps.
     */
    private function collectMetrics(DateTimeInterface $start, DateTimeInterface $end, ?int $locationId): array
    {
        $params = [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
        $locationSql = '';
        if ($locationId !== null) {
            $locationSql = ' AND (location_id = ? OR location_id IS NULL)';
            $params[] = $locationId;
        }

        $salesSql = "SELECT 
                COUNT(*) AS sale_count,
                COALESCE(SUM(subtotal), 0) AS subtotal,
                COALESCE(SUM(tax_amount), 0) AS tax_amount,
                COALESCE(SUM(discount_amount), 0) AS discount_amount,
                COALESCE(SUM(total_amount), 0) AS total_amount,
                COALESCE(SUM(amount_paid), 0) AS amount_paid,
                COALESCE(SUM(change_amount), 0) AS change_amount
            FROM sales
            WHERE created_at BETWEEN ? AND ?" . $locationSql;

        $stmt = $this->db->prepare($salesSql);
        $stmt->execute($params);
        $salesRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $paymentSql = "SELECT payment_method, COUNT(*) AS count, COALESCE(SUM(total_amount), 0) AS total_amount, COALESCE(SUM(amount_paid), 0) AS paid_amount
            FROM sales
            WHERE created_at BETWEEN ? AND ?" . $locationSql . "
            GROUP BY payment_method";
        $stmt = $this->db->prepare($paymentSql);
        $stmt->execute($params);
        $paymentRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $voidParams = [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
        $voidSql = "SELECT COUNT(*) AS void_count, COALESCE(SUM(original_total), 0) AS void_total
            FROM void_transactions
            WHERE void_timestamp BETWEEN ? AND ?";
        $voidStmt = $this->db->prepare($voidSql);
        $voidStmt->execute($voidParams);
        $voidRow = $voidStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $cashDrawer = $this->estimateCashDrawer($paymentRows, $salesRow['change_amount'] ?? 0.0);

        return [
            'sales' => [
                'count' => (int)($salesRow['sale_count'] ?? 0),
                'subtotal' => (float)($salesRow['subtotal'] ?? 0),
                'tax' => (float)($salesRow['tax_amount'] ?? 0),
                'discount' => (float)($salesRow['discount_amount'] ?? 0),
                'total' => (float)($salesRow['total_amount'] ?? 0),
                'amount_paid' => (float)($salesRow['amount_paid'] ?? 0),
                'change_given' => (float)($salesRow['change_amount'] ?? 0),
            ],
            'payments' => $this->normalizePaymentBreakdown($paymentRows),
            'voids' => [
                'count' => (int)($voidRow['void_count'] ?? 0),
                'total' => (float)($voidRow['void_total'] ?? 0),
            ],
            'drawer' => $cashDrawer,
        ];
    }

    private function normalizePaymentBreakdown(array $rows): array
    {
        $breakdown = [];
        foreach ($rows as $row) {
            $method = $row['payment_method'] ?: 'unknown';
            $breakdown[] = [
                'method' => $method,
                'count' => (int)($row['count'] ?? 0),
                'total_amount' => (float)($row['total_amount'] ?? 0),
                'paid_amount' => (float)($row['paid_amount'] ?? 0),
            ];
        }
        return $breakdown;
    }

    private function estimateCashDrawer(array $paymentRows, float $changeGiven): array
    {
        $cashPaid = 0.0;
        foreach ($paymentRows as $row) {
            if (($row['payment_method'] ?? '') === 'cash') {
                $cashPaid += (float)($row['paid_amount'] ?? 0);
            }
        }

        return [
            'cash_received' => $cashPaid,
            'change_given' => $changeGiven,
            'expected_drawer_cash' => $cashPaid - $changeGiven,
        ];
    }

    private function normalizeDateTimeForStorage(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return date('Y-m-d H:i:s', (int)$value);
        }

        $stringValue = trim((string)$value);

        try {
            $dt = new DateTimeImmutable($stringValue);
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            // Attempt manual cleanup of ISO 8601 style strings
            $clean = preg_replace('/T/', ' ', $stringValue);
            $clean = preg_replace('/([\+\-]\d{2}):?(\d{2})$/', '', $clean);
            $timestamp = strtotime($clean);
            return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
        }
    }
}
