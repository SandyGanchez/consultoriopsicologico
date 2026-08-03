<?php

use App\Core\Session;
use App\Helpers\Helper;

$paciente = $paciente ?? [];
$historial = $historial ?? [];
$citasPendientes = $citasPendientes ?? [];
$completo = $completo ?? null;
$modo = $modo ?? 'crear';
$esEdicion = $modo === 'editar';
$csrf = Session::csrfToken();

$clvPac = (string) ($paciente['ClvPac'] ?? $historial['ClvPac'] ?? '');
$clvHist = (string) ($historial['ClvHist'] ?? '');
$nombre = trim((string) ($paciente['NombrePaciente'] ?? ''));

$seg = $completo['seguimiento'] ?? [];
$cita = $completo['cita'] ?? null;
$evolucion = $completo['evolucion'] ?? [];
$diagnostico = $completo['diagnostico'] ?? [];
$recomendaciones = $completo['recomendaciones'] ?? [['TipoRecomendacion' => '', 'DescripcionRec' => '']];

if ($recomendaciones === []) {
    $recomendaciones = [['TipoRecomendacion' => '', 'DescripcionRec' => '']];
}

$action = $esEdicion
    ? Helper::baseUrl('psicologo/expediente/seguimientos/actualizar')
    : Helper::baseUrl('psicologo/expediente/seguimientos/guardar');

$volver = Helper::baseUrl(
    'psicologo/pacientes/ver/' .
    rawurlencode($clvPac) .
    '/expediente?tab=seguimiento'
);

$tiposRec = [
    'TAREA',
    'EJERCICIO',
    'LECTURA',
    'HABITO',
    'CANALIZACION',
    'ESTUDIO_COMPLEMENTARIO',
    'OTRA'
];

?>

