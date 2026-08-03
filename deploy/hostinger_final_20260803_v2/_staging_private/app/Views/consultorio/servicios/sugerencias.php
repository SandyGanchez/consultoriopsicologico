<?php

use App\Helpers\Helper;

$sugerencias = is_array($sugerencias ?? null) ? $sugerencias : [];
$sugerenciasHabilitadas = !empty($sugerenciasHabilitadas);

?>

<section class="clinic-services-page clinic-services-header">

    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <span class="consultorio-page-eyebrow">Catálogo institucional</span>
            <h1>Sugerencias de servicios</h1>
            <p class="mb-0">
                Revisa propuestas de los especialistas. Aprobar abre el formulario
                de alta; el servicio solo se crea al confirmar.
            </p>
        </div>
        <a href="<?= Helper::baseUrl('consultorio/servicios'); ?>" class="btn btn-clinic-secondary">
            Volver a servicios
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

    <?php if (!$sugerenciasHabilitadas): ?>
        <div class="alert alert-warning" role="status">
            La tabla <code>sugerencia_servicio</code> aún no está aplicada en esta
            base. Consulta la migración propuesta antes de habilitarla.
        </div>
    <?php elseif ($sugerencias === []): ?>
        <div class="clinic-services-card text-muted">
            No hay sugerencias registradas.
        </div>
    <?php else: ?>
        <div class="clinic-services-card table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Especialista</th>
                        <th>Nombre sugerido</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sugerencias as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($item['NombrePsicologo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars((string) ($item['NombreSugerido'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars((string) ($item['EstadoSugerencia'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars((string) ($item['FechaSolicitud'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-end">
                                <a
                                    class="btn btn-sm btn-clinic-secondary"
                                    href="<?= Helper::baseUrl(
                                        'consultorio/servicios/sugerencias/ver?id='
                                        . (int) ($item['IdSugerenciaServicio'] ?? 0)
                                    ); ?>"
                                >
                                    Ver detalle
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</section>
