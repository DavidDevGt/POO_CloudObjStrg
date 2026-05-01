<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use Config\Auth;
use Config\Csrf;
use Config\Logger;
use Config\RateLimit;
use Models\Upload;
use Models\UrlShortener;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['pdfFile'])) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

if (!Auth::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Autenticación requerida.']);
    exit;
}

$userId = Auth::getUserId();

// 20 uploads per hour per user
if (!RateLimit::check('upload', (string) $userId, 20, 3600)) {
    Logger::warning('upload', 'Rate limit upload', ['user_id' => $userId]);
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Límite de subidas alcanzado. Espera un momento.']);
    exit;
}

$csrfToken = $_POST['_csrf_token'] ?? '';
if (!Csrf::validate($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido. Recarga la página.']);
    exit;
}

try {
    $upload       = new Upload(null, null, $userId);
    $urlShortener = new UrlShortener(null, $userId);

    $documentId = $upload->upload($_FILES['pdfFile']);

    $baseUrl  = rtrim($_ENV['BASE_URL'] ?? $urlShortener->getBaseUrl(), '/');
    $shortUrl = $urlShortener->createShortUrl($documentId, $baseUrl);

    Csrf::regenerate();

    Logger::info('upload', 'Upload ok', ['user_id' => $userId, 'document_id' => $documentId]);

    echo json_encode([
        'success' => true,
        'message' => 'Archivo subido con éxito.',
        'link'    => $shortUrl,
    ]);
} catch (\Throwable $e) {
    Logger::error('upload', 'Upload failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
    http_response_code(400);
    // Never leak internal exception messages to the client.
    echo json_encode(['success' => false, 'message' => 'Error al subir el archivo.']);
}
