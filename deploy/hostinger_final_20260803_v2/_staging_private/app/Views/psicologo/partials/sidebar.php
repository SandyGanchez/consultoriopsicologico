<?php

use App\Helpers\Helper;

$rutaActual = parse_url(
    $_SERVER['REQUEST_URI'] ?? '',
    PHP_URL_PATH
) ?: '';

$usuarioSidebar = is_array($usuario ?? null) ? $usuario : [];

$nombreEspecialista = trim(
    (string) (($usuarioSidebar['NombrePer'] ?? '') . ' ' . ($usuarioSidebar['ApPatPer'] ?? ''))
);
if ($nombreEspecialista === '') {
    $nombreEspecialista = trim((string) ($usuarioSidebar['CorreoUsu'] ?? 'Especialista'));
}

$nombreConsultorio = trim((string) ($consultorio['NombreCons'] ?? 'Consultorio psicológico'));
$logo = trim((string) ($consultorio['LogotipoCons'] ?? ''));

$activoContiene = static function (string $rutaActual, string $segmento): string {
    return str_contains($rutaActual, '/' . trim($segmento, '/')) ? ' active' : '';
};

$esInicio = (bool) preg_match('#/psicologo/?$#', $rutaActual);
$activoAgenda = (
    $activoContiene($rutaActual, 'psicologo/agenda') !== ''
    || $activoContiene($rutaActual, 'psicologo/calendario') !== ''
) ? ' active' : '';
$activoPacientes = (
    $activoContiene($rutaActual, 'psicologo/pacientes') !== ''
    && $activoContiene($rutaActual, 'psicologo/expediente') === ''
) ? ' active' : '';
$activoExpediente = $activoContiene($rutaActual, 'psicologo/expediente');
$activoPerfil = $activoContiene($rutaActual, 'psicologo/perfil');
$activoServicios = $activoContiene($rutaActual, 'psicologo/servicios');
$activoDisponibilidad = $activoContiene($rutaActual, 'psicologo/disponibilidad');
$activoNotificaciones = $activoContiene($rutaActual, 'notificaciones');
$activoConfiguracion = $activoContiene($rutaActual, 'psicologo/configuracion');

?>

<aside
    class="psicologo-sidebar"
    id="psicologoSidebar"
    aria-label="Menú del especialista"
    aria-hidden="true"
>

    <div class="psicologo-sidebar__brand psicologo-brand">
        <a
            href="<?= Helper::baseUrl('psicologo'); ?>"
            class="psicologo-brand-link"
        >
            <div class="psicologo-brand-logo" aria-hidden="true">
                <?php if ($logo !== ''): ?>
                    <img
                        src="<?= htmlspecialchars(
                            Helper::logotipoConsultorioUrl($logo),
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        alt=""
                    >
                <?php else: ?>
                    <i class="bi bi-heart-pulse-fill"></i>
                <?php endif; ?>
            </div>
            <div>
                <strong class="psicologo-brand-name">
                    <?= htmlspecialchars($nombreEspecialista, ENT_QUOTES, 'UTF-8'); ?>
                </strong>
                <small>Panel del especialista</small>
                <?php if ($nombreConsultorio !== ''): ?>
                    <span class="psicologo-brand-consultorio">
                        <?= htmlspecialchars($nombreConsultorio, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                <?php endif; ?>
            </div>
        </a>
    </div>

    <div class="psicologo-sidebar__home">
        <a
            href="<?= Helper::baseUrl('/'); ?>"
            class="psicologo-home-link"
            aria-label="Ir a la página principal"
            title="Ir a la página principal"
        >
            <i class="bi bi-house-door" aria-hidden="true"></i>
            <span>Página principal</span>
        </a>
    </div>

    <nav
        class="psicologo-sidebar__menu psicologo-menu"
        aria-label="Navegación del panel"
    >

        <p class="psicologo-menu-group">Principal</p>

        <a
            href="<?= Helper::baseUrl('psicologo'); ?>"
            class="psicologo-menu-link<?= $esInicio ? ' active' : ''; ?>"
            <?= $esInicio ? 'aria-current="page"' : ''; ?>
        >
            <i class="bi bi-grid-1x2-fill" aria-hidden="true"></i>
            <span>Inicio</span>
        </a>

        <a
            href="<?= Helper::baseUrl('psicologo/agenda'); ?>"
            class="psicologo-menu-link<?= $activoAgenda; ?>"
            <?= $activoAgenda !== '' ? 'aria-current="page"' : ''; ?>
        >
            <i class="bi bi-calendar-week" aria-hidden="true"></i>
            <span>Agenda</span>
        </a>

        <a
            href="<?= Helper::baseUrl('psicologo/pacientes'); ?>"
            class="psicologo-menu-link<?= $activoPacientes; ?>"
            <?= $activoPacientes !== '' ? 'aria-current="page"' : ''; ?>
        >
            <i class="bi bi-people-fill" aria-hidden="true"></i>
            <span>Mis pacientes</span>
        </a>

        <p class="psicologo-menu-group">Gestión clínica</p>

        <a
            href="<?= Helper::baseUrl('psicologo/expediente'); ?>"
            class="psicologo-menu-link<?= $activoExpediente; ?>"
            <?= $activoExpediente !== '' ? 'aria-current="page"' : ''; ?>
        >
            <i class="bi bi-folder2-open" aria-hidden="true"></i>
            <span>Expedientes</span>
        </a>

        <a
            href="<?= Helper::baseUrl('psicologo/disponibilidad'); ?>"
            class="psicologo-menu-link<?= $activoDisponibilidad; ?>"
            <?= $activoDisponibilidad !== '' ? 'aria-current="page"' : ''; ?>
        >
            <i class="bi bi-clock-history" aria-hidden="true"></i>
            <span>Disponibilidad</span>
        </a>

        <a
            href="<?= Helper::baseUrl('psicologo/servicios'); ?>"
            class="psicologo-menu-link<?= $activoServicios; ?>"
            <?= $activoServicios !== '' ? 'aria-current="page"' : ''; ?>
        >
            <i class="bi bi-clipboard2-heart-fill" aria-hidden="true"></i>
            <span>Mis servicios</span>
        </a>

        <a
            href="<?= Helper::baseUrl('psicologo/perfil'); ?>"
            class="psicologo-menu-link<?= $activoPerfil; ?>"
            <?= $activoPerfil !== '' ? 'aria-current="page"' : ''; ?>
        >
            <i class="bi bi-person-badge-fill" aria-hidden="true"></i>
            <span>Perfil profesional</span>
        </a>

        <p class="psicologo-menu-group">Soporte</p>

        <a
            href="<?= Helper::baseUrl('notificaciones'); ?>"
            class="psicologo-menu-link<?= $activoNotificaciones; ?>"
            <?= $activoNotificaciones !== '' ? 'aria-current="page"' : ''; ?>
        >
            <i class="bi bi-bell-fill" aria-hidden="true"></i>
            <span>Notificaciones</span>
        </a>

        <a
            href="<?= Helper::baseUrl('psicologo/configuracion'); ?>"
            class="psicologo-menu-link<?= $activoConfiguracion; ?>"
            <?= $activoConfiguracion !== '' ? 'aria-current="page"' : ''; ?>
        >
            <i class="bi bi-gear-fill" aria-hidden="true"></i>
            <span>Configuración</span>
        </a>

    </nav>

    <div class="psicologo-sidebar__footer">
        <a
            href="<?= Helper::baseUrl('logout'); ?>"
            class="psicologo-sidebar__logout"
        >
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
            <span>Cerrar sesión</span>
        </a>
    </div>

</aside>
