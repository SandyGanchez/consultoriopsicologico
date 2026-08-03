<?php

$usuarioNavbar = is_array($usuario ?? null) ? $usuario : [];

$nombreUsuario = trim(
    ($usuarioNavbar['NombrePer'] ?? '') . ' ' .
    ($usuarioNavbar['ApPatPer'] ?? '')
);

if ($nombreUsuario === '') {
    $nombreUsuario = trim(
        (string) ($usuarioNavbar['CorreoUsu'] ?? 'Especialista')
    );
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

        <?php require __DIR__ . '/../../partials/campana-notificaciones.php'; ?>

        <div class="psicologo-user">

            <?php
            // Mismo patrón estructural que paciente-navbar-user-avatar.
            $usuarioAvatar = $usuarioNavbar;
            $avatarClass = 'psicologo-navbar-user-avatar';
            $avatarImageClass = '';
            $avatarEmptyClass = '';
            $avatarFallback = 'E';
            require __DIR__ . '/../../partials/avatar-perfil.php';
            unset($avatarImageClass, $avatarEmptyClass);
            ?>

            <div class="psicologo-user-meta d-none d-sm-block">

                <strong>
                    <?= htmlspecialchars(
                        $nombreUsuario,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </strong>

                <small>Especialista</small>

            </div>

        </div>

    </div>

</header>
