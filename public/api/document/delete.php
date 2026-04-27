<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/bootstrap.php';

use Config\Auth;
use Config\Csrf;
use Models\Document;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

if (!Auth::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Autenticación requerida.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

if (!Csrf::validate($body['_csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido. Recarga la página.']);
    exit;
}

$documentId = filter_var($body['document_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($documentId === false || $documentId === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de documento inválido.']);
    exit;
}

$userId   = Auth::getUserId();
$docModel = new Document(null, null, $userId);

if (!$docModel->deactivate((int) $documentId, $userId)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Documento no encontrado o ya eliminado.']);
    exit;
}

$docModel->logAccess((int) $documentId, 'eliminar');

echo json_encode(['success' => true, 'message' => 'Documento eliminado.']);
