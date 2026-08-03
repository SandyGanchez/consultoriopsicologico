<?php

use App\Helpers\Helper;

$cita = is_array($cita ?? null) ? $cita : [];
$cancelacion = is_array($cancelacion ?? null) ? $cancelacion : [];
$csrf = (string) ($csrf ?? \App\Core\Session::csrfToken());

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
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre'
    ];

    $dia = (int) $dt->format('j');
    $mes = $meses[(int) $dt->format('n')] ?? $dt->format('m');
    $anio = $dt->format('Y');

    return "{$dia} de {$mes} de {$anio}";
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

$estado = strtoupper(trim((string) ($cita['EstadoCita'] ?? '')));

$etiquetaEstado = match ($estado) {
    'PROGRAMADA' => 'Programada',
    'ASISTIDA' => 'Asistida',
    'CANCELADA' => 'Cancelada',
    'INASISTENCIA' => 'Inasistencia',
    default => $estado
};

$claseEstado = match ($estado) {
    'PROGRAMADA' => 'is-programada',
    'ASISTIDA' => 'is-asistida',
    'CANCELADA' => 'is-cancelada',
    'INASISTENCIA' => 'is-inasistencia',
    default => 'is-programada'
};

$iconoEstado = match ($estado) {
    'PROGRAMADA' => 'bi-calendar-check',
    'ASISTIDA' => 'bi-check-circle',
    'CANCELADA' => 'bi-x-circle',
    'INASISTENCIA' => 'bi-person-x',
    default => 'bi-calendar'
};

$motivoCancelacion = trim((string) ($cita['MotivoCancelacion'] ?? ''));
$fechaCancelacion = trim((string) ($cita['FechaCancelacion'] ?? ''));

$limiteHoras = $cancelacion['limiteHoras']
    ?? (
        isset($cita['LimiteCancHoras'])
        && is_numeric($cita['LimiteCancHoras'])
            ? (int) $cita['LimiteCancHoras']
            : null
    );

$textoPolitica = null;

if ($limiteHoras !== null && $limiteHoras >= 0) {
    $textoPolitica = $limiteHoras === 1
        ? '1 hora'
        : $limiteHoras . ' horas';
}

$puedeCancelar = $estado === 'PROGRAMADA'
    && !empty($cancelacion['puedeCancelar']);

$fechaLimiteTexto = trim(
    (string) ($cancelacion['fechaHoraLimiteTexto'] ?? '')
);

$horaInicio = $formatearHora($cita['HraInicioCita'] ?? null);
$horaFin = $formatearHora($cita['HraFinCita'] ?? null);
$duracion = (int) ($cita['DuracionAplicadaMin'] ?? 0);
$clvCita = (string) ($cita['ClvCita'] ?? '');

$esPasadaProgramada = false;

if ($estado === 'PROGRAMADA') {
    $fechaRaw = trim((string) ($cita['FechaCita'] ?? ''));
    $horaRaw = trim((string) ($cita['HraInicioCita'] ?? ''));

    if ($fechaRaw !== '' && $horaRaw !== '') {
        try {
            $zona = new DateTimeZone('America/Mexico_City');
            $inicio = new DateTimeImmutable(
                $fechaRaw . ' ' . substr($horaRaw, 0, 8),
                $zona
            );
            $ahora = new DateTimeImmutable('now', $zona);
            $esPasadaProgramada = $inicio < $ahora;
        } catch (Throwable $e) {
            $esPasadaProgramada = false;
        }
    }
}

?>

