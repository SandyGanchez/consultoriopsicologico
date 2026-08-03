<?php

use App\Helpers\Helper;

$paciente = $paciente ?? [];
$historial = $historial ?? null;
$completo = $completo ?? null;
$citaHabilitadora = $citaHabilitadora ?? null;
$puedeCrear = !empty($puedeCrear);
$puedeEditar = !empty($puedeEditar);
$puedeRegistrarSeguimiento = !empty($puedeRegistrarSeguimiento);
$tieneConsentimientoPrivacidad = isset($tieneConsentimientoPrivacidad)
    ? !empty($tieneConsentimientoPrivacidad)
    : true;
$mensajeConsentimiento = $mensajeConsentimiento ?? null;
$seguimientos = $seguimientos ?? [];
$totalSeguimientos = (int) ($totalSeguimientos ?? count($seguimientos));
$tabActiva = $tabActiva ?? 'ficha';
$clvPac = (string) ($paciente['ClvPac'] ?? '');
$clvHist = (string) ($historial['ClvHist'] ?? '');
$nombre = trim((string) ($paciente['NombrePaciente'] ?? ''));

$fotoUrl = '';
if (!empty($paciente['TieneFoto']) && !empty($paciente['FotoArchivo'])) {
    $fotoUrl = Helper::baseUrl(
        'uploads/perfiles/' . $paciente['FotoArchivo']
    );
}

$baseExpediente = Helper::baseUrl(
    'psicologo/pacientes/ver/' .
    rawurlencode($clvPac) .
    '/expediente'
);

$etiquetasEstado = [
    'PROGRAMADA' => 'Programada',
    'ASISTIDA' => 'Asistida',
    'CANCELADA' => 'Cancelada',
    'INASISTENCIA' => 'Inasistencia'
];

$estado = $completo['estado'] ?? null;
$apreciacion = $completo['apreciaciones'][0] ?? null;

?>

