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
        body {
            background: #121212;
            color: white;
        }

        .container-fluid {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
        }

        .btn-primary {
            border: none;
        }

        .notification-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }

        .notification {
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
        }
    </style>
</head>

<body x-data="uploadForm()">
    <div class="container-fluid d-flex justify-content-center align-items-center" style="height: 100vh;">
        <div class="row">
            <div class="col-12 text-center">
                <h2 class="mb-3">Subir Archivo PDF <i class="bi bi-cloud-arrow-up-fill"></i></h2>
                <form id="uploadForm" @submit.prevent="uploadFile" class="d-flex justify-content-center">
                    <div class="input-group mb-3">
                        <input type="file" class="form-control" name="pdfFile" id="pdfFile" required>
                        <button type="submit" class="btn btn-primary" x-bind:disabled="isUploading">
                            <i class="bi bi-upload" x-show="!isUploading"></i>
                            <span x-show="isUploading" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="notification-container" x-show="showMessage" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div :class="{'alert alert-success': isSuccess, 'alert alert-danger': !isSuccess}" class="notification" x-text="message"></div>
    </div>

    <script>
        function uploadForm() {
            return {
                isUploading: false,
                message: '',
                showMessage: false,
                isSuccess: false,

                uploadFile() {
                    this.isUploading = true;
                    const formData = new FormData(document.getElementById('uploadForm'));

                    fetch('./upload_endpoint.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            this.message = data.message;
                            this.isSuccess = data.success;
                            this.showMessage = true;
                            setTimeout(() => this.showMessage = false, 3000);
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            this.message = 'Error al subir el archivo.';
                            this.isSuccess = false;
                            this.showMessage = true;
                            setTimeout(() => this.showMessage = false, 3000);
                        })
                        .finally(() => {
                            this.isUploading = false;
                        });
                }
            }
        }
    </script>

</body>



</html>