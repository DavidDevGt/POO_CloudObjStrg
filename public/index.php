<?php
$string = "string";
$array = [];
$int = 20;
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
    <!-- jQuery (Quiero usar $.ajax) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

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
            display: flex;
            flex-direction: column-reverse;
        }

        .notification {
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            transition: all 0.5s ease-in-out;
        }
    </style>
</head>

<body x-data="uploadForm()">
    <div class="container-fluid d-flex justify-content-center align-items-center" style="height: 100vh;">
        <div class="row">
            <div class="col-12 text-center">
                <h1 class="mb-3">Subir Archivo PDF <i class="bi bi-cloud-arrow-up-fill"></i></h1>
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

    <div class="notification-container" x-show="showMessage">
        <div :class="{'alert alert-success': isSuccess, 'alert alert-danger': !isSuccess}" class="notification" x-text="message" x-show="showMessage" x-transition:leave="transition ease-in duration-500"></div>
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

                    $.ajax({
                        url: './upload_endpoint.php',
                        type: 'POST',
                        data: formData,
                        contentType: false,
                        processData: false,
                        success: (data) => {
                            this.message = data.message;
                            this.isSuccess = data.success;
                            this.showMessage = true;
                            if (data.success) {
                                this.clearForm();
                            }
                        },
                        error: (jqXHR, textStatus, errorThrown) => {
                            console.error('Error:', errorThrown);
                            this.message = 'Error al subir el archivo.';
                            this.isSuccess = false;
                            this.showMessage = true;
                        },
                        complete: () => {
                            this.isUploading = false;
                            setTimeout(() => {
                                this.showMessage = false;
                            }, 3000);
                        }
                    });
                },

                clearForm() {
                    document.getElementById('uploadForm').reset();
                }
            }
        }
    </script>

</body>

</html>