<?php

namespace App\Services;

use DateTime;
use Exception;
use PDO;
use PDOException;

class MaintenanceService
{
    private PDO $db;

    private const STATUSES = ['open', 'assigned', 'in_progress', 'on_hold', 'resolved', 'closed'];
    private const PRIORITIES = ['low', 'normal', 'high'];

    private const VALID_TRANSITIONS = [
        'open' => ['assigned', 'in_progress', 'on_hold', 'resolved', 'closed'],
        'assigned' => ['in_progress', 'on_hold', 'resolved', 'closed'],
        'in_progress' => ['on_hold', 'resolved', 'closed'],
        'on_hold' => ['in_progress', 'resolved', 'closed'],
        'resolved' => ['closed', 'in_progress'],
        'closed' => [],
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function ensureSchema(): void
    {
        $this->createRequestsTable();
        $this->syncRequestsTable();
        $this->createLogsTable();
        $this->syncLogsTable();
    }

    public function createRequest(array $payload, ?int $userId): array
    {
        $this->ensureSchema();

        $title = trim((string)($payload['title'] ?? ''));
        if ($title === '') {
            throw new Exception('Issue title is required.');
        }

        $priority = strtolower((string)($payload['priority'] ?? 'normal'));
        if (!in_array($priority, self::PRIORITIES, true)) {
            $priority = 'normal';
        }

        $status = strtolower((string)($payload['status'] ?? 'open'));
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'open';
        }

        $roomId = isset($payload['room_id']) && $payload['room_id'] !== '' ? (int)$payload['room_id'] : null;
        $bookingId = isset($payload['booking_id']) && $payload['booking_id'] !== '' ? (int)$payload['booking_id'] : null;
        $assignedTo = isset($payload['assigned_to']) && $payload['assigned_to'] !== '' ? (int)$payload['assigned_to'] : null;

        $reporterType = trim((string)($payload['reporter_type'] ?? 'staff'));
        if ($reporterType === '') {
            $reporterType = 'staff';
        }

        $reporterName = trim((string)($payload['reporter_name'] ?? '')) ?: null;
        $reporterContact = trim((string)($payload['reporter_contact'] ?? '')) ?: null;
        $description = trim((string)($payload['description'] ?? '')) ?: null;
        $notes = trim((string)($payload['notes'] ?? '')) ?: null;

        $dueDate = null;
        if (!empty($payload['due_date'])) {
            $dueDate = $this->normalizeDate($payload['due_date']);
        }

        $startedAt = null;
        if (!empty($payload['started_at'])) {
            $startedAt = $this->normalizeDateTime($payload['started_at']);
        }

        $completedAt = null;
        if (!empty($payload['completed_at'])) {
            $completedAt = $this->normalizeDateTime($payload['completed_at']);
        }

        $reference = trim((string)($payload['reference_code'] ?? '')) ?: null;

        $trackingCode = trim((string)($payload['tracking_code'] ?? '')) ?: null;
        if ($reporterType === 'guest') {
            if ($trackingCode !== null) {
                $trackingCode = strtoupper($trackingCode);
            } else {
                $trackingCode = $this->generateTrackingCode();
            }
        } else {
            $trackingCode = $trackingCode ?: null;
        }

        $createdBy = $userId !== null ? $userId : null;

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO maintenance_requests (
                    room_id,
                    booking_id,
                    title,
                    description,
                    priority,
                    status,
                    reporter_type,
                    reporter_name,
                    reporter_contact,
                    tracking_code,
                    reference_code,
                    due_date,
                    started_at,
                    completed_at,
                    assigned_to,
                    created_by,
                    created_at,
                    updated_at,
                    notes
                ) VALUES (
                    :room_id,
                    :booking_id,
                    :title,
                    :description,
                    :priority,
                    :status,
                    :reporter_type,
                    :reporter_name,
                    :reporter_contact,
                    :tracking_code,
                    :reference_code,
                    :due_date,
                    :started_at,
                    :completed_at,
                    :assigned_to,
                    :created_by,
                    NOW(),
                    NOW(),
                    :notes
                )"
            );

            $stmt->execute([
                ':room_id' => $roomId,
                ':booking_id' => $bookingId,
                ':title' => $title,
                ':description' => $description,
                ':priority' => $priority,
                ':status' => $status,
                ':reporter_type' => $reporterType !== '' ? $reporterType : 'staff',
                ':reporter_name' => $reporterName,
                ':reporter_contact' => $reporterContact,
                ':tracking_code' => $trackingCode,
                ':reference_code' => $reference,
                ':due_date' => $dueDate,
                ':started_at' => $startedAt,
                ':completed_at' => $completedAt,
                ':assigned_to' => $assignedTo,
                ':created_by' => $createdBy,
                ':notes' => $notes,
            ]);

