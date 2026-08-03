<?php

/**
 * VISTA RETIRADA DE USO ACTIVO (instalación de consultorio único).
 * Conservada temporalmente hasta verificar dependencias y pruebas.
 * La portada pública es ahora home/index vía GET /.
 */

use App\Helpers\Helper;

$consultorios = is_array($consultorios ?? null) ? $consultorios : [];
$busquedaConsultorios = trim((string) ($busquedaConsultorios ?? ''));
$filtrosActivos = !empty($filtrosActivos);
$totalConsultorios = (int) ($totalConsultorios ?? count($consultorios));

?>

<section class="platform-hero" id="inicio">
    <div class="container py-5">
        <div class="row align-items-center g-4 min-vh-75">
            <div class="col-lg-7">
                <span class="platform-hero__eyebrow">PsicoMatch</span>
                <h1 class="platform-hero__title">
                    Encuentra el consultorio psicológico adecuado para ti
                </h1>
                <p class="platform-hero__lead">
                    Explora consultorios publicados, conoce a sus especialistas
                    y agenda tu cita con confianza.
                </p>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a
                        href="#consultorios"
                        class="btn rounded-pill px-4 text-white"
                        style="background:#99CDD8;"
                    >
                        Ver consultorios
                    </a>
                    <a
                        href="<?= Helper::baseUrl('registro'); ?>"
                        class="btn btn-outline-secondary rounded-pill px-4"
                    >
                        Crear cuenta
                    </a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="platform-hero__panel" id="contacto">
                    <h2 class="h5 mb-3" style="color:#657166;">Acceso rápido</h2>
                    <p class="text-muted mb-4">
                        ¿Ya tienes cuenta? Inicia sesión para agendar o administrar tu consultorio.
                    </p>
                    <a
                        href="<?= Helper::baseUrl('login'); ?>"
                        class="btn w-100 rounded-pill text-white mb-2"
                        style="background:#657166;"
                    >
                        Iniciar sesión
                    </a>
                    <a
                        href="<?= Helper::baseUrl('registro'); ?>"
                        class="btn w-100 btn-outline-secondary rounded-pill"
                    >
                        Registrarme
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="platform-clinics py-5" id="consultorios" aria-labelledby="consultorios-heading">
    <div class="container">

        <header class="text-center mb-4">
            <h2 id="consultorios-heading" class="platform-clinics__title">
                Consultorios publicados
            </h2>
            <p class="text-muted mx-auto" style="max-width:640px;">
                Busca por nombre, eslogan o ubicación y entra a la página pública de cada consultorio.
            </p>
        </header>

        <form
            method="GET"
            action="<?= Helper::baseUrl(''); ?>"
            class="specialists-search card border-0 shadow-sm mb-4"
            role="search"
        >
            <div class="card-body p-3 p-md-4">
                <div class="row g-3 align-items-end">
                    <div class="col-md-9">
                        <label for="busquedaConsultorio" class="form-label">
                            Buscar consultorio
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-search" aria-hidden="true"></i>
                            </span>
                            <input
                                type="search"
                                class="form-control"
                                id="busquedaConsultorio"
                                name="busqueda"
                                value="<?= htmlspecialchars($busquedaConsultorios, ENT_QUOTES, 'UTF-8'); ?>"
                                placeholder="Nombre, municipio o especialidad del consultorio"
                                maxlength="80"
                                autocomplete="off"
                            >
                        </div>
                    </div>
                    <div class="col-md-3 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn text-white flex-grow-1" style="background:#657166;">
                            Buscar
                        </button>
                        <a
                            href="<?= Helper::baseUrl(''); ?>#consultorios"
                            class="btn btn-outline-secondary flex-grow-1"
                        >
                            Limpiar
                        </a>
                    </div>
                </div>
            </div>
        </form>

        <?php if ($filtrosActivos): ?>
            <p class="mb-4" style="color:#657166;">
                <strong><?= $totalConsultorios; ?></strong>
                consultorio<?= $totalConsultorios === 1 ? '' : 's'; ?>
                encontrado<?= $totalConsultorios === 1 ? '' : 's'; ?>
                para “<?= htmlspecialchars($busquedaConsultorios, ENT_QUOTES, 'UTF-8'); ?>”
            </p>
        <?php endif; ?>

        <?php if ($consultorios === []): ?>
            <div class="specialists-section__empty text-center">
                <i class="bi bi-building specialists-section__empty-icon" aria-hidden="true"></i>
                <p class="specialists-section__empty-text mb-0">
                    <?= $filtrosActivos
                        ? 'No encontramos consultorios con esos criterios.'
                        : 'Por el momento no hay consultorios publicados.'; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($consultorios as $item): ?>
                    <?php
                        $clv = trim((string) ($item['ClvCons'] ?? ''));
                        $nombre = trim((string) ($item['NombreCons'] ?? ''));
                        $slogan = trim((string) ($item['Slogan'] ?? ''));
                        $desc = trim((string) ($item['Descripcion'] ?? ''));
                        $muni = trim((string) ($item['MunicipioDir'] ?? ''));
                        $edo = trim((string) ($item['EstadoDir'] ?? ''));
                        $ubicacion = trim(implode(', ', array_filter([$muni, $edo])));
                        $logo = Helper::logotipoConsultorioUrl(
                            $item['LogotipoCons'] ?? null
                        );
                        $url = Helper::baseUrl(
                            'consultorios/' . rawurlencode($clv)
                        );
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <article class="platform-clinic-card h-100">
                            <div class="platform-clinic-card__head">
                                <?php if ($logo !== ''): ?>
                                    <img
                                        src="<?= htmlspecialchars($logo, ENT_QUOTES, 'UTF-8'); ?>"
                                        alt="Logotipo de <?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>"
                                        class="platform-clinic-card__logo"
                                        width="64"
                                        height="64"
                                    >
                                <?php else: ?>
                                    <span class="platform-clinic-card__logo-fallback" aria-hidden="true">
                                        <?= htmlspecialchars(
                                            mb_strtoupper(mb_substr($nombre !== '' ? $nombre : 'C', 0, 1)),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    </span>
                                <?php endif; ?>
                                <div>
                                    <h3 class="platform-clinic-card__name">
                                        <?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>
                                    </h3>
                                    <?php if ($ubicacion !== ''): ?>
                                        <p class="platform-clinic-card__place mb-0">
                                            <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                            <?= htmlspecialchars($ubicacion, ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($slogan !== ''): ?>
                                <p class="platform-clinic-card__slogan">
                                    <?= htmlspecialchars($slogan, ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($desc !== ''): ?>
                                <p class="platform-clinic-card__desc">
                                    <?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            <?php endif; ?>

                            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" class="btn platform-clinic-card__cta">
                                Ver consultorio
                            </a>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</section>
