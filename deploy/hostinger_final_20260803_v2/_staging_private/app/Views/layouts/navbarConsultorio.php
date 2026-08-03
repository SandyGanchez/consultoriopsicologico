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
        aria-controls="consultorioSidebar"
        aria-expanded="false"
        aria-label="Abrir menú de navegación"
    >
        <i class="bi bi-list" aria-hidden="true"></i>
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

        <?php require __DIR__ . '/../partials/campana-notificaciones.php'; ?>

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
