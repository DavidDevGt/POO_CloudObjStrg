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

$body = json_decode(file_get_contents('php://input'), true) ?? [];

if (!Csrf::validate($body['_csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido. Recarga la página.']);
    exit;
}

$email    = trim($body['email']    ?? '');
$password = trim($body['password'] ?? '');
$nombre   = trim($body['nombre']   ?? '') ?: null;

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Correo y contraseña son obligatorios.']);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El correo electrónico no es válido.']);
    exit;
}

$userModel = new User();

if ($userModel->emailExists($email)) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Este correo ya está registrado.']);
    exit;
}

try {
    $newId = $userModel->create($email, $password, $nombre);
    Auth::login($newId);
    echo json_encode(['success' => true, 'message' => 'Cuenta creada con éxito.']);
} catch (\RuntimeException $e) {
    error_log('[register] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al crear la cuenta. Inténtalo de nuevo.']);
}
