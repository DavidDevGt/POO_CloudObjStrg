<?php

namespace Config;

use PDO;
use PDOException;

class Database {
    private $host = "localhost";
    private $db_name = "pdf_store";
    private $username = "root";
    private $password = "";
    private $conn;

    public function getDBConection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
        } catch (PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }
        return $this->conn;
    }
}