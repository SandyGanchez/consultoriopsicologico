<?php

use App\Helpers\Helper;

$usuario = $_SESSION['usuario'] ?? [];

$nombre = trim(
    ($usuario['NombrePer'] ?? '') . ' ' .
    ($usuario['ApPatPer'] ?? '')
);

?>

<aside class="sidebar-paciente">

    <div class="sidebar-header">

        <img
            src="<?= Helper::baseUrl('assets/img/logo.png'); ?>"
            alt="PsicoMatch"
            class="sidebar-logo"
        >

        <h5>PsicoMatch</h5>

    </div>

    <div class="sidebar-user">

        <i class="bi bi-person-circle"></i>

        <span><?= htmlspecialchars($nombre); ?></span>

    </div>

    <ul class="sidebar-menu">

        <li>
            <a href="<?= Helper::baseUrl('paciente'); ?>">
                <i class="bi bi-house-door-fill"></i>
                Inicio
            </a>
        </li>

        <li>
            <a href="<?= Helper::baseUrl('paciente/mis-citas'); ?>">
                <i class="bi bi-calendar-check"></i>
                Mis citas
            </a>
        </li>

        <li>
            <a href="<?= Helper::baseUrl('paciente/agendar'); ?>">
                <i class="bi bi-calendar-plus"></i>
                Agendar cita
            </a>
        </li>

        <li>
            <a href="<?= Helper::baseUrl('paciente/historial'); ?>">
                <i class="bi bi-clock-history"></i>
                Historial
            </a>
        </li>

        <li>
            <a href="<?= Helper::baseUrl('paciente/perfil'); ?>">
                <i class="bi bi-person-vcard"></i>
                Perfil
            </a>
        </li>

        <li>
            <a href="<?= Helper::baseUrl('paciente/notificaciones'); ?>">
                <i class="bi bi-bell"></i>
                Notificaciones
            </a>
        </li>

    </ul>

    <div class="sidebar-footer">

        <a
            href="<?= Helper::baseUrl('logout'); ?>"
            class="btn btn-danger w-100"
        >
            <i class="bi bi-box-arrow-right"></i>
            Cerrar sesión
        </a>

    </div>

</aside>