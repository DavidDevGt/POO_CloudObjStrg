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
    }

    public function run(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("Migration file not found: {$filePath}");
        }

        $sql = file_get_contents($filePath);

        try {
            $this->db->exec($sql);
            echo 'Migration applied: ' . basename($filePath) . PHP_EOL;
        } catch (\PDOException $e) {
            throw new RuntimeException(
                'Migration failed [' . basename($filePath) . ']: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}

$migrate = new Migrate();
$migrate->run(__DIR__ . '/001_create_tables.sql');
$migrate->run(__DIR__ . '/002_add_indexes_cascade.sql');
$migrate->run(__DIR__ . '/003_add_users_multitenancy.sql');
