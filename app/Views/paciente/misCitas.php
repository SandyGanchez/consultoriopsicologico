<?php

use App\Helpers\Helper;

?>

<div class="container-fluid">

    <!-- =========================================
         ENCABEZADO
    ========================================== -->

    <div class="card paciente-card-header shadow-sm mb-4">

        <div class="card-body">

            <h2>

                <i class="bi bi-calendar-check-fill me-2"></i>

                Mis citas

            </h2>

            <p class="mb-0">

                Aquí puedes consultar tus citas programadas,
                asistidas y demás estados registrados.

            </p>

        </div>

    </div>


    <!-- =========================================
         MENSAJE DE ÉXITO
    ========================================== -->

    <?php if (!empty($_SESSION['success'])): ?>

        <div class="alert alert-success">

            <i class="bi bi-check-circle-fill me-2"></i>

            <?= htmlspecialchars($_SESSION['success']); ?>

        </div>

        <?php unset($_SESSION['success']); ?>

    <?php endif; ?>


    <!-- =========================================
         MENSAJE DE ERROR
    ========================================== -->

    <?php if (!empty($_SESSION['error'])): ?>

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-triangle-fill me-2"></i>

            <?= htmlspecialchars($_SESSION['error']); ?>

        </div>

        <?php unset($_SESSION['error']); ?>

    <?php endif; ?>


    <!-- =========================================
         SIN CITAS
    ========================================== -->

    <?php if (empty($citas)): ?>

        <div class="card shadow-sm">

            <div class="card-body text-center py-5">

                <i class="bi bi-calendar-x display-1"></i>

                <h3 class="mt-3">

                    Aún no tienes citas registradas

                </h3>

                <p class="text-muted">

                    Cuando agendes una cita aparecerá aquí.

                </p>


                <a
                    href="<?= Helper::baseUrl('paciente/agendar'); ?>"
                    class="btn btn-success mt-2"
                >

                    <i class="bi bi-calendar-plus me-2"></i>

                    Agendar cita

                </a>

            </div>

        </div>


    <?php else: ?>


        <!-- =========================================
             LISTADO DE CITAS
        ========================================== -->

        <div class="row g-4">

            <?php foreach ($citas as $cita): ?>

                <div class="col-lg-6">

                    <div
                        class="card shadow-sm h-100 paciente-cita-card"
                    >


                        <!-- ENCABEZADO -->

                        <div
                            class="card-header paciente-card-title d-flex justify-content-between align-items-center"
                        >

                            <span>

                                <i
                                    class="bi bi-calendar-heart-fill me-2"
                                ></i>

                                <?= htmlspecialchars(
                                    $cita['FechaCita']
                                ); ?>

                            </span>


                            <?php

                            $badge = 'bg-secondary';

                            switch ($cita['EstadoCita']) {

                                case 'PROGRAMADA':

                                    $badge = 'bg-success';

                                    break;

                                case 'ASISTIDA':

                                    $badge = 'bg-primary';

                                    break;

                                case 'CANCELADA':

                                    $badge = 'bg-danger';

                                    break;

                                case 'INASISTENCIA':

                                    $badge = 'bg-warning text-dark';

                                    break;

                            }

                            ?>


                            <span class="badge <?= $badge; ?>">

                                <?= htmlspecialchars(
                                    $cita['EstadoCita']
                                ); ?>

                            </span>

                        </div>


                        <!-- CUERPO -->

                        <div class="card-body">


                            <!-- HORA -->

                            <div class="mb-3">

                                <small class="text-muted">

                                    <i class="bi bi-clock me-1"></i>

                                    Hora

                                </small>

                                <h5 class="mb-0">

                                    <?= htmlspecialchars(
                                        $cita['HraInicioCita']
                                    ); ?>

                                    -

                                    <?= htmlspecialchars(
                                        $cita['HraFinCita']
                                    ); ?>

                                </h5>

                            </div>


                            <!-- PSICÓLOGO -->

                            <div class="mb-3">

                                <small class="text-muted">

                                    <i class="bi bi-person-badge me-1"></i>

                                    Psicólogo

                                </small>

                                <h5 class="mb-0">

                                    <?= htmlspecialchars(
                                        $cita['NombrePsicologo']
                                    ); ?>

                                </h5>

                            </div>


                            <!-- SERVICIO -->

                            <div class="mb-3">

                                <small class="text-muted">

                                    <i class="bi bi-journal-medical me-1"></i>

                                    Servicio

                                </small>

                                <h5 class="mb-0">

                                    <?= htmlspecialchars(
                                        $cita['NombreServicio']
                                    ); ?>

                                </h5>

                            </div>


                            <!-- NOTAS -->

                            <?php if (!empty($cita['NotasCita'])): ?>

                                <div class="alert alert-light border mt-3">

                                    <strong>

                                        <i class="bi bi-chat-left-text me-1"></i>

                                        Notas

                                    </strong>

                                    <br>

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $cita['NotasCita']
                                        )
                                    ); ?>

                                </div>

                            <?php endif; ?>

                        </div>


                        <!-- PIE -->

                        <div class="card-footer bg-white border-0">

    <div class="d-grid gap-2">

        <a
            href="<?= Helper::baseUrl(
                'paciente/cita-detalle?cita=' .
                urlencode($cita['ClvCita'])
            ); ?>"
            class="btn btn-outline-success"
        >

            <i class="bi bi-eye-fill me-2"></i>

            Ver detalles

        </a>

        <?php if ($cita['EstadoCita'] === 'PROGRAMADA'): ?>

            <button
                type="button"
                class="btn btn-outline-danger"
                data-bs-toggle="modal"
                data-bs-target="#cancelarModal<?= htmlspecialchars($cita['ClvCita']); ?>"
            >

                <i class="bi bi-x-circle me-2"></i>

                Cancelar cita

            </button>

        <?php endif; ?>

    </div>

</div>

                    </div>

                </div>

                <?php if ($cita['EstadoCita'] === 'PROGRAMADA'): ?>

<div
    class="modal fade"
    id="cancelarModal<?= htmlspecialchars($cita['ClvCita']); ?>"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog">

        <div class="modal-content">

            <form
                method="POST"
                action="<?= Helper::baseUrl('paciente/cancelar-cita'); ?>"
            >

                <div class="modal-header">

                    <h5 class="modal-title">

                        Cancelar cita

                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>

                <div class="modal-body">

                    <input
                        type="hidden"
                        name="cita"
                        value="<?= htmlspecialchars($cita['ClvCita']); ?>"
                    >

                    <div class="mb-3">

                        <label class="form-label">

                            Motivo de cancelación

                        </label>

                        <textarea
                            name="motivo"
                            class="form-control"
                            rows="4"
                            maxlength="255"
                            required
                        ></textarea>

                    </div>

                    <div class="alert alert-warning mb-0">

                        <i class="bi bi-exclamation-triangle-fill me-2"></i>

                        Esta acción no se puede deshacer.

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >

                        Regresar

                    </button>

                    <button
                        type="submit"
                        class="btn btn-danger"
                    >

                        Cancelar cita

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<?php endif; ?>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>