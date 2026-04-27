<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use Config\Csrf;
use Models\Document;
use Models\SignDocument;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cuerpo de solicitud inválido.']);
    exit;
}

// CSRF check
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

    // Rotate CSRF token after successful use.
    Csrf::regenerate();

    echo json_encode([
        'success'      => true,
        'message'      => 'Firma guardada correctamente.',
        'signature_id' => $signId,
    ]);
} catch (\Throwable $e) {
    error_log('[sign_endpoint] ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
