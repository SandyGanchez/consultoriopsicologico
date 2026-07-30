<?php

use App\Core\Session;
use App\Helpers\Helper;

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

$dashboardActivo =
    enlaceAdministradorActivo(
        $rutaActual,
        'administrador',
        true
    );

$consultoriosActivo =
    enlaceAdministradorActivo(
        $rutaActual,
        'administrador/consultorios'
    );

?>

<aside
    class="admin-sidebar"
    id="adminSidebar"
>

    <a
        class="admin-sidebar-brand"
        href="<?= Helper::baseUrl(
            'administrador'
        ); ?>"
    >

        <div class="admin-logo">
            Ψ
        </div>

        <div>
            <span class="admin-brand-title">
                PsicoMatch
            </span>

            <span class="admin-brand-subtitle">
                Administración
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
                    class="admin-nav-link
                           <?= $dashboardActivo ?>"
                    href="<?= Helper::baseUrl(
                        'administrador'
                    ); ?>"
                >
                    <span class="admin-nav-icon">
                        ⌂
                    </span>

                    <span>
                        Inicio
                    </span>
                </a>
            </li>

            <li>
                <a
                    class="admin-nav-link
                           <?= $consultoriosActivo ?>"
                    href="<?= Helper::baseUrl(
                        'administrador/consultorios'
                    ); ?>"
                >
                    <span class="admin-nav-icon">
                        ✚
                    </span>

                    <span>
                        Consultorios
                    </span>
                </a>
            </li>
 <li>
    <a
        class="admin-nav-link
               <?= $notificacionesActivo ?>"
        href="<?= Helper::baseUrl(
            'notificaciones'
        ); ?>"
    >
        <span class="admin-nav-icon">
            🔔
        </span>

        <span>
            Notificaciones
        </span>

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
            href="<?= Helper::baseUrl(
                'logout'
            ); ?>"
            onclick="
                return confirm(
                    '¿Deseas cerrar tu sesión?'
                );
            "
        >
            <span aria-hidden="true">
                ↪
            </span>

            <span>
                Cerrar sesión
            </span>
        </a>

    </div>

</aside>