<?php

namespace Models;

use Config\Database;
use Exception;
use PDOException;

class UrlShortener
{
    private $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getDBConection();
    }

    public function createShortUrl($documentoId, $urlBase)
    {
        try {
            $enlace = bin2hex(random_bytes(6));
            $stmt = $this->db->prepare("INSERT INTO enlaces_cortos (documento_id, enlace) VALUES (:documento_id, CONCAT(:urlBase, :enlace))");
            $stmt->bindParam(":documento_id", $documentoId);
            $stmt->bindParam(":urlBase", $urlBase);
            $stmt->bindParam(":enlace", $enlace);
            $stmt->execute();
        } catch (PDOException  $e) {
            throw new Exception("Error al crear el enlace corto: " . $e->getMessage());
        }
    }
}
