<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error <?= (int) ($errorCode ?? 400) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-4bw+/aepP/YC94hEpVNVgiZdgIC5+VKNBQNGCHeKRQK+d1o7F8bNpvO9LGiPSS"
          crossorigin="anonymous">
    <style>body { background:#121212; color:#fff; }</style>
</head>
<body>
<div class="d-flex flex-column justify-content-center align-items-center" style="min-height:100vh;">
    <h1 class="display-6 fw-bold mb-2"><?= (int) ($errorCode ?? 400) ?></h1>
    <p class="text-secondary"><?= htmlspecialchars((string) ($errorMessage ?? 'Ha ocurrido un error.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
    <a href="/" class="btn btn-outline-light mt-3">Volver al inicio</a>
</div>
</body>
</html>
