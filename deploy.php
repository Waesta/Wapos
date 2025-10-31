<?php
/**
 * WAPOS Deployment Script
 * For shared hosting deployment with rollback capability
 * 
 * Usage: php deploy.php [version]
 */

require_once __DIR__ . '/config.php';

class Deployer
{
    private string $rootPath;
    private string $releasesPath;
    private string $currentPath;
    private string $backupPath;
    private PDO $db;

    public function __construct()
    {
        $this->rootPath = __DIR__;
        $this->releasesPath = $this->rootPath . '/releases';
        $this->currentPath = $this->rootPath . '/current';
        $this->backupPath = $this->rootPath . '/backups';
        
        $this->ensureDirectories();
        $this->initDatabase();
    }

    /**
     * Deploy new version
     */
    public function deploy(string $version): void
    {
        echo "🚀 Starting deployment of version {$version}...\n";

        try {
            // Step 1: Backup database
            echo "📦 Creating database backup...\n";
            $this->backupDatabase();

            // Step 2: Run migrations
            echo "🔄 Running database migrations...\n";
            $this->runMigrations();

            // Step 3: Clear caches
            echo "🧹 Clearing caches...\n";
            $this->clearCaches();

            // Step 4: Update version file
            echo "📝 Updating version...\n";
            file_put_contents($this->rootPath . '/version.txt', $version);

            echo "✅ Deployment completed successfully!\n";
            echo "Version: {$version}\n";
            echo "Time: " . date('Y-m-d H:i:s') . "\n";

        } catch (\Exception $e) {
            echo "❌ Deployment failed: " . $e->getMessage() . "\n";
            echo "🔙 Rolling back...\n";
            $this->rollback();
            exit(1);
        }
    }

    /**
     * Rollback to previous version
     */
    public function rollback(): void
    {
        echo "🔙 Rolling back to previous version...\n";

        try {
            // Restore database from latest backup
            $latestBackup = $this->getLatestBackup();
            
            if ($latestBackup) {
                echo "📥 Restoring database from {$latestBackup}...\n";
                $this->restoreDatabase($latestBackup);
            }

            echo "✅ Rollback completed\n";

        } catch (\Exception $e) {
            echo "❌ Rollback failed: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    /**
     * Backup database
     */
    private function backupDatabase(): void
    {
        $filename = $this->backupPath . '/db_' . date('Y-m-d_H-i-s') . '.sql';
        
        $command = sprintf(
            'mysqldump -h%s -u%s -p%s %s > %s',
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME,
            $filename
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception("Database backup failed");
        }

        echo "✓ Database backed up to {$filename}\n";

        // Keep only last 3 backups
        $this->cleanupOldBackups(3);
    }

    /**
     * Restore database from backup
     */
    private function restoreDatabase(string $backupFile): void
    {
        $command = sprintf(
            'mysql -h%s -u%s -p%s %s < %s',
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME,
            $backupFile
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception("Database restore failed");
        }

        echo "✓ Database restored from {$backupFile}\n";
    }

    /**
     * Run database migrations
     */
    private function runMigrations(): void
    {
        $migrationsPath = $this->rootPath . '/database/migrations';
        
        if (!is_dir($migrationsPath)) {
            echo "⚠ No migrations directory found\n";
            return;
        }

        $files = glob($migrationsPath . '/*.sql');
        sort($files);

        foreach ($files as $file) {
            $migrationName = basename($file, '.sql');
            
            // Check if already executed
            if ($this->isMigrationExecuted($migrationName)) {
                echo "⏭ Skipping {$migrationName} (already executed)\n";
                continue;
            }

            echo "▶ Running {$migrationName}...\n";
            
            $sql = file_get_contents($file);
            
            try {
                $this->db->exec($sql);
                $this->recordMigration($migrationName);
                echo "✓ {$migrationName} completed\n";
            } catch (\PDOException $e) {
                throw new \Exception("Migration {$migrationName} failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Clear caches
     */
    private function clearCaches(): void
    {
        $cachePath = $this->rootPath . '/cache';
        
        if (!is_dir($cachePath)) {
            return;
        }

        $files = glob($cachePath . '/*');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        echo "✓ Caches cleared\n";
    }

    /**
     * Check if migration was executed
     */
    private function isMigrationExecuted(string $name): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM migrations WHERE migration_name = ?");
        $stmt->execute([$name]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Record migration execution
     */
    private function recordMigration(string $name): void
    {
        $stmt = $this->db->prepare("INSERT INTO migrations (migration_name) VALUES (?)");
        $stmt->execute([$name]);
    }

    /**
     * Get latest backup file
     */
    private function getLatestBackup(): ?string
    {
        $files = glob($this->backupPath . '/db_*.sql');
        
        if (empty($files)) {
            return null;
        }

        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return $files[0];
    }

    /**
     * Cleanup old backups
     */
    private function cleanupOldBackups(int $keep = 3): void
    {
        $files = glob($this->backupPath . '/db_*.sql');
        
        if (count($files) <= $keep) {
            return;
        }

        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $toDelete = array_slice($files, $keep);
        
        foreach ($toDelete as $file) {
            unlink($file);
        }

        echo "✓ Cleaned up " . count($toDelete) . " old backup(s)\n";
    }

    /**
     * Ensure required directories exist
     */
    private function ensureDirectories(): void
    {
        $dirs = [
            $this->releasesPath,
            $this->backupPath,
            $this->rootPath . '/logs',
            $this->rootPath . '/cache'
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Initialize database connection
     */
    private function initDatabase(): void
    {
        try {
            $this->db = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (\PDOException $e) {
            die("Database connection failed: " . $e->getMessage() . "\n");
        }
    }
}

// Main execution
if (php_sapi_name() !== 'cli') {
    die("This script must be run from command line\n");
}

$version = $argv[1] ?? date('Y.m.d-His');
$action = $argv[2] ?? 'deploy';

$deployer = new Deployer();

if ($action === 'rollback') {
    $deployer->rollback();
} else {
    $deployer->deploy($version);
}
