<?php

declare(strict_types=1);

namespace Models;

use Config\Database;
use PDO;
use PDOException;
use RuntimeException;

class User
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function create(string $email, string $password, ?string $nombre = null): int
    {
        $sql = 'INSERT INTO usuarios (email, password_hash, nombre) VALUES (:email, :hash, :nombre)';
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':email'  => mb_strtolower(trim($email)),
                ':hash'   => $this->hashPassword($password),
                ':nombre' => $nombre,
            ]);
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            throw new RuntimeException('Could not create user: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM usuarios WHERE email = :email AND active = 1 LIMIT 1'
        );
        $stmt->execute([':email' => mb_strtolower(trim($email))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM usuarios WHERE id = :id AND active = 1 LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function verifyPassword(array $user, string $password): bool
    {
        return password_verify($password, $user['password_hash']);
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM usuarios WHERE email = :email LIMIT 1'
        );
        $stmt->execute([':email' => mb_strtolower(trim($email))]);
        return $stmt->fetchColumn() !== false;
    }

    private function hashPassword(string $password): string
    {
        $cost = (int) ($_ENV['BCRYPT_COST'] ?? 12);
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost]);
    }
}
