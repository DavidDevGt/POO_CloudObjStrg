<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/bootstrap.php';

use Config\Auth;
use Config\Csrf;
use Models\User;

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

$userId    = Auth::getUserId();
$userModel = new User();
$current   = $userModel->findById($userId);

if ($current === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado.']);
    exit;
}

$action = $body['action'] ?? '';

if ($action === 'update_nombre') {
    $nombre = trim($body['nombre'] ?? '');
    if ($nombre === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El nombre no puede estar vacío.']);
        exit;
    }
    $userModel->updateNombre($userId, mb_substr($nombre, 0, 100));
    echo json_encode(['success' => true, 'message' => 'Nombre actualizado.']);
    exit;
}

if ($action === 'change_password') {
    $currentPassword = $body['current_password'] ?? '';
    $newPassword     = $body['new_password']     ?? '';

    if (!$userModel->verifyPassword($current, $currentPassword)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'La contraseña actual es incorrecta.']);
        exit;
    }

    if (strlen($newPassword) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'La nueva contraseña debe tener al menos 8 caracteres.']);
        exit;
    }

    $userModel->updatePassword($userId, $newPassword);
    echo json_encode(['success' => true, 'message' => 'Contraseña actualizada.']);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
