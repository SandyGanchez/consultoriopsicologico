<?php

use App\Core\Session;
use App\Helpers\Helper;

$valido = (bool) ($valido ?? false);
$token = (string) ($token ?? '');
$nombre = (string) ($nombre ?? '');
$correoEnmascarado = (string) ($correoEnmascarado ?? '');
$error = $error ?? null;
$mensaje = (string) ($mensaje ?? '');

?>

<div class="activacion-card">

    <a class="activacion-brand" href="<?= Helper::baseUrl(''); ?>">
        <span aria-hidden="true">P</span>
        PsicoMatch
    </a>

    <h1>Activar cuenta</h1>

    <?php if (!$valido): ?>

        <div class="activacion-alert" role="alert">
            <?= htmlspecialchars(
                $mensaje !== ''
                    ? $mensaje
                    : 'El enlace de activación no es válido o ha expirado.',
                ENT_QUOTES,
                'UTF-8'
            ); ?>
        </div>

        <p class="activacion-lead">
            Solicita un nuevo enlace a quien te invitó o inicia sesión si ya activaste tu cuenta.
        </p>

        <a class="activacion-link" href="<?= Helper::baseUrl('login'); ?>">
            Ir al inicio de sesión
        </a>

    <?php else: ?>

        <p class="activacion-lead">
            Establece tu contraseña para activar el acceso. El enlace es de un solo uso.
        </p>

        <div class="activacion-meta">
            <div><strong><?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?></strong></div>
            <div><?= htmlspecialchars($correoEnmascarado, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>

        <?php if (!empty($error)): ?>

            <div class="activacion-alert" role="alert">
                <?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?>
            </div>

        <?php endif; ?>

        <form
            method="POST"
            action="<?= Helper::baseUrl('activar-cuenta'); ?>"
            autocomplete="off"
            id="formActivarCuenta"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(Session::csrfToken(), ENT_QUOTES, 'UTF-8'); ?>"
            >
            <input
                type="hidden"
                name="token"
                value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>"
            >

            <label for="password">Contraseña</label>
            <input
                type="password"
                id="password"
                name="password"
                minlength="8"
                autocomplete="new-password"
                required
            >

            <label for="confirmar_password">Confirmar contraseña</label>
            <input
                type="password"
                id="confirmar_password"
                name="confirmar_password"
                minlength="8"
                autocomplete="new-password"
                required
            >

            <ul class="activacion-reqs">
                <li>Mínimo 8 caracteres</li>
                <li>Debe incluir letras y números</li>
                <li>Ambas contraseñas deben coincidir</li>
            </ul>

            <button type="submit" class="activacion-btn" id="btnActivarCuenta">
                Activar cuenta
            </button>
        </form>

        <script>
            (function () {
                var form = document.getElementById('formActivarCuenta');
                var btn = document.getElementById('btnActivarCuenta');
                if (!form || !btn) return;
                form.addEventListener('submit', function () {
                    btn.disabled = true;
                    btn.textContent = 'Activando…';
                });
            })();
        </script>

    <?php endif; ?>

</div>
