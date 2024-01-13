<?php

namespace Models;

use Config\Database;
use Exception;
use PDOException;

class SignDocument
{
    private $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getDBConection();
    }

    public function saveSignature($documentoId, $firmaData)
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO firmas (documento_id, firma_data) VALUES (:documento_id, :firma_data)");
            $stmt->bindParam(":documento_id", $documentoId);
            $stmt->bindParam(":firma_data", $firmaData);
            $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error al guardar la firma: " . $e->getMessage());
        }
    }
}
