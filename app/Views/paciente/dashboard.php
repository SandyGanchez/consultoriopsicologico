<?php

use App\Helpers\Helper;

$escapar = static function ($valor): string {
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
};

$formatearFechaCorta = static function (?string $fecha): string {
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

$formatearFechaLarga = static function (?string $fecha): string {
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

    if (preg_match('/^\d{2}:\d{2}/', $hora)) {
        return substr($hora, 0, 5);
    }

    return $hora;
};

$formatearRelativo = static function (?string $fecha): string {
    if ($fecha === null || trim($fecha) === '') {
        return '';
    }

    try {
        $zona = new DateTimeZone('America/Mexico_City');
        $momento = new DateTimeImmutable($fecha, $zona);
        $ahora = new DateTimeImmutable('now', $zona);
        $segundos = $ahora->getTimestamp() - $momento->getTimestamp();

        if ($segundos < 60) {
            return 'Hace un momento';
        }

        if ($segundos < 3600) {
            $mins = (int) floor($segundos / 60);

            return $mins === 1
                ? 'Hace 1 minuto'
                : "Hace {$mins} minutos";
        }

        if ($segundos < 86400) {
            $horas = (int) floor($segundos / 3600);

            return $horas === 1
                ? 'Hace 1 hora'
                : "Hace {$horas} horas";
        }

        $dias = (int) floor($segundos / 86400);

        return $dias === 1
            ? 'Hace 1 día'
            : "Hace {$dias} días";
    } catch (Throwable $e) {
        return '';
    }
};

$etiquetaEstado = static function (string $estado): string {
    return match (strtoupper($estado)) {
        'PROGRAMADA' => 'Programada',
        'ASISTIDA' => 'Asistida',
        'CANCELADA' => 'Cancelada',
        'INASISTENCIA' => 'Inasistencia',
        default => $estado
    };
};

$claseEstado = static function (string $estado): string {
    return match (strtoupper($estado)) {
        'PROGRAMADA' => 'is-programada',
        'ASISTIDA' => 'is-asistida',
        'CANCELADA' => 'is-cancelada',
        'INASISTENCIA' => 'is-inasistencia',
        default => 'is-programada'
    };
};

$nombrePaciente = trim((string) ($nombrePaciente ?? ''));
$fechaActual = (string) ($fechaActual ?? '');
$proximaCita = is_array($proximaCita ?? null) ? $proximaCita : null;
$siguientesCitas = is_array($siguientesCitas ?? null) ? $siguientesCitas : [];
$resumenCitas = is_array($resumenCitas ?? null) ? $resumenCitas : [];
$actividadReciente = is_array($actividadReciente ?? null) ? $actividadReciente : [];
$notificacionesRecientes = is_array($notificacionesRecientes ?? null)
    ? $notificacionesRecientes
    : [];
$notificacionesNoLeidas = (int) ($notificacionesNoLeidas ?? 0);

$programadasFuturas = (int) ($resumenCitas['programadasFuturas'] ?? 0);
$asistidas = (int) ($resumenCitas['asistidas'] ?? 0);
$canceladas = (int) ($resumenCitas['canceladas'] ?? 0);

?>

<section class="paciente-dashboard">

    <?php
        require __DIR__ . '/partials/aviso-perfil-incompleto.php';
    ?>

    <header class="paciente-welcome">

        <div class="paciente-welcome-copy">

            <p class="paciente-welcome-date">
                <?= $escapar($fechaActual); ?>
            </p>

            <h1>
                Hola, <?= $escapar($nombrePaciente); ?>
            </h1>

            <p class="paciente-welcome-text">
                Organiza tus citas y consulta tus próximos espacios de atención.
            </p>

        </div>

        <div
            class="paciente-welcome-art"
            aria-hidden="true"
        >
            <span class="paciente-welcome-orb paciente-welcome-orb--a"></span>
            <span class="paciente-welcome-orb paciente-welcome-orb--b"></span>
            <i class="bi bi-heart-pulse-fill"></i>
        </div>

    </header>

    <section
        class="paciente-stats"
        aria-label="Resumen de actividad"
    >

        <article class="paciente-stat paciente-stat--proximas">
            <div class="paciente-stat-icon" aria-hidden="true">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div>
                <span class="paciente-stat-label">Próximas citas</span>
                <strong class="paciente-stat-value">
                    <?= $escapar($programadasFuturas); ?>
                </strong>
            </div>
        </article>

        <article class="paciente-stat paciente-stat--asistidas">
            <div class="paciente-stat-icon" aria-hidden="true">
                <i class="bi bi-check-circle"></i>
            </div>
            <div>
                <span class="paciente-stat-label">Citas asistidas</span>
                <strong class="paciente-stat-value">
                    <?= $escapar($asistidas); ?>
                </strong>
            </div>
        </article>

        <article class="paciente-stat paciente-stat--canceladas">
            <div class="paciente-stat-icon" aria-hidden="true">
                <i class="bi bi-calendar-x"></i>
            </div>
            <div>
                <span class="paciente-stat-label">Citas canceladas</span>
                <strong class="paciente-stat-value">
                    <?= $escapar($canceladas); ?>
                </strong>
            </div>
        </article>

        <article class="paciente-stat paciente-stat--avisos">
            <div class="paciente-stat-icon" aria-hidden="true">
                <i class="bi bi-bell"></i>
            </div>
            <div>
                <span class="paciente-stat-label">Avisos pendientes</span>
                <strong class="paciente-stat-value">
                    <?= $escapar($notificacionesNoLeidas); ?>
                </strong>
            </div>
        </article>

    </section>

    <div class="paciente-dashboard-grid">

        <div class="paciente-dashboard-main">

            <?php if ($proximaCita): ?>

                <?php
                    $clvProxima = (string) ($proximaCita['ClvCita'] ?? '');
                    $horaInicio = $formatearHora(
                        $proximaCita['HraInicioCita'] ?? null
                    );
                    $horaFin = $formatearHora(
                        $proximaCita['HraFinCita'] ?? null
                    );
                    $duracion = (int) (
                        $proximaCita['DuracionAplicadaMin'] ?? 0
                    );
                ?>

                <section
                    class="paciente-next-appointment"
                    aria-labelledby="proxima-cita-titulo"
                >

                    <div class="paciente-next-appointment-date">
                        <span>Tu próxima cita</span>
                        <strong>
                            <?= $escapar(
                                $formatearFechaLarga(
                                    $proximaCita['FechaCita'] ?? null
                                )
                            ); ?>
                        </strong>
                        <em>
                            <?= $escapar($horaInicio); ?>
                            <?php if ($horaFin !== ''): ?>
                                – <?= $escapar($horaFin); ?>
                            <?php endif; ?>
                        </em>
                    </div>

                    <div class="paciente-next-appointment-body">

                        <div class="paciente-next-appointment-head">
                            <h2 id="proxima-cita-titulo">
                                <?= $escapar(
                                    $proximaCita['NombreServicio'] ?? 'Cita'
                                ); ?>
                            </h2>
                            <span class="paciente-badge is-programada">
                                Programada
                            </span>
                        </div>

                        <ul class="paciente-next-appointment-meta">
                            <li>
                                <i class="bi bi-person-heart" aria-hidden="true"></i>
                                <span>
                                    <?= $escapar(
                                        $proximaCita['NombrePsicologo'] ?? ''
                                    ); ?>
                                </span>
                            </li>
                            <li>
                                <i class="bi bi-building" aria-hidden="true"></i>
                                <span>
                                    <?= $escapar(
                                        $proximaCita['NombreCons'] ?? ''
                                    ); ?>
                                </span>
                            </li>
                            <?php if ($duracion > 0): ?>
                                <li>
                                    <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                                    <span>
                                        <?= $escapar($duracion); ?> minutos
                                    </span>
                                </li>
                            <?php endif; ?>
                            <li>
                                <i class="bi bi-cash-coin" aria-hidden="true"></i>
                                <span>
                                    Costo de la consulta:
                                    <?= $escapar(
                                        Helper::formatearMonedaMxn(
                                            $proximaCita['CostoAplicado'] ?? 0
                                        )
                                    ); ?>
                                </span>
                            </li>
                        </ul>

                        <p class="paciente-appointment-note paciente-appointment-note--muted">
                            Corresponde a la tarifa registrada al momento de agendar.
                        </p>

                        <div class="paciente-next-appointment-actions">
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
                            <a
                                class="paciente-btn paciente-btn-secondary"
                                href="<?= $escapar(
                                    Helper::baseUrl('paciente/mis-citas')
                                ); ?>"
                            >
                                Ver mis citas
                            </a>
                        </div>

                    </div>

                </section>

            <?php else: ?>

                <section
                    class="paciente-next-appointment paciente-next-appointment--empty"
                    aria-labelledby="sin-proxima-cita"
                >

                    <div class="paciente-empty-icon" aria-hidden="true">
                        <i class="bi bi-calendar-plus"></i>
                    </div>

                    <h2 id="sin-proxima-cita">
                        Aún no tienes una próxima cita
                    </h2>

                    <p>
                        Encuentra un especialista y agenda un horario
                        que se adapte a ti.
                    </p>

                    <a
                        class="paciente-btn paciente-btn-primary"
                        href="<?= $escapar(
                            Helper::baseUrl('paciente/agendar')
                        ); ?>"
                    >
                        Agendar una cita
                    </a>

                </section>

            <?php endif; ?>

            <section
                class="paciente-quick-actions"
                aria-labelledby="acciones-rapidas-titulo"
            >

                <h2 id="acciones-rapidas-titulo">
                    ¿Qué deseas hacer?
                </h2>

                <div class="paciente-quick-actions-grid">

                    <a
                        class="paciente-quick-action"
                        href="<?= $escapar(
                            Helper::baseUrl('paciente/agendar')
                        ); ?>"
                    >
                        <span class="paciente-quick-action-icon" aria-hidden="true">
                            <i class="bi bi-calendar-plus"></i>
                        </span>
                        <span class="paciente-quick-action-copy">
                            <strong>Agendar una cita</strong>
                            <small>
                                Consulta especialistas, servicios y horarios disponibles.
                            </small>
                        </span>
                    </a>

                    <a
                        class="paciente-quick-action"
                        href="<?= $escapar(
                            Helper::baseUrl('paciente/mis-citas')
                        ); ?>"
                    >
                        <span class="paciente-quick-action-icon" aria-hidden="true">
                            <i class="bi bi-calendar-week"></i>
                        </span>
                        <span class="paciente-quick-action-copy">
                            <strong>Mis citas</strong>
                            <small>
                                Revisa tus citas programadas y anteriores.
                            </small>
                        </span>
                    </a>

                    <a
                        class="paciente-quick-action"
                        href="<?= $escapar(
                            Helper::baseUrl('notificaciones')
                        ); ?>"
                    >
                        <span class="paciente-quick-action-icon" aria-hidden="true">
                            <i class="bi bi-bell"></i>
                        </span>
                        <span class="paciente-quick-action-copy">
                            <strong>Notificaciones</strong>
                            <small>
                                Consulta avisos y actualizaciones de tus citas.
                            </small>
                        </span>
                    </a>

                    <a
                        class="paciente-quick-action"
                        href="<?= $escapar(
                            Helper::baseUrl('paciente/perfil')
                        ); ?>"
                    >
                        <span class="paciente-quick-action-icon" aria-hidden="true">
                            <i class="bi bi-person-vcard"></i>
                        </span>
                        <span class="paciente-quick-action-copy">
                            <strong>Mi perfil</strong>
                            <small>
                                Actualiza tus datos personales.
                            </small>
                        </span>
                    </a>

                </div>

            </section>

            <section
                class="paciente-appointments"
                aria-labelledby="proximas-citas-titulo"
            >

                <div class="paciente-section-head">
                    <h2 id="proximas-citas-titulo">Próximas citas</h2>
                    <a href="<?= $escapar(
                        Helper::baseUrl('paciente/mis-citas')
                    ); ?>">
                        Ver todas
                    </a>
                </div>

                <?php if (empty($siguientesCitas)): ?>

                    <div class="paciente-panel-empty">
                        <?php if ($proximaCita): ?>
                            No tienes más citas programadas después de la próxima.
                        <?php else: ?>
                            Cuando agendas una cita, aparecerá aquí.
                        <?php endif; ?>
                    </div>

                <?php else: ?>

                    <div class="table-responsive paciente-appointments-table-wrap">
                        <table class="paciente-appointments-table">
                            <caption class="visually-hidden">
                                Listado de próximas citas programadas
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col">Fecha</th>
                                    <th scope="col">Hora</th>
                                    <th scope="col">Especialista</th>
                                    <th scope="col">Servicio</th>
                                    <th scope="col">Estado</th>
                                    <th scope="col">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($siguientesCitas as $cita): ?>
                                    <?php
                                        $clv = (string) ($cita['ClvCita'] ?? '');
                                        $estado = strtoupper(
                                            (string) ($cita['EstadoCita'] ?? '')
                                        );
                                    ?>
                                    <tr>
                                        <td>
                                            <?= $escapar(
                                                $formatearFechaCorta(
                                                    $cita['FechaCita'] ?? null
                                                )
                                            ); ?>
                                        </td>
                                        <td>
                                            <?= $escapar(
                                                $formatearHora(
                                                    $cita['HraInicioCita'] ?? null
                                                )
                                            ); ?>
                                        </td>
                                        <td>
                                            <?= $escapar(
                                                $cita['NombrePsicologo'] ?? ''
                                            ); ?>
                                        </td>
                                        <td>
                                            <?= $escapar(
                                                $cita['NombreServicio'] ?? ''
                                            ); ?>
                                        </td>
                                        <td>
                                            <span class="paciente-badge <?= $escapar(
                                                $claseEstado($estado)
                                            ); ?>">
                                                <?= $escapar(
                                                    $etiquetaEstado($estado)
                                                ); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a
                                                class="paciente-link-action"
                                                href="<?= $escapar(
                                                    Helper::baseUrl(
                                                        'paciente/cita-detalle?cita='
                                                        . rawurlencode($clv)
                                                    )
                                                ); ?>"
                                            >
                                                Ver
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="paciente-appointments-cards">
                        <?php foreach ($siguientesCitas as $cita): ?>
                            <?php
                                $clv = (string) ($cita['ClvCita'] ?? '');
                                $estado = strtoupper(
                                    (string) ($cita['EstadoCita'] ?? '')
                                );
                            ?>
                            <article class="paciente-appointment-card">
                                <div class="paciente-appointment-card-top">
                                    <strong>
                                        <?= $escapar(
                                            $formatearFechaCorta(
                                                $cita['FechaCita'] ?? null
                                            )
                                        ); ?>
                                    </strong>
                                    <span class="paciente-badge <?= $escapar(
                                        $claseEstado($estado)
                                    ); ?>">
                                        <?= $escapar(
                                            $etiquetaEstado($estado)
                                        ); ?>
                                    </span>
                                </div>
                                <p>
                                    <i class="bi bi-clock" aria-hidden="true"></i>
                                    <?= $escapar(
                                        $formatearHora(
                                            $cita['HraInicioCita'] ?? null
                                        )
                                    ); ?>
                                </p>
                                <p>
                                    <i class="bi bi-person" aria-hidden="true"></i>
                                    <?= $escapar(
                                        $cita['NombrePsicologo'] ?? ''
                                    ); ?>
                                </p>
                                <p>
                                    <i class="bi bi-journal-medical" aria-hidden="true"></i>
                                    <?= $escapar(
                                        $cita['NombreServicio'] ?? ''
                                    ); ?>
                                </p>
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
                        <?php endforeach; ?>
                    </div>

                <?php endif; ?>

            </section>

            <?php if (!empty($actividadReciente)): ?>

                <section
                    class="paciente-activity"
                    aria-labelledby="actividad-reciente-titulo"
                >

                    <div class="paciente-section-head">
                        <h2 id="actividad-reciente-titulo">
                            Actividad reciente
                        </h2>
                        <a href="<?= $escapar(
                            Helper::baseUrl('paciente/historial')
                        ); ?>">
                            Ver historial
                        </a>
                    </div>

                    <ul class="paciente-activity-list">
                        <?php foreach ($actividadReciente as $item): ?>
                            <?php
                                $estado = strtoupper(
                                    (string) ($item['EstadoCita'] ?? '')
                                );
                            ?>
                            <li>
                                <div>
                                    <strong>
                                        <?= $escapar(
                                            $item['NombreServicio'] ?? ''
                                        ); ?>
                                    </strong>
                                    <small>
                                        <?= $escapar(
                                            $formatearFechaCorta(
                                                $item['FechaCita'] ?? null
                                            )
                                        ); ?>
                                        ·
                                        <?= $escapar(
                                            $item['NombrePsicologo'] ?? ''
                                        ); ?>
                                    </small>
                                </div>
                                <span class="paciente-badge <?= $escapar(
                                    $claseEstado($estado)
                                ); ?>">
                                    <?= $escapar(
                                        $etiquetaEstado($estado)
                                    ); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                </section>

            <?php endif; ?>

        </div>

        <aside
            class="paciente-dashboard-aside"
            aria-labelledby="notificaciones-dashboard-titulo"
        >

            <section class="paciente-notifications-panel">

                <div class="paciente-section-head">
                    <h2 id="notificaciones-dashboard-titulo">
                        Notificaciones recientes
                    </h2>
                    <?php if ($notificacionesNoLeidas > 0): ?>
                        <span class="paciente-notifications-count">
                            <?= $escapar($notificacionesNoLeidas); ?>
                            sin leer
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (empty($notificacionesRecientes)): ?>

                    <div class="paciente-panel-empty">
                        Todo está al día. No tienes notificaciones nuevas.
                    </div>

                <?php else: ?>

                    <ul class="paciente-notifications-list">
                        <?php foreach ($notificacionesRecientes as $n): ?>
                            <?php
                                $clave = (string) ($n['ClvNotif'] ?? '');
                                $leida = (int) ($n['LeidaNotif'] ?? 0) === 1;
                            ?>
                            <li class="<?= $leida ? '' : 'is-unread'; ?>">
                                <a
                                    href="<?= $escapar(
                                        Helper::baseUrl(
                                            'notificaciones/abrir/'
                                            . rawurlencode($clave)
                                        )
                                    ); ?>"
                                >
                                    <strong>
                                        <?= $escapar(
                                            $n['TituloNotif'] ?? 'Notificación'
                                        ); ?>
                                    </strong>
                                    <span>
                                        <?= $escapar(
                                            $n['MensajeNotif'] ?? ''
                                        ); ?>
                                    </span>
                                    <small>
                                        <?= $escapar(
                                            $formatearRelativo(
                                                $n['FechaNotif'] ?? null
                                            )
                                        ); ?>
                                    </small>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                <?php endif; ?>

                <a
                    class="paciente-notifications-all"
                    href="<?= $escapar(
                        Helper::baseUrl('notificaciones')
                    ); ?>"
                >
                    Ver todas
                </a>

            </section>

        </aside>

    </div>

</section>
