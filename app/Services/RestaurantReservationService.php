<?php

namespace App\Services;

use DateTime;
use Exception;
use PDO;
use PDOException;

class RestaurantReservationService
{
    private PDO $db;

    private const TABLE_NAME = 'table_reservations';
    private const STATUSES = ['pending', 'confirmed', 'seated', 'completed', 'cancelled', 'no_show'];
    private const BLOCKING_STATUSES = ['pending', 'confirmed', 'seated'];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function ensureSchema(): void
    {
        $this->createReservationsTable();
        $this->syncReservationsTable();
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
        $this->addColumnIfMissing('seated_at', 'TIMESTAMP NULL DEFAULT NULL');
        $this->addColumnIfMissing('completed_at', 'TIMESTAMP NULL DEFAULT NULL');
        $this->addColumnIfMissing('created_by', 'INT UNSIGNED NULL');
        $this->addForeignKeyIfMissing('fk_reservations_creator', 'created_by', 'users', 'id', 'SET NULL');
        $this->ensureStatusValues();
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
            ':created_by' => $userId,
        ]);

        $reservationId = (int)$this->db->lastInsertId();
        return $this->getReservation($reservationId) ?? [];
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

        if (empty($fields)) {
            return $existing;
        }

        $fields[] = 'updated_at = NOW()';
        $sql = 'UPDATE ' . self::TABLE_NAME . ' SET ' . implode(', ', $fields) . ' WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->getReservation($reservationId) ?? [];
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

        if ($row) {
            $row['internal_notes_history'] = $this->formatInternalNotesHistory($row['internal_notes'] ?? null);
            $row['status_timestamps'] = [
                'created_at' => $row['created_at'] ?? null,
                'seated_at' => $row['seated_at'] ?? null,
                'completed_at' => $row['completed_at'] ?? null,
            ];
        }

        return $row ?: null;
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
}
