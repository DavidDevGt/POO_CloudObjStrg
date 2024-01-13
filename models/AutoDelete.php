<?php

namespace Models;

use Config\Database;
use Exception;
use PDOException;

class AutoDelete
{
    private $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getDBConection();
    }

    public function deleteExpiredDocuments()
    {
        try {
            $this->db->exec("UPDATE documentos SET active = FALSE WHERE fecha_subida < NOW() - INTERVAL 12 HOUR");
            $this->db->exec("UPDATE enlaces_cortos SET active = FALSE WHERE fecha_expiracion < NOW()");
        } catch (PDOException $e) {
            throw new Exception("Error al eliminar documentos o enlaces caducados: " . $e->getMessage());
        }
    }
}
