<?php

use App\Core\Session;
use App\Helpers\Helper;

$csrf = Session::csrfToken();

?>

<section class="psicologo-agenda-page">

    <?php if (!empty($_SESSION['success'])): ?>

        <div class="alert alert-success">

            <?= htmlspecialchars($_SESSION['success']); ?>

        </div>

        <?php unset($_SESSION['success']); ?>

    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>

        <div class="alert alert-danger">

            <?= htmlspecialchars($_SESSION['error']); ?>

        </div>

        <?php unset($_SESSION['error']); ?>

    <?php endif; ?>

    <div class="psicologo-agenda-header">

        <div class="psicologo-agenda-header__text">

            <h1>Mi agenda</h1>

            <p>
                Consulta y administra tus próximas sesiones.
            </p>

        </div>

        <button
            type="button"
            class="psicologo-agenda-add-btn"
            id="btnNuevaCitaAgenda"
            title="Nueva cita"
            aria-label="Nueva cita"
            <?= empty($pacientes) || empty($servicios)
                ? 'disabled'
                : ''; ?>
        >
            <i class="bi bi-plus-lg"></i>
        </button>

    </div>

    <form
        id="formFiltrosAgendaPsi"
        class="psicologo-agenda-filters"
    >

        <div class="psicologo-agenda-filter">

            <label for="estadoAgendaPsi">
                Estado
            </label>

            <select
                name="estado"
                id="estadoAgendaPsi"
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
            class="btn psicologo-agenda-filter-btn"
        >
            <i class="bi bi-funnel"></i>
            Filtrar
        </button>

        <button
            type="button"
            class="btn psicologo-agenda-clear-btn"
            id="limpiarFiltrosAgendaPsi"
        >
            Limpiar
        </button>

    </form>

    <div class="psicologo-agenda-legend">

        <span>Estados:</span>

        <span class="psicologo-agenda-legend-item">
            <i class="agenda-dot programada"></i>
            Programada
        </span>

        <span class="psicologo-agenda-legend-item">
            <i class="agenda-dot asistida"></i>
            Asistida
        </span>

        <span class="psicologo-agenda-legend-item">
            <i class="agenda-dot cancelada"></i>
            Cancelada
        </span>

        <span class="psicologo-agenda-legend-item">
            <i class="agenda-dot inasistencia"></i>
            Inasistencia
        </span>

    </div>

    <div class="psicologo-agenda-panel">

        <div id="calendarioPsicologo"></div>

    </div>

</section>

<div
    class="modal fade"
    id="modalDetalleCitaPsi"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content psicologo-agenda-modal">

            <div class="modal-header">

                <h2 class="modal-title fs-5">
                    Detalle de la sesión
                </h2>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>

            <div class="modal-body">

                <dl class="psicologo-agenda-details">

                    <div>
                        <dt>Paciente</dt>
                        <dd id="detallePacientePsi">—</dd>
                    </div>

                    <div>
                        <dt>Servicio</dt>
                        <dd id="detalleServicioPsi">—</dd>
                    </div>

                    <div>
                        <dt>Fecha</dt>
                        <dd id="detalleFechaPsi">—</dd>
                    </div>

                    <div>
                        <dt>Horario</dt>
                        <dd id="detalleHorarioPsi">—</dd>
                    </div>

                    <div>
                        <dt>Duración</dt>
                        <dd id="detalleDuracionPsi">—</dd>
                    </div>

                    <div>
                        <dt>Tarifa aplicada</dt>
                        <dd id="detalleTarifaPsi">—</dd>
                    </div>

                    <div>
                        <dt>Estado</dt>
                        <dd>
                            <span
                                id="detalleEstadoPsi"
                                class="agenda-status"
                            >
                                —
                            </span>
                        </dd>
                    </div>

                </dl>

                <div
                    id="detalleAsistenciaMensajePsi"
                    class="psi-asistencia-aviso d-none"
                ></div>

                <div
                    id="detalleAccionesAsistenciaPsi"
                    class="psi-asistencia-acciones d-none"
                >
                    <button
                        type="button"
                        class="btn psicologo-agenda-primary-btn"
                        id="btnAbrirAsistidaPsi"
                    >
                        Registrar asistencia
                    </button>
                    <button
                        type="button"
                        class="btn psicologo-agenda-filter-btn"
                        id="btnAbrirInasistenciaPsi"
                    >
                        Registrar inasistencia
                    </button>
                    <a
                        href="#"
                        id="enlaceVerPacienteAsistenciaPsi"
                        class="btn psicologo-agenda-filter-btn d-none"
                    >
                        Ver paciente
                    </a>
                    <a
                        href="#"
                        id="enlaceCompletarDatosAsistenciaPsi"
                        class="btn psicologo-agenda-filter-btn d-none"
                    >
                        Completar datos faltantes
                    </a>
                </div>

                <div
                    id="detalleAccionClinicaPsi"
                    class="psi-asistencia-clinica d-none"
                >
                    <a
                        href="#"
                        id="enlaceAccionClinicaPsi"
                        class="btn psicologo-agenda-primary-btn"
                    >
                        Acción clínica
                    </a>
                    <p
                        id="textoAccionClinicaPsi"
                        class="psi-asistencia-clinica__texto d-none"
                    ></p>
                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn psicologo-agenda-filter-btn"
                    data-bs-dismiss="modal"
                >
                    Cerrar
                </button>

            </div>

        </div>

    </div>

