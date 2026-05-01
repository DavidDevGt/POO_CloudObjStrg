<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use Config\Database;

header('Content-Type: application/json');
header('Cache-Control: no-store');

$checks  = [];
$healthy = true;

// ── Database ──────────────────────────────────────────────────────────────────
try {
    $pdo  = Database::getConnection();
    $stmt = $pdo->query('SELECT 1');
    $checks['database'] = ['status' => 'ok'];
} catch (\Throwable $e) {
    $checks['database'] = ['status' => 'error', 'detail' => 'connection failed'];
    $healthy = false;
}

// ── Uploads directory writable ────────────────────────────────────────────────
$uploadDir = dirname(__DIR__) . '/uploads/';
if (is_writable($uploadDir)) {
    $checks['uploads'] = ['status' => 'ok'];
} else {
    $checks['uploads'] = ['status' => 'error', 'detail' => 'directory not writable'];
    $healthy = false;
}

// ── Disk space (warn below 500 MB free) ───────────────────────────────────────
$free = disk_free_space($uploadDir);
if ($free !== false) {
    $freeMb = round($free / 1_048_576);
    $checks['disk'] = [
        'status'   => $freeMb > 500 ? 'ok' : 'warn',
        'free_mb'  => $freeMb,
    ];
} else {
    $checks['disk'] = ['status' => 'unknown'];
}

// ── PHP version ───────────────────────────────────────────────────────────────
$checks['php'] = [
    'status'  => 'ok',
    'version' => PHP_VERSION,
];

// ── Response ──────────────────────────────────────────────────────────────────
$status = $healthy ? 'healthy' : 'degraded';
http_response_code($healthy ? 200 : 503);

echo json_encode([
    'status' => $status,
    'checks' => $checks,
    'ts'     => date('c'),
], JSON_PRETTY_PRINT);
