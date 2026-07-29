<?php

use App\Helpers\Helper;

?>

<div class="container-fluid">

    <div class="card paciente-card-header shadow-sm mb-4">

        <div class="card-body">

            <h2>

                <i class="bi bi-clock-history me-2"></i>

                Historial clínico

            </h2>

            <p class="mb-0">

                Consulta el historial de tus citas realizadas,
                canceladas o registradas como inasistencia.

            </p>

        </div>

    </div>


    <?php if (empty($historial)): ?>

        <div class="card shadow-sm">

            <div class="card-body text-center py-5">

                <i class="bi bi-journal-x display-1"></i>

                <h4 class="mt-3">

                    Aún no existe historial.

                </h4>

                <p class="text-muted">

                    Cuando tengas citas finalizadas aparecerán aquí.

                </p>

            </div>

        </div>

    <?php else: ?>


        <div class="row g-4">

            <?php foreach ($historial as $cita): ?>

                <?php

                $badge='bg-secondary';

                switch($cita['EstadoCita']){

                    case 'ASISTIDA':
                        $badge='bg-primary';
                        break;

                    case 'CANCELADA':
                        $badge='bg-danger';
                        break;

                    case 'INASISTENCIA':
                        $badge='bg-warning text-dark';
                        break;

                }

                ?>

                <div class="col-lg-6">

                    <div class="card shadow-sm h-100">

                        <div class="card-header d-flex justify-content-between align-items-center">

                            <strong>

                                <?= htmlspecialchars($cita['FechaCita']); ?>

                            </strong>

                            <span class="badge <?= $badge; ?>">

                                <?= htmlspecialchars($cita['EstadoCita']); ?>

                            </span>

                        </div>

                        <div class="card-body">

                            <div class="mb-3">

                                <small class="text-muted">

                                    Hora

                                </small>

                                <h6>

                                    <?= htmlspecialchars($cita['HraInicioCita']); ?>

                                    -

                                    <?= htmlspecialchars($cita['HraFinCita']); ?>

                                </h6>

                            </div>


                            <div class="mb-3">

                                <small class="text-muted">

                                    Psicólogo

                                </small>

                                <h6>

                                    <?= htmlspecialchars($cita['NombrePsicologo']); ?>

                                </h6>

                            </div>


                            <div class="mb-3">

                                <small class="text-muted">

                                    Servicio

                                </small>

                                <h6>

                                    <?= htmlspecialchars($cita['NombreServicio']); ?>

                                </h6>

                            </div>


                            <?php if(!empty($cita['MotivoCancelacion'])): ?>

                                <div class="alert alert-danger">

                                    <strong>

                                        Motivo de cancelación

                                    </strong>

                                    <br>

                                    <?= nl2br(htmlspecialchars($cita['MotivoCancelacion'])); ?>

                                </div>

                            <?php endif; ?>


                            <?php if(!empty($cita['NotasCita'])): ?>

                                <div class="alert alert-light border">

                                    <strong>

                                        Notas

                                    </strong>

                                    <br>

                                    <?= nl2br(htmlspecialchars($cita['NotasCita'])); ?>

                                </div>

                            <?php endif; ?>

                        </div>

                        <div class="card-footer bg-white border-0">

                            <a

                                href="<?= Helper::baseUrl('paciente/cita-detalle?cita=' . urlencode($cita['ClvCita'])); ?>"

                                class="btn btn-outline-success w-100"

                            >

                                <i class="bi bi-eye-fill me-2"></i>

                                Ver detalles

                            </a>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>