<?php

use App\Helpers\Helper;

$rutaActual = parse_url(
    $_SERVER['REQUEST_URI'],
    PHP_URL_PATH
);

?>

<aside
    class="consultorio-sidebar"
    id="consultorioSidebar"
>

    <div class="consultorio-brand">

        <a
            href="<?= Helper::baseUrl('consultorio'); ?>"
            class="consultorio-brand-link"
        >

            <div class="consultorio-brand-logo">

                <?php if (!empty($consultorio['LogotipoCons'])): ?>

                    <img
                        src="<?= Helper::baseUrl(
                            'assets/img/logo/' .
                            $consultorio['LogotipoCons']
                        ); ?>"
                        alt="Logotipo"
                    >

                <?php else: ?>

                    <i class="bi bi-heart-pulse-fill"></i>

                <?php endif; ?>

            </div>

            <div>

                <span class="consultorio-brand-name">
                    <?= htmlspecialchars(
                        $consultorio['NombreCons']
                        ?? 'Consultorio'
                    ); ?>
                </span>

                <small>
                    Administración
                </small>

            </div>

        </a>

    </div>

    <nav class="consultorio-menu">

        <a
            href="<?= Helper::baseUrl('consultorio'); ?>"
            class="consultorio-menu-link
            <?= str_ends_with($rutaActual, '/consultorio')
                ? 'active'
                : ''; ?>"
        >
            <i class="bi bi-house-door-fill"></i>
            <span>Inicio</span>
        </a>

        <a
            href="<?= Helper::baseUrl('consultorio/agenda'); ?>"
            class="consultorio-menu-link
            <?= str_contains($rutaActual, '/consultorio/agenda')
                ? 'active'
                : ''; ?>"
        >
            <i class="bi bi-calendar3"></i>
            <span>Agenda</span>
        </a>

        <a
            href="<?= Helper::baseUrl('consultorio/psicologos'); ?>"
            class="consultorio-menu-link
            <?= str_contains($rutaActual, '/consultorio/psicologos')
                ? 'active'
                : ''; ?>"
        >
            <i class="bi bi-person-badge-fill"></i>
            <span>Psicólogos</span>
        </a>

        <a
            href="<?= Helper::baseUrl('consultorio/servicios'); ?>"
            class="consultorio-menu-link
            <?= str_contains($rutaActual, '/consultorio/servicios')
                ? 'active'
                : ''; ?>"
        >
            <i class="bi bi-clipboard2-heart-fill"></i>
            <span>Servicios</span>
        </a>

        <a
            href="<?= Helper::baseUrl('consultorio/configuracion'); ?>"
            class="consultorio-menu-link
            <?= str_contains($rutaActual, '/consultorio/configuracion')
                ? 'active'
                : ''; ?>"
        >
            <i class="bi bi-gear-fill"></i>
            <span>Configuración</span>
        </a>

        <a
            href="<?= Helper::baseUrl('consultorio/notificaciones'); ?>"
            class="consultorio-menu-link
            <?= str_contains($rutaActual, '/consultorio/notificaciones')
                ? 'active'
                : ''; ?>"
        >
            <i class="bi bi-bell-fill"></i>
            <span>Notificaciones</span>
        </a>

    </nav>

    <div class="consultorio-sidebar-footer">

        <a
            href="<?= Helper::baseUrl('logout'); ?>"
            class="consultorio-menu-link consultorio-logout"
        >
            <i class="bi bi-box-arrow-right"></i>
            <span>Cerrar sesión</span>
        </a>

    </div>

</aside>