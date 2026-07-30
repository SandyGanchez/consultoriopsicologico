<?php

use App\Helpers\Helper;

$totalConsultorios =
    $totalConsultorios ?? 0;

$consultoriosActivos =
    $consultoriosActivos ?? 0;

$consultoriosInactivos =
    $consultoriosInactivos ?? 0;

$consultoriosRecientes =
    $consultoriosRecientes ?? [];

$success = $success ?? null;
$error = $error ?? null;

$nombreAdministrador =
    $usuario['NombrePer']
    ?? $usuario['nombre']
    ?? 'Administrador';

?>

<div class="container py-4">

    <?php if (!empty($success)): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert"
        >
            <?= htmlspecialchars(
                $success,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>
        </div>

    <?php endif; ?>

    <?php if (!empty($error)): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert"
        >
            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>
        </div>

    <?php endif; ?>

    <div
        class="d-flex flex-column flex-md-row
               justify-content-between
               align-items-md-center
               gap-3 mb-4"
    >
        <div>
            <h1 class="h3 mb-1">
                Panel de administración
            </h1>

            <p class="text-muted mb-0">
                Bienvenido,

                <?= htmlspecialchars(
                    $nombreAdministrador,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>.
            </p>
        </div>

        <a
            href="<?= Helper::baseUrl(
                'administrador/consultorios/crear'
            ) ?>"
            class="btn btn-primary"
        >
            Registrar consultorio
        </a>
    </div>

    <!-- RESUMEN -->

    <div class="row g-3 mb-4">

        <div class="col-12 col-md-4">

            <div class="card shadow-sm h-100 border-0">

                <div class="card-body">

                    <p class="text-muted mb-1">
                        Total de consultorios
                    </p>

                    <h2 class="display-6 mb-0">
                        <?= (int) $totalConsultorios ?>
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-12 col-md-4">

            <div class="card shadow-sm h-100 border-0">

                <div class="card-body">

                    <p class="text-muted mb-1">
                        Consultorios activos
                    </p>

                    <h2 class="display-6 text-success mb-0">
                        <?= (int) $consultoriosActivos ?>
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-12 col-md-4">

            <div class="card shadow-sm h-100 border-0">

                <div class="card-body">

                    <p class="text-muted mb-1">
                        Consultorios inactivos
                    </p>

                    <h2 class="display-6 text-danger mb-0">
                        <?= (int) $consultoriosInactivos ?>
                    </h2>

                </div>

            </div>

        </div>

    </div>

    <!-- CONSULTORIOS RECIENTES -->

    <div class="card shadow-sm border-0">

        <div
            class="card-header bg-white
                   d-flex flex-column flex-sm-row
                   justify-content-between
                   align-items-sm-center
                   gap-2 py-3"
        >
            <h2 class="h5 mb-0">
                Consultorios recientes
            </h2>

            <a
                href="<?= Helper::baseUrl(
                    'administrador/consultorios'
                ) ?>"
                class="btn btn-sm btn-outline-primary"
            >
                Ver todos
            </a>
        </div>

        <div class="card-body p-0">

            <?php if (empty($consultoriosRecientes)): ?>

                <div class="p-5 text-center">

                    <p class="text-muted mb-3">
                        No hay consultorios registrados.
                    </p>

                    <a
                        href="<?= Helper::baseUrl(
                            'administrador/consultorios/crear'
                        ) ?>"
                        class="btn btn-primary btn-sm"
                    >
                        Registrar el primero
                    </a>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table
                        class="table table-hover
                               align-middle mb-0"
                    >

                        <thead class="table-light">

                            <tr>
                                <th>Consultorio</th>
                                <th>Ubicación</th>
                                <th>Correo</th>
                                <th>Estado</th>
                                <th>Fecha de registro</th>
                                <th class="text-end">Acción</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach (
                                $consultoriosRecientes
                                as $consultorio
                            ): ?>

                                <?php

                                $clvConsultorio =
                                    $consultorio['ClvCons']
                                    ?? '';

                                $nombreConsultorio =
                                    $consultorio['NombreCons']
                                    ?? 'Sin nombre';

                                $correoConsultorio =
                                    $consultorio['CorreoElectronico']
                                    ?? 'Sin correo';

                                $municipio =
                                    $consultorio['MunicipioDir']
                                    ?? '';

                                $estado =
                                    $consultorio['EstadoDir']
                                    ?? '';

                                $estatus =
                                    strtoupper(
                                        $consultorio[
                                            'EstatusCons'
                                        ]
                                        ?? 'INACTIVO'
                                    );

                                $activo =
                                    $estatus === 'ACTIVO';

                                $fechaRegistro =
                                    $consultorio[
                                        'FechaRegistroCons'
                                    ]
                                    ?? null;

                                $ubicacion =
                                    implode(
                                        ', ',
                                        array_filter([
                                            $municipio,
                                            $estado
                                        ])
                                    );

                                ?>

                                <tr>

                                    <td>
                                        <span class="fw-semibold">

                                            <?= htmlspecialchars(
                                                $nombreConsultorio,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </span>
                                    </td>

                                    <td>

                                        <?= htmlspecialchars(
                                            $ubicacion !== ''
                                                ? $ubicacion
                                                : 'Sin ubicación',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>

                                    <td>

                                        <?= htmlspecialchars(
                                            $correoConsultorio,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>

                                    <td>

                                        <span
                                            class="badge <?= $activo
                                                ? 'bg-success'
                                                : 'bg-secondary'
                                            ?>"
                                        >
                                            <?= htmlspecialchars(
                                                $estatus,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>

                                    </td>

                                    <td>

                                        <?php if (
                                            !empty($fechaRegistro)
                                            && strtotime(
                                                $fechaRegistro
                                            ) !== false
                                        ): ?>

                                            <?= htmlspecialchars(
                                                date(
                                                    'd/m/Y',
                                                    strtotime(
                                                        $fechaRegistro
                                                    )
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        <?php else: ?>

                                            <span class="text-muted">
                                                Sin fecha
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td class="text-end">

                                        <?php if (
                                            $clvConsultorio !== ''
                                        ): ?>

                                            <a
                                                href="<?= Helper::baseUrl(
                                                    'administrador/consultorios/ver/'
                                                    . rawurlencode(
                                                        $clvConsultorio
                                                    )
                                                ) ?>"
                                                class="btn btn-sm
                                                       btn-outline-primary"
                                            >
                                                Ver
                                            </a>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>