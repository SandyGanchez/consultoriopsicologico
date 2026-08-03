<?php

use App\Core\Session;
use App\Helpers\Helper;

require __DIR__ . '/header.php';

$usuario = Session::get('usuario') ?? [];

$nombreAdministrador = trim(
    implode(
        ' ',
        array_filter([
            $usuario['NombrePer'] ?? '',
            $usuario['ApPatPer'] ?? ''
        ])
    )
);

if ($nombreAdministrador === '') {
    $nombreAdministrador =
        $usuario['nombre']
        ?? 'Administrador';
}

$correoAdministrador =
    $usuario['CorreoUsu']
    ?? '';

?>

<link
    rel="stylesheet"
    href="<?= Helper::baseUrl('assets/css/notificaciones-campana.css'); ?>"
>

<style>
    :root {
        --admin-primary: #657166;
        --admin-primary-dark: #4f5b51;
        --admin-primary-soft: #dce7df;

        --admin-accent: #99cdd8;
        --admin-accent-dark: #68aebc;
        --admin-accent-soft: #e4f2f5;

        --admin-background: #f4f8f6;
        --admin-surface: #ffffff;

        --admin-text: #3f4941;
        --admin-muted: #7b877e;
        --admin-border: #dfe8e2;

        --admin-success: #5f8b6d;
        --admin-warning: #d9a45f;
        --admin-danger: #b96b6b;

        --admin-sidebar-width: 280px;
        --admin-topbar-height: 82px;

        --admin-radius: 22px;
        --admin-shadow:
            0 12px 35px rgba(73, 91, 78, 0.09);
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        background: var(--admin-background);
        color: var(--admin-text);
        font-family:
            Inter,
            "Segoe UI",
            Arial,
            sans-serif;
    }

    .admin-layout {
        min-height: 100vh;
        display: flex;
    }

    /*
    ========================================
                    SIDEBAR
    ========================================
    */

    .admin-sidebar {
        position: fixed;
        top: 0;
        bottom: 0;
        left: 0;

        width: var(--admin-sidebar-width);

        display: flex;
        flex-direction: column;

        padding: 24px 18px;

        background:
            linear-gradient(
                180deg,
                #e1eee7 0%,
                #d7e4dc 50%,
                #cedbcf 100%
            );

        border-right: 1px solid
            rgba(101, 113, 102, 0.12);

        z-index: 1040;

        transition:
            transform 0.25s ease;
    }

    .admin-sidebar-brand {
        display: flex;
        align-items: center;
        gap: 13px;

        padding: 4px 4px 24px;

        border-bottom: 1px solid
            rgba(101, 113, 102, 0.18);

        text-decoration: none;
        color: var(--admin-primary-dark);
    }

    .admin-logo {
        width: 52px;
        height: 52px;

        display: flex;
        align-items: center;
        justify-content: center;

        flex-shrink: 0;

        border-radius: 17px;

        background: var(--admin-surface);
        color: var(--admin-primary);

        font-size: 1.4rem;
        font-weight: 800;

        box-shadow:
            0 8px 20px
            rgba(73, 91, 78, 0.10);
    }

    .admin-brand-title {
        display: block;

        color: var(--admin-primary-dark);

        font-size: 1.01rem;
        font-weight: 800;
        line-height: 1.25;
    }

    .admin-brand-subtitle {
        display: block;
        margin-top: 4px;

        color: var(--admin-muted);

        font-size: 0.79rem;
        font-weight: 500;
    }

    .admin-navigation {
        margin-top: 28px;
    }

    .admin-navigation-title {
        margin: 0 10px 10px;

        color: var(--admin-muted);

        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.13em;
        text-transform: uppercase;
    }

    .admin-nav-list {
        display: flex;
        flex-direction: column;
        gap: 8px;

        margin: 0;
        padding: 0;

        list-style: none;
    }

    .admin-nav-link {
        display: flex;
        align-items: center;
        gap: 14px;

        min-height: 54px;

        padding: 12px 16px;

        border-radius: 17px;

        color: var(--admin-primary-dark);

        font-size: 0.95rem;
        font-weight: 700;

        text-decoration: none;

        transition:
            background-color 0.2s ease,
            color 0.2s ease,
            transform 0.2s ease,
            box-shadow 0.2s ease;
    }

    .admin-nav-link:hover {
        background: rgba(255, 255, 255, 0.65);
        color: var(--admin-primary-dark);

        transform: translateX(3px);
    }

    .admin-nav-link.active {
        background: var(--admin-surface);
        color: var(--admin-primary-dark);

        box-shadow:
            0 10px 24px
            rgba(73, 91, 78, 0.10);
    }

    .admin-nav-icon {
        width: 35px;
        height: 35px;

        display: inline-flex;
        align-items: center;
        justify-content: center;

        flex-shrink: 0;

        border-radius: 12px;

        background: rgba(101, 113, 102, 0.09);
        color: var(--admin-primary);

        font-size: 1rem;
    }

    .admin-nav-link.active .admin-nav-icon {
        background: var(--admin-accent-soft);
        color: var(--admin-accent-dark);
    }

    .admin-sidebar-footer {
        margin-top: auto;
        padding-top: 22px;
    }

    .admin-logout-link {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;

        min-height: 50px;

        padding: 12px 16px;

        border: 1px solid
            rgba(185, 107, 107, 0.18);

        border-radius: 16px;

        background: #f4dfd7;
        color: #8b5757;

        font-weight: 800;
        text-decoration: none;

        transition:
            background-color 0.2s ease,
            transform 0.2s ease;
    }

    .admin-logout-link:hover {
        background: #eed1c6;
        color: #7e4d4d;

        transform: translateY(-1px);
    }

    /*
    ========================================
                CONTENIDO PRINCIPAL
    ========================================
    */

    .admin-main {
        width: calc(
            100% - var(--admin-sidebar-width)
        );

        min-height: 100vh;

        margin-left:
            var(--admin-sidebar-width);
    }

    /*
    ========================================
                    TOPBAR
    ========================================
    */

    .admin-topbar {
        position: sticky;
        top: 0;

        min-height: var(--admin-topbar-height);

        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;

        padding: 15px 34px;

        background:
            rgba(255, 255, 255, 0.94);

        border-bottom:
            1px solid var(--admin-border);

        backdrop-filter: blur(12px);

        z-index: 1020;
    }

    .admin-topbar-left {
        min-width: 0;
    }

    .admin-topbar-title {
        margin: 0;

        color: var(--admin-primary-dark);

        font-size: 1.15rem;
        font-weight: 800;
    }

    .admin-topbar-subtitle {
        margin: 3px 0 0;

        color: var(--admin-muted);

        font-size: 0.83rem;
    }

    .admin-topbar-right {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    /*
    ========================================
                NOTIFICACIONES
    ========================================
    */

    .admin-notifications {
        position: relative;
    }

    .admin-notification-button {
        position: relative;

        width: 45px;
        height: 45px;

        display: inline-flex;
        align-items: center;
        justify-content: center;

        border: 1px solid var(--admin-border);
        border-radius: 15px;

        background: var(--admin-surface);
        color: var(--admin-primary-dark);

        font-size: 1.15rem;

        cursor: pointer;

        transition:
            background-color 0.2s ease,
            transform 0.2s ease,
            box-shadow 0.2s ease;
    }

    .admin-notification-button:hover,
    .admin-notification-button:focus {
        background: var(--admin-accent-soft);

        box-shadow:
            0 8px 20px
            rgba(73, 91, 78, 0.10);

        transform: translateY(-1px);
    }

    .admin-notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;

        min-width: 20px;
        height: 20px;

        display: inline-flex;
        align-items: center;
        justify-content: center;

        padding: 0 5px;

        border: 2px solid var(--admin-surface);
        border-radius: 999px;

        background: var(--admin-danger);
        color: #ffffff;

        font-size: 0.66rem;
        font-weight: 800;
        line-height: 1;
    }

    .admin-notification-panel {
        position: absolute;
        top: calc(100% + 12px);
        right: 0;

        width: min(370px, calc(100vw - 28px));

        display: none;

        overflow: hidden;

        border: 1px solid var(--admin-border);
        border-radius: 19px;

        background: var(--admin-surface);

        box-shadow:
            0 18px 45px
            rgba(62, 79, 67, 0.17);

        z-index: 1080;
    }

    .admin-notifications.is-open
    .admin-notification-panel {
        display: block;
    }

    .admin-notification-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;

        padding: 18px 20px;

        border-bottom: 1px solid var(--admin-border);
    }

    .admin-notification-header h3 {
        margin: 0;

        color: var(--admin-primary-dark);

        font-size: 0.95rem;
        font-weight: 800;
    }

    .admin-notification-count {
        color: var(--admin-muted);

        font-size: 0.75rem;
        font-weight: 700;
    }

    .admin-notification-list {
        max-height: 340px;

        margin: 0;
        padding: 0;

        overflow-y: auto;

        list-style: none;
    }

    .admin-notification-item {
        padding: 16px 20px;

        border-bottom: 1px solid var(--admin-border);
    }

    .admin-notification-item:last-child {
        border-bottom: 0;
    }

    .admin-notification-item.unread {
        background:
            rgba(153, 205, 216, 0.11);
    }

    .admin-notification-title {
        display: block;

        margin-bottom: 5px;

        color: var(--admin-primary-dark);

        font-size: 0.84rem;
        font-weight: 800;
    }

    .admin-notification-message {
        margin: 0;

        color: var(--admin-muted);

        font-size: 0.78rem;
        line-height: 1.5;
    }

    .admin-notification-date {
        display: block;

        margin-top: 7px;

        color: #99a39c;

        font-size: 0.69rem;
    }

    .admin-notification-empty {
        padding: 30px 20px;

        color: var(--admin-muted);

        text-align: center;
        font-size: 0.82rem;
    }

    .admin-notification-footer {
        display: block;

        padding: 14px 20px;

        border-top: 1px solid var(--admin-border);

        background: #f8fbf9;
        color: var(--admin-primary);

        font-size: 0.79rem;
        font-weight: 800;
        text-align: center;
        text-decoration: none;
    }

    .admin-notification-footer:hover {
        color: var(--admin-primary-dark);
    }

    .admin-user {
        display: flex;
        align-items: center;
        gap: 12px;

        min-width: 0;
    }

    .admin-avatar {
        width: 45px;
        height: 45px;

        display: flex;
        align-items: center;
        justify-content: center;

        flex-shrink: 0;

        border-radius: 50%;

        background:
            linear-gradient(
                145deg,
                var(--admin-accent-soft),
                #d7e8df
            );

        color: var(--admin-primary-dark);

        font-size: 1rem;
        font-weight: 800;
    }

    .admin-user-name {
        display: block;

        max-width: 190px;

        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;

        color: var(--admin-primary-dark);

        font-size: 0.89rem;
        font-weight: 800;
    }

    .admin-user-role {
        display: block;

        color: var(--admin-muted);

        font-size: 0.75rem;
    }

    .admin-menu-button {
        display: none;

        width: 43px;
        height: 43px;

        border: 0;
        border-radius: 13px;

        background: var(--admin-primary-soft);
        color: var(--admin-primary-dark);

        font-size: 1.35rem;
    }

    /*
    ========================================
                ÁREA DEL DASHBOARD
    ========================================
    */

    .admin-content {
        padding: 34px;
    }

    .admin-content > .container,
    .admin-content > .container-fluid {
        max-width: 1450px;
    }

    /*
    ========================================
            ESTILOS PARA BOOTSTRAP
    ========================================
    */

    .admin-content h1,
    .admin-content h2,
    .admin-content h3,
    .admin-content h4,
    .admin-content h5,
    .admin-content h6 {
        color: var(--admin-primary-dark);
        font-weight: 800;
    }

    .admin-content .text-muted {
        color: var(--admin-muted) !important;
    }

    .admin-content .card {
        border: 1px solid
            rgba(101, 113, 102, 0.08) !important;

        border-radius: var(--admin-radius);

        background: var(--admin-surface);

        box-shadow:
            var(--admin-shadow) !important;

        overflow: hidden;
    }

    .admin-content .card-header {
        padding: 20px 24px;

        background:
            var(--admin-surface) !important;

        border-bottom:
            1px solid var(--admin-border);
    }

    .admin-content .card-body {
        padding: 24px;
    }

    .admin-content .btn {
        border-radius: 13px;

        padding: 10px 18px;

        font-weight: 700;

        transition:
            transform 0.2s ease,
            box-shadow 0.2s ease;
    }

    .admin-content .btn:hover {
        transform: translateY(-1px);
    }

    .admin-content .btn-primary {
        border-color: var(--admin-primary);
        background: var(--admin-primary);
    }

    .admin-content .btn-primary:hover,
    .admin-content .btn-primary:focus {
        border-color:
            var(--admin-primary-dark);

        background:
            var(--admin-primary-dark);

        box-shadow:
            0 8px 18px
            rgba(79, 91, 81, 0.2);
    }

    .admin-content .btn-outline-primary {
        border-color: var(--admin-primary);
        color: var(--admin-primary);
    }

    .admin-content .btn-outline-primary:hover {
        border-color: var(--admin-primary);
        background: var(--admin-primary);
        color: white;
    }

    .admin-content .btn-outline-secondary {
        border-color: var(--admin-accent-dark);
        color: var(--admin-accent-dark);
    }

    .admin-content .btn-outline-secondary:hover {
        border-color: var(--admin-accent-dark);
        background: var(--admin-accent-dark);
        color: white;
    }

    .admin-content .table {
        margin-bottom: 0;

        color: var(--admin-text);

        --bs-table-hover-bg:
            rgba(153, 205, 216, 0.10);
    }

    .admin-content .table thead th {
        padding: 15px 18px;

        border-bottom: 0;

        background:
            var(--admin-primary-soft);

        color:
            var(--admin-primary-dark);

        font-size: 0.77rem;
        font-weight: 800;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .admin-content .table tbody td {
        padding: 17px 18px;

        border-color: var(--admin-border);

        vertical-align: middle;
    }

    .admin-content .badge {
        padding: 7px 11px;

        border-radius: 999px;

        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.03em;
    }

    .admin-content .bg-success {
        background:
            var(--admin-success) !important;
    }

    .admin-content .bg-secondary {
        background:
            var(--admin-primary) !important;
    }

    .admin-content .bg-warning {
        background:
            #efd29d !important;
    }

    .admin-content .alert {
        border: 0;
        border-radius: 16px;

        box-shadow:
            0 8px 22px
            rgba(73, 91, 78, 0.06);
    }

    .admin-content .alert-success {
        background: #dfefe4;
        color: #496b53;
    }

    .admin-content .alert-danger {
        background: #f4dfdf;
        color: #895555;
    }

    .admin-content .form-control,
    .admin-content .form-select {
        min-height: 46px;

        border:
            1px solid var(--admin-border);

        border-radius: 13px;

        background-color: #fbfdfc;

        color: var(--admin-text);
    }

    .admin-content textarea.form-control {
        min-height: 115px;
    }

    .admin-content .form-control:focus,
    .admin-content .form-select:focus {
        border-color: var(--admin-accent-dark);

        box-shadow:
            0 0 0 0.2rem
            rgba(153, 205, 216, 0.23);
    }

    .admin-content .form-label {
        color: var(--admin-primary-dark);
        font-weight: 700;
    }

    /*
    ========================================
            TARJETAS DEL DASHBOARD
    ========================================
    */

    .admin-content .row.g-3.mb-4 > div
    .card {
        position: relative;
        overflow: hidden;

        background:
            linear-gradient(
                135deg,
                rgba(220, 232, 225, 0.95),
                rgba(255, 255, 255, 1) 55%,
                rgba(228, 242, 245, 0.92)
            );
    }

    .admin-content .row.g-3.mb-4 > div
    .card::after {
        position: absolute;
        right: -40px;
        bottom: -55px;

        width: 140px;
        height: 140px;

        border-radius: 50%;

        background:
            rgba(153, 205, 216, 0.15);

        content: "";
    }

    .admin-content .display-6 {
        color: var(--admin-primary-dark);
        font-weight: 800;
    }

    /*
    ========================================
                    OVERLAY
    ========================================
    */

    .admin-sidebar-overlay {
        position: fixed;
        inset: 0;

        display: none;

        background:
            rgba(42, 53, 45, 0.45);

        z-index: 1035;
    }

    /*
    ========================================
                  RESPONSIVE
    ========================================
    */

    @media (max-width: 991.98px) {
        .admin-sidebar {
            transform: translateX(-100%);

            box-shadow:
                12px 0 35px
                rgba(49, 63, 53, 0.16);
        }

        body.admin-sidebar-open
        .admin-sidebar {
            transform: translateX(0);
        }

        body.admin-sidebar-open
        .admin-sidebar-overlay {
            display: block;
        }

        .admin-main {
            width: 100%;
            margin-left: 0;
        }

        .admin-menu-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .admin-topbar {
            min-height: 72px;

            padding: 12px 20px;
        }

        .admin-content {
            padding: 24px 18px;
        }
    }

    @media (max-width: 575.98px) {
        .admin-topbar-title,
        .admin-topbar-subtitle {
            display: none;
        }

        .admin-user-name {
            max-width: 115px;
        }

        .admin-topbar-right {
            gap: 9px;
        }

        .admin-notification-button {
            width: 42px;
            height: 42px;

            border-radius: 13px;
        }

        .admin-user-email {
            display: none;
        }

        .admin-content {
            padding: 19px 12px;
        }

        .admin-content .container,
        .admin-content .container-fluid {
            padding-right: 0;
            padding-left: 0;
        }

        .admin-content .card-body {
            padding: 19px;
        }

        .admin-content .card-header {
            padding: 17px 19px;
        }
    }
</style>

<div class="admin-layout">

    <?php require __DIR__ . '/navbar_admin.php'; ?>

    <div
        class="admin-sidebar-overlay"
        id="adminSidebarOverlay"
    ></div>

    <main class="admin-main">

        <header class="admin-topbar">

            <div
                class="d-flex align-items-center
                       gap-3 admin-topbar-left"
            >
                <button
                    type="button"
                    class="admin-menu-button"
                    id="adminMenuButton"
                    aria-label="Abrir menú"
                >
                    ☰
                </button>

                <div>
                    <h2 class="admin-topbar-title">
                        Panel administrativo
                    </h2>

                    <p class="admin-topbar-subtitle">
                        Cuenta del consultorio de la instalación
                    </p>
                </div>
            </div>

            <div class="admin-topbar-right">

                <?php require __DIR__ . '/../partials/campana-notificaciones.php'; ?>

                <div class="admin-user">

                    <div class="admin-avatar">
                        <?= htmlspecialchars(
                            mb_strtoupper(
                                mb_substr(
                                    $nombreAdministrador,
                                    0,
                                    1
                                )
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>

                    <div>
                        <span class="admin-user-name">
                            <?= htmlspecialchars(
                                $nombreAdministrador,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </span>

                        <span class="admin-user-role">
                            Administrador
                        </span>
                    </div>

                </div>

            </div>

        </header>

        <section class="admin-content">

            <?php require $content; ?>

        </section>

    </main>

</div>

<script src="<?= Helper::baseUrl('assets/js/notificaciones-campana.js'); ?>"></script>

<script>
    document.addEventListener(
        'DOMContentLoaded',
        function () {
            const menuButton =
                document.getElementById(
                    'adminMenuButton'
                );

            const overlay =
                document.getElementById(
                    'adminSidebarOverlay'
                );

            // Campana unificada: ver notificaciones-campana.js
            const notifications = null;
            const notificationButton = null;

            function cerrarNotificaciones() {
                return;
            }

            function alternarNotificaciones(
                event
            ) {
                event.stopPropagation();

                if (!notifications) {
                    return;
                }

                const abierto =
                    notifications.classList.toggle(
                        'is-open'
                    );

                notificationButton.setAttribute(
                    'aria-expanded',
                    abierto ? 'true' : 'false'
                );
            }

            function abrirCerrarMenu() {
                document.body.classList.toggle(
                    'admin-sidebar-open'
                );
            }

            function cerrarMenu() {
                document.body.classList.remove(
                    'admin-sidebar-open'
                );
            }

            if (menuButton) {
                menuButton.addEventListener(
                    'click',
                    abrirCerrarMenu
                );
            }

            if (overlay) {
                overlay.addEventListener(
                    'click',
                    cerrarMenu
                );
            }

            if (notificationButton) {
                notificationButton.addEventListener(
                    'click',
                    alternarNotificaciones
                );
            }

            document.addEventListener(
                'click',
                function (event) {
                    if (
                        notifications &&
                        !notifications.contains(
                            event.target
                        )
                    ) {
                        cerrarNotificaciones();
                    }
                }
            );

            document.addEventListener(
                'keydown',
                function (event) {
                    if (event.key === 'Escape') {
                        cerrarNotificaciones();
                        cerrarMenu();
                    }
                }
            );

            document.querySelectorAll(
                '.admin-nav-link'
            ).forEach(function (enlace) {
                enlace.addEventListener(
                    'click',
                    function () {
                        if (
                            window.innerWidth < 992
                        ) {
                            cerrarMenu();
                        }
                    }
                );
            });

            window.addEventListener(
                'resize',
                function () {
                    if (
                        window.innerWidth >= 992
                    ) {
                        cerrarMenu();
                    }
                }
            );
        }
    );
</script>

<?php require __DIR__ . '/footer.php'; ?>