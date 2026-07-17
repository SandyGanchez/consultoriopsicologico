<?php

use App\Helpers\Helper;

$rutaActual = parse_url(
    $_SERVER['REQUEST_URI'],
    PHP_URL_PATH
);

function menuPsicologoActivo(
    string $rutaActual,
    string $ruta
): string {
    return str_contains($rutaActual, $ruta)
        ? ' active'
        : '';
}

$nombreConsultorio =
    $consultorio['NombreCons'] ??
    'Consultorio psicológico';

$logo =
    $consultorio['LogotipoCons'] ?? '';

?>

<aside
    class="psicologo-sidebar"
    id="psicologoSidebar"
>

    <div class="psicologo-brand">

        <a
            href="<?= Helper::baseUrl('psicologo'); ?>"
            class="psicologo-brand-link"
        >

            <div class="psicologo-brand-logo">

                <?php if ($logo !== ''): ?>

                    <img
                        src="<?= Helper::baseUrl(
                            'assets/img/' . $logo
                        ); ?>"
                        alt="Logotipo"
                    >

                <?php else: ?>

                    <i class="bi bi-heart-pulse-fill"></i>

                <?php endif; ?>

            </div>

            <div>

                <strong class="psicologo-brand-name">
                    <?= htmlspecialchars(
                        $nombreConsultorio
                    ); ?>
                </strong>

                <small>Panel del especialista</small>

            </div>

        </a>

    </div>

    <nav class="psicologo-menu">

        <a
            href="<?= Helper::baseUrl('psicologo'); ?>"
            class="psicologo-menu-link<?= menuPsicologoActivo(
                $rutaActual,
                '/psicologo'
            ); ?>"
        >
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Inicio</span>
        </a>

        <a
            href="<?= Helper::baseUrl(
                'psicologo/calendario'
            ); ?>"
            class="psicologo-menu-link<?= menuPsicologoActivo(
                $rutaActual,
                '/psicologo/calendario'
            ); ?>"
        >
            <i class="bi bi-calendar-week"></i>
            <span>Mi agenda</span>
        </a>

        <a
            href="<?= Helper::baseUrl(
                'psicologo/pacientes'
            ); ?>"
            class="psicologo-menu-link<?= menuPsicologoActivo(
                $rutaActual,
                '/psicologo/pacientes'
            ); ?>"
        >
            <i class="bi bi-people-fill"></i>
            <span>Mis pacientes</span>
        </a>

        <a
            href="<?= Helper::baseUrl(
                'psicologo/expediente'
            ); ?>"
            class="psicologo-menu-link<?= menuPsicologoActivo(
                $rutaActual,
                '/psicologo/expediente'
            ); ?>"
        >
            <i class="bi bi-folder2-open"></i>
            <span>Expedientes</span>
        </a>
<a
    href="<?= Helper::baseUrl(
        'psicologo/perfil'
    ); ?>"
    class="psicologo-menu-link<?= menuPsicologoActivo(
        $rutaActual,
        '/psicologo/perfil'
    ); ?>"
>
    <i class="bi bi-person-badge-fill"></i>
    <span>Mi perfil</span>
</a>
<a
    href="<?= Helper::baseUrl(
        'psicologo/servicios'
    ); ?>"
    class="psicologo-menu-link<?= menuPsicologoActivo(
        $rutaActual,
        '/psicologo/servicios'
    ); ?>"
>
    <i class="bi bi-clipboard2-heart-fill"></i>
    <span>Mis servicios</span>
</a>
        <a
            href="<?= Helper::baseUrl(
                'psicologo/disponibilidad'
            ); ?>"
            class="psicologo-menu-link<?= menuPsicologoActivo(
                $rutaActual,
                '/psicologo/disponibilidad'
            ); ?>"
        >
            <i class="bi bi-clock-history"></i>
            <span>Disponibilidad</span>
        </a>

        <a
            href="<?= Helper::baseUrl(
                'psicologo/configuracion'
            ); ?>"
            class="psicologo-menu-link<?= menuPsicologoActivo(
                $rutaActual,
                '/psicologo/configuracion'
            ); ?>"
        >
            <i class="bi bi-gear-fill"></i>
            <span>Configuración</span>
        </a>

    </nav>

    <div class="psicologo-sidebar-footer">

        <a
            href="<?= Helper::baseUrl('logout'); ?>"
            class="psicologo-menu-link psicologo-logout"
        >
            <i class="bi bi-box-arrow-left"></i>
            <span>Cerrar sesión</span>
        </a>

    </div>

</aside>