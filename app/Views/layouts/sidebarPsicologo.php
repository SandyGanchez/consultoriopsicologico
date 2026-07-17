<?php

use App\Helpers\Helper;

$rutaActual = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

?>

<aside class="psychologist-sidebar" id="psychologistSidebar">

    <div class="sidebar-brand">

        <a
            href="<?= Helper::baseUrl('psicologo'); ?>"
            class="sidebar-brand-link">

            <div class="sidebar-logo">

                <i class="bi bi-heart-pulse-fill"></i>

            </div>

            <div class="sidebar-brand-text">

                <span class="sidebar-title">
                    Consultorio
                </span>

                <small class="sidebar-subtitle">
                    Panel del psicólogo
                </small>

            </div>

        </a>

    </div>

    <nav class="sidebar-menu">

        <a
            href="<?= Helper::baseUrl('psicologo'); ?>"
            class="sidebar-link <?= $rutaActual === '/consultorio_psicologico/public/psicologo' ? 'active' : ''; ?>">

            <i class="bi bi-grid-1x2-fill"></i>

            <span>Inicio</span>

        </a>

        <a
            href="<?= Helper::baseUrl('psicologo/pacientes'); ?>"
            class="sidebar-link <?= str_contains($rutaActual, '/psicologo/pacientes') ? 'active' : ''; ?>">

            <i class="bi bi-people-fill"></i>

            <span>Pacientes</span>

        </a>

        <a
            href="<?= Helper::baseUrl('psicologo/calendario'); ?>"
            class="sidebar-link <?= str_contains($rutaActual, '/psicologo/calendario') ? 'active' : ''; ?>">

            <i class="bi bi-calendar3"></i>

            <span>Calendario</span>

        </a>

        <a
            href="<?= Helper::baseUrl('psicologo/expediente'); ?>"
            class="sidebar-link <?= str_contains($rutaActual, '/psicologo/expediente') ? 'active' : ''; ?>">

            <i class="bi bi-folder2-open"></i>

            <span>Expedientes</span>

        </a>

        <a
            href="<?= Helper::baseUrl('psicologo/servicios'); ?>"
            class="sidebar-link <?= str_contains($rutaActual, '/psicologo/servicios') ? 'active' : ''; ?>">

            <i class="bi bi-clipboard2-heart"></i>

            <span>Servicios</span>

        </a>

        <a
            href="<?= Helper::baseUrl('psicologo/horarios'); ?>"
            class="sidebar-link <?= str_contains($rutaActual, '/psicologo/horarios') ? 'active' : ''; ?>">

            <i class="bi bi-clock-fill"></i>

            <span>Horarios</span>

        </a>

        <a
            href="<?= Helper::baseUrl('psicologo/configuracion'); ?>"
            class="sidebar-link <?= str_contains($rutaActual, '/psicologo/configuracion') ? 'active' : ''; ?>">

            <i class="bi bi-gear-fill"></i>

            <span>Configuración</span>

        </a>

    </nav>

    <div class="sidebar-footer">

        <a
            href="<?= Helper::baseUrl('logout'); ?>"
            class="sidebar-link sidebar-logout">

            <i class="bi bi-box-arrow-right"></i>

            <span>Cerrar sesión</span>

        </a>

    </div>

</aside>