<section class="psi-expediente-page">

    <?php if (!empty($mensajeError)): ?>
        <div class="psi-expediente-alert psi-expediente-alert--error">
            <?= htmlspecialchars((string) $mensajeError, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($mensajeExito)): ?>
        <div class="psi-expediente-alert psi-expediente-alert--success">
            <?= htmlspecialchars((string) $mensajeExito, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php
    $citasRegistrarAsistencia = is_array($citasRegistrarAsistencia ?? null)
        ? $citasRegistrarAsistencia
        : [];
    if (!empty($infoPersonalIncompleta)) {
        $tipo = 'datos';
        $titulo = 'Información incompleta';
        $mensaje = 'Puedes completar datos personales o de domicilio faltantes.';
        $etiquetaBoton = 'Completar datos faltantes';
        $urlBoton = 'psicologo/pacientes/ver/'
            . rawurlencode($clvPac)
            . '/completar-informacion?retorno=expediente';
        $etiquetaSecundaria = '';
        $urlSecundaria = '';
        require __DIR__ . '/../partials/aviso-pendiente-clinico.php';
    }
    foreach ($citasRegistrarAsistencia as $citaPend) {
        $tipo = 'asistencia';
        $titulo = 'Registrar asistencia';
        $mensaje = 'La cita ya comenzó. Registra si el paciente asistió para continuar con la documentación clínica.';
        $etiquetaBoton = 'Registrar asistencia';
        $urlBoton = 'psicologo/agenda';
        $etiquetaSecundaria = 'Ver paciente';
        $urlSecundaria = 'psicologo/pacientes/ver/' . rawurlencode($clvPac);
        require __DIR__ . '/../partials/aviso-pendiente-clinico.php';
    }
    if (!empty($historiaPendienteOperativa) || (!empty($puedeCrear) && empty($historial))) {
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
    } elseif (!empty($seguimientoPendienteOperativo) && $clvHist !== '') {
        $tipo = 'seguimiento';
        $titulo = 'Seguimiento terapéutico pendiente';
        $mensaje = '';
        $etiquetaBoton = 'Registrar seguimiento';
        $urlBoton = 'psicologo/expediente/'
            . rawurlencode($clvHist)
            . '/seguimientos/nuevo';
        $etiquetaSecundaria = '';
        $urlSecundaria = '';
        require __DIR__ . '/../partials/aviso-pendiente-clinico.php';
    }
    ?>

    <a
        href="<?= Helper::baseUrl('psicologo/pacientes'); ?>"
        class="psi-expediente-back"
    >
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
        Volver a Mis pacientes
    </a>

    <header class="psi-expediente-header">
        <div class="psi-expediente-avatar">
            <?php if ($fotoUrl !== ''): ?>
                <img
                    src="<?= htmlspecialchars($fotoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                    alt="Fotografía de <?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>"
                >
            <?php else: ?>
                <span>
                    <?= htmlspecialchars(
                        (string) ($paciente['Iniciales'] ?? 'P'),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="psi-expediente-identity">
            <h1><?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p>
                <?= htmlspecialchars(
                    (string) ($paciente['CorreoUsu'] ?? $paciente['TelefonoUsu'] ?? '—'),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </p>
            <?php if (!empty($paciente['ProximaFecha'])): ?>
                <p class="psi-expediente-next">
                    Próxima cita:
                    <?= htmlspecialchars(
                        date('d/m/Y', strtotime((string) $paciente['ProximaFecha'])),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                    ·
                    <?= htmlspecialchars(
                        substr((string) ($paciente['ProximaHora'] ?? ''), 0, 5),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </p>
            <?php endif; ?>
        </div>
    </header>

    <ul class="nav nav-pills psi-expediente-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a
                class="nav-link<?= $tabActiva === 'ficha' ? ' active' : ''; ?>"
                href="<?= htmlspecialchars($baseExpediente . '?tab=ficha', ENT_QUOTES, 'UTF-8'); ?>"
            >
                Ficha de identificación
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a
                class="nav-link<?= $tabActiva === 'historia' ? ' active' : ''; ?>"
                href="<?= htmlspecialchars($baseExpediente . '?tab=historia', ENT_QUOTES, 'UTF-8'); ?>"
            >
                Historia clínica
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a
                class="nav-link<?= $tabActiva === 'seguimiento' ? ' active' : ''; ?>"
                href="<?= htmlspecialchars($baseExpediente . '?tab=seguimiento', ENT_QUOTES, 'UTF-8'); ?>"
            >
                Seguimiento de sesiones
            </a>
        </li>
    </ul>

    <div class="psi-expediente-panel">

        <?php if ($tabActiva === 'ficha'): ?>

            <div class="psi-expediente-notice">
                Los datos personales son administrados desde el perfil del paciente.
            </div>

            <div class="psi-expediente-readonly-grid">
                <div>
                    <span>Nombre completo</span>
                    <strong><?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
                <div>
                    <span>Fecha de nacimiento</span>
                    <strong>
                        <?= !empty($paciente['FechaNacimiento'])
                            ? htmlspecialchars(
                                date('d/m/Y', strtotime((string) $paciente['FechaNacimiento'])),
                                ENT_QUOTES,
                                'UTF-8'
                            )
                            : '—'; ?>
                    </strong>
                </div>
                <div>
                    <span>Edad</span>
                    <strong>
                        <?= $paciente['Edad'] !== null
                            ? (int) $paciente['Edad'] . ' años'
                            : '—'; ?>
                    </strong>
                </div>
                <div>
                    <span>Género</span>
                    <strong>
                        <?= htmlspecialchars(
                            (string) ($paciente['GeneroPer'] ?? '—'),
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </strong>
                </div>
                <div>
                    <span>Correo</span>
                    <strong>
                        <?= htmlspecialchars(
                            (string) ($paciente['CorreoUsu'] ?? '—'),
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </strong>
                </div>
                <div>
                    <span>Teléfono</span>
                    <strong>
                        <?= htmlspecialchars(
                            (string) ($paciente['TelefonoUsu'] ?? '—'),
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </strong>
                </div>
            </div>

        <?php elseif ($tabActiva === 'historia'): ?>

            <?php if ($historial && $completo): ?>

                <div class="psi-expediente-toolbar">
                    <div>
                        <h2>Historia clínica inicial</h2>
                        <p>
                            Expediente
                            <?= htmlspecialchars(
                                (string) ($historial['NumeroExpediente'] ?? ''),
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                            · Apertura
                            <?= !empty($historial['FechaAperturaHist'])
                                ? htmlspecialchars(
                                    date(
                                        'd/m/Y',
                                        strtotime((string) $historial['FechaAperturaHist'])
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                                : '—'; ?>
                            <?php if (!empty($historial['FechaActualizacionHist'])): ?>
                                · Actualizada
                                <?= htmlspecialchars(
                                    date(
                                        'd/m/Y H:i',
                                        strtotime((string) $historial['FechaActualizacionHist'])
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if ($puedeEditar): ?>
                        <a
                            href="<?= Helper::baseUrl(
                                'psicologo/pacientes/historia/editar/' .
                                rawurlencode((string) $historial['ClvHist'])
                            ); ?>"
                            class="psi-expediente-btn"
                        >
                            Editar historia
                        </a>
                    <?php endif; ?>
                </div>

                <section class="psi-expediente-section">
                    <h3>Motivo de consulta y estado psicológico</h3>
                    <p>
                        <strong>Motivo:</strong>
                        <?= nl2br(htmlspecialchars(
                            (string) ($estado['MotivoConsulta'] ?? '—'),
                            ENT_QUOTES,
                            'UTF-8'
                        )); ?>
                    </p>
                    <?php if (!empty($estado['SintomasReferidos'])): ?>
                        <p>
                            <strong>Síntomas referidos:</strong>
                            <?= nl2br(htmlspecialchars(
                                (string) $estado['SintomasReferidos'],
                                ENT_QUOTES,
                                'UTF-8'
                            )); ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($estado['ObservacionesIniciales'])): ?>
                        <p>
                            <strong>Observaciones:</strong>
                            <?= nl2br(htmlspecialchars(
                                (string) $estado['ObservacionesIniciales'],
                                ENT_QUOTES,
                                'UTF-8'
                            )); ?>
                        </p>
                    <?php endif; ?>
                </section>

                <?php if (!empty($completo['antecedentes_patologicos'])): ?>
                    <section class="psi-expediente-section">
                        <h3>Antecedentes patológicos</h3>
                        <ul>
                            <?php foreach ($completo['antecedentes_patologicos'] as $ant): ?>
                                <li>
                                    <?= htmlspecialchars(
                                        (string) ($ant['TipoAntecedente'] ?? ''),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                    <?php if (!empty($ant['DescripcionAntecedente'])): ?>
                                        —
                                        <?= htmlspecialchars(
                                            (string) $ant['DescripcionAntecedente'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>

                <?php if (!empty($completo['antecedentes_familiares'])): ?>
                    <section class="psi-expediente-section">
                        <h3>Antecedentes familiares</h3>
                        <ul>
                            <?php foreach ($completo['antecedentes_familiares'] as $ant): ?>
                                <li>
                                    <?= htmlspecialchars(
                                        (string) ($ant['TipoAntecedenteFam'] ?? ''),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                    <?php if (!empty($ant['FamiliarRelacionado'])): ?>
                                        (<?= htmlspecialchars(
                                            (string) $ant['FamiliarRelacionado'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>)
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>

                <?php if (!empty($completo['adicciones'])): ?>
                    <section class="psi-expediente-section">
                        <h3>Adicciones</h3>
                        <ul>
                            <?php foreach ($completo['adicciones'] as $adi): ?>
                                <li>
                                    <?= htmlspecialchars(
                                        (string) ($adi['TipoAdiccion'] ?? ''),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>

                <?php if (!empty($completo['reactivos'])): ?>
                    <section class="psi-expediente-section">
                        <h3>Reactivos psicológicos</h3>
                        <ul>
                            <?php foreach ($completo['reactivos'] as $rea): ?>
                                <li>
                                    <strong>
                                        <?= htmlspecialchars(
                                            (string) ($rea['NombreReactivo'] ?? ''),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    </strong>
                                    <?php if (!empty($rea['ResultadoReactivo'])): ?>
                                        —
                                        <?= htmlspecialchars(
                                            (string) $rea['ResultadoReactivo'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>

                <?php if ($apreciacion): ?>
                    <section class="psi-expediente-section">
                        <h3>Apreciación diagnóstica</h3>
                        <p>
                            <?= nl2br(htmlspecialchars(
                                (string) ($apreciacion['DiagnosticoInicial'] ?? '—'),
                                ENT_QUOTES,
                                'UTF-8'
                            )); ?>
                        </p>
                        <?php if (!empty($apreciacion['PlanTratamiento'])): ?>
                            <p>
                                <strong>Plan:</strong>
                                <?= nl2br(htmlspecialchars(
                                    (string) $apreciacion['PlanTratamiento'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )); ?>
                            </p>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

            <?php elseif (
                empty($historial)
                && empty($tieneConsentimientoPrivacidad)
            ): ?>
                <div class="alert alert-warning" role="alert">
                    <?= htmlspecialchars(
                        (string) ($mensajeConsentimiento ??
                            'El paciente debe aceptar el Aviso de Privacidad y otorgar su consentimiento para el tratamiento de datos sensibles antes de integrar la historia clínica.'),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </div>
            <?php elseif ($puedeCrear && $citaHabilitadora): ?>

                <div class="psi-expediente-empty">
                    <h2>Historia clínica inicial pendiente</h2>
                    <p>
                        La cita asistida del
                        <?= htmlspecialchars(
                            date(
                                'd/m/Y',
                                strtotime((string) $citaHabilitadora['FechaCita'])
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                        ·
                        <?= htmlspecialchars(
                            (string) ($citaHabilitadora['NombreServicio'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                        habilita el registro clínico inicial.
                    </p>
                    <a
                        href="<?= Helper::baseUrl(
                            'psicologo/pacientes/ver/' .
                            rawurlencode($clvPac) .
                            '/historia/nueva'
                        ); ?>"
                        class="psi-expediente-btn"
                    >
                        Crear historia clínica inicial
                    </a>
                </div>

            <?php else: ?>

                <div class="psi-expediente-empty">
                    <h2>Historia clínica pendiente</h2>
                    <p>
                        El historial clínico inicial se habilitará después de
                        registrar la primera cita como asistida.
                    </p>
                </div>

            <?php endif; ?>

        <?php else: ?>

            <?php if (!$historial): ?>

                <div class="psi-expediente-empty">
                    <h2>Seguimiento de sesiones</h2>
                    <p>
                        Primero debes registrar la historia clínica inicial
                        para habilitar los seguimientos terapéuticos.
                    </p>
                </div>

            <?php else: ?>

                <div class="psi-expediente-toolbar">
                    <div>
                        <h2>Seguimiento de sesiones</h2>
                        <p>
                            <?= $totalSeguimientos; ?>
                            seguimiento<?= $totalSeguimientos === 1 ? '' : 's'; ?>
                            registrado<?= $totalSeguimientos === 1 ? '' : 's'; ?>.
                        </p>
                    </div>
                    <?php if ($puedeRegistrarSeguimiento): ?>
                        <a
                            href="<?= Helper::baseUrl(
                                'psicologo/expediente/' .
                                rawurlencode($clvHist) .
                                '/seguimientos/nuevo'
                            ); ?>"
                            class="psi-expediente-btn psi-expediente-btn--round"
                        >
                            <i class="bi bi-plus-lg" aria-hidden="true"></i>
                            Registrar seguimiento
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($seguimientos === []): ?>
                    <div class="psi-expediente-empty">
                        <p>Aún no existen seguimientos registrados.</p>
                        <?php if (!$puedeRegistrarSeguimiento): ?>
                            <p class="psi-expediente-hint">
                                No existen sesiones asistidas pendientes de registrar.
                            </p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="psi-seguimiento-list">
                        <?php foreach ($seguimientos as $seg): ?>
                            <?php
                            $obs = trim((string) ($seg['ObservacionesSeg'] ?? ''));
                            $tema = trim((string) ($seg['TemaAbordado'] ?? ''));
                            $diag = trim((string) ($seg['DiagnosticoActual'] ?? ''));
                            $obsCorta = $obs !== ''
                                ? (mb_strlen($obs) > 90 ? mb_substr($obs, 0, 90) . '…' : $obs)
                                : '—';
                            $diagCorto = $diag !== ''
                                ? (mb_strlen($diag) > 70 ? mb_substr($diag, 0, 70) . '…' : $diag)
                                : '—';
                            ?>
                            <article class="psi-seguimiento-card">
                                <div class="psi-seguimiento-card__meta">
                                    <span class="psi-seguimiento-badge">
                                        Sesión <?= (int) ($seg['NumeroSesion'] ?? 0); ?>
                                    </span>
                                    <strong>
                                        <?= !empty($seg['FechaCita'])
                                            ? htmlspecialchars(
                                                date('d/m/Y', strtotime((string) $seg['FechaCita'])),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            )
                                            : '—'; ?>
                                        <?= htmlspecialchars(
                                            substr((string) ($seg['HraInicioCita'] ?? ''), 0, 5),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    </strong>
                                    <span class="psi-seguimiento-estatus">
                                        <?= htmlspecialchars(
                                            (string) ($seg['EstatusSeg'] ?? ''),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    </span>
                                </div>
                                <div class="psi-seguimiento-card__body">
                                    <p>
                                        <span>Servicio / tema</span>
                                        <strong>
                                            <?= htmlspecialchars(
                                                (string) ($seg['NombreServicio'] ?? '—'),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                            <?php if ($tema !== ''): ?>
                                                ·
                                                <?= htmlspecialchars($tema, ENT_QUOTES, 'UTF-8'); ?>
                                            <?php endif; ?>
                                        </strong>
                                    </p>
                                    <p>
                                        <span>Observaciones</span>
                                        <strong><?= htmlspecialchars($obsCorta, ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </p>
                                    <p>
                                        <span>Diagnóstico</span>
                                        <strong><?= htmlspecialchars($diagCorto, ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </p>
                                </div>
                                <div class="psi-seguimiento-card__actions">
                                    <a
                                        href="<?= Helper::baseUrl(
                                            'psicologo/expediente/seguimientos/ver/' .
                                            rawurlencode((string) $seg['ClvSeg'])
                                        ); ?>"
                                        class="psi-expediente-btn-secondary"
                                    >
                                        Ver
                                    </a>
                                    <a
                                        href="<?= Helper::baseUrl(
                                            'psicologo/expediente/seguimientos/editar/' .
                                            rawurlencode((string) $seg['ClvSeg'])
                                        ); ?>"
                                        class="psi-expediente-btn"
                                    >
                                        Editar
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        <?php endif; ?>

    </div>

</section>
