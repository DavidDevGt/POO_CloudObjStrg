<?php

declare(strict_types=1);

namespace Models;

use Config\Database;
use PDO;
use RuntimeException;

class UrlShortener
{
    private PDO $db;

    private const SLUG_BYTES  = 8;   // 16 hex chars — 2^64 collision space
    private const MAX_RETRIES = 5;

    private ?int $userId;

    public function __construct(?PDO $db = null, ?int $userId = null)
    {
        $this->db     = $db ?? Database::getConnection();
        $this->userId = $userId;
    }

    /**
     * Generates a unique slug, builds the short URL pointing to edit_pdf.php,
     * and persists both to the enlaces_cortos table.
     */
    public function createShortUrl(int $documentId, string $baseUrl): string
    {
        $slug     = $this->generateUniqueSlug();
        $shortUrl = rtrim($baseUrl, '/') . '/edit_pdf.php?id=' . $slug;

        $stmt = $this->db->prepare(
            'INSERT INTO enlaces_cortos (documento_id, enlace, slug)
             VALUES (:documento_id, :enlace, :slug)'
        );
        $stmt->execute([
            ':documento_id' => $documentId,
            ':enlace'       => $shortUrl,
            ':slug'         => $slug,
        ]);

        return $shortUrl;
    }

    public function getBaseUrl(): string
    {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return "{$scheme}://{$host}";
    }

    private function generateUniqueSlug(): string
    {
        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            $slug = bin2hex(random_bytes(self::SLUG_BYTES));

            $stmt = $this->db->prepare(
                'SELECT 1 FROM enlaces_cortos WHERE slug = :slug LIMIT 1'
            );
            $stmt->execute([':slug' => $slug]);

            if ($stmt->fetchColumn() === false) {
                return $slug;
            }
        }

        throw new RuntimeException(
            'Could not generate a unique URL slug after ' . self::MAX_RETRIES . ' attempts.'
        );
    }
}
