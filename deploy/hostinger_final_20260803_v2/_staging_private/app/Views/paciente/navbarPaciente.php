<?php

$usuarioNavbar = is_array($usuario ?? null)
    ? $usuario
    : ($_SESSION['usuario'] ?? []);

if (!is_array($usuarioNavbar)) {
    $usuarioNavbar = [];
}

$nombreNavbar = trim(
    ($usuarioNavbar['NombrePer'] ?? '') . ' ' .
    ($usuarioNavbar['ApPatPer'] ?? '')
);

if ($nombreNavbar === '') {
    $nombreNavbar = trim(
        (string) ($usuarioNavbar['CorreoUsu'] ?? 'Paciente')
    );
}

?>

<header class="paciente-navbar">

    <div class="d-flex align-items-center gap-3">

        <button
            type="button"
            class="btn paciente-sidebar-toggle d-lg-none"
            id="pacienteSidebarToggle"
            aria-label="Abrir menú"
            aria-expanded="false"
            aria-controls="pacienteSidebar"
        >
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>

        <div class="paciente-navbar-title">

            <span>Panel del paciente</span>

            <small>
                Agenda y gestiona tus sesiones
            </small>

        </div>

    </div>

    <div class="paciente-navbar-actions">

        <?php require __DIR__ . '/../partials/campana-notificaciones.php'; ?>

        <div class="paciente-navbar-user">

            <?php
            $usuarioAvatar = $usuarioNavbar;
            $avatarClass = 'paciente-navbar-user-avatar';
            $avatarFallback = 'P';
            require __DIR__ . '/../partials/avatar-perfil.php';
            ?>

            <div class="d-none d-sm-block">

                <strong>
                    <?= htmlspecialchars(
                        $nombreNavbar,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </strong>

                <small>Paciente</small>

            </div>

        </div>

    </div>

</header>
