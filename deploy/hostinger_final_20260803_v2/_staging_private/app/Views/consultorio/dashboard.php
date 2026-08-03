<?php

use App\Helpers\Helper;
use App\Core\Session;

$estadoCodigo = (string) ($estadoPagina['codigo'] ?? 'BORRADOR');
$etiquetaPagina = (string) ($etiquetaPaginaPublica ?? 'Borrador');
$progreso = $progresoPublicacion ?? [
    'porcentaje' => 0,
    'completados' => [],
    'pendientes' => [],
    'listo' => false
];
$pendientesFlash = $pendientesPublicacion ?? [];
$csrf = $csrf ?? Session::csrfToken();
$cuentaActiva = (int) ($usuario['EstadoUsu'] ?? 0) === 1;
$puedePublicar = !empty($progreso['listo'])
    && in_array($estadoCodigo, ['BORRADOR', 'OCULTO'], true);

$nombreResponsable = trim(
    (string) (($usuario['NombrePer'] ?? '') . ' ' . ($usuario['ApPatPer'] ?? ''))
);
if ($nombreResponsable === '') {
    $nombreResponsable = 'Responsable';
}

$nombreConsultorio = trim((string) ($consultorio['NombreCons'] ?? 'Consultorio'));
$resumen = is_array($resumenActividad ?? null) ? $resumenActividad : [];
$alertas = is_array($alertasOperativas ?? null) ? $alertasOperativas : [];
$proximas = is_array($proximasCitas ?? null) ? $proximasCitas : [];

$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$fechaHoy = '';
try {
    $meses = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];
    $mesesCortos = [
        1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr',
        5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago',
        9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
    ];
    $dt = new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City'));
    $fechaHoy = $dt->format('j') . ' de '
        . ($meses[(int) $dt->format('n')] ?? $dt->format('m'))
        . ' de ' . $dt->format('Y');
} catch (Throwable $e) {
    $fechaHoy = date('d/m/Y');
    $mesesCortos = [];
}

$formatearHora = static function (?string $hora): string {
    $hora = trim((string) $hora);
    return $hora === '' ? '' : substr($hora, 0, 5);
};

$etiquetaEstado = static function (string $estado): string {
    return match (strtoupper($estado)) {
        'PROGRAMADA' => 'Programada',
        'ASISTIDA' => 'Asistida',
        'CANCELADA' => 'Cancelada',
        'INASISTENCIA' => 'Inasistencia',
        default => $estado !== '' ? $estado : 'Programada',
    };
};

$claseBadgeEstado = static function (string $estado): string {
    return match (strtoupper($estado)) {
        'ASISTIDA' => 'consultorio-status-badge consultorio-status-badge--asistida',
        'CANCELADA' => 'consultorio-status-badge consultorio-status-badge--cancelada',
        'INASISTENCIA' => 'consultorio-status-badge consultorio-status-badge--inasistencia',
        default => 'consultorio-status-badge consultorio-status-badge--programada',
    };
};

$programadas = (int) ($resumen['programadas'] ?? ($citasProgramadas ?? 0));
$asistidas = (int) ($resumen['asistidas'] ?? ($citasAsistidas ?? 0));
$inasistenciasN = (int) ($resumen['inasistencias'] ?? ($inasistencias ?? 0));
$canceladas = (int) ($resumen['canceladas'] ?? ($citasCanceladas ?? 0));
$totalActividad = $programadas + $asistidas + $inasistenciasN + $canceladas;

$pctActividad = static function (int $valor) use ($totalActividad): int {
    if ($totalActividad < 1) {
        return 0;
    }
    return (int) round(($valor / $totalActividad) * 100);
};

$badgePublicacion = match ($estadoCodigo) {
    'PUBLICADO' => 'consultorio-pub-badge consultorio-pub-badge--ok',
    'OCULTO' => 'consultorio-pub-badge consultorio-pub-badge--muted',
    default => 'consultorio-pub-badge consultorio-pub-badge--draft',
};

?>

