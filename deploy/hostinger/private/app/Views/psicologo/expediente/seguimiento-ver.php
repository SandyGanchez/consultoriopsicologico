<?php

use App\Helpers\Helper;

$paciente = $paciente ?? [];
$historial = $historial ?? [];
$completo = $completo ?? [];

$seg = $completo['seguimiento'] ?? [];
$cita = $completo['cita'] ?? [];
$evolucion = $completo['evolucion'] ?? [];
$diagnostico = $completo['diagnostico'] ?? [];
$recomendaciones = $completo['recomendaciones'] ?? [];

$clvPac = (string) ($paciente['ClvPac'] ?? $historial['ClvPac'] ?? '');
$clvSeg = (string) ($seg['ClvSeg'] ?? '');
$nombre = trim((string) ($paciente['NombrePaciente'] ?? ''));

$volver = Helper::baseUrl(
    'psicologo/pacientes/ver/' .
    rawurlencode($clvPac) .
    '/expediente?tab=seguimiento'
);

?>

<section class="psi-expediente-page">

    <a href="<?= htmlspecialchars($volver, ENT_QUOTES, 'UTF-8'); ?>" class="psi-expediente-back">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
        Volver a seguimientos
    </a>

    <header class="psi-expediente-form-header">
        <h1>
            Sesión <?= (int) ($seg['NumeroSesion'] ?? 0); ?>
        </h1>
        <p>
            <?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>
            <?php if (!empty($cita['FechaCita'])): ?>
                ·
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
            <?php endif; ?>
        </p>
    </header>

    <div class="psi-expediente-toolbar">
        <span class="psi-seguimiento-badge">
            <?= htmlspecialchars((string) ($seg['EstatusSeg'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        </span>
        <a
            href="<?= Helper::baseUrl(
                'psicologo/expediente/seguimientos/editar/' .
                rawurlencode($clvSeg)
            ); ?>"
            class="psi-expediente-btn"
        >
            Editar seguimiento
        </a>
    </div>

    <div class="psi-expediente-panel">

        <section class="psi-expediente-section">
            <h3>Información de la sesión</h3>
            <p><strong>Objetivo:</strong> <?= nl2br(htmlspecialchars((string) ($seg['ObjetivoSesion'] ?? '—'), ENT_QUOTES, 'UTF-8')); ?></p>
            <p><strong>Tema abordado:</strong> <?= nl2br(htmlspecialchars((string) ($seg['TemaAbordado'] ?? '—'), ENT_QUOTES, 'UTF-8')); ?></p>
            <p><strong>Desarrollo:</strong> <?= nl2br(htmlspecialchars((string) ($seg['DesarrolloSesion'] ?? '—'), ENT_QUOTES, 'UTF-8')); ?></p>
            <p><strong>Técnicas:</strong> <?= nl2br(htmlspecialchars((string) ($seg['TecnicasAplicadas'] ?? '—'), ENT_QUOTES, 'UTF-8')); ?></p>
            <p><strong>Respuesta del paciente:</strong> <?= nl2br(htmlspecialchars((string) ($seg['RespuestaPaciente'] ?? '—'), ENT_QUOTES, 'UTF-8')); ?></p>
            <p><strong>Estado emocional:</strong> <?= htmlspecialchars((string) ($seg['EstadoEmocional'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Observaciones:</strong> <?= nl2br(htmlspecialchars((string) ($seg['ObservacionesSeg'] ?? '—'), ENT_QUOTES, 'UTF-8')); ?></p>
            <p><strong>Acuerdos:</strong> <?= nl2br(htmlspecialchars((string) ($seg['AcuerdosSeg'] ?? '—'), ENT_QUOTES, 'UTF-8')); ?></p>
            <p><strong>Tareas:</strong> <?= nl2br(htmlspecialchars((string) ($seg['TareasAsignadas'] ?? '—'), ENT_QUOTES, 'UTF-8')); ?></p>
            <p><strong>Próxima acción:</strong> <?= nl2br(htmlspecialchars((string) ($seg['ProximaAccion'] ?? '—'), ENT_QUOTES, 'UTF-8')); ?></p>
        </section>

        <?php if ($evolucion): ?>
            <section class="psi-expediente-section">
                <h3>Evolución</h3>
                <p><strong>Avances:</strong> <?= nl2br(htmlspecialchars((string) ($evolucion['AvancesSeg'] ?? '—'), ENT_QUOTES, 'UTF-8')); ?></p>
                <p><strong>Dificultades:</strong> <?= nl2br(htmlspecialchars((string) ($evolucion['DificultadesSeg'] ?? '—'), ENT_QUOTES, 'UTF-8')); ?></p>
                <p><strong>Retrocesos:</strong> <?= nl2br(htmlspecialchars((string) ($evolucion['RetrocesosSeg'] ?? '—'), ENT_QUOTES, 'UTF-8')); ?></p>
                <p><strong>Cumplimiento de tareas:</strong> <?= htmlspecialchars((string) ($evolucion['CumplimientoTareas'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Pronóstico:</strong> <?= nl2br(htmlspecialchars((string) ($evolucion['PronosticoActual'] ?? '—'), ENT_QUOTES, 'UTF-8')); ?></p>
            </section>
        <?php endif; ?>

        <?php if ($diagnostico): ?>
            <section class="psi-expediente-section">
                <h3>Diagnóstico de seguimiento</h3>
                <p><strong>Tipo de cambio:</strong> <?= htmlspecialchars((string) ($diagnostico['TipoCambioDiag'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Diagnóstico actual:</strong> <?= nl2br(htmlspecialchars((string) ($diagnostico['DiagnosticoActual'] ?? '—'), ENT_QUOTES, 'UTF-8')); ?></p>
                <?php if (!empty($diagnostico['JustificacionCambio'])): ?>
                    <p><strong>Justificación:</strong> <?= nl2br(htmlspecialchars((string) $diagnostico['JustificacionCambio'], ENT_QUOTES, 'UTF-8')); ?></p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="psi-expediente-section">
            <h3>Recomendaciones</h3>
            <?php if ($recomendaciones === []): ?>
                <p>Sin recomendaciones registradas.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($recomendaciones as $rec): ?>
                        <li class="<?= !empty($rec['Cumplida']) ? 'psi-rec-cumplida' : ''; ?>">
                            <strong>
                                <?= htmlspecialchars(
                                    (string) ($rec['TipoRecomendacion'] ?? ''),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </strong>
                            —
                            <?= htmlspecialchars(
                                (string) ($rec['DescripcionRec'] ?? ''),
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                            <?php if (!empty($rec['Cumplida'])): ?>
                                <span class="psi-seguimiento-badge">Cumplida</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

    </div>

</section>
