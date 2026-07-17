<?php
use App\Core\Session;
use App\Helpers\Helper;

$error = Session::get('error');
$success = Session::get('success');

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
                            <i class="bi bi-key-fill"></i>
                        </div>

                        <h1>Recuperar contraseña</h1>

                        <p>
                            Ingresa el correo electrónico asociado a tu cuenta.
                            Generaremos un código temporal para verificar tu
                            identidad.
                        </p>

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
    id="recoveryForm"
    action="<?= Helper::baseUrl('forgot-password'); ?>"
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

    <div class="mb-4">

        <label
            for="correo"
            class="form-label recovery-label"
        >
            Correo electrónico
        </label>

        <div class="recovery-input-group">

            <span class="recovery-input-icon">
                <i class="bi bi-envelope-fill"></i>
            </span>

            <input
                type="email"
                id="correo"
                name="correo"
                class="form-control recovery-input"
                placeholder="nombre@correo.com"
                autocomplete="email"
                maxlength="100"
                required
            >

        </div>

    </div>

    <button
        type="submit"
        id="sendCodeButton"
        class="btn recovery-primary-button"
    >
        <i class="bi bi-send-fill"></i>
        <span>Enviar código</span>
    </button>

</form>

                    <div class="recovery-footer">

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
    const form = document.getElementById('recoveryForm');
    const button = document.getElementById('sendCodeButton');

    if (!form || !button) {
        return;
    }

    form.addEventListener('submit', () => {
        if (!form.checkValidity()) {
            return;
        }

        button.disabled = true;

        button.innerHTML = `
            <span
                class="spinner-border spinner-border-sm"
                role="status"
                aria-hidden="true"
            ></span>
            <span>Enviando código...</span>
        `;
    });
});
</script>