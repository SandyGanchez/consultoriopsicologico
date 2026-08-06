<?php

use App\Helpers\Helper;

$escapar = static function ($valor): string {
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
};

$formatearFecha = static function (?string $fecha): string {
    $fecha = trim((string) $fecha);

    if ($fecha === '') {
        return '';
    }

    try {
        $dt = new DateTimeImmutable(
            $fecha,
            new DateTimeZone('America/Mexico_City')
        );
    } catch (Throwable $e) {
        return $fecha;
    }

    $meses = [
        1 => 'ene',
        2 => 'feb',
        3 => 'mar',
        4 => 'abr',
        5 => 'may',
        6 => 'jun',
        7 => 'jul',
        8 => 'ago',
        9 => 'sep',
        10 => 'oct',
        11 => 'nov',
        12 => 'dic'
    ];

    $dia = (int) $dt->format('j');
    $mes = $meses[(int) $dt->format('n')] ?? $dt->format('m');
    $anio = $dt->format('Y');

    return "{$dia} {$mes} {$anio}";
};

$formatearHora = static function (?string $hora): string {
    $hora = trim((string) $hora);

    if ($hora === '') {
        return '';
    }

    return preg_match('/^\d{2}:\d{2}/', $hora)
        ? substr($hora, 0, 5)
        : $hora;
};

$etiquetaEstado = static function (string $estado): string {
    return match (strtoupper($estado)) {
        'ASISTIDA' => 'Asistida',
        'CANCELADA' => 'Cancelada',
        'INASISTENCIA' => 'Inasistencia',
        'PROGRAMADA' => 'Programada',
        default => $estado
    };
};

$claseEstado = static function (string $estado): string {
    return match (strtoupper($estado)) {
        'ASISTIDA' => 'is-asistida',
        'CANCELADA' => 'is-cancelada',
        'INASISTENCIA' => 'is-inasistencia',
        'PROGRAMADA' => 'is-programada',
        default => 'is-programada'
    };
};

$iconoEstado = static function (string $estado): string {
    return match (strtoupper($estado)) {
        'ASISTIDA' => 'bi-check-circle',
        'CANCELADA' => 'bi-x-circle',
        'INASISTENCIA' => 'bi-person-x',
        'PROGRAMADA' => 'bi-calendar-check',
        default => 'bi-calendar'
    };
};

$historial = is_array($historial ?? null) ? $historial : [];
$filtroEstado = strtoupper((string) ($filtroEstado ?? 'TODAS'));
$paginaActual = max(1, (int) ($paginaActual ?? 1));
$totalPaginas = max(1, (int) ($totalPaginas ?? 1));
$totalHistorial = max(0, (int) ($totalHistorial ?? 0));

$filtros = [
    'TODAS' => 'Todas',
    'PROGRAMADA' => 'Programadas',
    'ASISTIDA' => 'Asistidas',
    'CANCELADA' => 'Canceladas',
    'INASISTENCIA' => 'Inasistencias'
];

$fechaDesde = trim((string) ($fechaDesde ?? ''));
$fechaHasta = trim((string) ($fechaHasta ?? ''));
$conteosEstado = is_array($conteosEstado ?? null) ? $conteosEstado : [];

$urlFiltro = static function (
    string $estado,
    int $pagina = 1
) use ($escapar, $fechaDesde, $fechaHasta): string {
    $params = [];

    if ($estado !== 'TODAS') {
        $params['estado'] = $estado;
    }

    if ($fechaDesde !== '') {
        $params['desde'] = $fechaDesde;
    }

    if ($fechaHasta !== '') {
        $params['hasta'] = $fechaHasta;
    }

    if ($pagina > 1) {
        $params['pagina'] = $pagina;
    }

    $query = http_build_query($params);
    $ruta = 'paciente/historial' . ($query !== '' ? '?' . $query : '');

    return $escapar(Helper::baseUrl($ruta));
};

?>

