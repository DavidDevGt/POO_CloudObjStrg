<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS']);
$dotenv->required(['APP_ENV'])->allowedValues(['development', 'production', 'testing']);

// Session must be started before any output and before CSRF is used.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
