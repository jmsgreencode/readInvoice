<?php

declare(strict_types=1);

namespace App\Database;

use App\Logging\StructuredLogger;

class MigrationRunner
{
    private Connection $db;
    private StructuredLogger $logger;
    private string $migrationsPath;

    public function __construct(Connection $db, StructuredLogger $logger, string $migrationsPath)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->migrationsPath = $migrationsPath;
    }

    public function run(): void
    {
        $this->ensureMigrationsTable();
        $applied = $this->getAppliedMigrations();
        $files = $this->getMigrationFiles();

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                $this->logger->error('Failed to read migration file', ['file' => $name]);
                continue;
            }

            try {
                $this->db->beginTransaction();

                $statements = array_filter(
                    array_map('trim', explode(';', $sql)),
                    fn(string $s) => $s !== ''
                );

                foreach ($statements as $statement) {
                    $this->db->execute($statement);
                }

                $this->db->execute(
                    'INSERT INTO migrations (name, applied_at) VALUES (?, NOW())',
                    [$name]
                );

                $this->db->commit();
                $this->logger->info('Migration applied', ['migration' => $name]);
            } catch (\Exception $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                $this->logger->error('Migration failed', [
                    'migration' => $name,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }
    }

    private function ensureMigrationsTable(): void
    {
        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL UNIQUE,
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private function getAppliedMigrations(): array
    {
        $stmt = $this->db->execute('SELECT name FROM migrations ORDER BY id');
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function getMigrationFiles(): array
    {
        $files = glob($this->migrationsPath . '/*.sql');
        sort($files);
        return $files;
    }
}
