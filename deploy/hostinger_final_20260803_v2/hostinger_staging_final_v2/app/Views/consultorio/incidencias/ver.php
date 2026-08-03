<?php

use App\Core\Session;
use App\Helpers\Helper;
use App\Services\IncidenciaSoporteService;

/** @var IncidenciaSoporteService $servicio */
$servicio = $servicio ?? new IncidenciaSoporteService();
$incidencia = is_array($incidencia ?? null) ? $incidencia : [];
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$id = (int) ($incidencia['IdIncidencia'] ?? 0);
$estado = (string) ($incidencia['EstadoIncidencia'] ?? '');
$csrf = (string) ($csrf ?? Session::csrfToken());
$obsConsultorio = trim((string) ($incidencia['ObservacionConsultorio'] ?? ''));
$escaladaHija = is_array($incidencia['EscaladaHija'] ?? null)
    ? $incidencia['EscaladaHija']
    : null;

$puedeEnProceso = $estado === 'PENDIENTE';
$puedeResolver = in_array($estado, ['PENDIENTE', 'EN_PROCESO'], true);
$puedeEscalar = $puedeResolver
    && !(
        is_array($escaladaHija)
        && strtoupper(trim((string) ($escaladaHija['EstadoIncidencia'] ?? ''))) !== 'RESUELTA'
    );

?>

