<?php

declare(strict_types=1);

namespace Config;

class RateLimit
{
    /**
     * Returns true if the request is allowed, false if the limit is exceeded.
     *
     * @param string $action  Logical action name (e.g. 'login', 'upload').
     * @param string $key     Per-caller identifier (IP, user ID, email hash…).
     * @param int    $max     Maximum allowed hits within $window seconds.
     * @param int    $window  Sliding window duration in seconds.
     */
    public static function check(string $action, string $key, int $max, int $window): bool
    {
        return function_exists('apcu_fetch') && ini_get('apc.enabled')
            ? self::checkApcu($action, $key, $max, $window)
            : self::checkFile($action, $key, $max, $window);
    }

    // ── APCu backend ────────────────────────────────────────────────────────

    private static function checkApcu(string $action, string $key, int $max, int $window): bool
    {
        $cacheKey = "rl:{$action}:{$key}";
        $now = time();

        $hits = apcu_fetch($cacheKey, $exists);
        if (!$exists || !is_array($hits)) {
            $hits = [];
        }

        $hits = array_values(array_filter($hits, fn (int $t) => $t > $now - $window));

        if (count($hits) >= $max) {
            return false;
        }

        $hits[] = $now;
        apcu_store($cacheKey, $hits, $window);

        return true;
    }

    // ── File-based fallback ──────────────────────────────────────────────────

    private static function checkFile(string $action, string $key, int $max, int $window): bool
    {
        $dir = sys_get_temp_dir() . '/rl';
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $file = $dir . '/' . hash('sha256', "{$action}:{$key}") . '.json';
        $now = time();
        $lock = fopen($file, 'c+');

        if ($lock === false) {
            return true; // fail open if filesystem is unavailable
        }

        flock($lock, LOCK_EX);
        $raw = stream_get_contents($lock);
        $data = json_decode($raw !== false ? $raw : '[]', true);

        if (!is_array($data)) {
            $data = [];
        }

        $data = array_values(array_filter($data, fn (int $t) => $t > $now - $window));

        if (count($data) >= $max) {
            flock($lock, LOCK_UN);
            fclose($lock);

            return false;
        }

        $data[] = $now;
        ftruncate($lock, 0);
        rewind($lock);
        fwrite($lock, json_encode($data));
        flock($lock, LOCK_UN);
        fclose($lock);

        return true;
    }
}
