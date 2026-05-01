<?php

declare(strict_types=1);

namespace Config;

class Csrf
{
    private const TOKEN_KEY = '_csrf_token';
    private const TOKEN_BYTES = 32;

    private function __construct()
    {
    }

    public static function getToken(): string
    {
        if (!isset($_SESSION[self::TOKEN_KEY]) || $_SESSION[self::TOKEN_KEY] === '') {
            self::regenerate();
        }

        return $_SESSION[self::TOKEN_KEY];
    }

    public static function regenerate(): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $_SESSION[self::TOKEN_KEY] = $token;

        return $token;
    }

    /**
     * Timing-safe comparison to prevent timing attacks.
     */
    public static function validate(string $token): bool
    {
        if (!isset($_SESSION[self::TOKEN_KEY]) || $_SESSION[self::TOKEN_KEY] === '') {
            return false;
        }

        return hash_equals($_SESSION[self::TOKEN_KEY], $token);
    }

    /**
     * Renders a hidden input ready to embed in any HTML form.
     */
    public static function field(): string
    {
        return sprintf(
            '<input type="hidden" name="_csrf_token" value="%s">',
            htmlspecialchars(self::getToken(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
    }
}