<section class="consultorio-agenda">

    <p class="mb-2">
        <a href="<?= Helper::baseUrl('consultorio/incidencias'); ?>">
            ← Volver al listado
        </a>
    </p>

    <div class="consultorio-page-header">
        <div>
            <span class="consultorio-page-eyebrow">
                Soporte de acceso
            </span>
            <h1>Incidencia #<?= $id; ?></h1>
        </div>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= $esc($success); ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= $esc($error); ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-7">
            <article class="bg-white border rounded-3 p-3">
                <p class="mb-2"><strong>Tipo:</strong>
                    <?= $esc($servicio->etiquetaTipo((string) ($incidencia['TipoIncidencia'] ?? ''))); ?>
                </p>
                <p class="mb-2"><strong>Estado:</strong> <?= $esc($estado); ?></p>
                <p class="mb-2"><strong>Correo reportado:</strong>
                    <?= $esc($servicio->ocultarCorreo((string) ($incidencia['CorreoReportado'] ?? ''))); ?>
                </p>
                <p class="mb-2"><strong>Fecha:</strong>
                    <?= $esc((string) ($incidencia['FechaSolicitud'] ?? '')); ?>
                </p>
                <p class="mb-2"><strong>Cuenta relacionada:</strong>
                    <?php if (trim((string) ($incidencia['ClvUsuSolicitante'] ?? '')) !== ''): ?>
                        Sí
                        <?php if (!empty($incidencia['RolSolicitante'])): ?>
                            (rol: <?= $esc((string) $incidencia['RolSolicitante']); ?>)
                        <?php endif; ?>
                    <?php else: ?>
                        No
                    <?php endif; ?>
                </p>
                <p class="mb-1"><strong>Descripción</strong></p>
                <p class="mb-0" style="white-space:pre-wrap;">
                    <?= $esc((string) ($incidencia['Descripcion'] ?? '')); ?>
                </p>

                <?php if ($obsConsultorio !== ''): ?>
                    <hr>
                    <p class="mb-1"><strong>Observación del consultorio</strong></p>
                    <p class="mb-0" style="white-space:pre-wrap;">
                        <?= $esc($obsConsultorio); ?>
                    </p>
                <?php endif; ?>

                <?php if (is_array($escaladaHija)): ?>
                    <hr>
                    <p class="mb-1"><strong>Escalada a soporte técnico</strong></p>
                    <p class="mb-0">
                        Ticket #<?= (int) ($escaladaHija['IdIncidencia'] ?? 0); ?>
                        · Estado: <?= $esc((string) ($escaladaHija['EstadoIncidencia'] ?? '')); ?>
                        · Fecha: <?= $esc((string) ($escaladaHija['FechaSolicitud'] ?? '')); ?>
                    </p>
                    <p class="small text-muted mb-0 mt-1">
                        Las notas internas de soporte técnico no se muestran aquí.
                    </p>
                <?php endif; ?>
            </article>
        </div>

        <div class="col-lg-5">
            <?php if ($puedeEnProceso || $puedeResolver): ?>
                <article class="bg-white border rounded-3 p-3 mb-3">
                    <h2 class="h6">Atención</h2>

                    <?php if ($puedeEnProceso): ?>
                        <form
                            method="POST"
                            action="<?= Helper::baseUrl('consultorio/incidencias/' . $id . '/actualizar'); ?>"
                            class="mb-3"
                        >
                            <input type="hidden" name="csrf_token" value="<?= $esc($csrf); ?>">
                            <input type="hidden" name="estado" value="EN_PROCESO">
                            <button
                                type="submit"
                                class="btn btn-sm w-100 btn-outline-secondary"
                                onclick="return confirm('¿Marcar esta incidencia como EN_PROCESO?');"
                            >
                                Marcar en proceso
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($puedeResolver): ?>
                        <form
                            method="POST"
                            action="<?= Helper::baseUrl('consultorio/incidencias/' . $id . '/actualizar'); ?>"
                        >
                            <input type="hidden" name="csrf_token" value="<?= $esc($csrf); ?>">
                            <input type="hidden" name="estado" value="RESUELTA">
                            <label class="form-label" for="observacionIncidenciaCons">
                                Observación (obligatoria)
                            </label>
                            <textarea
                                class="form-control mb-2"
                                id="observacionIncidenciaCons"
                                name="observacion"
                                rows="4"
                                maxlength="1000"
                                required
                                minlength="5"
                            ></textarea>
                            <button
                                type="submit"
                                class="btn btn-sm w-100 btn-dark"
                                onclick="return confirm('¿Marcar esta incidencia como RESUELTA?');"
                            >
                                Marcar resuelta
                            </button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endif; ?>

            <?php if ($puedeEscalar): ?>
                <article class="bg-white border rounded-3 p-3">
                    <h2 class="h6">Escalar a soporte técnico</h2>
                    <p class="small text-muted">
                        Usa esta opción cuando el problema requiera intervención
                        de la plataforma (activación, correo, bloqueo técnico).
                    </p>
                    <form
                        method="POST"
                        action="<?= Helper::baseUrl('consultorio/incidencias/' . $id . '/escalar'); ?>"
                    >
                        <input type="hidden" name="csrf_token" value="<?= $esc($csrf); ?>">
                        <label class="form-label" for="descripcionTecnica">
                            Descripción técnica
                        </label>
                        <textarea
                            class="form-control mb-2"
                            id="descripcionTecnica"
                            name="descripcion_tecnica"
                            rows="4"
                            maxlength="1000"
                            required
                            minlength="10"
                            placeholder="Describe qué intentaste y qué falla."
                        ></textarea>
                        <button
                            type="submit"
                            class="btn btn-sm w-100 btn-outline-danger"
                            onclick="return confirm('¿Escalar esta incidencia a soporte técnico?');"
                        >
                            Escalar a administrador
                        </button>
                    </form>
                </article>
            <?php elseif (is_array($escaladaHija)): ?>
                <article class="bg-white border rounded-3 p-3">
                    <p class="small mb-0">
                        Esta incidencia ya fue escalada a soporte técnico
                        (ticket #<?= (int) ($escaladaHija['IdIncidencia'] ?? 0); ?>,
                        estado <?= $esc((string) ($escaladaHija['EstadoIncidencia'] ?? '')); ?>).
                    </p>
                </article>
            <?php endif; ?>
        </div>
    </div>

</section>
