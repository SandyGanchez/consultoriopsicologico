<?php

use App\Core\Session;
use App\Helpers\Helper;

$sugerencia = is_array($sugerencia ?? null) ? $sugerencia : [];
$csrf = Session::csrfToken();
$estado = (string) ($sugerencia['EstadoSugerencia'] ?? '');
$id = (int) ($sugerencia['IdSugerenciaServicio'] ?? 0);
$pendiente = $estado === 'PENDIENTE';

?>

<section class="clinic-services-page clinic-services-header">

    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <span class="consultorio-page-eyebrow">Sugerencia</span>
            <h1><?= htmlspecialchars((string) ($sugerencia['NombreSugerido'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="mb-0">
                Propuesta de
                <?= htmlspecialchars((string) ($sugerencia['NombrePsicologo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
            </p>
        </div>
        <a href="<?= Helper::baseUrl('consultorio/servicios/sugerencias'); ?>" class="btn btn-clinic-secondary">
            Volver
        </a>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="clinic-services-card">
        <div class="clinic-service-detail-grid">
            <div class="clinic-service-detail-item">
                <span>Estado</span>
                <strong><?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
            <div class="clinic-service-detail-item">
                <span>Fecha de solicitud</span>
                <strong><?= htmlspecialchars((string) ($sugerencia['FechaSolicitud'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
        </div>

        <div class="mt-4">
            <h2 class="h6 text-uppercase text-muted">Descripción sugerida</h2>
            <p><?= nl2br(htmlspecialchars((string) ($sugerencia['DescripcionSugerida'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></p>
        </div>

        <div class="mt-3">
            <h2 class="h6 text-uppercase text-muted">Justificación</h2>
            <p class="mb-0"><?= nl2br(htmlspecialchars((string) ($sugerencia['Justificacion'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></p>
        </div>

        <?php if (!empty($sugerencia['ObservacionConsultorio'])): ?>
            <div class="mt-3">
                <h2 class="h6 text-uppercase text-muted">Observación del consultorio</h2>
                <p class="mb-0"><?= nl2br(htmlspecialchars((string) $sugerencia['ObservacionConsultorio'], ENT_QUOTES, 'UTF-8')); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($pendiente): ?>
            <div class="d-flex flex-wrap gap-2 mt-4">
                <a
                    class="btn btn-clinic-primary"
                    href="<?= Helper::baseUrl(
                        'consultorio/servicios/nuevo?sugerencia=' . $id
                    ); ?>"
                >
                    Iniciar aprobación
                </a>
            </div>

            <form
                method="POST"
                action="<?= Helper::baseUrl('consultorio/servicios/sugerencias/rechazar'); ?>"
                class="mt-4"
            >
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="idSugerencia" value="<?= $id; ?>">
                <label for="observacion" class="form-label">Observación al rechazar *</label>
                <textarea
                    class="form-control mb-3"
                    name="observacion"
                    id="observacion"
                    rows="3"
                    maxlength="500"
                    required
                ></textarea>
                <button type="submit" class="btn btn-clinic-secondary">
                    Rechazar sugerencia
                </button>
            </form>
        <?php endif; ?>
    </div>

</section>