            $requestId = (int)$this->db->lastInsertId();

            $this->addLogInternal($requestId, $status, $notes, $userId);

            if ($status === 'in_progress' && !$startedAt) {
                $this->updateTimestamps($requestId, startedAt: date('Y-m-d H:i:s'));
            } elseif (in_array($status, ['resolved', 'closed'], true)) {
                $now = date('Y-m-d H:i:s');
                $this->updateTimestamps($requestId, startedAt: $startedAt ?: $now, completedAt: $completedAt ?: $now);
            }

            $this->db->commit();

            return $this->getRequestById($requestId);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateRequest(int $requestId, array $payload, ?int $userId = null): array
    {
        $this->ensureSchema();

        $existing = $this->getRequestById($requestId);
        if (!$existing) {
            throw new Exception('Maintenance request not found.');
        }

        $fields = [];
        $params = [':id' => $requestId];
        $statusChange = null;
        $statusNote = $payload['status_note'] ?? null;

        if (array_key_exists('status', $payload)) {
            $requestedStatus = strtolower((string)$payload['status']);
            if (!in_array($requestedStatus, self::STATUSES, true)) {
                throw new Exception('Unsupported status value.');
            }
            if ($requestedStatus !== $existing['status']) {
                if (!$this->canTransition($existing['status'], $requestedStatus)) {
                    throw new Exception('Cannot move request from ' . $existing['status'] . ' to ' . $requestedStatus . '.');
                }
                $statusChange = $requestedStatus;
            }
        }

        if (array_key_exists('title', $payload)) {
            $title = trim((string)$payload['title']);
            if ($title === '') {
                throw new Exception('Issue title cannot be empty.');
            }
            $fields[] = 'title = :title';
            $params[':title'] = $title;
        }

        if (array_key_exists('description', $payload)) {
            $desc = trim((string)$payload['description']);
            $fields[] = 'description = :description';
            $params[':description'] = $desc !== '' ? $desc : null;
        }

        if (array_key_exists('notes', $payload)) {
            $notes = trim((string)$payload['notes']);
            $fields[] = 'notes = :notes';
            $params[':notes'] = $notes !== '' ? $notes : null;
        }

        if (array_key_exists('priority', $payload)) {
            $priority = strtolower((string)$payload['priority']);
            if (!in_array($priority, self::PRIORITIES, true)) {
                $priority = 'normal';
            }
            $fields[] = 'priority = :priority';
            $params[':priority'] = $priority;
        }

        if (array_key_exists('room_id', $payload)) {
            $fields[] = 'room_id = :room_id';
            $params[':room_id'] = $payload['room_id'] !== '' ? (int)$payload['room_id'] : null;
        }

        if (array_key_exists('booking_id', $payload)) {
            $fields[] = 'booking_id = :booking_id';
            $params[':booking_id'] = $payload['booking_id'] !== '' ? (int)$payload['booking_id'] : null;
        }

        if (array_key_exists('assigned_to', $payload)) {
            $fields[] = 'assigned_to = :assigned_to';
            $params[':assigned_to'] = $payload['assigned_to'] !== '' ? (int)$payload['assigned_to'] : null;
        }

        if (array_key_exists('reporter_type', $payload)) {
            $fields[] = 'reporter_type = :reporter_type';
            $params[':reporter_type'] = trim((string)$payload['reporter_type']) ?: 'staff';
        }

        if (array_key_exists('reporter_name', $payload)) {
            $fields[] = 'reporter_name = :reporter_name';
            $params[':reporter_name'] = trim((string)$payload['reporter_name']) ?: null;
        }

        if (array_key_exists('reporter_contact', $payload)) {
            $fields[] = 'reporter_contact = :reporter_contact';
            $params[':reporter_contact'] = trim((string)$payload['reporter_contact']) ?: null;
        }

        if (array_key_exists('reference_code', $payload)) {
            $fields[] = 'reference_code = :reference_code';
            $params[':reference_code'] = trim((string)$payload['reference_code']) ?: null;
        }

        if (array_key_exists('due_date', $payload)) {
            $fields[] = 'due_date = :due_date';
            $params[':due_date'] = $payload['due_date'] !== '' ? $this->normalizeDate($payload['due_date']) : null;
        }

        if (array_key_exists('started_at', $payload)) {
            $fields[] = 'started_at = :started_at';
            $params[':started_at'] = $payload['started_at'] !== '' ? $this->normalizeDateTime($payload['started_at']) : null;
        }

        if (array_key_exists('completed_at', $payload)) {
            $fields[] = 'completed_at = :completed_at';
            $params[':completed_at'] = $payload['completed_at'] !== '' ? $this->normalizeDateTime($payload['completed_at']) : null;
        }

        $didUpdate = false;
        if (!empty($fields)) {
            $fields[] = 'updated_at = NOW()';
            $sql = 'UPDATE maintenance_requests SET ' . implode(', ', $fields) . ' WHERE id = :id';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $didUpdate = true;
        }

        if ($statusChange !== null) {
            return $this->updateStatus(
                $requestId,
                $statusChange,
                $statusNote,
                $userId ?? ($existing['created_by'] ?? null) ?? 0
            );
        }

        if ($didUpdate) {
            return $this->getRequestById($requestId);
        }

        return $existing;
    }

