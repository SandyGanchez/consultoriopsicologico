<?php

use App\Core\Session;
use App\Helpers\Helper;

$diasSemana = is_array($diasSemana ?? null) ? $diasSemana : [];
$erroresHorario = is_array($erroresHorario ?? null) ? $erroresHorario : [];
$horarioOld = is_array($horarioOld ?? null) ? $horarioOld : [];
$returnTo = in_array(($returnTo ?? ''), ['configuracion', 'horario'], true)
    ? (string) $returnTo
    : 'horario';
$variante = ($variante ?? 'tabla') === 'cards' ? 'cards' : 'tabla';
$csrf = Session::csrfToken();

$h = static function ($v): string {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
};

?>

<form
    method="POST"
    action="<?= $h(Helper::baseUrl('consultorio/horario/guardar')); ?>"
    class="form-horario-semana"
    id="formHorarioSemana"
    data-horario-semana
>

    <input type="hidden" name="csrf_token" value="<?= $h($csrf); ?>">
    <input type="hidden" name="returnTo" value="<?= $h($returnTo); ?>">

    <?php if ($variante === 'tabla'): ?>

        <div class="table-responsive">
            <table class="table consultorio-table align-middle">
                <thead>
                    <tr>
                        <th>Día</th>
                        <th>Activo</th>
                        <th>Hora inicio</th>
                        <th>Hora fin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($diasSemana as $dia): ?>
                        <?php
                        $codigo = (string) ($dia['DiaSemana'] ?? '');
                        $horario = $dia['Horario'] ?? null;
                        $etiqueta = (string) ($dia['Etiqueta'] ?? $codigo);
                        $old = $horarioOld[$codigo] ?? null;
                        $activo = is_array($old)
                            ? (($old['activo'] ?? '0') === '1')
                            : (($horario['EstatusHorario'] ?? '') === 'ACTIVO');
                        $horaInicio = is_array($old)
                            ? (string) ($old['horaInicio'] ?? '')
                            : substr((string) ($horario['HoraInicio'] ?? ''), 0, 5);
                        $horaFin = is_array($old)
                            ? (string) ($old['horaFin'] ?? '')
                            : substr((string) ($horario['HoraFin'] ?? ''), 0, 5);
                        $errorDia = $erroresHorario[$codigo] ?? null;
                        if (is_array($errorDia)) {
                            $errorDia = implode(' ', $errorDia);
                        }
                        ?>
                        <tr>
                            <td><strong><?= $h($etiqueta); ?></strong></td>
                            <?php if ($horario === null): ?>
                                <td colspan="3" class="text-muted">
                                    Este día aún no está configurado.
                                </td>
                            <?php else: ?>
                                <td>
                                    <input
                                        type="hidden"
                                        name="dias[<?= $h($codigo); ?>][activo]"
                                        value="0"
                                    >
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="dias[<?= $h($codigo); ?>][activo]"
                                            id="activo_<?= $h($codigo); ?>"
                                            value="1"
                                            <?= $activo ? 'checked' : ''; ?>
                                        >
                                        <label
                                            class="form-check-label"
                                            for="activo_<?= $h($codigo); ?>"
                                        >
                                            Activo
                                        </label>
                                    </div>
                                </td>
                                <td>
                                    <label class="visually-hidden" for="inicio_<?= $h($codigo); ?>">
                                        Hora de apertura <?= $h($etiqueta); ?>
                                    </label>
                                    <input
                                        type="time"
                                        class="form-control"
                                        id="inicio_<?= $h($codigo); ?>"
                                        name="dias[<?= $h($codigo); ?>][horaInicio]"
                                        value="<?= $h($horaInicio); ?>"
                                    >
                                </td>
                                <td>
                                    <label class="visually-hidden" for="fin_<?= $h($codigo); ?>">
                                        Hora de cierre <?= $h($etiqueta); ?>
                                    </label>
                                    <input
                                        type="time"
                                        class="form-control"
                                        id="fin_<?= $h($codigo); ?>"
                                        name="dias[<?= $h($codigo); ?>][horaFin]"
                                        value="<?= $h($horaFin); ?>"
                                    >
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php if (!empty($errorDia)): ?>
                            <tr>
                                <td colspan="4" class="text-danger small pt-0">
                                    <?= $h((string) $errorDia); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>

        <?php foreach ($diasSemana as $dia): ?>
            <?php
            $codigo = (string) ($dia['DiaSemana'] ?? '');
            $horario = $dia['Horario'] ?? null;
            $etiqueta = (string) ($dia['Etiqueta'] ?? $codigo);
            $old = $horarioOld[$codigo] ?? null;
            $activo = is_array($old)
                ? (($old['activo'] ?? '0') === '1')
                : (($horario['EstatusHorario'] ?? '') === 'ACTIVO');
            $horaInicio = is_array($old)
                ? (string) ($old['horaInicio'] ?? '')
                : substr((string) ($horario['HoraInicio'] ?? ''), 0, 5);
            $horaFin = is_array($old)
                ? (string) ($old['horaFin'] ?? '')
                : substr((string) ($horario['HoraFin'] ?? ''), 0, 5);
            $errorDia = $erroresHorario[$codigo] ?? null;
            if (is_array($errorDia)) {
                $errorDia = implode(' ', $errorDia);
            }
            ?>
            <div class="schedule-day">
                <div class="schedule-day__label">
                    <?= $h($etiqueta); ?>
                    <span class="badge <?= $activo ? 'bg-success' : 'bg-secondary'; ?> ms-2">
                        <?= $activo ? 'Activo' : 'Inactivo'; ?>
                    </span>
                </div>

                <?php if ($horario === null): ?>
                    <p class="small text-muted mb-0">
                        Este día aún no está configurado.
                    </p>
                <?php else: ?>
                    <input
                        type="hidden"
                        name="dias[<?= $h($codigo); ?>][activo]"
                        value="0"
                    >
                    <div class="form-check mb-2">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="dias[<?= $h($codigo); ?>][activo]"
                            id="cfg_activo_<?= $h($codigo); ?>"
                            value="1"
                            <?= $activo ? 'checked' : ''; ?>
                        >
                        <label
                            class="form-check-label"
                            for="cfg_activo_<?= $h($codigo); ?>"
                        >
                            Día activo
                        </label>
                    </div>
                    <div class="schedule-day__row">
                        <label class="visually-hidden" for="cfg_inicio_<?= $h($codigo); ?>">
                            Hora de apertura <?= $h($etiqueta); ?>
                        </label>
                        <input
                            type="time"
                            name="dias[<?= $h($codigo); ?>][horaInicio]"
                            id="cfg_inicio_<?= $h($codigo); ?>"
                            class="form-control form-control-sm"
                            value="<?= $h($horaInicio); ?>"
                            aria-label="Hora inicio <?= $h($etiqueta); ?>"
                        >
                        <label class="visually-hidden" for="cfg_fin_<?= $h($codigo); ?>">
                            Hora de cierre <?= $h($etiqueta); ?>
                        </label>
                        <input
                            type="time"
                            name="dias[<?= $h($codigo); ?>][horaFin]"
                            id="cfg_fin_<?= $h($codigo); ?>"
                            class="form-control form-control-sm"
                            value="<?= $h($horaFin); ?>"
                            aria-label="Hora fin <?= $h($etiqueta); ?>"
                        >
                    </div>
                    <?php if (!empty($errorDia)): ?>
                        <div class="settings-field__error mb-2">
                            <?= $h((string) $errorDia); ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>

    <div class="mt-3">
        <button
            type="submit"
            class="btn <?= $variante === 'cards'
                ? 'btn-settings-primary'
                : 'agenda-filter-button'; ?>"
            data-horario-submit
        >
            Guardar horario de atención
        </button>
    </div>
</form>
