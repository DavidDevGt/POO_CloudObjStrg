<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use Models\Document;

// Slug is always 16 lowercase hex chars (bin2hex of 8 bytes).
$slug = $_GET['id'] ?? '';

if (!preg_match('/^[0-9a-f]{16}$/', $slug)) {
    http_response_code(400);
    exit('Invalid document identifier.');
}

$document = new Document();
$doc      = $document->findBySlug($slug);

if ($doc === null) {
    http_response_code(404);
    exit('Document not found or has been deactivated.');
}

if ($document->isExpired($doc)) {
    http_response_code(410);
    exit('This document link has expired.');
}

$document->logAccess((int) $doc['id'], 'descargar');
$document->serveFile($doc);