</div>

<div
    class="modal fade"
    id="modalConfirmarAsistidaPsi"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content psicologo-agenda-modal">
            <div class="modal-header">
                <h2 class="modal-title fs-5">Confirmar asistencia</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Confirmas que el paciente asistió a esta sesión?</p>
                <dl class="psicologo-agenda-details">
                    <div>
                        <dt>Paciente</dt>
                        <dd id="confirmAsistidaPaciente">—</dd>
                    </div>
                    <div>
                        <dt>Servicio</dt>
                        <dd id="confirmAsistidaServicio">—</dd>
                    </div>
                    <div>
                        <dt>Fecha</dt>
                        <dd id="confirmAsistidaFecha">—</dd>
                    </div>
                    <div>
                        <dt>Horario</dt>
                        <dd id="confirmAsistidaHorario">—</dd>
                    </div>
                </dl>
                <div class="psi-asistencia-aviso">
                    Después de confirmar, podrás registrar la historia clínica
                    inicial o el seguimiento de la sesión.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn psicologo-agenda-filter-btn" data-bs-dismiss="modal">
                    Volver
                </button>
                <button type="button" class="btn psicologo-agenda-primary-btn" id="btnConfirmarAsistidaPsi">
                    Sí, marcar como asistida
                </button>
            </div>
        </div>
    </div>
</div>

<div
    class="modal fade"
    id="modalConfirmarInasistenciaPsi"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content psicologo-agenda-modal">
            <div class="modal-header">
                <h2 class="modal-title fs-5">Registrar inasistencia</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Confirmas que el paciente no asistió a esta cita?</p>
                <dl class="psicologo-agenda-details">
                    <div>
                        <dt>Paciente</dt>
                        <dd id="confirmInasistenciaPaciente">—</dd>
                    </div>
                    <div>
                        <dt>Servicio</dt>
                        <dd id="confirmInasistenciaServicio">—</dd>
                    </div>
                    <div>
                        <dt>Fecha</dt>
                        <dd id="confirmInasistenciaFecha">—</dd>
                    </div>
                    <div>
                        <dt>Horario</dt>
                        <dd id="confirmInasistenciaHorario">—</dd>
                    </div>
                </dl>
                <div class="psi-asistencia-aviso psi-asistencia-aviso--warn">
                    Una cita registrada como inasistencia no permitirá crear
                    historia clínica ni seguimiento terapéutico.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn psicologo-agenda-filter-btn" data-bs-dismiss="modal">
                    Volver
                </button>
                <button type="button" class="btn psicologo-agenda-primary-btn" id="btnConfirmarInasistenciaPsi">
                    Sí, registrar inasistencia
                </button>
            </div>
        </div>
    </div>
