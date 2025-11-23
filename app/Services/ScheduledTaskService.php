<?php

namespace App\Services;

use DateInterval;
use DateTimeImmutable;
use Exception;
use PDO;
use PDOException;

class ScheduledTaskService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS scheduled_tasks (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            task_name VARCHAR(100) NOT NULL,
            task_type ENUM('backup','report','alert','cleanup') NOT NULL,
            schedule VARCHAR(50) NOT NULL,
            last_run DATETIME NULL,
            next_run DATETIME NULL,
            status ENUM('active','inactive','running','failed') DEFAULT 'active',
            config TEXT NULL,
            message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_task_name (task_name)
        ) ENGINE=InnoDB";

        $this->db->exec($sql);
    }

    public function upsertBackupTask(array $config): void
    {
        $payload = [
            'schedule' => $config['frequency'] ?? 'daily',
            'config' => json_encode($config),
            'next_run' => $this->computeNextRun($config)->format('Y-m-d H:i:s'),
        ];

        $stmt = $this->db->prepare(
            "INSERT INTO scheduled_tasks (task_name, task_type, schedule, next_run, config)
             VALUES ('daily_backup', 'backup', :schedule, :next_run, :config)
             ON DUPLICATE KEY UPDATE schedule = VALUES(schedule), config = VALUES(config), next_run = VALUES(next_run), status = 'active'"
        );
        $stmt->execute($payload);
    }

    public function getTaskByName(string $taskName): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM scheduled_tasks WHERE task_name = :name');
        $stmt->execute([':name' => $taskName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDueTasks(): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM scheduled_tasks
             WHERE status IN ('active','failed')
               AND next_run IS NOT NULL
               AND next_run <= NOW()"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function markRunning(int $taskId): void
    {
        $stmt = $this->db->prepare('UPDATE scheduled_tasks SET status = "running", last_run = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $taskId]);
    }

    public function markCompleted(int $taskId, bool $success, ?string $message, array $config): void
    {
        $nextRun = $this->computeNextRun($config)->format('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            'UPDATE scheduled_tasks SET status = :status, message = :message, next_run = :next_run, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            ':status' => $success ? 'active' : 'failed',
            ':message' => $message,
            ':next_run' => $nextRun,
            ':id' => $taskId,
        ]);
    }

    public function deactivateTask(string $taskName): void
    {
        $stmt = $this->db->prepare('UPDATE scheduled_tasks SET status = "inactive" WHERE task_name = :name');
        $stmt->execute([':name' => $taskName]);
    }

    private function computeNextRun(array $config): DateTimeImmutable
    {
        $frequency = $config['frequency'] ?? 'daily';
        $time = $config['time'] ?? '02:00';
        $weekday = strtolower($config['weekday'] ?? 'monday');

        $now = new DateTimeImmutable('now');
        $base = DateTimeImmutable::createFromFormat('Y-m-d H:i', $now->format('Y-m-d') . ' ' . $time) ?: $now;

        if ($frequency === 'hourly') {
            $candidate = $now->add(new DateInterval('PT1H'))->setTime((int)$now->format('H'), (int)$base->format('i'));
            return $candidate;
        }

        if ($frequency === 'weekly') {
            $candidate = $base;
            $targetDow = self::weekdayToInt($weekday);
            $currentDow = (int)$base->format('w');
            $days = ($targetDow - $currentDow + 7) % 7;
            if ($days === 0 && $base <= $now) {
                $days = 7;
            }
            $candidate = $candidate->add(new DateInterval('P' . $days . 'D'));
            return $candidate;
        }

        // default daily
        if ($base <= $now) {
            $base = $base->add(new DateInterval('P1D'));
        }
        return $base;
    }

    private static function weekdayToInt(string $weekday): int
    {
        $map = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];
        return $map[$weekday] ?? 1;
    }
}
