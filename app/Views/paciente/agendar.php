<?php

use App\Helpers\Helper;

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;

unset($_SESSION['success'], $_SESSION['error']);

?>

<div class="container-fluid">

    <!-- Encabezado -->
    <div class="card shadow-sm mb-4 paciente-card-header">

        <div class="card-body p-4">

            <h2>

                <i class="bi bi-calendar-plus-fill me-2"></i>

                Agendar una cita

            </h2>

            <p class="mb-0">

                Selecciona el psicólogo, el servicio, la fecha y el horario para programar tu próxima sesión.

            </p>

        </div>

    </div>

    <!-- Mensajes -->
    <?php if ($success): ?>

        <div class="alert alert-success">

            <i class="bi bi-check-circle-fill me-2"></i>

            <?= htmlspecialchars($success); ?>

        </div>

    <?php endif; ?>

    <?php if ($error): ?>

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-triangle-fill me-2"></i>

            <?= htmlspecialchars($error); ?>

        </div>

    <?php endif; ?>

    <div class="card shadow-sm">

        <div class="card-header paciente-card-title">

            <i class="bi bi-journal-medical me-2"></i>

            Información de la cita

        </div>

        <div class="card-body">

            <form
                action="<?= Helper::baseUrl('paciente/agendar'); ?>"
                method="POST"
            >

                <div class="row g-4">

                    <!-- Psicólogo -->
                    <div class="col-md-6">

                        <label class="form-label fw-semibold">

                            Psicólogo

                        </label>

                        <select
                            name="psicologo"
                            class="form-select"
                            required
                        >

                            <option value="">

                                Selecciona un psicólogo

                            </option>

                            <?php foreach ($psicologos as $psicologo): ?>

                                <option
                                    value="<?= $psicologo['ClvPsi']; ?>"
                                >

                                    <?= htmlspecialchars($psicologo['NombrePsicologo']); ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <!-- Servicio -->
                    <div class="col-md-6">

                        <label class="form-label fw-semibold">

                            Servicio

                        </label>

                        <select
                            name="servicio"
                            class="form-select"
                            required
                        >

                            <option value="">

                                Selecciona un servicio

                            </option>

                            <?php foreach ($servicios as $servicio): ?>

                                <option
                                    value="<?= $servicio['ClvServ']; ?>"
                                >

                                    <?= htmlspecialchars($servicio['NombreServicio']); ?>

                                    - $<?= number_format($servicio['CostoServicio'], 2); ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <!-- Fecha -->
                    <div class="col-md-6">

                        <label class="form-label fw-semibold">

                            Fecha

                        </label>

                        <input
                            type="date"
                            name="fecha"
                            class="form-control"
                            min="<?= date('Y-m-d'); ?>"
                            required
                        >

                    </div>

                    <!-- Hora -->
                    <div class="col-md-6">

                        <label class="form-label fw-semibold">

                            Hora

                        </label>

                        <select
                            name="hora"
                            class="form-select"
                            required
                        >

                            <option value="">

                                Selecciona un horario

                            </option>

                            <?php

                            $horas = [

                                '09:00:00',

                                '10:00:00',

                                '11:00:00',

                                '12:00:00',

                                '13:00:00',

                                '14:00:00',

                                '15:00:00',

                                '16:00:00',

                                '17:00:00'

                            ];

                            foreach ($horas as $hora):

                            ?>

                                <option value="<?= $hora; ?>">

                                    <?= substr($hora, 0, 5); ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-end gap-3">

                    <a
                        href="<?= Helper::baseUrl('paciente'); ?>"
                        class="btn btn-outline-secondary px-4"
                    >

                        Cancelar

                    </a>

                    <button
                        type="submit"
                        class="btn btn-success px-4"
                    >

                        <i class="bi bi-check-circle me-2"></i>

                        Confirmar cita

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

