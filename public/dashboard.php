<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use Config\Auth;
use Config\Csrf;
use Models\Document;

Auth::requireAuthOrRedirect('login.php');

$userId   = Auth::getUserId();
$user     = Auth::getUser();
$docModel = new Document(null, null, $userId);
$docs     = $docModel->listByUser($userId);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis documentos — PDF Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">PDF Manager</a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <span class="text-white-50 small">
                <?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES) ?>
            </span>
            <a href="index.php" class="btn btn-sm btn-outline-light">Subir PDF</a>
            <a href="logout.php" class="btn btn-sm btn-outline-secondary">Cerrar sesión</a>
        </div>
    </div>
</nav>

<div class="container" style="max-width:900px">
    <h5 class="mb-3">Mis documentos</h5>

    <?php if (empty($docs)): ?>
        <div class="alert alert-info">
            Aún no tienes documentos.
            <a href="index.php">Sube tu primer PDF</a>.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle bg-white shadow-sm rounded">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Subido</th>
                        <th>Expira</th>
                        <th>Link corto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($docs as $doc): ?>
                    <tr>
                        <td><?= htmlspecialchars($doc['nombre'], ENT_QUOTES) ?></td>
                        <td class="text-nowrap small text-muted">
                            <?= htmlspecialchars($doc['fecha_subida'] ?? '', ENT_QUOTES) ?>
                        </td>
                        <td class="text-nowrap small text-muted">
                            <?= $doc['fecha_expiracion']
                                ? htmlspecialchars($doc['fecha_expiracion'], ENT_QUOTES)
                                : '—' ?>
                        </td>
                        <td>
                            <?php if (!empty($doc['slug'])): ?>
                                <a href="edit_pdf.php?id=<?= htmlspecialchars($doc['slug'], ENT_QUOTES) ?>"
                                   class="btn btn-sm btn-outline-primary" target="_blank">
                                    Ver / Firmar
                                </a>
                                <button class="btn btn-sm btn-outline-secondary"
                                        onclick="navigator.clipboard.writeText('<?= htmlspecialchars($doc['enlace'] ?? '', ENT_QUOTES) ?>')">
                                    Copiar
                                </button>
                            <?php else: ?>
                                <span class="text-muted small">Sin link</span>
                            <?php endif ?>
                        </td>
                    </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>

</body>
</html>
