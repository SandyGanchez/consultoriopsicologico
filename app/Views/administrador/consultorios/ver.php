<?php

use App\Core\Session;
use App\Helpers\Helper;

$consultorio = is_array($consultorio ?? null) ? $consultorio : null;
$estadoPagina = is_array($estadoPaginaPublica ?? null) ? $estadoPaginaPublica : [];
$activacionInfo = is_array($activacionInfo ?? null) ? $activacionInfo : null;
$soportaRecuperacion = !empty($soportaRecuperacion);
$success = $success ?? null;
$error = $error ?? null;

if (!$consultorio) {
    echo '<div class="container py-4"><div class="alert alert-danger">No se encontró el consultorio.</div></div>';
    return;
}

$esc = static function (mixed $valor): string {
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
};

$nombreResponsable = trim(implode(' ', array_filter([
    $consultorio['NombrePer'] ?? '',
    $consultorio['ApPatPer'] ?? '',
    $consultorio['ApMatPer'] ?? '',
])));

$estatusInst = strtoupper((string) ($consultorio['EstatusCons'] ?? 'INACTIVO'));
$estadoUsuario = $consultorio['EstadoUsu'] ?? null;
$cuentaActiva = (int) $estadoUsuario === 1;
$requiereCambio = (int) ($consultorio['RequiereCambioContrasena'] ?? 0) === 1;
$pendienteActivacion = $requiereCambio || (!$cuentaActiva && ($activacionInfo['requiere_activacion'] ?? true));

$fechaRegistro = $consultorio['FechaRegistroCons'] ?? null;
$fechaFormateada = (
    !empty($fechaRegistro)
    && strtotime((string) $fechaRegistro) !== false
)
    ? date('d/m/Y H:i', strtotime((string) $fechaRegistro))
    : 'Sin fecha';

$fechaEnvio = $activacionInfo['fecha_ultimo_envio'] ?? null;
$fechaEnvioFmt = (
    !empty($fechaEnvio) && strtotime((string) $fechaEnvio) !== false
)
    ? date('d/m/Y H:i', strtotime((string) $fechaEnvio))
    : 'Sin envíos';

