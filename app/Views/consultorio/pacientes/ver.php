<?php

use App\Helpers\Helper;

$ficha = is_array($ficha ?? null) ? $ficha : [];
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$clvPac = (string) ($ficha['ClvPac'] ?? '');
$nombre = trim(implode(' ', array_filter([
    (string) ($ficha['NombrePer'] ?? ''),
    (string) ($ficha['ApPatPer'] ?? ''),
    (string) ($ficha['ApMatPer'] ?? ''),
])));

?>

<section class="consultorio-psicologos">
    <div class="consultorio-page-header d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <span class="consultorio-page-eyebrow">Ficha administrativa</span>
            <h1><?= $esc($nombre !== '' ? $nombre : $clvPac); ?></h1>
            <p>Solo datos administrativos. Sin contenido clínico.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a
                class="btn btn-outline-secondary"
                href="<?= Helper::baseUrl('consultorio/pacientes'); ?>"
            >Volver</a>
            <a
                class="btn agenda-filter-button"
                href="<?= Helper::baseUrl('consultorio/pacientes/editar/' . rawurlencode($clvPac)); ?>"
            >Editar</a>
        </div>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= $esc($success); ?></div>
    <?php endif; ?>
    <?php if (!empty($warning)): ?>
        <div class="alert alert-warning"><?= $esc($warning); ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= $esc($error); ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="consultorio-dashboard-panel h-100">
                <h2 class="h5">Datos de cuenta</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-5">ClvPac</dt>
                    <dd class="col-sm-7"><?= $esc($clvPac); ?></dd>
                    <dt class="col-sm-5">ClvUsu</dt>
                    <dd class="col-sm-7"><?= $esc($ficha['ClvUsu'] ?? ''); ?></dd>
                    <dt class="col-sm-5">Nombre</dt>
                    <dd class="col-sm-7"><?= $esc($nombre); ?></dd>
                    <dt class="col-sm-5">Correo</dt>
                    <dd class="col-sm-7"><?= $esc($ficha['CorreoUsu'] ?? ''); ?></dd>
                    <dt class="col-sm-5">Teléfono</dt>
                    <dd class="col-sm-7"><?= $esc($ficha['TelefonoUsu'] ?? ''); ?></dd>
                    <dt class="col-sm-5">EstadoUsu</dt>
                    <dd class="col-sm-7"><?= (int) ($ficha['EstadoUsu'] ?? 0) === 1 ? 'Activo' : 'Inactivo'; ?></dd>
                    <dt class="col-sm-5">Estado paciente</dt>
                    <dd class="col-sm-7"><?= (int) ($ficha['EstadoActivoPac'] ?? 0) === 1 ? 'Activo' : 'Inactivo'; ?></dd>
                    <dt class="col-sm-5">Requiere cambio contraseña</dt>
                    <dd class="col-sm-7"><?= (int) ($ficha['RequiereCambioContrasena'] ?? 0) === 1 ? 'Sí' : 'No'; ?></dd>
                    <dt class="col-sm-5">Fecha de registro</dt>
                    <dd class="col-sm-7"><?= $esc($ficha['FechaRegistroPac'] ?? ''); ?></dd>
                </dl>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="consultorio-dashboard-panel h-100">
                <h2 class="h5">Relación con el consultorio</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-6">Citas en este consultorio</dt>
                    <dd class="col-sm-6"><?= (int) ($ficha['totalCitasConsultorio'] ?? 0); ?></dd>
                    <dt class="col-sm-6">Citas globales</dt>
                    <dd class="col-sm-6"><?= (int) ($ficha['totalCitasGlobales'] ?? 0); ?></dd>
                    <dt class="col-sm-6">Expediente registrado</dt>
                    <dd class="col-sm-6"><?= !empty($ficha['tieneExpediente']) ? 'Sí' : 'No'; ?></dd>
                    <dt class="col-sm-6">Seguimientos (conteo)</dt>
                    <dd class="col-sm-6"><?= (int) ($ficha['totalSeguimientos'] ?? 0); ?></dd>
                    <dt class="col-sm-6">Diagnósticos (conteo)</dt>
                    <dd class="col-sm-6"><?= (int) ($ficha['totalDiagnosticos'] ?? 0); ?></dd>
                    <dt class="col-sm-6">Puede eliminarse</dt>
                    <dd class="col-sm-6"><?= !empty($ficha['puedeEliminarFisicamente']) ? 'Sí' : 'No'; ?></dd>
                </dl>
                <p class="small text-muted mt-3 mb-0">
                    El consultorio no tiene acceso al expediente clínico ni a notas de seguimiento.
                </p>
            </div>
        </div>
    </div>
</section>
