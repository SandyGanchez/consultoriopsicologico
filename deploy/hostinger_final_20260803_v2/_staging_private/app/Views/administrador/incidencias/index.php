<?php

use App\Core\Session;
use App\Helpers\Helper;
use App\Services\IncidenciaSoporteService;

/** @var IncidenciaSoporteService $servicio */
$servicio = $servicio ?? new IncidenciaSoporteService();
$incidencias = is_array($incidencias ?? null) ? $incidencias : [];
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

?>

<section class="admin-page">
    <p class="text-uppercase small mb-1" style="color:#99CDD8;letter-spacing:.06em;">
        Soporte técnico
    </p>
    <h1 class="h3 mb-2" style="color:#657166;">Incidencias de acceso</h1>
    <p class="mb-4" style="color:#657166;">
        Solo tickets con destino administrador (cuenta CONSULTORIO o escaladas
        desde el consultorio). Los reportes ordinarios de pacientes y especialistas
        los atiende el consultorio en primer nivel.
    </p>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= $esc($success); ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= $esc($error); ?></div>
    <?php endif; ?>

    <?php if ($incidencias === []): ?>
        <div class="alert alert-light border" style="border-color:#DAEBE3;color:#657166;">
            No hay incidencias técnicas registradas por el momento.
        </div>
    <?php else: ?>
        <div class="table-responsive bg-white border rounded-3 p-2" style="border-color:#DAEBE3 !important;">
            <table class="table align-middle mb-0">
                <thead>
                    <tr style="color:#657166;">
                        <th>Folio</th>
                        <th>Tipo</th>
                        <th>Nivel</th>
                        <th>Correo</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Origen</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($incidencias as $item): ?>
                        <?php
                        $id = (int) ($item['IdIncidencia'] ?? 0);
                        $estado = (string) ($item['EstadoIncidencia'] ?? '');
                        $nivel = strtoupper(trim((string) ($item['NivelAtencion'] ?? 'PRIMER_NIVEL')));
                        $idOrigen = (int) ($item['IdIncidenciaOrigen'] ?? 0);
                        $tieneCuenta = trim((string) ($item['ClvUsuSolicitante'] ?? '')) !== '';
                        ?>
                        <tr>
                            <td>#<?= $id; ?></td>
                            <td><?= $esc($servicio->etiquetaTipo((string) ($item['TipoIncidencia'] ?? ''))); ?></td>
                            <td>
                                <span class="badge rounded-pill"
                                    style="background:<?= $nivel === 'ESCALADA' ? '#F3C3B2' : '#CFD6C4'; ?>;color:#657166;">
                                    <?= $esc($servicio->etiquetaNivel($nivel)); ?>
                                </span>
                            </td>
                            <td><?= $esc($servicio->ocultarCorreo((string) ($item['CorreoReportado'] ?? ''))); ?></td>
                            <td><?= $esc((string) ($item['FechaSolicitud'] ?? '')); ?></td>
                            <td>
                                <span class="badge rounded-pill"
                                    style="background:<?= $estado === 'RESUELTA' ? '#CFD6C4' : ($estado === 'EN_PROCESO' ? '#99CDD8' : '#FDE8D3'); ?>;color:#657166;">
                                    <?= $esc($estado); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($idOrigen > 0): ?>
                                    Esc. #<?= $idOrigen; ?>
                                <?php else: ?>
                                    <?= $tieneCuenta ? 'Cuenta CONSULTORIO' : 'Sin cuenta'; ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a
                                    class="btn btn-sm btn-outline-secondary"
                                    href="<?= Helper::baseUrl('administrador/incidencias/' . $id); ?>"
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
