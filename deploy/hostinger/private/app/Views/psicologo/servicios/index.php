<?php

use App\Core\Session;
use App\Helpers\Helper;

$csrf = Session::csrfToken();
$misServicios = $misServicios ?? [];
$catalogoDisponible = $catalogoDisponible ?? [];

$totalServicios = count($misServicios);
$serviciosActivos = 0;
$serviciosInactivos = 0;

foreach ($misServicios as $servicioAsignado) {
    if (($servicioAsignado['EstatusAsignacion'] ?? '') === 'ACTIVA') {
        $serviciosActivos++;
    } else {
        $serviciosInactivos++;
    }
}

$serviciosCatalogo = count($catalogoDisponible);

function resumirTextoServicio(string $texto, int $limite = 110): string
{
    $texto = trim($texto);

    if ($texto === '') {
        return '';
    }

    if (mb_strlen($texto, 'UTF-8') <= $limite) {
        return $texto;
    }

    return mb_substr($texto, 0, $limite - 3, 'UTF-8') . '...';
}

?>

<section class="psychologist-services-page">

    <header class="psychologist-services-header">

        <span class="psychologist-services-header__eyebrow">
            Configuración profesional
        </span>

        <h1>Mis servicios</h1>

        <p>
            Administra los servicios que ofreces, su duración y el precio para tus
            pacientes.
        </p>

    </header>

    <div class="psychologist-services-summary" aria-label="Resumen de servicios">

        <article class="psychologist-services-stat">
            <span>Total de servicios</span>
            <strong><?= $totalServicios; ?></strong>
        </article>

        <article class="psychologist-services-stat">
            <span>Activos</span>
            <strong><?= $serviciosActivos; ?></strong>
        </article>

        <article class="psychologist-services-stat">
            <span>Inactivos</span>
            <strong><?= $serviciosInactivos; ?></strong>
        </article>

        <article class="psychologist-services-stat">
            <span>Disponibles en catálogo</span>
            <strong><?= $serviciosCatalogo; ?></strong>
        </article>

    </div>

    <?php if (!empty($_SESSION['success'])): ?>

        <div
            class="alert psychologist-services-alert psychologist-services-alert--success alert-dismissible fade show"
            role="alert"
        >
            <i class="bi bi-check-circle" aria-hidden="true"></i>
            <span>
                <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>
        </div>

        <?php unset($_SESSION['success']); ?>

    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>

        <div
            class="alert psychologist-services-alert psychologist-services-alert--error alert-dismissible fade show"
            role="alert"
        >
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            <span>
                <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>
        </div>

        <?php unset($_SESSION['error']); ?>

    <?php endif; ?>

    <section class="psychologist-services-section" aria-labelledby="mis-servicios-titulo">

        <div class="psychologist-services-section__head">

            <h2 id="mis-servicios-titulo">Mis servicios</h2>

            <p>
                Servicios que ya seleccionaste y configuraste para tus pacientes.
            </p>

        </div>

        <?php if ($misServicios === []): ?>

            <div class="psychologist-services-empty">

                <i class="bi bi-briefcase" aria-hidden="true"></i>

                <h3>Aún no has agregado servicios</h3>

                <p>
                    Selecciona un servicio del catálogo de tu consultorio y configura su precio
                    y duración.
                </p>

            </div>

        <?php else: ?>

            <div class="psychologist-services-grid">

                <?php foreach ($misServicios as $servicio): ?>

                    <?php
                    $activo =
                        ($servicio['EstatusAsignacion'] ?? '') === 'ACTIVA';
                    $precio = (float) ($servicio['PrecioServicio'] ?? 0);
                    $duracion = (int) ($servicio['DuracionMinutos'] ?? 0);
                    $descripcion = resumirTextoServicio(
                        (string) ($servicio['Descripcion'] ?? '')
                    );
                    $nombreServicio = (string) ($servicio['NombreServicio'] ?? '');
                    ?>

                    <article
                        class="psychologist-service-card<?= $activo
                            ? ''
                            : ' psychologist-service-card--inactive'; ?>"
                    >

                        <div class="psychologist-service-card__top">

                            <div class="psychologist-service-icon" aria-hidden="true">
                                <i class="bi bi-heart-pulse"></i>
                            </div>

                            <span
                                class="psychologist-service-status <?= $activo
                                    ? 'psychologist-service-status--active'
                                    : 'psychologist-service-status--inactive'; ?>"
                            >
                                <i
                                    class="bi <?= $activo
                                        ? 'bi-check-circle'
                                        : 'bi-pause-circle'; ?>"
                                    aria-hidden="true"
                                ></i>
                                <?= $activo ? 'Activo' : 'Inactivo'; ?>
                            </span>

                        </div>

                        <h3 class="psychologist-service-title">
                            <?= htmlspecialchars($nombreServicio, ENT_QUOTES, 'UTF-8'); ?>
                        </h3>

                        <?php if ($descripcion !== ''): ?>

                            <p class="psychologist-service-description">
                                <?= htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8'); ?>
                            </p>

                        <?php else: ?>

                            <p class="psychologist-service-description psychologist-service-description--empty">
                                Sin descripción disponible.
                            </p>

                        <?php endif; ?>

                        <div class="psychologist-service-meta">

                            <div class="psychologist-service-price">
                                <i class="bi bi-cash-coin" aria-hidden="true"></i>
                                <span>
                                    $<?= number_format($precio, 2); ?>
                                </span>
                            </div>

                            <div class="psychologist-service-duration">
                                <i class="bi bi-clock" aria-hidden="true"></i>
                                <span>
                                    <?= $duracion; ?>
                                    minuto<?= $duracion === 1 ? '' : 's'; ?>
                                </span>
                            </div>

                        </div>

                        <?php if ($precio <= 0): ?>

                            <p class="psychologist-service-help">
                                Servicio gratuito o precio pendiente de configurar.
                            </p>

                        <?php endif; ?>

                        <div class="psychologist-service-actions">

                            <a
                                href="<?= Helper::baseUrl(
                                    'psicologo/servicios/editar'
                                ); ?>?id=<?= urlencode(
                                    $servicio['ClvServ'] ?? ''
                                ); ?>"
                                class="btn btn-psychologist-secondary"
                                aria-label="Editar configuración de <?= htmlspecialchars($nombreServicio, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                Editar configuración
                            </a>

                            <form
                                method="POST"
                                action="<?= Helper::baseUrl(
                                    'psicologo/servicios/cambiar-estatus'
                                ); ?>"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
                                >

                                <input
                                    type="hidden"
                                    name="clvServ"
                                    value="<?= htmlspecialchars(
                                        $servicio['ClvServ'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>"
                                >

                                <input
                                    type="hidden"
                                    name="accion"
                                    value="<?= $activo ? 'inactivar' : 'activar'; ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn <?= $activo
                                        ? 'btn-psychologist-deactivate'
                                        : 'btn-psychologist-primary'; ?>"
                                    aria-label="<?= $activo
                                        ? 'Desactivar'
                                        : 'Activar'; ?> <?= htmlspecialchars($nombreServicio, ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    <i
                                        class="bi <?= $activo
                                            ? 'bi-pause-circle'
                                            : 'bi-play-circle'; ?>"
                                        aria-hidden="true"
                                    ></i>
                                    <?= $activo ? 'Desactivar' : 'Activar'; ?>
                                </button>

                            </form>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

    <section
        class="psychologist-services-catalog"
        aria-labelledby="catalogo-servicios-titulo"
    >

        <div class="psychologist-services-section__head">

            <h2 id="catalogo-servicios-titulo">Catálogo del consultorio</h2>

            <p>
                Servicios activos de tu consultorio que todavía puedes seleccionar.
            </p>

        </div>

        <?php if ($catalogoDisponible === []): ?>

            <div class="psychologist-services-empty">

                <i class="bi bi-collection" aria-hidden="true"></i>

                <h3>Catálogo al día</h3>

                <p>
                    Ya agregaste todos los servicios activos disponibles en tu consultorio.
                </p>

            </div>

        <?php else: ?>

            <div class="psychologist-services-grid">

                <?php foreach ($catalogoDisponible as $servicio): ?>

                    <?php
                    $descripcionCatalogo = resumirTextoServicio(
                        (string) ($servicio['Descripcion'] ?? '')
                    );
                    $nombreCatalogo = (string) ($servicio['NombreServicio'] ?? '');
                    $precioSugerido = (float) ($servicio['CostoServicio'] ?? 0);
                    $duracionSugerida = (int) ($servicio['DuracionMinutos'] ?? 0);
                    ?>

                    <article class="psychologist-service-card psychologist-service-card--catalog">

                        <div class="psychologist-service-card__top">

                            <div class="psychologist-service-icon" aria-hidden="true">
                                <i class="bi bi-journal-medical"></i>
                            </div>

                            <span class="psychologist-service-suggested">
                                Valores sugeridos
                            </span>

                        </div>

                        <h3 class="psychologist-service-title">
                            <?= htmlspecialchars($nombreCatalogo, ENT_QUOTES, 'UTF-8'); ?>
                        </h3>

                        <?php if ($descripcionCatalogo !== ''): ?>

                            <p class="psychologist-service-description">
                                <?= htmlspecialchars(
                                    $descripcionCatalogo,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </p>

                        <?php else: ?>

                            <p class="psychologist-service-description psychologist-service-description--empty">
                                Sin descripción disponible.
                            </p>

                        <?php endif; ?>

                        <p class="psychologist-service-help">
                            Sugerido por el consultorio
                        </p>

                        <div class="psychologist-service-meta">

                            <div class="psychologist-service-price">
                                <i class="bi bi-cash-coin" aria-hidden="true"></i>
                                <span>
                                    $<?= number_format($precioSugerido, 2); ?>
                                </span>
                            </div>

                            <div class="psychologist-service-duration">
                                <i class="bi bi-clock" aria-hidden="true"></i>
                                <span>
                                    <?= $duracionSugerida; ?>
                                    minuto<?= $duracionSugerida === 1 ? '' : 's'; ?>
                                </span>
                            </div>

                        </div>

                        <p class="psychologist-service-help">
                            Podrás personalizar el precio y la duración antes de agregarlo.
                        </p>

                        <div class="psychologist-service-actions">

                            <a
                                href="<?= Helper::baseUrl(
                                    'psicologo/servicios/seleccionar'
                                ); ?>?id=<?= urlencode(
                                    $servicio['ClvServ'] ?? ''
                                ); ?>"
                                class="btn btn-psychologist-primary"
                                aria-label="Agregar <?= htmlspecialchars($nombreCatalogo, ENT_QUOTES, 'UTF-8'); ?> a mis servicios"
                            >
                                <i class="bi bi-plus-circle" aria-hidden="true"></i>
                                Agregar a mis servicios
                            </a>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</section>
