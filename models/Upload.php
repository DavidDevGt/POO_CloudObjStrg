<?php

namespace Models;

use Config\Database;

class Upload {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getDBConection();
    }

    public function uploadFile($file) {
        // TODO - Upload file to server
        $targetDir = "../uploads/";
        $targetFile = $targetDir . basename($file["name"]);
        $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

        // Revisar si el archivo es un PDF
        if ($fileType != "pdf") {
            throw new \Exception("El archivo que quieres subir no es un PDF");
        }

        // Revisar si el archivo ya existe
        if (file_exists($targetFile)) {
            throw new \Exception("El archivo que quieres subir ya existe");
        }
    }

    public function saveMetadata() {
        // TODO - Save metadata to database
    }
}