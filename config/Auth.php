<?php

declare(strict_types=1);

namespace Config;

use Models\User;
use RuntimeException;

class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function loadFromSession(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        $userId = $_SESSION['user_id'] ?? null;
        if ($userId === null) {
            return;
        }

        $userModel = new User();
        self::$user = $userModel->findById((int) $userId);

        if (self::$user === null) {
            unset($_SESSION['user_id']);
        }
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;

        $userModel = new User();
        self::$user = $userModel->findById($userId);
        self::$loaded = true;
    }

    public static function logout(): void
    {
        session_destroy();
        session_start();
        $_SESSION = [];
        self::$user = null;
        self::$loaded = false;
    }

    public static function isAuthenticated(): bool
    {
        return self::$user !== null;
    }

    public static function getUserId(): ?int
    {
        return self::$user !== null ? (int) self::$user['id'] : null;
    }

    public static function getUser(): ?array
    {
        return self::$user;
    }

    public static function requireAuth(): int
    {
        if (self::$user === null) {
            throw new RuntimeException('Authentication required.', 401);
        }
        return (int) self::$user['id'];
    }

    public static function requireAuthOrRedirect(string $url = '/login.php'): int
    {
        if (self::$user === null) {
            header('Location: ' . $url);
            exit;
        }
        return (int) self::$user['id'];
    }

    /** Reset state — used only in tests. */
    public static function reset(): void
    {
        self::$user = null;
        self::$loaded = false;
    }
}
