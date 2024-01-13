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

    public function createEncodedShortUrl($documentoId, $urlBase)
    {
        try {
            $enlaceCodificado = $this->encodeDocumentId($documentoId);
            $enlaceCompleto = $urlBase . '?id=' . $enlaceCodificado;
            $stmt = $this->db->prepare("INSERT INTO enlaces_cortos (documento_id, enlace) VALUES (:documento_id, :enlace)");
            $stmt->bindParam(":documento_id", $documentoId);
            $stmt->bindParam(":enlace", $enlaceCompleto);
            $stmt->execute();
            return $enlaceCompleto;
        } catch (PDOException $e) {
            throw new Exception("Error al crear el enlace corto: " . $e->getMessage());
        }
    }

    public function obtenerUrlBaseActual()
    {
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        return "$scheme://$host";
    }

    private function encodeDocumentId($documentoId)
    {

        $encodedId = base64_encode(hex2bin($documentoId));
        $encodedId = preg_replace('/[^a-zA-Z0-9]/', '', $encodedId); // Remove non-alphanumeric characters

        // Limit the encoded ID to 8 to 10 characters
        $encodedId = substr($encodedId, 0, rand(8, 10));

        return $encodedId;
    }
}
