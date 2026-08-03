<?php

use App\Helpers\Helper;
use App\Services\IncidenciaSoporteService;

/** @var IncidenciaSoporteService $servicio */
$servicio = $servicio ?? new IncidenciaSoporteService();
$incidencias = is_array($incidencias ?? null) ? $incidencias : [];
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

?>

<section class="consultorio-agenda">

    <div class="consultorio-page-header">
        <div>
            <span class="consultorio-page-eyebrow">
                Soporte de acceso
            </span>
            <h1>Incidencias de acceso</h1>
            <p>
                Reportes de pacientes y especialistas sobre problemas
                para iniciar sesión o recuperar su cuenta. Sin datos clínicos.
            </p>
        </div>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= $esc($success); ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= $esc($error); ?></div>
    <?php endif; ?>

    <?php if ($incidencias === []): ?>
        <div class="alert alert-light border">
            No hay incidencias de acceso pendientes de revisar.
        </div>
    <?php else: ?>
        <div class="table-responsive bg-white border rounded-3 p-2">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Tipo</th>
                        <th>Correo</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Escalada</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($incidencias as $item): ?>
                        <?php
                        $id = (int) ($item['IdIncidencia'] ?? 0);
                        $estado = (string) ($item['EstadoIncidencia'] ?? '');
                        $obsCons = trim((string) ($item['ObservacionConsultorio'] ?? ''));
                        $escalada = $obsCons === 'Escalada a soporte técnico';
                        ?>
                        <tr>
                            <td>#<?= $id; ?></td>
                            <td><?= $esc($servicio->etiquetaTipo((string) ($item['TipoIncidencia'] ?? ''))); ?></td>
                            <td><?= $esc($servicio->ocultarCorreo((string) ($item['CorreoReportado'] ?? ''))); ?></td>
                            <td><?= $esc((string) ($item['FechaSolicitud'] ?? '')); ?></td>
                            <td>
                                <span class="badge rounded-pill text-bg-light">
                                    <?= $esc($estado); ?>
                                </span>
                            </td>
                            <td><?= $escalada ? 'Sí' : '—'; ?></td>
                            <td class="text-end">
                                <a
                                    class="btn btn-sm btn-outline-secondary"
                                    href="<?= Helper::baseUrl('consultorio/incidencias/' . $id); ?>"
                                >
                                    Revisar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</section>
