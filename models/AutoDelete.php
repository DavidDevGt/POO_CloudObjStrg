<?php

declare(strict_types=1);

namespace Models;

use Config\Database;
use PDO;
use PDOException;
use RuntimeException;

class AutoDelete
{
    private PDO    $db;
    private string $uploadDir;
    private int    $deleteIntervalHours;

    public function __construct(?PDO $db = null, ?string $uploadDir = null)
    {
        $this->db                  = $db ?? Database::getConnection();
        $this->uploadDir           = $uploadDir ?? dirname(__DIR__) . '/uploads/';
        $this->deleteIntervalHours = (int) ($_ENV['AUTO_DELETE_HOURS'] ?? 12);
    }

    /**
     * Deactivates expired documents and links, then removes physical files.
     * The DB update is wrapped in a transaction; file deletion runs after
     * a successful commit so the disk and DB stay in sync.
     */
    public function deleteExpiredDocuments(): void
    {
        try {
            $this->db->beginTransaction();

            // Collect file paths before marking inactive.
            $select = $this->db->prepare(
                "SELECT ruta FROM documentos
                 WHERE  active = TRUE
                   AND  fecha_subida < DATE_SUB(NOW(), INTERVAL :hours HOUR)"
            );
            $select->execute([':hours' => $this->deleteIntervalHours]);
            $expiredPaths = $select->fetchAll(PDO::FETCH_COLUMN, 0);

            // Deactivate expired documents.
            $updateDocs = $this->db->prepare(
                "UPDATE documentos SET active = FALSE
                 WHERE  active = TRUE
                   AND  fecha_subida < DATE_SUB(NOW(), INTERVAL :hours HOUR)"
            );
            $updateDocs->execute([':hours' => $this->deleteIntervalHours]);

            // Deactivate expired short links.
            $this->db->exec(
                "UPDATE enlaces_cortos SET active = FALSE
                 WHERE  active = TRUE AND fecha_expiracion < NOW()"
            );

            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new RuntimeException('Failed to deactivate expired records.', 0, $e);
        }

        // Physical deletion runs outside the transaction: if unlink() fails the
        // record is already inactive, so the file simply becomes an orphan that
        // a future run can clean up — no rollback risk.
        foreach ($expiredPaths as $ruta) {
            $filePath = $this->uploadDir . basename($ruta);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }
}
