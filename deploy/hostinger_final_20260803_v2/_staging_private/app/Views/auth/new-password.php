<?php

use App\Core\Session;
use App\Helpers\Helper;

$error = Session::get('error');

Session::remove('error');

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
                            <i class="bi bi-lock-fill"></i>
                        </div>

                        <h1>Nueva contraseña</h1>

                        <p>
                            El código se validó correctamente. Ahora crea una
                            contraseña nueva para recuperar tu cuenta.
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

                 <form
    action="<?= Helper::baseUrl('new-password'); ?>"
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

                        <div class="mb-3">

                            <label
                                for="password"
                                class="form-label recovery-label"
                            >
                                Nueva contraseña
                            </label>

                            <div class="recovery-input-group">

                                <span class="recovery-input-icon">
                                    <i class="bi bi-lock-fill"></i>
                                </span>

                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-control recovery-input"
                                    placeholder="Mínimo 8 caracteres"
                                    minlength="8"
                                    autocomplete="new-password"
                                    required
                                >

                                <button
                                    type="button"
                                    class="recovery-password-toggle"
                                    data-target="password"
                                >
                                    <i class="bi bi-eye"></i>
                                </button>

                            </div>

                        </div>

                        <div class="mb-3">

                            <label
                                for="confirmar_password"
                                class="form-label recovery-label"
                            >
                                Confirmar contraseña
                            </label>

                            <div class="recovery-input-group">

                                <span class="recovery-input-icon">
                                    <i class="bi bi-shield-lock-fill"></i>
                                </span>

                                <input
                                    type="password"
                                    id="confirmar_password"
                                    name="confirmar_password"
                                    class="form-control recovery-input"
                                    placeholder="Repite la contraseña"
                                    minlength="8"
                                    autocomplete="new-password"
                                    required
                                >

                                <button
                                    type="button"
                                    class="recovery-password-toggle"
                                    data-target="confirmar_password"
                                >
                                    <i class="bi bi-eye"></i>
                                </button>

                            </div>

                        </div>

                        <div class="password-requirements">

                            <span id="requirementLength">
                                <i class="bi bi-circle"></i>
                                Mínimo 8 caracteres
                            </span>

                            <span id="requirementLetter">
                                <i class="bi bi-circle"></i>
                                Al menos una letra
                            </span>

                            <span id="requirementNumber">
                                <i class="bi bi-circle"></i>
                                Al menos un número
                            </span>

                            <span id="requirementMatch">
                                <i class="bi bi-circle"></i>
                                Las contraseñas coinciden
                            </span>

                        </div>

                        <button
                            type="submit"
                            class="btn recovery-primary-button mt-4"
                        >
                            <i class="bi bi-check-circle-fill"></i>
                            Guardar contraseña
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
    const password = document.getElementById('password');
    const confirmation = document.getElementById('confirmar_password');

    const requirementLength =
        document.getElementById('requirementLength');

    const requirementLetter =
        document.getElementById('requirementLetter');

    const requirementNumber =
        document.getElementById('requirementNumber');

    const requirementMatch =
        document.getElementById('requirementMatch');

    const updateRequirement = (element, valid) => {
        element.classList.toggle('valid', valid);

        const icon = element.querySelector('i');

        icon.className = valid
            ? 'bi bi-check-circle-fill'
            : 'bi bi-circle';
    };

    const validate = () => {
        const value = password.value;
        const confirmationValue = confirmation.value;

        updateRequirement(
            requirementLength,
            value.length >= 8
        );

        updateRequirement(
            requirementLetter,
            /[A-Za-z]/.test(value)
        );

        updateRequirement(
            requirementNumber,
            /\d/.test(value)
        );

        updateRequirement(
            requirementMatch,
            value !== '' && value === confirmationValue
        );
    };

    password.addEventListener('input', validate);
    confirmation.addEventListener('input', validate);

    document
        .querySelectorAll('.recovery-password-toggle')
        .forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(
                    button.dataset.target
                );

                const icon = button.querySelector('i');

                const visible = input.type === 'text';

                input.type = visible ? 'password' : 'text';

                icon.className = visible
                    ? 'bi bi-eye'
                    : 'bi bi-eye-slash';
            });
        });
});
</script>