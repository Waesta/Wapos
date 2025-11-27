<?php

namespace App\Services;

use DateTime;
use Exception;
use PDO;
use PDOException;
use Throwable;

class RestaurantReservationService
{
    private PDO $db;

    private const TABLE_NAME = 'table_reservations';
    private const DEPOSIT_TABLE = 'reservation_deposit_payments';
    private const STATUSES = ['pending', 'confirmed', 'seated', 'completed', 'cancelled', 'no_show'];
    private const BLOCKING_STATUSES = ['pending', 'confirmed', 'seated'];
    private const DEPOSIT_STATUSES = ['not_required', 'pending', 'due', 'paid', 'waived', 'forfeited', 'refunded'];
    private const MANUAL_DEPOSIT_STATUSES = ['waived', 'forfeited', 'refunded'];
    private const PAYMENT_METHODS = ['mobile_money', 'cash', 'card', 'bank_transfer'];
    private const PAYMENT_METHOD_LABELS = [
        'mobile_money' => 'Mobile Money',
        'cash' => 'Cash',
        'card' => 'Card',
        'bank_transfer' => 'Bank Transfer',
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function ensureSchema(): void
    {
        $this->createReservationsTable();
        $this->syncReservationsTable();
        $this->ensureDepositSchema();
    }

    private function createReservationsTable(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS ' . self::TABLE_NAME . ' (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            table_id INT UNSIGNED NOT NULL,
            customer_id INT UNSIGNED NULL,
            guest_name VARCHAR(150) NOT NULL,
            guest_phone VARCHAR(30) NOT NULL,
            party_size INT NOT NULL,
            reservation_date DATE NOT NULL,
            reservation_time TIME NOT NULL,
            duration_minutes INT NOT NULL DEFAULT 120,
            status VARCHAR(30) NOT NULL DEFAULT "confirmed",
            special_requests TEXT NULL,
            internal_notes TEXT NULL,
            deposit_required TINYINT(1) NOT NULL DEFAULT 0,
            deposit_amount DECIMAL(10,2) NULL DEFAULT NULL,
            deposit_due_at DATETIME NULL DEFAULT NULL,
            deposit_status VARCHAR(30) NOT NULL DEFAULT "not_required",
            deposit_total_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            deposit_payment_method VARCHAR(50) NULL DEFAULT NULL,
            deposit_reference VARCHAR(100) NULL DEFAULT NULL,
            deposit_paid_at DATETIME NULL DEFAULT NULL,
            cancellation_policy TEXT NULL,
            deposit_notes TEXT NULL,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            seated_at TIMESTAMP NULL DEFAULT NULL,
            completed_at TIMESTAMP NULL DEFAULT NULL,
            CONSTRAINT fk_reservations_table FOREIGN KEY (table_id) REFERENCES restaurant_tables(id) ON DELETE CASCADE,
            CONSTRAINT fk_reservations_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
            CONSTRAINT fk_reservations_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_reservation_date (reservation_date),
            INDEX idx_reservation_table (table_id),
            INDEX idx_reservation_status (status)
        ) ENGINE=InnoDB';

        $this->db->exec($sql);
    }

    private function syncReservationsTable(): void
    {
        $this->addColumnIfMissing('duration_minutes', 'INT NOT NULL DEFAULT 120');
        $this->addColumnIfMissing('special_requests', 'TEXT NULL');
        $this->addColumnIfMissing('internal_notes', 'TEXT NULL');
        $this->addColumnIfMissing('deposit_required', 'TINYINT(1) NOT NULL DEFAULT 0');
        $this->addColumnIfMissing('deposit_amount', 'DECIMAL(10,2) NULL DEFAULT NULL');
        $this->addColumnIfMissing('deposit_due_at', 'DATETIME NULL DEFAULT NULL');
        $this->addColumnIfMissing('deposit_status', 'VARCHAR(30) NOT NULL DEFAULT "not_required"');
        $this->addColumnIfMissing('deposit_total_paid', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
        $this->addColumnIfMissing('deposit_payment_method', 'VARCHAR(50) NULL DEFAULT NULL');
        $this->addColumnIfMissing('deposit_reference', 'VARCHAR(100) NULL DEFAULT NULL');
        $this->addColumnIfMissing('deposit_paid_at', 'DATETIME NULL DEFAULT NULL');
        $this->addColumnIfMissing('cancellation_policy', 'TEXT NULL');
        $this->addColumnIfMissing('deposit_notes', 'TEXT NULL');
        $this->addColumnIfMissing('seated_at', 'TIMESTAMP NULL DEFAULT NULL');
        $this->addColumnIfMissing('completed_at', 'TIMESTAMP NULL DEFAULT NULL');
        $this->addColumnIfMissing('created_by', 'INT UNSIGNED NULL');
        $this->addForeignKeyIfMissing('fk_reservations_creator', 'created_by', 'users', 'id', 'SET NULL');
        $this->ensureStatusValues();
    }

    private function ensureDepositSchema(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS ' . self::DEPOSIT_TABLE . ' (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reservation_id INT UNSIGNED NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            method VARCHAR(50) NOT NULL,
            reference VARCHAR(100) NULL DEFAULT NULL,
            customer_phone VARCHAR(30) NULL DEFAULT NULL,
            notes TEXT NULL,
            recorded_by INT UNSIGNED NULL,
            recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            metadata TEXT NULL,
            CONSTRAINT fk_reservation_deposit_reservation FOREIGN KEY (reservation_id) REFERENCES ' . self::TABLE_NAME . ' (id) ON DELETE CASCADE,
            CONSTRAINT fk_reservation_deposit_user FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL,
            INDEX idx_reservation_deposit (reservation_id),
            INDEX idx_reservation_deposit_recorded (recorded_at)
        ) ENGINE=InnoDB';

        $this->db->exec($sql);
    }

    private function addColumnIfMissing(string $column, string $definition): void
    {
        $sql = 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':table' => self::TABLE_NAME,
            ':column' => $column,
        ]);

        if ((int)$stmt->fetchColumn() === 0) {
            $this->db->exec('ALTER TABLE ' . self::TABLE_NAME . ' ADD COLUMN ' . $column . ' ' . $definition);
        }
    }