    public function updateStatus(int $requestId, string $status, ?string $notes, int $userId): array
    {
        $this->ensureSchema();

        $status = strtolower($status);
        if (!in_array($status, self::STATUSES, true)) {
            throw new Exception('Unsupported status value.');
        }

        $request = $this->getRequestById($requestId);
        if (!$request) {
            throw new Exception('Maintenance request not found.');
        }

        $notes = trim((string)$notes) ?: null;

        $this->db->beginTransaction();
        try {
            $timestamps = [];
            if ($status === 'in_progress' && empty($request['started_at'])) {
                $timestamps['started_at'] = date('Y-m-d H:i:s');
            }

            if (in_array($status, ['resolved', 'closed'], true)) {
                if (empty($request['started_at'])) {
                    $timestamps['started_at'] = date('Y-m-d H:i:s');
                }
                $timestamps['completed_at'] = date('Y-m-d H:i:s');
            }

            if ($status === 'open') {
                $timestamps['started_at'] = null;
                $timestamps['completed_at'] = null;
            }

            $updateSql = 'UPDATE maintenance_requests SET status = :status, updated_at = NOW()';
            $params = [
                ':status' => $status,
                ':id' => $requestId,
            ];

            if (array_key_exists('started_at', $timestamps)) {
                $updateSql .= ', started_at = :started_at';
                $params[':started_at'] = $timestamps['started_at'];
            }

            if (array_key_exists('completed_at', $timestamps)) {
                $updateSql .= ', completed_at = :completed_at';
                $params[':completed_at'] = $timestamps['completed_at'];
            }

            $stmt = $this->db->prepare($updateSql . ' WHERE id = :id');
            $stmt->execute($params);

            $this->addLogInternal($requestId, $status, $notes, $userId);

            $this->db->commit();

            return $this->getRequestById($requestId);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function assignRequest(int $requestId, ?int $assigneeId, int $userId, ?string $notes = null): array
    {
        $this->ensureSchema();

        $request = $this->getRequestById($requestId);
        if (!$request) {
            throw new Exception('Maintenance request not found.');
        }

        $stmt = $this->db->prepare('UPDATE maintenance_requests SET assigned_to = :assigned_to, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            ':assigned_to' => $assigneeId,
            ':id' => $requestId,
        ]);

        $noteText = $notes ? trim($notes) : null;
        if ($assigneeId !== null) {
            $noteText = $noteText ?: 'Technician assigned';
        } else {
            $noteText = $noteText ?: 'Assignment removed';
        }
        $this->addLogInternal($requestId, $request['status'] ?? 'open', $noteText, $userId);

        return $this->getRequestById($requestId);
    }

    public function getRequestById(int $requestId): ?array
    {
        $sql = "
            SELECT r.*, rooms.room_number, rooms.status AS room_status, rb.booking_number,
                   CONCAT_WS(' ', tech.full_name, tech.username) AS assigned_name,
                   CONCAT_WS(' ', creator.full_name, creator.username) AS created_by_name
            FROM maintenance_requests r
            LEFT JOIN rooms ON r.room_id = rooms.id
            LEFT JOIN room_bookings rb ON r.booking_id = rb.id
            LEFT JOIN users tech ON r.assigned_to = tech.id
            LEFT JOIN users creator ON r.created_by = creator.id
            WHERE r.id = :id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getRequestByTrackingCode(string $trackingCode, ?string $contact = null): ?array
    {
        $this->ensureSchema();

        $trackingCode = strtoupper(trim($trackingCode));
        if ($trackingCode === '') {
            return null;
        }

        $sql = "
            SELECT r.id, r.title, r.description, r.priority, r.status, r.reporter_name,
                   r.reporter_contact, r.created_at, r.updated_at, r.started_at, r.completed_at,
                   rooms.room_number,
                   CONCAT_WS(' ', tech.full_name, tech.username) AS assigned_name
            FROM maintenance_requests r
            LEFT JOIN rooms ON r.room_id = rooms.id
            LEFT JOIN users tech ON r.assigned_to = tech.id
            WHERE r.tracking_code = :tracking_code
        ";

        $params = [':tracking_code' => $trackingCode];
        if ($contact !== null && trim($contact) !== '') {
            $sql .= ' AND (r.reporter_contact IS NULL OR LOWER(r.reporter_contact) = :contact_check)';
            $params[':contact_check'] = strtolower(trim($contact));
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['timeline'] = $this->getPublicTimeline((int)$row['id']);
        return $row;
    }

    public function getRequests(array $filters = []): array
    {
        $this->ensureSchema();

        $where = [];
        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $where[] = 'r.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['priority']) && in_array($filters['priority'], self::PRIORITIES, true)) {
            $where[] = 'r.priority = :priority';
            $params[':priority'] = $filters['priority'];
        }

        if (!empty($filters['assigned_to'])) {
            $where[] = 'r.assigned_to = :assigned_to';
            $params[':assigned_to'] = (int)$filters['assigned_to'];
        }

        if (!empty($filters['room_id'])) {
            $where[] = 'r.room_id = :room_id';
            $params[':room_id'] = (int)$filters['room_id'];
        }

        if (!empty($filters['reporter_type'])) {
            $where[] = 'r.reporter_type = :reporter_type';
            $params[':reporter_type'] = $filters['reporter_type'];
        }

        if (!empty($filters['due_today'])) {
            $where[] = 'r.due_date = CURDATE()';
        }

        $sql = "
            SELECT r.id, r.room_id, r.booking_id, r.title, r.description, r.priority,
                   r.status, r.reporter_type, r.reporter_name, r.reporter_contact,
                   r.reference_code, r.due_date, r.started_at, r.completed_at,
                   r.assigned_to, r.created_by, r.created_at, r.updated_at, r.notes,
                   rooms.room_number, rooms.status AS room_status,
                   rb.booking_number,
                   CONCAT_WS(' ', tech.full_name, tech.username) AS assigned_name,
                   CONCAT_WS(' ', creator.full_name, creator.username) AS created_by_name
            FROM maintenance_requests r
            LEFT JOIN rooms ON r.room_id = rooms.id
            LEFT JOIN room_bookings rb ON r.booking_id = rb.id
            LEFT JOIN users tech ON r.assigned_to = tech.id
            LEFT JOIN users creator ON r.created_by = creator.id
        ";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY r.status IN (\'resolved\',\'closed\'), r.due_date IS NULL, r.due_date ASC, r.priority DESC, r.created_at DESC';

        if (!empty($filters['limit'])) {
            $limit = max(1, (int)$filters['limit']);
            $sql .= ' LIMIT ' . $limit;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }

    public function getPublicTimeline(int $requestId): array
    {
        $stmt = $this->db->prepare(
            "SELECT status, notes, created_at FROM maintenance_request_logs WHERE request_id = :id ORDER BY created_at ASC"
        );
        $stmt->execute([':id' => $requestId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static function ($row) {
            return [
                'status' => $row['status'],
                'notes' => $row['notes'],
                'created_at' => $row['created_at'],
            ];
        }, $rows);
    }

    public function getLogs(int $requestId): array
    {
        $this->ensureSchema();

        $stmt = $this->db->prepare(
            "SELECT l.*, CONCAT_WS(' ', u.full_name, u.username) AS created_by_name
             FROM maintenance_request_logs l
             LEFT JOIN users u ON l.created_by = u.id
             WHERE l.request_id = :id
             ORDER BY l.created_at ASC"
        );
        $stmt->execute([':id' => $requestId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }

    public function getSummary(): array
    {
        $this->ensureSchema();

        $counts = [];
        foreach (self::STATUSES as $status) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM maintenance_requests WHERE status = :status');
            $stmt->execute([':status' => $status]);
            $counts[$status] = (int)$stmt->fetchColumn();
        }

        $stmt = $this->db->query("SELECT COUNT(*) FROM maintenance_requests WHERE priority = 'high' AND status IN ('open','in_progress')");
        $counts['high_priority'] = (int)$stmt->fetchColumn();

        $stmt = $this->db->query("SELECT COUNT(*) FROM maintenance_requests WHERE due_date IS NOT NULL AND due_date < CURDATE() AND status NOT IN ('resolved','closed')");
        $counts['overdue'] = (int)$stmt->fetchColumn();

        return $counts;
    }

    public function getTechnicianLoad(): array
    {
        $this->ensureSchema();

        $sql = "
            SELECT
                mr.assigned_to AS technician_id,
                CONCAT_WS(' ', tech.full_name, tech.username) AS technician_name,
                SUM(CASE WHEN mr.status IN ('open','assigned') THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN mr.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress,
                SUM(CASE WHEN mr.status = 'on_hold' THEN 1 ELSE 0 END) AS on_hold,
                COUNT(mr.id) AS total
            FROM maintenance_requests mr
            LEFT JOIN users tech ON mr.assigned_to = tech.id
            WHERE mr.assigned_to IS NOT NULL
              AND mr.status NOT IN ('resolved','closed')
            GROUP BY mr.assigned_to, technician_name
            ORDER BY total DESC, technician_name ASC
        ";

        $stmt = $this->db->query($sql);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        if (!$rows) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'technician_id' => isset($row['technician_id']) ? (int)$row['technician_id'] : 0,
                'technician_name' => trim($row['technician_name'] ?? '') ?: 'Unassigned',
                'pending' => (int)($row['pending'] ?? 0),
                'in_progress' => (int)($row['in_progress'] ?? 0),
                'on_hold' => (int)($row['on_hold'] ?? 0),
                'total' => (int)($row['total'] ?? 0),
            ];
        }, $rows);
    }

    private function addLogInternal(int $requestId, string $status, ?string $notes, ?int $userId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO maintenance_request_logs (request_id, status, notes, created_by, created_at)
             VALUES (:request_id, :status, :notes, :created_by, NOW())'
        );
        $stmt->execute([
            ':request_id' => $requestId,
            ':status' => $status,
            ':notes' => $notes,
            ':created_by' => $userId,
        ]);
    }

    private function updateTimestamps(int $requestId, ?string $startedAt = null, ?string $completedAt = null): void
    {
        $fields = [];
        $params = [':id' => $requestId];

        if ($startedAt !== null) {
            $fields[] = 'started_at = :started_at';
            $params[':started_at'] = $startedAt;
        }

        if ($completedAt !== null) {
            $fields[] = 'completed_at = :completed_at';
            $params[':completed_at'] = $completedAt;
        }

        if (empty($fields)) {
            return;
        }

        $fields[] = 'updated_at = NOW()';
        $sql = 'UPDATE maintenance_requests SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    private function createRequestsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS maintenance_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            room_id INT UNSIGNED NULL,
            booking_id INT UNSIGNED NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT NULL,
            priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
            status ENUM('open','assigned','in_progress','on_hold','resolved','closed') NOT NULL DEFAULT 'open',
            reporter_type VARCHAR(40) NOT NULL DEFAULT 'staff',
            reporter_name VARCHAR(120) NULL,
            reporter_contact VARCHAR(120) NULL,
            tracking_code VARCHAR(16) NULL,
            reference_code VARCHAR(60) NULL,
            due_date DATE NULL,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            assigned_to INT UNSIGNED NULL,
            created_by INT UNSIGNED NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_priority (priority),
            INDEX idx_due_date (due_date)
        ) ENGINE=InnoDB";

        $this->db->exec($sql);
    }

    private function syncRequestsTable(): void
    {
        $this->addColumnIfMissing('maintenance_requests', 'notes', 'TEXT NULL AFTER created_by');
        $this->addColumnIfMissing('maintenance_requests', 'reference_code', 'VARCHAR(60) NULL AFTER reporter_contact');
        $this->addColumnIfMissing('maintenance_requests', 'reporter_type', "VARCHAR(40) NOT NULL DEFAULT 'staff' AFTER status");
        $this->addColumnIfMissing('maintenance_requests', 'tracking_code', 'VARCHAR(16) NULL AFTER reporter_contact');
        try {
            $this->db->exec('ALTER TABLE maintenance_requests MODIFY created_by INT UNSIGNED NULL');
        } catch (PDOException $e) {
            // Ignore alteration failure (already nullable or permissions issue)
        }
        $this->ensureRequestStatusEnum();
        $this->ensureForeignKey('maintenance_requests', 'fk_maintenance_requests_room', 'room_id', 'rooms', 'id');
        $this->ensureForeignKey('maintenance_requests', 'fk_maintenance_requests_booking', 'booking_id', 'room_bookings', 'id');
        $this->ensureForeignKey('maintenance_requests', 'fk_maintenance_requests_assigned', 'assigned_to', 'users', 'id');
        $this->ensureForeignKey('maintenance_requests', 'fk_maintenance_requests_created', 'created_by', 'users', 'id');
    }

    private function generateTrackingCode(): string
    {
        do {
            $code = strtoupper(bin2hex(random_bytes(3)));
        } while ($this->trackingCodeExists($code));

        return $code;
    }

    private function trackingCodeExists(string $code): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM maintenance_requests WHERE tracking_code = ? LIMIT 1');
        $stmt->execute([$code]);
        return (bool)$stmt->fetchColumn();
    }

