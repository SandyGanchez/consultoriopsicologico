<?php

use App\Core\Session;
use App\Helpers\Helper;

$error = Session::get('error');
$success = Session::get('success');
$correo = Session::get('recovery_email') ?? '';

Session::remove('error');
Session::remove('success');

$nombreConsultorio =
    $consultorio['NombreCons']
    ?? 'Consultorio Psicológico';

?>

<section class="recovery-page">

    <div class="recovery-decoration recovery-decoration-one"></div>
    <div class="recovery-decoration recovery-decoration-two"></div>

    <div class="container">

        <div class="row justify-content-center">

            <div class="col-12 col-md-9 col-lg-6 col-xl-5">

                <div class="recovery-card">

                    <div class="recovery-header">

                        <a
                            href="<?= Helper::baseUrl(''); ?>"
                            class="recovery-brand"
                        >
                            <span class="recovery-brand-icon">
                                <i class="bi bi-heart-pulse-fill"></i>
                            </span>

                            <span>
                                <?= htmlspecialchars($nombreConsultorio); ?>
                            </span>
                        </a>

                        <div class="recovery-icon">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>

                        <h1>Verifica tu código</h1>

                        <p>
                            Ingresa el código de seis dígitos generado para:
                        </p>

                        <span class="recovery-email-badge">
                            <i class="bi bi-envelope-check-fill"></i>

                            <?= htmlspecialchars($correo); ?>
                        </span>

                    </div>

                    <?php if ($error): ?>

                        <div class="alert alert-danger recovery-alert">

                            <i class="bi bi-exclamation-circle-fill"></i>

                            <span>
                                <?= htmlspecialchars($error); ?>
                            </span>

                        </div>

                    <?php endif; ?>

                    <?php if ($success): ?>

                        <div class="alert alert-success recovery-alert">

                            <i class="bi bi-check-circle-fill"></i>

                            <span>
                                <?= htmlspecialchars($success); ?>
                            </span>

                        </div>

                    <?php endif; ?>

                   

                    <form
                        action="<?= Helper::baseUrl('verify-code'); ?>"
                        method="POST"
                        class="recovery-form"
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

                        <label
                            for="codigo"
                            class="form-label recovery-label text-center d-block"
                        >
                            Código de verificación
                        </label>

                        <div class="verification-code-wrapper">

                            <input
                                type="text"
                                id="codigo"
                                name="codigo"
                                class="form-control verification-code-input"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                maxlength="6"
                                pattern="[0-9]{6}"
                                placeholder="000000"
                                required
                            >

                        </div>

                        <p class="recovery-help-text">
                            El código tiene una vigencia de diez minutos.
                        </p>

                        <button
                            type="submit"
                            class="btn recovery-primary-button"
                        >
                            <i class="bi bi-check2-circle"></i>
                            Verificar código
                        </button>

                    </form>

                    <div class="recovery-footer recovery-footer-column">

                        <a href="<?= Helper::baseUrl('forgot-password'); ?>">
                            <i class="bi bi-arrow-clockwise"></i>
                            Generar otro código
                        </a>

                        <a href="<?= Helper::baseUrl('login'); ?>">
                            <i class="bi bi-arrow-left"></i>
                            Regresar al inicio de sesión
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const codigoInput = document.getElementById('codigo');

    if (!codigoInput) {
        return;
    }

    codigoInput.addEventListener('input', () => {
        codigoInput.value = codigoInput.value
            .replace(/\D/g, '')
            .slice(0, 6);
    });

    codigoInput.focus();
});
</script>