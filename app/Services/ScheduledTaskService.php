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
    }

    /**
     * Upsert backup task using existing schema:
     * task_key, description, schedule_expression, next_run_at, last_run_at, status, is_active
     */
    public function upsertBackupTask(array $config): void
    {
        $scheduleExpr = $this->configToScheduleExpression($config);
        $nextRun = $this->computeNextRun($config)->format('Y-m-d H:i:s');
        $description = 'Automated system backup (' . ($config['frequency'] ?? 'daily') . ')';

        $stmt = $this->db->prepare(
            "INSERT INTO scheduled_tasks (task_key, description, schedule_expression, next_run_at, is_active)
             VALUES ('system_backup', :description, :schedule_expr, :next_run, 1)
             ON DUPLICATE KEY UPDATE 
                description = VALUES(description), 
                schedule_expression = VALUES(schedule_expression), 
                next_run_at = VALUES(next_run_at), 
                is_active = 1"
        );
        $stmt->execute([
            ':description' => $description,
            ':schedule_expr' => $scheduleExpr,
            ':next_run' => $nextRun,
        ]);
    }

    public function getTaskByKey(string $taskKey): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM scheduled_tasks WHERE task_key = :key');
        $stmt->execute([':key' => $taskKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDueTasks(): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM scheduled_tasks
             WHERE is_active = 1
               AND status IN ('pending','failed')
               AND next_run_at IS NOT NULL
               AND next_run_at <= NOW()"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function markRunning(int $taskId): void
    {
        $stmt = $this->db->prepare("UPDATE scheduled_tasks SET status = 'running', last_run_at = NOW(), updated_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $taskId]);
    }

    public function markCompleted(int $taskId, bool $success, ?string $message, array $config): void
    {
        $nextRun = $this->computeNextRun($config)->format('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            "UPDATE scheduled_tasks SET status = :status, last_error = :message, next_run_at = :next_run, updated_at = NOW() WHERE id = :id"
        );
        $stmt->execute([
            ':status' => $success ? 'pending' : 'failed',
            ':message' => $success ? null : $message,
            ':next_run' => $nextRun,
            ':id' => $taskId,
        ]);
    }

    public function deactivateTask(string $taskKey): void
    {
        $stmt = $this->db->prepare('UPDATE scheduled_tasks SET is_active = 0 WHERE task_key = :key');
        $stmt->execute([':key' => $taskKey]);
    }

    private function configToScheduleExpression(array $config): string
    {
        $frequency = $config['frequency'] ?? 'daily';
        $time = $config['time'] ?? '02:00';
        $weekday = $config['weekday'] ?? 'monday';

        if ($frequency === 'hourly') {
            return 'hourly';
        }
        if ($frequency === 'weekly') {
            return "weekly:{$weekday}@{$time}";
        }
        return "daily@{$time}";
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
