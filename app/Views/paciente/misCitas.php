<div class="container-fluid">

    <!-- Encabezado -->

    <div class="card paciente-card-header shadow-sm mb-4">

        <div class="card-body">

            <h2>

                <i class="bi bi-calendar-check-fill me-2"></i>

                Mis citas

            </h2>

            <p class="mb-0">

                Aquí puedes consultar todas tus citas
                programadas, finalizadas o canceladas.

            </p>

        </div>

    </div>


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

            </div>

        </div>

    <?php else: ?>


        <div class="row g-4">

            <?php foreach ($citas as $cita): ?>

                <div class="col-lg-6">

                    <div class="card shadow-sm h-100 paciente-cita-card">

                        <div class="card-header paciente-card-title d-flex justify-content-between align-items-center">

                            <span>

                                <i class="bi bi-calendar-heart-fill me-2"></i>

                                <?= htmlspecialchars($cita['FechaCita']); ?>

                            </span>

                            <?php

                                $badge='bg-secondary';

                                switch($cita['EstadoCita']){

                                    case 'PROGRAMADA':
                                        $badge='bg-success';
                                        break;

                                    case 'FINALIZADA':
                                        $badge='bg-primary';
                                        break;

                                    case 'CANCELADA':
                                        $badge='bg-danger';
                                        break;

                                }

                            ?>

                            <span class="badge <?= $badge; ?>">

                                <?= htmlspecialchars($cita['EstadoCita']); ?>

                            </span>

                        </div>


                        <div class="card-body">

                            <div class="mb-3">

                                <small>

                                    Hora

                                </small>

                                <h5>

                                    <?= htmlspecialchars($cita['HraInicioCita']); ?>

                                    -

                                    <?= htmlspecialchars($cita['HraFinCita']); ?>

                                </h5>

                            </div>


                            <div class="mb-3">

                                <small>

                                    Psicólogo

                                </small>

                                <h5>

                                    <?= htmlspecialchars($cita['NombrePsicologo']); ?>

                                </h5>

                            </div>


                            <div class="mb-3">

                                <small>

                                    Servicio

                                </small>

                                <h5>

                                    <?= htmlspecialchars($cita['NombreServicio']); ?>

                                </h5>

                            </div>


                            <?php if(!empty($cita['NotasCita'])): ?>

                                <div class="alert alert-light border mt-3">

                                    <strong>

                                        Notas

                                    </strong>

                                    <br>

                                    <?= nl2br(htmlspecialchars($cita['NotasCita'])); ?>

                                </div>

                            <?php endif; ?>

                        </div>


                        <div class="card-footer bg-white border-0">

                            <button
                                class="btn btn-outline-success w-100"
                                disabled
                            >

                                <i class="bi bi-eye-fill me-2"></i>

                                Ver detalles

                            </button>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>