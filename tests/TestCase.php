<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as PhpUnitTestCase;

abstract class TestCase extends PhpUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clean session state between tests so CSRF tokens don't bleed.
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        // Mockery expectations are verified on tearDown.
        if (class_exists(\Mockery::class)) {
            \Mockery::close();
        }
        $_SESSION = [];
        parent::tearDown();
    }

    /**
     * Creates a temporary PDF-like file (starts with PDF magic bytes).
     * Caller is responsible for unlinking the file.
     */
    protected function makeTempPdf(int $sizeBytes = 1024): string
    {
        $path = tempnam(sys_get_temp_dir(), 'phpunit_pdf_');
        // Minimal PDF magic header so finfo returns application/pdf
        $content = "%PDF-1.4\n" . str_repeat('x', max(0, $sizeBytes - 9));
        file_put_contents($path, $content);
        return $path;
    }

    /**
     * Creates a temporary non-PDF file.
     */
    protected function makeTempText(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'phpunit_txt_');
        file_put_contents($path, 'This is not a PDF.');
        return $path;
    }
}
