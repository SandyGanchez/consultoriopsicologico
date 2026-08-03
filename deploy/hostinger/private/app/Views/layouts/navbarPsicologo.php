<?php

use App\Helpers\Helper;

$nombrePsicologo = trim(
    ($usuario['NombrePer'] ?? '') . ' ' .
    ($usuario['ApPatPer'] ?? '')
);

if ($nombrePsicologo === '') {
    $nombrePsicologo = 'Psicólogo';
}

?>

<nav class="psychologist-navbar">

    <div class="d-flex align-items-center gap-3">

        <button
            type="button"
            class="btn sidebar-toggle d-lg-none"
            id="sidebarToggle"
            aria-label="Abrir menú">

            <i class="bi bi-list"></i>

        </button>

        <div class="navbar-search d-none d-md-flex">

            <i class="bi bi-search"></i>

            <input
                type="search"
                placeholder="Buscar pacientes o citas..."
                aria-label="Buscar">

        </div>

    </div>

    <div class="navbar-actions">

        <button
            type="button"
            class="navbar-icon-button"
            aria-label="Notificaciones">

            <i class="bi bi-bell"></i>

            <span class="notification-badge">
                0
            </span>

        </button>

        <div class="dropdown">

            <button
                class="profile-button dropdown-toggle"
                type="button"
                data-bs-toggle="dropdown"
                aria-expanded="false">

                <div class="profile-avatar">

                    <i class="bi bi-person-fill"></i>

                </div>

                <div class="profile-information d-none d-md-block">

                    <span class="profile-name">
                        <?= htmlspecialchars($nombrePsicologo); ?>
                    </span>

                    <small class="profile-role">
                        Psicólogo
                    </small>

                </div>

            </button>

            <ul class="dropdown-menu dropdown-menu-end border-0 shadow">

                <li>

                    <a
                        class="dropdown-item"
                        href="<?= Helper::baseUrl('psicologo/configuracion'); ?>">

                        <i class="bi bi-gear me-2"></i>
                        Configuración

                    </a>

                </li>

                <li>
                    <hr class="dropdown-divider">
                </li>

                <li>

                    <a
                        class="dropdown-item text-danger"
                        href="<?= Helper::baseUrl('logout'); ?>">

                        <i class="bi bi-box-arrow-right me-2"></i>
                        Cerrar sesión

                    </a>

                </li>

            </ul>

        </div>

    </div>

</nav>