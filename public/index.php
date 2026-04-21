<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use Config\Csrf;

$csrfToken = Csrf::getToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Object Storage PDF</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-4bw+/aepP/YC94hEpVNVgiZdgIC5+VKNBQNGCHeKRQK+d1o7F8bNpvO9LGiPSS"
        crossorigin="anonymous">
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css"
        rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #121212; color: #fff; }
        .upload-card {
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: .75rem;
            padding: 2rem;
            max-width: 520px;
            width: 100%;
        }
        .link-box {
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.15);
            border-radius: .5rem;
            padding: .75rem 1rem;
            word-break: break-all;
            font-family: monospace;
            font-size: .9rem;
        }
    </style>
</head>
<body x-data="uploadForm()">
    <div class="d-flex justify-content-center align-items-center" style="min-height:100vh; padding:1rem;">
        <div class="upload-card">
            <h1 class="fs-4 fw-semibold mb-4 text-center">
                <i class="bi bi-cloud-arrow-up-fill me-2"></i>Subir PDF
            </h1>

            <form id="uploadForm" @submit.prevent="submitUpload">
                <input type="hidden" name="_csrf_token" id="csrf_token"
                       value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

                <div class="mb-3">
                    <input type="file"
                           class="form-control"
                           name="pdfFile"
                           id="pdfFile"
                           accept="application/pdf"
                           required>
                    <div class="form-text text-secondary">Máximo 5 MB · Solo PDF</div>
                </div>

                <button type="submit" class="btn btn-primary w-100" :disabled="isUploading">
                    <span x-show="!isUploading">
                        <i class="bi bi-upload me-1"></i> Subir archivo
                    </span>
                    <span x-show="isUploading" class="d-flex align-items-center justify-content-center gap-2">
                        <span class="spinner-border spinner-border-sm" role="status"></span>
                        Subiendo…
                    </span>
                </button>
            </form>

            <!-- Short link panel (shown after successful upload) -->
            <div x-show="shortLink" x-transition class="mt-4">
                <p class="mb-1 small text-secondary">Link de acceso generado:</p>
                <div class="link-box mb-2" x-text="shortLink"></div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-light flex-grow-1" @click="copyLink">
                        <i class="bi bi-clipboard me-1"></i>Copiar
                    </button>
                    <a :href="shortLink" target="_blank" class="btn btn-sm btn-outline-info flex-grow-1">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Abrir
                    </a>
                </div>
            </div>

            <!-- Inline error toast -->
            <div x-show="errorMessage" x-transition
                 class="alert alert-danger mt-3 py-2 mb-0 small"
                 x-text="errorMessage">
            </div>
        </div>
    </div>

    <script>
    function uploadForm() {
        return {
            isUploading : false,
            shortLink   : '',
            errorMessage: '',

            async submitUpload() {
                const file = document.getElementById('pdfFile').files[0];
                if (!this.validateFile(file)) return;

                this.isUploading  = true;
                this.shortLink    = '';
                this.errorMessage = '';

                const formData = new FormData(document.getElementById('uploadForm'));

                try {
                    const res  = await fetch('./upload_endpoint.php', { method: 'POST', body: formData });
                    const data = await res.json();

                    if (data.success) {
                        this.shortLink = data.link;
                        document.getElementById('uploadForm').reset();
                        // Refresh CSRF token from hidden input returned by server
                        if (data.csrf_token) {
                            document.getElementById('csrf_token').value = data.csrf_token;
                        }
                    } else {
                        this.errorMessage = data.message;
                    }
                } catch {
                    this.errorMessage = 'Error de red. Intenta de nuevo.';
                } finally {
                    this.isUploading = false;
                }
            },

            validateFile(file) {
                if (!file) {
                    Swal.fire('Error', 'Selecciona un archivo PDF.', 'error');
                    return false;
                }
                if (file.type !== 'application/pdf') {
                    Swal.fire('Error', 'Solo se permiten archivos PDF.', 'error');
                    return false;
                }
                if (file.size > 5_000_000) {
                    Swal.fire('Error', 'El archivo supera el límite de 5 MB.', 'error');
                    return false;
                }
                return true;
            },

            copyLink() {
                navigator.clipboard.writeText(this.shortLink).then(() => {
                    Swal.fire({ icon: 'success', title: '¡Copiado!', timer: 1200, showConfirmButton: false });
                });
            },
        };
    }
    </script>
</body>
</html>
