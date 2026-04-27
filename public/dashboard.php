<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use Config\Auth;
use Config\Csrf;
use Models\Document;

Auth::requireAuthOrRedirect('login.php');

$userId      = Auth::getUserId();
$user        = Auth::getUser();
$docModel    = new Document(null, null, $userId);
$docs        = $docModel->listByUser($userId);
$csrfToken   = Csrf::getToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis documentos — PDF Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand fw-semibold" href="#">PDF Manager</a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <span class="text-white-50 small d-none d-md-inline">
                <?= htmlspecialchars($user['nombre'] ?? $user['email'] ?? '', ENT_QUOTES) ?>
            </span>
            <a href="index.php"    class="btn btn-sm btn-primary">
                <i class="bi bi-upload me-1"></i>Subir PDF
            </a>
            <a href="account.php"  class="btn btn-sm btn-outline-light">
                <i class="bi bi-person-circle"></i>
            </a>
            <a href="logout.php"   class="btn btn-sm btn-outline-secondary">Salir</a>
        </div>
    </div>
</nav>

<div class="container" style="max-width:960px" x-data="dashboard()" x-cloak>

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="mb-0">Mis documentos
            <span class="badge bg-secondary ms-1"><?= count($docs) ?></span>
        </h5>
    </div>

    <div x-show="msg" :class="'alert py-2 small alert-' + msgType" x-text="msg"></div>

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
                        <th class="text-center">Firmas</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($docs as $doc): ?>
                    <tr :class="deleted.has(<?= (int)$doc['id'] ?>) ? 'd-none' : ''">
                        <td class="fw-medium" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                            <?= htmlspecialchars($doc['nombre'], ENT_QUOTES) ?>
                        </td>
                        <td class="text-nowrap small text-muted">
                            <?= htmlspecialchars(substr($doc['fecha_subida'] ?? '', 0, 10), ENT_QUOTES) ?>
                        </td>
                        <td class="text-center">
                            <?php if ((int)$doc['firma_count'] > 0): ?>
                                <span class="badge bg-success">
                                    <i class="bi bi-pen me-1"></i><?= (int)$doc['firma_count'] ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif ?>
                        </td>
                        <td>
                            <?php
                            $expiry = $doc['fecha_expiracion'] ?? null;
                            if ($expiry && strtotime($expiry) < time()): ?>
                                <span class="badge bg-warning text-dark">Expirado</span>
                            <?php else: ?>
                                <span class="badge bg-success-subtle text-success">Activo</span>
                            <?php endif ?>
                        </td>
                        <td class="text-nowrap">
                            <?php if (!empty($doc['slug'])): ?>
                            <a href="edit_pdf.php?id=<?= htmlspecialchars($doc['slug'], ENT_QUOTES) ?>"
                               class="btn btn-sm btn-outline-primary" target="_blank" title="Ver / Firmar">
                                <i class="bi bi-eye"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-secondary"
                                    title="Copiar enlace"
                                    @click="copy('<?= htmlspecialchars($doc['enlace'] ?? '', ENT_QUOTES) ?>')">
                                <i class="bi bi-clipboard"></i>
                            </button>
                            <?php endif ?>
                            <button class="btn btn-sm btn-outline-danger"
                                    title="Eliminar"
                                    @click="deleteDoc(<?= (int)$doc['id'] ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.10/dist/cdn.min.js" defer></script>
<script>
const CSRF = <?= json_encode($csrfToken) ?>;

function dashboard() {
    return {
        deleted: new Set(),
        msg: '', msgType: 'success',

        async deleteDoc(id) {
            if (!confirm('¿Eliminar este documento? Esta acción no se puede deshacer.')) return;
            try {
                const res  = await fetch('api/document/delete.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ document_id: id, _csrf_token: CSRF }),
                });
                const json = await res.json();
                if (json.success) {
                    this.deleted.add(id);
                    this.msgType = 'success';
                    this.msg = 'Documento eliminado.';
                } else {
                    this.msgType = 'danger';
                    this.msg = json.message;
                }
            } catch {
                this.msgType = 'danger';
                this.msg = 'Error de red al eliminar.';
            }
        },

        copy(url) {
            navigator.clipboard.writeText(url).then(() => {
                this.msgType = 'success';
                this.msg = 'Enlace copiado al portapapeles.';
            });
        },
    };
}
</script>
</body>
</html>
