<?php

use App\Core\Session;
use App\Helpers\Helper;
use App\Services\EdadService;

$datos = is_array($datos ?? null) ? $datos : [];
$errores = is_array($errores ?? null) ? $errores : [];
$limitesEdad = (new EdadService())->limitesInput('adulto');

$valor = static function (string $campo, string $default = '') use ($datos): string {
    return htmlspecialchars(
        (string) ($datos[$campo] ?? $default),
        ENT_QUOTES,
        'UTF-8'
    );
};

$clase = static function (string $campo) use ($errores): string {
    return isset($errores[$campo]) && $errores[$campo] !== ''
        ? 'is-invalid'
        : '';
};

$error = static function (string $campo) use ($errores): void {
    if (empty($errores[$campo])) {
        return;
    }

    echo '<div class="invalid-feedback">'
        . htmlspecialchars((string) $errores[$campo], ENT_QUOTES, 'UTF-8')
        . '</div>';
};

$genero = (string) ($datos['generoResponsable'] ?? '');
?>

<div class="container py-4">

    <div class="mb-4">
        <a
            href="<?= Helper::baseUrl('administrador'); ?>"
            class="text-decoration-none"
        >
            ← Regresar al panel
        </a>
    </div>

    <h1 class="h3 mb-1">Configurar consultorio</h1>
    <p class="text-muted mb-4">
        Alta única de la cuenta del consultorio de esta instalación.
        El responsable completará la configuración comercial y operativa
        tras activar su acceso.
    </p>

    <?php if (!empty($errores['general'])): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars((string) $errores['general'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form
        method="POST"
        action="<?= Helper::baseUrl('administrador/consultorio/guardar'); ?>"
        novalidate
        id="formConsultorioAdmin"
    >
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(Session::csrfToken(), ENT_QUOTES, 'UTF-8'); ?>"
        >

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h2 class="h5 mb-0">Consultorio</h2>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="NombreCons" class="form-label">
                            Nombre del consultorio <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            id="NombreCons"
                            name="NombreCons"
                            class="form-control <?= $clase('NombreCons'); ?>"
                            value="<?= $valor('nombreConsultorio'); ?>"
                            maxlength="100"
                            required
                        >
                        <?php $error('NombreCons'); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h2 class="h5 mb-0">Ubicación mínima</h2>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    Requerida por la base de datos (<code>direccion</code>).
                    El consultorio podrá completar calle y mapa después.
                </p>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="PaisDir" class="form-label">País *</label>
                        <input
                            type="text"
                            id="PaisDir"
                            name="PaisDir"
                            class="form-control <?= $clase('PaisDir'); ?>"
                            value="<?= $valor('pais', 'México'); ?>"
                            required
                        >
                        <?php $error('PaisDir'); ?>
                    </div>
                    <div class="col-md-4">
                        <label for="EstadoDir" class="form-label">Estado *</label>
                        <input
                            type="text"
                            id="EstadoDir"
                            name="EstadoDir"
                            class="form-control <?= $clase('EstadoDir'); ?>"
                            value="<?= $valor('estado'); ?>"
                            required
                        >
                        <?php $error('EstadoDir'); ?>
                    </div>
                    <div class="col-md-4">
                        <label for="MunicipioDir" class="form-label">Municipio *</label>
                        <input
                            type="text"
                            id="MunicipioDir"
                            name="MunicipioDir"
                            class="form-control <?= $clase('MunicipioDir'); ?>"
                            value="<?= $valor('municipio'); ?>"
                            required
                        >
                        <?php $error('MunicipioDir'); ?>
                    </div>
                    <div class="col-md-6">
                        <label for="ColoniaDir" class="form-label">Colonia *</label>
                        <input
                            type="text"
                            id="ColoniaDir"
                            name="ColoniaDir"
                            class="form-control <?= $clase('ColoniaDir'); ?>"
                            value="<?= $valor('colonia'); ?>"
                            required
                        >
                        <?php $error('ColoniaDir'); ?>
                    </div>
                    <div class="col-md-6">
                        <label for="CodPostDir" class="form-label">Código postal *</label>
                        <input
                            type="text"
                            id="CodPostDir"
                            name="CodPostDir"
                            class="form-control <?= $clase('CodPostDir'); ?>"
                            value="<?= $valor('codigoPostal'); ?>"
                            maxlength="5"
                            inputmode="numeric"
                            required
                        >
                        <?php $error('CodPostDir'); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h2 class="h5 mb-0">Responsable y cuenta de acceso</h2>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    Se enviará un enlace seguro de activación. El administrador
                    no define ni conoce la contraseña.
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="NombrePer" class="form-label">Nombre *</label>
                        <input
                            type="text"
                            id="NombrePer"
                            name="NombrePer"
                            class="form-control <?= $clase('NombrePer'); ?>"
                            value="<?= $valor('nombreResponsable'); ?>"
                            required
                        >
                        <?php $error('NombrePer'); ?>
                    </div>
                    <div class="col-md-4">
                        <label for="ApPatPer" class="form-label">Apellido paterno *</label>
                        <input
                            type="text"
                            id="ApPatPer"
                            name="ApPatPer"
                            class="form-control <?= $clase('ApPatPer'); ?>"
                            value="<?= $valor('apellidoPaternoResponsable'); ?>"
                            required
                        >
                        <?php $error('ApPatPer'); ?>
                    </div>
                    <div class="col-md-4">
                        <label for="ApMatPer" class="form-label">Apellido materno *</label>
                        <input
                            type="text"
                            id="ApMatPer"
                            name="ApMatPer"
                            class="form-control <?= $clase('ApMatPer'); ?>"
                            value="<?= $valor('apellidoMaternoResponsable'); ?>"
                            required
                        >
                        <?php $error('ApMatPer'); ?>
                    </div>
                    <div class="col-md-6">
                        <label for="FechaNacimiento" class="form-label">Fecha de nacimiento *</label>
                        <input
                            type="date"
                            id="FechaNacimiento"
                            name="FechaNacimiento"
                            class="form-control <?= $clase('FechaNacimiento'); ?>"
                            value="<?= $valor('fechaNacimientoResponsable'); ?>"
                            min="<?= htmlspecialchars($limitesEdad['min'], ENT_QUOTES, 'UTF-8'); ?>"
                            max="<?= htmlspecialchars($limitesEdad['max'], ENT_QUOTES, 'UTF-8'); ?>"
                            required
                        >
                        <div class="form-text">Debes tener al menos 18 años.</div>
                        <?php $error('FechaNacimiento'); ?>
                    </div>
                    <div class="col-md-6">
                        <label for="GeneroPer" class="form-label">Género *</label>
                        <select
                            id="GeneroPer"
                            name="GeneroPer"
                            class="form-select <?= $clase('GeneroPer'); ?>"
                            required
                        >
                            <option value="">Selecciona una opción</option>
                            <option value="Femenino" <?= $genero === 'Femenino' ? 'selected' : ''; ?>>Femenino</option>
                            <option value="Masculino" <?= $genero === 'Masculino' ? 'selected' : ''; ?>>Masculino</option>
                            <option value="Otro" <?= $genero === 'Otro' ? 'selected' : ''; ?>>Otro</option>
                        </select>
                        <?php $error('GeneroPer'); ?>
                    </div>
                    <div class="col-md-6">
                        <label for="CorreoUsu" class="form-label">Correo de acceso *</label>
                        <input
                            type="email"
                            id="CorreoUsu"
                            name="CorreoUsu"
                            class="form-control <?= $clase('CorreoUsu'); ?>"
                            value="<?= $valor('correoResponsable'); ?>"
                            required
                        >
                        <?php $error('CorreoUsu'); ?>
                    </div>
                    <div class="col-md-6">
                        <label for="TelefonoUsu" class="form-label">Teléfono *</label>
                        <input
                            type="tel"
                            id="TelefonoUsu"
                            name="TelefonoUsu"
                            class="form-control <?= $clase('TelefonoUsu'); ?>"
                            value="<?= $valor('telefonoResponsable'); ?>"
                            maxlength="10"
                            inputmode="numeric"
                            required
                        >
                        <?php $error('TelefonoUsu'); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button
                type="submit"
                class="btn btn-primary"
                onclick="this.disabled=true;this.form.submit();"
            >
                Configurar consultorio
            </button>
            <a
                href="<?= Helper::baseUrl('administrador'); ?>"
                class="btn btn-outline-secondary"
            >
                Cancelar
            </a>
        </div>
    </form>
</div>
