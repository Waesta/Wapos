<?php
use App\Services\ScheduledTaskService;
use App\Services\SystemBackupService;

require_once __DIR__ . '/../includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain', true, 403);
    echo "This script is intended to be executed via CLI." . PHP_EOL;
    exit(1);
}

$echo = static function (string $message): void {
    echo '[' . date('Y-m-d H:i:s') . "] {$message}" . PHP_EOL;
};

$echo('Initializing scheduler context...');

$db = Database::getInstance();
$pdo = $db->getConnection();
$scheduledTaskService = new ScheduledTaskService($pdo);
$backupService = new SystemBackupService($pdo);

$dueTasks = $scheduledTaskService->getDueTasks();
if (empty($dueTasks)) {
    $echo('No scheduled tasks are due at this time.');
    exit(0);
}

foreach ($dueTasks as $task) {
    $taskId = (int)($task['id'] ?? 0);
    $taskName = $task['task_name'] ?? 'unknown';
    $taskType = $task['task_type'] ?? 'unknown';

    $config = [];
    if (!empty($task['config'])) {
        $decoded = json_decode($task['config'], true);
        if (is_array($decoded)) {
            $config = $decoded;
        }
    }

    if (empty($config)) {
        // Fallback to current backup configuration so that next_run calculations remain stable
        $config = $backupService->getConfig();
    }

    $scheduledTaskService->markRunning($taskId);
    $echo("Executing task '{$taskName}' ({$taskType})...");

    $success = false;
    $message = 'Completed';

    try {
        switch ($taskType) {
            case 'backup':
                $backupService->runBackup([
                    'backup_type' => 'auto',
                    'storage_path' => $config['storage_path'] ?? null,
                ]);
                $success = true;
                $message = 'Backup completed';
                break;

            default:
                $message = 'Unsupported task type';
                throw new RuntimeException($message);
        }
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $echo("Task '{$taskName}' failed: {$message}");
    }

    $scheduledTaskService->markCompleted($taskId, $success, $message, $config);

    if ($success) {
        $echo("Task '{$taskName}' finished successfully.");
    } else {
        $echo("Task '{$taskName}' marked as failed.");
    }
}

$echo('Scheduler run complete.');
