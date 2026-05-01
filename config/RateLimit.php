<?php

declare(strict_types=1);

namespace Config;

/**
 * File-based sliding-window rate limiter.
 * APCu is preferred when available (in-memory, faster).
 * Falls back to a JSON file in sys_get_temp_dir() for environments without APCu.
 *
 * Usage:
 *   RateLimit::check('login', $ip, max: 10, window: 60);  // 10 attempts per 60s
 */
class RateLimit
{
    private function __construct() {}

    /**
     * @throws \RuntimeException with HTTP 429 code when limit exceeded.
     */
    public static function check(string $action, string $key, int $max, int $window): void
    {
        $storeKey = "rl:{$action}:{$key}";
        $now      = time();

        if (extension_loaded('apcu') && apcu_enabled()) {
            self::checkApcu($storeKey, $now, $max, $window);
        } else {
            self::checkFile($storeKey, $now, $max, $window);
        }
    }

    private static function checkApcu(string $key, int $now, int $max, int $window): void
    {
        $data = apcu_fetch($key, $success);

        if (!$success) {
            $data = ['count' => 0, 'reset_at' => $now + $window];
        }

        if ($now > $data['reset_at']) {
            $data = ['count' => 0, 'reset_at' => $now + $window];
        }

        $data['count']++;
        apcu_store($key, $data, $window);

        if ($data['count'] > $max) {
            http_response_code(429);
            header('Retry-After: ' . max(0, $data['reset_at'] - $now));
            throw new \RuntimeException('Demasiados intentos. Espera un momento e inténtalo de nuevo.', 429);
        }
    }

    private static function checkFile(string $key, int $now, int $max, int $window): void
    {
        $file   = sys_get_temp_dir() . '/rl_' . md5($key) . '.json';
        $data   = [];

        if (file_exists($file)) {
            $json = file_get_contents($file);
            $data = json_decode($json, true) ?? [];
        }

        // Remove timestamps outside the window (sliding window).
        $data = array_filter($data, fn(int $ts) => $ts > ($now - $window));
        $data[] = $now;

        file_put_contents($file, json_encode(array_values($data)), LOCK_EX);

        if (count($data) > $max) {
            http_response_code(429);
            header('Retry-After: ' . $window);
            throw new \RuntimeException('Demasiados intentos. Espera un momento e inténtalo de nuevo.', 429);
        }
    }

    /** Returns the client's best-guess IP, respecting TRUST_PROXY. */
    public static function clientKey(): string
    {
        if (($_ENV['TRUST_PROXY'] ?? 'false') === 'true') {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if ($forwarded !== '') {
                return trim(explode(',', $forwarded)[0]);
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
