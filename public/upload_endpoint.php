<?php

require_once '../vendor/autoload.php';

use Models\Upload;

header('Content-Type: application/json');

$upload = new Upload();
$response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["pdfFile"])) {
    try {
        $upload->uploadFile($_FILES["pdfFile"]);
        // Después de subir el archivo correctamente
        $uniqueId = bin2hex(random_bytes(16)); // Genera un identificador único
        $expiryTime = time() + (12 * 60 * 60); // 12 horas desde ahora

        $response = ['success' => true, 'message' => "Archivo PDF subido con éxito."];
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
}

echo json_encode($response);