<section class="paciente-detail-page">

    <header class="paciente-page-header">
        <div class="paciente-page-header-icon" aria-hidden="true">
            <i class="bi bi-calendar-event"></i>
        </div>
        <div class="paciente-page-header-copy">
            <h1>Detalle de la cita</h1>
            <p>Consulta la información operativa de tu cita.</p>
        </div>
    </header>

    <article class="paciente-detail-card">

        <div class="paciente-detail-head">
            <h2>
                <?= $escapar($cita['NombreServicio'] ?? 'Cita'); ?>
            </h2>
            <span class="paciente-status <?= $escapar($claseEstado); ?>">
                <i
                    class="bi <?= $escapar($iconoEstado); ?>"
                    aria-hidden="true"
                ></i>
                <?= $escapar($etiquetaEstado); ?>
            </span>
        </div>

        <dl class="paciente-detail-grid">
            <div class="paciente-detail-field">
                <dt>Fecha</dt>
                <dd>
                    <?= $escapar(
                        $formatearFecha($cita['FechaCita'] ?? null)
                    ); ?>
                </dd>
            </div>

            <div class="paciente-detail-field">
                <dt>Horario</dt>
                <dd>
                    <?= $escapar($horaInicio); ?>
                    <?php if ($horaFin !== ''): ?>
                        – <?= $escapar($horaFin); ?>
                    <?php endif; ?>
                </dd>
            </div>

            <div class="paciente-detail-field">
                <dt>Especialista</dt>
                <dd><?= $escapar($cita['NombrePsicologo'] ?? ''); ?></dd>
            </div>

            <div class="paciente-detail-field">
                <dt>Consultorio</dt>
                <dd><?= $escapar($cita['NombreCons'] ?? ''); ?></dd>
            </div>

            <?php if ($duracion > 0): ?>
                <div class="paciente-detail-field">
                    <dt>Duración</dt>
                    <dd><?= $escapar($duracion); ?> minutos</dd>
                </div>
            <?php endif; ?>

            <div class="paciente-detail-field">
                <dt>Costo de la consulta</dt>
                <dd>
                    <?= $escapar(
                        Helper::formatearMonedaMxn($cita['CostoAplicado'] ?? 0)
                    ); ?>
                </dd>
            </div>
        </dl>

        <p class="paciente-detail-note paciente-detail-note--muted">
            Corresponde a la tarifa registrada al momento de agendar.
        </p>

        <?php if ($esPasadaProgramada): ?>
            <p class="paciente-detail-note is-warning">
                Esta cita aún no tiene un resultado de asistencia registrado.
            </p>
        <?php endif; ?>

        <?php if ($estado === 'CANCELADA' && $motivoCancelacion !== ''): ?>
            <p class="paciente-detail-note">
                Motivo de cancelación:
                <?= $escapar($motivoCancelacion); ?>
            </p>
        <?php endif; ?>

        <?php if ($estado === 'CANCELADA' && $fechaCancelacion !== ''): ?>
            <p class="paciente-detail-note">
                Fecha de cancelación:
                <?= $escapar($fechaCancelacion); ?>
            </p>
        <?php endif; ?>

        <?php if ($estado === 'PROGRAMADA'): ?>
            <?php if ($puedeCancelar && $fechaLimiteTexto !== ''): ?>
                <p class="paciente-detail-note">
                    Puedes cancelar hasta el
                    <?= $escapar($fechaLimiteTexto); ?>.
                </p>
            <?php elseif (($cancelacion['codigo'] ?? '') === 'PLAZO_INSUFICIENTE'): ?>
                <p class="paciente-detail-note is-warning">
                    <?php if ($fechaLimiteTexto !== ''): ?>
                        El periodo de cancelación finalizó el
                        <?= $escapar($fechaLimiteTexto); ?>.
                    <?php else: ?>
                        El periodo de cancelación ha finalizado.
                    <?php endif; ?>
                </p>
            <?php elseif (
                ($cancelacion['codigo'] ?? '') === 'CITA_INICIADA'
                || $esPasadaProgramada
            ): ?>
                <p class="paciente-detail-note is-warning">
                    La cita ya comenzó y no puede cancelarse.
                </p>
            <?php elseif (!empty($cancelacion['mensaje'])): ?>
                <p class="paciente-detail-note">
                    <?= $escapar($cancelacion['mensaje']); ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>

        <div class="paciente-detail-actions">
            <a
                class="paciente-btn paciente-btn-secondary"
                href="<?= $escapar(Helper::baseUrl('paciente/mis-citas')); ?>"
            >
                Volver a mis citas
            </a>

            <a
                class="paciente-btn paciente-btn-secondary"
                href="<?= $escapar(Helper::baseUrl('paciente/historial')); ?>"
            >
                Ver historial
            </a>

            <?php if ($puedeCancelar): ?>
                <button
                    type="button"
                    class="paciente-btn paciente-btn-danger-soft"
                    data-bs-toggle="modal"
                    data-bs-target="#cancelarDetalleModal"
                >
                    Cancelar cita
                </button>
            <?php endif; ?>
        </div>

    </article>

</section>

<?php if ($puedeCancelar): ?>

    <div
        class="modal fade"
        id="cancelarDetalleModal"
        tabindex="-1"
        aria-labelledby="cancelarDetalleTitulo"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content paciente-cancel-modal">
                <form
                    method="POST"
                    action="<?= $escapar(
                        Helper::baseUrl('paciente/cancelar-cita')
                    ); ?>"
                >
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="cancelarDetalleTitulo">
                            ¿Deseas cancelar esta cita?
                        </h2>
                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Cerrar"
                        ></button>
                    </div>
                    <div class="modal-body">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= $escapar($csrf); ?>"
                        >
                        <input
                            type="hidden"
                            name="cita"
                            value="<?= $escapar($clvCita); ?>"
                        >
                        <p class="paciente-cancel-warning">
                            Esta acción cambiará la cita a estado CANCELADA
                            y no podrá revertirse desde este módulo.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button
                            type="button"
                            class="paciente-btn paciente-btn-secondary"
                            data-bs-dismiss="modal"
                        >
                            Conservar cita
                        </button>
                        <button
                            type="submit"
                            class="paciente-btn paciente-btn-danger-soft"
                        >
                            Sí, cancelar cita
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php endif; ?>
