<?php

declare(strict_types=1);

namespace Config;

class Logger
{
    private static function entry(string $level, string $channel, string $message, array $context = []): void
    {
        $entry = array_filter([
            'ts' => date('c'),
            'level' => $level,
            'channel' => $channel,
            'message' => $message,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'path' => $_SERVER['REQUEST_URI'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ] + $context, fn ($v) => $v !== null);

        error_log(json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function info(string $channel, string $message, array $context = []): void
    {
        self::entry('info', $channel, $message, $context);
    }

    public static function warning(string $channel, string $message, array $context = []): void
    {
        self::entry('warning', $channel, $message, $context);
    }

    public static function error(string $channel, string $message, array $context = []): void
    {
        self::entry('error', $channel, $message, $context);
    }
}
