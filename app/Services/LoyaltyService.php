<?php

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

class LoyaltyService
{
    private PDO $db;
    private bool $schemaEnsured = false;
    private bool $saleSummaryTableEnsured = false;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        if ($this->schemaEnsured) {
            return;
        }

        $this->db->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS loyalty_programs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                points_per_dollar DECIMAL(10,4) DEFAULT 1.0,
                redemption_rate DECIMAL(10,4) DEFAULT 0.01,
                min_points_redemption INT DEFAULT 100,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
        SQL);

        $this->db->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS customer_loyalty_points (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                customer_id INT UNSIGNED NOT NULL,
                program_id INT UNSIGNED NOT NULL,
                points_earned INT DEFAULT 0,
                points_redeemed INT DEFAULT 0,
                last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_clp_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
                CONSTRAINT fk_clp_program FOREIGN KEY (program_id) REFERENCES loyalty_programs(id),
                UNIQUE KEY unique_customer_program (customer_id, program_id)
            ) ENGINE=InnoDB
        SQL);

        $this->db->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS loyalty_transactions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                customer_id INT UNSIGNED NOT NULL,
                program_id INT UNSIGNED NOT NULL,
                transaction_type ENUM('earn','redeem','adjust','expire') NOT NULL,
                points INT NOT NULL,
                sale_id INT UNSIGNED NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_lt_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
                CONSTRAINT fk_lt_program FOREIGN KEY (program_id) REFERENCES loyalty_programs(id),
                CONSTRAINT fk_lt_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL
            ) ENGINE=InnoDB
        SQL);

        $this->schemaEnsured = true;
    }

    public function getActiveProgram(): ?array
    {
        $this->ensureSchema();
        $stmt = $this->db->query("SELECT * FROM loyalty_programs WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
        $program = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!$program) {
            return null;
        }
        return $program;
    }

    public function ensureDefaultProgram(): array
    {
        $program = $this->getActiveProgram();
        if ($program) {
            return $program;
        }

        $sql = "INSERT INTO loyalty_programs (name, description, points_per_dollar, redemption_rate, min_points_redemption, is_active) VALUES (:name, :description, :ppd, :rate, :min_points, 1)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => 'Default Loyalty Program',
            ':description' => 'Automatically generated default program',
            ':ppd' => 1.0,
            ':rate' => 0.01,
            ':min_points' => 100,
        ]);

        $programId = (int) $this->db->lastInsertId();
        return $this->getProgramById($programId);
    }

    public function getPrograms(): array
    {
        $this->ensureSchema();
        $stmt = $this->db->query("SELECT * FROM loyalty_programs ORDER BY is_active DESC, name ASC");
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    public function createProgram(array $data): array
    {
        $this->ensureSchema();
        $sql = "INSERT INTO loyalty_programs (name, description, points_per_dollar, redemption_rate, min_points_redemption, is_active) VALUES (:name, :description, :ppd, :rate, :min_points, :is_active)";
        $stmt = $this->db->prepare($sql);
        $isActive = !empty($data['is_active']) ? 1 : 0;
        $stmt->execute([
            ':name' => trim($data['name'] ?? 'New Program'),
            ':description' => trim($data['description'] ?? ''),
            ':ppd' => isset($data['points_per_dollar']) ? (float) $data['points_per_dollar'] : 1.0,
            ':rate' => isset($data['redemption_rate']) ? (float) $data['redemption_rate'] : 0.01,
            ':min_points' => isset($data['min_points_redemption']) ? (int) $data['min_points_redemption'] : 100,
            ':is_active' => $isActive,
        ]);

        $programId = (int) $this->db->lastInsertId();

        if ($isActive) {
            $this->setActiveProgram($programId);
        }

        return $this->getProgramById($programId);
    }

    public function setActiveProgram(int $programId): array
    {
        $this->ensureSchema();
        $program = $this->getProgramById($programId);
        if (!$program) {
            throw new RuntimeException('Loyalty program not found.');
        }

        $this->db->beginTransaction();
        try {
            $this->db->exec("UPDATE loyalty_programs SET is_active = 0 WHERE id <> " . (int) $programId);
            $this->db->prepare("UPDATE loyalty_programs SET is_active = 1 WHERE id = :id")
                ->execute([':id' => $programId]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $this->getProgramById($programId);
    }

    public function deleteProgram(int $programId): bool
    {
        $this->ensureSchema();
        $program = $this->getProgramById($programId);
        if (!$program) {
            throw new RuntimeException('Loyalty program not found.');
        }

        if ((int) $program['is_active'] === 1) {
            throw new RuntimeException('Cannot delete an active loyalty program.');
        }

        $stmt = $this->db->prepare("DELETE FROM loyalty_programs WHERE id = :id");
        return $stmt->execute([':id' => $programId]);
    }

    public function getProgramById(int $programId): ?array
    {
        $this->ensureSchema();
        $stmt = $this->db->prepare("SELECT * FROM loyalty_programs WHERE id = ? LIMIT 1");
        $stmt->execute([$programId]);
        $program = $stmt->fetch(PDO::FETCH_ASSOC);
        return $program ?: null;
    }

    public function saveProgram(int $programId, array $data): array
    {
        $this->ensureSchema();
        $program = $this->getProgramById($programId);
        if (!$program) {
            throw new RuntimeException('Loyalty program not found.');
        }

        $sql = "UPDATE loyalty_programs SET name = :name, description = :description, points_per_dollar = :ppd, redemption_rate = :rate, min_points_redemption = :min_points, is_active = :is_active WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'] ?? $program['name'],
            ':description' => $data['description'] ?? $program['description'],
            ':ppd' => isset($data['points_per_dollar']) ? (float) $data['points_per_dollar'] : (float) $program['points_per_dollar'],
            ':rate' => isset($data['redemption_rate']) ? (float) $data['redemption_rate'] : (float) $program['redemption_rate'],
            ':min_points' => isset($data['min_points_redemption']) ? (int) $data['min_points_redemption'] : (int) $program['min_points_redemption'],
            ':is_active' => isset($data['is_active']) ? (int) $data['is_active'] : (int) $program['is_active'],
            ':id' => $programId,
        ]);

        if (!empty($data['is_active'])) {
            $this->db->prepare("UPDATE loyalty_programs SET is_active = 0 WHERE id <> :id")->execute([':id' => $programId]);
        }

        return $this->getProgramById($programId);
    }

    public function getCustomerById(int $customerId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE id = ? LIMIT 1");
        $stmt->execute([$customerId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        return $customer ?: null;
    }

    public function findCustomerByIdentifier(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $stmt = $this->db->prepare("SELECT * FROM customers WHERE phone = :identifier OR email = :identifier LIMIT 1");
        $stmt->execute([':identifier' => $identifier]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($customer) {
            return $customer;
        }

        $stmt = $this->db->prepare("SELECT * FROM customers WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $identifier]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        return $customer ?: null;
    }

    public function createCustomer(array $data): array
    {
        $payload = [
            'name' => $data['name'] ?? ('Loyalty Customer ' . date('His')),
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'total_purchases' => 0,
            'total_orders' => 0,
            'is_active' => 1,
        ];

        $columns = array_keys($payload);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);

        $sql = sprintf(
            'INSERT INTO customers (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_combine($placeholders, array_values($payload)));

        $customerId = (int) $this->db->lastInsertId();
        return $this->getCustomerById($customerId);
    }

    public function getEnrollment(int $customerId, int $programId, bool $forUpdate = false): ?array
    {
        $sql = "SELECT *, (points_earned - points_redeemed) AS points_balance FROM customer_loyalty_points WHERE customer_id = :customer AND program_id = :program";
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':customer' => $customerId,
            ':program' => $programId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function ensureEnrollment(int $customerId, int $programId, bool $forUpdate = false): array
    {
        $this->ensureSchema();
        $enrollment = $this->getEnrollment($customerId, $programId, $forUpdate);
        if ($enrollment) {
            return $enrollment;
        }

        $stmt = $this->db->prepare("INSERT INTO customer_loyalty_points (customer_id, program_id, points_earned, points_redeemed) VALUES (:customer, :program, 0, 0)");
        $stmt->execute([
            ':customer' => $customerId,
            ':program' => $programId,
        ]);

        return $this->getEnrollment($customerId, $programId, $forUpdate) ?? [
            'id' => (int) $this->db->lastInsertId(),
            'customer_id' => $customerId,
            'program_id' => $programId,
            'points_earned' => 0,
            'points_redeemed' => 0,
            'points_balance' => 0,
        ];
    }

    public function calculateEarnedPoints(float $amount, float $pointsPerCurrency): int
    {
        if ($amount <= 0 || $pointsPerCurrency <= 0) {
            return 0;
        }

        return (int) floor($amount * $pointsPerCurrency);
    }

    public function calculateRedemptionValue(int $points, float $redemptionRate): float
    {
        if ($points <= 0 || $redemptionRate <= 0) {
            return 0.0;
        }

        return round($points * $redemptionRate, 2);
    }

    public function validateRedemption(int $customerId, int $programId, int $points): array
    {
        if ($points <= 0) {
            throw new RuntimeException('Points to redeem must be greater than zero.');
        }

        $program = $this->getProgramById($programId) ?? $this->ensureDefaultProgram();

        if ($points < (int) $program['min_points_redemption']) {
            throw new RuntimeException(sprintf('Minimum redemption is %d points.', (int) $program['min_points_redemption']));
        }

        $enrollment = $this->ensureEnrollment($customerId, (int) $program['id']);
        $balance = (int) ($enrollment['points_balance'] ?? 0);

        if ($balance < $points) {
            throw new RuntimeException('Customer does not have enough loyalty points.');
        }

        $value = $this->calculateRedemptionValue($points, (float) $program['redemption_rate']);

        return [
            'program' => $program,
            'points_requested' => $points,
            'points_balance' => $balance,
            'value' => $value,
        ];
    }

    public function applySaleLoyalty(array $payload): array
    {
        $customerId = (int) ($payload['customer_id'] ?? 0);
        if ($customerId <= 0) {
            return ['applied' => false];
        }

        $programId = isset($payload['program_id']) ? (int) $payload['program_id'] : null;
        $program = $programId ? $this->getProgramById($programId) : null;
        if (!$program) {
            $program = $this->getActiveProgram() ?? $this->ensureDefaultProgram();
        }

        $saleId = (int) ($payload['sale_id'] ?? 0);
        $saleTotal = (float) ($payload['sale_total'] ?? 0);
        $pointsToRedeem = max(0, (int) ($payload['points_to_redeem'] ?? 0));
        $discountAmount = max(0.0, (float) ($payload['discount_amount'] ?? 0));
        $earnedPoints = $this->calculateEarnedPoints(max(0.0, $saleTotal), (float) $program['points_per_dollar']);

        $results = [
            'applied' => true,
            'earned_points' => 0,
            'redeemed_points' => 0,
            'program_id' => (int) $program['id'],
        ];

        $this->db->beginTransaction();
        try {
            $enrollment = $this->ensureEnrollment($customerId, (int) $program['id'], true);
            $balance = (int) ($enrollment['points_balance'] ?? 0);

            if ($pointsToRedeem > 0) {
                if ($pointsToRedeem > $balance) {
                    throw new RuntimeException('Customer does not have enough loyalty points for redemption.');
                }

                $expectedValue = $this->calculateRedemptionValue($pointsToRedeem, (float) $program['redemption_rate']);
                if ($discountAmount <= 0 || abs($expectedValue - $discountAmount) > 0.05) {
                    $discountAmount = $expectedValue;
                }

                $this->db->prepare("UPDATE customer_loyalty_points SET points_redeemed = points_redeemed + :points, last_activity = NOW() WHERE id = :id")
                    ->execute([
                        ':points' => $pointsToRedeem,
                        ':id' => $enrollment['id'],
                    ]);

                $this->recordTransaction([
                    'customer_id' => $customerId,
                    'program_id' => (int) $program['id'],
                    'transaction_type' => 'redeem',
                    'points' => $pointsToRedeem,
                    'sale_id' => $saleId ?: null,
                    'description' => $payload['redeem_description'] ?? sprintf('Redeemed %d points for sale #%d', $pointsToRedeem, $saleId),
                ]);

                $results['redeemed_points'] = $pointsToRedeem;
                $balance -= $pointsToRedeem;
            }

            if ($earnedPoints > 0) {
                $this->db->prepare("UPDATE customer_loyalty_points SET points_earned = points_earned + :points, last_activity = NOW() WHERE id = :id")
                    ->execute([
                        ':points' => $earnedPoints,
                        ':id' => $enrollment['id'],
                    ]);

                $this->recordTransaction([
                    'customer_id' => $customerId,
                    'program_id' => (int) $program['id'],
                    'transaction_type' => 'earn',
                    'points' => $earnedPoints,
                    'sale_id' => $saleId ?: null,
                    'description' => $payload['earn_description'] ?? sprintf('Earned points from sale #%d', $saleId),
                ]);

                $results['earned_points'] = $earnedPoints;
            }

            $this->db->commit();

            $results['balance_after'] = $balance + $earnedPoints;
            $results['discount_amount'] = $discountAmount;
            if ($saleId > 0) {
                $this->saveSaleSummary($saleId, [
                    'customer_id' => $customerId,
                    'program_id' => (int) $program['id'],
                    'points_earned' => $earnedPoints,
                    'points_redeemed' => $pointsToRedeem,
                    'discount_amount' => $discountAmount,
                    'balance_after' => $results['balance_after'] ?? null,
                ]);
            }
            return $results;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function recordTransaction(array $data): void
    {
        $this->ensureSchema();
        $stmt = $this->db->prepare("INSERT INTO loyalty_transactions (customer_id, program_id, transaction_type, points, sale_id, description) VALUES (:customer_id, :program_id, :transaction_type, :points, :sale_id, :description)");
        $stmt->execute([
            ':customer_id' => $data['customer_id'],
            ':program_id' => $data['program_id'],
            ':transaction_type' => $data['transaction_type'],
            ':points' => $data['points'],
            ':sale_id' => $data['sale_id'],
            ':description' => $data['description'] ?? null,
        ]);
    }

    private function ensureSaleSummaryTable(): void
    {
        if ($this->saleSummaryTableEnsured) {
            return;
        }

        $this->ensureSchema();

        $this->db->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS sale_loyalty_summary (
                sale_id INT UNSIGNED PRIMARY KEY,
                customer_id INT UNSIGNED NOT NULL,
                program_id INT UNSIGNED NOT NULL,
                points_earned INT DEFAULT 0,
                points_redeemed INT DEFAULT 0,
                discount_amount DECIMAL(15,2) DEFAULT 0,
                balance_after INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
                FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
                FOREIGN KEY (program_id) REFERENCES loyalty_programs(id)
            ) ENGINE=InnoDB
        SQL);

        $this->saleSummaryTableEnsured = true;
    }

    private function saveSaleSummary(int $saleId, array $data): void
    {
        $this->ensureSaleSummaryTable();

        $stmt = $this->db->prepare(
            "INSERT INTO sale_loyalty_summary (sale_id, customer_id, program_id, points_earned, points_redeemed, discount_amount, balance_after)
             VALUES (:sale_id, :customer_id, :program_id, :points_earned, :points_redeemed, :discount_amount, :balance_after)
             ON DUPLICATE KEY UPDATE
                 customer_id = VALUES(customer_id),
                 program_id = VALUES(program_id),
                 points_earned = VALUES(points_earned),
                 points_redeemed = VALUES(points_redeemed),
                 discount_amount = VALUES(discount_amount),
                 balance_after = VALUES(balance_after)"
        );

        $stmt->execute([
            ':sale_id' => $saleId,
            ':customer_id' => $data['customer_id'],
            ':program_id' => $data['program_id'],
            ':points_earned' => (int) ($data['points_earned'] ?? 0),
            ':points_redeemed' => (int) ($data['points_redeemed'] ?? 0),
            ':discount_amount' => (float) ($data['discount_amount'] ?? 0),
            ':balance_after' => (int) ($data['balance_after'] ?? 0),
        ]);
    }

    public function getSaleSummary(int $saleId): ?array
    {
        try {
            $this->ensureSaleSummaryTable();
        } catch (Throwable $e) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT * FROM sale_loyalty_summary WHERE sale_id = ? LIMIT 1");
        $stmt->execute([$saleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getProgramStats(int $programId): array
    {
        $this->ensureSchema();
        $stats = [
            'members' => 0,
            'points_earned' => 0,
            'points_redeemed' => 0,
            'breakage' => 0,
        ];

        $stmt = $this->db->prepare("SELECT COUNT(*) AS members, COALESCE(SUM(points_earned),0) AS earned, COALESCE(SUM(points_redeemed),0) AS redeemed FROM customer_loyalty_points WHERE program_id = ?");
        $stmt->execute([$programId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $stats['members'] = (int) $row['members'];
            $stats['points_earned'] = (int) $row['earned'];
            $stats['points_redeemed'] = (int) $row['redeemed'];
            $stats['breakage'] = max(0, $stats['points_earned'] - $stats['points_redeemed']);
        }

        return $stats;
    }

    public function getTopMembers(int $programId, int $limit = 5): array
    {
        $sql = "SELECT c.id, c.name, c.phone, c.email, lp.points_earned, lp.points_redeemed, (lp.points_earned - lp.points_redeemed) AS points_balance
                FROM customer_loyalty_points lp
                JOIN customers c ON lp.customer_id = c.id
                WHERE lp.program_id = :program
                ORDER BY points_balance DESC
                LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':program', $programId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
