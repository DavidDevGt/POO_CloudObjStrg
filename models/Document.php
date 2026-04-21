<?php

declare(strict_types=1);

namespace Models;

use Config\Database;
use PDO;
use PDOException;
use RuntimeException;

class Document
{
    private PDO    $db;
    private string $uploadDir;

    public function __construct(?PDO $db = null, ?string $uploadDir = null)
    {
        $this->db        = $db ?? Database::getConnection();
        $this->uploadDir = $uploadDir ?? dirname(__DIR__) . '/uploads/';
    }

    /**
     * Fetches a document + its link record by the short URL slug.
     * Returns null when not found, inactive, or expired.
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT d.id, d.nombre, d.ruta, d.active, d.fecha_subida,
                    e.id          AS link_id,
                    e.enlace,
                    e.active      AS link_active,
                    e.fecha_expiracion,
                    e.slug
             FROM   enlaces_cortos e
             JOIN   documentos d ON d.id = e.documento_id
             WHERE  e.slug    = :slug
               AND  e.active  = TRUE
               AND  d.active  = TRUE
             LIMIT  1"
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function isExpired(array $document): bool
    {
        if ($document['fecha_expiracion'] === null) {
            return false;
        }

        return (new \DateTimeImmutable()) > (new \DateTimeImmutable($document['fecha_expiracion']));
    }

    /**
     * Returns the absolute path to the stored file.
     *
     * @throws RuntimeException when the file is missing on disk.
     */
    public function getFilePath(array $document): string
    {
        $path = $this->uploadDir . basename($document['ruta']);

        if (!file_exists($path)) {
            throw new RuntimeException('Physical file not found.');
        }

        return $path;
    }

    /**
     * Writes response headers and streams the PDF to the client.
     */
    public function serveFile(array $document): void
    {
        $path = $this->getFilePath($document);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . rawurlencode($document['nombre']) . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');

        readfile($path);
    }

    /**
     * Appends an access log row to the acciones table.
     *
     * @param string $accion  One of: descargar, subir, firmar, eliminar
     */
    public function logAccess(int $documentId, string $accion): void
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO acciones (documento_id, accion) VALUES (:documento_id, :accion)'
            );
            $stmt->execute([
                ':documento_id' => $documentId,
                ':accion'       => $accion,
            ]);
        } catch (PDOException $e) {
            // Audit failures must not break the main flow; log and continue.
            error_log('[Document::logAccess] ' . $e->getMessage());
        }
    }
}