    private function addForeignKeyIfMissing(string $constraintName, string $column, string $refTable, string $refColumn, string $onDelete = 'CASCADE'): void
    {
        $sql = 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE table_schema = DATABASE() AND table_name = :table AND constraint_name = :constraint';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':table' => self::TABLE_NAME,
            ':constraint' => $constraintName,
        ]);

        if ((int)$stmt->fetchColumn() === 0) {
            $this->db->exec(
                'ALTER TABLE ' . self::TABLE_NAME .
                ' ADD CONSTRAINT ' . $constraintName .
                ' FOREIGN KEY (' . $column . ') REFERENCES ' . $refTable . '(' . $refColumn . ') ON DELETE ' . $onDelete
            );
        }
    }

    private function ensureStatusValues(): void
    {
        // Convert legacy ENUMs to plain VARCHAR to allow new statuses and easy updates.
        $sql = 'SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = DATABASE() AND table_name = :table AND column_name = "status"';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':table' => self::TABLE_NAME]);
        $type = $stmt->fetchColumn();

        if ($type && strtolower($type) === 'enum') {
            $this->db->exec('ALTER TABLE ' . self::TABLE_NAME . ' MODIFY status VARCHAR(30) NOT NULL DEFAULT "confirmed"');
        }
    }

    private function appendInternalNote(int $reservationId, string $note, int $userId): void
    {
        $sql = 'UPDATE ' . self::TABLE_NAME . ' SET internal_notes = CONCAT_WS(CHAR(10), internal_notes, :note), updated_at = NOW() WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stamp = '[' . date('Y-m-d H:i') . '] User #' . $userId . ': ' . $note;
        $stmt->execute([
            ':note' => $stamp,
            ':id' => $reservationId,
        ]);
    }

    public function createReservation(array $payload, int $userId): array
    {
        $this->ensureSchema();

        $tableId = isset($payload['table_id']) ? (int)$payload['table_id'] : 0;
        if ($tableId <= 0) {
            throw new Exception('Table selection is required.');
        }

        $partySize = isset($payload['party_size']) ? (int)$payload['party_size'] : 0;
        if ($partySize <= 0) {
            throw new Exception('Party size must be greater than zero.');
        }

        $reservationDate = trim((string)($payload['reservation_date'] ?? ''));
        $reservationTime = trim((string)($payload['reservation_time'] ?? ''));
        if ($reservationDate === '' || $reservationTime === '') {
            throw new Exception('Reservation date and time are required.');
        }

        $durationMinutes = isset($payload['duration_minutes']) && (int)$payload['duration_minutes'] > 0
            ? (int)$payload['duration_minutes']
            : 120;

        $status = strtolower(trim((string)($payload['status'] ?? 'confirmed')));
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'confirmed';
        }

        if (!$this->isTableAvailable($tableId, $reservationDate, $reservationTime, $durationMinutes, null, $status)) {
            throw new Exception('Selected table is not available for the requested time slot.');
        }

        $guestName = trim((string)($payload['guest_name'] ?? ''));
        if ($guestName === '') {
            throw new Exception('Guest name is required.');
        }

        $guestPhone = trim((string)($payload['guest_phone'] ?? ''));
        if ($guestPhone === '') {
            throw new Exception('Guest phone is required.');
        }

        $customerId = isset($payload['customer_id']) && $payload['customer_id'] !== ''
            ? (int)$payload['customer_id']
            : null;

        $specialRequests = trim((string)($payload['special_requests'] ?? '')) ?: null;

        $depositRequired = !empty($payload['deposit_required']) ? 1 : 0;
        $depositAmount = $this->sanitizeMoneyValue($payload['deposit_amount'] ?? null);
        if ($depositRequired) {
            if ($depositAmount === null || $depositAmount <= 0) {
                throw new Exception('Deposit amount must be greater than zero when required.');
            }
        } else {
            $depositAmount = null;
        }

        $depositDueAt = $depositRequired
            ? $this->validateDepositDueDate($payload['deposit_due_at'] ?? null, $reservationDate, $reservationTime)
            : null;
        $cancellationPolicy = trim((string)($payload['cancellation_policy'] ?? '')) ?: null;
        $depositNotes = trim((string)($payload['deposit_notes'] ?? '')) ?: null;
        $initialDepositStatus = $depositRequired ? 'pending' : 'not_required';

        $stmt = $this->db->prepare(
            'INSERT INTO ' . self::TABLE_NAME . ' (
                table_id,
                customer_id,
                guest_name,
                guest_phone,
                party_size,
                reservation_date,
                reservation_time,
                duration_minutes,
                status,
                special_requests,
                deposit_required,
                deposit_amount,
                deposit_due_at,
                deposit_status,
                deposit_total_paid,
                deposit_payment_method,
                deposit_reference,
                deposit_paid_at,
                cancellation_policy,
                deposit_notes,
                created_by,
                created_at,
                updated_at
            ) VALUES (
                :table_id,
                :customer_id,
                :guest_name,
                :guest_phone,
                :party_size,
                :reservation_date,
                :reservation_time,
                :duration_minutes,
                :status,
                :special_requests,
                :deposit_required,
                :deposit_amount,
                :deposit_due_at,
                :deposit_status,
                :deposit_total_paid,
                :deposit_payment_method,
                :deposit_reference,
                :deposit_paid_at,
                :cancellation_policy,
                :deposit_notes,
                :created_by,
                NOW(),
                NOW()
            )'
        );

        $stmt->execute([
            ':table_id' => $tableId,
            ':customer_id' => $customerId,
            ':guest_name' => $guestName,
            ':guest_phone' => $guestPhone,
            ':party_size' => $partySize,
            ':reservation_date' => $reservationDate,
            ':reservation_time' => $reservationTime,
            ':duration_minutes' => $durationMinutes,
            ':status' => $status,
            ':special_requests' => $specialRequests,
            ':deposit_required' => $depositRequired,
            ':deposit_amount' => $depositAmount,
            ':deposit_due_at' => $depositDueAt,
            ':deposit_status' => $initialDepositStatus,
            ':deposit_total_paid' => 0.00,
            ':deposit_payment_method' => null,
            ':deposit_reference' => null,
            ':deposit_paid_at' => null,
            ':cancellation_policy' => $cancellationPolicy,
            ':deposit_notes' => $depositNotes,
            ':created_by' => $userId,
        ]);

        $reservationId = (int)$this->db->lastInsertId();
        return $this->refreshDepositSnapshot($reservationId);
    }

    public function updateReservation(int $reservationId, array $payload): array
    {
        $this->ensureSchema();

        $existing = $this->getReservation($reservationId);
        if (!$existing) {
            throw new Exception('Reservation not found.');
        }

        $fields = [];
        $params = [':id' => $reservationId];

        if (array_key_exists('table_id', $payload)) {
            $tableId = (int)$payload['table_id'];
            if ($tableId <= 0) {
                throw new Exception('Table selection is required.');
            }
            $fields[] = 'table_id = :table_id';
            $params[':table_id'] = $tableId;
        } else {
            $tableId = (int)$existing['table_id'];
        }

        if (array_key_exists('party_size', $payload)) {
            $partySize = (int)$payload['party_size'];
            if ($partySize <= 0) {
                throw new Exception('Party size must be greater than zero.');
            }
            $fields[] = 'party_size = :party_size';
            $params[':party_size'] = $partySize;
        } else {
            $partySize = (int)$existing['party_size'];
        }

        $reservationDate = $existing['reservation_date'];
        $reservationTime = $existing['reservation_time'];
        $durationMinutes = (int)$existing['duration_minutes'];

        if (array_key_exists('reservation_date', $payload)) {
            $reservationDate = trim((string)$payload['reservation_date']);
            if ($reservationDate === '') {
                throw new Exception('Reservation date cannot be empty.');
            }
            $fields[] = 'reservation_date = :reservation_date';
            $params[':reservation_date'] = $reservationDate;
        }

        if (array_key_exists('reservation_time', $payload)) {
            $reservationTime = trim((string)$payload['reservation_time']);
            if ($reservationTime === '') {
                throw new Exception('Reservation time cannot be empty.');
            }
            $fields[] = 'reservation_time = :reservation_time';
            $params[':reservation_time'] = $reservationTime;
        }

        if (array_key_exists('duration_minutes', $payload)) {
            $durationMinutes = (int)$payload['duration_minutes'];
            if ($durationMinutes <= 0) {
                $durationMinutes = 120;
            }
            $fields[] = 'duration_minutes = :duration_minutes';
            $params[':duration_minutes'] = $durationMinutes;
        }

        $status = $existing['status'];
        if (array_key_exists('status', $payload)) {
            $candidateStatus = strtolower(trim((string)$payload['status']));
            if (in_array($candidateStatus, self::STATUSES, true)) {
                $status = $candidateStatus;
                $fields[] = 'status = :status';
                $params[':status'] = $status;
            }
        }

        if (!$this->isTableAvailable($tableId, $reservationDate, $reservationTime, $durationMinutes, $reservationId, $status)) {
            throw new Exception('Selected table is not available for the requested time slot.');
        }

        if (array_key_exists('guest_name', $payload)) {
            $guestName = trim((string)$payload['guest_name']);
            if ($guestName === '') {
                throw new Exception('Guest name cannot be empty.');
            }
            $fields[] = 'guest_name = :guest_name';
            $params[':guest_name'] = $guestName;
        }

        if (array_key_exists('guest_phone', $payload)) {
            $guestPhone = trim((string)$payload['guest_phone']);
            if ($guestPhone === '') {
                throw new Exception('Guest phone cannot be empty.');
            }
            $fields[] = 'guest_phone = :guest_phone';
            $params[':guest_phone'] = $guestPhone;
        }

        if (array_key_exists('customer_id', $payload)) {
            $customerId = $payload['customer_id'] !== '' ? (int)$payload['customer_id'] : null;
            $fields[] = 'customer_id = :customer_id';
            $params[':customer_id'] = $customerId;
        }

        if (array_key_exists('special_requests', $payload)) {
            $specialRequests = trim((string)$payload['special_requests']);
            $fields[] = 'special_requests = :special_requests';
            $params[':special_requests'] = $specialRequests !== '' ? $specialRequests : null;
        }

        $depositRequired = (int)$existing['deposit_required'];
        if (array_key_exists('deposit_required', $payload)) {
            $depositRequired = !empty($payload['deposit_required']) ? 1 : 0;
            $fields[] = 'deposit_required = :deposit_required';
            $params[':deposit_required'] = $depositRequired;

            if ($depositRequired === 0) {
                $fields[] = 'deposit_status = :deposit_status_reset';
                $params[':deposit_status_reset'] = 'not_required';
            } elseif ((int)$existing['deposit_required'] === 0) {
                $fields[] = 'deposit_status = :deposit_status_pending';
                $params[':deposit_status_pending'] = 'pending';
            }
        }

        if ($depositRequired) {
            if (array_key_exists('deposit_amount', $payload)) {
                $newDepositAmount = $this->sanitizeMoneyValue($payload['deposit_amount']);
                if ($newDepositAmount === null || $newDepositAmount <= 0) {
                    throw new Exception('Deposit amount must be greater than zero when required.');
                }
                $fields[] = 'deposit_amount = :deposit_amount';
                $params[':deposit_amount'] = $newDepositAmount;
            } elseif ($existing['deposit_amount'] === null || (float)$existing['deposit_amount'] <= 0) {
                throw new Exception('Deposit amount must be greater than zero when required.');
            }

            if (array_key_exists('deposit_due_at', $payload)) {
                $depositDueAt = $this->validateDepositDueDate($payload['deposit_due_at'], $reservationDate, $reservationTime);
                $fields[] = 'deposit_due_at = :deposit_due_at';
                $params[':deposit_due_at'] = $depositDueAt;
            }
        } else {
            if ($existing['deposit_amount'] !== null || array_key_exists('deposit_amount', $payload)) {
                $fields[] = 'deposit_amount = :deposit_amount_reset';
                $params[':deposit_amount_reset'] = null;
            }

            if ($existing['deposit_due_at'] !== null || array_key_exists('deposit_due_at', $payload)) {
                $fields[] = 'deposit_due_at = :deposit_due_at_reset';
                $params[':deposit_due_at_reset'] = null;
            }
        }

        if (array_key_exists('cancellation_policy', $payload)) {
            $policy = trim((string)$payload['cancellation_policy']);
            $fields[] = 'cancellation_policy = :cancellation_policy';
            $params[':cancellation_policy'] = $policy !== '' ? $policy : null;
        }

        if (array_key_exists('deposit_notes', $payload)) {
            $notes = trim((string)$payload['deposit_notes']);
            $fields[] = 'deposit_notes = :deposit_notes';
            $params[':deposit_notes'] = $notes !== '' ? $notes : null;
        }

        if (empty($fields)) {
            return $existing;
        }

        $fields[] = 'updated_at = NOW()';
        $sql = 'UPDATE ' . self::TABLE_NAME . ' SET ' . implode(', ', $fields) . ' WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->refreshDepositSnapshot($reservationId);
    }

    public function updateStatus(int $reservationId, string $status, ?string $notes, int $userId): array
    {
        $this->ensureSchema();

        $status = strtolower(trim($status));
        if (!in_array($status, self::STATUSES, true)) {
            throw new Exception('Unsupported reservation status.');
        }

        $reservation = $this->getReservation($reservationId);
        if (!$reservation) {
            throw new Exception('Reservation not found.');
        }

        $fields = ['status = :status', 'updated_at = NOW()'];
        $params = [
            ':status' => $status,
            ':id' => $reservationId,
        ];

        if ($status === 'seated') {
            $fields[] = 'seated_at = NOW()';
        }

        if (in_array($status, ['completed', 'cancelled', 'no_show'], true)) {
            $fields[] = 'completed_at = NOW()';
        }

        $stmt = $this->db->prepare('UPDATE ' . self::TABLE_NAME . ' SET ' . implode(', ', $fields) . ' WHERE id = :id');
        $stmt->execute($params);

        if ($notes && trim($notes) !== '') {
            // store note in special_requests append
            $noteText = trim($notes);
            $this->appendInternalNote($reservationId, $noteText, $userId);
        }

        return $this->getReservation($reservationId) ?? [];
    }

    public function getSummary(?string $date): array
    {
        $this->ensureSchema();

        $targetDate = $date;
        if (!$targetDate || trim($targetDate) === '') {
            $targetDate = date('Y-m-d');
        }

        try {
            $targetDate = (new DateTime($targetDate))->format('Y-m-d');
        } catch (Exception $e) {
            throw new Exception('Invalid date supplied for summary.');
        }

        $summary = [
            'date' => $targetDate,
            'total' => 0,
        ];

        foreach (self::STATUSES as $status) {
            $summary[$status] = 0;
        }

        $stmt = $this->db->prepare(
            'SELECT status, COUNT(*) AS count
             FROM ' . self::TABLE_NAME . '
             WHERE reservation_date = :reservation_date
             GROUP BY status'
        );
        $stmt->execute([':reservation_date' => $targetDate]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $status = $row['status'] ?? '';
            $count = (int)($row['count'] ?? 0);
            if ($status !== '' && array_key_exists($status, $summary)) {
                $summary[$status] = $count;
                $summary['total'] += $count;
            }
        }

        return $summary;
    }

    public function getReservation(int $reservationId): ?array
    {
        $reservation = $this->fetchReservationRow($reservationId);
        if (!$reservation) {
            return null;
        }

        return $this->refreshDepositSnapshot($reservationId, $reservation);
    }

    private function formatInternalNotesHistory(?string $rawNotes): array
    {
        if ($rawNotes === null || trim($rawNotes) === '') {
            return [];
        }

        $lines = preg_split('/\r?\n/', $rawNotes) ?: [];
        $trimmed = array_filter(array_map('trim', $lines));

        return array_values($trimmed);
    }

    public function getReservations(array $filters = []): array
    {
        $this->ensureSchema();

        $conditions = [];
        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $conditions[] = 'r.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['table_id'])) {
            $conditions[] = 'r.table_id = :table_id';
            $params[':table_id'] = (int)$filters['table_id'];
        }

        if (!empty($filters['date'])) {
            $conditions[] = 'r.reservation_date = :reservation_date';
            $params[':reservation_date'] = $filters['date'];
        }

        if (!empty($filters['from_date'])) {
            $conditions[] = 'r.reservation_date >= :from_date';
            $params[':from_date'] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $conditions[] = 'r.reservation_date <= :to_date';
            $params[':to_date'] = $filters['to_date'];
        }

        $sql = '
            SELECT r.*, t.table_number, t.table_name, t.capacity,
                   CONCAT_WS(" ", creator.full_name, creator.username) AS created_by_name
            FROM ' . self::TABLE_NAME . ' r
            LEFT JOIN restaurant_tables t ON r.table_id = t.id
            LEFT JOIN users creator ON creator.id = r.created_by
        ';

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY r.reservation_date ASC, r.reservation_time ASC';

        if (!empty($filters['limit'])) {
            $limit = max(1, (int)$filters['limit']);
            $sql .= ' LIMIT ' . $limit;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }

    public function getDepositPayments(int $reservationId): array
    {
        $this->ensureSchema();

        $stmt = $this->db->prepare(
            'SELECT id, reservation_id, amount, method, reference, customer_phone, notes, recorded_by, recorded_at
             FROM ' . self::DEPOSIT_TABLE . '
             WHERE reservation_id = :reservation_id
             ORDER BY recorded_at ASC, id ASC'
        );
        $stmt->execute([':reservation_id' => $reservationId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function recordDepositPayment(int $reservationId, array $payload, int $userId): array
    {
        $this->ensureSchema();

        $reservation = $this->fetchReservationRow($reservationId);
        if (!$reservation) {
            throw new Exception('Reservation not found.');
        }

        if ((int)$reservation['deposit_required'] === 0) {
            throw new Exception('Deposit is not required for this reservation.');
        }

        $amount = $this->sanitizeMoneyValue($payload['amount'] ?? null);
        if ($amount === null || $amount <= 0) {
            throw new Exception('Deposit payment amount must be greater than zero.');
        }

        $method = strtolower(trim((string)($payload['method'] ?? '')));
        if ($method === '') {
            throw new Exception('Payment method is required.');
        }
        if (!in_array($method, self::PAYMENT_METHODS, true)) {
            throw new Exception('Unsupported payment method.');
        }

        $reference = trim((string)($payload['reference'] ?? '')) ?: null;
        $customerPhone = trim((string)($payload['customer_phone'] ?? '')) ?: null;
        if ($method === 'mobile_money') {
            if (!$customerPhone || strlen($customerPhone) < 7) {
                throw new Exception('Mobile money payments require the customer phone number.');
            }
        } else {
            $customerPhone = null;
        }
        $notes = trim((string)($payload['notes'] ?? '')) ?: null;

        $stmt = $this->db->prepare(
            'INSERT INTO ' . self::DEPOSIT_TABLE . ' (reservation_id, amount, method, reference, customer_phone, notes, recorded_by, recorded_at)
             VALUES (:reservation_id, :amount, :method, :reference, :customer_phone, :notes, :recorded_by, NOW())'
        );

        $stmt->execute([
            ':reservation_id' => $reservationId,
            ':amount' => $amount,
            ':method' => $method,
            ':reference' => $reference,
            ':customer_phone' => $customerPhone,
            ':notes' => $notes,
            ':recorded_by' => $userId > 0 ? $userId : null,
        ]);

        return $this->refreshDepositSnapshot($reservationId);
    }

    private function refreshDepositSnapshot(int $reservationId, ?array $reservation = null): array
    {
        $reservation = $reservation ?? $this->fetchReservationRow($reservationId);
        if (!$reservation) {
            throw new Exception('Reservation not found.');
        }

        $depositRequired = (int)($reservation['deposit_required'] ?? 0) === 1;
        $depositAmount = $reservation['deposit_amount'] !== null ? (float)$reservation['deposit_amount'] : null;
        $payments = $depositRequired ? $this->getDepositPayments($reservationId) : [];

        $totalPaid = 0.0;
        $lastPayment = null;
        foreach ($payments as $payment) {
            $totalPaid += (float)$payment['amount'];
            $lastPayment = $payment;
        }

        $currentStatus = $reservation['deposit_status'] ?? 'not_required';
        $newStatus = $currentStatus;
        if (!$depositRequired) {
            $newStatus = 'not_required';
        } elseif (!in_array($currentStatus, self::MANUAL_DEPOSIT_STATUSES, true)) {
            if ($depositAmount !== null && $depositAmount > 0 && ($totalPaid + 0.0001) >= $depositAmount) {
                $newStatus = 'paid';
            } elseif (!empty($reservation['deposit_due_at']) && strtotime((string)$reservation['deposit_due_at']) < time()) {
                $newStatus = 'due';
            } else {
                $newStatus = 'pending';
            }
        }

        $updateStmt = $this->db->prepare(
            'UPDATE ' . self::TABLE_NAME . ' SET
                deposit_total_paid = :total_paid,
                deposit_payment_method = :payment_method,
                deposit_reference = :deposit_reference,
                deposit_paid_at = :deposit_paid_at,
                deposit_status = :deposit_status,
                updated_at = NOW()
             WHERE id = :id'
        );

        $updateStmt->execute([
            ':total_paid' => round($totalPaid, 2),
            ':payment_method' => $lastPayment['method'] ?? null,
            ':deposit_reference' => $lastPayment['reference'] ?? null,
            ':deposit_paid_at' => $lastPayment['recorded_at'] ?? null,
            ':deposit_status' => $newStatus,
            ':id' => $reservationId,
        ]);

        $reservation['deposit_total_paid'] = round($totalPaid, 2);
        $reservation['deposit_payment_method'] = $lastPayment['method'] ?? null;
        $reservation['deposit_reference'] = $lastPayment['reference'] ?? null;
        $reservation['deposit_paid_at'] = $lastPayment['recorded_at'] ?? null;
        $reservation['deposit_status'] = $newStatus;
        $reservation['deposit_payments'] = $payments;
        $reservation['deposit_balance'] = $depositAmount !== null ? max(0.0, round($depositAmount - $totalPaid, 2)) : null;
        $reservation['internal_notes_history'] = $this->formatInternalNotesHistory($reservation['internal_notes'] ?? null);
        $reservation['status_timestamps'] = [
            'created_at' => $reservation['created_at'] ?? null,
            'seated_at' => $reservation['seated_at'] ?? null,
            'completed_at' => $reservation['completed_at'] ?? null,
        ];

        return $reservation;
    }

    private function fetchReservationRow(int $reservationId): ?array
    {
        $sql = '
            SELECT r.*, 
                   t.table_number,
                   t.table_name,
                   t.capacity,
                   CONCAT_WS(" ", creator.full_name, creator.username) AS created_by_name
            FROM ' . self::TABLE_NAME . ' r
            LEFT JOIN restaurant_tables t ON r.table_id = t.id
            LEFT JOIN users creator ON creator.id = r.created_by
            WHERE r.id = :id
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $reservationId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function sanitizeMoneyValue($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $normalized = str_replace([',', ' '], '', $value);
        } else {
            $normalized = (string)$value;
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        return round((float)$normalized, 2);
    }

    private function validateDepositDueDate(?string $rawDueAt, string $reservationDate, string $reservationTime): string
    {
        $rawDueAt = $rawDueAt ? trim($rawDueAt) : '';
        if ($rawDueAt === '') {
            throw new Exception('Deposit due date/time is required when a deposit is enabled.');
        }

        $prepared = str_contains($rawDueAt, 'T') ? str_replace('T', ' ', $rawDueAt) : $rawDueAt;
        $dueAt = DateTime::createFromFormat('Y-m-d H:i', $prepared) ?: new DateTime($prepared);

        $reservationStart = new DateTime($reservationDate . ' ' . $reservationTime);
        if ($dueAt > $reservationStart) {
            throw new Exception('Deposit due date must be on or before the reservation start time.');
        }

        return $dueAt->format('Y-m-d H:i:s');
    }
}
