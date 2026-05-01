<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Config\Csrf;
use Tests\TestCase;

class CsrfTest extends TestCase
{
    // setUp() clears $_SESSION via parent TestCase.

    public function testGetTokenGeneratesTokenOnFirstCall(): void
    {
        $token = Csrf::getToken();

        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes → 64 hex chars
    }

    public function testGetTokenReturnsSameTokenOnSubsequentCalls(): void
    {
        $first = Csrf::getToken();
        $second = Csrf::getToken();

        $this->assertSame($first, $second);
    }

    public function testRegenerateReturnsNewToken(): void
    {
        $old = Csrf::getToken();
        $new = Csrf::regenerate();

        $this->assertNotSame($old, $new);
        $this->assertSame($new, Csrf::getToken());
    }

    public function testValidateReturnsTrueForCorrectToken(): void
    {
        $token = Csrf::getToken();

        $this->assertTrue(Csrf::validate($token));
    }

    public function testValidateReturnsFalseForWrongToken(): void
    {
        Csrf::getToken(); // ensure session has a token

        $this->assertFalse(Csrf::validate('wrong_token'));
    }

    public function testValidateReturnsFalseWhenSessionIsEmpty(): void
    {
        $_SESSION = [];

        $this->assertFalse(Csrf::validate('anything'));
    }

    public function testFieldContainsHiddenInput(): void
    {
        $html = Csrf::field();

        $this->assertStringContainsString('<input type="hidden"', $html);
        $this->assertStringContainsString('name="_csrf_token"', $html);
        $this->assertStringContainsString(Csrf::getToken(), $html);
    }

    public function testTokenIsHexadecimal(): void
    {
        $token = Csrf::getToken();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }
}
