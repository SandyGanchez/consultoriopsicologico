<?php

use App\Core\Session;
use App\Helpers\Helper;
use App\Services\InstalacionConsultorioService;

$usuario = Session::get('usuario') ?? [];

$rutaActual = parse_url(
    $_SERVER['REQUEST_URI'] ?? '',
    PHP_URL_PATH
);

$rutaActual = is_string($rutaActual)
    ? trim($rutaActual, '/')
    : '';

function enlaceAdministradorActivo(
    string $rutaActual,
    string $rutaObjetivo,
    bool $coincidenciaExacta = false
): string {
    $rutaObjetivo = trim(
        parse_url(
            Helper::baseUrl($rutaObjetivo),
            PHP_URL_PATH
        ) ?? '',
        '/'
    );

    if ($coincidenciaExacta) {
        return $rutaActual === $rutaObjetivo
            ? 'active'
            : '';
    }

    return (
        $rutaActual === $rutaObjetivo
        || str_starts_with(
            $rutaActual,
            $rutaObjetivo . '/'
        )
    )
        ? 'active'
        : '';
}

$dashboardActivo = enlaceAdministradorActivo(
    $rutaActual,
    'administrador',
    true
);

$consultorioActivo = enlaceAdministradorActivo(
    $rutaActual,
    'administrador/consultorio'
) || enlaceAdministradorActivo(
    $rutaActual,
    'administrador/consultorios'
);

$notificacionesActivo = enlaceAdministradorActivo(
    $rutaActual,
    'notificaciones'
);

$estadoInst = (new InstalacionConsultorioService())->resolver()['estado'] ?? 'ninguno';
$sinConsultorio = $estadoInst === 'ninguno';

$moduloIncidencias = false;
$contadorIncidencias = 0;
try {
    $servicioInc = new \App\Services\IncidenciaSoporteService();
    $moduloIncidencias = $servicioInc->moduloDisponible();
    if ($moduloIncidencias) {
        $contadorIncidencias = $servicioInc->contarAbiertasAdministrador();
    }
} catch (Throwable $e) {
    $moduloIncidencias = false;
    $contadorIncidencias = 0;
}

$incidenciasActivo = $moduloIncidencias
    ? enlaceAdministradorActivo($rutaActual, 'administrador/incidencias')
    : '';

?>

<aside
    class="admin-sidebar"
    id="adminSidebar"
>

    <a
        class="admin-sidebar-brand"
        href="<?= Helper::baseUrl('administrador'); ?>"
    >

        <div class="admin-logo">
            Ψ
        </div>

        <div>
            <span class="admin-brand-title">
                PsicoMatch
            </span>

            <span class="admin-brand-subtitle">
                Soporte de instalación
            </span>
        </div>

    </a>

    <nav
        class="admin-navigation"
        aria-label="Navegación administrativa"
    >

        <p class="admin-navigation-title">
            Panel general
        </p>

        <ul class="admin-nav-list">

            <li>
                <a
                    class="admin-nav-link"
                    href="<?= Helper::baseUrl('/'); ?>"
                    aria-label="Ir a la página principal"
                    title="Ir a la página principal"
                >
                    <i class="bi bi-house-door" aria-hidden="true"></i>
                    <span>Página principal</span>
                </a>
            </li>

            <li>
                <a
                    class="admin-nav-link <?= $dashboardActivo ?>"
                    href="<?= Helper::baseUrl('administrador'); ?>"
                >
                    <span class="admin-nav-icon">⌂</span>
                    <span>Inicio</span>
                </a>
            </li>

            <?php if ($sinConsultorio): ?>
                <li>
                    <a
                        class="admin-nav-link <?= $consultorioActivo ?>"
                        href="<?= Helper::baseUrl('administrador/consultorio/configurar'); ?>"
                    >
                        <span class="admin-nav-icon">✚</span>
                        <span>Configurar consultorio</span>
                    </a>
                </li>
            <?php else: ?>
                <li>
                    <a
                        class="admin-nav-link <?= $consultorioActivo ?>"
                        href="<?= Helper::baseUrl('administrador/consultorio'); ?>"
                    >
                        <span class="admin-nav-icon">✚</span>
                        <span>Cuenta del consultorio</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($moduloIncidencias): ?>
                <li>
                    <a
                        class="admin-nav-link <?= $incidenciasActivo ?>"
                        href="<?= Helper::baseUrl('administrador/incidencias'); ?>"
                    >
                        <span class="admin-nav-icon">⚑</span>
                        <span>Incidencias</span>
                        <?php if ($contadorIncidencias > 0): ?>
                            <span class="admin-notification-badge">
                                <?= $contadorIncidencias > 99
                                    ? '99+'
                                    : (int) $contadorIncidencias ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endif; ?>

            <li>
                <a
                    class="admin-nav-link <?= $notificacionesActivo ?>"
                    href="<?= Helper::baseUrl('notificaciones'); ?>"
                >
                    <span class="admin-nav-icon">🔔</span>
                    <span>Notificaciones</span>

                    <?php if (
                        !empty($totalNotificacionesNoLeidas)
                        && $totalNotificacionesNoLeidas > 0
                    ): ?>
                        <span class="admin-notification-badge">
                            <?= $totalNotificacionesNoLeidas > 99
                                ? '99+'
                                : (int) $totalNotificacionesNoLeidas ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>

        </ul>

    </nav>

    <div class="admin-sidebar-footer">

        <a
            class="admin-logout-link"
            href="<?= Helper::baseUrl('logout'); ?>"
            onclick="return confirm('¿Deseas cerrar tu sesión?');"
        >
            <span aria-hidden="true">↪</span>
            <span>Cerrar sesión</span>
        </a>

    </div>

</aside>
