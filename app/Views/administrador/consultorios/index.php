<?php

use App\Helpers\Helper;

$consultorios = $consultorios ?? [];
$success = $success ?? null;
$error = $error ?? null;

function e(mixed $valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<div class="container-fluid py-4">

    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= e($success); ?>
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
            <?= e($error); ?>
            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Consultorios</h1>
            <p class="text-muted mb-0">
                Administra los consultorios registrados y sus cuentas de acceso.
            </p>
        </div>

        <a
            href="<?= Helper::baseUrl('administrador/consultorios/crear'); ?>"
            class="btn btn-primary"
        >
            <i class="bi bi-plus-circle me-1"></i>
            Registrar consultorio
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">

            <?php if (empty($consultorios)): ?>

                <div class="text-center py-5 px-3">
                    <i class="bi bi-building fs-1"></i>

                    <h2 class="h5 mt-3">
                        No hay consultorios registrados
                    </h2>

                    <p class="text-muted">
                        Registra el primer consultorio para comenzar.
                    </p>

                    <a
                        href="<?= Helper::baseUrl('administrador/consultorios/crear'); ?>"
                        class="btn btn-primary"
                    >
                        Registrar consultorio
                    </a>
                </div>

            <?php else: ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Consultorio</th>
                                <th>Responsable</th>
                                <th>Correo</th>
                                <th>Teléfono</th>
                                <th>Municipio</th>
                                <th>Fecha de registro</th>
                                <th>Estatus</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($consultorios as $consultorio): ?>
                                <?php
                                $clvCons = (string) ($consultorio['ClvCons'] ?? '');

                                $estatus = strtoupper(
                                    trim((string) ($consultorio['EstatusCons'] ?? 'INACTIVO'))
                                );

                                $activo = $estatus === 'ACTIVO';

                                $nombreResponsable = trim(
                                    implode(' ', array_filter([
                                        $consultorio['NombrePer'] ?? '',
                                        $consultorio['ApPatPer'] ?? '',
                                        $consultorio['ApMatPer'] ?? ''
                                    ]))
                                );

                                $nombreResponsable = $nombreResponsable !== ''
                                    ? $nombreResponsable
                                    : 'Sin responsable';

                                $correo = $consultorio['CorreoElectronico']
                                    ?? $consultorio['CorreoUsu']
                                    ?? 'Sin correo';

                                $telefono = $consultorio['TelefonoCons']
                                    ?? $consultorio['TelefonoUsu']
                                    ?? 'Sin teléfono';

                                $municipio = $consultorio['MunicipioDir']
                                    ?? 'Sin municipio';

                                $fechaRegistro = $consultorio['FechaRegistroCons'] ?? null;

                                $fechaFormateada = (
                                    !empty($fechaRegistro)
                                    && strtotime((string) $fechaRegistro) !== false
                                )
                                    ? date('d/m/Y', strtotime((string) $fechaRegistro))
                                    : 'Sin fecha';

                                $tieneResponsable = !empty($consultorio['ClvUsu']);
                                ?>

                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <div
                                                class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                                style="width:44px;height:44px;background:#e4f2f5;color:#657166;"
                                            >
                                                <i class="bi bi-building"></i>
                                            </div>

                                            <div>
                                                <strong><?= e($consultorio['NombreCons'] ?? 'Sin nombre'); ?></strong>
                                                <div class="small text-muted"><?= e($clvCons); ?></div>
                                            </div>
                                        </div>
                                    </td>

                                    <td><?= e($nombreResponsable); ?></td>
                                    <td><?= e($correo); ?></td>
                                    <td><?= e($telefono); ?></td>
                                    <td><?= e($municipio); ?></td>
                                    <td><?= e($fechaFormateada); ?></td>

                                    <td>
                                        <span class="badge <?= $activo ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?= $activo ? 'ACTIVO' : 'INACTIVO'; ?>
                                        </span>
                                    </td>

                                    <td class="text-end">
                                        <div class="d-flex flex-wrap justify-content-end gap-1">

                                            <a
                                                href="<?= Helper::baseUrl(
                                                    'administrador/consultorios/ver/'
                                                    . rawurlencode($clvCons)
                                                ); ?>"
                                                class="btn btn-sm btn-outline-info"
                                                title="Ver consultorio"
                                            >
                                                <i class="bi bi-eye"></i>
                                                Ver
                                            </a>

                                            <a
                                                href="<?= Helper::baseUrl(
                                                    'administrador/consultorios/editar/'
                                                    . rawurlencode($clvCons)
                                                ); ?>"
                                                class="btn btn-sm btn-outline-primary"
                                                title="Editar consultorio"
                                            >
                                                <i class="bi bi-pencil"></i>
                                                Editar
                                            </a>

                                            <?php if ($activo): ?>
                                                <form
                                                    method="POST"
                                                    action="<?= Helper::baseUrl(
                                                        'administrador/consultorios/desactivar/'
                                                        . rawurlencode($clvCons)
                                                    ); ?>"
                                                    onsubmit="return confirm('¿Deseas desactivar este consultorio y bloquear temporalmente sus cuentas relacionadas?');"
                                                >
                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-outline-warning"
                                                    >
                                                        <i class="bi bi-pause-circle"></i>
                                                        Desactivar
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form
                                                    method="POST"
                                                    action="<?= Helper::baseUrl(
                                                        'administrador/consultorios/activar/'
                                                        . rawurlencode($clvCons)
                                                    ); ?>"
                                                    onsubmit="return confirm('¿Deseas activar este consultorio y restablecer el acceso de sus cuentas relacionadas?');"
                                                >
                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-outline-success"
                                                    >
                                                        <i class="bi bi-check-circle"></i>
                                                        Activar
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <form
                                                method="POST"
                                                action="<?= Helper::baseUrl(
                                                    'administrador/consultorios/restablecer-acceso/'
                                                    . rawurlencode($clvCons)
                                                ); ?>"
                                                onsubmit="return confirm('¿Deseas restablecer el acceso de <?= e($nombreResponsable); ?>? Correo: <?= e($consultorio['CorreoUsu'] ?? 'sin correo'); ?>');"
                                            >
                                                <button
                                                    type="submit"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    <?= !$tieneResponsable ? 'disabled' : ''; ?>
                                                >
                                                    <i class="bi bi-key"></i>
                                                    Restablecer
                                                </button>
                                            </form>

                                        </div>
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