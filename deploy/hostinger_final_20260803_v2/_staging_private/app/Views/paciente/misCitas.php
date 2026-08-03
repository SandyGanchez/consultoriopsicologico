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

$esCitaPasada = static function (array $cita): bool {
    $fecha = trim((string) ($cita['FechaCita'] ?? ''));
    $hora = trim((string) ($cita['HraInicioCita'] ?? ''));

    if ($fecha === '' || $hora === '') {
        return false;
    }

    try {
        $zona = new DateTimeZone('America/Mexico_City');
        $inicio = new DateTimeImmutable(
            $fecha . ' ' . substr($hora, 0, 8),
            $zona
        );
        $ahora = new DateTimeImmutable('now', $zona);

        return $inicio < $ahora;
    } catch (Throwable $e) {
        return false;
    }
};

$citas = is_array($citas ?? null) ? $citas : [];
$proximaCita = is_array($proximaCita ?? null) ? $proximaCita : null;
$resumenCitas = is_array($resumenCitas ?? null) ? $resumenCitas : [];
$csrf = (string) ($csrf ?? \App\Core\Session::csrfToken());

$programadas = (int) ($resumenCitas['programadas'] ?? count($citas));
$cancelables = (int) ($resumenCitas['cancelables'] ?? 0);
$noLeidas = (int) ($resumenCitas['noLeidas'] ?? 0);

$flashSuccess = trim((string) ($_SESSION['success'] ?? ''));
$flashError = trim((string) ($_SESSION['error'] ?? ''));
unset($_SESSION['success'], $_SESSION['error']);

?>

