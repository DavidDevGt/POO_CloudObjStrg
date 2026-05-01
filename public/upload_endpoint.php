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

$log = new Logger('upload');

try {
    RateLimit::check('upload', (string) $userId, max: 20, window: 3600);
} catch (\RuntimeException $e) {
    $log->warning('Upload rate limit exceeded', ['user_id' => $userId]);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$csrfToken = $_POST['_csrf_token'] ?? '';
if (!Csrf::validate($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido. Recarga la página.']);
    exit;
}

// Known, safe user-facing validation messages — all others become generic.
$safeMessages = [
    'Only PDF files are allowed.',
    'Upload error, code:',
];

try {
    $upload       = new Upload(null, null, $userId);
    $urlShortener = new UrlShortener(null, $userId);

    $documentId = $upload->upload($_FILES['pdfFile']);

    $baseUrl  = rtrim($_ENV['BASE_URL'] ?? $urlShortener->getBaseUrl(), '/');
    $shortUrl = $urlShortener->createShortUrl($documentId, $baseUrl);

    Csrf::regenerate();

    $log->info('Document uploaded', ['user_id' => $userId, 'doc_id' => $documentId]);

    echo json_encode([
        'success' => true,
        'message' => 'Archivo subido con éxito.',
        'link'    => $shortUrl,
    ]);
} catch (\Throwable $e) {
    $log->error('Upload failed', ['user_id' => $userId, 'error' => $e->getMessage()]);

    $isSafe = false;
    foreach ($safeMessages as $safe) {
        if (str_starts_with($e->getMessage(), $safe)) {
            $isSafe = true;
            break;
        }
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $isSafe ? $e->getMessage() : 'Error al subir el archivo. Inténtalo de nuevo.',
    ]);
}
