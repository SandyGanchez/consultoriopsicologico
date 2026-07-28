<div class="container-fluid">

   <!-- Bienvenida -->

<div class="card shadow-sm mb-4 paciente-card-header">

    <div class="card-body p-4">

        <h2>

            <?= $saludo; ?>,

            <?= htmlspecialchars($usuario['NombrePer']); ?> 👋

        </h2>

        <p class="mb-2">

            <?= ucfirst($fechaActual); ?>

        </p>

        <p class="mb-0">

            Esperamos que tengas un excelente día
            y que este espacio te ayude a cuidar
            de tu bienestar emocional.

        </p>

    </div>

</div>


    <div class="row g-4">

        <!-- Próxima cita -->
        <div class="col-lg-8">

            <div class="card shadow-sm h-100">

                <div class="card-header paciente-card-title">

                    <i class="bi bi-calendar-heart-fill me-2"></i>

                    Próxima cita

                </div>

                <div class="card-body">

                    <?php if ($proximaCita): ?>

                        <div class="row">

                            <div class="col-md-6 mb-4">

                                <small class="text-muted">

                                    Fecha

                                </small>

                                <h5>

                                    <?= htmlspecialchars($proximaCita['FechaCita']); ?>

                                </h5>

                            </div>

                            <div class="col-md-6 mb-4">

                                <small class="text-muted">

                                    Hora

                                </small>

                                <h5>

                                    <?= htmlspecialchars($proximaCita['HraInicioCita']); ?>

                                </h5>

                            </div>

                            <div class="col-md-6">

                                <small class="text-muted">

                                    Psicólogo

                                </small>

                                <h5>

                                    <?= htmlspecialchars($proximaCita['NombrePsicologo']); ?>

                                </h5>

                            </div>

                            <div class="col-md-6">

                                <small class="text-muted">

                                    Servicio

                                </small>

                                <h5>

                                    <?= htmlspecialchars($proximaCita['NombreServicio']); ?>

                                </h5>

                            </div>

                        </div>

                    <?php else: ?>

                        <div class="text-center py-5">

                            <i class="bi bi-calendar-x display-2"></i>

                            <h4 class="mt-3">

                                No tienes citas programadas

                            </h4>

                            <p class="text-muted">

                                Agenda tu primera cita desde el menú lateral.

                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- Información -->
        <div class="col-lg-4">

            <div class="card shadow-sm h-100">

                <div class="card-header paciente-card-title">

                    <i class="bi bi-person-circle me-2"></i>

                    Mi información

                </div>

                <div class="card-body">

                    <p>

                        <strong>Nombre</strong><br>

                        <?= htmlspecialchars($usuario['NombrePer']); ?>

                        <?= htmlspecialchars($usuario['ApPatPer']); ?>

                        <?= htmlspecialchars($usuario['ApMatPer']); ?>

                    </p>

                    <hr>

                    <p>

                        <strong>Correo electrónico</strong><br>

                        <?= htmlspecialchars($usuario['CorreoUsu']); ?>

                    </p>

                    <hr>

                    <p>

                        <strong>Teléfono</strong><br>

                        <?= htmlspecialchars($usuario['TelefonoUsu']); ?>

                    </p>

                </div>

            </div>

        </div>

    </div>


    <!-- Frase -->
    <div class="card shadow-sm mb-4 paciente-card-header">

        <div class="card-header paciente-card-title">

            <i class="bi bi-heart-pulse-fill me-2"></i>

            Frase del día

        </div>

        <div class="card-body text-center">

            <h4 class="mb-3">

                🌿

            </h4>

            <blockquote class="blockquote mb-0">

                <p>

                    "Cuidar tu salud mental también es un acto de amor propio."

                </p>

            </blockquote>

        </div>

    </div>

</div>