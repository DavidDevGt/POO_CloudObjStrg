<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use Config\Csrf;
use Config\Logger;
use Config\RateLimit;
use Models\Document;
use Models\SignDocument;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// 30 signatures per hour per IP
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!RateLimit::check('sign', $ip, 30, 3600)) {
    Logger::warning('sign', 'Rate limit sign', ['ip' => $ip]);
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Límite de firmas alcanzado. Espera un momento.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cuerpo de solicitud inválido.']);
    exit;
}

$csrfToken = $body['_csrf_token'] ?? '';
if (!Csrf::validate($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido. Recarga la página.']);
    exit;
}

$documentId    = filter_var($body['document_id'] ?? '', FILTER_VALIDATE_INT);
$signatureData = $body['signature_data'] ?? '';

if ($documentId === false || $documentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Identificador de documento inválido.']);
    exit;
}

if (empty($signatureData)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se recibió ninguna firma.']);
    exit;
}

try {
    $signDoc  = new SignDocument();
    $signId   = $signDoc->saveSignature((int) $documentId, $signatureData);

    $docModel = new Document();
    $docModel->logAccess((int) $documentId, 'firmar');

    Csrf::regenerate();

    Logger::info('sign', 'Sign ok', ['document_id' => $documentId, 'signature_id' => $signId]);

    echo json_encode([
        'success'      => true,
        'message'      => 'Firma guardada correctamente.',
        'signature_id' => $signId,
    ]);
} catch (\Throwable $e) {
    Logger::error('sign', 'Sign failed', ['document_id' => $documentId ?? null, 'error' => $e->getMessage()]);
    http_response_code(400);
    // Never expose internal exception messages to anonymous callers.
    echo json_encode(['success' => false, 'message' => 'Error al guardar la firma.']);
}
