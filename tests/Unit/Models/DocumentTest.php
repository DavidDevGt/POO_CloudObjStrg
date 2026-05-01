<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use Models\Document;
use PDO;
use PDOStatement;
use RuntimeException;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    private PDO          $pdo;
    private PDOStatement $stmt;
    private string       $tmpDir;
    private Document     $document;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/doc_test_' . uniqid() . '/';
        mkdir($this->tmpDir, 0755, true);

        $this->pdo = $this->createMock(PDO::class);
        $this->stmt = $this->createMock(PDOStatement::class);
        $this->document = new Document($this->pdo, $this->tmpDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '*') ?: [] as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
        @rmdir($this->tmpDir);
        parent::tearDown();
    }

    // ── findBySlug ────────────────────────────────────────────────────────

    public function testFindBySlugReturnsDocumentRow(): void
    {
        $expected = ['id' => 1, 'nombre' => 'test.pdf', 'ruta' => 'abc123.pdf',
                     'active' => 1, 'fecha_expiracion' => null, 'slug' => 'abc1234567890123'];

        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('fetch')->willReturn($expected);
        $this->pdo->method('prepare')->willReturn($this->stmt);

        $result = $this->document->findBySlug('abc1234567890123');

        $this->assertSame($expected, $result);
    }

    public function testFindBySlugReturnsNullWhenNotFound(): void
    {
        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('fetch')->willReturn(false);
        $this->pdo->method('prepare')->willReturn($this->stmt);

        $result = $this->document->findBySlug('0000000000000000');

        $this->assertNull($result);
    }

    // ── isExpired ─────────────────────────────────────────────────────────

    public function testIsExpiredReturnsFalseWhenNullExpiration(): void
    {
        $doc = ['fecha_expiracion' => null];

        $this->assertFalse($this->document->isExpired($doc));
    }

    public function testIsExpiredReturnsTrueForPastDate(): void
    {
        $doc = ['fecha_expiracion' => '2000-01-01 00:00:00'];

        $this->assertTrue($this->document->isExpired($doc));
    }

    public function testIsExpiredReturnsFalseForFutureDate(): void
    {
        $doc = ['fecha_expiracion' => '2099-01-01 00:00:00'];

        $this->assertFalse($this->document->isExpired($doc));
    }

    // ── getFilePath ───────────────────────────────────────────────────────

    public function testGetFilePathReturnsCorrectAbsolutePath(): void
    {
        // Create a real temp file to satisfy file_exists().
        $filename = 'abcdef1234567890.pdf';
        file_put_contents($this->tmpDir . $filename, '%PDF-1.4');

        $doc = ['ruta' => $filename];
        $path = $this->document->getFilePath($doc);

        $this->assertSame($this->tmpDir . $filename, $path);
    }

    public function testGetFilePathThrowsWhenFileIsMissing(): void
    {
        $doc = ['ruta' => 'nonexistent_file.pdf'];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found/');

        $this->document->getFilePath($doc);
    }

    // ── logAccess ─────────────────────────────────────────────────────────

    public function testLogAccessExecutesInsert(): void
    {
        $this->stmt->expects($this->once())
            ->method('execute')
            ->with([':documento_id' => 1, ':accion' => 'descargar'])
            ->willReturn(true);

        $this->pdo->method('prepare')->willReturn($this->stmt);

        $this->document->logAccess(1, 'descargar');
        $this->addToAssertionCount(1);
    }

    public function testLogAccessDoesNotThrowOnDbError(): void
    {
        $this->stmt->method('execute')->willThrowException(
            new \PDOException('Connection lost')
        );
        $this->pdo->method('prepare')->willReturn($this->stmt);

        // Should silently swallow the exception and log to error_log.
        $this->document->logAccess(1, 'descargar');
        $this->addToAssertionCount(1);
    }
}
