<?php

use App\Core\Session;
use App\Helpers\Helper;

$csrf = Session::csrfToken();

?>

<section class="consultorio-psicologos">

    <div class="consultorio-page-header d-flex justify-content-between align-items-start gap-3 flex-wrap">

        <div>

            <span class="consultorio-page-eyebrow">
                Gestión de especialistas
            </span>

            <h1>Psicólogos</h1>

            <p>
                Administra los especialistas vinculados al consultorio.
            </p>

        </div>

        <a
            href="<?= Helper::baseUrl(
                'consultorio/psicologos/nuevo'
            ); ?>"
            class="btn agenda-filter-button"
        >
            <i class="bi bi-person-plus-fill"></i>
            Agregar psicólogo
        </a>

    </div>

    <?php if (!empty($_SESSION['success'])): ?>

        <div class="alert alert-success alert-dismissible fade show">

            <?= htmlspecialchars($_SESSION['success']); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>

        </div>

        <?php unset($_SESSION['success']); ?>

    <?php endif; ?>

    <?php if (!empty($_SESSION['warning'])): ?>

        <div class="alert alert-warning alert-dismissible fade show">

            <?= htmlspecialchars($_SESSION['warning']); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>

        </div>

        <?php unset($_SESSION['warning']); ?>

    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>

        <div class="alert alert-danger alert-dismissible fade show">

            <?= htmlspecialchars($_SESSION['error']); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>

        </div>

        <?php unset($_SESSION['error']); ?>

    <?php endif; ?>

    <?php if (empty($psicologos)): ?>

        <div class="consultorio-dashboard-panel text-center py-5">

            <i class="bi bi-person-workspace fs-1"></i>

            <h2 class="h5 mt-3">
                No hay especialistas registrados
            </h2>

            <p class="mb-4">
                Registra al primer psicólogo del consultorio.
            </p>

            <a
                href="<?= Helper::baseUrl(
                    'consultorio/psicologos/nuevo'
                ); ?>"
                class="btn agenda-filter-button"
            >
                Agregar psicólogo
            </a>

        </div>

    <?php else: ?>

        <div class="consultorio-dashboard-panel">

            <div class="table-responsive">

                <table class="table consultorio-table align-middle">

                    <thead>

                        <tr>
                            <th>Especialista</th>
                            <th>Contacto</th>
                            <th>Especialidad</th>
                            <th>Cédula</th>
                            <th>Estado</th>
                            <th>Página pública</th>
                            <th class="text-end">Acciones</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($psicologos as $psicologo): ?>

                            <?php
                            $estadoAct =
                                (string) ($psicologo['EstadoActivacion'] ?? '');
                            $etiquetaAct =
                                (string) ($psicologo['EstadoActivacionEtiqueta'] ?? '');
                            $claseEstado = match ($estadoAct) {
                                'ACTIVO' => 'status-asistida',
                                'PENDIENTE_ACTIVACION' => 'status-programada',
                                'ACTIVACION_VENCIDA' => 'status-inasistencia',
                                default => 'status-cancelada'
                            };
                            ?>

                            <tr>

                                <td>

                                    <div class="d-flex align-items-center gap-3">

                                        <div class="consultorio-user-avatar">

                                            <i class="bi bi-person-heart"></i>

                                        </div>

                                        <div>

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $psicologo['NombreCompleto']
                                                ); ?>
                                            </strong>

                                            <small class="d-block text-muted">
                                                Registrado:
                                                <?= htmlspecialchars(
                                                    date(
                                                        'd/m/Y',
                                                        strtotime(
                                                            $psicologo[
                                                                'FechaRegistroPsi'
                                                            ]
                                                        )
                                                    )
                                                ); ?>
                                            </small>

                                        </div>

                                    </div>

                                </td>

                                <td>

                                    <div>
                                        <?= htmlspecialchars(
                                            $psicologo['CorreoUsu']
                                        ); ?>
                                    </div>

                                    <small class="text-muted">
                                        <?= htmlspecialchars(
                                            $psicologo['TelefonoUsu']
                                        ); ?>
                                    </small>

                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $psicologo['EspecialidadPsi']
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $psicologo['CedulaProfesional']
                                    ); ?>
                                </td>

                                <td>

                                    <span class="agenda-status <?= htmlspecialchars($claseEstado); ?>">
                                        <?= htmlspecialchars($etiquetaAct); ?>
                                    </span>

                                </td>

                                <td>

                                    <?php if (
                                        (int) $psicologo['MostrarEnPagina'] === 1
                                    ): ?>

                                        <span class="badge text-bg-success">
                                            Visible
                                        </span>

                                    <?php else: ?>

                                        <span class="badge text-bg-secondary">
                                            Oculto
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td class="text-end">

                                    <a
                                       href="<?= Helper::baseUrl(
    'consultorio/psicologos/editar'
); ?>?id=<?= urlencode(
    $psicologo['ClvPsi']
); ?>"
                                        class="btn btn-sm btn-outline-secondary"
                                        title="Editar"
                                    >
                                        <i class="bi bi-pencil-square"></i>
                                    </a>

                                    <?php if (
                                        !empty($psicologo['PuedeReenviarActivacion'])
                                    ): ?>

                                        <form
                                            method="POST"
                                            action="<?= Helper::baseUrl(
                                                'consultorio/psicologos/reenviar-activacion'
                                            ); ?>"
                                            class="d-inline"
                                            onsubmit="this.querySelector('button').disabled=true;"
                                        >
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="clvUsu"
                                                value="<?= htmlspecialchars(
                                                    (string) $psicologo['ClvUsu'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"
                                            >
                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-primary"
                                                title="Reenviar activación"
                                            >
                                                <i class="bi bi-envelope-arrow-up"></i>
                                            </button>
                                        </form>

                                    <?php endif; ?>

                                    <?php if (
                                        in_array(
                                            $estadoAct,
                                            ['ACTIVO', 'INACTIVO'],
                                            true
                                        )
                                    ): ?>

                                        <a
                                           href="<?= Helper::baseUrl(
    'consultorio/psicologos/cambiar-estatus'
); ?>?id=<?= urlencode(
    $psicologo['ClvPsi']
); ?>"
                                            class="btn btn-sm btn-outline-secondary"
                                            title="Cambiar estado"
                                        >
                                            <?php if (
                                                $psicologo['EstatusPsi'] === 'ACTIVO'
                                            ): ?>

                                                <i class="bi bi-person-dash"></i>

                                            <?php else: ?>

                                                <i class="bi bi-person-check"></i>

                                            <?php endif; ?>
                                        </a>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    <?php endif; ?>

</section>
