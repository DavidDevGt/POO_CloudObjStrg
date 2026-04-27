<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use Config\Auth;
use Config\Csrf;

if (Auth::isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

$csrfField = Csrf::field();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear cuenta — PDF Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh">
<div class="container" style="max-width:440px" x-data="registerForm()" x-cloak>
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h4 class="mb-4 text-center">Crear cuenta</h4>

            <div x-show="error" x-text="error" class="alert alert-danger py-2 small"></div>

            <form @submit.prevent="submit">
                <?= $csrfField ?>
                <div class="mb-3">
                    <label class="form-label">Nombre (opcional)</label>
                    <input type="text" name="nombre" x-model="nombre"
                           class="form-control" autocomplete="name">
                </div>
                <div class="mb-3">
                    <label class="form-label">Correo electrónico</label>
                    <input type="email" name="email" x-model="email"
                           class="form-control" required autocomplete="email">
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="password" x-model="password"
                           class="form-control" required autocomplete="new-password"
                           minlength="8">
                    <div class="form-text">Mínimo 8 caracteres.</div>
                </div>
                <button type="submit" class="btn btn-success w-100" :disabled="loading">
                    <span x-show="loading" class="spinner-border spinner-border-sm me-2"></span>
                    Registrarme
                </button>
            </form>

            <hr>
            <p class="text-center small mb-0">
                ¿Ya tienes cuenta?
                <a href="login.php">Inicia sesión</a>
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.10/dist/cdn.min.js" defer></script>
<script>
function registerForm() {
    return {
        nombre: '', email: '', password: '', loading: false, error: '',
        async submit() {
            this.loading = true;
            this.error   = '';
            const form   = document.querySelector('form');
            const data   = Object.fromEntries(new FormData(form));
            try {
                const res  = await fetch('api/auth/register.php', {
                    method:  'POST',
                    headers: {'Content-Type': 'application/json'},
                    body:    JSON.stringify(data),
                });
                const json = await res.json();
                if (json.success) {
                    window.location.href = 'dashboard.php';
                } else {
                    this.error = json.message;
                }
            } catch {
                this.error = 'Error de red. Inténtalo de nuevo.';
            } finally {
                this.loading = false;
            }
        }
    };
}
</script>
</body>
</html>
