<?php

use App\Helpers\Helper;

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
            Registrar especialista
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
                Registrar especialista
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

                                    <?php if (
                                        $psicologo['EstatusPsi'] === 'ACTIVO'
                                    ): ?>

                                        <span class="agenda-status status-asistida">
                                            Activo
                                        </span>

                                    <?php else: ?>

                                        <span class="agenda-status status-inasistencia">
                                            Inactivo
                                        </span>

                                    <?php endif; ?>

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

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    <?php endif; ?>

</section>