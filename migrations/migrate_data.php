<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use Config\Database;

class Migrate
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->ensureVersionTable();
    }

    /**
     * Creates the schema_versions table if it doesn't exist.
     * This enables idempotent migrations — re-running is safe.
     */
    private function ensureVersionTable(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS schema_versions (
                version     VARCHAR(64) PRIMARY KEY,
                applied_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                description VARCHAR(255) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    private function isApplied(string $version): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM schema_versions WHERE version = :v LIMIT 1');
        $stmt->execute([':v' => $version]);
        return $stmt->fetchColumn() !== false;
    }

    private function markApplied(string $version, string $description): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO schema_versions (version, description) VALUES (:v, :d)'
        );
        $stmt->execute([':v' => $version, ':d' => $description]);
    }

    public function run(string $filePath, string $description = ''): void
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("Migration file not found: {$filePath}");
        }

        $version = basename($filePath);

        if ($this->isApplied($version)) {
            echo "Skipped (already applied): {$version}" . PHP_EOL;
            return;
        }

        $sql = file_get_contents($filePath);

        try {
            $this->db->exec($sql);
            $this->markApplied($version, $description ?: $version);
            echo "Applied: {$version}" . PHP_EOL;
        } catch (\PDOException $e) {
            throw new RuntimeException(
                "Migration failed [{$version}]: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
}

$migrate = new Migrate();
$migrate->run(__DIR__ . '/001_create_tables.sql',          'Initial schema');
$migrate->run(__DIR__ . '/002_add_indexes_cascade.sql',    'Indexes and cascade FKs');
$migrate->run(__DIR__ . '/003_add_users_multitenancy.sql', 'Users table and user_id FKs');
