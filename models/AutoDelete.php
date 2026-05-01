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
    private ?int   $userId;

    public function __construct(?PDO $db = null, ?string $uploadDir = null, ?int $userId = null)
    {
        $this->db = $db ?? Database::getConnection();
        $this->uploadDir = $uploadDir ?? dirname(__DIR__) . '/uploads/';
        $this->deleteIntervalHours = (int) ($_ENV['AUTO_DELETE_HOURS'] ?? 12);
        $this->userId = $userId;
    }

    /**
     * Deactivates expired documents and links, then removes physical files.
     * The DB update is wrapped in a transaction; file deletion runs after
     * a successful commit so the disk and DB stay in sync.
     */
    public function deleteExpiredDocuments(): void
    {
        $userFilter = $this->userId !== null ? 'AND user_id = :user_id' : '';

        try {
            $this->db->beginTransaction();

            // Collect file paths before marking inactive.
            $selectSql = "SELECT ruta FROM documentos
                          WHERE  active = TRUE
                            AND  fecha_subida < DATE_SUB(NOW(), INTERVAL :hours HOUR)
                          {$userFilter}";
            $select = $this->db->prepare($selectSql);
            $params = [':hours' => $this->deleteIntervalHours];
            if ($this->userId !== null) {
                $params[':user_id'] = $this->userId;
            }
            $select->execute($params);
            $expiredPaths = $select->fetchAll(PDO::FETCH_COLUMN, 0);

            // Deactivate expired documents.
            $updateSql = "UPDATE documentos SET active = FALSE
                          WHERE  active = TRUE
                            AND  fecha_subida < DATE_SUB(NOW(), INTERVAL :hours HOUR)
                          {$userFilter}";
            $updateDocs = $this->db->prepare($updateSql);
            $updateDocs->execute($params);

            // Deactivate expired short links.
            $this->db->exec(
                'UPDATE enlaces_cortos SET active = FALSE
                 WHERE  active = TRUE AND fecha_expiracion < NOW()'
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
