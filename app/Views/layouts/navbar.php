<?php

use App\Core\Session;
use App\Helpers\Helper;

$consultorio = $consultorio ?? [];
$usuario = Session::get('usuario');

$sesionActiva = is_array($usuario);

$nombreConsultorio = trim(
    (string) ($consultorio['NombreCons'] ?? '')
);

if ($nombreConsultorio === '') {
    $nombreConsultorio = 'PsicoMatch';
}

$logotipo = trim(
    (string) ($consultorio['LogotipoCons'] ?? '')
);

if ($logotipo === '') {
    $logotipo = 'logo-temporal.png';
}

$rol = strtoupper(
    trim((string) ($usuario['RolUsu'] ?? ''))
);

$rutaInicio = '';

if ($sesionActiva) {
    switch ($rol) {
        case 'ADMINISTRADOR':
            $rutaInicio = 'administrador';
            break;

        case 'PACIENTE':
            $rutaInicio = 'paciente';
            break;

        case 'PSICOLOGO':
            $rutaInicio = 'psicologo';
            break;

        case 'CONSULTORIO':
            $rutaInicio = 'consultorio';
            break;

        default:
            $rutaInicio = '';
            break;
    }
}

?>

<nav
    class="navbar navbar-expand-lg navbar-light shadow-sm sticky-top"
    style="background: #FFFFFF;"
>
    <div class="container">

        <a
            class="navbar-brand d-flex align-items-center"
            href="<?= Helper::baseUrl($rutaInicio); ?>"
        >
            <img
                src="<?= Helper::baseUrl(
                    'assets/img/logo/' . rawurlencode($logotipo)
                ); ?>"
                alt="Logo de <?= htmlspecialchars(
                    $nombreConsultorio,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
                width="55"
                height="55"
                class="me-3 rounded-circle object-fit-cover"
            >

            <div>
                <div
                    class="fw-bold"
                    style="color: #657166;"
                >
                    <?= htmlspecialchars(
                        $nombreConsultorio,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </div>

                <small style="color: #99CDD8;">
                    Bienestar emocional
                </small>
            </div>
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#menu"
            aria-controls="menu"
            aria-expanded="false"
            aria-label="Mostrar menú"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div
            class="collapse navbar-collapse"
            id="menu"
        >
            <ul class="navbar-nav ms-auto align-items-lg-center">

                <?php if (!$sesionActiva): ?>

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="<?= Helper::baseUrl(); ?>"
                        >
                            Inicio
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="<?= Helper::baseUrl(
                                '#servicios'
                            ); ?>"
                        >
                            Servicios
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="<?= Helper::baseUrl(
                                '#horarios'
                            ); ?>"
                        >
                            Horarios
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="<?= Helper::baseUrl(
                                '#informacion'
                            ); ?>"
                        >
                            Contacto
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="<?= Helper::baseUrl(
                                '#redes'
                            ); ?>"
                        >
                            Redes
                        </a>
                    </li>

                    <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                        <a
                            class="btn btn-outline-secondary rounded-pill px-4"
                            href="<?= Helper::baseUrl('login'); ?>"
                        >
                            Iniciar sesión
                        </a>
                    </li>

                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        <a
                            class="btn rounded-pill px-4 text-white"
                            style="background: #99CDD8;"
                            href="<?= Helper::baseUrl(
                                'registro'
                            ); ?>"
                        >
                            Crear cuenta
                        </a>
                    </li>

                <?php else: ?>

                    <li class="nav-item">

                        <span class="nav-link text-muted">

                            <?= htmlspecialchars(
                                $usuario['CorreoUsu']
                                    ?? 'Usuario',
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>

                        </span>

                    </li>

                    <li class="nav-item ms-lg-3 mt-2 mt-lg-0">

                        <a
                            href="<?= Helper::baseUrl(
                                'logout'
                            ); ?>"
                            class="btn btn-outline-secondary rounded-pill px-4"
                        >
                            <i class="bi bi-box-arrow-right me-1"></i>
                            Cerrar sesión
                        </a>

                    </li>

                <?php endif; ?>

            </ul>
        </div>

    </div>
</nav>