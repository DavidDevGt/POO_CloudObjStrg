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

$log = new Logger('sign');

try {
    RateLimit::check('sign', RateLimit::clientKey(), max: 30, window: 3600);
} catch (\RuntimeException $e) {
    $log->warning('Sign rate limit exceeded', ['ip' => RateLimit::clientKey()]);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
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

// Safe, known user-facing messages from SignDocument.
$safeMessages = [
    'Signature data exceeds the 2 MB limit.',
    'Invalid signature format. Expected a PNG data URL.',
];

try {
    $signDoc = new SignDocument();
    $signId  = $signDoc->saveSignature((int) $documentId, $signatureData);

    $docModel = new Document();
    $docModel->logAccess((int) $documentId, 'firmar');

    Csrf::regenerate();

    $log->info('Signature saved', ['doc_id' => $documentId, 'sign_id' => $signId]);

    echo json_encode([
        'success'      => true,
        'message'      => 'Firma guardada correctamente.',
        'signature_id' => $signId,
    ]);
} catch (\Throwable $e) {
    $log->error('Sign failed', ['doc_id' => $documentId, 'error' => $e->getMessage()]);

    $isSafe = in_array($e->getMessage(), $safeMessages, true);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $isSafe ? $e->getMessage() : 'Error al guardar la firma. Inténtalo de nuevo.',
    ]);
}
