<?php

use App\Helpers\Helper;

?>

<nav class="navbar navbar-expand-lg navbar-light shadow-sm sticky-top" style="background:#FFFFFF;">

    <div class="container">

        <a class="navbar-brand d-flex align-items-center" href="<?= Helper::baseUrl(); ?>">

            <img
                src="<?= Helper::baseUrl('assets/img/logo/' . ($consultorio['LogotipoCons'] ?: 'logo-temporal.png')); ?>"
                alt="Logo"
                width="55"
                class="me-3 rounded-circle">

            <div>

                <div class="fw-bold" style="color:#657166;">
                    <?= htmlspecialchars($consultorio['NombreCons']); ?>
                </div>

                <small style="color:#99CDD8;">
                    Bienestar emocional
                </small>

            </div>

        </a>

        <button class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#menu">

            <span class="navbar-toggler-icon"></span>

        </button>

        <div class="collapse navbar-collapse" id="menu">

            <ul class="navbar-nav ms-auto align-items-lg-center">

                <li class="nav-item">
                    <a class="nav-link" href="#">Inicio</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#servicios">Servicios</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#horarios">Horarios</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#informacion">Contacto</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#redes">Redes</a>
                </li>

                <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                    <a class="btn btn-outline-secondary rounded-pill px-4" href="<?= \App\Helpers\Helper::baseUrl('login'); ?>">
                        Iniciar sesión
                    </a>
                </li>

                <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                    <a class="btn rounded-pill px-4 text-white"
                       style="background:#99CDD8;"
                       href="<?= \App\Helpers\Helper::baseUrl('registro'); ?>">
                        Crear cuenta
                    </a>
                </li>
<a href="<?= \App\Helpers\Helper::baseUrl('logout'); ?>" 
   class="text-decoration-none text-muted d-inline-flex align-items-center ms-3"
   style="font-size: 0.85rem;">
    <i class="bi bi-box-arrow-right me-1"></i>
    Cerrar sesión
</a>
            </ul>

        </div>

    </div>

</nav>