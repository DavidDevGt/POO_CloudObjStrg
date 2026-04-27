<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use Config\Auth;
use Config\Csrf;
use Models\Upload;
use Models\UrlShortener;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['pdfFile'])) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// Auth guard — unauthenticated callers receive 401 JSON instead of a redirect.
if (!Auth::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Autenticación requerida.']);
    exit;
}
$userId = Auth::getUserId();

// CSRF validation
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

    // Rotate token after successful operation.
    Csrf::regenerate();

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
