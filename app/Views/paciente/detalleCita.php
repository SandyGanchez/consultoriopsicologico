<?php

use App\Helpers\Helper;

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

<div class="container-fluid">

    <div class="card shadow-sm">

        <div class="card-header paciente-card-title d-flex justify-content-between">

            <h3 class="mb-0">

                <i class="bi bi-calendar-event me-2"></i>

                Detalle de la cita

            </h3>

            <span class="badge <?= $badge; ?>">

                <?= htmlspecialchars($cita['EstadoCita']); ?>

            </span>

        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-6 mb-4">

                    <h6 class="text-muted">Fecha</h6>

                    <p><?= htmlspecialchars($cita['FechaCita']); ?></p>

                </div>

                <div class="col-md-6 mb-4">

                    <h6 class="text-muted">Horario</h6>

                    <p>

                        <?= htmlspecialchars($cita['HraInicioCita']); ?>

                        -

                        <?= htmlspecialchars($cita['HraFinCita']); ?>

                    </p>

                </div>

                <div class="col-md-6 mb-4">

                    <h6 class="text-muted">Psicólogo</h6>

                    <p><?= htmlspecialchars($cita['NombrePsicologo']); ?></p>

                </div>

                <div class="col-md-6 mb-4">

                    <h6 class="text-muted">Servicio</h6>

                    <p><?= htmlspecialchars($cita['NombreServicio']); ?></p>

                </div>

                <div class="col-md-6 mb-4">

                    <h6 class="text-muted">Duración</h6>

                    <p>

                        <?= htmlspecialchars($cita['DuracionAplicadaMin']); ?>

                        minutos

                    </p>

                </div>

                <div class="col-md-6 mb-4">

                    <h6 class="text-muted">Costo</h6>

                    <p>

                        $

                        <?= number_format(
                            $cita['CostoAplicado'],
                            2
                        ); ?>

                    </p>

                </div>

                <div class="col-md-6 mb-4">

                    <h6 class="text-muted">

                        Consultorio

                    </h6>

                    <p>

                        <?= htmlspecialchars(
                            $cita['NombreCons']
                        ); ?>

                    </p>

                </div>

                <?php if (!empty($cita['NotasCita'])): ?>

                    <div class="col-12 mb-4">

                        <h6 class="text-muted">

                            Notas

                        </h6>

                        <div class="alert alert-light">

                            <?= nl2br(htmlspecialchars($cita['NotasCita'])); ?>

                        </div>

                    </div>

                <?php endif; ?>

                <?php if ($cita['EstadoCita'] === 'CANCELADA'): ?>

                    <div class="col-12 mb-4">

                        <h6 class="text-danger">

                            Motivo de cancelación

                        </h6>

                        <div class="alert alert-danger">

                            <?= nl2br(htmlspecialchars($cita['MotivoCancelacion'])); ?>

                        </div>

                    </div>

                    <?php if (!empty($cita['FechaCancelacion'])): ?>

                        <div class="col-12">

                            <h6 class="text-muted">

                                Fecha de cancelación

                            </h6>

                            <p>

                                <?= htmlspecialchars(
                                    $cita['FechaCancelacion']
                                ); ?>

                            </p>

                        </div>

                    <?php endif; ?>

                <?php endif; ?>

            </div>

        </div>

        <div class="card-footer bg-white">

            <a
                href="<?= Helper::baseUrl('paciente/mis-citas'); ?>"
                class="btn btn-success"
            >

                <i class="bi bi-arrow-left me-2"></i>

                Regresar

            </a>

        </div>

    </div>

</div>