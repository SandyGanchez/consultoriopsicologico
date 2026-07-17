<?php

$nombreUsuario = trim(
    ($usuario['NombrePer'] ?? '') . ' ' .
    ($usuario['ApPatPer'] ?? '')
);

if ($nombreUsuario === '') {
    $nombreUsuario =
        $usuario['CorreoUsu'] ??
        'Especialista';
}

?>

<header class="psicologo-navbar">

    <div class="d-flex align-items-center gap-3">

        <button
            type="button"
            class="btn psicologo-sidebar-toggle d-lg-none"
            id="psicologoSidebarToggle"
            aria-label="Abrir menú"
        >
            <i class="bi bi-list"></i>
        </button>

        <div class="psicologo-navbar-title">

            <span>Panel del especialista</span>

            <small>
                Administra tus sesiones y pacientes
            </small>

        </div>

    </div>

    <div class="psicologo-navbar-actions">

        <button
            type="button"
            class="psicologo-notification-button"
            title="Notificaciones"
        >
            <i class="bi bi-bell"></i>

            <span class="psicologo-notification-count">
                0
            </span>
        </button>

        <div class="psicologo-user">

            <div class="psicologo-user-avatar">
                <i class="bi bi-person-heart"></i>
            </div>

            <div class="d-none d-sm-block">

                <strong>
                    <?= htmlspecialchars(
                        $nombreUsuario
                    ); ?>
                </strong>

                <small>Especialista</small>

            </div>

        </div>

    </div>

</header>