<section class="paciente-history-page">

    <header class="paciente-page-header">
        <div class="paciente-page-header-icon" aria-hidden="true">
            <i class="bi bi-clock-history"></i>
        </div>
        <div class="paciente-page-header-copy">
            <h1>Historial de citas</h1>
            <p>Revisa tus citas anteriores y su estado de atención.</p>
        </div>
    </header>

    <nav class="paciente-filter-chips" aria-label="Filtrar historial por estado">
        <?php foreach ($filtros as $valor => $etiqueta): ?>
            <?php
                $activo = $filtroEstado === $valor;
                $conteo = (int) ($conteosEstado[$valor] ?? 0);
            ?>
            <a
                href="<?= $urlFiltro($valor, 1); ?>"
                class="paciente-filter-chip<?= $activo ? ' is-active' : ''; ?>"
                <?= $activo ? 'aria-current="page"' : ''; ?>
            >
                <?= $escapar($etiqueta); ?>
                <span aria-hidden="true">(<?= $escapar((string) $conteo); ?>)</span>
            </a>
        <?php endforeach; ?>
    </nav>

    <form
        class="paciente-history-date-filters"
        method="GET"
        action="<?= $escapar(Helper::baseUrl('paciente/historial')); ?>"
    >
        <?php if ($filtroEstado !== 'TODAS'): ?>
            <input type="hidden" name="estado" value="<?= $escapar($filtroEstado); ?>">
        <?php endif; ?>
        <label>
            Desde
            <input type="date" name="desde" value="<?= $escapar($fechaDesde); ?>">
        </label>
        <label>
            Hasta
            <input type="date" name="hasta" value="<?= $escapar($fechaHasta); ?>">
        </label>
        <button type="submit" class="paciente-btn paciente-btn-secondary">
            Aplicar fechas
        </button>
    </form>

    <?php if ($totalHistorial > 0): ?>
        <p class="paciente-history-count">
            <?= $escapar($totalHistorial); ?>
            <?= $totalHistorial === 1
                ? 'cita en el historial'
                : 'citas en el historial'; ?>
            <?php if ($filtroEstado !== 'TODAS'): ?>
                · filtro
                <?= $escapar($etiquetaEstado($filtroEstado)); ?>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if (empty($historial)): ?>

        <div class="paciente-empty-state">
            <div class="paciente-empty-state-icon" aria-hidden="true">
                <i class="bi bi-journal-text"></i>
            </div>
            <h2>Aún no hay citas en tu historial</h2>
            <p>
                Cuando tus citas se registren como asistidas, canceladas o
                inasistencias, aparecerán aquí.
            </p>
            <a
                class="paciente-btn paciente-btn-primary"
                href="<?= $escapar(Helper::baseUrl('paciente/mis-citas')); ?>"
            >
                Ir a mis citas
            </a>
        </div>

    <?php else: ?>

        <ol class="paciente-history-timeline">
            <?php foreach ($historial as $cita): ?>
                <?php
                    $clv = (string) ($cita['ClvCita'] ?? '');
                    $estado = strtoupper((string) ($cita['EstadoCita'] ?? ''));
                    $motivo = trim((string) ($cita['MotivoCancelacion'] ?? ''));
                    $horaInicio = $formatearHora($cita['HraInicioCita'] ?? null);
                    $horaFin = $formatearHora($cita['HraFinCita'] ?? null);
                ?>
                <li class="paciente-history-item <?= $escapar($claseEstado($estado)); ?>">
                    <div class="paciente-history-marker" aria-hidden="true"></div>

                    <article class="paciente-history-card">
                        <div class="paciente-history-card-top">
                            <div>
                                <strong class="paciente-history-date">
                                    <?= $escapar(
                                        $formatearFecha($cita['FechaCita'] ?? null)
                                    ); ?>
                                </strong>
                                <span class="paciente-history-time">
                                    <?= $escapar($horaInicio); ?>
                                    <?php if ($horaFin !== ''): ?>
                                        – <?= $escapar($horaFin); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <span class="paciente-status <?= $escapar($claseEstado($estado)); ?>">
                                <i
                                    class="bi <?= $escapar($iconoEstado($estado)); ?>"
                                    aria-hidden="true"
                                ></i>
                                <?= $escapar($etiquetaEstado($estado)); ?>
                            </span>
                        </div>

                        <h2>
                            <?= $escapar($cita['NombreServicio'] ?? ''); ?>
                        </h2>

                        <ul class="paciente-appointments-meta">
                            <li>
                                <i class="bi bi-person" aria-hidden="true"></i>
                                <?= $escapar($cita['NombrePsicologo'] ?? ''); ?>
                            </li>
                            <li>
                                <i class="bi bi-building" aria-hidden="true"></i>
                                <?= $escapar($cita['NombreCons'] ?? ''); ?>
                            </li>
                        </ul>

                        <?php if (!empty($cita['notaOperativa'])): ?>
                            <p class="paciente-appointment-note">
                                <?= $escapar((string) $cita['notaOperativa']); ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($estado === 'CANCELADA' && $motivo !== ''): ?>
                            <p class="paciente-appointment-note">
                                Motivo de cancelación:
                                <?= $escapar($motivo); ?>
                            </p>
                        <?php endif; ?>

                        <a
                            class="paciente-btn paciente-btn-secondary"
                            href="<?= $escapar(
                                Helper::baseUrl(
                                    'paciente/cita-detalle?cita='
                                    . rawurlencode($clv)
                                )
                            ); ?>"
                        >
                            Ver detalles
                        </a>
                    </article>
                </li>
            <?php endforeach; ?>
        </ol>

        <?php if ($totalPaginas > 1): ?>
            <nav
                class="paciente-pagination"
                aria-label="Paginación del historial"
            >
                <a
                    class="paciente-pagination-link<?= $paginaActual <= 1 ? ' is-disabled' : ''; ?>"
                    href="<?= $paginaActual <= 1
                        ? '#'
                        : $urlFiltro($filtroEstado, 1); ?>"
                    <?= $paginaActual <= 1 ? 'aria-disabled="true" tabindex="-1"' : ''; ?>
                >
                    Primera
                </a>
                <a
                    class="paciente-pagination-link<?= $paginaActual <= 1 ? ' is-disabled' : ''; ?>"
                    href="<?= $paginaActual <= 1
                        ? '#'
                        : $urlFiltro($filtroEstado, $paginaActual - 1); ?>"
                    <?= $paginaActual <= 1 ? 'aria-disabled="true" tabindex="-1"' : ''; ?>
                >
                    Anterior
                </a>

                <span class="paciente-pagination-status">
                    Página <?= $escapar($paginaActual); ?>
                    de <?= $escapar($totalPaginas); ?>
                </span>

                <a
                    class="paciente-pagination-link<?= $paginaActual >= $totalPaginas ? ' is-disabled' : ''; ?>"
                    href="<?= $paginaActual >= $totalPaginas
                        ? '#'
                        : $urlFiltro($filtroEstado, $paginaActual + 1); ?>"
                    <?= $paginaActual >= $totalPaginas
                        ? 'aria-disabled="true" tabindex="-1"'
                        : ''; ?>
                >
                    Siguiente
                </a>
                <a
                    class="paciente-pagination-link<?= $paginaActual >= $totalPaginas ? ' is-disabled' : ''; ?>"
                    href="<?= $paginaActual >= $totalPaginas
                        ? '#'
                        : $urlFiltro($filtroEstado, $totalPaginas); ?>"
                    <?= $paginaActual >= $totalPaginas
                        ? 'aria-disabled="true" tabindex="-1"'
                        : ''; ?>
                >
                    Última
                </a>
            </nav>
        <?php endif; ?>

    <?php endif; ?>

</section>
