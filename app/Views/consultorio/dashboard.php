<section class="consultorio-dashboard">

    <div class="consultorio-page-header">

        <div>

            <span class="consultorio-page-eyebrow">
                Panel general
            </span>

            <h1>
                Consultorio
            </h1>

            <p>
                Consulta los indicadores y próximas sesiones
                del establecimiento.
            </p>

        </div>

    </div>

    <div class="consultorio-dashboard-panel">

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
                href="#"
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