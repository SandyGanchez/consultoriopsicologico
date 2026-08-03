<?php

use App\Helpers\Helper;

$nombre = trim(
    ($usuario['NombrePer'] ?? '') . ' ' .
    ($usuario['ApPatPer'] ?? '')
);

if ($nombre === '') {
    $nombre = 'Especialista';
}

$citasHoy = $citasHoy ?? 0;
$totalPacientes = $totalPacientes ?? 0;
$citasSemana = $citasSemana ?? 0;
$proximasCitas = $proximasCitas ?? [];
$perfilIncompleto = (bool) ($perfilIncompleto ?? false);

?>

<section class="psicologo-dashboard">

    <div class="psicologo-page-header">

        <span class="psicologo-page-eyebrow">
            Resumen profesional
        </span>

        <h1>
            Hola, <?= htmlspecialchars($nombre); ?>
        </h1>

        <p>
            Consulta tus actividades, pacientes y próximas
            sesiones desde tu panel personal.
        </p>

    </div>

    <?php if ($perfilIncompleto): ?>

        <div
            class="alert mb-4"
            style="background:#FDE8D3;border:0;color:#657166;"
            role="status"
        >
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <strong>Completa tu perfil profesional</strong>
                    <p class="mb-0 mt-1">
                        Tu cuenta está activa, pero aún no se muestra en la
                        página pública. Completa tu perfil para continuar.
                    </p>
                </div>
                <a
                    href="<?= Helper::baseUrl('psicologo/perfil'); ?>"
                    class="btn psychologist-patients-primary-btn"
                >
                    Ir a mi perfil
                </a>
            </div>
        </div>

    <?php endif; ?>

    <?php
    $citasRegistrarAsistencia = is_array($citasRegistrarAsistencia ?? null)
        ? $citasRegistrarAsistencia
        : [];
    $historiasPendientes = is_array($historiasPendientes ?? null)
        ? $historiasPendientes
        : [];
    $seguimientosPendientes = is_array($seguimientosPendientes ?? null)
        ? $seguimientosPendientes
        : [];
    ?>

    <div
        id="psiPendientesVivo"
        data-psi-pendientes-vivo
        aria-live="polite"
        aria-atomic="false"
        aria-relevant="additions text"
    >
    <?php if ($citasRegistrarAsistencia !== []): ?>
        <div class="psi-pendiente-bloque mb-4" data-bloque="asistencia">
            <?php foreach ($citasRegistrarAsistencia as $citaPend): ?>
                <?php
                $tipo = 'asistencia';
                $titulo = 'Registrar asistencia';
                $mensaje = 'La cita ya comenzó. Registra si el paciente asistió para continuar con la documentación clínica. '
                    . trim((string) ($citaPend['NombrePaciente'] ?? 'Paciente'))
                    . ' · '
                    . date('d/m/Y', strtotime((string) ($citaPend['FechaCita'] ?? 'now')))
                    . ' '
                    . substr((string) ($citaPend['HraInicioCita'] ?? ''), 0, 5);
                $etiquetaBoton = 'Registrar asistencia';
                $urlBoton = 'psicologo/agenda';
                $etiquetaSecundaria = 'Ver paciente';
                $urlSecundaria = 'psicologo/pacientes/ver/'
                    . rawurlencode((string) ($citaPend['ClvPac'] ?? ''));
                require __DIR__ . '/partials/aviso-pendiente-clinico.php';
                ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($historiasPendientes !== []): ?>
        <div class="psi-pendiente-bloque mb-4" data-bloque="historia">
            <?php foreach ($historiasPendientes as $pendiente): ?>
                <?php
                $tipo = 'historia';
                $titulo = 'Historia clínica inicial pendiente';
                $mensaje = (string) ($pendiente['NombrePaciente'] ?? 'Paciente');
                $etiquetaBoton = 'Crear historia clínica inicial';
                $urlBoton = 'psicologo/pacientes/ver/'
                    . rawurlencode((string) ($pendiente['ClvPac'] ?? ''))
                    . '/historia/nueva';
                $etiquetaSecundaria = '';
                $urlSecundaria = '';
                require __DIR__ . '/partials/aviso-pendiente-clinico.php';
                ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($seguimientosPendientes !== []): ?>
        <div class="psi-pendiente-bloque mb-4" data-bloque="seguimiento">
            <?php foreach ($seguimientosPendientes as $segPend): ?>
                <?php
                $tipo = 'seguimiento';
                $titulo = 'Seguimiento terapéutico pendiente';
                $mensaje = trim((string) ($segPend['NombrePaciente'] ?? 'Paciente'))
                    . ' · '
                    . date('d/m/Y', strtotime((string) ($segPend['FechaCita'] ?? 'now')));
                $etiquetaBoton = 'Registrar seguimiento';
                $urlBoton = 'psicologo/expediente/'
                    . rawurlencode((string) ($segPend['ClvHist'] ?? ''))
                    . '/seguimientos/nuevo';
                $etiquetaSecundaria = '';
                $urlSecundaria = '';
                require __DIR__ . '/partials/aviso-pendiente-clinico.php';
                ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </div>

    <script>
    window.psicologoPendientes = {
        url: <?= json_encode(
            Helper::baseUrl('psicologo/agenda/pendientes-operativos'),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ); ?>,
        loginUrl: <?= json_encode(
            Helper::baseUrl('login'),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ); ?>,
        intervaloMs: 60000
    };
    </script>
    <script src="<?= Helper::assetUrl('assets/js/psicologo-pendientes-vivo.js'); ?>"></script>

    <div class="row g-4 mb-4">

        <div class="col-12 col-md-6 col-xl-4">

            <article class="psicologo-stat-card">

                <div class="psicologo-stat-icon">
                    <i class="bi bi-calendar-check"></i>
                </div>

                <div>
                    <span>Citas para hoy</span>

                    <strong>
                        <?= (int) $citasHoy; ?>
                    </strong>
                </div>

            </article>

        </div>

        <div class="col-12 col-md-6 col-xl-4">

            <article class="psicologo-stat-card">

                <div class="psicologo-stat-icon">
                    <i class="bi bi-people-fill"></i>
                </div>

                <div>
                    <span>Pacientes activos</span>

                    <strong>
                        <?= (int) $totalPacientes; ?>
                    </strong>
                </div>

            </article>

        </div>

        <div class="col-12 col-md-6 col-xl-4">

            <article class="psicologo-stat-card">

                <div class="psicologo-stat-icon">
                    <i class="bi bi-calendar-week"></i>
                </div>

                <div>
                    <span>Citas esta semana</span>

                    <strong>
                        <?= (int) $citasSemana; ?>
                    </strong>
                </div>

            </article>

        </div>

    </div>

    <div class="row g-4">

        <div class="col-12 col-xl-8">

            <div class="psicologo-panel">

                <div class="psicologo-panel-header">

                    <div>

                        <span class="psicologo-section-label">
                            Agenda
                        </span>

                        <h2>Próximas citas</h2>

                    </div>

                    <a
                        href="<?= Helper::baseUrl(
                            'psicologo/agenda'
                        ); ?>"
                        class="psicologo-link"
                    >
                        Ver agenda
                        <i class="bi bi-arrow-right"></i>
                    </a>

                </div>

                <?php if (empty($proximasCitas)): ?>

                    <div class="psicologo-empty-state">

                        <i class="bi bi-calendar2-heart"></i>

                        <h3>No hay próximas citas</h3>

                        <p>
                            Las sesiones programadas aparecerán
                            en este espacio.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="table-responsive">

                        <table class="table psicologo-table align-middle">

                            <thead>

                                <tr>
                                    <th>Paciente</th>
                                    <th>Servicio</th>
                                    <th>Fecha</th>
                                    <th>Horario</th>
                                    <th>Estado</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach (
                                    $proximasCitas as $cita
                                ): ?>

                                    <tr>

                                        <td>

                                            <div class="d-flex align-items-center gap-3">

                                                <div class="psicologo-patient-avatar">
                                                    <i class="bi bi-person"></i>
                                                </div>

                                                <strong>
                                                    <?= htmlspecialchars(
                                                        $cita[
                                                            'NombrePaciente'
                                                        ]
                                                    ); ?>
                                                </strong>

                                            </div>

                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $cita[
                                                    'NombreServicio'
                                                ]
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= date(
                                                'd/m/Y',
                                                strtotime(
                                                    $cita[
                                                        'FechaCita'
                                                    ]
                                                )
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                substr(
                                                    $cita[
                                                        'HraInicioCita'
                                                    ],
                                                    0,
                                                    5
                                                )
                                            ); ?>
                                        </td>

                                        <td>

                                            <span class="psicologo-status status-programada">
                                                Programada
                                            </span>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>

        </div>

        <div class="col-12 col-xl-4">

            <div class="psicologo-panel h-100">

                <div class="psicologo-panel-header">

                    <div>

                        <span class="psicologo-section-label">
                            Accesos rápidos
                        </span>

                        <h2>Herramientas</h2>

                    </div>

                </div>

                <div class="psicologo-quick-actions">

                    <a
                        href="<?= Helper::baseUrl(
                            'psicologo/agenda'
                        ); ?>"
                        class="psicologo-quick-link"
                    >
                        <div>
                            <i class="bi bi-calendar-week"></i>
                        </div>

                        <span>Consultar mi agenda</span>

                        <i class="bi bi-chevron-right"></i>
                    </a>

                    <a
                        href="<?= Helper::baseUrl(
                            'psicologo/pacientes'
                        ); ?>"
                        class="psicologo-quick-link"
                    >
                        <div>
                            <i class="bi bi-people"></i>
                        </div>

                        <span>Ver mis pacientes</span>

                        <i class="bi bi-chevron-right"></i>
                    </a>

                    <a
                        href="<?= Helper::baseUrl(
                            'psicologo/disponibilidad'
                        ); ?>"
                        class="psicologo-quick-link"
                    >
                        <div>
                            <i class="bi bi-clock-history"></i>
                        </div>

                        <span>Configurar disponibilidad</span>

                        <i class="bi bi-chevron-right"></i>
                    </a>

                    <a
                        href="<?= Helper::baseUrl(
                            'psicologo/configuracion'
                        ); ?>"
                        class="psicologo-quick-link"
                    >
                        <div>
                            <i class="bi bi-person-gear"></i>
                        </div>

                        <span>Editar mi perfil</span>

                        <i class="bi bi-chevron-right"></i>
                    </a>

                </div>

            </div>

        </div>

    </div>

</section>