$csrf = Session::csrfToken();
$correo = (string) ($consultorio['CorreoUsu'] ?? '');
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

    <div class="mb-4">
        <h1 class="h3 mb-1"><?= $esc($consultorio['NombreCons'] ?? 'Consultorio'); ?></h1>
        <p class="text-muted mb-0">Cuenta principal del consultorio de esta instalación</p>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-0">Información de la cuenta</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Nombre</dt>
                        <dd class="col-sm-8"><?= $esc($consultorio['NombreCons'] ?? 'Sin nombre'); ?></dd>

                        <dt class="col-sm-4">Responsable</dt>
                        <dd class="col-sm-8"><?= $esc($nombreResponsable !== '' ? $nombreResponsable : 'Sin responsable'); ?></dd>

                        <dt class="col-sm-4">Correo de acceso</dt>
                        <dd class="col-sm-8"><?= $esc($correo !== '' ? $correo : 'Sin cuenta'); ?></dd>

                        <dt class="col-sm-4">Teléfono</dt>
                        <dd class="col-sm-8"><?= $esc($consultorio['TelefonoUsu'] ?? $consultorio['TelefonoCons'] ?? 'Sin teléfono'); ?></dd>

                        <dt class="col-sm-4">Fecha de registro</dt>
                        <dd class="col-sm-8"><?= $esc($fechaFormateada); ?></dd>

                        <dt class="col-sm-4">Estado de cuenta</dt>
                        <dd class="col-sm-8">
                            <span class="badge <?= $cuentaActiva ? 'bg-success' : 'bg-secondary'; ?>">
                                <?= $cuentaActiva ? 'ACTIVA' : 'INACTIVA'; ?>
                            </span>
                        </dd>

                        <dt class="col-sm-4">Estado de activación</dt>
                        <dd class="col-sm-8">
                            <?php if ($pendienteActivacion): ?>
                                <span class="badge bg-info text-dark">Pendiente / requiere enlace</span>
                            <?php else: ?>
                                <span class="badge bg-success">Completada</span>
                            <?php endif; ?>
                            <div class="small text-muted mt-1">
                                Último enlace: <?= $esc($fechaEnvioFmt); ?>
                                · Token: <?= $esc((string) ($activacionInfo['estado'] ?? 'SIN_REGISTRO')); ?>
                            </div>
                        </dd>

                        <dt class="col-sm-4">Estatus institucional</dt>
                        <dd class="col-sm-8">
                            <?= $esc($estatusInst); ?>
                        </dd>

                        <dt class="col-sm-4">Página pública</dt>
                        <dd class="col-sm-8">
                            <?= $esc($estadoPagina['etiqueta'] ?? 'No disponible'); ?>
                            <div class="small text-muted">Solo lectura.</div>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-0">Acciones administrativas</h2>
                </div>
                <div class="card-body d-grid gap-2">

                    <a
                        href="<?= Helper::baseUrl('administrador/consultorio/editar'); ?>"
                        class="btn btn-outline-secondary w-100"
                    >
                        Editar datos administrativos
                    </a>

                    <?php if ($cuentaActiva): ?>
                        <form
                            method="POST"
                            action="<?= Helper::baseUrl('administrador/consultorio/cambiar-estado-cuenta'); ?>"
                            onsubmit="return confirm('¿Inactivar la cuenta principal <?= $esc($correo); ?>? No se modificará el estatus institucional ni otras cuentas.');"
                        >
                            <input type="hidden" name="csrf_token" value="<?= $esc($csrf); ?>">
                            <input type="hidden" name="accion" value="INACTIVAR">
                            <button type="submit" class="btn btn-outline-warning w-100">Inactivar cuenta</button>
                        </form>
                    <?php else: ?>
                        <form
                            method="POST"
                            action="<?= Helper::baseUrl('administrador/consultorio/cambiar-estado-cuenta'); ?>"
                            onsubmit="return confirm('¿Activar la cuenta principal <?= $esc($correo); ?>? No cambia contraseña ni el estatus institucional.');"
                        >
                            <input type="hidden" name="csrf_token" value="<?= $esc($csrf); ?>">
                            <input type="hidden" name="accion" value="ACTIVAR">
                            <button type="submit" class="btn btn-outline-success w-100">Activar cuenta</button>
                        </form>
                    <?php endif; ?>

                    <div class="border rounded p-2">
                        <div class="small text-muted mb-2">Estatus institucional</div>
                        <div class="d-grid gap-2">
                            <?php foreach (['ACTIVO', 'INACTIVO', 'BLOQUEADO'] as $est): ?>
                                <?php if ($estatusInst === $est) { continue; } ?>
                                <form
                                    method="POST"
                                    action="<?= Helper::baseUrl('administrador/consultorio/cambiar-estatus-institucional'); ?>"
                                    onsubmit="return confirm('¿Cambiar estatus institucional a <?= $esc($est); ?>? El registro se conservará por historial.');"
                                >
                                    <input type="hidden" name="csrf_token" value="<?= $esc($csrf); ?>">
                                    <input type="hidden" name="estatus" value="<?= $esc($est); ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-dark w-100">
                                        Marcar <?= $esc($est); ?>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if ($pendienteActivacion): ?>
                        <form
                            method="POST"
                            action="<?= Helper::baseUrl('administrador/consultorio/reenviar-activacion'); ?>"
                            onsubmit="return confirm('¿Reenviar el enlace de activación a <?= $esc($correo); ?>?');"
                        >
                            <input type="hidden" name="csrf_token" value="<?= $esc($csrf); ?>">
                            <button type="submit" class="btn btn-outline-primary w-100">Reenviar activación</button>
                        </form>

                        <?php if (!empty($puedeEliminarSinActividad)): ?>
                            <form
                                method="POST"
                                action="<?= Helper::baseUrl('administrador/consultorio/eliminar-sin-actividad'); ?>"
                                onsubmit="return confirm('Esta acción eliminará definitivamente este registro porque todavía no tiene actividad asociada.');"
                            >
                                <input type="hidden" name="csrf_token" value="<?= $esc($csrf); ?>">
                                <button type="submit" class="btn btn-outline-danger w-100">Cancelar alta sin actividad</button>
                            </form>
                        <?php endif; ?>
                    <?php elseif ($cuentaActiva): ?>
                        <form
                            method="POST"
                            action="<?= Helper::baseUrl('administrador/consultorio/restablecer-acceso'); ?>"
                            onsubmit="return confirm('¿Generar enlace de recuperación para <?= $esc($correo); ?>? No se cambiará la contraseña aquí ni el estado de la cuenta.');"
                        >
                            <input type="hidden" name="csrf_token" value="<?= $esc($csrf); ?>">
                            <button type="submit" class="btn btn-outline-secondary w-100" <?= $soportaRecuperacion ? '' : 'title="Requiere migración RECUPERACION_CONSULTORIO"'; ?>>
                                Restablecer acceso
                            </button>
                        </form>
                        <?php if (!$soportaRecuperacion): ?>
                            <p class="small text-muted mb-0">
                                La recuperación usa un propósito de token separado. Aplica primero la migración propuesta
                                <code>2026_08_03_tipo_recuperacion_consultorio.sql</code>.
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <p class="small text-muted mb-0 mt-2">
                        El administrador no tiene acceso a historia clínica, expedientes ni diagnósticos.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
