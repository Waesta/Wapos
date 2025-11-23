<?php

namespace App\Services;

use DateInterval;
use DateTimeImmutable;
use Exception;
use PDO;
use PDOException;
use ZipArchive;

class SystemBackupService
{
    private PDO $db;
    private string $defaultBackupDir;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->defaultBackupDir = ROOT_PATH . '/backups';
        $this->ensureSchema();
        $this->ensureDirectory($this->defaultBackupDir);
    }

    /**
     * Ensure the backup logs table exists.
     */
    private function ensureSchema(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS backup_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            backup_file VARCHAR(255) NOT NULL,
            backup_size BIGINT DEFAULT 0,
            backup_type ENUM('auto','manual') DEFAULT 'manual',
            status ENUM('success','failed','running') DEFAULT 'running',
            message TEXT NULL,
            storage_path VARCHAR(255) NOT NULL,
            initiated_by INT UNSIGNED NULL,
            retention_days INT DEFAULT 30,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB";

        $this->db->exec($sql);
    }

    /**
     * Ensure a directory exists before writing files.
     */
    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Return persisted backup configuration values.
     */
    public function getConfig(): array
    {
        return [
            'frequency' => settings('backup_frequency') ?? 'daily',
            'time' => settings('backup_time_of_day') ?? '02:00',
            'weekday' => settings('backup_weekday') ?? 'monday',
            'retention_days' => (int)(settings('backup_retention_days') ?? 30),
            'storage_path' => settings('backup_storage_path') ?? $this->defaultBackupDir,
        ];
    }

    /**
     * Persist backup configuration.
     */
    public function persistConfig(array $config): void
    {
        SettingsStore::persistMany([
            'backup_frequency' => $config['frequency'] ?? 'daily',
            'backup_time_of_day' => $config['time'] ?? '02:00',
            'backup_weekday' => $config['weekday'] ?? 'monday',
            'backup_retention_days' => (string)($config['retention_days'] ?? 30),
            'backup_storage_path' => $config['storage_path'] ?? $this->defaultBackupDir,
        ]);

        $this->ensureDirectory($config['storage_path'] ?? $this->defaultBackupDir);
    }

    /**
     * Run a backup immediately.
     */
    public function runBackup(array $options = [], ?int $initiatedBy = null): array
    {
        $config = array_merge($this->getConfig(), $options);
        $storagePath = $config['storage_path'] ?: $this->defaultBackupDir;
        $this->ensureDirectory($storagePath);

        $timestamp = (new DateTimeImmutable())->format('Y-m-d_H-i-s');
        $baseFilename = sprintf('wapos_%s', $timestamp);
        $sqlPath = $storagePath . '/' . $baseFilename . '.sql';
        $zipPath = $storagePath . '/' . $baseFilename . '.zip';

        $logId = $this->createLogRecord([
            'backup_file' => basename($zipPath),
            'storage_path' => $storagePath,
            'backup_type' => $options['backup_type'] ?? 'manual',
            'status' => 'running',
            'initiated_by' => $initiatedBy,
            'retention_days' => (int)$config['retention_days'],
        ]);

        try {
            $this->dumpDatabaseToFile($sqlPath);
            $this->createArchive($sqlPath, $zipPath);
            @unlink($sqlPath);

            $size = file_exists($zipPath) ? filesize($zipPath) : 0;
            $this->updateLogRecord($logId, [
                'status' => 'success',
                'backup_size' => $size,
                'message' => 'Backup completed successfully',
            ]);

            $this->purgeExpiredBackups((int)$config['retention_days']);

            return [
                'success' => true,
                'id' => $logId,
                'file' => $zipPath,
                'size' => $size,
            ];
        } catch (Exception $e) {
            $this->updateLogRecord($logId, [
                'status' => 'failed',
                'message' => $e->getMessage(),
            ]);
            if (file_exists($sqlPath)) {
                @unlink($sqlPath);
            }
            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }
            throw $e;
        }
    }

    /**
     * Run mysqldump to export the database.
     */
    private function dumpDatabaseToFile(string $destination): void
    {
        $command = sprintf(
            'mysqldump --host=%s --user=%s --password=%s %s > %s 2>&1',
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            escapeshellarg(DB_PASS),
            escapeshellarg(DB_NAME),
            escapeshellarg($destination)
        );

        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            $message = implode("\n", $output);
            throw new Exception('mysqldump failed: ' . $message);
        }

        if (!file_exists($destination)) {
            throw new Exception('Backup file was not created.');
        }
    }

    /**
     * Zip the dumped SQL file for storage.
     */
    private function createArchive(string $sourceSql, string $destinationZip): void
    {
        $zip = new ZipArchive();
        if ($zip->open($destinationZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Unable to create backup archive.');
        }

        $zip->addFile($sourceSql, basename($sourceSql));
        $zip->setArchiveComment('WAPOS automated backup');
        $zip->close();
    }

    private function createLogRecord(array $payload): int
    {
        $sql = "INSERT INTO backup_logs (backup_file, backup_size, backup_type, status, message, storage_path, initiated_by, retention_days)
                VALUES (:backup_file, 0, :backup_type, :status, '', :storage_path, :initiated_by, :retention_days)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':backup_file' => $payload['backup_file'],
            ':backup_type' => $payload['backup_type'] ?? 'manual',
            ':status' => $payload['status'] ?? 'running',
            ':storage_path' => $payload['storage_path'],
            ':initiated_by' => $payload['initiated_by'],
            ':retention_days' => $payload['retention_days'] ?? 30,
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function updateLogRecord(int $id, array $payload): void
    {
        if (empty($payload)) {
            return;
        }

        $fields = [];
        $params = [':id' => $id];
        foreach ($payload as $key => $value) {
            $fields[] = "$key = :$key";
            $params[":" . $key] = $value;
        }

        $sql = 'UPDATE backup_logs SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    public function listBackups(int $limit = 20): array
    {
        $stmt = $this->db->prepare('SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getBackupById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM backup_logs WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function deleteBackup(int $id): bool
    {
        $record = $this->getBackupById($id);
        if (!$record) {
            return false;
        }

        $fullPath = rtrim($record['storage_path'], '/\\') . '/' . $record['backup_file'];
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }

        $stmt = $this->db->prepare('DELETE FROM backup_logs WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function purgeExpiredBackups(int $retentionDays): int
    {
        if ($retentionDays <= 0) {
            return 0;
        }

        $threshold = (new DateTimeImmutable())
            ->sub(new DateInterval('P' . max(1, $retentionDays) . 'D'))
            ->format('Y-m-d H:i:s');

        $stmt = $this->db->prepare('SELECT id, backup_file, storage_path FROM backup_logs WHERE created_at <= :threshold');
        $stmt->execute([':threshold' => $threshold]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $deleted = 0;

        foreach ($rows as $row) {
            $fullPath = rtrim($row['storage_path'], '/\\') . '/' . $row['backup_file'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
            $this->deleteBackup((int)$row['id']);
            $deleted++;
        }

        return $deleted;
    }
}
