<?php

declare(strict_types=1);

namespace Tests\Integration;

use Config\Database;
use Models\AutoDelete;
use Models\Document;
use Models\SignDocument;
use Models\Upload;
use Models\UrlShortener;
use Tests\TestCase;

/**
 * Integration tests — require a running MySQL instance with the test database.
 *
 * Setup before running:
 *   mysql -u root -e "CREATE DATABASE IF NOT EXISTS pdf_store_test;"
 *   mysql -u root pdf_store_test < migrations/001_create_tables.sql
 *   mysql -u root pdf_store_test < migrations/002_add_indexes_cascade.sql
 *
 * Run with:
 *   composer test:integration
 *
 * @group integration
 */
class UploadFlowTest extends TestCase
{
    private \PDO   $db;
    private string $tmpDir;
    private string $testFile;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->db = Database::getConnection();
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Integration DB not available: ' . $e->getMessage());
        }

        $this->tmpDir   = sys_get_temp_dir() . '/integration_test_' . uniqid() . '/';
        mkdir($this->tmpDir, 0755, true);

        // Create a minimal valid PDF file.
        $this->testFile = $this->tmpDir . 'sample.pdf';
        file_put_contents($this->testFile, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        foreach (glob($this->tmpDir . '*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->tmpDir);
        parent::tearDown();
    }

    // ── Full upload → findBySlug → isExpired flow ─────────────────────────

    public function testFullUploadAndRetrievalFlow(): void
    {
        // 1. Simulate saving metadata directly (upload() needs move_uploaded_file).
        $upload     = new Upload($this->db, $this->tmpDir);
        $storedName = bin2hex(random_bytes(16)) . '.pdf';

        // Copy test file as if it were already moved.
        copy($this->testFile, $this->tmpDir . $storedName);

        $saveMetadata = new \ReflectionMethod(Upload::class, 'saveMetadata');
        $saveMetadata->setAccessible(true);
        $documentId = $saveMetadata->invoke($upload, 'integration_test.pdf', $storedName);

        $this->assertIsInt($documentId);
        $this->assertGreaterThan(0, $documentId);

        // 2. Create a short URL for the document.
        $shortener = new UrlShortener($this->db);
        $shortUrl  = $shortener->createShortUrl($documentId, 'http://localhost/public');

        $this->assertStringContainsString('/edit_pdf.php?id=', $shortUrl);

        // 3. Extract slug and retrieve the document.
        parse_str(parse_url($shortUrl, PHP_URL_QUERY), $params);
        $slug = $params['id'];

        $docModel = new Document($this->db, $this->tmpDir);
        $doc      = $docModel->findBySlug($slug);

        $this->assertNotNull($doc);
        $this->assertEquals($documentId, (int) $doc['id']);
        $this->assertEquals('integration_test.pdf', $doc['nombre']);
    }

    public function testFindBySlugReturnsNullForUnknownSlug(): void
    {
        $docModel = new Document($this->db, $this->tmpDir);
        $result   = $docModel->findBySlug('0000000000000000');

        $this->assertNull($result);
    }

    public function testSignatureIsSavedAndLinkedToDocument(): void
    {
        // Create a document row first.
        $stmt = $this->db->prepare('INSERT INTO documentos (nombre, ruta) VALUES (:n, :r)');
        $stmt->execute([':n' => 'sign_test.pdf', ':r' => 'sign_test_stored.pdf']);
        $docId = (int) $this->db->lastInsertId();

        $signDoc   = new SignDocument($this->db);
        $signData  = 'data:image/png;base64,' . base64_encode('fake-sig-data');
        $signatureId = $signDoc->saveSignature($docId, $signData);

        $this->assertGreaterThan(0, $signatureId);

        // Verify persistence.
        $check = $this->db->prepare('SELECT documento_id FROM firmas WHERE id = :id');
        $check->execute([':id' => $signatureId]);
        $row = $check->fetch();

        $this->assertEquals($docId, (int) $row['documento_id']);
    }

    public function testAutoDeleteDeactivatesOldDocuments(): void
    {
        // Insert a document with an old timestamp.
        $this->db->exec(
            "INSERT INTO documentos (nombre, ruta, fecha_subida)
             VALUES ('old.pdf', 'old_stored.pdf', DATE_SUB(NOW(), INTERVAL 48 HOUR))"
        );
        $oldId = (int) $this->db->lastInsertId();

        $autoDelete = new AutoDelete($this->db, $this->tmpDir);
        $autoDelete->deleteExpiredDocuments();

        $stmt = $this->db->prepare('SELECT active FROM documentos WHERE id = :id');
        $stmt->execute([':id' => $oldId]);
        $row = $stmt->fetch();

        $this->assertSame(0, (int) $row['active']);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function cleanDatabase(): void
    {
        try {
            // Delete in FK-safe order (children first).
            $this->db->exec('DELETE FROM acciones');
            $this->db->exec('DELETE FROM firmas');
            $this->db->exec('DELETE FROM enlaces_cortos');
            $this->db->exec('DELETE FROM documentos');
        } catch (\Throwable) {
            // Best-effort cleanup.
        }
    }
}
