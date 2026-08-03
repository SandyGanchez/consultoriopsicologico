<?php

use App\Core\Session;
use App\Helpers\Helper;
use App\Services\IncidenciaSoporteService;

/** @var IncidenciaSoporteService $servicio */
$servicio = $servicio ?? new IncidenciaSoporteService();
$incidencia = is_array($incidencia ?? null) ? $incidencia : [];
$relacionadaPrincipal = !empty($relacionadaPrincipal);
$cuentaPrincipal = is_array($cuentaPrincipal ?? null) ? $cuentaPrincipal : null;
$activacionInfo = is_array($activacionInfo ?? null) ? $activacionInfo : null;
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$id = (int) ($incidencia['IdIncidencia'] ?? 0);
$estado = (string) ($incidencia['EstadoIncidencia'] ?? '');
$nivel = strtoupper(trim((string) ($incidencia['NivelAtencion'] ?? 'PRIMER_NIVEL')));
$idOrigen = (int) ($incidencia['IdIncidenciaOrigen'] ?? 0);
$csrf = Session::csrfToken();

$puedeEnProceso = $estado === 'PENDIENTE';
$puedeResolver = in_array($estado, ['PENDIENTE', 'EN_PROCESO'], true);

$estadoUsu = (int) ($cuentaPrincipal['EstadoUsu'] ?? 0);
$requiereActivacion = !empty($activacionInfo['requiere_activacion']);

?>

