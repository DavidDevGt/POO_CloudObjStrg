<?php

require_once '../vendor/autoload.php';

use Models\Upload;

$upload = new Upload();
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["pdfFile"])) {
    try {
        $upload->uploadFile($_FILES["pdfFile"]);
        $message = "Archivo subido con éxito.";
    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Object Storage PDF</title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
</head>

<body x-data="{ isUploading: false, message: '<?php echo $message; ?>' }">
    <div class="container">
        <h1>Subir Archivo PDF</h1>
        <form action="index.php" method="post" enctype="multipart/form-data" @submit="isUploading = true">
            <input type="file" name="pdfFile" id="pdfFile" required>
            <button type="submit" :disabled="isUploading">Subir</button>
        </form>
        <div x-show="message" x-text="message"></div>
    </div>
</body>

</html>