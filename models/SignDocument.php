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

    private const MAX_SIGNATURE_BYTES = 2_097_152; // 2 MB base64 cap

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Persists a base64-encoded PNG signature for a document.
     *
     * @throws RuntimeException on validation or DB failure.
     * @return int  The new signature ID.
     */
    public function saveSignature(int $documentId, string $signatureData): int
    {
        $this->validateSignatureData($signatureData);

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO firmas (documento_id, firma_data)
                 VALUES (:documento_id, :firma_data)'
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

    private function validateSignatureData(string $data): void
    {
        if (strlen($data) > self::MAX_SIGNATURE_BYTES) {
            throw new RuntimeException('Signature data exceeds the 2 MB limit.');
        }

        if (!str_starts_with($data, 'data:image/png;base64,')) {
            throw new RuntimeException('Invalid signature format. Expected a PNG data URL.');
        }
    }
}
