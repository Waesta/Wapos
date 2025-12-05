<?php

namespace App\Services;

use DateTime;
use Exception;
use PDO;

class RoomBookingService
{
    private PDO $db;

    private const LEGACY_BOOKINGS_TABLE = 'bookings';
    private const NEW_BOOKINGS_TABLE = 'room_bookings';
    private const FOLIOS_TABLE = 'room_folios';
    private const PAYMENTS_TABLE = 'room_booking_payments';
    private const PAYMENT_METHODS = ['mobile_money', 'cash', 'card', 'bank_transfer'];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    private function createRoomBookingPaymentsTable(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS ' . self::PAYMENTS_TABLE . ' (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_id INT UNSIGNED NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            method VARCHAR(50) NOT NULL,
            reference VARCHAR(100) NULL,
            customer_phone VARCHAR(30) NULL,
            notes TEXT NULL,
            gateway_reference VARCHAR(100) NULL,
            recorded_by INT UNSIGNED NULL,
            recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_room_booking_payment_booking FOREIGN KEY (booking_id) REFERENCES ' . self::NEW_BOOKINGS_TABLE . ' (id) ON DELETE CASCADE,
            INDEX idx_booking_payment_booking (booking_id)
        ) ENGINE=InnoDB';

        try {
            $this->db->exec($sql);
        } catch (Exception $e) {
            $fallback = 'CREATE TABLE IF NOT EXISTS ' . self::PAYMENTS_TABLE . ' (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                booking_id INT UNSIGNED NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                method VARCHAR(50) NOT NULL,
                reference VARCHAR(100) NULL,
                customer_phone VARCHAR(30) NULL,
                notes TEXT NULL,
                gateway_reference VARCHAR(100) NULL,
                recorded_by INT UNSIGNED NULL,
                recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_booking_payment_booking (booking_id)
            ) ENGINE=InnoDB';
            $this->db->exec($fallback);
        }
    }

    private function recordBookingPayment(int $bookingId, array $payload, ?int $userId = null): void
    {
        $amount = isset($payload['amount']) ? (float)$payload['amount'] : 0;
        if ($amount <= 0) {
            throw new Exception('Payment amount must be greater than zero.');
        }

        $method = strtolower(trim((string)($payload['method'] ?? '')));
        if (!in_array($method, self::PAYMENT_METHODS, true)) {
            throw new Exception('Unsupported payment method.');
        }

        $reference = trim((string)($payload['reference'] ?? '')) ?: null;
        $customerPhone = trim((string)($payload['customer_phone'] ?? '')) ?: null;
        if ($method === 'mobile_money') {
            if (!$customerPhone || strlen($customerPhone) < 7) {
                throw new Exception('Customer phone number is required for mobile money payments.');
            }
        } else {
            $customerPhone = null;
        }

        $notes = trim((string)($payload['notes'] ?? '')) ?: null;
        $gatewayReference = trim((string)($payload['gateway_reference'] ?? '')) ?: null;
        $recordedAt = $payload['date'] ?? null;

        $stmt = $this->db->prepare(
            'INSERT INTO ' . self::PAYMENTS_TABLE . ' (room_booking_id, amount, payment_method, reference_number, notes, received_by, received_at)
             VALUES (:booking_id, :amount, :method, :reference, :notes, :recorded_by, COALESCE(:recorded_at, NOW()))'
        );

        $stmt->execute([
            ':booking_id' => $bookingId,
            ':amount' => $amount,
            ':method' => $method,
            ':reference' => $reference,
            ':notes' => $notes,
            ':recorded_by' => $userId ?: null,
            ':recorded_at' => $recordedAt,
        ]);
    }

    /**
     * Ensure modern room-booking tables exist.
     */
    public function ensureSchema(): void
    {
        $hasCustomers = $this->tableExists('customers');
        $hasUsers = $this->tableExists('users');

        if (!$this->tableExists(self::NEW_BOOKINGS_TABLE)) {
            $this->createRoomBookingsTable($hasCustomers, $hasUsers);
        } else {
            $this->syncRoomBookingsColumns($hasCustomers);
        }

        if (!$this->tableExists(self::FOLIOS_TABLE)) {
            $this->createRoomFoliosTable();
        } else {
            // Check if booking_id column exists - if not, table has wrong schema
            $columns = $this->getTableColumns(self::FOLIOS_TABLE);
            if (!in_array('booking_id', $columns, true)) {
                // Drop and recreate with correct schema (disable FK checks)
                try {
                    $this->db->exec("SET FOREIGN_KEY_CHECKS = 0");
                    $this->db->exec("DROP TABLE IF EXISTS " . self::FOLIOS_TABLE);
                    $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");
                    $this->createRoomFoliosTable();
                } catch (\Exception $e) {
                    error_log("Failed to recreate room_folios table: " . $e->getMessage());
                    // Last resort - try to add the column
                    try {
                        $this->db->exec("ALTER TABLE " . self::FOLIOS_TABLE . " ADD COLUMN booking_id INT NOT NULL DEFAULT 0 FIRST");
                    } catch (\Exception $e2) {
                        error_log("Failed to add booking_id column: " . $e2->getMessage());
                    }
                }
            } else {
                $this->syncRoomFoliosColumns();
            }
        }

        if (!$this->tableExists(self::PAYMENTS_TABLE)) {
            $this->createRoomBookingPaymentsTable();
        }

        $this->ensureMigrationTable();

        if (!$this->schemaReady()) {
            $this->createBasicRoomSchema();
            if (!$this->schemaReady()) {
                throw new Exception('Room folio tables could not be created. Please review database permissions and engine support.');
            }
        }
    }

    /**
     * Migrate legacy booking rows into the new room_bookings/room_folios tables.
     *
     * @return array{migrated:int,skipped:int}
     * @throws Exception
     */
    public function migrateLegacyBookings(): array
    {
        $this->ensureSchema();

        $legacyExists = $this->tableExists(self::LEGACY_BOOKINGS_TABLE);
        if (!$legacyExists) {
            return ['migrated' => 0, 'skipped' => 0];
        }

        $migrated = 0;
        $skipped = 0;

        $stmt = $this->db->query(
            "SELECT * FROM " . self::LEGACY_BOOKINGS_TABLE . " ORDER BY id ASC"
        );
        $bookings = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        foreach ($bookings as $legacy) {
            $legacyId = (int)$legacy['id'];

            if ($this->alreadyMigrated($legacyId)) {
                $skipped++;
                continue;
            }

            $this->db->beginTransaction();
            try {
                $newBookingId = $this->insertRoomBooking($legacy);
                $this->seedRoomFolio($newBookingId, $legacy);
                $this->flagMigrated($legacyId, $newBookingId);
                $this->db->commit();
                $migrated++;
            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
        }

        return ['migrated' => $migrated, 'skipped' => $skipped];
    }

    private function insertRoomBooking(array $legacy): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO room_bookings (
                booking_number,
                room_id,
                customer_id,
                legacy_booking_id,
                guest_name,
                guest_phone,
                guest_email,
                guest_id_number,
                check_in_date,
                check_out_date,
                actual_check_in,
                actual_check_out,
                adults,
                children,
                total_nights,
                rate_per_night,
                total_amount,
                amount_paid,
                payment_status,
                status,
                notes,
                created_by,
                created_at,
                updated_at
            ) VALUES (
                :booking_number,
                :room_id,
                :customer_id,
                :legacy_booking_id,
                :guest_name,
                :guest_phone,
                :guest_email,
                :guest_id_number,
                :check_in_date,
                :check_out_date,
                :actual_check_in,
                :actual_check_out,
                :adults,
                :children,
                :total_nights,
                :rate_per_night,
                :total_amount,
                :amount_paid,
                :payment_status,
                :status,
                :notes,
                :created_by,
                :created_at,
                :updated_at
            )"
        );

        $bookingStatus = $legacy['booking_status'] ?? 'confirmed';
        $statusMap = [
            'checked-in' => 'checked_in',
            'checked-out' => 'checked_out',
            'confirmed' => 'confirmed',
            'cancelled' => 'cancelled',
            'pending' => 'pending',
        ];
        $normalizedStatus = $statusMap[$bookingStatus] ?? 'confirmed';

        $checkInTime = $legacy['check_in_time'] ?? null;
        $checkOutTime = $legacy['check_out_time'] ?? null;

        $createdAt = $legacy['created_at'] ?? date('Y-m-d H:i:s');
        $updatedAt = $legacy['updated_at'] ?? $createdAt;

        $stmt->execute([
            ':booking_number' => $legacy['booking_number'] ?? ('BK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6))),
            ':room_id' => (int)$legacy['room_id'],
            ':customer_id' => $legacy['customer_id'] ?? null,
            ':legacy_booking_id' => $legacy['id'] ?? null,
            ':guest_name' => $legacy['guest_name'] ?? 'Unknown Guest',
            ':guest_phone' => $legacy['guest_phone'] ?? '',
            ':guest_email' => $legacy['guest_email'] ?? null,
            ':guest_id_number' => $legacy['guest_id_number'] ?? null,
            ':check_in_date' => $legacy['check_in_date'],
            ':check_out_date' => $legacy['check_out_date'],
            ':actual_check_in' => $checkInTime ?: null,
            ':actual_check_out' => $checkOutTime ?: null,
            ':adults' => (int)($legacy['adults'] ?? 1),
            ':children' => (int)($legacy['children'] ?? 0),
            ':total_nights' => (int)($legacy['total_nights'] ?? $this->calculateNights($legacy['check_in_date'], $legacy['check_out_date'])),
            ':rate_per_night' => (float)($legacy['room_rate'] ?? 0),
            ':total_amount' => (float)($legacy['total_amount'] ?? 0),
            ':amount_paid' => (float)($legacy['amount_paid'] ?? 0),
            ':payment_status' => $legacy['payment_status'] ?? 'pending',
            ':status' => $normalizedStatus,
            ':notes' => $legacy['special_requests'] ?? null,
            ':created_by' => $legacy['user_id'] ?? null,
            ':created_at' => $createdAt,
            ':updated_at' => $updatedAt,
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function seedRoomFolio(int $bookingId, array $legacy): void
    {
        $ratePerNight = (float)($legacy['room_rate'] ?? 0);
        $nights = (int)($legacy['total_nights'] ?? 0);
        if ($ratePerNight <= 0 || $nights <= 0) {
            return;
        }

        $chargeDate = DateTime::createFromFormat('Y-m-d', (string)$legacy['check_in_date']);
        $folioDate = $chargeDate ? $chargeDate->format('Y-m-d') : date('Y-m-d');

        $stmt = $this->db->prepare(
            "INSERT INTO room_folios (
                booking_id,
                item_type,
                description,
                amount,
                quantity,
                date_charged,
                created_by
            ) VALUES (
                :booking_id,
                :item_type,
                :description,
                :amount,
                :quantity,
                :date_charged,
                :created_by
            )"
        );

        $stmt->execute([
            ':booking_id' => $bookingId,
            ':item_type' => 'room_charge',
            ':description' => 'Room charge',
            ':amount' => $ratePerNight * $nights,
            ':quantity' => $nights,
            ':date_charged' => $folioDate,
            ':created_by' => $legacy['user_id'] ?? null,
        ]);

        $amountPaid = (float)($legacy['amount_paid'] ?? 0);
        if ($amountPaid > 0) {
            $paymentStmt = $this->db->prepare(
                "INSERT INTO room_folios (
                    booking_id,
                    item_type,
                    description,
                    amount,
                    quantity,
                    date_charged,
                    created_by
                ) VALUES (
                    :booking_id,
                    :item_type,
                    :description,
                    :amount,
                    :quantity,
                    :date_charged,
                    :created_by
                )"
            );

            $paymentStmt->execute([
                ':booking_id' => $bookingId,
                ':item_type' => 'payment',
                ':description' => 'Legacy payment balance',
                ':amount' => -1 * $amountPaid,
                ':quantity' => 1,
                ':date_charged' => $folioDate,
                ':created_by' => $legacy['user_id'] ?? null,
            ]);
        }
    }

    /**
     * Create a new booking with folio seed.
     *
     * @param array $payload
     * @param int $userId
     * @return array{booking_id:int,booking_number:string,total_amount:float,total_nights:int}
     * @throws Exception
     */
    public function createBooking(array $payload, int $userId): array
    {
        $this->ensureSchema();

        $roomId = (int)($payload['room_id'] ?? 0);
        if ($roomId <= 0) {
            throw new Exception('Room selection is required.');
        }

        $checkInDate = $payload['check_in_date'] ?? null;
        $checkOutDate = $payload['check_out_date'] ?? null;
        if (!$checkInDate || !$checkOutDate) {
            throw new Exception('Check-in and check-out dates are required.');
        }

        $nights = $this->calculateNights($checkInDate, $checkOutDate);
        if ($nights <= 0) {
            throw new Exception('Check-out date must be after check-in date.');
        }

        $rate = isset($payload['rate_per_night']) ? (float)$payload['rate_per_night'] : $this->fetchRoomRate($roomId);
        if ($rate <= 0) {
            throw new Exception('Room rate must be greater than zero.');
        }

        $totalAmount = round($rate * $nights, 2);
        $deposit = isset($payload['deposit_amount']) ? max(0, (float)$payload['deposit_amount']) : 0.0;
        $depositMethod = $payload['deposit_method'] ?? null;
        if ($deposit > 0) {
            if (!$depositMethod || !in_array($depositMethod, self::PAYMENT_METHODS, true)) {
                throw new Exception('Deposit payment method is required.');
            }
            if ($depositMethod === 'mobile_money') {
                $depositPhone = trim((string)($payload['deposit_customer_phone'] ?? ''));
                if ($depositPhone === '' || strlen($depositPhone) < 7) {
                    throw new Exception('Customer phone number is required for mobile money deposits.');
                }
            }
        }
        $paymentStatus = $deposit >= $totalAmount ? 'paid' : ($deposit > 0 ? 'partial' : 'unpaid');

        $bookingNumber = $this->generateBookingNumber();

        $guestName = trim((string)($payload['guest_name'] ?? ''));
        if ($guestName === '') {
            throw new Exception('Guest name is required.');
        }

        $guestPhone = trim((string)($payload['guest_phone'] ?? ''));
        if ($guestPhone === '') {
            throw new Exception('Guest phone is required.');
        }

        $customerId = $payload['customer_id'] ?? null;
        $customerId = ($customerId !== '' && $customerId !== null && $customerId !== 0) ? (int)$customerId : null;
        
        // Validate customer_id exists if provided
        if ($customerId !== null) {
            $checkCustomer = $this->db->prepare("SELECT id FROM customers WHERE id = ?");
            $checkCustomer->execute([$customerId]);
            if (!$checkCustomer->fetch()) {
                $customerId = null; // Customer doesn't exist, set to null
            }
        }

        $adults = max(1, (int)($payload['adults'] ?? 1));
        $children = max(0, (int)($payload['children'] ?? 0));

        $notes = trim((string)($payload['special_requests'] ?? ''));

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO room_bookings (
                    booking_number,
                    room_id,
                    customer_id,
                    legacy_booking_id,
                    guest_name,
                    guest_phone,
                    guest_email,
                    guest_id_number,
                    check_in_date,
                    check_out_date,
                    actual_check_in,
                    actual_check_out,
                    adults,
                    children,
                    total_nights,
                    rate_per_night,
                    total_amount,
                    amount_paid,
                    payment_status,
                    status,
                    notes,
                    created_by,
                    created_at,
                    updated_at
                ) VALUES (
                    :booking_number,
                    :room_id,
                    :customer_id,
                    NULL,
                    :guest_name,
                    :guest_phone,
                    :guest_email,
                    :guest_id_number,
                    :check_in_date,
                    :check_out_date,
                    NULL,
                    NULL,
                    :adults,
                    :children,
                    :total_nights,
                    :rate_per_night,
                    :total_amount,
                    :amount_paid,
                    :payment_status,
                    'confirmed',
                    :notes,
                    :created_by,
                    NOW(),
                    NOW()
                )"
            );

            $stmt->execute([
                ':booking_number' => $bookingNumber,
                ':room_id' => $roomId,
                ':customer_id' => $customerId,
                ':guest_name' => $guestName,
                ':guest_phone' => $guestPhone,
                ':guest_email' => trim((string)($payload['guest_email'] ?? '')) ?: null,
                ':guest_id_number' => trim((string)($payload['guest_id_number'] ?? '')) ?: null,
                ':check_in_date' => $checkInDate,
                ':check_out_date' => $checkOutDate,
                ':adults' => $adults,
                ':children' => $children,
                ':total_nights' => $nights,
                ':rate_per_night' => $rate,
                ':total_amount' => $totalAmount,
                ':amount_paid' => $deposit,
                ':payment_status' => $paymentStatus,
                ':notes' => $notes ?: null,
                ':created_by' => $userId
            ]);

            $bookingId = (int)$this->db->lastInsertId();

            // Seed folio with room charge
            $folioStmt = $this->db->prepare(
                "INSERT INTO room_folios (
                    booking_id,
                    item_type,
                    description,
                    amount,
                    quantity,
                    date_charged,
                    created_by
                ) VALUES (
                    :booking_id,
                    :item_type,
                    :description,
                    :amount,
                    :quantity,
                    :date_charged,
                    :created_by
                )"
            );

            $folioStmt->execute([
                ':booking_id' => $bookingId,
                ':item_type' => 'room_charge',
                ':description' => 'Room charge',
                ':amount' => $totalAmount,
                ':quantity' => $nights,
                ':date_charged' => $checkInDate,
                ':created_by' => $userId
            ]);

            if ($deposit > 0) {
                $this->recordBookingPayment($bookingId, [
                    'amount' => $deposit,
                    'method' => $depositMethod,
                    'reference' => $payload['deposit_reference'] ?? null,
                    'customer_phone' => $payload['deposit_customer_phone'] ?? null,
                    'notes' => $payload['deposit_notes'] ?? null,
                    'date' => $checkInDate,
                ], $userId);

                $folioStmt->execute([
                    ':booking_id' => $bookingId,
                    ':item_type' => 'payment',
                    ':description' => sprintf('Deposit payment (%s)', strtoupper(str_replace('_', ' ', $depositMethod))),
                    ':amount' => -1 * $deposit,
                    ':quantity' => 1,
                    ':date_charged' => $checkInDate,
                    ':created_by' => $userId
                ]);
            }

            // Mark room as occupied (table ENUM: available, occupied, maintenance, out_of_service)
            $roomStmt = $this->db->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
            $roomStmt->execute([$roomId]);

            $this->db->commit();

            return [
                'booking_id' => $bookingId,
                'booking_number' => $bookingNumber,
                'total_amount' => $totalAmount,
                'total_nights' => $nights,
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Mark a booking as checked in, with legacy fallback.
     */
    public function checkIn(int $bookingId, int $userId): void
    {
        try {
            $this->ensureSchema();
        } catch (Exception $e) {
            // Ignore schema provisioning errors to allow legacy fallback
        }

        $hasModern = $this->tableExists(self::NEW_BOOKINGS_TABLE);

        $this->db->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');

            $modernBooking = null;
            if ($hasModern) {
                $stmt = $this->db->prepare('SELECT * FROM room_bookings WHERE id = ? FOR UPDATE');
                $stmt->execute([$bookingId]);
                $modernBooking = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            if ($modernBooking) {
                $update = $this->db->prepare(
                    "UPDATE room_bookings
                     SET status = 'checked_in',
                         actual_check_in = :actual_check_in,
                         updated_at = :updated_at
                     WHERE id = :id"
                );
                $update->execute([
                    ':actual_check_in' => $now,
                    ':updated_at' => $now,
                    ':id' => $bookingId,
                ]);

                $roomUpdate = $this->db->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
                $roomUpdate->execute([(int)$modernBooking['room_id']]);
            } else {
                $legacyStmt = $this->db->prepare('SELECT * FROM bookings WHERE id = ? FOR UPDATE');
                $legacyStmt->execute([$bookingId]);
                $legacyBooking = $legacyStmt->fetch(PDO::FETCH_ASSOC);

                if (!$legacyBooking) {
                    throw new Exception('Booking not found');
                }

                $updateLegacy = $this->db->prepare(
                    "UPDATE bookings
                     SET booking_status = 'checked-in',
                         check_in_time = :now
                     WHERE id = :id"
                );
                $updateLegacy->execute([
                    ':now' => $now,
                    ':id' => $bookingId,
                ]);

                $roomUpdate = $this->db->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
                $roomUpdate->execute([(int)$legacyBooking['room_id']]);
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Retrieve booking invoice data with folio ledger if available.
     */
    public function checkOut(int $bookingId, int $userId): void
    {
        try {
            $this->ensureSchema();
        } catch (Exception $e) {
            // Allow legacy flow even if schema provisioning fails
        }

        $hasModern = $this->tableExists(self::NEW_BOOKINGS_TABLE);
        $hasFolio = $this->tableExists(self::FOLIOS_TABLE);

        $this->db->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');

            $modernBooking = null;
            if ($hasModern) {
                $stmt = $this->db->prepare('SELECT * FROM room_bookings WHERE id = ? FOR UPDATE');
                $stmt->execute([$bookingId]);
                $modernBooking = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            if ($modernBooking) {
                $roomId = (int)$modernBooking['room_id'];

                $amountPaid = (float)($modernBooking['amount_paid'] ?? 0);
                if ($hasFolio) {
                    $balanceStmt = $this->db->prepare('SELECT COALESCE(SUM(amount),0) FROM room_folios WHERE booking_id = ?');
                    $balanceStmt->execute([$bookingId]);
                    $balance = (float)$balanceStmt->fetchColumn();

                    if ($balance > 0.0001) {
                        $paymentStmt = $this->db->prepare(
                            "INSERT INTO room_folios (booking_id, item_type, description, amount, quantity, date_charged, created_by)
                             VALUES (:booking_id, 'payment', :description, :amount, 1, :date_charged, :created_by)"
                        );
                        $paymentStmt->execute([
                            ':booking_id' => $bookingId,
                            ':description' => 'Check-out settlement',
                            ':amount' => -1 * $balance,
                            ':date_charged' => date('Y-m-d'),
                            ':created_by' => $userId ?: null,
                        ]);
                    }

                    $amountPaidStmt = $this->db->prepare(
                        'SELECT COALESCE(SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END),0) FROM room_folios WHERE booking_id = ?'
                    );
                    $amountPaidStmt->execute([$bookingId]);
                    $amountPaid = (float)$amountPaidStmt->fetchColumn();
                }

                $update = $this->db->prepare(
                    "UPDATE room_bookings
                     SET status = 'checked_out',
                         actual_check_out = :actual_check_out,
                         payment_status = 'paid',
                         amount_paid = :amount_paid,
                         updated_at = :updated_at
                     WHERE id = :id"
                );
                $update->execute([
                    ':actual_check_out' => $now,
                    ':amount_paid' => $amountPaid,
                    ':updated_at' => $now,
                    ':id' => $bookingId,
                ]);

                $roomUpdate = $this->db->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
                $roomUpdate->execute([$roomId]);
            } else {
                $legacyStmt = $this->db->prepare('SELECT * FROM bookings WHERE id = ? FOR UPDATE');
                $legacyStmt->execute([$bookingId]);
                $legacyBooking = $legacyStmt->fetch(PDO::FETCH_ASSOC);

                if (!$legacyBooking) {
                    throw new Exception('Booking not found');
                }

                $updateLegacy = $this->db->prepare(
                    "UPDATE bookings
                     SET booking_status = 'checked-out',
                         check_out_time = :now,
                         payment_status = 'paid'
                     WHERE id = :id"
                );
                $updateLegacy->execute([
                    ':now' => $now,
                    ':id' => $bookingId,
                ]);

                $roomUpdate = $this->db->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
                $roomUpdate->execute([(int)$legacyBooking['room_id']]);
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getInvoiceData(int $bookingId): array
    {
        try {
            $this->ensureSchema();
        } catch (Exception $e) {
            // Ignore schema errors; we'll fall back to legacy booking retrieval if needed.
        }

        $hasModern = $this->tableExists(self::NEW_BOOKINGS_TABLE);
        $hasFolio = $this->tableExists(self::FOLIOS_TABLE);

        if ($hasModern) {
            $stmt = $this->db->prepare(
                "SELECT b.*, r.room_number, rt.name AS room_type_name, u.full_name AS booked_by
                 FROM room_bookings b
                 JOIN rooms r ON b.room_id = r.id
                 JOIN room_types rt ON r.room_type_id = rt.id
                 LEFT JOIN users u ON b.created_by = u.id
                 WHERE b.id = ?
                 LIMIT 1"
            );
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($booking) {
                $folioEntries = [];
                if ($hasFolio) {
                    $folioStmt = $this->db->prepare(
                        "SELECT id, item_type, description, amount, quantity, date_charged, created_at
                         FROM room_folios
                         WHERE booking_id = ?
                         ORDER BY date_charged ASC, id ASC"
                    );
                    $folioStmt->execute([$bookingId]);
                    $folioEntries = $folioStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }

                $totalCharges = 0.0;
                $totalPayments = 0.0;
                foreach ($folioEntries as $entry) {
                    $amount = (float)($entry['amount'] ?? 0);
                    if ($amount >= 0) {
                        $totalCharges += $amount;
                    } else {
                        $totalPayments += abs($amount);
                    }
                }

                if (empty($folioEntries)) {
                    $totalCharges = (float)($booking['total_amount'] ?? 0);
                    $totalPayments = (float)($booking['amount_paid'] ?? 0);
                }

                $balanceDue = max(0, round($totalCharges - $totalPayments, 2));

                return [
                    'mode' => 'modern',
                    'booking' => $booking,
                    'folio' => $folioEntries,
                    'totals' => [
                        'total_charges' => round($totalCharges, 2),
                        'total_payments' => round($totalPayments, 2),
                        'balance_due' => $balanceDue,
                    ],
                ];
            }
        }

        $stmt = $this->db->prepare(
            "SELECT b.*, r.room_number, rt.name AS room_type_name, u.full_name AS booked_by
             FROM bookings b
             JOIN rooms r ON b.room_id = r.id
             JOIN room_types rt ON r.room_type_id = rt.id
             LEFT JOIN users u ON b.user_id = u.id
             WHERE b.id = ?
             LIMIT 1"
        );
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $totalAmount = $booking ? (float)($booking['total_amount'] ?? 0) : 0.0;
        $amountPaid = $booking ? (float)($booking['amount_paid'] ?? 0) : 0.0;

        return [
            'mode' => 'legacy',
            'booking' => $booking,
            'folio' => [],
            'totals' => [
                'total_charges' => round($totalAmount, 2),
                'total_payments' => round($amountPaid, 2),
                'balance_due' => max(0, round($totalAmount - $amountPaid, 2)),
            ],
        ];
    }

    /**
     * Record a folio entry against a booking (room service, adjustments, etc.).
     */
    public function addFolioEntry(int $bookingId, array $entry, ?int $userId = null): int
    {
        $this->ensureSchema();

        if (!$this->tableExists(self::FOLIOS_TABLE)) {
            throw new Exception('Room folio table is not provisioned. Please run the room management upgrade.');
        }

        $booking = $this->getRoomBooking($bookingId);
        if (!$booking) {
            throw new Exception('Booking not found or not migrated to folio system.');
        }

        if (!in_array($booking['status'], ['checked_in', 'confirmed'], true)) {
            throw new Exception('Charges can only be posted to confirmed or checked-in bookings.');
        }

        $amount = isset($entry['amount']) ? (float)$entry['amount'] : null;
        if ($amount === null) {
            throw new Exception('Folio entry amount is required.');
        }

        $itemType = $entry['item_type'] ?? 'service';
        if (!in_array($itemType, ['room_charge', 'service', 'tax', 'deposit', 'payment', 'adjustment'], true)) {
            throw new Exception('Unsupported folio item type: ' . $itemType);
        }

        $description = trim((string)($entry['description'] ?? 'Folio entry'));
        if ($description === '') {
            $description = 'Folio entry';
        }

        $quantity = isset($entry['quantity']) ? (float)$entry['quantity'] : 1;
        $dateCharged = $entry['date_charged'] ?? date('Y-m-d');

        $referenceId = $entry['reference_id'] ?? null;
        $referenceSource = $entry['reference_source'] ?? null;

        $stmt = $this->db->prepare(
            "INSERT INTO room_folios (
                booking_id,
                item_type,
                description,
                amount,
                quantity,
                date_charged,
                created_by,
                reference_id,
                reference_source
            ) VALUES (
                :booking_id,
                :item_type,
                :description,
                :amount,
                :quantity,
                :date_charged,
                :created_by,
                :reference_id,
                :reference_source
            )"
        );

        $stmt->execute([
            ':booking_id' => $bookingId,
            ':item_type' => $itemType,
            ':description' => $description,
            ':amount' => $amount,
            ':quantity' => $quantity,
            ':date_charged' => $dateCharged,
            ':created_by' => $userId,
            ':reference_id' => $referenceId,
            ':reference_source' => $referenceSource,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Fetch a modern room booking row.
     */
    public function getRoomBooking(int $bookingId): ?array
    {
        if (!$this->tableExists(self::NEW_BOOKINGS_TABLE)) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT * FROM room_bookings WHERE id = ? LIMIT 1');
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return $booking ?: null;
    }

    private function generateBookingNumber(): string
    {
        return 'BK-' . date('Ymd') . '-' . strtoupper(substr(uniqid('', true), -6));
    }

    private function calculateNights(string $checkIn, string $checkOut): int
    {
        $dateIn = new DateTime($checkIn);
        $dateOut = new DateTime($checkOut);
        return $dateIn->diff($dateOut)->days;
    }

    private function fetchRoomRate(int $roomId): float
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(rt.base_rate, 0) AS rate
             FROM rooms r
             JOIN room_types rt ON r.room_type_id = rt.id
             WHERE r.id = ?"
        );
        $stmt->execute([$roomId]);
        $rate = $stmt->fetchColumn();
        return $rate !== false ? (float)$rate : 0.0;
    }

    private function tableExists(string $table): bool
    {
        $quoted = $this->db->quote($table);
        $stmt = $this->db->query("SHOW TABLES LIKE {$quoted}");
        return $stmt && $stmt->fetchColumn() !== false;
    }

    private function getTableColumns(string $table): array
    {
        try {
            $stmt = $this->db->prepare('SHOW COLUMNS FROM ' . $table);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            return [];
        }
    }

    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        $columns = $this->getTableColumns($table);
        if (!in_array($column, $columns, true)) {
            try {
                $this->db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            } catch (Exception $e) {
                // Column might already exist or other issue - log and continue
                error_log("Failed to add column {$column} to {$table}: " . $e->getMessage());
            }
        }
    }

    private function createRoomBookingsTable(bool $hasCustomers, bool $hasUsers): void
    {
        $customerFk = $hasCustomers
            ? ', CONSTRAINT fk_room_bookings_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL'
            : '';
        $userFk = $hasUsers
            ? ', CONSTRAINT fk_room_bookings_user FOREIGN KEY (created_by) REFERENCES users(id)'
            : '';

        $sql = "CREATE TABLE IF NOT EXISTS room_bookings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            booking_number VARCHAR(50) UNIQUE NOT NULL,
            room_id INT NOT NULL,
            customer_id INT NULL,
            legacy_booking_id INT NULL,
            guest_name VARCHAR(100) NOT NULL,
            guest_phone VARCHAR(20) NOT NULL,
            guest_email VARCHAR(100) NULL,
            guest_id_number VARCHAR(50) NULL,
            check_in_date DATE NOT NULL,
            check_out_date DATE NOT NULL,
            actual_check_in DATETIME NULL,
            actual_check_out DATETIME NULL,
            adults INT DEFAULT 1,
            children INT DEFAULT 0,
            total_nights INT NOT NULL,
            rate_per_night DECIMAL(10,2) NOT NULL,
            total_amount DECIMAL(15,2) NOT NULL,
            amount_paid DECIMAL(15,2) DEFAULT 0,
            payment_status ENUM('pending','partial','paid','refunded') DEFAULT 'pending',
            status ENUM('pending','confirmed','checked_in','checked_out','cancelled','no_show') DEFAULT 'confirmed',
            notes TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_room_bookings_room FOREIGN KEY (room_id) REFERENCES rooms(id)
            {$customerFk}
            {$userFk}
        ) ENGINE=InnoDB";

        try {
            $this->db->exec($sql);
        } catch (Exception $e) {
            // Fallback without foreign keys if engines differ
            $fallbackSql = "CREATE TABLE IF NOT EXISTS room_bookings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                booking_number VARCHAR(50) UNIQUE NOT NULL,
                room_id INT NOT NULL,
                customer_id INT NULL,
                legacy_booking_id INT NULL,
                guest_name VARCHAR(100) NOT NULL,
                guest_phone VARCHAR(20) NOT NULL,
                guest_email VARCHAR(100) NULL,
                guest_id_number VARCHAR(50) NULL,
                check_in_date DATE NOT NULL,
                check_out_date DATE NOT NULL,
                actual_check_in DATETIME NULL,
                actual_check_out DATETIME NULL,
                adults INT DEFAULT 1,
                children INT DEFAULT 0,
                total_nights INT NOT NULL,
                rate_per_night DECIMAL(10,2) NOT NULL,
                total_amount DECIMAL(15,2) NOT NULL,
                amount_paid DECIMAL(15,2) DEFAULT 0,
                payment_status ENUM('pending','partial','paid','refunded') DEFAULT 'pending',
                status ENUM('pending','confirmed','checked_in','checked_out','cancelled','no_show') DEFAULT 'confirmed',
                notes TEXT NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB";
            $this->db->exec($fallbackSql);
        }
    }

    private function syncRoomBookingsColumns(bool $hasCustomers): void
    {
        // Core guest fields
        $this->addColumnIfMissing(self::NEW_BOOKINGS_TABLE, 'guest_name', "VARCHAR(100) NOT NULL DEFAULT ''");
        $this->addColumnIfMissing(self::NEW_BOOKINGS_TABLE, 'guest_phone', "VARCHAR(20) NOT NULL DEFAULT ''");
        $this->addColumnIfMissing(self::NEW_BOOKINGS_TABLE, 'guest_email', 'VARCHAR(100) NULL');
        $this->addColumnIfMissing(self::NEW_BOOKINGS_TABLE, 'guest_id_number', 'VARCHAR(50) NULL');
        
        // Booking details
        $this->addColumnIfMissing(self::NEW_BOOKINGS_TABLE, 'legacy_booking_id', 'INT NULL');
        $this->addColumnIfMissing(self::NEW_BOOKINGS_TABLE, 'total_nights', 'INT NOT NULL DEFAULT 1');
        $this->addColumnIfMissing(self::NEW_BOOKINGS_TABLE, 'rate_per_night', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
        $this->addColumnIfMissing(self::NEW_BOOKINGS_TABLE, 'total_amount', 'DECIMAL(15,2) NOT NULL DEFAULT 0');
        $this->addColumnIfMissing(self::NEW_BOOKINGS_TABLE, 'amount_paid', 'DECIMAL(15,2) NOT NULL DEFAULT 0');
        $this->addColumnIfMissing(self::NEW_BOOKINGS_TABLE, 'adults', 'INT DEFAULT 1');
        $this->addColumnIfMissing(self::NEW_BOOKINGS_TABLE, 'children', 'INT DEFAULT 0');
        $this->addColumnIfMissing(self::NEW_BOOKINGS_TABLE, 'notes', 'TEXT NULL');
        $this->addColumnIfMissing(self::NEW_BOOKINGS_TABLE, 'actual_check_in', 'DATETIME NULL');
        $this->addColumnIfMissing(self::NEW_BOOKINGS_TABLE, 'actual_check_out', 'DATETIME NULL');
        $this->addColumnIfMissing(self::NEW_BOOKINGS_TABLE, 'payment_status', "VARCHAR(20) NOT NULL DEFAULT 'pending'");
        $this->addColumnIfMissing(self::NEW_BOOKINGS_TABLE, 'status', "VARCHAR(20) NOT NULL DEFAULT 'confirmed'");
        $this->addColumnIfMissing(self::NEW_BOOKINGS_TABLE, 'booking_number', "VARCHAR(50) NOT NULL DEFAULT ''");

        if ($hasCustomers) {
            try {
                $this->db->exec(
                    "ALTER TABLE room_bookings
                     ADD CONSTRAINT fk_room_bookings_customer
                     FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL"
                );
            } catch (Exception $e) {
                // Ignore constraint creation issues (engine mismatch, already exists, etc.)
            }
        }
    }

    private function createRoomFoliosTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS room_folios (
            id INT PRIMARY KEY AUTO_INCREMENT,
            booking_id INT NOT NULL,
            item_type ENUM('room_charge','service','tax','deposit','payment','adjustment') NOT NULL,
            description VARCHAR(200) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            quantity DECIMAL(10,2) DEFAULT 1,
            reference_id INT NULL,
            reference_source VARCHAR(60) NULL,
            date_charged DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by INT NULL,
            CONSTRAINT fk_room_folios_booking FOREIGN KEY (booking_id) REFERENCES room_bookings(id) ON DELETE CASCADE
        ) ENGINE=InnoDB";

        try {
            $this->db->exec($sql);
        } catch (Exception $e) {
            $fallbackSql = "CREATE TABLE IF NOT EXISTS room_folios (
                id INT PRIMARY KEY AUTO_INCREMENT,
                booking_id INT NOT NULL,
                item_type ENUM('room_charge','service','tax','deposit','payment','adjustment') NOT NULL,
                description VARCHAR(200) NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                quantity DECIMAL(10,2) DEFAULT 1,
                reference_id INT NULL,
                reference_source VARCHAR(60) NULL,
                date_charged DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_by INT NULL
            ) ENGINE=InnoDB";
            $this->db->exec($fallbackSql);
        }
    }

    private function syncRoomFoliosColumns(): void
    {
        // Essential columns that MUST exist for the booking system to work
        $this->addColumnIfMissing(self::FOLIOS_TABLE, 'booking_id', 'INT NOT NULL');
        $this->addColumnIfMissing(self::FOLIOS_TABLE, 'item_type', "VARCHAR(20) NOT NULL DEFAULT 'service'");
        $this->addColumnIfMissing(self::FOLIOS_TABLE, 'description', "VARCHAR(200) NOT NULL DEFAULT ''");
        $this->addColumnIfMissing(self::FOLIOS_TABLE, 'amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->addColumnIfMissing(self::FOLIOS_TABLE, 'quantity', 'DECIMAL(10,2) DEFAULT 1');
        $this->addColumnIfMissing(self::FOLIOS_TABLE, 'date_charged', 'DATE NULL');
        $this->addColumnIfMissing(self::FOLIOS_TABLE, 'reference_id', 'INT NULL');
        $this->addColumnIfMissing(self::FOLIOS_TABLE, 'reference_source', 'VARCHAR(60) NULL');
        $this->addColumnIfMissing(self::FOLIOS_TABLE, 'created_by', 'INT NULL');
        $this->addColumnIfMissing(self::FOLIOS_TABLE, 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
        
        // Fix columns that might exist without defaults from older schema
        $columnsToFix = [
            'folio_number' => 'VARCHAR(50) NULL DEFAULT NULL',
            'total_amount' => 'DECIMAL(12,2) NULL DEFAULT NULL',
            'tax_amount' => 'DECIMAL(12,2) NULL DEFAULT NULL',
            'discount_amount' => 'DECIMAL(12,2) NULL DEFAULT NULL',
            'net_amount' => 'DECIMAL(12,2) NULL DEFAULT NULL',
            'status' => "VARCHAR(20) NULL DEFAULT 'active'",
            'notes' => 'TEXT NULL',
            'voided_at' => 'DATETIME NULL',
            'voided_by' => 'INT NULL',
            'void_reason' => 'TEXT NULL'
        ];
        
        foreach ($columnsToFix as $column => $definition) {
            try {
                $this->db->exec("ALTER TABLE " . self::FOLIOS_TABLE . " MODIFY COLUMN {$column} {$definition}");
            } catch (\Exception $e) {
                // Column might not exist, that's fine
            }
        }
    }

    private function ensureMigrationTable(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS room_booking_migrations (
                legacy_booking_id INT PRIMARY KEY,
                room_booking_id INT NOT NULL,
                migrated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_room_booking_migrations_room FOREIGN KEY (room_booking_id) REFERENCES room_bookings(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );
    }

    public function schemaReady(): bool
    {
        return $this->tableExists(self::NEW_BOOKINGS_TABLE) && $this->tableExists(self::FOLIOS_TABLE);
    }

    private function createBasicRoomSchema(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS room_bookings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                booking_number VARCHAR(50) UNIQUE NOT NULL,
                room_id INT NOT NULL,
                customer_id INT NULL,
                legacy_booking_id INT NULL,
                guest_name VARCHAR(100) NOT NULL,
                guest_phone VARCHAR(20) NOT NULL,
                guest_email VARCHAR(100) NULL,
                guest_id_number VARCHAR(50) NULL,
                check_in_date DATE NOT NULL,
                check_out_date DATE NOT NULL,
                actual_check_in DATETIME NULL,
                actual_check_out DATETIME NULL,
                adults INT DEFAULT 1,
                children INT DEFAULT 0,
                total_nights INT NOT NULL DEFAULT 1,
                rate_per_night DECIMAL(10,2) NOT NULL DEFAULT 0,
                total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0,
                payment_status ENUM('pending','partial','paid','refunded') DEFAULT 'pending',
                status ENUM('pending','confirmed','checked_in','checked_out','cancelled','no_show') DEFAULT 'confirmed',
                notes TEXT NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB"
        );

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS room_folios (
                id INT PRIMARY KEY AUTO_INCREMENT,
                booking_id INT NOT NULL,
                item_type ENUM('room_charge','service','tax','deposit','payment','adjustment') NOT NULL,
                description VARCHAR(200) NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                quantity DECIMAL(10,2) DEFAULT 1,
                reference_id INT NULL,
                reference_source VARCHAR(60) NULL,
                date_charged DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_by INT NULL
            ) ENGINE=InnoDB"
        );
    }

    private function alreadyMigrated(int $legacyBookingId): bool
    {
        if (!$this->tableExists('room_booking_migrations')) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT room_booking_id FROM room_booking_migrations WHERE legacy_booking_id = ? LIMIT 1'
        );

        try {
            $stmt->execute([$legacyBookingId]);
            return (bool)$stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    private function flagMigrated(int $legacyBookingId, int $newBookingId): void
    {
        // Persist mapping using a lightweight metadata table
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS room_booking_migrations (
                legacy_booking_id INT PRIMARY KEY,
                room_booking_id INT NOT NULL,
                migrated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_room_booking_migrations_room FOREIGN KEY (room_booking_id) REFERENCES room_bookings(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );

        $stmt = $this->db->prepare(
            'INSERT INTO room_booking_migrations (legacy_booking_id, room_booking_id) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE room_booking_id = VALUES(room_booking_id)'
        );
        $stmt->execute([$legacyBookingId, $newBookingId]);
    }

    /**
     * Send WhatsApp booking confirmation
     */
    public function sendWhatsAppConfirmation(int $bookingId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT b.*, r.room_number, rt.name as room_type_name
             FROM room_bookings b
             JOIN rooms r ON b.room_id = r.id
             JOIN room_types rt ON r.room_type_id = rt.id
             WHERE b.id = ?"
        );
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking || empty($booking['guest_phone'])) {
            return false;
        }

        try {
            $hospitalityService = new HospitalityWhatsAppService($this->db);
            $message = $this->buildConfirmationMessage($booking);
            $result = $hospitalityService->sendMessage($booking['guest_phone'], $message);
            return $result['success'] ?? false;
        } catch (Exception $e) {
            error_log("WhatsApp confirmation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send WhatsApp check-in reminder (day before)
     */
    public function sendCheckInReminder(int $bookingId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT b.*, r.room_number, rt.name as room_type_name
             FROM room_bookings b
             JOIN rooms r ON b.room_id = r.id
             JOIN room_types rt ON r.room_type_id = rt.id
             WHERE b.id = ? AND b.status IN ('pending', 'confirmed')"
        );
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking || empty($booking['guest_phone'])) {
            return false;
        }

        try {
            $hospitalityService = new HospitalityWhatsAppService($this->db);
            $businessName = function_exists('settings') ? (settings('business_name') ?? 'Our Hotel') : 'Our Hotel';
            
            $message = "🏨 *Check-in Reminder*\n\n";
            $message .= "Hi {$booking['guest_name']}!\n\n";
            $message .= "This is a friendly reminder about your upcoming stay at {$businessName}.\n\n";
            $message .= "📋 *Booking:* {$booking['booking_number']}\n";
            $message .= "📅 *Check-in:* " . date('l, M j, Y', strtotime($booking['check_in_date'])) . "\n";
            $message .= "⏰ *Time:* From 2:00 PM\n\n";
            $message .= "We look forward to welcoming you!\n\n";
            $message .= "Type *CONTACT* if you need assistance.";

            $result = $hospitalityService->sendMessage($booking['guest_phone'], $message);
            return $result['success'] ?? false;
        } catch (Exception $e) {
            error_log("WhatsApp reminder error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send WhatsApp checkout reminder
     */
    public function sendCheckOutReminder(int $bookingId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT b.*, r.room_number, rt.name as room_type_name
             FROM room_bookings b
             JOIN rooms r ON b.room_id = r.id
             JOIN room_types rt ON r.room_type_id = rt.id
             WHERE b.id = ? AND b.status = 'checked_in'"
        );
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking || empty($booking['guest_phone'])) {
            return false;
        }

        try {
            $hospitalityService = new HospitalityWhatsAppService($this->db);
            
            // Get folio balance
            $balanceStmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) as balance FROM room_folios WHERE booking_id = ?");
            $balanceStmt->execute([$bookingId]);
            $balance = (float)($balanceStmt->fetch(PDO::FETCH_ASSOC)['balance'] ?? 0);

            $message = "🏨 *Check-out Reminder*\n\n";
            $message .= "Good morning {$booking['guest_name']}!\n\n";
            $message .= "This is a reminder that check-out is today.\n\n";
            $message .= "🚪 *Room:* {$booking['room_number']}\n";
            $message .= "⏰ *Check-out time:* Before 11:00 AM\n";
            $message .= "💰 *Balance:* " . $this->formatCurrency($balance) . "\n\n";
            $message .= "Need late check-out? Type *CONTACT* to request.\n\n";
            $message .= "Thank you for staying with us! 🙏";

            $result = $hospitalityService->sendMessage($booking['guest_phone'], $message);
            return $result['success'] ?? false;
        } catch (Exception $e) {
            error_log("WhatsApp checkout reminder error: " . $e->getMessage());
            return false;
        }
    }

    private function buildConfirmationMessage(array $booking): string
    {
        $businessName = function_exists('settings') ? (settings('business_name') ?? 'Our Hotel') : 'Our Hotel';
        
        $message = "🎉 *Booking Confirmed!*\n\n";
        $message .= "Thank you for choosing {$businessName}!\n\n";
        $message .= "📋 *Booking Details*\n";
        $message .= "━━━━━━━━━━━━━━━━━━\n";
        $message .= "📋 *Booking #:* {$booking['booking_number']}\n";
        $message .= "👤 *Guest:* {$booking['guest_name']}\n";
        $message .= "🛏️ *Room:* {$booking['room_type_name']}\n";
        $message .= "📅 *Check-in:* " . date('D, M j, Y', strtotime($booking['check_in_date'])) . "\n";
        $message .= "📅 *Check-out:* " . date('D, M j, Y', strtotime($booking['check_out_date'])) . "\n";
        $message .= "🌙 *Nights:* {$booking['total_nights']}\n";
        $message .= "💰 *Total:* " . $this->formatCurrency($booking['total_amount']) . "\n";
        $message .= "━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "⏰ *Check-in:* From 2:00 PM\n";
        $message .= "⏰ *Check-out:* Before 11:00 AM\n\n";
        $message .= "📱 Save this message for reference.\n\n";
        $message .= "Type *MENU* for services during your stay.";

        return $message;
    }

    private function formatCurrency(float $amount): string
    {
        $symbol = function_exists('settings') ? (settings('currency_symbol') ?? 'KES') : 'KES';
        return $symbol . ' ' . number_format($amount, 2);
    }
}
