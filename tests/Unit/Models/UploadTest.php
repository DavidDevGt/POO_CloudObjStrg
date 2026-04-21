<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use Models\Upload;
use PDO;
use PDOStatement;
use RuntimeException;
use Tests\TestCase;

class UploadTest extends TestCase
{
    private PDO          $pdo;
    private PDOStatement $stmt;
    private string       $tmpDir;
    private Upload       $upload;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . '/upload_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);

        $this->pdo  = $this->createMock(PDO::class);
        $this->stmt = $this->createMock(PDOStatement::class);

        $this->upload = new Upload($this->pdo, $this->tmpDir . '/');
    }

    protected function tearDown(): void
    {
        // Clean up temp dir.
        foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->tmpDir);
        parent::tearDown();
    }

    // ── generateStoredName ────────────────────────────────────────────────

    public function testGenerateStoredNameReturnsPdfExtension(): void
    {
        $name = $this->invokePrivate('generateStoredName');

        $this->assertStringEndsWith('.pdf', $name);
    }

    public function testGenerateStoredNameIs32HexCharsPlusDotPdf(): void
    {
        $name = $this->invokePrivate('generateStoredName');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}\.pdf$/', $name);
    }

    public function testGenerateStoredNameIsUniqueAcrossCalls(): void
    {
        $a = $this->invokePrivate('generateStoredName');
        $b = $this->invokePrivate('generateStoredName');

        $this->assertNotEquals($a, $b);
    }

    // ── validate — upload error ───────────────────────────────────────────

    public function testValidateThrowsOnUploadError(): void
    {
        $file = $this->buildFile(['error' => UPLOAD_ERR_INI_SIZE, 'tmp_name' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Upload error/');

        $this->invokePrivate('validate', $file);
    }

    // ── validate — MIME type ─────────────────────────────────────────────

    public function testValidateThrowsOnNonPdfMimeType(): void
    {
        $tmpFile = $this->makeTempText();
        $file    = $this->buildFile(['tmp_name' => $tmpFile, 'size' => 100]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/PDF/');

        try {
            $this->invokePrivate('validate', $file);
        } finally {
            @unlink($tmpFile);
        }
    }

    // ── validate — file size ─────────────────────────────────────────────

    public function testValidateThrowsWhenFileTooLarge(): void
    {
        $tmpFile = $this->makeTempPdf(6_000_000); // 6 MB — over the 5 MB limit
        $file    = $this->buildFile(['tmp_name' => $tmpFile, 'size' => 6_000_000]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/MB/');

        try {
            $this->invokePrivate('validate', $file);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testValidatePassesForValidSmallPdf(): void
    {
        $tmpFile = $this->makeTempPdf(1024);
        $file    = $this->buildFile(['tmp_name' => $tmpFile, 'size' => 1024]);

        // No exception expected.
        $this->invokePrivate('validate', $file);
        $this->addToAssertionCount(1); // explicit assertion to silence risky-test warning

        @unlink($tmpFile);
    }

    // ── saveMetadata ─────────────────────────────────────────────────────

    public function testSaveMetadataTruncatesLongNames(): void
    {
        $longName = str_repeat('a', 300); // exceeds VARCHAR(255)

        $this->stmt->expects($this->once())->method('execute')->with(
            $this->callback(fn(array $p) => mb_strlen($p[':nombre']) <= 255)
        )->willReturn(true);

        $this->pdo->method('prepare')->willReturn($this->stmt);
        $this->pdo->method('lastInsertId')->willReturn('42');

        $id = $this->invokePrivate('saveMetadata', $longName, 'stored.pdf');

        $this->assertSame(42, $id);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function buildFile(array $overrides = []): array
    {
        return array_merge([
            'error'    => UPLOAD_ERR_OK,
            'tmp_name' => '',
            'size'     => 512,
            'name'     => 'document.pdf',
        ], $overrides);
    }

    private function invokePrivate(string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionMethod(Upload::class, $method);
        $ref->setAccessible(true);
        return $ref->invoke($this->upload, ...$args);
    }
}
