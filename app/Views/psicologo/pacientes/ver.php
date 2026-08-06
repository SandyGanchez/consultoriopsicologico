<?php

use App\Helpers\Helper;

$paciente = $paciente ?? [];
$citas = $citas ?? [];
$datosPersonales = is_array($datosPersonales ?? null) ? $datosPersonales : $paciente;
$faltantesPersonales = is_array($faltantesPersonales ?? null)
    ? $faltantesPersonales
    : ['persona' => [], 'direccion' => []];
$infoPersonalIncompleta = !empty($infoPersonalIncompleta);
$mensajeExito = $mensajeExito ?? null;
$mensajeError = $mensajeError ?? null;

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

$textoOPendiente = static function (mixed $valor): string {
    $texto = trim((string) ($valor ?? ''));

    return $texto !== '' ? $texto : 'Pendiente de registrar';
};

$esPendiente = static function (mixed $valor): bool {
    return trim((string) ($valor ?? '')) === '';
};

$fechaNacTexto = '';
if (!empty($datosPersonales['FechaNacimiento'])) {
    $fechaNacTexto = date(
        'd/m/Y',
        strtotime((string) $datosPersonales['FechaNacimiento'])
    );
}

$ubicacionPartes = array_filter([
    trim((string) ($datosPersonales['MunicipioDir'] ?? '')),
    trim((string) ($datosPersonales['EstadoDir'] ?? '')),
    trim((string) ($datosPersonales['PaisDir'] ?? ''))
]);
$ubicacionTexto = $ubicacionPartes !== []
    ? implode(', ', $ubicacionPartes)
    : '';

if (
    $mensajeError === null
    && !empty($_SESSION['error'])
) {
    $mensajeError = (string) $_SESSION['error'];
    unset($_SESSION['error']);
}

if (
    $mensajeExito === null
    && !empty($_SESSION['success'])
) {
    $mensajeExito = (string) $_SESSION['success'];
    unset($_SESSION['success']);
}

?>

