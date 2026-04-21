<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use Config\Csrf;
use Models\Document;

// Validate slug format before any DB query.
$slug = $_GET['id'] ?? '';

if (!preg_match('/^[0-9a-f]{16}$/', $slug)) {
    http_response_code(400);
    $errorMessage = 'El identificador del documento no es válido.';
    $errorCode    = 400;
    require __DIR__ . '/partials/error.php';
    exit;
}

$docModel = new Document();
$doc      = $docModel->findBySlug($slug);

if ($doc === null) {
    http_response_code(404);
    $errorMessage = 'El documento no existe o ha sido desactivado.';
    $errorCode    = 404;
    require __DIR__ . '/partials/error.php';
    exit;
}

if ($docModel->isExpired($doc)) {
    http_response_code(410);
    $errorMessage = 'Este enlace ha expirado.';
    $errorCode    = 410;
    require __DIR__ . '/partials/error.php';
    exit;
}

$documentId   = (int) $doc['id'];
$documentName = htmlspecialchars($doc['nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$downloadUrl  = 'download.php?id=' . urlencode($slug);
$csrfToken    = Csrf::getToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firmar — <?= $documentName ?></title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-4bw+/aepP/YC94hEpVNVgiZdgIC5+VKNBQNGCHeKRQK+d1o7F8bNpvO9LGiPSS"
        crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
    <style>
        body          { background:#121212; color:#fff; }
        .pdf-frame    { width:100%; height:65vh; border:none; border-radius:.5rem; background:#fff; }
        .sig-canvas   { width:100%; height:180px; border:1px solid rgba(255,255,255,.25);
                        border-radius:.5rem; background:#1e1e1e; cursor:crosshair; touch-action:none; }
        .section-card { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.1);
                        border-radius:.75rem; padding:1.25rem; }
    </style>
</head>
<body x-data="signatureApp()" x-init="init()">
    <div class="container py-4" style="max-width:860px;">

        <!-- Header -->
        <div class="d-flex align-items-center gap-3 mb-4">
            <i class="bi bi-file-earmark-pdf-fill fs-3 text-danger"></i>
            <div>
                <h1 class="fs-5 fw-semibold mb-0"><?= $documentName ?></h1>
                <small class="text-secondary">Revisa el documento y añade tu firma</small>
            </div>
        </div>

        <!-- PDF Viewer -->
        <div class="section-card mb-4">
            <p class="small text-secondary mb-2"><i class="bi bi-eye me-1"></i>Vista previa del documento</p>
            <iframe
                src="<?= $downloadUrl ?>"
                class="pdf-frame"
                title="Documento PDF">
                <p class="text-center p-4 text-dark">
                    Tu navegador no puede mostrar PDFs.
                    <a href="<?= $downloadUrl ?>" class="text-primary">Descargar</a>
                </p>
            </iframe>
        </div>

        <!-- Signature Pad -->
        <div class="section-card mb-4">
            <p class="small text-secondary mb-2"><i class="bi bi-pen me-1"></i>Área de firma</p>
            <canvas id="signature-canvas" class="sig-canvas d-block"></canvas>
            <div class="d-flex gap-2 mt-2">
                <button class="btn btn-sm btn-outline-secondary" @click="clearSignature">
                    <i class="bi bi-eraser me-1"></i>Limpiar
                </button>
                <span class="text-secondary small align-self-center ms-auto">
                    Firma con el ratón o el dedo
                </span>
            </div>
        </div>

        <!-- Submit -->
        <div class="d-flex gap-2">
            <button class="btn btn-success flex-grow-1"
                    @click="saveSignature"
                    :disabled="isSaving">
                <span x-show="!isSaving">
                    <i class="bi bi-check2-circle me-1"></i>Guardar firma
                </span>
                <span x-show="isSaving" class="d-flex align-items-center justify-content-center gap-2">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                    Guardando…
                </span>
            </button>
            <a href="<?= $downloadUrl ?>" download class="btn btn-outline-light">
                <i class="bi bi-download me-1"></i>Descargar PDF
            </a>
        </div>

    </div>

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
    <script>
    const DOCUMENT_ID = <?= $documentId ?>;
    const CSRF_TOKEN  = <?= json_encode($csrfToken) ?>;

    function signatureApp() {
        return {
            signaturePad: null,
            isSaving: false,
            signed: false,

            init() {
                const canvas = document.getElementById('signature-canvas');
                this.signaturePad = new SignaturePad(canvas, {
                    backgroundColor: 'rgb(30, 30, 30)',
                    penColor       : 'rgb(255, 255, 255)',
                    minWidth       : 1,
                    maxWidth       : 3,
                });
                this.resizeCanvas(canvas);
                window.addEventListener('resize', () => this.resizeCanvas(canvas));
            },

            resizeCanvas(canvas) {
                const ratio = window.devicePixelRatio || 1;
                const data  = this.signaturePad.toData();
                canvas.width  = canvas.offsetWidth  * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext('2d').scale(ratio, ratio);
                this.signaturePad.fromData(data);
            },

            clearSignature() {
                this.signaturePad.clear();
            },

            async saveSignature() {
                if (this.signaturePad.isEmpty()) {
                    Swal.fire('Firma vacía', 'Por favor, dibuja tu firma antes de guardar.', 'warning');
                    return;
                }

                if (this.signed) {
                    const confirm = await Swal.fire({
                        title: '¿Firmar de nuevo?',
                        text : 'Ya existe una firma guardada. ¿Deseas añadir otra?',
                        icon : 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, guardar',
                        cancelButtonText : 'Cancelar',
                    });
                    if (!confirm.isConfirmed) return;
                }

                this.isSaving = true;

                try {
                    const signatureData = this.signaturePad.toDataURL('image/png');
                    const res  = await fetch('./sign_endpoint.php', {
                        method : 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body   : JSON.stringify({
                            document_id   : DOCUMENT_ID,
                            signature_data: signatureData,
                            _csrf_token   : CSRF_TOKEN,
                        }),
                    });
                    const data = await res.json();

                    if (data.success) {
                        this.signed = true;
                        Swal.fire({
                            icon : 'success',
                            title: '¡Firma guardada!',
                            text : 'Tu firma ha sido registrada correctamente.',
                            timer: 2500,
                            showConfirmButton: false,
                        });
                        this.clearSignature();
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                } catch {
                    Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
                } finally {
                    this.isSaving = false;
                }
            },
        };
    }
    </script>
</body>
</html>
