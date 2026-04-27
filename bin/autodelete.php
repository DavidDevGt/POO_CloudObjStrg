#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use Models\AutoDelete;

$autoDelete = new AutoDelete();

try {
    $autoDelete->deleteExpiredDocuments();
    echo '[' . date('Y-m-d H:i:s') . '] AutoDelete: expired documents deactivated.' . PHP_EOL;
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] AutoDelete ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
