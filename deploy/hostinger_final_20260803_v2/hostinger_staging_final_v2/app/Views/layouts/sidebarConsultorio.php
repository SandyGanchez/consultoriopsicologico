<?php

use App\Helpers\Helper;

$rutaActual = parse_url(
    $_SERVER['REQUEST_URI'],
    PHP_URL_PATH
);

$contadorIncidenciasCons = 0;
$moduloIncidenciasCons = false;
try {
    $servicioIncCons = new \App\Services\IncidenciaSoporteService();
    $moduloIncidenciasCons = $servicioIncCons->moduloDisponible();
    if ($moduloIncidenciasCons) {
        $contadorIncidenciasCons = $servicioIncCons->contarAbiertasConsultorio(
            (string) ($consultorio['ClvCons'] ?? '')
        );
    }
} catch (Throwable $e) {
    $moduloIncidenciasCons = false;
    $contadorIncidenciasCons = 0;
}

$esInicio = (bool) preg_match('#/consultorio/?$#', (string) $rutaActual);
$nombreCons = trim((string) ($consultorio['NombreCons'] ?? 'Consultorio'));
$logoUrl = !empty($consultorio['LogotipoCons'])
    ? Helper::logotipoConsultorioUrl((string) $consultorio['LogotipoCons'])
    : '';

$activo = static function (bool $cond): string {
    return $cond ? ' active is-active' : '';
};

?>

<aside
    class="consultorio-sidebar"
    id="consultorioSidebar"
    aria-hidden="true"
>

    <div class="consultorio-sidebar__brand consultorio-brand">
        <a
            href="<?= Helper::baseUrl('consultorio'); ?>"
            class="consultorio-brand-link"
        >
            <div class="consultorio-brand-logo" aria-hidden="true">
                <?php if ($logoUrl !== ''): ?>
                    <img
                        src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                        alt=""
                    >
                <?php else: ?>
                    <i class="bi bi-building"></i>
                <?php endif; ?>
            </div>
            <div>
                <span class="consultorio-brand-name">
                    <?= htmlspecialchars($nombreCons, ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <small>Panel del consultorio</small>
            </div>
        </a>

        <a
            href="<?= Helper::baseUrl('/'); ?>"
            class="consultorio-home-link"
        >
            <i class="bi bi-house-door-fill" aria-hidden="true"></i>
            <span>Página principal</span>
        </a>
    </div>

    <nav
        class="consultorio-sidebar__menu consultorio-menu"
        aria-label="Menú del consultorio"
    >

        <p class="consultorio-menu-group">Principal</p>

        <a
            href="<?= Helper::baseUrl('consultorio'); ?>"
            class="consultorio-menu-link<?= $activo($esInicio); ?>"
        >
            <i class="bi bi-speedometer2" aria-hidden="true"></i>
            <span>Inicio</span>
        </a>

        <a
            href="<?= Helper::baseUrl('consultorio/agenda'); ?>"
            class="consultorio-menu-link<?= $activo(
                str_contains((string) $rutaActual, '/consultorio/agenda')
            ); ?>"
        >
            <i class="bi bi-calendar3" aria-hidden="true"></i>
            <span>Agenda</span>
        </a>

        <a
            href="<?= Helper::baseUrl('consultorio/actividad-especialistas'); ?>"
            class="consultorio-menu-link<?= $activo(
                str_contains((string) $rutaActual, '/consultorio/actividad-especialistas')
            ); ?>"
        >
            <i class="bi bi-bar-chart-line-fill" aria-hidden="true"></i>
            <span>Actividad de especialistas</span>
        </a>

        <p class="consultorio-menu-group">Gestión</p>

        <a
            href="<?= Helper::baseUrl('consultorio/psicologos'); ?>"
            class="consultorio-menu-link<?= $activo(
                str_contains((string) $rutaActual, '/consultorio/psicologos')
            ); ?>"
        >
            <i class="bi bi-person-badge-fill" aria-hidden="true"></i>
            <span>Psicólogos</span>
        </a>

        <a
            href="<?= Helper::baseUrl('consultorio/servicios'); ?>"
            class="consultorio-menu-link<?= $activo(
                str_contains((string) $rutaActual, '/consultorio/servicios')
                && !str_contains((string) $rutaActual, '/sugerencias')
            ); ?>"
        >
            <i class="bi bi-clipboard2-heart-fill" aria-hidden="true"></i>
            <span>Servicios</span>
        </a>

        <a
            href="<?= Helper::baseUrl('consultorio/servicios/sugerencias'); ?>"
            class="consultorio-menu-link<?= $activo(
                str_contains((string) $rutaActual, '/consultorio/servicios/sugerencias')
            ); ?>"
        >
            <i class="bi bi-lightbulb" aria-hidden="true"></i>
            <span>Sugerencias</span>
        </a>

        <a
            href="<?= Helper::baseUrl('consultorio/horario'); ?>"
            class="consultorio-menu-link<?= $activo(
                str_contains((string) $rutaActual, '/consultorio/horario')
            ); ?>"
        >
            <i class="bi bi-clock-fill" aria-hidden="true"></i>
            <span>Horarios</span>
        </a>

        <a
            href="<?= Helper::baseUrl('consultorio/configuracion'); ?>"
            class="consultorio-menu-link<?= $activo(
                str_contains((string) $rutaActual, '/consultorio/configuracion')
            ); ?>"
        >
            <i class="bi bi-gear-fill" aria-hidden="true"></i>
            <span>Configuración</span>
        </a>

        <p class="consultorio-menu-group">Soporte</p>

        <?php if ($moduloIncidenciasCons): ?>
            <a
                href="<?= Helper::baseUrl('consultorio/incidencias'); ?>"
                class="consultorio-menu-link<?= $activo(
                    str_contains((string) $rutaActual, '/consultorio/incidencias')
                ); ?>"
            >
                <i class="bi bi-flag-fill" aria-hidden="true"></i>
                <span>Incidencias de acceso</span>
                <?php if ($contadorIncidenciasCons > 0): ?>
                    <span class="consultorio-menu-badge">
                        <?= $contadorIncidenciasCons > 99
                            ? '99+'
                            : (int) $contadorIncidenciasCons ?>
                    </span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <a
            href="<?= Helper::baseUrl('notificaciones'); ?>"
            class="consultorio-menu-link<?= $activo(
                str_contains((string) $rutaActual, '/notificaciones')
            ); ?>"
        >
            <i class="bi bi-bell-fill" aria-hidden="true"></i>
            <span>Notificaciones</span>
        </a>

    </nav>

    <div class="consultorio-sidebar__footer consultorio-sidebar-footer">
        <a
            href="<?= Helper::baseUrl('logout'); ?>"
            class="consultorio-menu-link consultorio-logout"
        >
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
            <span>Cerrar sesión</span>
        </a>
    </div>

</aside>
