<?php

use App\Helpers\Helper;

?>

<section class="consultorio-horario">

    <div class="consultorio-page-header">

        <div>

            <span class="consultorio-page-eyebrow">
                Operación del consultorio
            </span>

            <h1>Horario general</h1>

            <p>
                Consulta y administra los días y horarios de atención
                del establecimiento.
            </p>

        </div>

    </div>

    <?php if (!empty($success)): ?>

        <div class="alert alert-success alert-dismissible fade show">

            <?= htmlspecialchars($success); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>

        </div>

    <?php endif; ?>

    <?php if (!empty($error)): ?>

        <div class="alert alert-danger alert-dismissible fade show">

            <?= htmlspecialchars($error); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>

        </div>

    <?php endif; ?>

    <div class="consultorio-dashboard-panel">

        <div class="table-responsive">

            <table class="table consultorio-table align-middle">

                <thead>

                    <tr>
                        <th>Día</th>
                        <th>Hora inicio</th>
                        <th>Hora fin</th>
                        <th>Estatus</th>
                        <th width="260">Acciones</th>
                    </tr>

                </thead>

                <tbody>

                <?php foreach ($diasSemana as $dia): ?>

                    <?php
                        $horario = $dia['Horario'];
                        $clave = $horario['ClvHorarioCons'] ?? '';
                        $activo =
                            ($horario['EstatusHorario'] ?? '') === 'ACTIVO';
                        $erroresDia =
                            $errores[$clave] ?? [];
                    ?>

                    <tr>

                        <td>
                            <strong>
                                <?= htmlspecialchars($dia['Etiqueta']); ?>
                            </strong>
                        </td>

                        <?php if ($horario === null): ?>

                            <td colspan="4" class="text-muted">
                                Este día aún no está configurado
                                para el consultorio.
                            </td>

                        <?php else: ?>

                            <td colspan="2">

                                <form
                                    method="POST"
                                    action="<?= Helper::baseUrl(
                                        'consultorio/horario/actualizar'
                                    ); ?>"
                                    class="row g-2 align-items-center"
                                >

                                    <input
                                        type="hidden"
                                        name="clvHorarioCons"
                                        value="<?= htmlspecialchars(
                                            $clave
                                        ); ?>"
                                    >

                                    <div class="col-6">

                                        <input
                                            type="time"
                                            name="horaInicio"
                                            class="form-control"
                                            value="<?= htmlspecialchars(
                                                substr(
                                                    (string) (
                                                        $horario[
                                                            'HoraInicio'
                                                        ] ?? ''
                                                    ),
                                                    0,
                                                    5
                                                )
                                            ); ?>"
                                            required
                                        >

                                    </div>

                                    <div class="col-6">

                                        <input
                                            type="time"
                                            name="horaFin"
                                            class="form-control"
                                            value="<?= htmlspecialchars(
                                                substr(
                                                    (string) (
                                                        $horario[
                                                            'HoraFin'
                                                        ] ?? ''
                                                    ),
                                                    0,
                                                    5
                                                )
                                            ); ?>"
                                            required
                                        >

                                    </div>

                                    <?php if (!empty($erroresDia)): ?>

                                        <div class="col-12">

                                            <div class="text-danger small">

                                                <?= htmlspecialchars(
                                                    implode(
                                                        ' ',
                                                        $erroresDia
                                                    )
                                                ); ?>

                                            </div>

                                        </div>

                                    <?php endif; ?>

                                    <div class="col-12">

                                        <button
                                            type="submit"
                                            class="btn btn-sm agenda-filter-button"
                                        >
                                            Guardar horario
                                        </button>

                                    </div>

                                </form>

                            </td>

                            <td>

                                <?php if ($activo): ?>

                                    <span class="badge bg-success">
                                        Activo
                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-secondary">
                                        Inactivo
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <form
                                    method="POST"
                                    action="<?= Helper::baseUrl(
                                        'consultorio/horario/cambiar-estatus'
                                    ); ?>"
                                    class="d-inline"
                                >

                                    <input
                                        type="hidden"
                                        name="clvHorarioCons"
                                        value="<?= htmlspecialchars(
                                            $clave
                                        ); ?>"
                                    >

                                    <?php if ($activo): ?>

                                        <input
                                            type="hidden"
                                            name="accion"
                                            value="inactivar"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-secondary"
                                        >
                                            Inactivar
                                        </button>

                                    <?php else: ?>

                                        <input
                                            type="hidden"
                                            name="accion"
                                            value="activar"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-success"
                                        >
                                            Activar
                                        </button>

                                    <?php endif; ?>

                                </form>

                            </td>

                        <?php endif; ?>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</section>
