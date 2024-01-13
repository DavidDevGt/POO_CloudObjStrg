<?php
$pdf_id = $_GET['id'] ?? '';

// Aquí incluyes la lógica para verificar si el PDF existe y si no ha expirado
// Esta función debe devolver la ruta del archivo si es válido, o null si no lo es
// $pdfPath = obtenerRutaPDF($pdf_id);
// if ($pdfPath === null) {
//     die("El PDF no existe o ha expirado.");
// }

// Revisar que la ruta del archivo es segura para el acceso
// $realPath = realpath($pdfPath);
// if ($realPath === false || !file_exists($realPath)) {
//     die("El archivo no se encuentra o es inaccesible.");
// }

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firmar Documento</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
    <!-- PDF.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.0.379/pdf_viewer.min.css" integrity="sha512-v7RQDI7qsfFNaXRzzylpsVV7ncQBdyozLze5YNgox/0z4Mc3Ellt2dBd0CbmufeD7IIh5TCJQ8ORAF/KvzVITg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>

<body>
    <h1 class="display-4 text-center mt-5 mb-3">Firmar Documento</h1>

    <canvas id="signature-pad"></canvas>

    <!-- SweetAlert2 -->
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Signature Pad -->
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
    <!-- PDF.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.0.379/pdf.min.mjs"></script>
    <script>
        // Inicialización de Signature Pad y PDF.js
        var canvas = document.getElementById('signature-pad');
        var signaturePad = new SignaturePad(canvas);
    </script>
</body>

</html>