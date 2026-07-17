<?php

use App\Helpers\Helper;

?>

<section class="consultorio-agenda">

    <div class="consultorio-page-header agenda-page-header">

        <div>

            <span class="consultorio-page-eyebrow">
                Organización de sesiones
            </span>

            <h1>Calendario general</h1>

            <p>
                Consulta las citas programadas de todos los
                especialistas del consultorio.
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

                <dl class="agenda-details">

                    <div>
                        <dt>Paciente</dt>
                        <dd id="detallePaciente">—</dd>
                    </div>

                    <div>
                        <dt>Especialista</dt>
                        <dd id="detallePsicologo">—</dd>
                    </div>

                    <div>
                        <dt>Servicio</dt>
                        <dd id="detalleServicio">—</dd>
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

                    <div class="agenda-detail-full">

                        <dt>Notas</dt>

                        <dd id="detalleNotas">
                            Sin notas
                        </dd>

                    </div>

                    <div
                        class="agenda-detail-full d-none"
                        id="bloqueCanceladaPor"
                    >
                        <dt>Cancelada por</dt>

                        <dd id="detalleCanceladaPor">
                            —
                        </dd>
                    </div>

                    <div
                        class="agenda-detail-full d-none"
                        id="bloqueFechaCancelacion"
                    >
                        <dt>Fecha de cancelación</dt>

                        <dd id="detalleFechaCancelacion">
                            —
                        </dd>
                    </div>

                    <div
                        class="agenda-detail-full d-none"
                        id="bloqueMotivoCancelacion"
                    >
                        <dt>Motivo de cancelación</dt>

                        <dd id="detalleMotivoCancelacion">
                            —
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