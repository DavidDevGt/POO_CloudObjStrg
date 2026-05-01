<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use Config\Database;

header('Content-Type: application/json');

$checks = [];
$ok     = true;

// Database
try {
    $pdo = Database::getInstance()->getConnection();
    $pdo->query('SELECT 1');
    $checks['database'] = 'ok';
} catch (\Throwable) {
    $checks['database'] = 'fail';
    $ok = false;
}

// Uploads directory writable
$uploadsDir = dirname(__DIR__) . '/uploads';
$checks['uploads_writable'] = (is_dir($uploadsDir) && is_writable($uploadsDir)) ? 'ok' : 'fail';
if ($checks['uploads_writable'] !== 'ok') {
    $ok = false;
}

// Disk space (warn below 100 MB)
$free = disk_free_space($uploadsDir);
$checks['disk_free_mb'] = $free !== false ? (int) ($free / 1024 / 1024) : null;
if ($free !== false && $free < 100 * 1024 * 1024) {
    $checks['disk_warning'] = 'low';
}

// PHP version
$checks['php_version'] = PHP_VERSION;

http_response_code($ok ? 200 : 503);
echo json_encode(['status' => $ok ? 'ok' : 'degraded', 'checks' => $checks], JSON_PRETTY_PRINT);
