<?php

require_once '../vendor/autoload.php';

use Models\Upload;
use Models\UrlShortener;

header('Content-Type: application/json');

$upload = new Upload();
$urlShortener = new UrlShortener();
$response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["pdfFile"])) {
    try {
        // Subida del archivo
        if ($upload->uploadFile($_FILES["pdfFile"])) {
            $documentoId = $upload->saveMetadata($_FILES["pdfFile"]["name"], "../uploads/" . $_FILES["pdfFile"]["name"]);
            $urlBase = $urlShortener->obtenerUrlBaseActual();
            $enlace = $urlShortener->createEncodedShortUrl($documentoId, $urlBase);

            $response = ['success' => true, 'message' => 'Archivo subido con éxito', 'link' => $enlace];
        } else {
            $response = ['success' => false, 'message' => 'No se pudo subir el archivo'];
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
}

echo json_encode($response);
