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

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Correo y contraseña son obligatorios.']);
    exit;
}

$userModel = new User();
$user      = $userModel->findByEmail($email);

if ($user === null || !$userModel->verifyPassword($user, $password)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Credenciales incorrectas.']);
    exit;
}

Auth::login((int) $user['id']);

echo json_encode(['success' => true, 'message' => 'Sesión iniciada.']);
