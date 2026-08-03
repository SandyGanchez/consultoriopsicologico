<?php

use App\Helpers\Helper;

$estadoInstalacion = (string) ($estadoInstalacion ?? 'ninguno');
$consultorio = is_array($consultorio ?? null) ? $consultorio : null;
$estadoPagina = is_array($estadoPaginaPublica ?? null) ? $estadoPaginaPublica : [];
$notificaciones = is_array($notificaciones ?? null) ? $notificaciones : [];
$ultimaIncidencia = is_array($ultimaIncidenciaAcceso ?? null) ? $ultimaIncidenciaAcceso : null;
$success = $success ?? null;
$error = $error ?? null;

$nombreAdministrador =
    $usuario['NombrePer']
    ?? $usuario['nombre']
    ?? 'Administrador';

$esc = static function (mixed $valor): string {
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
};

?>

<div class="container py-4">

    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $esc($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $esc($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Panel de administración</h1>
            <p class="text-muted mb-0">
                Bienvenido, <?= $esc($nombreAdministrador); ?>.
            </p>
        </div>

        <?php if ($estadoInstalacion === 'ninguno'): ?>
            <a
                href="<?= Helper::baseUrl('administrador/consultorios/crear'); ?>"
                class="btn btn-primary"
            >
                Configurar consultorio
            </a>
        <?php elseif ($consultorio): ?>
            <a
                href="<?= Helper::baseUrl(
                    'administrador/consultorios/ver/'
                    . rawurlencode((string) ($consultorio['ClvCons'] ?? ''))
                ); ?>"
                class="btn btn-outline-primary"
            >
                Ver cuenta del consultorio
            </a>
        <?php endif; ?>
    </div>

    <?php if ($estadoInstalacion === 'ninguno'): ?>

        <div class="card shadow-sm border-0">
            <div class="card-body p-5 text-center">
                <h2 class="h5 mb-2">Configura el consultorio para comenzar</h2>
                <p class="text-muted mb-4">
                    Esta instalación admite un único consultorio. Regístralo para
                    habilitar la activación de su cuenta.
                </p>
                <a
                    href="<?= Helper::baseUrl('administrador/consultorios/crear'); ?>"
                    class="btn btn-primary"
                >
                    Configurar consultorio
                </a>
            </div>
        </div>

    <?php elseif ($estadoInstalacion === 'multiple'): ?>

        <div class="alert alert-warning mb-0">
            Se detectó más de un consultorio en la base de datos. Corrige la
            instalación para dejar exactamente uno. El incidente quedó en el log.
        </div>

    <?php elseif ($consultorio): ?>

        <?php
        $nombreResponsable = trim(implode(' ', array_filter([
            $consultorio['NombrePer'] ?? '',
            $consultorio['ApPatPer'] ?? '',
            $consultorio['ApMatPer'] ?? '',
        ])));
        $estatusAdmin = strtoupper((string) ($consultorio['EstatusCons'] ?? 'INACTIVO'));
        $cuentaActiva = (int) ($consultorio['EstadoUsu'] ?? 0) === 1;
        $fechaAlta = $consultorio['FechaRegistroCons'] ?? null;
        $fechaAltaFmt = (
            !empty($fechaAlta) && strtotime((string) $fechaAlta) !== false
        )
            ? date('d/m/Y H:i', strtotime((string) $fechaAlta))
            : 'Sin fecha';
        $fechaIncidencia = $ultimaIncidencia['fecha'] ?? null;
        $fechaIncidenciaFmt = (
            !empty($fechaIncidencia) && strtotime((string) $fechaIncidencia) !== false
        )
            ? date('d/m/Y H:i', strtotime((string) $fechaIncidencia))
            : null;
        ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h2 class="h5 mb-0">Cuenta del consultorio</h2>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Nombre</dt>
                            <dd class="col-sm-8"><?= $esc($consultorio['NombreCons'] ?? 'Sin nombre'); ?></dd>

                            <dt class="col-sm-4">Responsable</dt>
                            <dd class="col-sm-8"><?= $esc($nombreResponsable !== '' ? $nombreResponsable : 'Sin responsable'); ?></dd>

                            <dt class="col-sm-4">Correo</dt>
                            <dd class="col-sm-8"><?= $esc($consultorio['CorreoUsu'] ?? $consultorio['CorreoElectronico'] ?? 'Sin correo'); ?></dd>

                            <dt class="col-sm-4">Teléfono</dt>
                            <dd class="col-sm-8"><?= $esc($consultorio['TelefonoUsu'] ?? $consultorio['TelefonoCons'] ?? 'Sin teléfono'); ?></dd>

                            <dt class="col-sm-4">Estado de cuenta</dt>
                            <dd class="col-sm-8">
                                <?= $cuentaActiva ? 'Activa' : 'Pendiente de activación / inactiva'; ?>
                            </dd>

                            <dt class="col-sm-4">Estado administrativo</dt>
                            <dd class="col-sm-8"><?= $esc($estatusAdmin); ?></dd>

                            <dt class="col-sm-4">Fecha de alta</dt>
                            <dd class="col-sm-8"><?= $esc($fechaAltaFmt); ?></dd>

                            <dt class="col-sm-4">Activación / página</dt>
                            <dd class="col-sm-8">
                                <?= $esc($estadoPagina['etiqueta'] ?? ($estadoPagina['codigo'] ?? 'Sin estado')); ?>
                            </dd>

                            <dt class="col-sm-4">Última incidencia de acceso</dt>
                            <dd class="col-sm-8">
                                <?php if ($ultimaIncidencia): ?>
                                    <?= $esc($ultimaIncidencia['descripcion'] ?? 'Incidencia'); ?>
                                    <?php if ($fechaIncidenciaFmt): ?>
                                        <span class="text-muted">— <?= $esc($fechaIncidenciaFmt); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($ultimaIncidencia['estado'])): ?>
                                        <span class="badge text-bg-light border ms-1"><?= $esc($ultimaIncidencia['estado']); ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Sin incidencias registradas
                                <?php endif; ?>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0">Notificaciones administrativas</h2>
                        <a href="<?= Helper::baseUrl('notificaciones'); ?>" class="btn btn-sm btn-outline-secondary">
                            Ver todas
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($notificaciones)): ?>
                            <p class="text-muted mb-0">No hay notificaciones recientes.</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($notificaciones as $notif): ?>
                                    <?php
                                    $fechaN = $notif['FechaNotif'] ?? null;
                                    $fechaNFmt = (
                                        !empty($fechaN) && strtotime((string) $fechaN) !== false
                                    )
                                        ? date('d/m/Y H:i', strtotime((string) $fechaN))
                                        : '';
                                    ?>
                                    <li class="list-group-item px-0">
                                        <div class="fw-semibold"><?= $esc($notif['TituloNotif'] ?? 'Notificación'); ?></div>
                                        <div class="small text-muted"><?= $esc($notif['MensajeNotif'] ?? ''); ?></div>
                                        <?php if ($fechaNFmt !== ''): ?>
                                            <div class="small text-muted mt-1"><?= $esc($fechaNFmt); ?></div>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>

</div>
