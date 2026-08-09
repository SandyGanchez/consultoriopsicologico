<?php

use App\Core\Session;
use App\Helpers\Helper;

require __DIR__ . '/partials/variables-identidad.php';

$correoMascarado = trim((string) ($correoMascarado ?? ''));
$segundosCooldown = max(0, (int) ($segundosCooldown ?? 0));

?>

<div class="container py-2">

    <div class="row justify-content-center">

        <div class="col-lg-10">

            <div class="card auth-card auth-card--register auth-card--login shadow-lg border-0">

                <div class="row g-0 auth-brand-layout">

                    <?php require __DIR__ . '/partials/panel-portada.php'; ?>

                    <div class="auth-form-panel order-1 order-lg-2 p-4 p-lg-5">

                        <?php require __DIR__ . '/partials/encabezado-marca.php'; ?>

                        <h4 class="fw-bold mb-2">
                            Verificar correo
                        </h4>

                        <p class="text-muted small mb-3">
                            Ingresa el código de 6 dígitos que enviamos a
                            <?php if ($correoMascarado !== ''): ?>
                                <strong><?= htmlspecialchars($correoMascarado, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <?php else: ?>
                                tu correo
                            <?php endif; ?>.
                        </p>

                        <?php if (Session::has('error')): ?>
                            <div class="alert alert-danger py-2 mb-3">
                                <?= htmlspecialchars(
                                    (string) Session::get('error'),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </div>
                            <?php Session::remove('error'); ?>
                        <?php endif; ?>

                        <?php if (Session::has('success')): ?>
                            <div class="alert alert-success py-2 mb-3">
                                <?= htmlspecialchars(
                                    (string) Session::get('success'),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </div>
                            <?php Session::remove('success'); ?>
                        <?php endif; ?>

                        <form
                            method="POST"
                            action="<?= Helper::baseUrl('verificar-correo'); ?>"
                            class="mb-3"
                        >
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars(
                                    Session::csrfToken(),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>"
                            >

                            <label class="form-label small mb-1" for="codigo">
                                Código de verificación
                            </label>
                            <input
                                type="text"
                                name="codigo"
                                id="codigo"
                                class="form-control form-control-lg text-center letter-spacing"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                pattern="[0-9]{6}"
                                maxlength="6"
                                minlength="6"
                                required
                                placeholder="••••••"
                            >

                            <button
                                type="submit"
                                class="btn btn-primary w-100 mt-3"
                            >
                                Verificar correo
                            </button>
                        </form>

                        <form
                            method="POST"
                            action="<?= Helper::baseUrl('verificar-correo/reenviar'); ?>"
                            class="text-center"
                        >
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars(
                                    Session::csrfToken(),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>"
                            >

                            <button
                                type="submit"
                                class="btn btn-link text-decoration-none"
                                id="btn-reenviar-verificacion"
                                <?= $segundosCooldown > 0 ? 'disabled' : ''; ?>
                            >
                                Reenviar código
                            </button>

                            <div
                                class="form-text small"
                                id="cooldown-verificacion"
                                data-segundos="<?= (int) $segundosCooldown; ?>"
                            >
                                <?php if ($segundosCooldown > 0): ?>
                                    Podrás reenviar en
                                    <span id="cooldown-num"><?= (int) $segundosCooldown; ?></span>
                                    segundo(s).
                                <?php endif; ?>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <a
                                href="<?= Helper::baseUrl('login'); ?>"
                                class="small text-muted"
                            >
                                Volver al inicio de sesión
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
    var box = document.getElementById('cooldown-verificacion');
    var btn = document.getElementById('btn-reenviar-verificacion');
    if (!box || !btn) return;

    var left = parseInt(box.getAttribute('data-segundos') || '0', 10);
    if (!left || left < 1) return;

    var num = document.getElementById('cooldown-num');
    var timer = setInterval(function () {
        left -= 1;
        if (left <= 0) {
            clearInterval(timer);
            btn.removeAttribute('disabled');
            box.textContent = '';
            return;
        }
        if (num) {
            num.textContent = String(left);
        }
    }, 1000);
})();
</script>
