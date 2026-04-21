<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use Models\SignDocument;
use PDO;
use PDOStatement;
use RuntimeException;
use Tests\TestCase;

class SignDocumentTest extends TestCase
{
    private PDO          $pdo;
    private PDOStatement $stmt;
    private SignDocument $signDoc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo     = $this->createMock(PDO::class);
        $this->stmt    = $this->createMock(PDOStatement::class);
        $this->signDoc = new SignDocument($this->pdo);
    }

    public function testSaveSignatureReturnsInsertedId(): void
    {
        $this->stmt->expects($this->once())->method('execute')->willReturn(true);
        $this->pdo->method('prepare')->willReturn($this->stmt);
        $this->pdo->method('lastInsertId')->willReturn('99');

        $id = $this->signDoc->saveSignature(1, 'data:image/png;base64,' . base64_encode('fake'));

        $this->assertSame(99, $id);
    }

    public function testSaveSignatureThrowsOnInvalidFormat(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/PNG data URL/');

        $this->signDoc->saveSignature(1, 'not-a-valid-data-url');
    }

    public function testSaveSignatureThrowsOnOversizedPayload(): void
    {
        $huge = 'data:image/png;base64,' . str_repeat('A', 2_097_153);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/2 MB/');

        $this->signDoc->saveSignature(1, $huge);
    }

    public function testSaveSignatureThrowsOnDbError(): void
    {
        $this->stmt->method('execute')->willThrowException(new \PDOException('DB down'));
        $this->pdo->method('prepare')->willReturn($this->stmt);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to save signature/');

        $this->signDoc->saveSignature(1, 'data:image/png;base64,' . base64_encode('x'));
    }

    public function testValidSignaturePrefixIsAccepted(): void
    {
        $this->stmt->method('execute')->willReturn(true);
        $this->pdo->method('prepare')->willReturn($this->stmt);
        $this->pdo->method('lastInsertId')->willReturn('1');

        $validData = 'data:image/png;base64,' . base64_encode(str_repeat('x', 100));
        $id = $this->signDoc->saveSignature(5, $validData);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }
}
