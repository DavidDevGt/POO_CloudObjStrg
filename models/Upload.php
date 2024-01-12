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
        // TODO - Cambiar el directorio en producción, fuera de la carpeta del proyecto
        $targetDir = "../uploads/";
        $fileType = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

        // Validar tipo de archivo
        if ($fileType != "pdf") {
            throw new Exception("Solo se permiten archivos PDF.");
        }

        // Validar tamaño del archivo (5MB)
        if ($file["size"] > 5000000) {
            throw new Exception("El archivo es demasiado grande.");
        }

        // Generar un nombre de archivo único
        $newFileName = $this->generateUniqueFileName($file["name"]);
        $targetFile = $targetDir . $newFileName;

        // Subir el archivo
        if (move_uploaded_file($file["tmp_name"], $targetFile)) {
            $this->saveMetadata($newFileName, $targetFile);
            return true;
        } else {
            throw new Exception("Error al subir el archivo.");
        }
    }

    private function generateUniqueFileName($fileName)
    {
        $uniquePrefix = uniqid();
        return $uniquePrefix . '_' . basename($fileName);
    }

    public function saveMetadata($fileName, $filePath)
    {
        // TODO - Mejorar 
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
