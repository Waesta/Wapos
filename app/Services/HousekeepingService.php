<?php

namespace App\Services;

use App\Services\Inventory\InventoryService;
use DateTime;
use Exception;
use PDO;
use PDOException;

class HousekeepingService
{
    private PDO $db;
    private InventoryService $inventoryService;

    /**
     * Permitted task statuses.
     */
    private const STATUSES = ['pending', 'in_progress', 'completed', 'cancelled'];

    /**
     * Permitted task priorities.
     */
    private const PRIORITIES = ['low', 'normal', 'high'];

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->inventoryService = new InventoryService($db);
    }

    public function ensureSchema(): void
    {
        $this->createTasksTable();
        $this->syncTasksTable();
        $this->createTaskLogsTable();
        $this->syncTaskLogsTable();
        $this->createTaskItemsTable();
    }

    public function createTask(array $data, int $userId): array
    {
        $this->ensureSchema();

        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            throw new Exception('Task title is required.');
        }

        $priority = strtolower((string)($data['priority'] ?? 'normal'));
        if (!in_array($priority, self::PRIORITIES, true)) {
            $priority = 'normal';
        }

        $status = strtolower((string)($data['status'] ?? 'pending'));
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'pending';
        }

        $roomId = isset($data['room_id']) && $data['room_id'] !== '' ? (int)$data['room_id'] : null;
        $bookingId = isset($data['booking_id']) && $data['booking_id'] !== '' ? (int)$data['booking_id'] : null;
        $assignedTo = isset($data['assigned_to']) && $data['assigned_to'] !== '' ? (int)$data['assigned_to'] : null;

        $scheduledDate = null;
        if (!empty($data['scheduled_date'])) {
            $scheduledDate = $this->normalizeDate($data['scheduled_date']);
        }

        $scheduledTime = null;
        if (!empty($data['scheduled_time'])) {
            $scheduledTime = $this->normalizeTime($data['scheduled_time']);
        }

        $dueAt = null;
        if (!empty($data['due_at'])) {
            $dueAt = $this->normalizeDateTime($data['due_at']);
        }

        $notes = trim((string)($data['notes'] ?? '')) ?: null;

        $consumables = $this->normalizeTaskItems($data['consumables'] ?? []);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO housekeeping_tasks (
                    room_id,
                    booking_id,
                    title,
                    notes,
                    priority,
                    status,
                    scheduled_date,
                    scheduled_time,
                    due_at,
                    assigned_to,
                    created_by,
                    created_at,
                    updated_at
                ) VALUES (
                    :room_id,
                    :booking_id,
                    :title,
                    :notes,
                    :priority,
                    :status,
                    :scheduled_date,
                    :scheduled_time,
                    :due_at,
                    :assigned_to,
                    :created_by,
                    NOW(),
                    NOW()
                )"
            );

            $stmt->execute([
                ':room_id' => $roomId,
                ':booking_id' => $bookingId,
                ':title' => $title,
                ':notes' => $notes,
                ':priority' => $priority,
                ':status' => $status,
                ':scheduled_date' => $scheduledDate,
                ':scheduled_time' => $scheduledTime,
                ':due_at' => $dueAt,
                ':assigned_to' => $assignedTo,
                ':created_by' => $userId,
            ]);

            $taskId = (int)$this->db->lastInsertId();

            $this->addTaskLogInternal($taskId, $status, $notes, $userId);

            if ($status === 'in_progress') {
                $this->updateTaskTimestamps($taskId, startedAt: date('Y-m-d H:i:s'));
            } elseif ($status === 'completed') {
                $now = date('Y-m-d H:i:s');
                $this->updateTaskTimestamps($taskId, startedAt: $now, completedAt: $now);
            }

            if (!empty($consumables)) {
                $this->syncTaskItems($taskId, $consumables);
            }

            $this->db->commit();

            return $this->getTaskById($taskId);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateTask(int $taskId, array $data, int $userId): array
    {
        $this->ensureSchema();

        $task = $this->getTaskById($taskId);
        if (!$task) {
            throw new Exception('Task not found.');
        }

        $fields = [];
        $params = [':id' => $taskId];

        if (array_key_exists('title', $data)) {
            $title = trim((string)$data['title']);
            if ($title === '') {
                throw new Exception('Task title cannot be empty.');
            }
            $fields[] = 'title = :title';
            $params[':title'] = $title;
        }

        if (array_key_exists('notes', $data)) {
            $notes = trim((string)$data['notes']);
            $fields[] = 'notes = :notes';
            $params[':notes'] = $notes !== '' ? $notes : null;
        }

        if (array_key_exists('priority', $data)) {
            $priority = strtolower((string)$data['priority']);
            if (!in_array($priority, self::PRIORITIES, true)) {
                $priority = 'normal';
            }
            $fields[] = 'priority = :priority';
            $params[':priority'] = $priority;
        }

        if (array_key_exists('scheduled_date', $data)) {
            $fields[] = 'scheduled_date = :scheduled_date';
            $params[':scheduled_date'] = $data['scheduled_date'] !== '' ? $this->normalizeDate($data['scheduled_date']) : null;
        }

        if (array_key_exists('scheduled_time', $data)) {
            $fields[] = 'scheduled_time = :scheduled_time';
            $params[':scheduled_time'] = $data['scheduled_time'] !== '' ? $this->normalizeTime($data['scheduled_time']) : null;
        }

        if (array_key_exists('due_at', $data)) {
            $fields[] = 'due_at = :due_at';
            $params[':due_at'] = $data['due_at'] !== '' ? $this->normalizeDateTime($data['due_at']) : null;
        }

        if (array_key_exists('room_id', $data)) {
            $fields[] = 'room_id = :room_id';
            $params[':room_id'] = $data['room_id'] !== '' ? (int)$data['room_id'] : null;
        }

        if (array_key_exists('booking_id', $data)) {
            $fields[] = 'booking_id = :booking_id';
            $params[':booking_id'] = $data['booking_id'] !== '' ? (int)$data['booking_id'] : null;
        }

        if (array_key_exists('assigned_to', $data)) {
            $fields[] = 'assigned_to = :assigned_to';
            $params[':assigned_to'] = $data['assigned_to'] !== '' ? (int)$data['assigned_to'] : null;
        }

        $consumables = null;
        if (array_key_exists('consumables', $data)) {
            $consumables = $this->normalizeTaskItems($data['consumables']);
        }

        if (empty($fields)) {
            return $task;
        }

        $fields[] = 'updated_at = NOW()';
        $sql = 'UPDATE housekeeping_tasks SET ' . implode(', ', $fields) . ' WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        if (is_array($consumables)) {
            $this->syncTaskItems($taskId, $consumables);
        }

        return $this->getTaskById($taskId);
    }

    public function updateStatus(int $taskId, string $status, ?string $notes, int $userId): array
    {
        $this->ensureSchema();

        $status = strtolower($status);
        if (!in_array($status, self::STATUSES, true)) {
            throw new Exception('Unsupported status value.');
        }

        $task = $this->getTaskById($taskId);
        if (!$task) {
            throw new Exception('Task not found.');
        }

        $notes = trim((string)$notes) ?: null;

        $this->db->beginTransaction();
        $consumeAfterCommit = $status === 'completed';
        try {
            $timestamps = [];
            if ($status === 'in_progress' && empty($task['started_at'])) {
                $timestamps['started_at'] = date('Y-m-d H:i:s');
            }

            if ($status === 'completed') {
                if (empty($task['started_at'])) {
                    $timestamps['started_at'] = date('Y-m-d H:i:s');
                }
                $timestamps['completed_at'] = date('Y-m-d H:i:s');
            }

            if ($status === 'pending') {
                $timestamps['started_at'] = null;
                $timestamps['completed_at'] = null;
            }

            $updateSql = 'UPDATE housekeeping_tasks SET status = :status, updated_at = NOW()';
            $params = [
                ':status' => $status,
                ':id' => $taskId,
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

            $this->addTaskLogInternal($taskId, $status, $notes, $userId);

            $this->db->commit();

            if ($consumeAfterCommit) {
                $this->consumeTaskItems($taskId, $userId);
            }

            return $this->getTaskById($taskId);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function addTaskLog(int $taskId, string $status, ?string $notes, int $userId): void
    {
        $this->ensureSchema();
        $status = strtolower($status);
        if (!in_array($status, self::STATUSES, true)) {
            throw new Exception('Unsupported status value.');
        }

        $this->addTaskLogInternal($taskId, $status, $notes, $userId);
    }

    public function getTaskById(int $taskId): ?array
    {
        $sql = "
            SELECT
                t.*, 
                r.room_number,
                rb.booking_number,
                CONCAT_WS(' ', assigned.full_name, assigned.username) AS assigned_name,
                CONCAT_WS(' ', creator.full_name, creator.username) AS created_by_name
            FROM housekeeping_tasks t
            LEFT JOIN rooms r ON t.room_id = r.id
            LEFT JOIN room_bookings rb ON t.booking_id = rb.id
            LEFT JOIN users assigned ON t.assigned_to = assigned.id
            LEFT JOIN users creator ON t.created_by = creator.id
            WHERE t.id = :id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $taskId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $consumablesMap = $this->loadTaskConsumables([$taskId]);
        $row['consumables'] = $consumablesMap[$taskId] ?? [];

        return $row;
    }

    public function getTasks(array $filters = []): array
    {
        $this->ensureSchema();

        $where = [];
        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $where[] = 't.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['scheduled_date'])) {
            $where[] = 't.scheduled_date = :scheduled_date';
            $params[':scheduled_date'] = $this->normalizeDate($filters['scheduled_date']);
        }

        if (!empty($filters['assigned_to'])) {
            $where[] = 't.assigned_to = :assigned_to';
            $params[':assigned_to'] = (int)$filters['assigned_to'];
        }

        if (!empty($filters['room_id'])) {
            $where[] = 't.room_id = :room_id';
            $params[':room_id'] = (int)$filters['room_id'];
        }

        $sql = "
            SELECT
                t.id,
                t.room_id,
                t.booking_id,
                t.title,
                t.notes,
                t.priority,
                t.status,
                t.scheduled_date,
                t.scheduled_time,
                t.due_at,
                t.started_at,
                t.completed_at,
                t.assigned_to,
                t.created_by,
                t.created_at,
                t.updated_at,
                r.room_number,
                rb.booking_number,
                CONCAT_WS(' ', assigned.full_name, assigned.username) AS assigned_name,
                CONCAT_WS(' ', creator.full_name, creator.username) AS created_by_name
            FROM housekeeping_tasks t
            LEFT JOIN rooms r ON t.room_id = r.id
            LEFT JOIN room_bookings rb ON t.booking_id = rb.id
            LEFT JOIN users assigned ON t.assigned_to = assigned.id
            LEFT JOIN users creator ON t.created_by = creator.id
        ";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY t.scheduled_date IS NULL, t.scheduled_date ASC, t.priority DESC, t.created_at DESC';

        if (!empty($filters['limit'])) {
            $limit = max(1, (int)$filters['limit']);
            $sql .= ' LIMIT ' . $limit;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$tasks) {
            return [];
        }

        $taskIds = array_column($tasks, 'id');
        $consumablesMap = $this->loadTaskConsumables($taskIds);

        foreach ($tasks as &$task) {
            $task['consumables'] = $consumablesMap[$task['id']] ?? [];
        }
        unset($task);

        return $tasks;
    }

    public function getTaskLogs(int $taskId): array
    {
        $this->ensureSchema();

        $stmt = $this->db->prepare(
            "SELECT l.*, CONCAT_WS(' ', u.full_name, u.username) AS created_by_name
             FROM housekeeping_task_logs l
             LEFT JOIN users u ON l.created_by = u.id
             WHERE l.task_id = :task_id
             ORDER BY l.created_at ASC"
        );
        $stmt->execute([':task_id' => $taskId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }

    /**
     * @param int[] $taskIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function loadTaskConsumables(array $taskIds): array
    {
        if (empty($taskIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $sql = "
            SELECT
                hti.task_id,
                hti.id,
                hti.product_id,
                prod.name AS product_name,
                hti.quantity,
                hti.notes,
                hti.consumed_at
            FROM housekeeping_task_items hti
            LEFT JOIN products prod ON prod.id = hti.product_id
            WHERE hti.task_id IN ($placeholders)
            ORDER BY hti.id ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($taskIds);

        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $taskId = (int)$row['task_id'];
            unset($row['task_id']);
            $map[$taskId][] = $row;
        }

        return $map;
    }

    public function assignTask(int $taskId, ?int $assigneeId, int $userId, ?string $notes = null): array
    {
        $this->ensureSchema();

        $task = $this->getTaskById($taskId);
        if (!$task) {
            throw new Exception('Task not found.');
        }

        $stmt = $this->db->prepare('UPDATE housekeeping_tasks SET assigned_to = :assigned_to, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            ':assigned_to' => $assigneeId,
            ':id' => $taskId,
        ]);

        $noteText = $notes ? trim($notes) : null;
        if ($assigneeId !== null) {
            $noteText = $noteText ?: 'Task assigned';
        } else {
            $noteText = $noteText ?: 'Assignment cleared';
        }
        $this->addTaskLogInternal($taskId, $task['status'] ?? 'pending', $noteText, $userId);

        return $this->getTaskById($taskId);
    }

    public function getDashboardSummary(): array
    {
        $this->ensureSchema();

        $counts = [];
        foreach (self::STATUSES as $status) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM housekeeping_tasks WHERE status = :status');
            $stmt->execute([':status' => $status]);
            $counts[$status] = (int)$stmt->fetchColumn();
        }

        $today = date('Y-m-d');
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM housekeeping_tasks WHERE scheduled_date = :today AND status IN ("pending","in_progress")');
        $stmt->execute([':today' => $today]);
        $counts['today'] = (int)$stmt->fetchColumn();

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM housekeeping_tasks WHERE due_at IS NOT NULL AND due_at < NOW() AND status <> "completed"');
        $stmt->execute();
        $counts['overdue'] = (int)$stmt->fetchColumn();

        return $counts;
    }

    private function addTaskLogInternal(int $taskId, string $status, ?string $notes, ?int $userId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO housekeeping_task_logs (task_id, status, notes, created_by, created_at)
             VALUES (:task_id, :status, :notes, :created_by, NOW())'
        );
        $stmt->execute([
            ':task_id' => $taskId,
            ':status' => $status,
            ':notes' => $notes,
            ':created_by' => $userId,
        ]);
    }

    private function updateTaskTimestamps(int $taskId, ?string $startedAt = null, ?string $completedAt = null): void
    {
        $fields = [];
        $params = [':id' => $taskId];

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
        $sql = 'UPDATE housekeeping_tasks SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    private function normalizeDate(string $date): string
    {
        $dt = new DateTime($date);
        return $dt->format('Y-m-d');
    }

    private function normalizeTime(string $time): string
    {
        $dt = new DateTime($time);
        return $dt->format('H:i:s');
    }

    private function normalizeDateTime(string $dateTime): string
    {
        $dt = new DateTime($dateTime);
        return $dt->format('Y-m-d H:i:s');
    }

    private function createTasksTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS housekeeping_tasks (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            room_id INT UNSIGNED NULL,
            booking_id INT UNSIGNED NULL,
            title VARCHAR(150) NOT NULL,
            notes TEXT NULL,
            priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
            status ENUM('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
            scheduled_date DATE NULL,
            scheduled_time TIME NULL,
            due_at DATETIME NULL,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            assigned_to INT UNSIGNED NULL,
            created_by INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_scheduled_date (scheduled_date)
        ) ENGINE=InnoDB";

        $this->db->exec($sql);
    }

    private function syncTasksTable(): void
    {
        $this->addColumnIfMissing('housekeeping_tasks', 'scheduled_time', 'TIME NULL AFTER scheduled_date');
        $this->addColumnIfMissing('housekeeping_tasks', 'due_at', 'DATETIME NULL AFTER scheduled_time');
        $this->addColumnIfMissing('housekeeping_tasks', 'started_at', 'DATETIME NULL AFTER due_at');
        $this->addColumnIfMissing('housekeeping_tasks', 'completed_at', 'DATETIME NULL AFTER started_at');
        $this->addColumnIfMissing('housekeeping_tasks', 'assigned_to', 'INT UNSIGNED NULL AFTER completed_at');
        $this->addColumnIfMissing('housekeeping_tasks', 'created_by', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER assigned_to');

        $this->ensureForeignKey('housekeeping_tasks', 'fk_housekeeping_tasks_room', 'room_id', 'rooms', 'id');
        $this->ensureForeignKey('housekeeping_tasks', 'fk_housekeeping_tasks_booking', 'booking_id', 'room_bookings', 'id');
        $this->ensureForeignKey('housekeeping_tasks', 'fk_housekeeping_tasks_assigned', 'assigned_to', 'users', 'id');
        $this->ensureForeignKey('housekeeping_tasks', 'fk_housekeeping_tasks_created_by', 'created_by', 'users', 'id');
    }

    private function createTaskLogsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS housekeeping_task_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            task_id INT UNSIGNED NOT NULL,
            status ENUM('pending','in_progress','completed','cancelled') NOT NULL,
            notes TEXT NULL,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_task (task_id)
        ) ENGINE=InnoDB";

        $this->db->exec($sql);
    }

    private function syncTaskLogsTable(): void
    {
        $this->addColumnIfMissing('housekeeping_task_logs', 'created_by', 'INT UNSIGNED NULL AFTER notes');
        $this->ensureForeignKey('housekeeping_task_logs', 'fk_housekeeping_task_logs_task', 'task_id', 'housekeeping_tasks', 'id');
        $this->ensureForeignKey('housekeeping_task_logs', 'fk_housekeeping_task_logs_user', 'created_by', 'users', 'id');
    }

    private function createTaskItemsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS housekeeping_task_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            task_id INT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL,
            quantity DECIMAL(18,4) NOT NULL DEFAULT 1,
            notes VARCHAR(255) NULL,
            consumed_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_task_item_task (task_id),
            INDEX idx_task_item_product (product_id),
            CONSTRAINT fk_housekeeping_task_items_task FOREIGN KEY (task_id) REFERENCES housekeeping_tasks(id) ON DELETE CASCADE,
            CONSTRAINT fk_housekeeping_task_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB";
        $this->db->exec($sql);
    }

    private function syncTaskItems(int $taskId, array $items): void
    {
        $normalized = $this->normalizeTaskItems($items);

        $delete = $this->db->prepare('DELETE FROM housekeeping_task_items WHERE task_id = :task_id');
        $delete->execute([':task_id' => $taskId]);

        if (empty($normalized)) {
            return;
        }

        $insert = $this->db->prepare('INSERT INTO housekeeping_task_items (task_id, product_id, quantity, notes, created_at, updated_at) VALUES (:task_id, :product_id, :quantity, :notes, NOW(), NOW())');
        foreach ($normalized as $item) {
            $insert->execute([
                ':task_id' => $taskId,
                ':product_id' => $item['product_id'],
                ':quantity' => $item['quantity'],
                ':notes' => $item['notes'] ?? null,
            ]);
        }
    }

    private function getTaskItems(int $taskId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM housekeeping_task_items WHERE task_id = :task_id');
        $stmt->execute([':task_id' => $taskId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function consumeTaskItems(int $taskId, ?int $userId): void
    {
        $items = $this->getTaskItems($taskId);
        if (empty($items)) {
            return;
        }

        $updateStmt = $this->db->prepare('UPDATE housekeeping_task_items SET consumed_at = NOW(), updated_at = NOW() WHERE id = :id');

        foreach ($items as $item) {
            if (!empty($item['consumed_at'])) {
                continue;
            }

            try {
                $this->inventoryService->recordConsumption((int) $item['product_id'], (float) $item['quantity'], [
                    'reference_type' => 'housekeeping_task',
                    'reference_id' => $taskId,
                    'reference_number' => 'HK-' . $taskId,
                    'source_module' => 'housekeeping',
                    'user_id' => $userId,
                    'notes' => $item['notes'] ?? null,
                ]);
                $updateStmt->execute([':id' => $item['id']]);
            } catch (Throwable $e) {
                error_log('Housekeeping consumable deduction failed for task ' . $taskId . ': ' . $e->getMessage());
            }
        }
    }

    private function normalizeTaskItems($items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $decoded = json_decode($item, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $item = $decoded;
                }
            }

            if (!is_array($item) || !isset($item['product_id'])) {
                continue;
            }

            $productId = (int) $item['product_id'];
            $quantity = isset($item['quantity']) ? (float) $item['quantity'] : 1;

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $normalized[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'notes' => isset($item['notes']) && trim((string)$item['notes']) !== '' ? trim((string)$item['notes']) : null,
            ];
        }

        return $normalized;
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

    private function ensureForeignKey(string $table, string $constraint, string $column, string $refTable, string $refColumn): void
    {
        if (!$this->tableExists($table) || !$this->tableExists($refTable)) {
            return;
        }

        if ($this->foreignKeyExists($table, $constraint)) {
            return;
        }

        $sql = "ALTER TABLE {$table} ADD CONSTRAINT {$constraint} FOREIGN KEY ({$column}) REFERENCES {$refTable}({$refColumn}) ON DELETE SET NULL";

        try {
            $this->db->exec($sql);
        } catch (PDOException $e) {
            // Ignore if engine mismatch or constraint already exists under different name
        }
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $stmt = $this->db->prepare(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND CONSTRAINT_NAME = :constraint
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
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
}
