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
$mostrarBienvenida = in_array(
    $estadoCodigo,
    ['BORRADOR', 'PENDIENTE_ACTIVACION'],
    true
);
$puedePublicar = !empty($progreso['listo'])
    && in_array($estadoCodigo, ['BORRADOR', 'OCULTO'], true);
$cuentaActiva = (int) ($usuario['EstadoUsu'] ?? 0) === 1;

?>

<section class="consultorio-dashboard">

    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars((string) $success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars((string) $error); ?>
            <?php if (!empty($pendientesFlash)): ?>
                <ul class="mb-0 mt-2">
                    <?php foreach ($pendientesFlash as $pendiente): ?>
                        <li>
                            <?= htmlspecialchars(
                                (string) ($pendiente['etiqueta'] ?? '')
                            ); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="consultorio-page-header">

        <div>

            <span class="consultorio-page-eyebrow">
                Panel general
            </span>

            <h1>
                <?= $mostrarBienvenida
                    ? 'Bienvenido a PsicoMatch'
                    : 'Consultorio'; ?>
            </h1>

            <p>
                <?= $mostrarBienvenida
                    ? 'Completa la configuración de tu consultorio antes de publicar tu página.'
                    : 'Consulta los indicadores y próximas sesiones del establecimiento.'; ?>
            </p>

        </div>

    </div>

    <div class="consultorio-dashboard-panel">

        <span class="consultorio-section-label">
            Página pública
        </span>

        <div class="row g-4 mt-1 align-items-start">

            <div class="col-12 col-lg-5">

                <article class="consultorio-stat-card h-100">

                    <div class="consultorio-stat-icon">
                        <i class="bi bi-globe2"></i>
                    </div>

                    <div class="w-100">

                        <span>Estado de la cuenta</span>
                        <strong class="d-block mb-2">
                            <?= $cuentaActiva ? 'Activa' : 'Pendiente de activación'; ?>
                        </strong>

                        <span>Página pública</span>
                        <strong class="d-block mb-3">
                            <?= htmlspecialchars($etiquetaPagina); ?>
                        </strong>

                        <span>Configuración</span>
                        <strong class="d-block">
                            <?= (int) ($progreso['porcentaje'] ?? 0); ?>%
                        </strong>

                        <div
                            class="progress mt-2"
                            role="progressbar"
                            aria-valuenow="<?= (int) ($progreso['porcentaje'] ?? 0); ?>"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            style="height:8px;"
                        >
                            <div
                                class="progress-bar"
                                style="width:<?= (int) ($progreso['porcentaje'] ?? 0); ?>%;background:#99CDD8;"
                            ></div>
                        </div>

                    </div>

                </article>

            </div>

            <div class="col-12 col-lg-7">

                <div class="row g-3">

                    <div class="col-md-6">
                        <h3 class="h6 text-muted text-uppercase mb-2">
                            Pasos completados
                        </h3>
                        <?php if (empty($progreso['completados'])): ?>
                            <p class="text-muted small mb-0">Ninguno todavía.</p>
                        <?php else: ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($progreso['completados'] as $item): ?>
                                    <li class="mb-1">
                                        <i class="bi bi-check-circle-fill text-success me-1"></i>
                                        <?= htmlspecialchars(
                                            (string) ($item['etiqueta'] ?? '')
                                        ); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <h3 class="h6 text-muted text-uppercase mb-2">
                            Pasos pendientes
                        </h3>
                        <?php if (empty($progreso['pendientes'])): ?>
                            <p class="text-success small mb-0">
                                Configuración mínima completa.
                            </p>
                        <?php else: ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($progreso['pendientes'] as $item): ?>
                                    <li class="mb-1">
                                        <i class="bi bi-circle me-1 text-muted"></i>
                                        <?= htmlspecialchars(
                                            (string) ($item['etiqueta'] ?? '')
                                        ); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">

                    <?php if ($estadoCodigo === 'PUBLICADO'): ?>

                        <a
                            href="<?= Helper::baseUrl(
                                'consultorios/'
                                . rawurlencode(
                                    (string) ($consultorio['ClvCons'] ?? '')
                                )
                            ); ?>"
                            class="btn btn-primary"
                            target="_blank"
                            rel="noopener"
                        >
                            Ver página pública
                        </a>

                        <a
                            href="<?= Helper::baseUrl('consultorio/configuracion'); ?>"
                            class="btn btn-outline-secondary"
                        >
                            Editar información
                        </a>

                        <form
                            method="POST"
                            action="<?= Helper::baseUrl('consultorio/publicacion/ocultar'); ?>"
                            class="d-inline"
                        >
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf); ?>">
                            <button
                                type="submit"
                                class="btn btn-outline-warning"
                                onclick="this.disabled=true;this.form.submit();"
                            >
                                Ocultar temporalmente
                            </button>
                        </form>

                    <?php elseif ($estadoCodigo === 'OCULTO'): ?>

                        <a
                            href="<?= Helper::baseUrl('consultorio/vista-previa'); ?>"
                            class="btn btn-outline-primary"
                            target="_blank"
                            rel="noopener"
                        >
                            Vista previa
                        </a>

                        <a
                            href="<?= Helper::baseUrl('consultorio/configuracion'); ?>"
                            class="btn btn-outline-secondary"
                        >
                            Editar información
                        </a>

                        <form
                            method="POST"
                            action="<?= Helper::baseUrl('consultorio/publicacion/publicar'); ?>"
                            class="d-inline"
                        >
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf); ?>">
                            <button
                                type="submit"
                                class="btn btn-primary"
                                <?= $puedePublicar ? '' : 'disabled'; ?>
                                <?= $puedePublicar
                                    ? 'onclick="this.disabled=true;this.form.submit();"'
                                    : ''; ?>
                            >
                                Volver a publicar
                            </button>
                        </form>

                    <?php else: ?>

                        <a
                            href="<?= Helper::baseUrl('consultorio/configuracion'); ?>"
                            class="btn btn-primary"
                        >
                            Completar configuración
                        </a>

                        <a
                            href="<?= Helper::baseUrl('consultorio/vista-previa'); ?>"
                            class="btn btn-outline-primary"
                            target="_blank"
                            rel="noopener"
                        >
                            Vista previa
                        </a>

                        <form
                            method="POST"
                            action="<?= Helper::baseUrl('consultorio/publicacion/publicar'); ?>"
                            class="d-inline"
                        >
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf); ?>">
                            <button
                                type="submit"
                                class="btn btn-success"
                                <?= $puedePublicar ? '' : 'disabled'; ?>
                                title="<?= $puedePublicar
                                    ? 'Publicar página pública'
                                    : 'Completa los requisitos mínimos para publicar'; ?>"
                                <?= $puedePublicar
                                    ? 'onclick="this.disabled=true;this.form.submit();"'
                                    : ''; ?>
                            >
                                Publicar
                            </button>
                        </form>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

    <div class="consultorio-dashboard-panel mt-4">

        <span class="consultorio-section-label">
            Indicadores
        </span>

        <div class="row g-4 mt-1">

            <div class="col-12 col-md-4">

                <article class="consultorio-stat-card">

                    <div class="consultorio-stat-icon">
                        <i class="bi bi-calendar2-check"></i>
                    </div>

                    <div>

                        <span>Citas de hoy</span>

                        <strong>
                            <?= (int) $citasHoy; ?>
                        </strong>

                    </div>

                </article>

            </div>

            <div class="col-12 col-md-4">

                <article class="consultorio-stat-card">

                    <div class="consultorio-stat-icon">
                        <i class="bi bi-people"></i>
                    </div>

                    <div>

                        <span>Pacientes registrados</span>

                        <strong>
                            <?= (int) $totalPacientes; ?>
                        </strong>

                    </div>

                </article>

            </div>

            <div class="col-12 col-md-4">

                <article class="consultorio-stat-card">

                    <div class="consultorio-stat-icon">
                        <i class="bi bi-calendar-week"></i>
                    </div>

                    <div>

                        <span>Citas de la semana</span>

                        <strong>
                            <?= (int) $citasSemana; ?>
                        </strong>

                    </div>

                </article>

            </div>

        </div>

    </div>

    <div class="consultorio-dashboard-panel mt-4">

        <div class="d-flex justify-content-between align-items-center mb-3">

            <span class="consultorio-section-label">
                Próximas sesiones
            </span>

            <a
                href="<?= Helper::baseUrl('consultorio/agenda'); ?>"
                class="consultorio-link"
            >
                Ver agenda
                <i class="bi bi-arrow-right"></i>
            </a>

        </div>

        <div class="table-responsive">

            <table class="table consultorio-table align-middle">

                <thead>

                    <tr>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Paciente</th>
                        <th>Especialista</th>
                        <th>Servicio</th>
                        <th>Notas</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (!empty($proximasCitas)): ?>

                        <?php foreach ($proximasCitas as $cita): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars(
                                        $cita['FechaCita']
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $cita['HraInicioCita']
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $cita['NombrePaciente']
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $cita['NombrePsicologo']
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $cita['NombreServicio']
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $cita['NotasCita'] ?? '—'
                                    ); ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="6"
                                class="text-center py-5 text-muted"
                            >

                                <i class="bi bi-calendar-x fs-3 d-block mb-2"></i>

                                No hay próximas sesiones registradas.

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</section>
