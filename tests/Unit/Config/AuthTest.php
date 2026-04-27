<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Config\Auth;
use RuntimeException;
use Tests\TestCase;

class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Auth::reset();
    }

    protected function tearDown(): void
    {
        Auth::reset();
        parent::tearDown();
    }

    public function testIsAuthenticatedReturnsFalseInitially(): void
    {
        $this->assertFalse(Auth::isAuthenticated());
    }

    public function testGetUserIdReturnsNullWhenNotAuthenticated(): void
    {
        $this->assertNull(Auth::getUserId());
    }

    public function testGetUserReturnsNullWhenNotAuthenticated(): void
    {
        $this->assertNull(Auth::getUser());
    }

    public function testRequireAuthThrowsWhenNotAuthenticated(): void
    {
        $this->expectException(RuntimeException::class);
        Auth::requireAuth();
    }

    public function testRequireAuthThrowsWithCode401(): void
    {
        try {
            Auth::requireAuth();
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertSame(401, $e->getCode());
        }
    }

    public function testLoadFromSessionDoesNothingWhenNoSessionUserId(): void
    {
        unset($_SESSION['user_id']);
        Auth::loadFromSession();

        $this->assertFalse(Auth::isAuthenticated());
    }

    public function testLoadFromSessionIsIdempotent(): void
    {
        unset($_SESSION['user_id']);

        Auth::loadFromSession();
        Auth::loadFromSession(); // second call should be a no-op

        $this->assertFalse(Auth::isAuthenticated());
    }
}