</div>

<div
    class="modal fade"
    id="modalNuevaCitaPsi"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content psicologo-agenda-modal">

            <form
                method="POST"
                action="<?= Helper::baseUrl(
                    'psicologo/agenda/guardar-cita'
                ); ?>"
            >

                <div class="modal-header">

                    <h2 class="modal-title fs-5">
                        Nueva cita
                    </h2>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>

                <div class="modal-body">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($csrf); ?>"
                    >

                    <div class="mb-3">

                        <label
                            for="pacienteNuevaCita"
                            class="form-label"
                        >
                            Paciente
                        </label>

                        <select
                            name="paciente"
                            id="pacienteNuevaCita"
                            class="form-select"
                            required
                        >

                            <option value="">
                                Selecciona un paciente
                            </option>

                            <?php foreach ($pacientes as $paciente): ?>

                                <option
                                    value="<?= htmlspecialchars(
                                        $paciente['ClvPac']
                                    ); ?>"
                                >
                                    <?= htmlspecialchars(
                                        $paciente['NombrePaciente']
                                    ); ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <div class="form-text mt-2">
                            ¿Es un paciente nuevo?
                            <a href="<?= Helper::baseUrl(
                                'psicologo/pacientes/registrar'
                            ); ?>">
                                Registrarlo
                            </a>
                        </div>

                    </div>

                    <div class="mb-3">

                        <label
                            for="servicioNuevaCita"
                            class="form-label"
                        >
                            Servicio
                        </label>

                        <select
                            name="servicio"
                            id="servicioNuevaCita"
                            class="form-select"
                            required
                        >

                            <option value="">
                                Selecciona un servicio
                            </option>

                            <?php foreach ($servicios as $servicio): ?>

                                <option
                                    value="<?= htmlspecialchars(
                                        $servicio['ClvServ']
                                    ); ?>"
                                    data-nombre="<?= htmlspecialchars(
                                        $servicio['NombreServicio'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>"
                                    data-duracion="<?= (int) $servicio['DuracionMinutos']; ?>"
                                    data-precio="<?= htmlspecialchars(
                                        (string) $servicio['PrecioServicio'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>"
                                >
                                    <?= htmlspecialchars(
                                        $servicio['NombreServicio']
                                    ); ?>
                                    (<?= (int) $servicio['DuracionMinutos']; ?>
                                    min)
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="mb-3">

                        <label
                            for="fechaNuevaCita"
                            class="form-label"
                        >
                            Fecha de la cita
                        </label>

                        <input
                            type="date"
                            name="fecha"
                            id="fechaNuevaCita"
                            class="form-control"
                            min="<?= htmlspecialchars(date('Y-m-d')); ?>"
                            required
                        >

                        <p
                            class="psi-nueva-cita-fecha-legible"
                            id="fechaNuevaCitaLegible"
                            aria-live="polite"
                        ></p>

                        <p
                            class="psi-nueva-cita-ayuda"
                            id="fechaNuevaCitaAyuda"
                        >
                            Selecciona una fecha para consultar los horarios disponibles.
                        </p>

                    </div>

                    <div
                        class="mb-3"
                        id="bloqueHorariosNuevaCita"
                    >

                        <label class="form-label">
                            Horarios disponibles
                        </label>

                        <div
                            class="psi-agenda-slots"
                            id="horariosNuevaCita"
                            role="group"
                            aria-label="Horarios disponibles"
                            tabindex="-1"
                        ></div>

                        <p
                            class="psi-nueva-cita-ayuda d-none"
                            id="horariosNuevaCitaEstado"
                        ></p>

                    </div>

                    <input
                        type="hidden"
                        name="hora"
                        id="horaNuevaCita"
                        value=""
                    >

                    <div
                        class="psi-nueva-cita-resumen d-none"
                        id="nuevaCitaResumen"
                    >

                        <h3 class="psi-nueva-cita-resumen__title">
                            Resumen
                        </h3>

                        <dl class="psi-nueva-cita-resumen__list">

                            <div>
                                <dt>Paciente</dt>
                                <dd id="resumenPaciente">—</dd>
                            </div>

                            <div>
                                <dt>Servicio</dt>
                                <dd id="resumenServicio">—</dd>
                            </div>

                            <div>
                                <dt>Fecha</dt>
                                <dd id="resumenFecha">—</dd>
                            </div>

                            <div>
                                <dt>Horario</dt>
                                <dd id="resumenHorario">—</dd>
                            </div>

                            <div>
                                <dt>Duración</dt>
                                <dd id="resumenDuracion">—</dd>
                            </div>

                            <div>
                                <dt>Costo</dt>
                                <dd id="resumenCosto">—</dd>
                            </div>

                            <div>
                                <dt>Atención</dt>
                                <dd>Presencial</dd>
                            </div>

                            <div>
                                <dt>Consultorio</dt>
                                <dd id="resumenConsultorio">—</dd>
                            </div>

                        </dl>

                    </div>

                    <?php if (empty($pacientes)): ?>

                        <div class="alert alert-warning mt-3 mb-0">

                            Aún no tienes pacientes con citas registradas.

                        </div>

                    <?php endif; ?>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        class="btn psicologo-agenda-filter-btn"
                        id="btnGuardarNuevaCita"
                        disabled
                        <?= empty($pacientes) || empty($servicios)
                            ? 'disabled'
                            : ''; ?>
                    >
                        <span
                            class="psi-btn-guardar-texto"
                            id="btnGuardarNuevaCitaTexto"
                        >
                            Guardar cita
                        </span>
                        <span
                            class="psi-btn-guardar-loading d-none"
                            id="btnGuardarNuevaCitaLoading"
                            aria-hidden="true"
                        >
                            <span
                                class="spinner-border spinner-border-sm me-2"
                                role="status"
                            ></span>
                            Guardando…
                        </span>
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<div
    class="toast-container psi-agenda-toast-container position-fixed top-0 end-0 p-3"
    id="psiAgendaToastContainer"
    aria-live="polite"
    aria-atomic="true"
></div>

<script>
    window.psicologoAgenda = {
        eventosUrl:
            '<?= Helper::baseUrl(
                'psicologo/agenda/eventos'
            ); ?>',
        pendientesUrl:
            '<?= Helper::baseUrl(
                'psicologo/agenda/pendientes-operativos'
            ); ?>',
        loginUrl:
            '<?= Helper::baseUrl('login'); ?>',
        horariosUrl:
            '<?= Helper::baseUrl(
                'psicologo/agenda/horarios-disponibles'
            ); ?>',
        guardarUrl:
            '<?= Helper::baseUrl(
                'psicologo/agenda/guardar-cita'
            ); ?>',
        asistenciaUrl:
            '<?= Helper::baseUrl(
                'psicologo/agenda/registrar-asistencia'
            ); ?>',
        csrfToken:
            <?= json_encode(
                \App\Core\Session::csrfToken(),
                JSON_UNESCAPED_UNICODE
            ); ?>,
        fechaMinima: '<?= date('Y-m-d'); ?>',
        fechaActual: '<?= date('Y-m-d'); ?>',
        horaActual: '<?= date('H:i:s'); ?>',
        zonaHoraria: 'America/Mexico_City',
        refrescoPendientesMs: 60000,
        consultorioNombre:
            <?= json_encode(
                $consultorio['NombreCons'] ?? 'Consultorio',
                JSON_UNESCAPED_UNICODE
            ); ?>,
        pacientePreseleccionado:
            <?= json_encode(
                $pacientePreseleccionado ?? '',
                JSON_UNESCAPED_UNICODE
            ); ?>,
        errorPacienteAgenda:
            <?= json_encode(
                $errorPacienteAgenda ?? '',
                JSON_UNESCAPED_UNICODE
            ); ?>
    };
</script>
