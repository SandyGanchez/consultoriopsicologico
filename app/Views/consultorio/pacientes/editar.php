<?php

use App\Core\Session;
use App\Helpers\Helper;

$ficha = is_array($ficha ?? null) ? $ficha : [];
$csrf = Session::csrfToken();
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$clvPac = (string) ($ficha['ClvPac'] ?? '');

?>

<section class="consultorio-psicologos">
    <div class="consultorio-page-header">
        <span class="consultorio-page-eyebrow">Edición administrativa</span>
        <h1>Editar paciente</h1>
        <p>
            Solo nombre, apellidos y teléfono. El correo se gestiona con el flujo seguro
            de la cuenta del paciente (CuentaService); no se modifica aquí.
        </p>
    </div>

    <div class="consultorio-dashboard-panel" style="max-width:640px;">
        <form method="POST" action="<?= Helper::baseUrl('consultorio/pacientes/actualizar'); ?>">
            <input type="hidden" name="csrf_token" value="<?= $esc($csrf); ?>">
            <input type="hidden" name="ClvPac" value="<?= $esc($clvPac); ?>">

            <div class="mb-3">
                <label class="form-label">ClvPac</label>
                <input type="text" class="form-control" value="<?= $esc($clvPac); ?>" disabled>
            </div>

            <div class="mb-3">
                <label class="form-label" for="NombrePer">Nombre</label>
                <input
                    type="text"
                    class="form-control"
                    id="NombrePer"
                    name="NombrePer"
                    required
                    maxlength="50"
                    value="<?= $esc($ficha['NombrePer'] ?? ''); ?>"
                >
            </div>

            <div class="mb-3">
                <label class="form-label" for="ApPatPer">Apellido paterno</label>
                <input
                    type="text"
                    class="form-control"
                    id="ApPatPer"
                    name="ApPatPer"
                    required
                    maxlength="50"
                    value="<?= $esc($ficha['ApPatPer'] ?? ''); ?>"
                >
            </div>

            <div class="mb-3">
                <label class="form-label" for="ApMatPer">Apellido materno</label>
                <input
                    type="text"
                    class="form-control"
                    id="ApMatPer"
                    name="ApMatPer"
                    maxlength="50"
                    value="<?= $esc($ficha['ApMatPer'] ?? ''); ?>"
                >
            </div>

            <div class="mb-3">
                <label class="form-label">Correo</label>
                <input
                    type="email"
                    class="form-control"
                    value="<?= $esc($ficha['CorreoUsu'] ?? ''); ?>"
                    disabled
                >
                <div class="form-text">Solo lectura en este módulo.</div>
            </div>

            <div class="mb-4">
                <label class="form-label" for="TelefonoUsu">Teléfono</label>
                <input
                    type="text"
                    class="form-control"
                    id="TelefonoUsu"
                    name="TelefonoUsu"
                    required
                    maxlength="15"
                    value="<?= $esc($ficha['TelefonoUsu'] ?? ''); ?>"
                >
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn agenda-filter-button">Guardar</button>
                <a
                    class="btn btn-outline-secondary"
                    href="<?= Helper::baseUrl('consultorio/pacientes/ver/' . rawurlencode($clvPac)); ?>"
                >Cancelar</a>
            </div>
        </form>
    </div>
</section>
