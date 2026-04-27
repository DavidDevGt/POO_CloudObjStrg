<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use Config\Auth;
use Config\Csrf;

Auth::requireAuthOrRedirect('login.php');

$user      = Auth::getUser();
$csrfField = Csrf::field();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi cuenta — PDF Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">PDF Manager</a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <a href="dashboard.php" class="btn btn-sm btn-outline-light">Mis documentos</a>
            <a href="logout.php"    class="btn btn-sm btn-outline-secondary">Cerrar sesión</a>
        </div>
    </div>
</nav>

<div class="container" style="max-width:560px" x-data="accountPage()" x-cloak>

    <h5 class="mb-4">Mi cuenta</h5>

    <div x-show="msg" :class="'alert py-2 small alert-' + msgType" x-text="msg"></div>

    <!-- ── Nombre ─────────────────────────────────────────── -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h6 class="card-title mb-3">Perfil</h6>
            <form @submit.prevent="updateNombre">
                <?= $csrfField ?>
                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" x-model="nombre"
                           class="form-control" maxlength="100">
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small">Correo electrónico</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES) ?>" disabled>
                </div>
                <button type="submit" class="btn btn-primary" :disabled="loading">
                    <span x-show="loading" class="spinner-border spinner-border-sm me-1"></span>
                    Guardar nombre
                </button>
            </form>
        </div>
    </div>

    <!-- ── Contraseña ─────────────────────────────────────── -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h6 class="card-title mb-3">Cambiar contraseña</h6>
            <form @submit.prevent="changePassword">
                <?= $csrfField ?>
                <div class="mb-3">
                    <label class="form-label">Contraseña actual</label>
                    <input type="password" x-model="currentPwd" class="form-control"
                           required autocomplete="current-password">
                </div>
                <div class="mb-3">
                    <label class="form-label">Nueva contraseña</label>
                    <input type="password" x-model="newPwd" class="form-control"
                           required minlength="8" autocomplete="new-password">
                    <div class="form-text">Mínimo 8 caracteres.</div>
                </div>
                <button type="submit" class="btn btn-warning" :disabled="loading">
                    <span x-show="loading" class="spinner-border spinner-border-sm me-1"></span>
                    Cambiar contraseña
                </button>
            </form>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.10/dist/cdn.min.js" defer></script>
<script>
function accountPage() {
    return {
        nombre: <?= json_encode($user['nombre'] ?? '') ?>,
        currentPwd: '', newPwd: '',
        loading: false, msg: '', msgType: 'success',

        csrf() {
            return document.querySelector('input[name="_csrf_token"]').value;
        },

        async post(action, extra) {
            this.loading = true; this.msg = '';
            try {
                const res  = await fetch('api/account/update.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ action, _csrf_token: this.csrf(), ...extra }),
                });
                const json = await res.json();
                this.msgType = json.success ? 'success' : 'danger';
                this.msg = json.message;
            } catch {
                this.msgType = 'danger'; this.msg = 'Error de red.';
            } finally {
                this.loading = false;
            }
        },

        updateNombre()   { this.post('update_nombre',   { nombre: this.nombre }); },
        changePassword() {
            this.post('change_password', {
                current_password: this.currentPwd,
                new_password:     this.newPwd,
            }).then(() => { this.currentPwd = ''; this.newPwd = ''; });
        },
    };
}
</script>
</body>
</html>
