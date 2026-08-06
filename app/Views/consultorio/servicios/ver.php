<?php

use App\Core\Session;
use App\Helpers\Helper;

$csrf = Session::csrfToken();
$activo = ($servicio['EstatusServicio'] ?? '') === 'ACTIVO';

?>

<section class="clinic-services-page clinic-services-header">

    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">

        <div>

            <span class="consultorio-page-eyebrow">
                Catálogo general
            </span>

            <h1>
                <?= htmlspecialchars(
                    $servicio['NombreServicio'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </h1>

            <p>
                Información general del servicio dentro del catálogo del consultorio.
            </p>

        </div>

        <div class="d-flex flex-wrap gap-2">

            <a
                href="<?= Helper::baseUrl(
                    'consultorio/servicios/editar'
                ); ?>?id=<?= urlencode($servicio['ClvServ'] ?? ''); ?>"
                class="btn btn-clinic-primary"
            >
                <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>
                Editar
            </a>

            <a
                href="<?= Helper::baseUrl('consultorio/servicios'); ?>"
                class="btn btn-clinic-secondary"
            >
                Volver al listado
            </a>

        </div>

    </div>

    <div class="clinic-services-card">

        <div class="clinic-service-detail-grid">

            <div class="clinic-service-detail-item">
                <span>Estatus</span>
                <strong>
                    <?php if ($activo): ?>
                        <span class="clinic-service-status clinic-service-status--active">
                            Activo
                        </span>
                    <?php else: ?>
                        <span class="clinic-service-status clinic-service-status--inactive">
                            Inactivo
                        </span>
                    <?php endif; ?>
                </strong>
            </div>

            <div class="clinic-service-detail-item">
                <span>Especialistas que lo ofrecen</span>
                <strong>
                    <?= (int) ($totalPsicologos ?? 0); ?>
                </strong>
            </div>

        </div>

        <div class="mt-4">

            <h2 class="h6 text-uppercase text-muted mb-2">
                Descripción
            </h2>

            <p class="mb-0">
                <?= nl2br(htmlspecialchars(
                    (string) ($servicio['Descripcion'] ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                )); ?>
            </p>

        </div>

        <div class="clinic-service-form-note mt-4">

            Estos valores son referencia institucional. Cada especialista
            configura su propio precio y duración. El consultorio no modifica
            esas tarifas individuales.

        </div>

        <?php
        $ofertasEspecialistas = is_array($ofertasEspecialistas ?? null)
            ? $ofertasEspecialistas
            : [];
        ?>

        <div class="mt-4">
            <h2 class="h6 text-uppercase text-muted mb-3">
                Especialistas con este servicio
            </h2>

            <?php if ($ofertasEspecialistas === []): ?>
                <p class="text-muted mb-0">
                    Todavía no hay relaciones registradas para este servicio.
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Especialista</th>
                                <th>Precio</th>
                                <th>Duración</th>
                                <th>Estado de oferta</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ofertasEspecialistas as $oferta): ?>
                                <?php
                                $precioOferta = (float) ($oferta['PrecioServicio'] ?? 0);
                                $duracionOferta = (int) ($oferta['DuracionMinutos'] ?? 0);
                                $estadoOferta = (string) ($oferta['EstatusAsignacion'] ?? '');
                                $disponible =
                                    $estadoOferta === 'ACTIVA'
                                    && $precioOferta > 0
                                    && $duracionOferta >= 1
                                    && $duracionOferta <= 480;
                                ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars(
                                            trim((string) ($oferta['NombrePsicologo'] ?? '')),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    </td>
                                    <td>
                                        <?= $precioOferta > 0
                                            ? htmlspecialchars(
                                                Helper::formatearMonedaMxn($precioOferta),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            )
                                            : 'Pendiente'; ?>
                                    </td>
                                    <td>
                                        <?= $duracionOferta > 0
                                            ? $duracionOferta . ' min'
                                            : 'Pendiente'; ?>
                                    </td>
                                    <td>
                                        <?= $disponible
                                            ? 'Disponible para citas'
                                            : ($estadoOferta === 'ACTIVA'
                                                ? 'Incompleta'
                                                : 'No ofrecido'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="clinic-service-actions mt-4">

            <form
                method="POST"
                action="<?= Helper::baseUrl(
                    'consultorio/servicios/cambiar-estatus'
                ); ?>"
                class="d-inline"
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

                <button type="submit" class="btn btn-clinic-secondary">
                    <?= $activo ? 'Desactivar servicio' : 'Activar servicio'; ?>
                </button>

            </form>

        </div>

    </div>

</section>
