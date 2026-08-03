<?php

use App\Helpers\Helper;

$rutaActual = parse_url(
    $_SERVER['REQUEST_URI'] ?? '',
    PHP_URL_PATH
) ?: '';

$usuarioSidebar = is_array($usuario ?? null)
    ? $usuario
    : ($_SESSION['usuario'] ?? []);

if (!is_array($usuarioSidebar)) {
    $usuarioSidebar = [];
}

$nombrePaciente = trim(
    ($usuarioSidebar['NombrePer'] ?? '') . ' ' .
    ($usuarioSidebar['ApPatPer'] ?? '')
);

if ($nombrePaciente === '') {
    $nombrePaciente = trim(
        (string) ($usuarioSidebar['CorreoUsu'] ?? 'Paciente')
    );
}

$urlLogo = Helper::logotipoConsultorioUrl(null, true);

if (!function_exists('menuPacienteActivo')) {
    function menuPacienteActivo(
        string $rutaActual,
        string $segmento,
        bool $exacto = false
    ): string {
        $rutaActual = rtrim($rutaActual, '/') ?: '/';
        $segmento = '/' . trim($segmento, '/');

        if ($exacto) {
            return preg_match(
                '#' . preg_quote($segmento, '#') . '$#',
                $rutaActual
            )
                ? ' active'
                : '';
        }

        return str_contains($rutaActual, $segmento)
            ? ' active'
            : '';
    }
}

$activoInicio = menuPacienteActivo(
    $rutaActual,
    'paciente',
    true
);
$activoAgendar = menuPacienteActivo(
    $rutaActual,
    'paciente/agendar'
);
$activoMisCitas = menuPacienteActivo(
    $rutaActual,
    'paciente/mis-citas'
) !== ''
    || menuPacienteActivo(
        $rutaActual,
        'paciente/cita-detalle'
    ) !== ''
        ? ' active'
        : '';
$activoHistorial = menuPacienteActivo(
    $rutaActual,
    'paciente/historial'
);
$activoNotificaciones = menuPacienteActivo(
    $rutaActual,
    'notificaciones'
);
$activoConfiguracion = menuPacienteActivo(
    $rutaActual,
    'paciente/configuracion'
);
$activoPerfil = (
    menuPacienteActivo($rutaActual, 'paciente/perfil') !== ''
    && $activoConfiguracion === ''
)
    ? ' active'
    : '';

?>

<aside
    class="paciente-sidebar"
    id="pacienteSidebar"
    aria-label="Menú del paciente"
>

    <div class="paciente-brand">

        <a
            href="<?= Helper::baseUrl('paciente'); ?>"
            class="paciente-brand-link"
        >

            <div class="paciente-brand-logo">

                <?php if ($urlLogo !== ''): ?>

                    <img
                        src="<?= htmlspecialchars(
                            $urlLogo,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        alt="PsicoMatch"
                    >

                <?php else: ?>

                    <i
                        class="bi bi-heart-pulse-fill"
                        aria-hidden="true"
                    ></i>

                <?php endif; ?>

            </div>

            <div>

                <strong class="paciente-brand-name">
                    PsicoMatch
                </strong>

                <small>Panel del paciente</small>

            </div>

        </a>

    </div>

    <nav class="paciente-menu" aria-label="Navegación principal">

        <a
            href="<?= Helper::baseUrl('paciente'); ?>"
            class="paciente-menu-link<?= $activoInicio; ?>"
            <?= $activoInicio !== '' ? 'aria-current="page"' : ''; ?>
        >
            <i class="bi bi-grid-1x2-fill" aria-hidden="true"></i>
            <span>Inicio</span>
        </a>

        <a
            href="<?= Helper::baseUrl('paciente/agendar'); ?>"
            class="paciente-menu-link<?= $activoAgendar; ?>"
            <?= $activoAgendar !== '' ? 'aria-current="page"' : ''; ?>
        >
            <i class="bi bi-calendar-plus" aria-hidden="true"></i>
            <span>Agendar cita</span>
        </a>

        <a
            href="<?= Helper::baseUrl('paciente/mis-citas'); ?>"
            class="paciente-menu-link<?= $activoMisCitas; ?>"
            <?= $activoMisCitas !== '' ? 'aria-current="page"' : ''; ?>
        >
            <i class="bi bi-calendar-check" aria-hidden="true"></i>
            <span>Mis citas</span>
        </a>

        <a
            href="<?= Helper::baseUrl('paciente/historial'); ?>"
            class="paciente-menu-link<?= $activoHistorial; ?>"
            <?= $activoHistorial !== '' ? 'aria-current="page"' : ''; ?>
        >
            <i class="bi bi-clock-history" aria-hidden="true"></i>
            <span>Historial</span>
        </a>

        <a
            href="<?= Helper::baseUrl('notificaciones'); ?>"
            class="paciente-menu-link<?= $activoNotificaciones; ?>"
            <?= $activoNotificaciones !== '' ? 'aria-current="page"' : ''; ?>
        >
            <i class="bi bi-bell-fill" aria-hidden="true"></i>
            <span>Mis notificaciones</span>
        </a>

        <a
            href="<?= Helper::baseUrl('paciente/perfil'); ?>"
            class="paciente-menu-link<?= $activoPerfil; ?>"
            <?= $activoPerfil !== '' ? 'aria-current="page"' : ''; ?>
        >
            <i class="bi bi-person-vcard" aria-hidden="true"></i>
            <span>Mi perfil</span>
        </a>

        <a
            href="<?= Helper::baseUrl('paciente/configuracion'); ?>"
            class="paciente-menu-link<?= $activoConfiguracion; ?>"
            <?= $activoConfiguracion !== '' ? 'aria-current="page"' : ''; ?>
        >
            <i class="bi bi-gear" aria-hidden="true"></i>
            <span>Configuración</span>
        </a>

    </nav>

    <div class="paciente-sidebar-footer">

        <div class="paciente-sidebar-user">

            <?php
            $usuarioAvatar = $usuarioSidebar;
            $avatarClass = 'paciente-sidebar-user-avatar';
            $avatarFallback = 'P';
            require __DIR__ . '/../partials/avatar-perfil.php';
            ?>

            <div class="paciente-sidebar-user-meta">

                <strong>
                    <?= htmlspecialchars(
                        $nombrePaciente,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </strong>

                <small>Paciente</small>

            </div>

        </div>

        <a
            href="<?= Helper::baseUrl('logout'); ?>"
            class="paciente-menu-link paciente-logout"
        >
            <i class="bi bi-box-arrow-left" aria-hidden="true"></i>
            <span>Cerrar sesión</span>
        </a>

    </div>

</aside>
