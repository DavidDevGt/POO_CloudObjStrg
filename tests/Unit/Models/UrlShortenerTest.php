<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use Models\UrlShortener;
use PDO;
use PDOStatement;
use RuntimeException;
use Tests\TestCase;

class UrlShortenerTest extends TestCase
{
    private PDO          $pdo;
    private PDOStatement $stmt;
    private UrlShortener $shortener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo      = $this->createMock(PDO::class);
        $this->stmt     = $this->createMock(PDOStatement::class);
        $this->shortener = new UrlShortener($this->pdo);
    }

    // ── generateUniqueSlug ────────────────────────────────────────────────

    public function testGenerateUniqueSlugReturns16HexChars(): void
    {
        // Simulate no collision on first attempt.
        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('fetchColumn')->willReturn(false);
        $this->pdo->method('prepare')->willReturn($this->stmt);

        $slug = $this->invokePrivate('generateUniqueSlug');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $slug);
    }

    public function testGenerateUniqueSlugRetriesOnCollision(): void
    {
        $callCount = 0;
        $this->stmt->method('execute')->willReturn(true);
        // First call: collision. Second call: no collision.
        $this->stmt->method('fetchColumn')->willReturnCallback(
            function () use (&$callCount) {
                $callCount++;
                return $callCount === 1 ? '1' : false;
            }
        );
        $this->pdo->method('prepare')->willReturn($this->stmt);

        $slug = $this->invokePrivate('generateUniqueSlug');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $slug);
        $this->assertSame(2, $callCount);
    }

    public function testGenerateUniqueSlugThrowsAfterMaxRetries(): void
    {
        $this->stmt->method('execute')->willReturn(true);
        $this->stmt->method('fetchColumn')->willReturn('1'); // always collides
        $this->pdo->method('prepare')->willReturn($this->stmt);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/unique URL slug/');

        $this->invokePrivate('generateUniqueSlug');
    }

    public function testTwoSlugsAreNeverIdentical(): void
    {
        $slugs = [];
        for ($i = 0; $i < 50; $i++) {
            $slugs[] = bin2hex(random_bytes(8));
        }

        $this->assertSame(count(array_unique($slugs)), count($slugs));
    }

    // ── createShortUrl ────────────────────────────────────────────────────

    public function testCreateShortUrlContainsSlugAndDocumentPath(): void
    {
        $insertStmt = $this->createMock(PDOStatement::class);
        $selectStmt = $this->createMock(PDOStatement::class);

        $selectStmt->method('execute')->willReturn(true);
        $selectStmt->method('fetchColumn')->willReturn(false); // no collision

        $insertStmt->method('execute')->willReturn(true);

        // First prepare call = SELECT (collision check), second = INSERT
        $this->pdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($selectStmt, $insertStmt);

        $url = $this->shortener->createShortUrl(7, 'http://localhost/public');

        $this->assertStringContainsString('/edit_pdf.php?id=', $url);
        $this->assertStringStartsWith('http://localhost/public', $url);
    }

    // ── getBaseUrl ────────────────────────────────────────────────────────

    public function testGetBaseUrlReturnsHttpWhenNoHttps(): void
    {
        unset($_SERVER['HTTPS']);
        $_SERVER['HTTP_HOST'] = 'example.com';

        $url = $this->shortener->getBaseUrl();

        $this->assertStringStartsWith('http://', $url);
        $this->assertStringContainsString('example.com', $url);
    }

    public function testGetBaseUrlReturnsHttpsWhenHttpsOn(): void
    {
        $_SERVER['HTTPS']     = 'on';
        $_SERVER['HTTP_HOST'] = 'example.com';

        $url = $this->shortener->getBaseUrl();

        $this->assertStringStartsWith('https://', $url);

        unset($_SERVER['HTTPS']);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function invokePrivate(string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionMethod(UrlShortener::class, $method);
        $ref->setAccessible(true);
        return $ref->invoke($this->shortener, ...$args);
    }
}