<section class="psi-expediente-page psi-historia-form-page" data-unsaved-guard="1">

    <a href="<?= htmlspecialchars($volver, ENT_QUOTES, 'UTF-8'); ?>" class="psi-expediente-back">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
        Volver a seguimientos
    </a>

    <header class="psi-expediente-form-header">
        <h1>
            <?= $esEdicion ? 'Editar seguimiento de sesión' : 'Agregar nueva sesión'; ?>
        </h1>
        <p>
            Paciente:
            <?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>
        </p>
    </header>

    <?php if (!empty($mensajeError)): ?>
        <div class="psi-expediente-alert psi-expediente-alert--error">
            <?= htmlspecialchars((string) $mensajeError, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form
        method="POST"
        action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>"
        class="psi-historia-form"
        id="formHistoriaClinica"
        autocomplete="off"
    >
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="ClvHist" value="<?= htmlspecialchars($clvHist, ENT_QUOTES, 'UTF-8'); ?>">

        <?php if ($esEdicion): ?>
            <input type="hidden" name="ClvSeg" value="<?= htmlspecialchars((string) ($seg['ClvSeg'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <?php endif; ?>

        <div class="accordion psi-historia-accordion" id="accordionSeguimiento">

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#secSesion">
                        A. Información de la sesión
                    </button>
                </h2>
                <div id="secSesion" class="accordion-collapse collapse show" data-bs-parent="#accordionSeguimiento">
                    <div class="accordion-body">

                        <?php if ($esEdicion && $cita): ?>
                            <div class="psi-expediente-notice">
                                Cita vinculada:
                                <?= htmlspecialchars(
                                    date('d/m/Y', strtotime((string) $cita['FechaCita'])),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                                ·
                                <?= htmlspecialchars(
                                    substr((string) ($cita['HraInicioCita'] ?? ''), 0, 5),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                                ·
                                <?= htmlspecialchars(
                                    (string) ($cita['NombreServicio'] ?? ''),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                                · ASISTIDA
                            </div>
                        <?php else: ?>
                            <label class="psi-field">
                                <span>Cita asistida *</span>
                                <select name="ClvCita" required>
                                    <option value="">Selecciona una cita…</option>
                                    <?php foreach ($citasPendientes as $c): ?>
                                        <option value="<?= htmlspecialchars((string) $c['ClvCita'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?= htmlspecialchars(
                                                date('d/m/Y', strtotime((string) $c['FechaCita'])),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                            ·
                                            <?= htmlspecialchars(
                                                substr((string) ($c['HraInicioCita'] ?? ''), 0, 5),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                            ·
                                            <?= htmlspecialchars(
                                                (string) ($c['NombreServicio'] ?? ''),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                            · ASISTIDA
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php endif; ?>

                        <div class="psi-form-grid">
                            <label class="psi-field">
                                <span>Hora de inicio real</span>
                                <input type="time" name="HoraInicioReal" value="<?= htmlspecialchars(substr((string) ($seg['HoraInicioReal'] ?? ''), 0, 5), ENT_QUOTES, 'UTF-8'); ?>">
                            </label>
                            <label class="psi-field">
                                <span>Hora de fin real</span>
                                <input type="time" name="HoraFinReal" value="<?= htmlspecialchars(substr((string) ($seg['HoraFinReal'] ?? ''), 0, 5), ENT_QUOTES, 'UTF-8'); ?>">
                            </label>
                        </div>

                        <label class="psi-field">
                            <span>Objetivo de la sesión</span>
                            <textarea name="ObjetivoSesion" rows="2"><?= htmlspecialchars((string) ($seg['ObjetivoSesion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Tema abordado</span>
                            <textarea name="TemaAbordado" rows="2"><?= htmlspecialchars((string) ($seg['TemaAbordado'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Desarrollo de la sesión</span>
                            <textarea name="DesarrolloSesion" rows="3"><?= htmlspecialchars((string) ($seg['DesarrolloSesion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Técnicas aplicadas</span>
                            <textarea name="TecnicasAplicadas" rows="2"><?= htmlspecialchars((string) ($seg['TecnicasAplicadas'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Respuesta del paciente</span>
                            <textarea name="RespuestaPaciente" rows="2"><?= htmlspecialchars((string) ($seg['RespuestaPaciente'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Estado emocional</span>
                            <input type="text" maxlength="100" name="EstadoEmocional" value="<?= htmlspecialchars((string) ($seg['EstadoEmocional'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label class="psi-field">
                            <span>Observaciones</span>
                            <textarea name="ObservacionesSeg" rows="2"><?= htmlspecialchars((string) ($seg['ObservacionesSeg'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Acuerdos</span>
                            <textarea name="AcuerdosSeg" rows="2"><?= htmlspecialchars((string) ($seg['AcuerdosSeg'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Tareas asignadas</span>
                            <textarea name="TareasAsignadas" rows="2"><?= htmlspecialchars((string) ($seg['TareasAsignadas'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Próxima acción</span>
                            <textarea name="ProximaAccion" rows="2"><?= htmlspecialchars((string) ($seg['ProximaAccion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Estatus del seguimiento</span>
                            <select name="EstatusSeg">
                                <?php foreach (['BORRADOR', 'FINALIZADO', 'CORREGIDO', 'ANULADO'] as $est): ?>
                                    <option value="<?= $est; ?>" <?= (($seg['EstatusSeg'] ?? 'FINALIZADO') === $est) ? 'selected' : ''; ?>>
                                        <?= $est; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secEvo">
                        B. Evolución de la sesión
                    </button>
                </h2>
                <div id="secEvo" class="accordion-collapse collapse" data-bs-parent="#accordionSeguimiento">
                    <div class="accordion-body">
                        <label class="psi-field">
                            <span>Avances</span>
                            <textarea name="evolucion[AvancesSeg]" rows="2"><?= htmlspecialchars((string) ($evolucion['AvancesSeg'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Dificultades</span>
                            <textarea name="evolucion[DificultadesSeg]" rows="2"><?= htmlspecialchars((string) ($evolucion['DificultadesSeg'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Retrocesos</span>
                            <textarea name="evolucion[RetrocesosSeg]" rows="2"><?= htmlspecialchars((string) ($evolucion['RetrocesosSeg'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Cumplimiento de tareas</span>
                            <select name="evolucion[CumplimientoTareas]">
                                <option value="">—</option>
                                <?php foreach (['COMPLETO', 'PARCIAL', 'NO_REALIZADO', 'NO_APLICA'] as $op): ?>
                                    <option value="<?= $op; ?>" <?= (($evolucion['CumplimientoTareas'] ?? '') === $op) ? 'selected' : ''; ?>><?= $op; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="psi-field">
                            <span>Cambios conductuales</span>
                            <textarea name="evolucion[CambiosConductuales]" rows="2"><?= htmlspecialchars((string) ($evolucion['CambiosConductuales'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Cambios emocionales</span>
                            <textarea name="evolucion[CambiosEmocionales]" rows="2"><?= htmlspecialchars((string) ($evolucion['CambiosEmocionales'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Factores de riesgo</span>
                            <textarea name="evolucion[FactoresRiesgo]" rows="2"><?= htmlspecialchars((string) ($evolucion['FactoresRiesgo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Factores protectores</span>
                            <textarea name="evolucion[FactoresProtectores]" rows="2"><?= htmlspecialchars((string) ($evolucion['FactoresProtectores'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Pronóstico actual</span>
                            <textarea name="evolucion[PronosticoActual]" rows="2"><?= htmlspecialchars((string) ($evolucion['PronosticoActual'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secDiag">
                        C. Diagnóstico de seguimiento
                    </button>
                </h2>
                <div id="secDiag" class="accordion-collapse collapse" data-bs-parent="#accordionSeguimiento">
                    <div class="accordion-body">
                        <label class="psi-field">
                            <span>Tipo de cambio</span>
                            <select name="diagnostico[TipoCambioDiag]">
                                <option value="">—</option>
                                <?php foreach (['SE_MANTIENE', 'MODIFICADO', 'DESCARTADO', 'NUEVO'] as $op): ?>
                                    <option value="<?= $op; ?>" <?= (($diagnostico['TipoCambioDiag'] ?? '') === $op) ? 'selected' : ''; ?>><?= $op; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="psi-field">
                            <span>Diagnóstico anterior</span>
                            <textarea name="diagnostico[DiagnosticoAnterior]" rows="2"><?= htmlspecialchars((string) ($diagnostico['DiagnosticoAnterior'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Diagnóstico actual</span>
                            <textarea name="diagnostico[DiagnosticoActual]" rows="3"><?= htmlspecialchars((string) ($diagnostico['DiagnosticoActual'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Código diagnóstico</span>
                            <input type="text" maxlength="20" name="diagnostico[CodigoDiagnostico]" value="<?= htmlspecialchars((string) ($diagnostico['CodigoDiagnostico'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label class="psi-field">
                            <span>Sistema de clasificación</span>
                            <select name="diagnostico[SistemaClasificacion]">
                                <option value="">—</option>
                                <?php foreach (['DSM5', 'CIE10', 'CIE11', 'OTRO'] as $op): ?>
                                    <option value="<?= $op; ?>" <?= (($diagnostico['SistemaClasificacion'] ?? '') === $op) ? 'selected' : ''; ?>><?= $op; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="psi-field">
                            <span>Justificación del cambio</span>
                            <textarea name="diagnostico[JustificacionCambio]" rows="2"><?= htmlspecialchars((string) ($diagnostico['JustificacionCambio'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secRec">
                        D. Recomendaciones
                    </button>
                </h2>
                <div id="secRec" class="accordion-collapse collapse" data-bs-parent="#accordionSeguimiento">
                    <div class="accordion-body">
                        <div class="psi-expediente-notice">
                            Las recomendaciones registradas se conservan como parte del historial de la
                            sesión. Puedes actualizarlas o marcarlas como cumplidas.
                        </div>
                        <div id="listaRecomendaciones" data-dynamic-list="recomendaciones">
                            <?php foreach ($recomendaciones as $i => $rec): ?>
                                <?php $esGuardada = !empty($rec['ClvRecSeg']); ?>
                                <div class="psi-dynamic-row<?= $esGuardada && !empty($rec['Cumplida']) ? ' psi-dynamic-row--cumplida' : ''; ?><?= $esGuardada ? ' psi-dynamic-row--persistida' : ''; ?>">
                                    <?php if ($esGuardada): ?>
                                        <input type="hidden" name="recomendaciones[<?= (int) $i; ?>][ClvRecSeg]" value="<?= htmlspecialchars((string) $rec['ClvRecSeg'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php endif; ?>
                                    <label class="psi-field">
                                        <span>Tipo</span>
                                        <select name="recomendaciones[<?= (int) $i; ?>][TipoRecomendacion]">
                                            <option value="">Selecciona…</option>
                                            <?php foreach ($tiposRec as $tipo): ?>
                                                <option value="<?= $tipo; ?>" <?= (($rec['TipoRecomendacion'] ?? '') === $tipo) ? 'selected' : ''; ?>>
                                                    <?= htmlspecialchars(str_replace('_', ' ', $tipo), ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label class="psi-field">
                                        <span>Descripción</span>
                                        <textarea name="recomendaciones[<?= (int) $i; ?>][DescripcionRec]" rows="2"><?= htmlspecialchars((string) ($rec['DescripcionRec'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </label>
                                    <label class="psi-field">
                                        <span>Fecha límite</span>
                                        <input type="date" name="recomendaciones[<?= (int) $i; ?>][FechaLimite]" value="<?= htmlspecialchars((string) ($rec['FechaLimite'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    </label>
                                    <label class="psi-inline-check">
                                        <input type="checkbox" name="recomendaciones[<?= (int) $i; ?>][Cumplida]" value="1" <?= !empty($rec['Cumplida']) ? 'checked' : ''; ?>>
                                        Cumplida
                                    </label>
                                    <?php if (!$esGuardada): ?>
                                        <button
                                            type="button"
                                            class="psi-expediente-btn-secondary psi-rec-remove"
                                            data-remove-new-row
                                        >
                                            Quitar fila nueva
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="psi-expediente-btn-secondary" data-add-row="listaRecomendaciones">
                            Agregar recomendación
                        </button>
                    </div>
                </div>
            </div>

        </div>

        <div class="psi-historia-actions">
            <a href="<?= htmlspecialchars($volver, ENT_QUOTES, 'UTF-8'); ?>" class="psi-expediente-btn-secondary">Cancelar</a>
            <button type="submit" class="psi-expediente-btn" id="btnGuardarHistoria">
                <?= $esEdicion ? 'Actualizar seguimiento' : 'Guardar seguimiento'; ?>
            </button>
        </div>
    </form>

    <template id="tplRecomendacion">
        <div class="psi-dynamic-row">
            <label class="psi-field">
                <span>Tipo</span>
                <select name="recomendaciones[__INDEX__][TipoRecomendacion]">
                    <option value="">Selecciona…</option>
                    <?php foreach ($tiposRec as $tipo): ?>
                        <option value="<?= $tipo; ?>"><?= htmlspecialchars(str_replace('_', ' ', $tipo), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="psi-field">
                <span>Descripción</span>
                <textarea name="recomendaciones[__INDEX__][DescripcionRec]" rows="2"></textarea>
            </label>
            <label class="psi-field">
                <span>Fecha límite</span>
                <input type="date" name="recomendaciones[__INDEX__][FechaLimite]">
            </label>
            <label class="psi-inline-check">
                <input type="checkbox" name="recomendaciones[__INDEX__][Cumplida]" value="1">
                Cumplida
            </label>
            <button
                type="button"
                class="psi-expediente-btn-secondary psi-rec-remove"
                data-remove-new-row
            >
                Quitar fila nueva
            </button>
        </div>
    </template>

</section>
