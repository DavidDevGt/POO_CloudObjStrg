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
    private ?int   $userId;

    public function __construct(?PDO $db = null, ?string $uploadDir = null, ?int $userId = null)
    {
        $this->db = $db ?? Database::getConnection();
        $this->uploadDir = $uploadDir ?? dirname(__DIR__) . '/uploads/';
        $this->userId = $userId;
    }

    /**
     * Fetches a document + its link record by the short URL slug.
     * Returns null when not found, inactive, or expired.
     * No user_id filter — signers access by slug without an account.
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT d.id, d.nombre, d.ruta, d.active, d.fecha_subida,
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
             LIMIT  1'
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Returns all active documents belonging to a user, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listByUser(int $userId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            'SELECT d.id, d.nombre, d.ruta, d.fecha_subida,
                    e.slug, e.enlace, e.fecha_expiracion,
                    (SELECT COUNT(*) FROM firmas f WHERE f.documento_id = d.id) AS firma_count
             FROM   documentos d
             LEFT JOIN enlaces_cortos e ON e.documento_id = d.id AND e.active = TRUE
             WHERE  d.user_id = :user_id
               AND  d.active  = TRUE
             ORDER BY d.fecha_subida DESC
             LIMIT  :lim OFFSET :off'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Soft-deletes a document owned by $ownerId.
     * Returns false if the document doesn't exist or doesn't belong to that user.
     */
    public function deactivate(int $documentId, int $ownerId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE documentos SET active = FALSE
             WHERE id = :id AND user_id = :user_id AND active = TRUE'
        );
        $stmt->execute([':id' => $documentId, ':user_id' => $ownerId]);

        return $stmt->rowCount() > 0;
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
            if ($this->userId !== null) {
                $stmt = $this->db->prepare(
                    'INSERT INTO acciones (documento_id, accion, user_id) VALUES (:documento_id, :accion, :user_id)'
                );
                $stmt->execute([
                    ':documento_id' => $documentId,
                    ':accion' => $accion,
                    ':user_id' => $this->userId,
                ]);
            } else {
                $stmt = $this->db->prepare(
                    'INSERT INTO acciones (documento_id, accion) VALUES (:documento_id, :accion)'
                );
                $stmt->execute([
                    ':documento_id' => $documentId,
                    ':accion' => $accion,
                ]);
            }
        } catch (PDOException $e) {
            // Audit failures must not break the main flow; log and continue.
            error_log('[Document::logAccess] ' . $e->getMessage());
        }
    }
}
