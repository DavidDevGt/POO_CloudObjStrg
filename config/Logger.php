<?php

declare(strict_types=1);

namespace Config;

class Logger
{
    private const LEVELS = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];

    private string $channel;
    private int    $minLevel;

    public function __construct(string $channel = 'app')
    {
        $this->channel  = $channel;
        $env            = $_ENV['APP_ENV'] ?? 'production';
        $this->minLevel = $env === 'development' ? 0 : 1;
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->write('debug', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        if ((self::LEVELS[$level] ?? 0) < $this->minLevel) {
            return;
        }

        $entry = json_encode([
            'ts'      => date('c'),
            'level'   => $level,
            'channel' => $this->channel,
            'message' => $message,
            'context' => $context,
            'request' => $this->requestContext(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        error_log($entry);
    }

    private function requestContext(): array
    {
        if (PHP_SAPI === 'cli') {
            return ['sapi' => 'cli'];
        }

        return [
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'path'   => $_SERVER['REQUEST_URI']    ?? '',
            'ip'     => $this->clientIp(),
        ];
    }

    private function clientIp(): string
    {
        // Trust X-Forwarded-For only if configured to do so.
        if (($_ENV['TRUST_PROXY'] ?? 'false') === 'true') {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if ($forwarded !== '') {
                return trim(explode(',', $forwarded)[0]);
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
