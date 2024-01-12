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
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
    <style>
        /* CSS personalizado */
        body {
            padding-top: 5rem;
        }

        .container {
            max-width: 500px;
        }
    </style>
</head>

<body x-data="{ isUploading: false, message: '<?php echo $message; ?>' }">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center">
                <h1 class="mb-3">Subir Archivo PDF</h1>
                <form action="index.php" method="post" enctype="multipart/form-data" @submit="isUploading = true">
                    <div class="mb-3">
                        <input type="file" class="form-control" name="pdfFile" id="pdfFile" required>
                    </div>
                    <button type="submit" class="btn btn-primary" :disabled="isUploading">
                        Subir
                        <span x-show="isUploading" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    </button>
                </form>
                <div x-show="message" class="alert" :class="{'alert-success': message === 'Archivo subido con éxito.', 'alert-danger': message !== 'Archivo subido con éxito.'}" x-text="message"></div>
            </div>
        </div>
    </div>
</body>

</html>