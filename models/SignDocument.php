<?php

declare(strict_types=1);

namespace Models;

use Config\Database;
use PDO;
use PDOException;
use RuntimeException;

class SignDocument
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function saveSignature(int $documentId, string $signatureData): int
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO firmas (documento_id, firma_data) VALUES (:documento_id, :firma_data)'
            );
            $stmt->execute([
                ':documento_id' => $documentId,
                ':firma_data'   => $signatureData,
            ]);

            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            throw new RuntimeException('Failed to save signature.', 0, $e);
        }
    }
}
