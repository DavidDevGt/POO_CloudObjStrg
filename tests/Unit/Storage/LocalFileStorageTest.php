<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use Storage\LocalFileStorage;
use Tests\TestCase;

class LocalFileStorageTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/lfs_test_' . uniqid() . '/';
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '*') as $f) {
            @unlink($f);
        }
        @rmdir($this->tmpDir);
        parent::tearDown();
    }

    public function testExistsReturnsFalseForMissingFile(): void
    {
        $storage = new LocalFileStorage($this->tmpDir);
        $this->assertFalse($storage->exists('nonexistent.pdf'));
    }

    public function testExistsReturnsTrueForExistingFile(): void
    {
        $storage = new LocalFileStorage($this->tmpDir);
        file_put_contents($this->tmpDir . 'test.pdf', '%PDF-1.4');

        $this->assertTrue($storage->exists('test.pdf'));
    }

    public function testDeleteReturnsTrueForMissingFile(): void
    {
        $storage = new LocalFileStorage($this->tmpDir);
        $this->assertTrue($storage->delete('ghost.pdf'));
    }

    public function testDeleteRemovesExistingFile(): void
    {
        $storage = new LocalFileStorage($this->tmpDir);
        file_put_contents($this->tmpDir . 'to_delete.pdf', 'data');

        $this->assertTrue($storage->delete('to_delete.pdf'));
        $this->assertFalse(file_exists($this->tmpDir . 'to_delete.pdf'));
    }

    public function testGetAbsolutePathReturnsCorrectPath(): void
    {
        $storage = new LocalFileStorage('/var/uploads/');
        $this->assertSame('/var/uploads/abc123.pdf', $storage->getAbsolutePath('abc123.pdf'));
    }
}
