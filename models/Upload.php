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
    }

    public function saveMetadata() {
        // TODO - Save metadata to database
    }
}