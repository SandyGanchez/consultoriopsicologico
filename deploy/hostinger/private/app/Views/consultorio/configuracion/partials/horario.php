<?php

use App\Core\Session;
use App\Helpers\Helper;

$csrf = Session::csrfToken();
$erroresHorario = $erroresHorario ?? [];

?>

<div class="settings-card clinic-schedule-card">

    <div class="settings-card__header">
        <i class="bi bi-clock" aria-hidden="true"></i>
        <span>Horario de atención</span>
    </div>

    <p class="small text-muted mb-3">
        Cada día se guarda de forma independiente. Los cambios respetan
        las disponibilidades activas de los especialistas.
    </p>

    <?php foreach ($diasSemana as $dia): ?>

        <?php
            $horario = $dia['Horario'];
            $clave = $horario['ClvHorarioCons'] ?? '';
            $activo =
                ($horario['EstatusHorario'] ?? '') === 'ACTIVO';
            $erroresDia = $erroresHorario[$clave] ?? [];
        ?>

        <div class="schedule-day">

            <div class="schedule-day__label">
                <?= htmlspecialchars($dia['Etiqueta']); ?>

                <?php if ($horario !== null): ?>

                    <span
                        class="badge <?= $activo
                            ? 'bg-success'
                            : 'bg-secondary'; ?> ms-2"
                    >
                        <?= $activo ? 'Activo' : 'Inactivo'; ?>
                    </span>

                <?php endif; ?>
            </div>

            <?php if ($horario === null): ?>

                <p class="small text-muted mb-0">
                    Este día aún no está configurado.
                </p>

            <?php else: ?>

                <form
                    method="POST"
                    action="<?= Helper::baseUrl(
                        'consultorio/horario/actualizar'
                    ); ?>"
                    class="mb-2"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($csrf); ?>"
                    >

                    <input
                        type="hidden"
                        name="returnTo"
                        value="configuracion"
                    >

                    <input
                        type="hidden"
                        name="clvHorarioCons"
                        value="<?= htmlspecialchars($clave); ?>"
                    >

                    <div class="schedule-day__row">

                        <input
                            type="time"
                            name="horaInicio"
                            class="form-control form-control-sm"
                            value="<?= htmlspecialchars(
                                substr(
                                    (string) (
                                        $horario['HoraInicio'] ?? ''
                                    ),
                                    0,
                                    5
                                )
                            ); ?>"
                            required
                            aria-label="Hora inicio <?= htmlspecialchars(
                                $dia['Etiqueta']
                            ); ?>"
                        >

                        <input
                            type="time"
                            name="horaFin"
                            class="form-control form-control-sm"
                            value="<?= htmlspecialchars(
                                substr(
                                    (string) (
                                        $horario['HoraFin'] ?? ''
                                    ),
                                    0,
                                    5
                                )
                            ); ?>"
                            required
                            aria-label="Hora fin <?= htmlspecialchars(
                                $dia['Etiqueta']
                            ); ?>"
                        >

                    </div>

                    <?php if (!empty($erroresDia)): ?>

                        <div class="settings-field__error mb-2">
                            <?= htmlspecialchars(
                                implode(' ', $erroresDia)
                            ); ?>
                        </div>

                    <?php endif; ?>

                    <button
                        type="submit"
                        class="btn btn-settings-secondary btn-sm"
                    >
                        Guardar horario
                    </button>

                </form>

                <div class="schedule-day__actions">

                    <form
                        method="POST"
                        action="<?= Helper::baseUrl(
                            'consultorio/horario/cambiar-estatus'
                        ); ?>"
                        class="d-inline"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars($csrf); ?>"
                        >

                        <input
                            type="hidden"
                            name="returnTo"
                            value="configuracion"
                        >

                        <input
                            type="hidden"
                            name="clvHorarioCons"
                            value="<?= htmlspecialchars($clave); ?>"
                        >

                        <?php if ($activo): ?>

                            <input
                                type="hidden"
                                name="accion"
                                value="inactivar"
                            >

                            <button
                                type="submit"
                                class="btn btn-settings-secondary btn-sm"
                            >
                                Inactivar día
                            </button>

                        <?php else: ?>

                            <input
                                type="hidden"
                                name="accion"
                                value="activar"
                            >

                            <button
                                type="submit"
                                class="btn btn-settings-secondary btn-sm"
                            >
                                Activar día
                            </button>

                        <?php endif; ?>

                    </form>

                </div>

            <?php endif; ?>

        </div>

    <?php endforeach; ?>

</div>
