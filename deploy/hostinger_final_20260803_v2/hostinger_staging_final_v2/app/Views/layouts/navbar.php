<?php

use App\Core\Session;
use App\Helpers\Helper;

$consultorio = is_array($consultorio ?? null) ? $consultorio : [];
$usuario = Session::get('usuario');
$sesionActiva = is_array($usuario);
$modoVistaPrevia = !empty($modoVistaPrevia);
$esPortadaPlataforma = !empty($esPortadaPlataforma);
$esNavbarGlobal = !empty($esNavbarGlobal) || $esPortadaPlataforma;

$nombreConsultorio = trim((string) ($consultorio['NombreCons'] ?? ''));
$clvConsNav = trim((string) ($consultorio['ClvCons'] ?? ''));

if ($esNavbarGlobal || $nombreConsultorio === '') {
    $nombreConsultorio = 'PsicoMatch';
}

$logotipo = ($esNavbarGlobal || $clvConsNav === '')
    ? ''
    : trim((string) ($consultorio['LogotipoCons'] ?? ''));

$logoUrl = $esNavbarGlobal
    ? Helper::logotipoPlataformaUrl()
    : Helper::logotipoConsultorioUrl($logotipo);

$rol = strtoupper(trim((string) ($usuario['RolUsu'] ?? '')));
$rutaPaginaPublica = trim((string) ($rutaPaginaPublica ?? ''));

$anclaInicio = static function (string $id) use ($rutaPaginaPublica): string {
    $base = $rutaPaginaPublica !== ''
        ? $rutaPaginaPublica
        : '';

    return rtrim(Helper::baseUrl($base), '/') . '#' . ltrim($id, '#');
};

$rutaMarca = Helper::baseUrl('');

$rutaPanel = $sesionActiva
    ? Helper::baseUrl(Helper::rutaPanelPorRol($rol))
    : Helper::baseUrl('login');

$csrfLogout = Session::csrfToken();

$mostrarNavConsultorio = !$esNavbarGlobal
    && $clvConsNav !== ''
    && (
        !$sesionActiva
        || in_array($rol, ['PACIENTE', 'CONSULTORIO', 'ADMINISTRADOR', 'PSICOLOGO'], true)
        || $modoVistaPrevia
    );

$enlacesConsultorio = [
    ['id' => 'inicio', 'etiqueta' => 'Inicio'],
    ['id' => 'nosotros', 'etiqueta' => 'Nosotros'],
    ['id' => 'servicios', 'etiqueta' => 'Servicios'],
    ['id' => 'especialistas', 'etiqueta' => 'Especialistas'],
    ['id' => 'horarios', 'etiqueta' => 'Horarios'],
    ['id' => 'ubicacion', 'etiqueta' => 'Ubicación'],
    ['id' => 'contacto', 'etiqueta' => 'Contacto'],
];

$renderAccionesSesion = static function () use (
    $sesionActiva,
    $rutaPanel,
    $csrfLogout
): void {
    if (!$sesionActiva) {
        ?>
        <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
            <a class="btn btn-outline-secondary rounded-pill px-3" href="<?= Helper::baseUrl('login'); ?>">
                Iniciar sesión
            </a>
        </li>
        <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
            <a class="btn rounded-pill px-4 text-white navbar-public__cta" href="<?= Helper::baseUrl('registro'); ?>">
                Crear cuenta
            </a>
        </li>
        <?php
        return;
    }
    ?>
    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
        <a class="btn btn-outline-secondary rounded-pill px-3" href="<?= htmlspecialchars($rutaPanel, ENT_QUOTES, 'UTF-8'); ?>">
            Ir a mi panel
        </a>
    </li>
    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
        <form method="POST" action="<?= Helper::baseUrl('logout'); ?>" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfLogout, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="btn btn-outline-secondary rounded-pill px-3">
                Cerrar sesión
            </button>
        </form>
    </li>
    <?php
};

?>

<nav
    id="navbarPublico"
    class="navbar navbar-expand-lg navbar-light bg-white sticky-top navbar-public"
    aria-label="<?= $esNavbarGlobal
        ? 'Navegación principal'
        : 'Navegación principal del consultorio'; ?>"
>
    <div class="container">

        <a
            class="navbar-brand d-flex align-items-center gap-2"
            href="<?= htmlspecialchars($rutaMarca, ENT_QUOTES, 'UTF-8'); ?>"
        >
            <?php if ($logoUrl !== ''): ?>
                <img
                    src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                    alt="Logotipo de <?= htmlspecialchars($nombreConsultorio, ENT_QUOTES, 'UTF-8'); ?>"
                    width="48"
                    height="48"
                    class="navbar-public__logo"
                >
            <?php else: ?>
                <span class="navbar-public__logo-fallback" aria-hidden="true">
                    <?= htmlspecialchars(
                        mb_strtoupper(mb_substr($nombreConsultorio, 0, 1)),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </span>
            <?php endif; ?>

            <span class="navbar-public__brand-text">
                <span class="navbar-public__name">
                    <?= htmlspecialchars($nombreConsultorio, ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <small class="navbar-public__tagline">
                    <?= $esNavbarGlobal
                        ? 'Bienestar emocional'
                        : 'Bienestar emocional'; ?>
                </small>
            </span>
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#menuPublico"
            aria-controls="menuPublico"
            aria-expanded="false"
            aria-label="Abrir o cerrar menú de navegación"
            id="btnMenuPublico"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="menuPublico">

            <?php if ($mostrarNavConsultorio): ?>

                <ul class="navbar-nav ms-auto align-items-lg-center navbar-public__links">
                    <?php foreach ($enlacesConsultorio as $enlace): ?>
                        <li class="nav-item">
                            <a
                                class="nav-link navbar-public__link"
                                href="<?= htmlspecialchars(
                                    $anclaInicio($enlace['id']),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>"
                                data-nav-section="<?= htmlspecialchars($enlace['id'], ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                <?= htmlspecialchars($enlace['etiqueta'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>

                    <?php $renderAccionesSesion(); ?>
                </ul>

            <?php else: ?>

                <ul class="navbar-nav ms-auto align-items-lg-center navbar-public__links">
                    <li class="nav-item">
                        <a class="nav-link navbar-public__link" href="<?= Helper::baseUrl(''); ?>">
                            Inicio
                        </a>
                    </li>
                    <?php $renderAccionesSesion(); ?>
                </ul>

            <?php endif; ?>

        </div>
    </div>
</nav>
