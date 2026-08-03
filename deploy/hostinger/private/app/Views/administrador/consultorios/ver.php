<?php

use App\Core\Session;
use App\Helpers\Helper;

$consultorio = is_array($consultorio ?? null) ? $consultorio : null;
$estadoPagina = is_array($estadoPaginaPublica ?? null) ? $estadoPaginaPublica : [];
$success = $success ?? null;
$error = $error ?? null;

if (!$consultorio) {
    echo '<div class="container py-4"><div class="alert alert-danger">No se encontró el consultorio.</div></div>';
    return;
}

$esc = static function (mixed $valor): string {
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
};

$clvCons = (string) ($consultorio['ClvCons'] ?? '');
$nombreResponsable = trim(implode(' ', array_filter([
    $consultorio['NombrePer'] ?? '',
    $consultorio['ApPatPer'] ?? '',
    $consultorio['ApMatPer'] ?? '',
])));

$estatusAdmin = strtoupper((string) ($consultorio['EstatusCons'] ?? 'INACTIVO'));
$estadoUsuario = $consultorio['EstadoUsu'] ?? null;
$cuentaActiva = (int) $estadoUsuario === 1;
$pendienteActivacion = $estadoUsuario !== null && !$cuentaActiva;

$fechaRegistro = $consultorio['FechaRegistroCons'] ?? null;
$fechaFormateada = (
    !empty($fechaRegistro)
    && strtotime((string) $fechaRegistro) !== false
)
    ? date('d/m/Y H:i', strtotime((string) $fechaRegistro))
    : 'Sin fecha';

$csrf = Session::csrfToken();
?>

<div class="container py-4">

    <div class="mb-4">
        <a href="<?= Helper::baseUrl('administrador'); ?>" class="text-decoration-none">
            ← Regresar al panel
        </a>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $esc($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $esc($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= $esc($consultorio['NombreCons'] ?? 'Consultorio'); ?></h1>
            <p class="text-muted mb-0">Ficha administrativa de cuenta</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-0">Información administrativa</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Clave</dt>
                        <dd class="col-sm-8"><?= $esc($clvCons !== '' ? $clvCons : 'Sin clave'); ?></dd>

                        <dt class="col-sm-4">Nombre</dt>
                        <dd class="col-sm-8"><?= $esc($consultorio['NombreCons'] ?? 'Sin nombre'); ?></dd>

                        <dt class="col-sm-4">Responsable</dt>
                        <dd class="col-sm-8"><?= $esc($nombreResponsable !== '' ? $nombreResponsable : 'Sin responsable'); ?></dd>

                        <dt class="col-sm-4">Correo de acceso</dt>
                        <dd class="col-sm-8"><?= $esc($consultorio['CorreoUsu'] ?? 'Sin cuenta'); ?></dd>

                        <dt class="col-sm-4">Teléfono</dt>
                        <dd class="col-sm-8"><?= $esc($consultorio['TelefonoUsu'] ?? $consultorio['TelefonoCons'] ?? 'Sin teléfono'); ?></dd>

                        <dt class="col-sm-4">Municipio</dt>
                        <dd class="col-sm-8"><?= $esc($consultorio['MunicipioDir'] ?? 'Sin municipio'); ?></dd>

                        <dt class="col-sm-4">Fecha de alta</dt>
                        <dd class="col-sm-8"><?= $esc($fechaFormateada); ?></dd>

                        <dt class="col-sm-4">Estado administrativo</dt>
                        <dd class="col-sm-8">
                            <span class="badge <?= $estatusAdmin === 'ACTIVO' ? 'bg-success' : 'bg-secondary'; ?>">
                                <?= $esc($estatusAdmin); ?>
                            </span>
                        </dd>

                        <dt class="col-sm-4">Estado de cuenta</dt>
                        <dd class="col-sm-8">
                            <?php if ($estadoUsuario === null): ?>
                                <span class="badge bg-secondary">Sin cuenta</span>
                            <?php else: ?>
                                <span class="badge <?= $cuentaActiva ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?= $cuentaActiva ? 'ACTIVA' : 'INACTIVA'; ?>
                                </span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-4">Estado de activación</dt>
                        <dd class="col-sm-8">
                            <?php if ($estadoUsuario === null): ?>
                                <span class="badge bg-secondary">Sin cuenta</span>
                            <?php elseif ($pendienteActivacion): ?>
                                <span class="badge bg-info text-dark">Pendiente de activación</span>
                            <?php else: ?>
                                <span class="badge bg-success">Activada</span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-4">Página pública</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-light text-dark border">
                                <?= $esc($estadoPagina['etiqueta'] ?? 'No disponible'); ?>
                            </span>
                            <div class="small text-muted mt-1">Solo lectura. La publicación la gestiona el consultorio.</div>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-0">Acciones de cuenta</h2>
                </div>
                <div class="card-body d-grid gap-2">

                    <?php if ($estatusAdmin === 'ACTIVO'): ?>
                        <form
                            method="POST"
                            action="<?= Helper::baseUrl('administrador/consultorios/desactivar/' . rawurlencode($clvCons)); ?>"
                            onsubmit="return confirm('¿Deseas inactivar este consultorio y suspender el acceso del responsable?');"
                        >
                            <button type="submit" class="btn btn-outline-warning w-100">Inactivar</button>
                        </form>
                    <?php else: ?>
                        <form
                            method="POST"
                            action="<?= Helper::baseUrl('administrador/consultorios/activar/' . rawurlencode($clvCons)); ?>"
                            onsubmit="return confirm('¿Deseas activar este consultorio? No se publicará automáticamente.');"
                        >
                            <button type="submit" class="btn btn-outline-success w-100">Activar</button>
                        </form>
                    <?php endif; ?>

                    <?php if (!empty($consultorio['ClvUsu']) && $pendienteActivacion): ?>
                        <form
                            method="POST"
                            action="<?= Helper::baseUrl('administrador/consultorios/reenviar-activacion/' . rawurlencode($clvCons)); ?>"
                            onsubmit="return confirm('¿Deseas reenviar el enlace de activación?');"
                        >
                            <input type="hidden" name="csrf_token" value="<?= $esc($csrf); ?>">
                            <button type="submit" class="btn btn-outline-primary w-100">Reenviar activación</button>
                        </form>
                    <?php elseif (!empty($consultorio['ClvUsu'])): ?>
                        <form
                            method="POST"
                            action="<?= Helper::baseUrl('administrador/consultorios/restablecer-acceso/' . rawurlencode($clvCons)); ?>"
                            onsubmit="return confirm('¿Deseas generar un enlace seguro de restablecimiento de acceso?');"
                        >
                            <input type="hidden" name="csrf_token" value="<?= $esc($csrf); ?>">
                            <button type="submit" class="btn btn-outline-secondary w-100">Restablecer acceso</button>
                        </form>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>
