<?php

declare(strict_types=1);

namespace Models;

use Config\Database;
use PDO;
use PDOException;
use RuntimeException;

class AutoDelete
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function deleteExpiredDocuments(): void
    {
        try {
            $this->db->beginTransaction();

            $this->db->exec(
                "UPDATE documentos SET active = FALSE
                 WHERE active = TRUE AND fecha_subida < NOW() - INTERVAL 12 HOUR"
            );
            $this->db->exec(
                "UPDATE enlaces_cortos SET active = FALSE
                 WHERE active = TRUE AND fecha_expiracion < NOW()"
            );

            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new RuntimeException(
                'Failed to deactivate expired records.',
                0,
                $e
            );
        }
    }
}
