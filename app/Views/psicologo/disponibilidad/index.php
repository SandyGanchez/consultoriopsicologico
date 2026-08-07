<?php

use App\Helpers\Helper;

?>

<section class="psicologo-dashboard">

    <div class="psicologo-page-header">

        <div>

            <span class="psicologo-page-eyebrow">
                Agenda personal
            </span>

            <h1>Mi disponibilidad</h1>

            <p>
                Define los bloques horarios en los que puedes atender.
                Deben estar dentro del horario general del consultorio
                y no solaparse entre sí.
            </p>

            <p class="text-muted mb-0 mt-2">
                Los horarios mostrados a los pacientes son opciones de inicio.
                Cuando una cita se reserva, las opciones que se superponen
                dejan de estar disponibles.
            </p>

        </div>

    </div>

    <?php if (!empty($success)): ?>

        <div class="alert alert-success alert-dismissible fade show" role="status">

            <?= htmlspecialchars($success); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>

        </div>

    <?php endif; ?>

    <?php if (!empty($warning)): ?>

        <div
            class="alert alert-warning alert-dismissible fade show"
            role="status"
        >
            <i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>
            <?= htmlspecialchars((string) $warning); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>
        </div>

    <?php endif; ?>

    <?php
    $alertaCompatibilidad = $alertaCompatibilidad ?? null;
    $resumenPorBloque = is_array($resumenPorBloque ?? null)
        ? $resumenPorBloque
        : [];
    ?>

    <?php if (!empty($alertaCompatibilidad['mensaje'])): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert"
            aria-live="polite"
        >
            <i class="bi bi-x-octagon-fill me-1" aria-hidden="true"></i>
            <strong>Incompatibilidad de servicios:</strong>
            <?= htmlspecialchars(
                (string) $alertaCompatibilidad['mensaje']
            ); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>
        </div>

    <?php endif; ?>

    <?php if (!empty($error)): ?>

        <div class="alert alert-danger alert-dismissible fade show" role="alert">

            <?= htmlspecialchars($error); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>

        </div>

    <?php endif; ?>

    <?php foreach ($diasSemana as $dia): ?>

        <?php
            $horarioCons = $dia['HorarioConsultorio'];
            $diaClave = $dia['DiaSemana'];
            $consultorioActivo =
                $horarioCons
                && ($horarioCons['EstatusHorario'] ?? '') === 'ACTIVO';
            $erroresNuevo =
                $errores['nuevo_' . $diaClave] ?? [];
        ?>

        <div class="psicologo-panel mb-4">

            <div class="mb-3">

                <h2 class="h5 mb-1">
                    <?= htmlspecialchars($dia['Etiqueta']); ?>
                </h2>

                <?php if ($consultorioActivo): ?>

                    <small class="text-muted">
                        Horario del consultorio:
                        <?= htmlspecialchars(
                            substr(
                                (string) $horarioCons['HoraInicio'],
                                0,
                                5
                            )
                        ); ?>
                        –
                        <?= htmlspecialchars(
                            substr(
                                (string) $horarioCons['HoraFin'],
                                0,
                                5
                            )
                        ); ?>
                    </small>

                <?php elseif ($horarioCons): ?>

                    <small class="text-muted">
                        El consultorio no atiende este día.
                    </small>

                <?php else: ?>

                    <small class="text-muted">
                        Horario del consultorio sin configurar.
                    </small>

                <?php endif; ?>

            </div>

            <?php if (!empty($erroresNuevo)): ?>

                <div class="alert alert-danger py-2">

                    <?= htmlspecialchars(
                        implode(' ', $erroresNuevo)
                    ); ?>

                </div>

            <?php endif; ?>

            <div class="table-responsive mb-3">

                <table class="table align-middle">

                    <thead>

                        <tr>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Estatus</th>
                            <th width="280">Acciones</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if (empty($dia['Bloques'])): ?>

                        <tr>

                            <td
                                colspan="4"
                                class="text-muted"
                            >
                                Sin bloques registrados.
                            </td>

                        </tr>

                    <?php endif; ?>

                    <?php foreach ($dia['Bloques'] as $bloque): ?>

                        <?php
                            $clave =
                                $bloque['ClvDisponibilidad'];
                            $activa =
                                ($bloque[
                                    'EstatusDisponibilidad'
                                ] ?? '') === 'ACTIVA';
                            $erroresBloque =
                                $errores[$clave] ?? [];
                            $resumenBloque =
                                $resumenPorBloque[$clave] ?? null;
                            $nivelAlerta = (string) (
                                $resumenBloque['nivelAlerta'] ?? ''
                            );
                            $badgeAlerta = match ($nivelAlerta) {
                                'verde' => 'success',
                                'ambar' => 'warning',
                                'rojo' => 'danger',
                                default => 'secondary'
                            };
                            $iconoAlerta = match ($nivelAlerta) {
                                'verde' => 'bi-check-circle-fill',
                                'ambar' => 'bi-exclamation-triangle-fill',
                                'rojo' => 'bi-x-octagon-fill',
                                default => 'bi-info-circle'
                            };
                            $textoAlerta = match ($nivelAlerta) {
                                'verde' => 'Compatible',
                                'ambar' => 'Revisar',
                                'rojo' => 'Incompatible',
                                default => 'Sin evaluar'
                            };
                        ?>

                        <tr>

                            <td colspan="4" class="border-0 p-0">

                                <?php if (!empty($erroresBloque)): ?>

                                    <div class="alert alert-danger py-2 mb-2">

                                        <?= htmlspecialchars(
                                            implode(
                                                ' ',
                                                $erroresBloque
                                            )
                                        ); ?>

                                    </div>

                                <?php endif; ?>

                            </td>

                        </tr>

                        <tr>

                            <td>

                                <form
                                    method="post"
                                    action="<?= Helper::baseUrl(
                                        'psicologo/disponibilidad/actualizar'
                                    ); ?>"
                                    id="form-<?= htmlspecialchars(
                                        $clave
                                    ); ?>"
                                >

                                    <input
                                        type="hidden"
                                        name="clvDisponibilidad"
                                        value="<?= htmlspecialchars(
                                            $clave
                                        ); ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="diaSemana"
                                        value="<?= htmlspecialchars(
                                            $diaClave
                                        ); ?>"
                                    >

                                </form>

                                <input
                                    type="time"
                                    name="horaInicio"
                                    class="form-control form-control-sm"
                                    form="form-<?= htmlspecialchars(
                                        $clave
                                    ); ?>"
                                    value="<?= htmlspecialchars(
                                        substr(
                                            (string) (
                                                $bloque[
                                                    'HoraInicio'
                                                ]
                                            ),
                                            0,
                                            5
                                        )
                                    ); ?>"
                                    required
                                >

                            </td>

                            <td>

                                <input
                                    type="time"
                                    name="horaFin"
                                    class="form-control form-control-sm"
                                    form="form-<?= htmlspecialchars(
                                        $clave
                                    ); ?>"
                                    value="<?= htmlspecialchars(
                                        substr(
                                            (string) (
                                                $bloque[
                                                    'HoraFin'
                                                ]
                                            ),
                                            0,
                                            5
                                        )
                                    ); ?>"
                                    required
                                >

                            </td>

                            <td>

                                <?php if ($activa): ?>

                                    <span class="badge bg-success">
                                        Activa
                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-secondary">
                                        Inactiva
                                    </span>

                                <?php endif; ?>

                                <?php if ($nivelAlerta !== ''): ?>
                                    <span
                                        class="badge bg-<?= htmlspecialchars(
                                            $badgeAlerta
                                        ); ?> ms-1"
                                        title="<?= htmlspecialchars(
                                            (string) (
                                                $resumenBloque['mensajeAlerta']
                                                ?? $textoAlerta
                                            )
                                        ); ?>"
                                    >
                                        <i
                                            class="bi <?= htmlspecialchars(
                                                $iconoAlerta
                                            ); ?>"
                                            aria-hidden="true"
                                        ></i>
                                        <span class="visually-hidden">
                                            Estado de compatibilidad:
                                        </span>
                                        <?= htmlspecialchars($textoAlerta); ?>
                                    </span>
                                <?php endif; ?>

                            </td>

                            <td>

                                <div class="d-flex flex-wrap gap-2">

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-outline-primary"
                                        form="form-<?= htmlspecialchars(
                                            $clave
                                        ); ?>"
                                    >
                                        Guardar
                                    </button>

                                    <form
                                        method="post"
                                        action="<?= Helper::baseUrl(
                                            'psicologo/disponibilidad/cambiar-estatus'
                                        ); ?>"
                                        class="d-inline"
                                    >

                                        <input
                                            type="hidden"
                                            name="clvDisponibilidad"
                                            value="<?= htmlspecialchars(
                                                $clave
                                            ); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="accion"
                                            value="<?= $activa
                                                ? 'inactivar'
                                                : 'activar'; ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-secondary"
                                        >
                                            <?= $activa
                                                ? 'Inactivar'
                                                : 'Activar'; ?>
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                        <?php if (
                            is_array($resumenBloque)
                            && !empty($resumenBloque['servicios'])
                        ): ?>
                            <tr>
                                <td colspan="4" class="bg-light">
                                    <?php if (!empty($resumenBloque['efectivo'])): ?>
                                        <p class="small mb-2">
                                            Bloque efectivo:
                                            <?= htmlspecialchars(
                                                substr(
                                                    (string) $resumenBloque['efectivo']['inicio'],
                                                    0,
                                                    5
                                                )
                                            ); ?>
                                            –
                                            <?= htmlspecialchars(
                                                substr(
                                                    (string) $resumenBloque['efectivo']['fin'],
                                                    0,
                                                    5
                                                )
                                            ); ?>
                                            (<?= (int) $resumenBloque['efectivo']['minutos']; ?> min)
                                        </p>
                                    <?php endif; ?>

                                    <?php if (!empty($resumenBloque['mensajeAlerta'])): ?>
                                        <p class="small mb-2">
                                            <i
                                                class="bi <?= htmlspecialchars($iconoAlerta); ?>"
                                                aria-hidden="true"
                                            ></i>
                                            <?= htmlspecialchars(
                                                (string) $resumenBloque['mensajeAlerta']
                                            ); ?>
                                        </p>
                                    <?php endif; ?>

                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th scope="col">Servicio</th>
                                                    <th scope="col">Duración</th>
                                                    <th scope="col">Capacidad máxima teórica</th>
                                                    <th scope="col">Opciones de inicio actualmente disponibles</th>
                                                    <th scope="col">Restante</th>
                                                    <th scope="col">Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (
                                                    $resumenBloque['servicios'] as $filaServ
                                                ): ?>
                                                    <?php
                                                    $nivelFila = (string) (
                                                        $filaServ['nivelAlerta'] ?? ''
                                                    );
                                                    $badgeFila = match ($nivelFila) {
                                                        'verde' => 'success',
                                                        'ambar' => 'warning',
                                                        'rojo' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                    $capTeorica = (int) (
                                                        $filaServ['capacidadTeorica'] ?? 0
                                                    );
                                                    $opcionesInicio = (int) (
                                                        $filaServ['opcionesInicioDisponibles']
                                                        ?? 0
                                                    );
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <?= htmlspecialchars(
                                                                (string) (
                                                                    $filaServ['NombreServicio']
                                                                    ?? ''
                                                                )
                                                            ); ?>
                                                        </td>
                                                        <td>
                                                            <?= (int) (
                                                                $filaServ['DuracionMinutos']
                                                                ?? 0
                                                            ); ?> min
                                                        </td>
                                                        <td>
                                                            <?= $capTeorica; ?>
                                                            <?= $capTeorica === 1
                                                                ? 'cita'
                                                                : 'citas'; ?>
                                                            <span class="visually-hidden">
                                                                (máximo teórico consecutivas, no garantizadas)
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?= $opcionesInicio; ?>
                                                            <?= $opcionesInicio === 1
                                                                ? 'opción'
                                                                : 'opciones'; ?>
                                                        </td>
                                                        <td>
                                                            <?= (int) (
                                                                $filaServ['minutosRestantes']
                                                                ?? 0
                                                            ); ?> min
                                                        </td>
                                                        <td>
                                                            <span
                                                                class="badge bg-<?= htmlspecialchars(
                                                                    $badgeFila
                                                                ); ?>"
                                                            >
                                                                <?= htmlspecialchars(
                                                                    (string) (
                                                                        $filaServ['etiquetaEstado']
                                                                        ?? ''
                                                                    )
                                                                ); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="small text-muted mb-0 mt-2">
                                        La capacidad máxima teórica no equivale a citas
                                        simultáneas. Las opciones de inicio son alternativas;
                                        al reservar una, las que se solapan dejan de estar
                                        disponibles.
                                    </p>
                                </td>
                            </tr>
                        <?php elseif (
                            is_array($resumenBloque)
                            && !empty($resumenBloque['mensajeAlerta'])
                        ): ?>
                            <tr>
                                <td colspan="4" class="bg-light small">
                                    <i
                                        class="bi <?= htmlspecialchars($iconoAlerta); ?>"
                                        aria-hidden="true"
                                    ></i>
                                    <?= htmlspecialchars(
                                        (string) $resumenBloque['mensajeAlerta']
                                    ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <?php if ($consultorioActivo): ?>

                <div class="border-top pt-3">

                    <p class="small text-muted mb-2">
                        Agregar bloque
                    </p>

                    <form
                        method="post"
                        action="<?= Helper::baseUrl(
                            'psicologo/disponibilidad/guardar'
                        ); ?>"
                        class="row g-2 align-items-end"
                    >

                        <input
                            type="hidden"
                            name="diaSemana"
                            value="<?= htmlspecialchars($diaClave); ?>"
                        >

                        <div class="col-sm-3 col-md-2">

                            <label class="form-label small mb-1">
                                Inicio
                            </label>

                            <input
                                type="time"
                                name="horaInicio"
                                class="form-control form-control-sm"
                                required
                            >

                        </div>

                        <div class="col-sm-3 col-md-2">

                            <label class="form-label small mb-1">
                                Fin
                            </label>

                            <input
                                type="time"
                                name="horaFin"
                                class="form-control form-control-sm"
                                required
                            >

                        </div>

                        <div class="col-sm-3 col-md-2">

                            <label class="form-label small mb-1">
                                Estatus inicial
                            </label>

                            <select
                                name="estatusDisponibilidad"
                                class="form-select form-select-sm"
                            >
                                <option value="ACTIVA">
                                    Activa
                                </option>
                                <option value="INACTIVA">
                                    Inactiva
                                </option>
                            </select>

                        </div>

                        <div class="col-sm-auto">

                            <button
                                type="submit"
                                class="btn btn-sm btn-primary"
                            >
                                <i class="bi bi-plus-circle"></i>
                                Agregar
                            </button>

                        </div>

                    </form>

                </div>

            <?php endif; ?>

        </div>

    <?php endforeach; ?>

</section>
