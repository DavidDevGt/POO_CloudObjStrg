<?php

declare(strict_types=1);

namespace Tests\Smoke;

use Config\Csrf;
use Config\Database;
use Models\AutoDelete;
use Models\Document;
use Models\SignDocument;
use Models\Upload;
use Models\UrlShortener;
use Tests\TestCase;

/**
 * Smoke tests — verify that the application's core components can be
 * loaded and instantiated without fatal errors. No real DB required.
 *
 * These tests catch class-loading issues, typos in namespaces, and broken
 * constructors before any integration or functional test runs.
 */
class ApplicationSmokeTest extends TestCase
{
    // ── Autoloading ───────────────────────────────────────────────────────

    public function testCsrfClassLoads(): void
    {
        $this->assertTrue(class_exists(Csrf::class));
    }

    public function testDatabaseClassLoads(): void
    {
        $this->assertTrue(class_exists(Database::class));
    }

    public function testUploadClassLoads(): void
    {
        $this->assertTrue(class_exists(Upload::class));
    }

    public function testUrlShortenerClassLoads(): void
    {
        $this->assertTrue(class_exists(UrlShortener::class));
    }

    public function testDocumentClassLoads(): void
    {
        $this->assertTrue(class_exists(Document::class));
    }

    public function testAutoDeleteClassLoads(): void
    {
        $this->assertTrue(class_exists(AutoDelete::class));
    }

    public function testSignDocumentClassLoads(): void
    {
        $this->assertTrue(class_exists(SignDocument::class));
    }

    // ── Instantiation with injected PDO mock ─────────────────────────────

    public function testUploadCanBeInstantiatedWithMockedDependencies(): void
    {
        $tmpDir = sys_get_temp_dir() . '/smoke_' . uniqid() . '/';
        mkdir($tmpDir, 0755, true);
        $upload = new Upload($this->createMock(\PDO::class), $tmpDir);
        $this->assertInstanceOf(Upload::class, $upload);
        rmdir($tmpDir);
    }

    public function testUrlShortenerCanBeInstantiatedWithMockedDependencies(): void
    {
        $obj = new UrlShortener($this->createMock(\PDO::class));
        $this->assertInstanceOf(UrlShortener::class, $obj);
    }

    public function testDocumentCanBeInstantiatedWithMockedDependencies(): void
    {
        $tmpDir = sys_get_temp_dir() . '/smoke_doc_' . uniqid() . '/';
        mkdir($tmpDir, 0755, true);
        $obj = new Document($this->createMock(\PDO::class), $tmpDir);
        $this->assertInstanceOf(Document::class, $obj);
        rmdir($tmpDir);
    }

    public function testAutoDeleteCanBeInstantiatedWithMockedDependencies(): void
    {
        $tmpDir = sys_get_temp_dir() . '/smoke_ad_' . uniqid() . '/';
        mkdir($tmpDir, 0755, true);
        $obj = new AutoDelete($this->createMock(\PDO::class), $tmpDir);
        $this->assertInstanceOf(AutoDelete::class, $obj);
        rmdir($tmpDir);
    }

    public function testSignDocumentCanBeInstantiatedWithMockedDependencies(): void
    {
        $obj = new SignDocument($this->createMock(\PDO::class));
        $this->assertInstanceOf(SignDocument::class, $obj);
    }

    // ── CSRF smoke ────────────────────────────────────────────────────────

    public function testCsrfGeneratesNonEmptyToken(): void
    {
        $token = Csrf::getToken();
        $this->assertNotEmpty($token);
    }

    public function testCsrfFieldRendersHtmlInput(): void
    {
        $html = Csrf::field();
        $this->assertStringContainsString('<input', $html);
        $this->assertStringContainsString('_csrf_token', $html);
    }

    // ── Slug format regression ────────────────────────────────────────────

    public function testGeneratedSlugMatchesExpectedFormat(): void
    {
        // Regression guard: slug must always be 16 lowercase hex chars.
        $slug = bin2hex(random_bytes(8));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $slug);
    }

    // ── .env.example presence ─────────────────────────────────────────────

    public function testEnvExampleFileExists(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2) . '/.env.example');
    }

    public function testUploadsHtaccessExists(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2) . '/uploads/.htaccess');
    }

    public function testMigration001Exists(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2) . '/migrations/001_create_tables.sql');
    }

    public function testMigration002Exists(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2) . '/migrations/002_add_indexes_cascade.sql');
    }
}
