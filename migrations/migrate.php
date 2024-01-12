<?php

require_once '../config/Database.php';

use Config\Database;

class Migrate {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getDBConection();
    }

    public function executeMigration($filePath) {
        try {
            $sql = file_get_contents($filePath);
            $this->db->exec($sql);
            echo "Migración ejecutada con éxito: " . basename($filePath) . "\n";
        } catch (PDOException  $e) {
            echo "Error de migración: " . $e->getMessage() . "\n";
        }
    }

}

// Echarse la migración como si fueras mexicano en la frontera :v
// Si pasa o no pasa solo Dios lo sabe

$migrate = new Migrate();
$migrate->executeMigration("migrations/000_initialize_database.sql");
