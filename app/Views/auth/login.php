<?php

use App\Core\Session;
use App\Helpers\Helper;

?>

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-10">

            <div class="card auth-card shadow-lg border-0">

                <div class="row g-0">

                    <div class="col-lg-5 auth-left d-flex flex-column justify-content-center text-center p-5">

                        <img src="<?= Helper::baseUrl('assets/img/logo/logo.png'); ?>"
                             class="auth-logo mb-4"
                             alt="Logo">

                        <h2 class="fw-bold">
                            Bienvenido nuevamente
                        </h2>

                        <p class="mt-3">
                            Inicia sesión para administrar tus citas,
                            consultar tu historial clínico y mantener
                            un seguimiento de tu bienestar emocional.
                        </p>

                    </div>

                    <div class="col-lg-7 auth-right p-5">

                        <h3 class="fw-bold mb-4">
                            Iniciar sesión
                        </h3>

                        <?php if(Session::has('error')): ?>

                            <div class="alert alert-danger">
                                <?= Session::get('error'); ?>
                            </div>

                            <?php Session::remove('error'); ?>

                        <?php endif; ?>

                        <form method="POST"
                              action="<?= Helper::baseUrl('login'); ?>">
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

                                <label class="form-label">
                                    Correo electrónico
                                </label>

                                <div class="input-group">

                                    <span class="input-group-text">
                                        <i class="bi bi-envelope-fill"></i>
                                    </span>

                                    <input
                                        type="email"
                                        name="correo"
                                        class="form-control"
                                        placeholder="ejemplo@gmail.com"
                                        required>

                                </div>

                            </div>

                            <div class="mb-4">

                                <label class="form-label">
                                    Contraseña
                                </label>

                                <div class="input-group">

                                    <span class="input-group-text">
                                        <i class="bi bi-lock-fill"></i>
                                    </span>

                                    <input
                                        type="password"
                                        id="password"
                                        name="password"
                                        class="form-control"
                                        placeholder="********"
                                        required>

                                    <button
                                        class="btn btn-outline-secondary"
                                        type="button"
                                        id="togglePassword">

                                        <i class="bi bi-eye"></i>

                                    </button>

                                </div>

                            </div>

                            <div class="form-check mb-4">

                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    id="remember"
                                    name="remember">

                                <label
                                    class="form-check-label"
                                    for="remember">

                                    Recordarme

                                </label>

                            </div>

                            <button
                                type="submit"
                                class="btn-login">

                                Iniciar sesión

                            </button>

                        </form>

                        <div class="mt-4 text-center">

                           <a href="<?= \App\Helpers\Helper::baseUrl('forgot-password'); ?>">
    ¿Olvidaste tu contraseña?
</a>

                        </div>

                        <div class="mt-3 text-center">

                            ¿No tienes cuenta?

                            <a href="<?= Helper::baseUrl('registro'); ?>">
                                Crear cuenta
                            </a>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>