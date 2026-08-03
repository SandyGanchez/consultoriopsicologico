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

    <h1>Restablecer acceso</h1>

    <?php if (!$valido): ?>

        <div class="activacion-alert" role="alert">
            <?= htmlspecialchars(
                $mensaje !== ''
                    ? $mensaje
                    : 'El enlace de recuperación no es válido o ha expirado.',
                ENT_QUOTES,
                'UTF-8'
            ); ?>
        </div>

        <p class="activacion-lead">
            Solicita un nuevo enlace a soporte o inicia sesión si ya restableciste tu acceso.
        </p>

        <a class="activacion-link" href="<?= Helper::baseUrl('login'); ?>">
            Ir al inicio de sesión
        </a>

    <?php else: ?>

        <p class="activacion-lead">
            Define una nueva contraseña para tu cuenta de consultorio.
            Este enlace es temporal y de un solo uso. No cambia el estado
            institucional del consultorio.
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
            action="<?= Helper::baseUrl('restablecer-acceso-consultorio'); ?>"
            autocomplete="off"
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

            <label for="password">Nueva contraseña</label>
            <input
                type="password"
                name="password"
                id="password"
                required
                minlength="8"
                autocomplete="new-password"
            >

            <label for="confirmar_password">Confirmar contraseña</label>
            <input
                type="password"
                name="confirmar_password"
                id="confirmar_password"
                required
                minlength="8"
                autocomplete="new-password"
            >

            <p class="activacion-hint">
                Mínimo 8 caracteres, con letras y números.
            </p>

            <button type="submit">Guardar nueva contraseña</button>
        </form>

    <?php endif; ?>

</div>
