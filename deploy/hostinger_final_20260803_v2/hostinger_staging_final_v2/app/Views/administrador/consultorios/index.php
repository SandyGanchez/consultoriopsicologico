<?php

/**
 * LEGACY / INACTIVO — listado multiconsultorio.
 * El controlador ya no renderiza esta vista (instalación = 1 consultorio).
 * Candidata a eliminación posterior.
 */

use App\Helpers\Helper;

$consultorios = $consultorios ?? [];
$success = $success ?? null;
$error = $error ?? null;

if (!function_exists('e')) {
    function e(mixed $valor): string
    {
        return htmlspecialchars(
            (string) $valor,
            ENT_QUOTES,
            'UTF-8'
        );
    }
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
            <?php
            /**
             * Vista de listado multiconsultorio retirada de uso activo.
             * listarConsultorios() redirige a la ficha del único consultorio.
             */
            ?>
            <h1 class="h3 mb-1">Cuenta del consultorio</h1>
            <p class="text-muted mb-0">
                Administra los consultorios registrados y sus cuentas de acceso.
            </p>
        </div>

        <?php if (empty($consultorios)): ?>
            <a
                href="<?= Helper::baseUrl('administrador/consultorios/crear'); ?>"
                class="btn btn-primary"
            >
                <i class="bi bi-plus-circle me-1"></i>
                Configurar consultorio
            </a>
        <?php endif; ?>
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
                                <th>Estado de página pública</th>
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

                                $estadoPagina = $consultorio['estadoPaginaPublica'] ?? [
                                    'codigo' => 'INACTIVO',
                                    'etiqueta' => 'Inactivo'
                                ];

                                $badgePagina = match (
                                    (string) ($estadoPagina['codigo'] ?? '')
                                ) {
                                    'PUBLICADO' => 'bg-success',
                                    'BORRADOR' => 'bg-secondary',
                                    'OCULTO' => 'bg-warning text-dark',
                                    'PENDIENTE_ACTIVACION' => 'bg-info text-dark',
                                    default => 'bg-dark'
                                };
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

                                    <td>
                                        <span class="badge <?= $badgePagina; ?>">
                                            <?= e($estadoPagina['etiqueta'] ?? 'Inactivo'); ?>
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
                                                title="Ver ficha administrativa"
                                            >
                                                <i class="bi bi-eye"></i>
                                                Ver
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

                                            <?php
                                            $cuentaPendiente = (int) ($consultorio['EstadoUsu'] ?? 0) !== 1;
                                            $csrfAdmin = \App\Core\Session::csrfToken();
                                            ?>

                                            <?php if ($cuentaPendiente && $tieneResponsable): ?>
                                                <form
                                                    method="POST"
                                                    action="<?= Helper::baseUrl(
                                                        'administrador/consultorios/reenviar-activacion/'
                                                        . rawurlencode($clvCons)
                                                    ); ?>"
                                                    onsubmit="return confirm('¿Deseas reenviar el enlace de activación a <?= e($consultorio['CorreoUsu'] ?? 'sin correo'); ?>?');"
                                                >
                                                    <input type="hidden" name="csrf_token" value="<?= e($csrfAdmin); ?>">
                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-outline-primary"
                                                    >
                                                        <i class="bi bi-envelope"></i>
                                                        Reenviar activación
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form
                                                    method="POST"
                                                    action="<?= Helper::baseUrl(
                                                        'administrador/consultorios/restablecer-acceso/'
                                                        . rawurlencode($clvCons)
                                                    ); ?>"
                                                    onsubmit="return confirm('¿Deseas restablecer el acceso de <?= e($nombreResponsable); ?>? Correo: <?= e($consultorio['CorreoUsu'] ?? 'sin correo'); ?>');"
                                                >
                                                    <input type="hidden" name="csrf_token" value="<?= e($csrfAdmin); ?>">
                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-outline-secondary"
                                                        <?= !$tieneResponsable ? 'disabled' : ''; ?>
                                                    >
                                                        <i class="bi bi-key"></i>
                                                        Restablecer
                                                    </button>
                                                </form>
                                            <?php endif; ?>

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