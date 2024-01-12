<?php

namespace Models;

use Config\Database;
use Exception;
use PDOException;

class Upload
{
    private $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getDBConection();
    }

    public function uploadFile($file)
    {
        // TODO - Upload file to server
        $targetDir = "../uploads/";
        $targetFile = $targetDir . basename($file["name"]);
        $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

        // Revisar si el archivo es un PDF
        if ($fileType != "pdf") {
            throw new Exception("El archivo que quieres subir no es un PDF");
        }

        // Revisar si el archivo ya existe
        if (file_exists($targetFile)) {
            throw new Exception("El archivo que quieres subir ya existe");
        }

        // Revisar el tamaño del archivo (5MB)
        if ($file["size"] > 5000000) {
            throw new Exception("El archivo que quieres subir es demasiado grande");
        }

        // Subir el archivo
        if (move_uploaded_file($file["tmp_name"], $targetFile)) {
            $this->saveMetadata($file["name"], $targetFile);
            return true;
        } else {
            throw new Exception("Hubo un error al subir el archivo.");
        }
    }

    public function saveMetadata($fileName, $filePath)
    {
        // TODO - Save metadata to database
        try {
            $stmt = $this->db->prepare("INSERT INTO documentos (nombre, ruta) VALUES (:nombre, :ruta)");
            $stmt->bindParam(":nombre", $fileName);
            $stmt->bindParam(":ruta", $filePath);
            $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error al guardar metadatos: " . $e->getMessage());
        }
    }
}
