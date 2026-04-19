<?php

declare(strict_types=1);

namespace Models;

use Config\Database;
use PDO;
use RuntimeException;

class UrlShortener
{
    private PDO $db;

    private const SLUG_BYTES   = 8;
    private const MAX_RETRIES  = 5;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function createShortUrl(int $documentId, string $baseUrl): string
    {
        $slug     = $this->generateUniqueSlug();
        $shortUrl = rtrim($baseUrl, '/') . '?id=' . $slug;

        $stmt = $this->db->prepare(
            'INSERT INTO enlaces_cortos (documento_id, enlace) VALUES (:documento_id, :enlace)'
        );
        $stmt->execute([
            ':documento_id' => $documentId,
            ':enlace'       => $shortUrl,
        ]);

        return $shortUrl;
    }

    public function getBaseUrl(): string
    {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'];

        return "{$scheme}://{$host}";
    }

    private function generateUniqueSlug(): string
    {
        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            $slug = bin2hex(random_bytes(self::SLUG_BYTES));

            $stmt = $this->db->prepare(
                "SELECT 1 FROM enlaces_cortos WHERE enlace LIKE :pattern LIMIT 1"
            );
            $stmt->execute([':pattern' => '%?id=' . $slug]);

            if ($stmt->fetchColumn() === false) {
                return $slug;
            }
        }

        throw new RuntimeException(
            'Could not generate a unique URL slug after ' . self::MAX_RETRIES . ' attempts.'
        );
    }
}
