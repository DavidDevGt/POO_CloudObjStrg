<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use Models\AutoDelete;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Tests\TestCase;

class AutoDeleteTest extends TestCase
{
    private PDO        $pdo;
    private string     $tmpDir;
    private AutoDelete $autoDelete;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/autodelete_test_' . uniqid() . '/';
        mkdir($this->tmpDir, 0755, true);

        $this->pdo = $this->createMock(PDO::class);
        $this->autoDelete = new AutoDelete($this->pdo, $this->tmpDir);
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

    public function testDeleteExpiredDocumentsCommitsTransaction(): void
    {
        $selectStmt = $this->createMock(PDOStatement::class);
        $selectStmt->method('execute')->willReturn(true);
        $selectStmt->method('fetchAll')->willReturn([]); // no expired files

        $updateStmt = $this->createMock(PDOStatement::class);
        $updateStmt->method('execute')->willReturn(true);

        $this->pdo->expects($this->once())->method('beginTransaction');
        $this->pdo->expects($this->once())->method('commit');
        $this->pdo->expects($this->never())->method('rollBack');

        $this->pdo->method('prepare')->willReturn($selectStmt, $updateStmt);
        $this->pdo->method('exec')->willReturn(0);

        $this->autoDelete->deleteExpiredDocuments();
        $this->addToAssertionCount(1);
    }

    public function testDeleteExpiredDocumentsRollsBackOnDbError(): void
    {
        $this->pdo->method('beginTransaction');
        $this->pdo->method('prepare')->willThrowException(new PDOException('Query failed'));
        $this->pdo->expects($this->once())->method('rollBack');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/expired records/');

        $this->autoDelete->deleteExpiredDocuments();
    }

    public function testDeleteExpiredDocumentsRemovesPhysicalFiles(): void
    {
        $filename = 'expired_doc.pdf';
        $filePath = $this->tmpDir . $filename;
        file_put_contents($filePath, '%PDF-1.4');

        $this->assertFileExists($filePath);

        $selectStmt = $this->createMock(PDOStatement::class);
        $selectStmt->method('execute')->willReturn(true);
        $selectStmt->method('fetchAll')->willReturn([$filename]);

        $updateStmt = $this->createMock(PDOStatement::class);
        $updateStmt->method('execute')->willReturn(true);

        $this->pdo->method('beginTransaction');
        $this->pdo->method('commit');
        $this->pdo->method('prepare')->willReturn($selectStmt, $updateStmt);
        $this->pdo->method('exec')->willReturn(0);

        $this->autoDelete->deleteExpiredDocuments();

        $this->assertFileDoesNotExist($filePath);
    }

    public function testDeleteDoesNotFailIfPhysicalFileMissing(): void
    {
        $selectStmt = $this->createMock(PDOStatement::class);
        $selectStmt->method('execute')->willReturn(true);
        $selectStmt->method('fetchAll')->willReturn(['ghost_file_that_does_not_exist.pdf']);

        $updateStmt = $this->createMock(PDOStatement::class);
        $updateStmt->method('execute')->willReturn(true);

        $this->pdo->method('beginTransaction');
        $this->pdo->method('commit');
        $this->pdo->method('prepare')->willReturn($selectStmt, $updateStmt);
        $this->pdo->method('exec')->willReturn(0);

        // Must not throw even though the file does not exist.
        $this->autoDelete->deleteExpiredDocuments();
        $this->addToAssertionCount(1);
    }
}
