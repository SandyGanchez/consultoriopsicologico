<?php

use App\Helpers\Helper;
use App\Core\Session;

?>

<div class="container py-2">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card auth-card shadow-lg border-0">
                <div class="row g-0">
                    <div class="col-lg-5 auth-left d-flex flex-column justify-content-center text-center p-4">
                        <img src="<?= Helper::baseUrl('assets/img/logo/logo-temporal.png'); ?>" class="auth-logo mb-3" style="max-width: 150px;" alt="Logo">
                        <h2 class="fw-bold">Crear una cuenta</h2>
                        <p class="mt-2 text-muted">
                            Regístrate para comenzar a gestionar tus citas, consultar tu historial clínico y dar seguimiento a tu bienestar emocional.
                        </p>
                    </div>

                    <div class="col-lg-7 auth-right p-4">
                        <h4 class="fw-bold mb-3">Registro de paciente</h4>

                        <?php if (Session::has('error')): ?>
                            <div class="alert alert-danger py-2 mb-3">
                                <?= Session::get('error'); ?>
                            </div>
                            <?php Session::remove('error'); ?>
                        <?php endif; ?>

                        <form method="POST" action="<?= Helper::baseUrl('registro'); ?>">
                             <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars(
            Session::csrfToken(),
            ENT_QUOTES,
            'UTF-8'
        ); ?>"
    >
                            <h6 class="mb-2 text-primary">Información personal</h6>
                            
                            <div class="row g-2">
                                <div class="col-md-12 mb-2">
                                    <label class="form-label small mb-1">Nombre</label>
                                    <input type="text" name="nombre" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small mb-1">Apellido paterno</label>
                                    <input type="text" name="apPat" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small mb-1">Apellido materno</label>
                                    <input type="text" name="apMat" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small mb-1">Fecha de nacimiento</label>
                                    <input type="date" name="fechaNacimiento" class="form-control form-control-sm" max="<?= date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small mb-1">Género</label>
                                    <select name="genero" class="form-select form-select-sm" required>
                                        <option value="">Seleccione...</option>
                                        <option value="Femenino">Femenino</option>
                                        <option value="Masculino">Masculino</option>
                                        <option value="Otro">Otro</option>
                                    </select>
                                </div>
                            </div>

                            <hr class="my-3">

                            <h6 class="mb-2 text-primary">Datos de acceso</h6>
                            <div class="row g-2">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small mb-1">Correo electrónico</label>
                                    <input type="email" name="correo" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small mb-1">Teléfono</label>
                                    <input type="text" name="telefono" maxlength="10" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small mb-1">Contraseña</label>
                                    <input type="password" name="password" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small mb-1">Confirmar contraseña</label>
                                    <input type="password" name="password2" class="form-control form-control-sm" required>
                                </div>
                            </div>

                            <button type="submit" class="btn w-100 text-white rounded-pill py-2 mt-3" style="background:#99CDD8;">
                                Crear cuenta
                            </button>
                        </form>

                        <div class="text-center mt-3">
                            <small>¿Ya tienes cuenta? <a href="<?= Helper::baseUrl('login'); ?>">Iniciar sesión</a></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>