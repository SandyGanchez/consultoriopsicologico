<?php

$nombreUsuario = trim(
    ($usuario['NombrePer'] ?? '') . ' ' .
    ($usuario['ApPatPer'] ?? '')
);

if ($nombreUsuario === '') {
    $nombreUsuario = 'Responsable';
}

?>

<nav class="consultorio-navbar">

    <button
        type="button"
        class="btn consultorio-sidebar-toggle d-lg-none"
        id="consultorioSidebarToggle"
        aria-label="Abrir menú"
    >
        <i class="bi bi-list"></i>
    </button>

    <div class="consultorio-navbar-title">

        <span>
            Panel administrativo
        </span>

        <small>
            <?= htmlspecialchars(
                $consultorio['NombreCons']
                ?? 'Consultorio'
            ); ?>
        </small>

    </div>

    <div class="consultorio-navbar-actions">

        <button
            type="button"
            class="consultorio-notification-button"
            aria-label="Notificaciones"
        >
            <i class="bi bi-bell"></i>

            <span class="consultorio-notification-count">
                0
            </span>
        </button>

        <div class="consultorio-user">

            <div class="consultorio-user-avatar">
                <i class="bi bi-person-fill"></i>
            </div>

            <div class="d-none d-md-block">

                <strong>
                    <?= htmlspecialchars($nombreUsuario); ?>
                </strong>

                <small>
                    Responsable
                </small>

            </div>

        </div>

    </div>

</nav>