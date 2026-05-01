<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Populate $_ENV directly — no .env file needed in CI / test runs.
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_NAME'] = 'pdf_store_test';
$_ENV['DB_USER'] = 'root';
$_ENV['DB_PASS'] = '';
$_ENV['UPLOAD_MAX_SIZE'] = '5000000';
$_ENV['AUTO_DELETE_HOURS'] = '12';
$_ENV['BASE_URL'] = 'http://localhost/public';

// Session must be available for CSRF tests.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
