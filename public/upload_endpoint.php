<?php

require_once '../vendor/autoload.php';

use Models\Upload;

header('Content-Type: application/json');

$upload = new Upload();
$response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["pdfFile"])) {
    try {
        $upload->uploadFile($_FILES["pdfFile"]);
        $uniqueId = bin2hex(random_bytes(16));
        $timeExpire = time() + 3600 * 12; 

        echo json_encode(['success' => true, 'message' => 'Archivo subido con éxito', 'link' => 'edit_pdf.php?id=' . $uniqueId]);
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
}

echo json_encode($response);