<section class="paciente-appointments-page">

    <header class="paciente-page-header">
        <div class="paciente-page-header-icon" aria-hidden="true">
            <i class="bi bi-calendar-check"></i>
        </div>
        <div class="paciente-page-header-copy">
            <h1>Mis citas</h1>
            <p>
                Consulta tus próximas citas y administra las que todavía están
                programadas.
            </p>
        </div>
        <a
            class="paciente-btn paciente-btn-primary paciente-page-header-action"
            href="<?= $escapar(Helper::baseUrl('paciente/agendar')); ?>"
        >
            Agendar cita
        </a>
    </header>

    <?php if ($flashSuccess !== ''): ?>
        <div class="paciente-flash paciente-flash--success" role="status">
            <i class="bi bi-check-circle" aria-hidden="true"></i>
            <?= $escapar($flashSuccess); ?>
        </div>
    <?php endif; ?>

    <?php if ($flashError !== ''): ?>
        <div class="paciente-flash paciente-flash--error" role="alert">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            <?= $escapar($flashError); ?>
        </div>
    <?php endif; ?>

    <section class="paciente-appointments-stats" aria-label="Resumen de citas">
        <article class="paciente-appointments-stat">
            <span class="paciente-appointments-stat-icon" aria-hidden="true">
                <i class="bi bi-calendar-check"></i>
            </span>
            <div>
                <span>Citas programadas</span>
                <strong><?= $escapar($programadas); ?></strong>
            </div>
        </article>

        <article class="paciente-appointments-stat">
            <span class="paciente-appointments-stat-icon is-next" aria-hidden="true">
                <i class="bi bi-calendar-event"></i>
            </span>
            <div>
                <span>Próxima cita</span>
                <strong>
                    <?php if ($proximaCita): ?>
                        <?= $escapar(
                            $formatearFecha($proximaCita['FechaCita'] ?? null)
                        ); ?>
                    <?php else: ?>
                        Sin programar
                    <?php endif; ?>
                </strong>
            </div>
        </article>

        <article class="paciente-appointments-stat">
            <span class="paciente-appointments-stat-icon is-cancel" aria-hidden="true">
                <i class="bi bi-calendar-x"></i>
            </span>
            <div>
                <span>Cancelaciones disponibles</span>
                <strong><?= $escapar($cancelables); ?></strong>
            </div>
        </article>

        <article class="paciente-appointments-stat">
            <span class="paciente-appointments-stat-icon is-bell" aria-hidden="true">
                <i class="bi bi-bell"></i>
            </span>
            <div>
                <span>Avisos pendientes</span>
                <strong><?= $escapar($noLeidas); ?></strong>
            </div>
        </article>
    </section>

    <?php if ($proximaCita): ?>
        <?php
            $clvProxima = (string) ($proximaCita['ClvCita'] ?? '');
            $horaInicioProx = $formatearHora($proximaCita['HraInicioCita'] ?? null);
            $horaFinProx = $formatearHora($proximaCita['HraFinCita'] ?? null);
        ?>
        <section
            class="paciente-appointments-featured"
            aria-labelledby="proxima-cita-mis-citas"
        >
            <div class="paciente-appointments-featured-date">
                <span>Tu próxima cita</span>
                <strong>
                    <?= $escapar(
                        $formatearFecha($proximaCita['FechaCita'] ?? null)
                    ); ?>
                </strong>
                <em>
                    <?= $escapar($horaInicioProx); ?>
                    <?php if ($horaFinProx !== ''): ?>
                        – <?= $escapar($horaFinProx); ?>
                    <?php endif; ?>
                </em>
            </div>
            <div class="paciente-appointments-featured-body">
                <div class="paciente-appointments-featured-head">
                    <h2 id="proxima-cita-mis-citas">
                        <?= $escapar($proximaCita['NombreServicio'] ?? 'Cita'); ?>
                    </h2>
                    <span class="paciente-status is-programada">
                        <i class="bi bi-calendar-check" aria-hidden="true"></i>
                        Programada
                    </span>
                </div>
                <ul class="paciente-appointments-meta">
                    <li>
                        <i class="bi bi-person-heart" aria-hidden="true"></i>
                        <?= $escapar($proximaCita['NombrePsicologo'] ?? ''); ?>
                    </li>
                    <li>
                        <i class="bi bi-building" aria-hidden="true"></i>
                        <?= $escapar($proximaCita['NombreCons'] ?? ''); ?>
                    </li>
                </ul>
                <a
                    class="paciente-btn paciente-btn-primary"
                    href="<?= $escapar(
                        Helper::baseUrl(
                            'paciente/cita-detalle?cita='
                            . rawurlencode($clvProxima)
                        )
                    ); ?>"
                >
                    Ver detalles
                </a>
            </div>
        </section>
    <?php endif; ?>

    <?php if (empty($citas)): ?>

        <div class="paciente-empty-state">
            <div class="paciente-empty-state-icon" aria-hidden="true">
                <i class="bi bi-calendar-plus"></i>
            </div>
            <h2>No tienes citas programadas</h2>
            <p>
                Agenda un espacio con el especialista que mejor se adapte a
                tus necesidades.
            </p>
            <a
                class="paciente-btn paciente-btn-primary"
                href="<?= $escapar(Helper::baseUrl('paciente/agendar')); ?>"
            >
                Agendar una cita
            </a>
        </div>

    <?php else: ?>

        <section
            class="paciente-appointments-list-section"
            aria-labelledby="listado-citas-programadas"
        >
            <div class="paciente-section-head">
                <h2 id="listado-citas-programadas">Citas programadas</h2>
                <a href="<?= $escapar(Helper::baseUrl('paciente/historial')); ?>">
                    Ver historial
                </a>
            </div>

            <div class="paciente-appointments-grid">
                <?php foreach ($citas as $cita): ?>
                    <?php
                        $clv = (string) ($cita['ClvCita'] ?? '');
                        $estado = strtoupper((string) ($cita['EstadoCita'] ?? ''));
                        $cancelacion = is_array($cita['cancelacion'] ?? null)
                            ? $cita['cancelacion']
                            : [];
                        $limiteHoras = $cancelacion['limiteHoras'] ?? null;
                        $textoPolitica = is_numeric($limiteHoras)
                            ? (
                                (int) $limiteHoras === 1
                                    ? '1 hora'
                                    : (int) $limiteHoras . ' horas'
                            )
                            : null;
                        $pasada = $esCitaPasada($cita);
                        $esProximaDestacada = $proximaCita
                            && ($proximaCita['ClvCita'] ?? '') === $clv;
                        $horaInicio = $formatearHora($cita['HraInicioCita'] ?? null);
                        $horaFin = $formatearHora($cita['HraFinCita'] ?? null);
                        $duracion = (int) ($cita['DuracionAplicadaMin'] ?? 0);
                    ?>

                    <article class="paciente-appointment-card<?= $esProximaDestacada ? ' is-featured-match' : ''; ?>">
                        <div class="paciente-appointment-card-date">
                            <strong>
                                <?= $escapar(
                                    $formatearFecha($cita['FechaCita'] ?? null)
                                ); ?>
                            </strong>
                            <span>
                                <?= $escapar($horaInicio); ?>
                                <?php if ($horaFin !== ''): ?>
                                    – <?= $escapar($horaFin); ?>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="paciente-appointment-card-body">
                            <div class="paciente-appointment-card-top">
                                <h3>
                                    <?= $escapar($cita['NombreServicio'] ?? ''); ?>
                                </h3>
                                <span class="paciente-status is-programada">
                                    <i class="bi bi-calendar-check" aria-hidden="true"></i>
                                    Programada
                                </span>
                            </div>

                            <ul class="paciente-appointments-meta">
                                <li>
                                    <i class="bi bi-person" aria-hidden="true"></i>
                                    <?= $escapar($cita['NombrePsicologo'] ?? ''); ?>
                                </li>
                                <li>
                                    <i class="bi bi-building" aria-hidden="true"></i>
                                    <?= $escapar($cita['NombreCons'] ?? ''); ?>
                                </li>
                                <?php if ($duracion > 0): ?>
                                    <li>
                                        <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                                        <?= $escapar($duracion); ?> minutos
                                    </li>
                                <?php endif; ?>
                                <li>
                                    <i class="bi bi-cash-coin" aria-hidden="true"></i>
                                    Costo de la consulta:
                                    <?= $escapar(
                                        Helper::formatearMonedaMxn(
                                            $cita['CostoAplicado'] ?? 0
                                        )
                                    ); ?>
                                </li>
                            </ul>

                            <p class="paciente-appointment-note paciente-appointment-note--muted">
                                Corresponde a la tarifa registrada al momento de agendar.
                            </p>

                            <?php
                                $fechaLimiteTexto = trim(
                                    (string) ($cancelacion['fechaHoraLimiteTexto'] ?? '')
                                );
                            ?>

                            <?php if ($pasada): ?>
                                <p class="paciente-appointment-note">
                                    Esta cita aún no tiene un resultado de
                                    asistencia registrado.
                                </p>
                            <?php endif; ?>

                            <?php if (
                                $estado === 'PROGRAMADA'
                                && !empty($cancelacion['puedeCancelar'])
                                && $fechaLimiteTexto !== ''
                            ): ?>
                                <p class="paciente-appointment-note">
                                    Puedes cancelar hasta el
                                    <?= $escapar($fechaLimiteTexto); ?>.
                                </p>
                            <?php endif; ?>

                            <div class="paciente-appointment-card-actions">
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

                                <?php if ($estado === 'PROGRAMADA'): ?>
                                    <?php if (!empty($cancelacion['puedeCancelar'])): ?>
                                        <button
                                            type="button"
                                            class="paciente-btn paciente-btn-danger-soft"
                                            data-bs-toggle="modal"
                                            data-bs-target="#cancelarModal<?= $escapar($clv); ?>"
                                        >
                                            Cancelar cita
                                        </button>
                                    <?php elseif (
                                        ($cancelacion['codigo'] ?? '') === 'PLAZO_INSUFICIENTE'
                                    ): ?>
                                        <p class="paciente-appointment-note">
                                            <?php if ($fechaLimiteTexto !== ''): ?>
                                                El periodo de cancelación finalizó el
                                                <?= $escapar($fechaLimiteTexto); ?>.
                                            <?php else: ?>
                                                El periodo de cancelación ha finalizado.
                                            <?php endif; ?>
                                        </p>
                                    <?php elseif (
                                        ($cancelacion['codigo'] ?? '') === 'CITA_INICIADA'
                                        || $pasada
                                    ): ?>
                                        <p class="paciente-appointment-note">
                                            La cita ya comenzó y no puede cancelarse.
                                        </p>
                                    <?php elseif (
                                        ($cancelacion['codigo'] ?? '') === 'POLITICA_NO_CONFIGURADA'
                                    ): ?>
                                        <p class="paciente-appointment-note">
                                            La política de cancelación está pendiente
                                            de configurar.
                                        </p>
                                    <?php elseif (!empty($cancelacion['mensaje'])): ?>
                                        <p class="paciente-appointment-note">
                                            <?= $escapar($cancelacion['mensaje']); ?>
                                        </p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>

                    <?php if (
                        $estado === 'PROGRAMADA'
                        && !empty($cancelacion['puedeCancelar'])
                    ): ?>
                        <div
                            class="modal fade"
                            id="cancelarModal<?= $escapar($clv); ?>"
                            tabindex="-1"
                            aria-labelledby="cancelarTitulo<?= $escapar($clv); ?>"
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
                                            <h2
                                                class="modal-title fs-5"
                                                id="cancelarTitulo<?= $escapar($clv); ?>"
                                            >
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
                                                value="<?= $escapar($clv); ?>"
                                            >

                                            <dl class="paciente-cancel-summary">
                                                <div>
                                                    <dt>Especialista</dt>
                                                    <dd>
                                                        <?= $escapar(
                                                            $cita['NombrePsicologo'] ?? ''
                                                        ); ?>
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt>Servicio</dt>
                                                    <dd>
                                                        <?= $escapar(
                                                            $cita['NombreServicio'] ?? ''
                                                        ); ?>
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt>Fecha</dt>
                                                    <dd>
                                                        <?= $escapar(
                                                            $formatearFecha(
                                                                $cita['FechaCita'] ?? null
                                                            )
                                                        ); ?>
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt>Hora</dt>
                                                    <dd>
                                                        <?= $escapar($horaInicio); ?>
                                                        <?php if ($horaFin !== ''): ?>
                                                            – <?= $escapar($horaFin); ?>
                                                        <?php endif; ?>
                                                    </dd>
                                                </div>
                                                <?php if ($textoPolitica !== null): ?>
                                                    <div>
                                                        <dt>Política</dt>
                                                        <dd>
                                                            Cancelación con al menos
                                                            <?= $escapar($textoPolitica); ?>
                                                            de anticipación.
                                                        </dd>
                                                    </div>
                                                <?php endif; ?>
                                            </dl>

                                            <p class="paciente-cancel-warning">
                                                Esta acción cambiará la cita a estado
                                                CANCELADA y no podrá revertirse desde
                                                este módulo.
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

                <?php endforeach; ?>
            </div>
        </section>

    <?php endif; ?>

</section>