<div class="container-fluid consultorio-dashboard px-0">

    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <?= $esc($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <?= $esc($error); ?>
            <?php if (!empty($pendientesFlash)): ?>
                <ul class="mb-0 mt-2">
                    <?php foreach ($pendientesFlash as $pendiente): ?>
                        <li><?= $esc($pendiente['etiqueta'] ?? ''); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="consultorio-welcome mb-4">
        <div class="consultorio-welcome__glow" aria-hidden="true"></div>
        <div class="row g-3 align-items-center position-relative">
            <div class="col-12 col-lg-8">
                <p class="consultorio-welcome__eyebrow mb-1">
                    <?= $esc($nombreConsultorio); ?>
                </p>
                <h1 class="consultorio-welcome__title mb-2">
                    Hola, <?= $esc($nombreResponsable); ?>
                </h1>
                <p class="consultorio-welcome__subtitle mb-3">
                    Consulta la actividad general y administra la operación del consultorio.
                </p>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <span class="<?= $esc($badgePublicacion); ?>">
                        <i class="bi bi-globe2" aria-hidden="true"></i>
                        <?= $esc($etiquetaPagina); ?>
                    </span>
                    <span class="consultorio-welcome__date">
                        <i class="bi bi-calendar3" aria-hidden="true"></i>
                        <?= $esc($fechaHoy); ?>
                    </span>
                    <span class="badge rounded-pill text-bg-light border">
                        Cuenta <?= $cuentaActiva ? 'activa' : 'pendiente'; ?>
                    </span>
                    <span class="badge rounded-pill text-bg-light border">
                        Configuración <?= (int) ($progreso['porcentaje'] ?? 0); ?>%
                    </span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a
                        href="<?= Helper::baseUrl('consultorio/agenda'); ?>"
                        class="btn btn-consultorio-primary"
                    >
                        <i class="bi bi-calendar-check" aria-hidden="true"></i>
                        Ver agenda
                    </a>
                    <a
                        href="<?= Helper::baseUrl('consultorio/configuracion'); ?>"
                        class="btn btn-outline-consultorio"
                    >
                        <i class="bi bi-gear" aria-hidden="true"></i>
                        Configuración
                    </a>
                    <?php if ($estadoCodigo === 'PUBLICADO'): ?>
                        <a
                            class="btn btn-outline-consultorio"
                            href="<?= Helper::baseUrl(
                                'consultorios/' . rawurlencode((string) ($consultorio['ClvCons'] ?? ''))
                            ); ?>"
                            target="_blank"
                            rel="noopener"
                        >
                            Ver página pública
                        </a>
                    <?php elseif ($puedePublicar): ?>
                        <form method="POST" action="<?= Helper::baseUrl('consultorio/publicacion/publicar'); ?>" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= $esc($csrf); ?>">
                            <button type="submit" class="btn btn-consultorio-primary">
                                Publicar página
                            </button>
                        </form>
                    <?php else: ?>
                        <a
                            class="btn btn-outline-consultorio"
                            href="<?= Helper::baseUrl('consultorio/vista-previa'); ?>"
                            target="_blank"
                            rel="noopener"
                        >
                            Vista previa
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="consultorio-welcome__aside">
                    <div class="consultorio-welcome__stat-mini">
                        <span>Hoy</span>
                        <strong><?= (int) ($citasHoy ?? 0); ?></strong>
                        <small>citas</small>
                    </div>
                    <div class="consultorio-welcome__stat-mini">
                        <span>Semana</span>
                        <strong><?= (int) ($citasSemana ?? 0); ?></strong>
                        <small>citas</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($alertas !== []): ?>
        <section class="mb-4" aria-label="Avisos operativos">
            <div class="row g-3">
                <?php foreach ($alertas as $alerta): ?>
                    <?php
                    $iconoAlerta = (string) ($alerta['icono'] ?? 'bi-info-circle');
                    $esCritica = str_contains(strtolower((string) ($alerta['titulo'] ?? '')), 'incidencia')
                        || str_contains(strtolower((string) ($alerta['titulo'] ?? '')), 'sin horario');
                    $claseAlerta = $esCritica
                        ? 'consultorio-ops-alert consultorio-ops-alert--warn'
                        : 'consultorio-ops-alert consultorio-ops-alert--info';
                    ?>
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="alert <?= $esc($claseAlerta); ?> d-flex align-items-start gap-3 mb-0 h-100 shadow-sm">
                            <span class="consultorio-ops-alert__icon" aria-hidden="true">
                                <i class="bi <?= $esc($iconoAlerta); ?>"></i>
                            </span>
                            <div class="flex-grow-1">
                                <strong class="d-block"><?= $esc($alerta['titulo'] ?? ''); ?></strong>
                                <span class="d-block small mb-2"><?= $esc($alerta['texto'] ?? ''); ?></span>
                                <a
                                    class="btn btn-sm btn-outline-consultorio"
                                    href="<?= Helper::baseUrl((string) ($alerta['href'] ?? 'consultorio')); ?>"
                                >
                                    Resolver
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="mb-4" aria-label="Resumen operativo">
        <div class="row g-3 g-xl-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <a
                    href="<?= Helper::baseUrl('consultorio/agenda'); ?>"
                    class="card consultorio-stat-card consultorio-stat-card--blue text-decoration-none h-100"
                >
                    <div class="card-body d-flex gap-3 align-items-start">
                        <span class="consultorio-stat-card__icon" aria-hidden="true">
                            <i class="bi bi-calendar2-check"></i>
                        </span>
                        <div class="min-w-0">
                            <span class="consultorio-stat-card__label">Citas de hoy</span>
                            <div class="consultorio-stat-card__value"><?= (int) ($citasHoy ?? 0); ?></div>
                            <span class="consultorio-stat-card__hint">Consulta la agenda del día</span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <a
                    href="<?= Helper::baseUrl('consultorio/agenda'); ?>"
                    class="card consultorio-stat-card consultorio-stat-card--green text-decoration-none h-100"
                >
                    <div class="card-body d-flex gap-3 align-items-start">
                        <span class="consultorio-stat-card__icon" aria-hidden="true">
                            <i class="bi bi-calendar-week"></i>
                        </span>
                        <div class="min-w-0">
                            <span class="consultorio-stat-card__label">Citas esta semana</span>
                            <div class="consultorio-stat-card__value"><?= (int) ($citasSemana ?? 0); ?></div>
                            <span class="consultorio-stat-card__hint">Actividad de los próximos días</span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <a
                    href="<?= Helper::baseUrl('consultorio/psicologos'); ?>"
                    class="card consultorio-stat-card consultorio-stat-card--peach text-decoration-none h-100"
                >
                    <div class="card-body d-flex gap-3 align-items-start">
                        <span class="consultorio-stat-card__icon" aria-hidden="true">
                            <i class="bi bi-person-badge"></i>
                        </span>
                        <div class="min-w-0">
                            <span class="consultorio-stat-card__label">Especialistas activos</span>
                            <div class="consultorio-stat-card__value"><?= (int) ($totalPsicologosActivos ?? 0); ?></div>
                            <span class="consultorio-stat-card__hint">Profesionales habilitados</span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <?php if (!empty($moduloIncidencias)): ?>
                    <a
                        href="<?= Helper::baseUrl('consultorio/incidencias'); ?>"
                        class="card consultorio-stat-card consultorio-stat-card--rose text-decoration-none h-100"
                    >
                        <div class="card-body d-flex gap-3 align-items-start">
                            <span class="consultorio-stat-card__icon" aria-hidden="true">
                                <i class="bi bi-flag"></i>
                            </span>
                            <div class="min-w-0">
                                <span class="consultorio-stat-card__label">Incidencias pendientes</span>
                                <div class="consultorio-stat-card__value"><?= (int) ($incidenciasAbiertas ?? 0); ?></div>
                                <span class="consultorio-stat-card__hint">Requieren revisión</span>
                            </div>
                        </div>
                    </a>
                <?php else: ?>
                    <a
                        href="<?= Helper::baseUrl('consultorio/servicios'); ?>"
                        class="card consultorio-stat-card consultorio-stat-card--rose text-decoration-none h-100"
                    >
                        <div class="card-body d-flex gap-3 align-items-start">
                            <span class="consultorio-stat-card__icon" aria-hidden="true">
                                <i class="bi bi-clipboard2-heart"></i>
                            </span>
                            <div class="min-w-0">
                                <span class="consultorio-stat-card__label">Servicios activos</span>
                                <div class="consultorio-stat-card__value"><?= (int) ($serviciosActivos ?? 0); ?></div>
                                <span class="consultorio-stat-card__hint">Catálogo institucional</span>
                            </div>
                        </div>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="row g-3 g-xl-4 mb-4">
        <div class="col-12 col-xl-8">
            <section class="card consultorio-panel-card shadow-sm h-100" aria-labelledby="proximas-titulo">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <h2 id="proximas-titulo" class="consultorio-panel-card__title mb-1">
                                <i class="bi bi-calendar2-event" aria-hidden="true"></i>
                                Próximas citas
                            </h2>
                            <p class="consultorio-panel-card__subtitle mb-0">
                                Vista operativa sin datos clínicos del paciente.
                            </p>
                        </div>
                        <a
                            href="<?= Helper::baseUrl('consultorio/agenda'); ?>"
                            class="btn btn-sm btn-outline-consultorio"
                        >
                            Ver agenda completa
                        </a>
                    </div>

                    <?php if ($proximas === []): ?>
                        <div class="consultorio-empty text-center py-5">
                            <i class="bi bi-calendar-x" aria-hidden="true"></i>
                            <p class="mb-0">No hay próximas citas programadas.</p>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($proximas as $cita): ?>
                                <?php
                                $horaIni = $formatearHora($cita['HraInicioCita'] ?? null);
                                $horaFin = $formatearHora($cita['HraFinCita'] ?? null);
                                $duracion = (int) ($cita['DuracionAplicadaMin'] ?? 0);
                                $estadoCita = strtoupper((string) ($cita['EstadoCita'] ?? 'PROGRAMADA'));
                                $fechaRaw = (string) ($cita['FechaCita'] ?? '');
                                $diaCita = $fechaRaw;
                                $mesCita = '';
                                try {
                                    if ($fechaRaw !== '') {
                                        $fd = new DateTimeImmutable($fechaRaw);
                                        $diaCita = $fd->format('j');
                                        $mesCita = $mesesCortos[(int) $fd->format('n')] ?? $fd->format('M');
                                    }
                                } catch (Throwable $e) {
                                    // conservar fecha cruda
                                }
                                ?>
                                <article class="consultorio-appointment-item consultorio-appointment-item--<?= $esc(strtolower($estadoCita)); ?>">
                                    <div class="consultorio-appointment-item__date" aria-label="Fecha <?= $esc($fechaRaw); ?>">
                                        <strong><?= $esc($diaCita); ?></strong>
                                        <span><?= $esc($mesCita); ?></span>
                                    </div>
                                    <div class="consultorio-appointment-item__info min-w-0">
                                        <strong class="d-block text-truncate">
                                            <?= $esc($cita['NombreServicio'] ?? 'Servicio'); ?>
                                        </strong>
                                        <span class="d-block text-truncate">
                                            <?= $esc($cita['NombrePsicologo'] ?? ''); ?>
                                        </span>
                                    </div>
                                    <div class="consultorio-appointment-item__time">
                                        <strong>
                                            <?= $esc($horaIni); ?>
                                            <?php if ($horaFin !== ''): ?>
                                                – <?= $esc($horaFin); ?>
                                            <?php endif; ?>
                                        </strong>
                                        <?php if ($duracion > 0): ?>
                                            <span><?= $esc($duracion); ?> min</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="consultorio-appointment-item__meta">
                                        <span class="<?= $esc($claseBadgeEstado($estadoCita)); ?>">
                                            <?= $esc($etiquetaEstado($estadoCita)); ?>
                                        </span>
                                        <small>
                                            Costo programado:
                                            <?= $esc(Helper::formatearMonedaMxn($cita['CostoAplicado'] ?? 0)); ?>
                                        </small>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <p class="consultorio-privacy-note mb-0 mt-3">
                        Los datos del paciente se mantienen privados en esta vista.
                    </p>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-4">
            <section class="card consultorio-panel-card shadow-sm h-100" aria-labelledby="actividad-titulo">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <h2 id="actividad-titulo" class="consultorio-panel-card__title mb-1">
                                <i class="bi bi-bar-chart-line" aria-hidden="true"></i>
                                Actividad de especialistas
                            </h2>
                            <p class="consultorio-panel-card__subtitle mb-0">
                                Resumen operativo del consultorio.
                            </p>
                        </div>
                        <a
                            href="<?= Helper::baseUrl('consultorio/actividad-especialistas'); ?>"
                            class="btn btn-sm btn-outline-consultorio"
                        >
                            Ver detalle
                        </a>
                    </div>

                    <?php
                    $metricasAct = [
                        ['label' => 'Programadas', 'valor' => $programadas, 'clase' => 'programadas'],
                        ['label' => 'Asistidas', 'valor' => $asistidas, 'clase' => 'asistidas'],
                        ['label' => 'Inasistencias', 'valor' => $inasistenciasN, 'clase' => 'inasistencias'],
                        ['label' => 'Cancelaciones', 'valor' => $canceladas, 'clase' => 'canceladas'],
                    ];
                    ?>
                    <div class="d-flex flex-column gap-3 flex-grow-1">
                        <?php foreach ($metricasAct as $m): ?>
                            <?php $pct = $pctActividad((int) $m['valor']); ?>
                            <div class="consultorio-activity-metric">
                                <div class="d-flex justify-content-between align-items-baseline gap-2 mb-1">
                                    <span><?= $esc($m['label']); ?></span>
                                    <strong><?= (int) $m['valor']; ?></strong>
                                </div>
                                <?php if ($totalActividad > 0): ?>
                                    <div
                                        class="progress consultorio-activity-progress"
                                        role="progressbar"
                                        aria-valuenow="<?= $pct; ?>"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                        aria-label="<?= $esc($m['label']); ?>: <?= $pct; ?> por ciento"
                                    >
                                        <div
                                            class="progress-bar consultorio-activity-progress__bar consultorio-activity-progress__bar--<?= $esc($m['clase']); ?>"
                                            style="width: <?= $pct; ?>%"
                                        ></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="consultorio-activity-importe mt-3">
                        <span>Importe programado</span>
                        <strong><?= $esc(Helper::formatearMonedaMxn($resumen['importeProgramado'] ?? 0)); ?></strong>
                    </div>
                    <p class="consultorio-privacy-note mb-0 mt-2">
                        Las tarifas registradas no representan necesariamente pagos cobrados.
                    </p>
                </div>
            </section>
        </div>
    </div>

    <section class="mb-2" aria-labelledby="acciones-titulo">
        <div class="d-flex justify-content-between align-items-end mb-3">
            <div>
                <h2 id="acciones-titulo" class="consultorio-section-title mb-1">Acciones rápidas</h2>
                <p class="consultorio-panel-card__subtitle mb-0">
                    Accesos directos a la operación del consultorio.
                </p>
            </div>
        </div>
        <div class="row g-3">
            <?php
            $acciones = [
                [
                    'href' => 'consultorio/agenda',
                    'icono' => 'bi-calendar3',
                    'titulo' => 'Ver agenda',
                    'desc' => 'Revisa la ocupación del día',
                ],
                [
                    'href' => 'consultorio/psicologos',
                    'icono' => 'bi-people',
                    'titulo' => 'Gestionar especialistas',
                    'desc' => 'Altas, estatus y perfiles',
                ],
                [
                    'href' => 'consultorio/servicios',
                    'icono' => 'bi-clipboard2-heart',
                    'titulo' => 'Consultar servicios',
                    'desc' => 'Catálogo y tarifas actuales',
                ],
                [
                    'href' => 'consultorio/horario',
                    'icono' => 'bi-clock',
                    'titulo' => 'Modificar horario',
                    'desc' => 'Días y horas institucionales',
                ],
                [
                    'href' => 'consultorio/configuracion',
                    'icono' => 'bi-gear',
                    'titulo' => 'Configuración',
                    'desc' => 'Identidad, ubicación y portada',
                ],
            ];
            if (!empty($moduloIncidencias)) {
                $acciones[] = [
                    'href' => 'consultorio/incidencias',
                    'icono' => 'bi-flag',
                    'titulo' => 'Revisar incidencias',
                    'desc' => 'Acceso de pacientes y psicólogos',
                ];
            }
            ?>
            <?php foreach ($acciones as $accion): ?>
                <div class="col-12 col-sm-6 col-xl-4">
                    <a
                        href="<?= Helper::baseUrl($accion['href']); ?>"
                        class="card consultorio-quick-action text-decoration-none h-100 shadow-sm"
                    >
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="consultorio-quick-action__icon" aria-hidden="true">
                                <i class="bi <?= $esc($accion['icono']); ?>"></i>
                            </span>
                            <span class="flex-grow-1 min-w-0">
                                <strong class="d-block"><?= $esc($accion['titulo']); ?></strong>
                                <span class="d-block"><?= $esc($accion['desc']); ?></span>
                            </span>
                            <i class="bi bi-arrow-right consultorio-quick-action__arrow" aria-hidden="true"></i>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

</div>
