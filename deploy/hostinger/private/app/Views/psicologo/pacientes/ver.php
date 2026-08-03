<?php

use App\Helpers\Helper;

$paciente = $paciente ?? [];
$citas = $citas ?? [];

$clvPac = (string) ($paciente['ClvPac'] ?? '');
$nombre = trim((string) ($paciente['NombrePaciente'] ?? ''));
$estado = (string) ($paciente['EstadoRelacion'] ?? 'HISTORICO');
$estadoClase = strtolower(str_replace('_', '-', $estado));

$fotoUrl = '';

if (!empty($paciente['TieneFoto']) && !empty($paciente['FotoArchivo'])) {
    $fotoUrl = Helper::baseUrl(
        'uploads/perfiles/' . $paciente['FotoArchivo']
    );
}

$etiquetasEstado = [
    'PROGRAMADA' => 'Programada',
    'ASISTIDA' => 'Asistida',
    'CANCELADA' => 'Cancelada',
    'INASISTENCIA' => 'Inasistencia'
];

?>

<section class="psychologist-patients-page psychologist-patient-detail">

    <?php if (!empty($_SESSION['error'])): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars((string) $_SESSION['error']); ?>
        </div>

        <?php unset($_SESSION['error']); ?>

    <?php endif; ?>

    <div class="psychologist-patient-detail__nav">

        <a
            href="<?= Helper::baseUrl('psicologo/pacientes'); ?>"
            class="psychologist-patients-back"
        >
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Volver a mis pacientes
        </a>

    </div>

    <article class="psychologist-patient-card psychologist-patient-detail-card">

        <div class="psychologist-patient-card__top">

            <div class="psychologist-patient-avatar psychologist-patient-avatar--lg">

                <?php if ($fotoUrl !== ''): ?>

                    <img
                        src="<?= htmlspecialchars($fotoUrl); ?>"
                        alt="Foto de <?= htmlspecialchars($nombre); ?>"
                    >

                <?php else: ?>

                    <span>
                        <?= htmlspecialchars(
                            (string) ($paciente['Iniciales'] ?? 'P')
                        ); ?>
                    </span>

                <?php endif; ?>

            </div>

            <div class="psychologist-patient-card__identity">

                <h1>
                    <?= htmlspecialchars($nombre); ?>
                </h1>

                <span
                    class="psychologist-patient-status psychologist-patient-status--<?= htmlspecialchars($estadoClase); ?>"
                >
                    <?= htmlspecialchars(
                        (string) ($paciente['EstadoEtiqueta'] ?? '')
                    ); ?>
                </span>

                <?php if ($paciente['Edad'] !== null): ?>

                    <p class="psychologist-patient-age">
                        <?= (int) $paciente['Edad']; ?> años
                    </p>

                <?php endif; ?>

            </div>

            <div class="psychologist-patient-detail__actions">

                <a
                    href="<?= Helper::baseUrl(
                        'psicologo/agenda?paciente=' .
                        rawurlencode($clvPac)
                    ); ?>"
                    class="btn psychologist-patients-primary-btn"
                >
                    <i class="bi bi-calendar-plus me-1"></i>
                    Agendar cita
                </a>

                <a
                    href="<?= Helper::baseUrl(
                        'psicologo/pacientes/ver/' .
                        rawurlencode($clvPac) .
                        '/expediente'
                    ); ?>"
                    class="btn psychologist-patients-secondary-btn"
                >
                    <i class="bi bi-folder2-open me-1"></i>
                    Expediente clínico
                </a>

            </div>

        </div>

        <div class="psychologist-patient-contact psychologist-patient-contact--detail">

            <p>
                <i class="bi bi-envelope" aria-hidden="true"></i>
                <?= htmlspecialchars(
                    (string) ($paciente['CorreoUsu'] ?? '—')
                ); ?>
            </p>

            <p>
                <i class="bi bi-telephone" aria-hidden="true"></i>
                <?= htmlspecialchars(
                    (string) ($paciente['TelefonoUsu'] ?? '—')
                ); ?>
            </p>

            <p>
                <i class="bi bi-calendar-heart" aria-hidden="true"></i>
                Relación desde
                <?= !empty($paciente['FechaDesde'])
                    ? htmlspecialchars(
                        date(
                            'd/m/Y',
                            strtotime((string) $paciente['FechaDesde'])
                        )
                    )
                    : '—'; ?>
            </p>

            <p>
                <i class="bi bi-journal-check" aria-hidden="true"></i>
                <?= (int) ($paciente['TotalCitas'] ?? 0); ?>
                cita<?= (int) ($paciente['TotalCitas'] ?? 0) === 1 ? '' : 's'; ?>
                con este especialista
            </p>

        </div>

        <p class="psychologist-patient-clinical-note">
            La historia clínica inicial se habilita después de registrar
            la primera cita como asistida.
        </p>

    </article>

    <div class="psychologist-patient-summary-grid">

        <article class="psychologist-patient-summary-card">

            <h2>Próxima cita</h2>

            <?php if (!empty($paciente['ProximaFecha'])): ?>

                <p class="psychologist-patient-summary-card__value">
                    <?= htmlspecialchars(
                        date(
                            'd/m/Y',
                            strtotime((string) $paciente['ProximaFecha'])
                        )
                    ); ?>
                    ·
                    <?= htmlspecialchars(
                        substr(
                            (string) ($paciente['ProximaHora'] ?? ''),
                            0,
                            5
                        )
                    ); ?>
                </p>

                <p class="psychologist-patient-summary-card__meta">
                    <?= htmlspecialchars(
                        (string) ($paciente['ProximaServicio'] ?? 'Servicio')
                    ); ?>
                </p>

            <?php else: ?>

                <p class="psychologist-patient-summary-card__empty">
                    No hay citas programadas futuras.
                </p>

            <?php endif; ?>

        </article>

        <article class="psychologist-patient-summary-card">

            <h2>Última cita</h2>

            <?php if (!empty($paciente['UltimaFecha'])): ?>

                <p class="psychologist-patient-summary-card__value">
                    <?= htmlspecialchars(
                        date(
                            'd/m/Y',
                            strtotime((string) $paciente['UltimaFecha'])
                        )
                    ); ?>
                    ·
                    <?= htmlspecialchars(
                        substr(
                            (string) ($paciente['UltimaHora'] ?? ''),
                            0,
                            5
                        )
                    ); ?>
                </p>

                <p class="psychologist-patient-summary-card__meta">
                    <?= htmlspecialchars(
                        (string) ($paciente['UltimaServicio'] ?? 'Servicio')
                    ); ?>
                    ·
                    <?= htmlspecialchars(
                        $etiquetasEstado[$paciente['UltimaEstado'] ?? '']
                            ?? (string) ($paciente['UltimaEstado'] ?? '')
                    ); ?>
                </p>

            <?php else: ?>

                <p class="psychologist-patient-summary-card__empty">
                    Aún no hay citas pasadas registradas.
                </p>

            <?php endif; ?>

        </article>

    </div>

    <section
        class="psychologist-patient-history"
        id="historial-citas"
    >

        <h2>Historial de citas contigo</h2>

        <?php if ($citas === []): ?>

            <div class="psychologist-patients-empty psychologist-patients-empty--compact">

                <p>No hay citas registradas con este paciente.</p>

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table psychologist-patient-history-table">

                    <thead>
                        <tr>
                            <th>Servicio</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Duración</th>
                            <th>Estado</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($citas as $cita): ?>

                            <tr>
                                <td>
                                    <?= htmlspecialchars(
                                        (string) ($cita['NombreServicio'] ?? '—')
                                    ); ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars(
                                        date(
                                            'd/m/Y',
                                            strtotime(
                                                (string) ($cita['FechaCita'] ?? '')
                                            )
                                        )
                                    ); ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars(
                                        substr(
                                            (string) ($cita['HraInicioCita'] ?? ''),
                                            0,
                                            5
                                        )
                                    ); ?>
                                    <?php if (!empty($cita['HraFinCita'])): ?>
                                        –
                                        <?= htmlspecialchars(
                                            substr(
                                                (string) $cita['HraFinCita'],
                                                0,
                                                5
                                            )
                                        ); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= (int) ($cita['DuracionAplicadaMin'] ?? 0); ?>
                                    min
                                </td>
                                <td>
                                    <span
                                        class="psychologist-patient-status psychologist-patient-status--cita-<?= strtolower(
                                            (string) ($cita['EstadoCita'] ?? '')
                                        ); ?>"
                                    >
                                        <?= htmlspecialchars(
                                            $etiquetasEstado[$cita['EstadoCita'] ?? '']
                                                ?? (string) ($cita['EstadoCita'] ?? '')
                                        ); ?>
                                    </span>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

</section>
