<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use Models\Upload;
use Models\UrlShortener;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['pdfFile'])) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

try {
    $upload       = new Upload();
    $urlShortener = new UrlShortener();

    $documentId = $upload->upload($_FILES['pdfFile']);
    $shortUrl   = $urlShortener->createShortUrl($documentId, $urlShortener->getBaseUrl());

    echo json_encode([
        'success' => true,
        'message' => 'Archivo subido con éxito.',
        'link'    => $shortUrl,
    ]);
} catch (\Throwable $e) {
    error_log('[upload_endpoint] ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
