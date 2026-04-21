<?php

declare(strict_types=1);

namespace Models;

use Config\Database;
use finfo;
use PDO;
use RuntimeException;

class Upload
{
    private PDO    $db;
    private string $uploadDir;

    private const ALLOWED_MIME   = 'application/pdf';
    private const FILENAME_BYTES = 16;

    public function __construct(?PDO $db = null, ?string $uploadDir = null)
    {
        $this->db        = $db ?? Database::getConnection();
        $this->uploadDir = $uploadDir ?? dirname(__DIR__) . '/uploads/';

        if (!is_dir($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0755, true) && !is_dir($this->uploadDir)) {
                throw new RuntimeException('Could not create upload directory.');
            }
        }
    }

    /**
     * Validates, moves the file, and persists metadata inside a single transaction.
     *
     * @throws RuntimeException on any validation or I/O failure.
     * @return int  The new document ID.
     */
    public function upload(array $file): int
    {
        $this->validate($file);

        $storedName  = $this->generateStoredName();
        $destination = $this->uploadDir . $storedName;

        $this->db->beginTransaction();
        try {
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new RuntimeException('Failed to move the uploaded file.');
            }

            $documentId = $this->saveMetadata($file['name'], $storedName);
            $this->db->commit();

            return $documentId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            if (file_exists($destination)) {
                unlink($destination);
            }
            throw $e;
        }
    }

    private function validate(array $file): void
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload error, code: ' . $file['error']);
        }

        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if ($mimeType !== self::ALLOWED_MIME) {
            throw new RuntimeException('Only PDF files are allowed.');
        }

        $maxSize = (int) ($_ENV['UPLOAD_MAX_SIZE'] ?? 5_000_000);
        if ($file['size'] > $maxSize) {
            throw new RuntimeException(
                'File exceeds the ' . round($maxSize / 1_000_000) . ' MB size limit.'
            );
        }
    }

    private function generateStoredName(): string
    {
        return bin2hex(random_bytes(self::FILENAME_BYTES)) . '.pdf';
    }

    private function saveMetadata(string $originalName, string $storedName): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO documentos (nombre, ruta) VALUES (:nombre, :ruta)'
        );
        $stmt->execute([
            ':nombre' => mb_substr($originalName, 0, 255),
            ':ruta'   => $storedName,
        ]);

        return (int) $this->db->lastInsertId();
    }
}
