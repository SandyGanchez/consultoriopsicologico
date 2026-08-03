<?php

use App\Core\Session;
use App\Helpers\Helper;

$identidad = is_array($identidadConsultorio ?? null) ? $identidadConsultorio : [];
$correoIngresado = trim((string) ($correoIngresado ?? ''));

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

// Eslogan del formulario solo si es corto.
if ($sloganMarca !== '' && mb_strlen($sloganMarca) > 80) {
    $sloganMarca = '';
}

/*
 * Panel izquierdo: mismos valores que produce Crear cuenta
 * (variables-identidad.php con identidadPlataforma = true).
 * No cambiar el diseño del registro; solo reutilizar panel-portada.php.
 */
$portadaUrl = null;
$slogan = 'Tu bienestar emocional es nuestra prioridad';
$nombreCons = $tieneConsultorio && $nombreMarca !== ''
    ? $nombreMarca
    : 'Atención psicológica';

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
                            Iniciar sesión
                        </h4>
                        <p class="text-muted mb-4">
                            Accede a tu cuenta para continuar
                        </p>

                        <?php if (Session::has('success')): ?>
                            <div class="alert alert-success py-2 mb-3" role="status">
                                <?= $esc(Session::get('success')); ?>
                            </div>
                            <?php Session::remove('success'); ?>
                        <?php endif; ?>

                        <?php if (Session::has('error')): ?>
                            <div
                                class="alert alert-danger py-2 mb-3"
                                role="alert"
                                id="loginErrorGeneral"
                            >
                                <?= $esc(Session::get('error')); ?>
                            </div>
                            <?php Session::remove('error'); ?>
                        <?php endif; ?>

                        <form
                            method="POST"
                            action="<?= Helper::baseUrl('login'); ?>"
                            id="formLogin"
                            novalidate
                        >
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= $esc(Session::csrfToken()); ?>"
                            >

                            <div class="mb-3">
                                <label class="form-label" for="correoLogin">
                                    Correo electrónico
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-envelope-fill" aria-hidden="true"></i>
                                    </span>
                                    <input
                                        type="email"
                                        name="correo"
                                        id="correoLogin"
                                        class="form-control"
                                        placeholder="ejemplo@correo.com"
                                        value="<?= $esc($correoIngresado); ?>"
                                        autocomplete="email"
                                        required
                                        autofocus
                                    >
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label" for="password">
                                    Contraseña
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
                                        placeholder="********"
                                        autocomplete="current-password"
                                        required
                                    >
                                    <button
                                        class="btn btn-outline-secondary"
                                        type="button"
                                        id="togglePassword"
                                        aria-label="Mostrar u ocultar contraseña"
                                    >
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-check mb-4">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    id="remember"
                                    name="remember"
                                >
                                <label class="form-check-label" for="remember">
                                    Recordarme
                                </label>
                            </div>

                            <button
                                type="submit"
                                class="btn-login"
                                id="btnIniciarSesion"
                            >
                                Iniciar sesión
                            </button>
                        </form>

                        <div class="mt-4 text-center">
                            <a href="<?= Helper::baseUrl('forgot-password'); ?>">
                                ¿Olvidaste tu contraseña?
                            </a>
                        </div>

                        <div class="mt-3">
                            <details class="auth-ayuda-cuenta">
                                <summary class="text-center" style="cursor:pointer;color:#657166;">
                                    ¿Necesitas ayuda con tu cuenta?
                                </summary>
                                <form
                                    method="POST"
                                    action="<?= Helper::baseUrl('login/ayuda-cuenta'); ?>"
                                    class="mt-3"
                                    id="formAyudaCuenta"
                                    data-ayuda-once="1"
                                >
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= $esc(Session::csrfToken()); ?>"
                                    >
                                    <div class="mb-2">
                                        <label class="form-label" for="ayudaCorreo">Correo</label>
                                        <input
                                            type="email"
                                            name="correo"
                                            id="ayudaCorreo"
                                            class="form-control form-control-sm"
                                            maxlength="100"
                                            required
                                            autocomplete="email"
                                        >
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label" for="ayudaTipo">Tipo de problema</label>
                                        <select name="tipo" id="ayudaTipo" class="form-select form-select-sm" required>
                                            <option value="AUTENTICACION">No puedo iniciar sesión</option>
                                            <option value="CUENTA_BLOQUEADA">Cuenta bloqueada o inactiva</option>
                                            <option value="ACTIVACION">Activación de cuenta</option>
                                            <option value="RECUPERACION">Recuperación de acceso</option>
                                            <option value="CAMBIO_CORREO">Cambio de correo</option>
                                            <option value="OTRO_ACCESO">Otro problema de acceso</option>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label" for="ayudaDescripcion">Descripción breve</label>
                                        <textarea
                                            name="descripcion"
                                            id="ayudaDescripcion"
                                            class="form-control form-control-sm"
                                            rows="3"
                                            minlength="10"
                                            maxlength="1000"
                                            required
                                        ></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100" id="btnAyudaCuenta">
                                        Enviar solicitud de ayuda
                                    </button>
                                    <p class="small text-muted mt-2 mb-0">
                                        No incluyas contraseñas, información clínica ni datos sensibles.
                                        Esta solicitud no cambia contraseñas ni confirma si el correo existe.
                                    </p>
                                </form>
                                <script>
                                (function () {
                                    var form = document.getElementById('formAyudaCuenta');
                                    if (!form || form.getAttribute('data-ayuda-bound') === '1') return;
                                    form.setAttribute('data-ayuda-bound', '1');
                                    form.addEventListener('submit', function () {
                                        var btn = document.getElementById('btnAyudaCuenta');
                                        if (btn) {
                                            btn.disabled = true;
                                            btn.textContent = 'Enviando…';
                                        }
                                    });
                                })();
                                </script>
                            </details>
                        </div>

                        <div class="mt-3 text-center">
                            <a href="<?= Helper::baseUrl(''); ?>">
                                Volver al inicio
                            </a>
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<script>
(function () {
    var form = document.getElementById('formLogin');
    var btn = document.getElementById('btnIniciarSesion');
    if (!form || !btn) {
        return;
    }

    var enviando = false;
    form.addEventListener('submit', function () {
        if (enviando) {
            return;
        }
        enviando = true;
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        btn.textContent = 'Iniciando sesión…';
    });

    var errorBox = document.getElementById('loginErrorGeneral');
    if (errorBox) {
        var correo = document.getElementById('correoLogin');
        if (correo) {
            correo.focus();
        }
    }
})();
</script>
