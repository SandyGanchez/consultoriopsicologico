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
                <span>Duración sugerida</span>
                <strong class="clinic-service-duration">
                    <?= (int) ($servicio['DuracionMinutos'] ?? 0); ?> minutos
                </strong>
            </div>

            <div class="clinic-service-detail-item">
                <span>Precio sugerido</span>
                <strong class="clinic-service-price">
                    $<?= number_format(
                        (float) ($servicio['CostoServicio'] ?? 0),
                        2
                    ); ?>
                </strong>
            </div>

            <div class="clinic-service-detail-item">
                <span>Especialistas asignados</span>
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

            Estos valores funcionan como referencia. Cada especialista
            podrá configurar posteriormente el precio y la duración que
            aplicará al impartir este servicio.

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
