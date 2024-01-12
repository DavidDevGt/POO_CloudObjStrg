<?php

namespace Config;

use PDO;
use PDOException;

class Database
{
    private $host = "localhost";
    private $db_name = "pdf_store";
    private $username = "root";
    private $password = "";
    private $conn;

    public function getDBConection()
    {
        $this->conn = null;
        try {
            // Conexión inicial al servidor MySQL
            $this->conn = new PDO("mysql:host=" . $this->host, $this->username, $this->password);
            $this->conn->exec("set names utf8");

            // Crear la base de datos si no existe y reconectar
            $this->conn->exec("CREATE DATABASE IF NOT EXISTS `$this->db_name`;
                               USE `$this->db_name`;");
        } catch (PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