<section class="admin-page">
    <p class="mb-2">
        <a href="<?= Helper::baseUrl('administrador/incidencias'); ?>" style="color:#657166;">
            ← Volver al listado
        </a>
    </p>

    <h1 class="h3 mb-3" style="color:#657166;">
        Incidencia #<?= $id; ?>
        <span class="badge rounded-pill ms-1"
            style="background:<?= $nivel === 'ESCALADA' ? '#F3C3B2' : '#CFD6C4'; ?>;color:#657166;font-size:.55em;vertical-align:middle;">
            <?= $esc($servicio->etiquetaNivel($nivel)); ?>
        </span>
    </h1>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= $esc($success); ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= $esc($error); ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-7">
            <article class="bg-white border rounded-3 p-3" style="border-color:#DAEBE3 !important;">
                <p class="mb-2"><strong>Tipo:</strong>
                    <?= $esc($servicio->etiquetaTipo((string) ($incidencia['TipoIncidencia'] ?? ''))); ?>
                </p>
                <p class="mb-2"><strong>Nivel de atención:</strong>
                    <?= $esc($servicio->etiquetaNivel($nivel)); ?>
                </p>
                <p class="mb-2"><strong>Estado:</strong> <?= $esc($estado); ?></p>
                <p class="mb-2"><strong>Correo reportado:</strong>
                    <?= $esc($servicio->ocultarCorreo((string) ($incidencia['CorreoReportado'] ?? ''))); ?>
                </p>
                <p class="mb-2"><strong>Fecha:</strong>
                    <?= $esc((string) ($incidencia['FechaSolicitud'] ?? '')); ?>
                </p>
                <?php if ($idOrigen > 0): ?>
                    <p class="mb-2"><strong>Incidencia origen (consultorio):</strong>
                        #<?= $idOrigen; ?>
                    </p>
                <?php endif; ?>
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
                <p class="mb-0" style="white-space:pre-wrap;color:#657166;">
                    <?= $esc((string) ($incidencia['Descripcion'] ?? '')); ?>
                </p>

                <?php if (trim((string) ($incidencia['ObservacionConsultorio'] ?? '')) !== ''): ?>
                    <hr>
                    <p class="mb-1"><strong>Observación del consultorio</strong></p>
                    <p class="mb-0" style="white-space:pre-wrap;">
                        <?= $esc((string) $incidencia['ObservacionConsultorio']); ?>
                    </p>
                <?php endif; ?>

                <?php if (trim((string) ($incidencia['ObservacionAdministrador'] ?? '')) !== ''): ?>
                    <hr>
                    <p class="mb-1"><strong>Observación administrativa</strong></p>
                    <p class="mb-0" style="white-space:pre-wrap;">
                        <?= $esc((string) $incidencia['ObservacionAdministrador']); ?>
                    </p>
                <?php endif; ?>
            </article>
        </div>

        <div class="col-lg-5">
            <?php if ($puedeEnProceso || $puedeResolver): ?>
                <article class="bg-white border rounded-3 p-3 mb-3" style="border-color:#DAEBE3 !important;">
                    <h2 class="h6" style="color:#657166;">Atención</h2>

                    <?php if ($puedeEnProceso): ?>
                        <form method="POST" action="<?= Helper::baseUrl('administrador/incidencias/' . $id . '/actualizar'); ?>" class="mb-3">
                            <input type="hidden" name="csrf_token" value="<?= $esc($csrf); ?>">
                            <input type="hidden" name="estado" value="EN_PROCESO">
                            <button
                                type="submit"
                                class="btn btn-sm w-100 text-white"
                                style="background:#99CDD8;"
                                onclick="return confirm('¿Marcar esta incidencia como EN_PROCESO?');"
                            >
                                Marcar en proceso
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($puedeResolver): ?>
                        <form method="POST" action="<?= Helper::baseUrl('administrador/incidencias/' . $id . '/actualizar'); ?>">
                            <input type="hidden" name="csrf_token" value="<?= $esc($csrf); ?>">
                            <input type="hidden" name="estado" value="RESUELTA">
                            <label class="form-label" for="observacionIncidencia">Observación (obligatoria)</label>
                            <textarea
                                class="form-control mb-2"
                                id="observacionIncidencia"
                                name="observacion"
                                rows="4"
                                maxlength="1000"
                                required
                                minlength="5"
                            ></textarea>
                            <button
                                type="submit"
                                class="btn btn-sm w-100 text-white"
                                style="background:#657166;"
                                onclick="return confirm('¿Marcar esta incidencia como RESUELTA?');"
                            >
                                Marcar resuelta
                            </button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endif; ?>

            <?php if ($relacionadaPrincipal && is_array($cuentaPrincipal)): ?>
                <article class="bg-white border rounded-3 p-3" style="border-color:#F3C3B2 !important;background:#FDE8D3;">
                    <h2 class="h6" style="color:#657166;">Acciones de la cuenta principal</h2>
                    <p class="small mb-3" style="color:#657166;">
                        Solo disponibles cuando el solicitante es la cuenta CONSULTORIO principal.
                        No se ejecutan al resolver la incidencia. Usa confirmación y POST.
                    </p>

                    <?php if ($requiereActivacion): ?>
                        <form method="POST" action="<?= Helper::baseUrl('administrador/consultorio/reenviar-activacion'); ?>" class="mb-2">
                            <input type="hidden" name="csrf_token" value="<?= $esc($csrf); ?>">
                            <button
                                type="submit"
                                class="btn btn-sm btn-outline-secondary w-100"
                                onclick="return confirm('¿Reenviar activación a la cuenta principal?');"
                            >
                                Reenviar activación
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($estadoUsu === 0 && !$requiereActivacion): ?>
                        <form method="POST" action="<?= Helper::baseUrl('administrador/consultorio/cambiar-estado-cuenta'); ?>" class="mb-2">
                            <input type="hidden" name="csrf_token" value="<?= $esc($csrf); ?>">
                            <input type="hidden" name="accion" value="ACTIVAR">
                            <button
                                type="submit"
                                class="btn btn-sm btn-outline-secondary w-100"
                                onclick="return confirm('¿Activar la cuenta principal? Solo cambia EstadoUsu.');"
                            >
                                Activar cuenta
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($estadoUsu === 1 && !$requiereActivacion): ?>
                        <form method="POST" action="<?= Helper::baseUrl('administrador/consultorio/restablecer-acceso'); ?>">
                            <input type="hidden" name="csrf_token" value="<?= $esc($csrf); ?>">
                            <button
                                type="submit"
                                class="btn btn-sm btn-outline-secondary w-100"
                                onclick="return confirm('¿Enviar restablecimiento de acceso (RECUPERACION_CONSULTORIO)?');"
                            >
                                Restablecer acceso
                            </button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php elseif (trim((string) ($incidencia['ClvUsuSolicitante'] ?? '')) !== ''): ?>
                <article class="bg-white border rounded-3 p-3" style="border-color:#DAEBE3 !important;">
                    <p class="small mb-0" style="color:#657166;">
                        La cuenta relacionada no es la cuenta principal CONSULTORIO.
                        Solo se documenta el problema técnico; no hay acciones clínicas ni de perfil operativo.
                    </p>
                </article>
            <?php endif; ?>
        </div>
    </div>
</section>