<section class="psychologist-patients-page psychologist-patient-detail">

    <?php if (!empty($mensajeExito)): ?>
        <div class="alert alert-success" role="status">
            <?= htmlspecialchars((string) $mensajeExito); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($mensajeError)): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars((string) $mensajeError); ?>
        </div>
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

    <?php
    $citasRegistrarAsistencia = is_array($citasRegistrarAsistencia ?? null)
        ? $citasRegistrarAsistencia
        : [];
    $citasSeguimientoPendiente = is_array($citasSeguimientoPendiente ?? null)
        ? $citasSeguimientoPendiente
        : [];
    $clvHistPendiente = (string) ($clvHistPendiente ?? '');
    ?>

    <?php if ($infoPersonalIncompleta): ?>
        <?php
        $tipo = 'datos';
        $titulo = 'Información incompleta';
        $mensaje = 'Faltan datos personales o de domicilio que puedes completar.';
        $etiquetaBoton = 'Completar datos faltantes';
        $urlBoton = 'psicologo/pacientes/ver/'
            . rawurlencode($clvPac)
            . '/completar-informacion?retorno=detalle';
        $etiquetaSecundaria = '';
        $urlSecundaria = '';
        require __DIR__ . '/../partials/aviso-pendiente-clinico.php';
        ?>
    <?php endif; ?>

    <?php foreach ($citasRegistrarAsistencia as $citaPend): ?>
        <?php
        $tipo = 'asistencia';
        $titulo = 'Registrar asistencia';
        $mensaje = 'Esta cita está pendiente de registrar asistencia.';
        $etiquetaBoton = 'Registrar asistencia';
        $urlBoton = 'psicologo/agenda';
        $etiquetaSecundaria = 'Completar datos faltantes';
        $urlSecundaria = $infoPersonalIncompleta
            ? 'psicologo/pacientes/ver/'
                . rawurlencode($clvPac)
                . '/completar-informacion?retorno=detalle'
            : '';
        require __DIR__ . '/../partials/aviso-pendiente-clinico.php';
        ?>
    <?php endforeach; ?>

    <?php if (!empty($historiaPendiente)): ?>
        <?php
        $tipo = 'historia';
        $titulo = 'Historia clínica inicial pendiente';
        $mensaje = '';
        $etiquetaBoton = 'Crear historia clínica inicial';
        $urlBoton = 'psicologo/pacientes/ver/'
            . rawurlencode($clvPac)
            . '/historia/nueva';
        $etiquetaSecundaria = '';
        $urlSecundaria = '';
        require __DIR__ . '/../partials/aviso-pendiente-clinico.php';
        ?>
    <?php endif; ?>

    <?php if (!empty($seguimientoPendiente) && $clvHistPendiente !== ''): ?>
        <?php
        $tipo = 'seguimiento';
        $titulo = 'Seguimiento terapéutico pendiente';
        $mensaje = count($citasSeguimientoPendiente) . ' sesión(es) asistida(s) sin seguimiento.';
        $etiquetaBoton = 'Registrar seguimiento';
        $urlBoton = 'psicologo/expediente/'
            . rawurlencode($clvHistPendiente)
            . '/seguimientos/nuevo';
        $etiquetaSecundaria = '';
        $urlSecundaria = '';
        require __DIR__ . '/../partials/aviso-pendiente-clinico.php';
        ?>
    <?php endif; ?>

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

    <section class="psychologist-patient-ficha-personal">
        <div class="psychologist-patient-summary-grid">
            <article class="psychologist-patient-summary-card">
                <h2>Información registrada</h2>
                <dl class="psi-paciente-ficha-dl">
                    <div>
                        <dt>Nombre</dt>
                        <dd><?= htmlspecialchars($textoOPendiente($datosPersonales['NombrePer'] ?? '')); ?></dd>
                    </div>
                    <div>
                        <dt>Apellido paterno</dt>
                        <dd><?= htmlspecialchars($textoOPendiente($datosPersonales['ApPatPer'] ?? '')); ?></dd>
                    </div>
                    <?php if (!$esPendiente($datosPersonales['ApMatPer'] ?? null)): ?>
                        <div>
                            <dt>Apellido materno</dt>
                            <dd><?= htmlspecialchars((string) $datosPersonales['ApMatPer']); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($fechaNacTexto !== ''): ?>
                        <div>
                            <dt>Fecha de nacimiento</dt>
                            <dd><?= htmlspecialchars($fechaNacTexto); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!$esPendiente($datosPersonales['GeneroPer'] ?? null)): ?>
                        <div>
                            <dt>Género</dt>
                            <dd><?= htmlspecialchars((string) $datosPersonales['GeneroPer']); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($ubicacionTexto !== ''): ?>
                        <div>
                            <dt>Ubicación</dt>
                            <dd><?= htmlspecialchars($ubicacionTexto); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!$esPendiente($datosPersonales['ColoniaDir'] ?? null)): ?>
                        <div>
                            <dt>Colonia</dt>
                            <dd><?= htmlspecialchars((string) $datosPersonales['ColoniaDir']); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!$esPendiente($datosPersonales['CalleDir'] ?? null)): ?>
                        <div>
                            <dt>Calle</dt>
                            <dd><?= htmlspecialchars((string) $datosPersonales['CalleDir']); ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </article>

            <article class="psychologist-patient-summary-card">
                <h2>Información pendiente</h2>
                <?php
                $etiquetasPendientes = [
                    'NombrePer' => 'Nombre',
                    'ApPatPer' => 'Apellido paterno',
                    'ApMatPer' => 'Apellido materno',
                    'FechaNacimiento' => 'Fecha de nacimiento',
                    'GeneroPer' => 'Género',
                    'PaisDir' => 'País',
                    'EstadoDir' => 'Estado',
                    'MunicipioDir' => 'Municipio',
                    'ColoniaDir' => 'Colonia',
                    'CalleDir' => 'Calle',
                    'CodPostDir' => 'Código postal',
                    'NumExtDir' => 'Número exterior',
                    'NumIntDir' => 'Número interior',
                    'ReferenciaDir' => 'Referencia'
                ];
                $listaPendiente = array_merge(
                    $faltantesPersonales['persona'] ?? [],
                    $faltantesPersonales['direccion'] ?? []
                );
                ?>
                <?php if ($listaPendiente === []): ?>
                    <p class="psychologist-patient-summary-card__empty">
                        No hay datos personales pendientes.
                    </p>
                <?php else: ?>
                    <ul class="psi-paciente-pendientes">
                        <?php foreach ($listaPendiente as $campoPendiente): ?>
                            <li>
                                <?= htmlspecialchars(
                                    $etiquetasPendientes[$campoPendiente]
                                        ?? $campoPendiente
                                ); ?>
                                <span>Pendiente de registrar</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>
        </div>
    </section>

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

        <?php
            $filtroEstadoCitas = strtoupper((string) ($filtroEstadoCitas ?? 'TODAS'));
            $conteosEstadoCitas = is_array($conteosEstadoCitas ?? null)
                ? $conteosEstadoCitas
                : [];
            $paginaCitas = max(1, (int) ($paginaCitas ?? 1));
            $totalPaginasCitas = max(1, (int) ($totalPaginasCitas ?? 1));
            $filtrosCitas = [
                'TODAS' => 'Todas',
                'PROGRAMADA' => 'Programadas',
                'ASISTIDA' => 'Asistidas',
                'CANCELADA' => 'Canceladas',
                'INASISTENCIA' => 'Inasistencias'
            ];
            $urlFiltroCitas = static function (
                string $estado,
                int $pagina = 1
            ) use ($clvPac): string {
                $params = [];
                if ($estado !== 'TODAS') {
                    $params['estado'] = $estado;
                }
                if ($pagina > 1) {
                    $params['pagina'] = $pagina;
                }
                $query = http_build_query($params);
                return Helper::baseUrl(
                    'psicologo/pacientes/ver/' . rawurlencode($clvPac)
                    . ($query !== '' ? '?' . $query : '')
                    . ($query !== '' ? '#historial-citas' : '#historial-citas')
                );
            };
        ?>

        <nav
            class="psychologist-patient-history-filters"
            aria-label="Filtrar historial de citas por estado"
        >
            <?php foreach ($filtrosCitas as $valor => $etiqueta): ?>
                <?php $activo = $filtroEstadoCitas === $valor; ?>
                <a
                    href="<?= htmlspecialchars($urlFiltroCitas($valor, 1), ENT_QUOTES, 'UTF-8'); ?>"
                    class="<?= $activo ? 'is-active' : ''; ?>"
                    <?= $activo ? 'aria-current="page"' : ''; ?>
                >
                    <?= htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8'); ?>
                    (<?= (int) ($conteosEstadoCitas[$valor] ?? 0); ?>)
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($citas === []): ?>

            <div class="psychologist-patients-empty psychologist-patients-empty--compact">

                <p>No hay citas registradas con este paciente para este filtro.</p>

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
                            <th>Nota operativa</th>
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
                                <td>
                                    <p class="psychologist-patient-history-note">
                                        <?= htmlspecialchars(
                                            (string) ($cita['notaOperativa'] ?? ''),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    </p>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <?php if ($totalPaginasCitas > 1): ?>
                <nav class="psychologist-patient-history-filters" aria-label="Paginación de citas">
                    <?php if ($paginaCitas > 1): ?>
                        <a href="<?= htmlspecialchars($urlFiltroCitas($filtroEstadoCitas, $paginaCitas - 1), ENT_QUOTES, 'UTF-8'); ?>">
                            Anterior
                        </a>
                    <?php endif; ?>
                    <span>Página <?= (int) $paginaCitas; ?> de <?= (int) $totalPaginasCitas; ?></span>
                    <?php if ($paginaCitas < $totalPaginasCitas): ?>
                        <a href="<?= htmlspecialchars($urlFiltroCitas($filtroEstadoCitas, $paginaCitas + 1), ENT_QUOTES, 'UTF-8'); ?>">
                            Siguiente
                        </a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>

        <?php endif; ?>

    </section>

</section>
