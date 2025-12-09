<?php

namespace App\Services;

use PDO;
use Throwable;

/**
 * TaskRunner - Executes scheduled tasks without cron jobs.
 * 
 * This class checks for due tasks on each request and runs them
 * in the background. It uses a lock file to prevent concurrent execution.
 */
class TaskRunner
{
    private PDO $db;
    private string $lockFile;
    private static bool $hasRun = false;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->lockFile = sys_get_temp_dir() . '/wapos_task_runner.lock';
    }

    /**
     * Check and run due tasks. Called from bootstrap/footer.
     * Uses locking to prevent concurrent execution.
     */
    public function checkAndRun(): void
    {
        // Only run once per request
        if (self::$hasRun) {
            return;
        }
        self::$hasRun = true;

        // Check if we should run (throttle to once per minute)
        if (!$this->shouldRun()) {
            return;
        }

        // Try to acquire lock
        if (!$this->acquireLock()) {
            return;
        }

        try {
            $this->runDueTasks();
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Throttle checks to once per minute using a simple file timestamp.
     */
    private function shouldRun(): bool
    {
        $checkFile = sys_get_temp_dir() . '/wapos_last_task_check.txt';
        
        if (file_exists($checkFile)) {
            $lastCheck = (int)file_get_contents($checkFile);
            // Only check every 60 seconds
            if (time() - $lastCheck < 60) {
                return false;
            }
        }

        file_put_contents($checkFile, time());
        return true;
    }

    /**
     * Acquire an exclusive lock to prevent concurrent task execution.
     */
    private function acquireLock(): bool
    {
        // Check if lock file exists and is recent (less than 5 minutes old)
        if (file_exists($this->lockFile)) {
            $lockTime = (int)file_get_contents($this->lockFile);
            if (time() - $lockTime < 300) {
                // Lock is still valid, another process is running
                return false;
            }
            // Lock is stale, remove it
            @unlink($this->lockFile);
        }

        // Create lock file
        file_put_contents($this->lockFile, time());
        return true;
    }

    /**
     * Release the lock.
     */
    private function releaseLock(): void
    {
        @unlink($this->lockFile);
    }

    /**
     * Run all due tasks.
     */
    private function runDueTasks(): void
    {
        $scheduledTaskService = new ScheduledTaskService($this->db);
        $dueTasks = $scheduledTaskService->getDueTasks();

        if (empty($dueTasks)) {
            return;
        }

        foreach ($dueTasks as $task) {
            $this->executeTask($task, $scheduledTaskService);
        }
    }

    /**
     * Execute a single task.
     */
    private function executeTask(array $task, ScheduledTaskService $scheduledTaskService): void
    {
        $taskId = (int)($task['id'] ?? 0);
        $taskKey = $task['task_key'] ?? 'unknown';

        // Determine task type from task_key
        $taskType = 'unknown';
        if (str_contains($taskKey, 'backup')) {
            $taskType = 'backup';
        }

        $scheduledTaskService->markRunning($taskId);

        $success = false;
        $message = 'Completed';
        $config = [];

        try {
            switch ($taskType) {
                case 'backup':
                    $backupService = new SystemBackupService($this->db);
                    $config = $backupService->getConfig();
                    $backupService->runBackup([
                        'backup_type' => 'auto',
                        'storage_path' => $config['storage_path'] ?? null,
                    ]);
                    $success = true;
                    $message = 'Backup completed automatically';
                    break;

                default:
                    $message = 'Unsupported task type: ' . $taskKey;
                    break;
            }
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $this->logError("Task '{$taskKey}' failed: {$message}");
        }

        $scheduledTaskService->markCompleted($taskId, $success, $message, $config);
    }

    /**
     * Log errors to the application log.
     */
    private function logError(string $message): void
    {
        $logFile = defined('ROOT_PATH') ? ROOT_PATH . '/logs/task_runner.log' : sys_get_temp_dir() . '/wapos_task_runner.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
    }

    /**
     * Static method to run from bootstrap - minimal overhead.
     */
    public static function trigger(PDO $db): void
    {
        // Run in a way that doesn't block the request
        try {
            $runner = new self($db);
            $runner->checkAndRun();
        } catch (Throwable $e) {
            // Silently fail - don't break the user's request
            error_log('TaskRunner error: ' . $e->getMessage());
        }
    }
}
