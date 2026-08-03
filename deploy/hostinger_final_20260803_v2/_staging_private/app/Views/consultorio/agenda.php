<?php

use App\Helpers\Helper;

$metricas = $metricas ?? [];
$conteoPorEspecialista = $conteoPorEspecialista ?? [];

?>

<section class="consultorio-agenda">

    <div class="consultorio-page-header agenda-page-header">

        <div>

            <span class="consultorio-page-eyebrow">
                Organización de sesiones
            </span>

            <h1>Calendario general</h1>

            <p>
                Consulta la ocupación de los especialistas sin
                exponer datos personales de los pacientes.
            </p>

        </div>

        <form
            id="formFiltrosAgenda"
            class="agenda-filtros"
        >

            <div class="agenda-filtro">

                <label for="psicologo">
                    Especialista
                </label>

                <select
                    name="psicologo"
                    id="psicologo"
                    class="form-select"
                >

                    <option value="">
                        Todos los especialistas
                    </option>

                    <?php foreach ($psicologos as $psicologo): ?>

                        <option
                            value="<?= htmlspecialchars(
                                $psicologo['ClvPsi']
                            ); ?>"
                        >
                            <?= htmlspecialchars(
                                $psicologo['NombrePsicologo']
                            ); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="agenda-filtro">

                <label for="estado">
                    Estado
                </label>

                <select
                    name="estado"
                    id="estado"
                    class="form-select"
                >

                    <option value="">
                        Todos los estados
                    </option>

                    <option value="PROGRAMADA">
                        Programada
                    </option>

                    <option value="ASISTIDA">
                        Asistida
                    </option>

                    <option value="CANCELADA">
                        Cancelada
                    </option>

                    <option value="INASISTENCIA">
                        Inasistencia
                    </option>

                </select>

            </div>

            <button
                type="submit"
                class="btn agenda-filter-button"
            >
                <i class="bi bi-funnel"></i>
                Filtrar
            </button>

            <button
                type="button"
                class="btn agenda-clear-button"
                id="limpiarFiltrosAgenda"
            >
                Limpiar
            </button>

        </form>

    </div>

    <div class="consultorio-dashboard-panel agenda-metrics-panel">

        <span class="consultorio-section-label">
            Indicadores operativos
        </span>

        <div class="row g-3 mt-1">

            <div class="col-6 col-lg-3">

                <article class="consultorio-stat-card">

                    <div class="consultorio-stat-icon">
                        <i class="bi bi-calendar2-check"></i>
                    </div>

                    <div>
                        <span>Programadas</span>
                        <strong>
                            <?= (int) ($metricas['programadas'] ?? 0); ?>
                        </strong>
                    </div>

                </article>

            </div>

            <div class="col-6 col-lg-3">

                <article class="consultorio-stat-card">

                    <div class="consultorio-stat-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>

                    <div>
                        <span>Asistidas</span>
                        <strong>
                            <?= (int) ($metricas['asistidas'] ?? 0); ?>
                        </strong>
                    </div>

                </article>

            </div>

            <div class="col-6 col-lg-3">

                <article class="consultorio-stat-card">

                    <div class="consultorio-stat-icon">
                        <i class="bi bi-x-circle"></i>
                    </div>

                    <div>
                        <span>Canceladas</span>
                        <strong>
                            <?= (int) ($metricas['canceladas'] ?? 0); ?>
                        </strong>
                    </div>

                </article>

            </div>

            <div class="col-6 col-lg-3">

                <article class="consultorio-stat-card">

                    <div class="consultorio-stat-icon">
                        <i class="bi bi-person-x"></i>
                    </div>

                    <div>
                        <span>Inasistencias</span>
                        <strong>
                            <?= (int) ($metricas['inasistencias'] ?? 0); ?>
                        </strong>
                    </div>

                </article>

            </div>

        </div>

        <?php if (!empty($conteoPorEspecialista)): ?>

            <div class="agenda-specialist-summary mt-4">

                <span class="consultorio-section-label">
                    Citas por especialista
                </span>

                <div class="table-responsive mt-2">

                    <table class="table agenda-summary-table mb-0">

                        <thead>
                            <tr>
                                <th>Especialista</th>
                                <th>Total</th>
                                <th>Programadas</th>
                                <th>Asistidas</th>
                                <th>Canceladas</th>
                                <th>Inasistencias</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($conteoPorEspecialista as $fila): ?>

                                <tr>
                                    <td>
                                        <?= htmlspecialchars(
                                            $fila['NombrePsicologo']
                                        ); ?>
                                    </td>
                                    <td>
                                        <?= (int) $fila['TotalCitas']; ?>
                                    </td>
                                    <td>
                                        <?= (int) $fila['Programadas']; ?>
                                    </td>
                                    <td>
                                        <?= (int) $fila['Asistidas']; ?>
                                    </td>
                                    <td>
                                        <?= (int) $fila['Canceladas']; ?>
                                    </td>
                                    <td>
                                        <?= (int) $fila['Inasistencias']; ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        <?php endif; ?>

    </div>

    <div class="agenda-legend">

        <span class="agenda-legend-title">
            Estados:
        </span>

        <span class="agenda-legend-item">
            <i class="agenda-dot programada"></i>
            Programada
        </span>

        <span class="agenda-legend-item">
            <i class="agenda-dot asistida"></i>
            Asistida
        </span>

        <span class="agenda-legend-item">
            <i class="agenda-dot cancelada"></i>
            Cancelada
        </span>

        <span class="agenda-legend-item">
            <i class="agenda-dot inasistencia"></i>
            Inasistencia
        </span>

    </div>

    <div class="consultorio-dashboard-panel">

        <div id="calendarioConsultorio"></div>

    </div>

