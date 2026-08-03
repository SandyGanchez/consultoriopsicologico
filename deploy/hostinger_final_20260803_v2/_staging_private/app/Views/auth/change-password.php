<?php

use App\Core\Session;
use App\Helpers\Helper;

$identidad = is_array($identidadConsultorio ?? null) ? $identidadConsultorio : [];

$esc = static function (mixed $valor): string {
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
};

$estadoInstalacion = (string) ($identidad['estado'] ?? 'ninguno');
$tieneConsultorio = $estadoInstalacion === 'unico';

$nombreMarca = $tieneConsultorio
    ? trim((string) ($identidad['nombre'] ?? ''))
    : '';
$sloganMarca = $tieneConsultorio
    ? trim((string) ($identidad['slogan'] ?? ''))
    : '';
$logoUrl = $tieneConsultorio
    ? trim((string) ($identidad['logoUrl'] ?? ''))
    : '';
$iniciales = trim((string) ($identidad['iniciales'] ?? ''));

if ($tieneConsultorio && $nombreMarca === '') {
    $nombreMarca = 'Consultorio';
}

if ($tieneConsultorio && $iniciales === '') {
    $iniciales = 'C';
}

if ($sloganMarca !== '' && mb_strlen($sloganMarca) > 80) {
    $sloganMarca = '';
}

/* Mismo panel decorativo que Crear cuenta / login. */
$portadaUrl = null;
$slogan = 'Tu bienestar emocional es nuestra prioridad';
$nombreCons = $tieneConsultorio && $nombreMarca !== ''
    ? $nombreMarca
    : 'Atención psicológica';

$error = Session::get('error');
Session::remove('error');

?>

<div class="container py-2">

    <div class="row justify-content-center">

        <div class="col-lg-10">

            <div class="card auth-card auth-card--register auth-card--login shadow-lg border-0">

                <div class="row g-0 auth-brand-layout">

                    <?php require __DIR__ . '/partials/panel-portada.php'; ?>

                    <div class="auth-form-panel order-1 order-lg-2 p-4 p-lg-5">

                        <div class="auth-brand-header text-center text-lg-start mb-4">

                            <?php if ($tieneConsultorio): ?>

                                <?php if ($logoUrl !== ''): ?>
                                    <img
                                        src="<?= $esc($logoUrl); ?>"
                                        class="auth-brand-logo"
                                        alt="<?= $esc('Logotipo de ' . $nombreMarca); ?>"
                                    >
                                <?php else: ?>
                                    <div
                                        class="auth-brand-logo-fallback"
                                        aria-hidden="true"
                                    >
                                        <?= $esc($iniciales); ?>
                                    </div>
                                <?php endif; ?>

                                <h2 class="auth-brand-name">
                                    <?= $esc($nombreMarca); ?>
                                </h2>

                                <?php if ($sloganMarca !== ''): ?>
                                    <p class="auth-login-form-slogan">
                                        <?= $esc($sloganMarca); ?>
                                    </p>
                                <?php endif; ?>

                            <?php else: ?>

                                <div
                                    class="auth-brand-logo-fallback"
                                    aria-hidden="true"
                                >
                                    <?= $esc($iniciales !== '' ? $iniciales : 'AS'); ?>
                                </div>

                                <h2 class="auth-brand-name">
                                    Acceso al sistema
                                </h2>

                                <p class="auth-login-form-slogan">
                                    Instalación en proceso de configuración
                                </p>

                            <?php endif; ?>

                        </div>

                        <h4 class="fw-bold mb-2" style="color:#657166;">
                            Establecer nueva contraseña
                        </h4>
                        <p class="text-muted mb-4">
                            Por seguridad debes cambiar la contraseña temporal
                            antes de continuar.
                        </p>

                        <?php if (!empty($error)): ?>
                            <div
                                class="alert alert-danger py-2 mb-3"
                                role="alert"
                                id="cambioPasswordError"
                            >
                                <?= $esc($error); ?>
                            </div>
                        <?php endif; ?>

                        <form
                            method="POST"
                            action="<?= Helper::baseUrl('cambiar-contrasena'); ?>"
                            id="formCambioPasswordTemporal"
                            novalidate
                        >
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= $esc(Session::csrfToken()); ?>"
                            >

                            <div class="mb-3">
                                <label class="form-label" for="password">
                                    Nueva contraseña
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock-fill" aria-hidden="true"></i>
                                    </span>
                                    <input
                                        type="password"
                                        id="password"
                                        name="password"
                                        class="form-control"
                                        placeholder="Mínimo 8 caracteres"
                                        minlength="8"
                                        autocomplete="new-password"
                                        required
                                        autofocus
                                    >
                                    <button
                                        class="btn btn-outline-secondary"
                                        type="button"
                                        id="toggleNuevaPassword"
                                        data-target="password"
                                        aria-label="Mostrar u ocultar contraseña"
                                    >
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label" for="confirmar_password">
                                    Confirmar nueva contraseña
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                                    </span>
                                    <input
                                        type="password"
                                        id="confirmar_password"
                                        name="confirmar_password"
                                        class="form-control"
                                        placeholder="Repite la contraseña"
                                        minlength="8"
                                        autocomplete="new-password"
                                        required
                                    >
                                    <button
                                        class="btn btn-outline-secondary"
                                        type="button"
                                        id="togglePasswordConfirm"
                                        data-target="confirmar_password"
                                        aria-label="Mostrar u ocultar confirmación"
                                    >
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>

                            <p class="small text-muted mb-4">
                                Debe tener al menos 8 caracteres, una letra y un número.
                            </p>

                            <button
                                type="submit"
                                class="btn-login"
                                id="btnGuardarPasswordTemporal"
                            >
                                Guardar contraseña
                            </button>
                        </form>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<script>
(function () {
    var form = document.getElementById('formCambioPasswordTemporal');
    var btn = document.getElementById('btnGuardarPasswordTemporal');

    if (form && btn) {
        var enviando = false;
        form.addEventListener('submit', function () {
            if (enviando) {
                return;
            }
            enviando = true;
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
            btn.textContent = 'Guardando…';
        });
    }

    document.querySelectorAll('[data-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.getAttribute('data-target'));
            if (!input) {
                return;
            }
            var icon = button.querySelector('i');
            var visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            if (icon) {
                icon.className = visible ? 'bi bi-eye' : 'bi bi-eye-slash';
            }
        });
    });

    var errorBox = document.getElementById('cambioPasswordError');
    if (errorBox) {
        var password = document.getElementById('password');
        if (password) {
            password.focus();
        }
    }
})();
</script>