    private function createLogsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS maintenance_request_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            request_id INT UNSIGNED NOT NULL,
            status ENUM('open','assigned','in_progress','on_hold','resolved','closed') NOT NULL,
            notes TEXT NULL,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_request (request_id)
        ) ENGINE=InnoDB";
        $this->db->exec($sql);
    }

    private function syncLogsTable(): void
    {
        $this->addColumnIfMissing('maintenance_request_logs', 'created_by', 'INT UNSIGNED NULL AFTER notes');
        $this->ensureLogStatusEnum();
        $this->ensureForeignKey('maintenance_request_logs', 'fk_maintenance_logs_request', 'request_id', 'maintenance_requests', 'id');
        $this->ensureForeignKey('maintenance_request_logs', 'fk_maintenance_logs_user', 'created_by', 'users', 'id');
    }

    private function ensureRequestStatusEnum(): void
    {
        try {
            $this->db->exec("ALTER TABLE maintenance_requests MODIFY status ENUM('open','assigned','in_progress','on_hold','resolved','closed') NOT NULL DEFAULT 'open'");
        } catch (PDOException $e) {
            // Ignore failure if permissions prevent altering enum
        }
    }

    private function ensureLogStatusEnum(): void
    {
        try {
            $this->db->exec("ALTER TABLE maintenance_request_logs MODIFY status ENUM('open','assigned','in_progress','on_hold','resolved','closed') NOT NULL");
        } catch (PDOException $e) {
            // Ignore alteration failure
        }
    }

    private function canTransition(?string $from, string $to): bool
    {
        $from = strtolower($from ?? '');
        $to = strtolower($to);
        if (!isset(self::VALID_TRANSITIONS[$from])) {
            return false;
        }

        return in_array($to, self::VALID_TRANSITIONS[$from], true);
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

    private function ensureForeignKey(string $table, string $constraint, string $column, string $referenceTable, string $referenceColumn): void
    {
        if (!$this->tableExists($table) || !$this->tableExists($referenceTable)) {
            return;
        }

        if ($this->foreignKeyExists($table, $constraint)) {
            return;
        }

        try {
            $this->db->exec(
                "ALTER TABLE {$table} ADD CONSTRAINT {$constraint} FOREIGN KEY ({$column}) REFERENCES {$referenceTable}({$referenceColumn}) ON DELETE SET NULL"
            );
        } catch (PDOException $e) {
            // ignore engine mismatch / duplicate constraint errors
        }
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $stmt = $this->db->prepare(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND CONSTRAINT_NAME = :constraint AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
        );
        $stmt->execute([
            ':table' => $table,
            ':constraint' => $constraint,
        ]);
        return (bool)$stmt->fetchColumn();
    }

    private function tableExists(string $table): bool
    {
        $safeTable = str_replace(['`', '\\'], ['', ''], $table);
        $quoted = $this->db->quote($safeTable);
        $stmt = $this->db->query("SHOW TABLES LIKE {$quoted}");
        return $stmt && $stmt->fetchColumn() !== false;
    }

    private function normalizeDate(string $date): string
    {
        $dt = new DateTime($date);
        return $dt->format('Y-m-d');
    }

    private function normalizeDateTime(string $value): string
    {
        $dt = new DateTime($value);
        return $dt->format('Y-m-d H:i:s');
    }
}