</section>

<div
    class="modal fade"
    id="modalDetalleCita"
    tabindex="-1"
    aria-labelledby="modalDetalleCitaLabel"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content agenda-modal">

            <div class="modal-header">

                <div>

                    <span class="consultorio-page-eyebrow">
                        Información de la sesión
                    </span>

                    <h2
                        class="modal-title fs-5"
                        id="modalDetalleCitaLabel"
                    >
                        Detalles de la cita
                    </h2>

                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Cerrar"
                ></button>

            </div>

            <div class="modal-body">

                <p class="agenda-privacy-note mb-3">
                    <i class="bi bi-shield-lock me-1"></i>
                    Los datos del paciente se mantienen privados.
                </p>

                <dl class="agenda-details">

                    <div>
                        <dt>Especialista</dt>
                        <dd id="detallePsicologo">—</dd>
                    </div>

                    <div>
                        <dt>Especialidad</dt>
                        <dd id="detalleEspecialidad">—</dd>
                    </div>

                    <div>
                        <dt>Servicio</dt>
                        <dd id="detalleServicio">—</dd>
                    </div>

                    <div>
                        <dt>Consultorio</dt>
                        <dd id="detalleConsultorio">—</dd>
                    </div>

                    <div>
                        <dt>Fecha</dt>
                        <dd id="detalleFecha">—</dd>
                    </div>

                    <div>
                        <dt>Horario</dt>
                        <dd id="detalleHorario">—</dd>
                    </div>

                    <div>
                        <dt>Duración</dt>
                        <dd id="detalleDuracion">—</dd>
                    </div>

                    <div>
                        <dt>Costo programado</dt>
                        <dd id="detalleCostoProgramado">—</dd>
                    </div>

                    <div>
                        <dt>Estado</dt>

                        <dd>
                            <span
                                id="detalleEstado"
                                class="agenda-status"
                            >
                                —
                            </span>
                        </dd>
                    </div>

                </dl>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn agenda-filter-button"
                    data-bs-dismiss="modal"
                >
                    Cerrar
                </button>

            </div>

        </div>

    </div>

</div>

<script>
    window.consultorioAgenda = {
        eventosUrl:
            '<?= Helper::baseUrl(
                'consultorio/agenda/eventos'
            ); ?>'
    };
</script>
