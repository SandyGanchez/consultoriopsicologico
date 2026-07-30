<?php

use App\Helpers\Helper;

$consultorio = $consultorio ?? [];
$datos = $datos ?? [];
$errores = $errores ?? [];

$modoEdicion = !empty(
    $consultorio['ClvCons']
);

$clvCons = $consultorio['ClvCons'] ?? '';

/*
=========================================
      DATOS DEL CONSULTORIO
=========================================
*/

$nombreCons =
    $datos['nombreConsultorio']
    ?? $consultorio['NombreCons']
    ?? '';

$sloganCons =
    $datos['slogan']
    ?? $consultorio['Slogan']
    ?? '';

$descripcionCons =
    $datos['descripcion']
    ?? $consultorio['Descripcion']
    ?? '';

$telefonoCons =
    $datos['telefonoConsultorio']
    ?? $consultorio['TelefonoCons']
    ?? '';

$correoCons =
    $datos['correoConsultorio']
    ?? $consultorio['CorreoElectronico']
    ?? '';

$limiteCancHoras =
    $datos['limiteCancelacion']
    ?? $consultorio['LimiteCancHoras']
    ?? 24;

/*
=========================================
              DIRECCIÓN
=========================================
*/

$paisDir =
    $datos['pais']
    ?? $consultorio['PaisDir']
    ?? 'México';

$estadoDir =
    $datos['estado']
    ?? $consultorio['EstadoDir']
    ?? '';

$municipioDir =
    $datos['municipio']
    ?? $consultorio['MunicipioDir']
    ?? '';

$coloniaDir =
    $datos['colonia']
    ?? $consultorio['ColoniaDir']
    ?? '';

$calleDir =
    $datos['calle']
    ?? $consultorio['CalleDir']
    ?? '';

$codPostDir =
    $datos['codigoPostal']
    ?? $consultorio['CodPostDir']
    ?? '';

$numExtDir =
    $datos['numeroExterior']
    ?? $consultorio['NumExtDir']
    ?? '';

$numIntDir =
    $datos['numeroInterior']
    ?? $consultorio['NumIntDir']
    ?? '';

/*
=========================================
      RESPONSABLE DEL CONSULTORIO
=========================================
*/

$nombrePer =
    $datos['nombreResponsable']
    ?? $consultorio['NombrePer']
    ?? '';

$apPatPer =
    $datos['apellidoPaternoResponsable']
    ?? $consultorio['ApPatPer']
    ?? '';

$apMatPer =
    $datos['apellidoMaternoResponsable']
    ?? $consultorio['ApMatPer']
    ?? '';

$fechaNacimiento =
    $datos['fechaNacimientoResponsable']
    ?? $consultorio['FechaNacimiento']
    ?? '';

$generoPer =
    $datos['generoResponsable']
    ?? $consultorio['GeneroPer']
    ?? '';

/*
=========================================
          CUENTA DEL RESPONSABLE
=========================================
*/

$correoUsu =
    $datos['correoResponsable']
    ?? $consultorio['CorreoUsu']
    ?? '';

$telefonoUsu =
    $datos['telefonoResponsable']
    ?? $consultorio['TelefonoUsu']
    ?? '';

$titulo = $modoEdicion
    ? 'Editar consultorio'
    : 'Registrar consultorio';

$descripcionTitulo = $modoEdicion
    ? 'Actualiza la información del consultorio y de su responsable.'
    : 'Registra el consultorio, su dirección y la cuenta del responsable.';

$accion = $modoEdicion
    ? Helper::baseUrl(
        'administrador/consultorios/actualizar/'
        . rawurlencode($clvCons)
    )
    : Helper::baseUrl(
        'administrador/consultorios/guardar'
    );

function valorFormulario(
    mixed $valor
): string {
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

function tieneError(
    array $errores,
    string $campo
): bool {
    return isset($errores[$campo])
        && $errores[$campo] !== '';
}

function mostrarError(
    array $errores,
    string $campo
): void {
    if (!tieneError($errores, $campo)) {
        return;
    }

    echo '<div class="invalid-feedback">'
        . htmlspecialchars(
            (string) $errores[$campo],
            ENT_QUOTES,
            'UTF-8'
        )
        . '</div>';
}

?>

<div class="container py-4">

    <div
        class="d-flex flex-column flex-md-row
               justify-content-between align-items-md-center
               gap-3 mb-4"
    >
        <div>
            <h1 class="h3 mb-1">
                <?= htmlspecialchars(
                    $titulo,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </h1>

            <p class="text-muted mb-0">
                <?= htmlspecialchars(
                    $descripcionTitulo,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </p>
        </div>

        <a
            href="<?= Helper::baseUrl(
                'administrador/consultorios'
            ); ?>"
            class="btn btn-outline-secondary"
        >
            Volver
        </a>
    </div>

    <?php if (!empty($errores['general'])): ?>

        <div
            class="alert alert-danger"
            role="alert"
        >
            <?= htmlspecialchars(
                $errores['general'],
                ENT_QUOTES,
                'UTF-8'
            ); ?>
        </div>

    <?php endif; ?>

    <form
        method="POST"
        action="<?= htmlspecialchars(
            $accion,
            ENT_QUOTES,
            'UTF-8'
        ); ?>"
        novalidate
    >

        <?php if ($modoEdicion): ?>

            <input
                type="hidden"
                name="ClvCons"
                value="<?= htmlspecialchars(
                    $clvCons,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >

        <?php endif; ?>

        <!-- INFORMACIÓN DEL CONSULTORIO -->

       <div class="card shadow-sm border-0 mb-4">

    <div class="card-header bg-white py-3">
        <h2 class="h5 mb-0">
            Información del consultorio
        </h2>
    </div>

    <div class="card-body">

        <div class="row g-3">

            <div class="col-12 col-md-8">

                <label
                    for="NombreCons"
                    class="form-label"
                >
                    Nombre del consultorio
                    <span class="text-danger">*</span>
                </label>

                <input
                    type="text"
                    id="NombreCons"
                    name="NombreCons"
                    class="form-control <?= tieneError(
                        $errores,
                        'NombreCons'
                    )
                        ? 'is-invalid'
                        : '';
                    ?>"
                    value="<?= valorFormulario($nombreCons); ?>"
                    maxlength="120"
                    required
                >

                <?php mostrarError(
                    $errores,
                    'NombreCons'
                ); ?>

            </div>

            <div class="col-12 col-md-4">

                <label
                    for="LimiteCancHoras"
                    class="form-label"
                >
                    Límite de cancelación
                </label>

                <div class="input-group">

                    <input
                        type="number"
                        id="LimiteCancHoras"
                        name="LimiteCancHoras"
                        class="form-control <?= tieneError(
                            $errores,
                            'LimiteCancHoras'
                        )
                            ? 'is-invalid'
                            : '';
                        ?>"
                        value="<?= valorFormulario($limiteCancHoras); ?>"
                        min="0"
                        max="168"
                    >

                    <span class="input-group-text">
                        horas
                    </span>

                    <?php mostrarError(
                        $errores,
                        'LimiteCancHoras'
                    ); ?>

                </div>

                <div class="form-text">
                    Tiempo mínimo previo para que un paciente pueda cancelar.
                </div>

            </div>

            <div class="col-12">

                <label
                    for="Slogan"
                    class="form-label"
                >
                    Eslogan
                </label>

                <input
                    type="text"
                    id="Slogan"
                    name="Slogan"
                    class="form-control <?= tieneError(
                        $errores,
                        'Slogan'
                    )
                        ? 'is-invalid'
                        : '';
                    ?>"
                    value="<?= valorFormulario($sloganCons); ?>"
                    maxlength="180"
                >

                <?php mostrarError(
                    $errores,
                    'Slogan'
                ); ?>

            </div>

            <div class="col-12">

                <label
                    for="Descripcion"
                    class="form-label"
                >
                    Descripción
                </label>

                <textarea
                    id="Descripcion"
                    name="Descripcion"
                    class="form-control <?= tieneError(
                        $errores,
                        'Descripcion'
                    )
                        ? 'is-invalid'
                        : '';
                    ?>"
                    rows="4"
                    maxlength="1000"
                ><?= valorFormulario($descripcionCons); ?></textarea>

                <?php mostrarError(
                    $errores,
                    'Descripcion'
                ); ?>

            </div>

            <div class="col-12 col-md-6">

                <label
                    for="CorreoElectronico"
                    class="form-label"
                >
                    Correo del consultorio
                    <span class="text-danger">*</span>
                </label>

                <input
                    type="email"
                    id="CorreoElectronico"
                    name="CorreoElectronico"
                    class="form-control <?= tieneError(
                        $errores,
                        'CorreoElectronico'
                    )
                        ? 'is-invalid'
                        : '';
                    ?>"
                    value="<?= valorFormulario($correoCons); ?>"
                    maxlength="120"
                    required
                >

                <?php mostrarError(
                    $errores,
                    'CorreoElectronico'
                ); ?>

            </div>

            <div class="col-12 col-md-6">

                <label
                    for="TelefonoCons"
                    class="form-label"
                >
                    Teléfono del consultorio
                    <span class="text-danger">*</span>
                </label>

                <input
                    type="tel"
                    id="TelefonoCons"
                    name="TelefonoCons"
                    class="form-control <?= tieneError(
                        $errores,
                        'TelefonoCons'
                    )
                        ? 'is-invalid'
                        : '';
                    ?>"
                    value="<?= valorFormulario($telefonoCons); ?>"
                    maxlength="10"
                    pattern="[0-9]{10}"
                    inputmode="numeric"
                    required
                >

                <?php mostrarError(
                    $errores,
                    'TelefonoCons'
                ); ?>

                <div class="form-text">
                    Debe contener 10 dígitos.
                </div>

            </div>

        </div>

    </div>

</div>
        <!-- DIRECCIÓN -->

        <div class="card shadow-sm border-0 mb-4">

            <div class="card-header bg-white py-3">
                <h2 class="h5 mb-0">
                    Dirección
                </h2>
            </div>

            <div class="card-body">

                <div class="row g-3">

                    <div class="col-12 col-md-4">

                        <label
                            for="PaisDir"
                            class="form-label"
                        >
                            País
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            id="PaisDir"
                            name="PaisDir"
                            class="form-control <?= tieneError(
                                $errores,
                                'PaisDir'
                            )
                                ? 'is-invalid'
                                : ''
                            ?>"
                            value="<?= valorFormulario($paisDir) ?>"
                            maxlength="50"
                            required
                        >

                        <?php mostrarError(
                            $errores,
                            'PaisDir'
                        ); ?>

                    </div>

                    <div class="col-12 col-md-4">

                        <label
                            for="EstadoDir"
                            class="form-label"
                        >
                            Estado
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            id="EstadoDir"
                            name="EstadoDir"
                            class="form-control <?= tieneError(
                                $errores,
                                'EstadoDir'
                            )
                                ? 'is-invalid'
                                : ''
                            ?>"
                            value="<?= valorFormulario($estadoDir) ?>"
                            maxlength="50"
                            required
                        >

                        <?php mostrarError(
                            $errores,
                            'EstadoDir'
                        ); ?>

                    </div>

                    <div class="col-12 col-md-4">

                        <label
                            for="MunicipioDir"
                            class="form-label"
                        >
                            Municipio
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            id="MunicipioDir"
                            name="MunicipioDir"
                            class="form-control <?= tieneError(
                                $errores,
                                'MunicipioDir'
                            )
                                ? 'is-invalid'
                                : ''
                            ?>"
                            value="<?= valorFormulario($municipioDir) ?>"
                            maxlength="50"
                            required
                        >

                        <?php mostrarError(
                            $errores,
                            'MunicipioDir'
                        ); ?>

                    </div>

                    <div class="col-12 col-md-5">

                        <label
                            for="ColoniaDir"
                            class="form-label"
                        >
                            Colonia
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            id="ColoniaDir"
                            name="ColoniaDir"
                            class="form-control <?= tieneError(
                                $errores,
                                'ColoniaDir'
                            )
                                ? 'is-invalid'
                                : ''
                            ?>"
                            value="<?= valorFormulario($coloniaDir) ?>"
                            maxlength="50"
                            required
                        >

                        <?php mostrarError(
                            $errores,
                            'ColoniaDir'
                        ); ?>

                    </div>

                    <div class="col-12 col-md-5">

                        <label
                            for="CalleDir"
                            class="form-label"
                        >
                            Calle
                        </label>

                        <input
                            type="text"
                            id="CalleDir"
                            name="CalleDir"
                            class="form-control <?= tieneError(
                                $errores,
                                'CalleDir'
                            )
                                ? 'is-invalid'
                                : ''
                            ?>"
                            value="<?= valorFormulario($calleDir) ?>"
                            maxlength="70"
                        >

                        <?php mostrarError(
                            $errores,
                            'CalleDir'
                        ); ?>

                    </div>

                    <div class="col-12 col-md-2">

                        <label
                            for="CodPostDir"
                            class="form-label"
                        >
                            Código postal
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            id="CodPostDir"
                            name="CodPostDir"
                            class="form-control <?= tieneError(
                                $errores,
                                'CodPostDir'
                            )
                                ? 'is-invalid'
                                : ''
                            ?>"
                            value="<?= valorFormulario($codPostDir) ?>"
                            maxlength="5"
                            pattern="[0-9]{5}"
                            inputmode="numeric"
                            required
                        >

                        <?php mostrarError(
                            $errores,
                            'CodPostDir'
                        ); ?>

                    </div>

                    <div class="col-12 col-md-3">

                        <label
                            for="NumExtDir"
                            class="form-label"
                        >
                            Número exterior
                        </label>

                        <input
                            type="text"
                            id="NumExtDir"
                            name="NumExtDir"
                            class="form-control <?= tieneError(
                                $errores,
                                'NumExtDir'
                            )
                                ? 'is-invalid'
                                : ''
                            ?>"
                            value="<?= valorFormulario($numExtDir) ?>"
                            maxlength="10"
                        >

                        <?php mostrarError(
                            $errores,
                            'NumExtDir'
                        ); ?>

                    </div>

                    <div class="col-12 col-md-3">

                        <label
                            for="NumIntDir"
                            class="form-label"
                        >
                            Número interior
                        </label>

                        <input
                            type="text"
                            id="NumIntDir"
                            name="NumIntDir"
                            class="form-control <?= tieneError(
                                $errores,
                                'NumIntDir'
                            )
                                ? 'is-invalid'
                                : ''
                            ?>"
                            value="<?= valorFormulario($numIntDir) ?>"
                            maxlength="10"
                        >

                        <?php mostrarError(
                            $errores,
                            'NumIntDir'
                        ); ?>

                    </div>

                </div>

            </div>

        </div>

      <!-- RESPONSABLE -->

<div class="card shadow-sm border-0 mb-4">

    <div class="card-header bg-white py-3">
        <h2 class="h5 mb-0">
            Responsable del consultorio
        </h2>
    </div>

    <div class="card-body">

        <div class="row g-3">

            <div class="col-12 col-md-4">

                <label
                    for="NombrePer"
                    class="form-label"
                >
                    Nombre
                    <span class="text-danger">*</span>
                </label>

                <input
                    type="text"
                    id="NombrePer"
                    name="NombrePer"
                    class="form-control <?= tieneError(
                        $errores,
                        'NombrePer'
                    )
                        ? 'is-invalid'
                        : '';
                    ?>"
                    value="<?= valorFormulario($nombrePer); ?>"
                    maxlength="50"
                    required
                >

                <?php mostrarError(
                    $errores,
                    'NombrePer'
                ); ?>

            </div>

            <div class="col-12 col-md-4">

                <label
                    for="ApPatPer"
                    class="form-label"
                >
                    Apellido paterno
                    <span class="text-danger">*</span>
                </label>

                <input
                    type="text"
                    id="ApPatPer"
                    name="ApPatPer"
                    class="form-control <?= tieneError(
                        $errores,
                        'ApPatPer'
                    )
                        ? 'is-invalid'
                        : '';
                    ?>"
                    value="<?= valorFormulario($apPatPer); ?>"
                    maxlength="50"
                    required
                >

                <?php mostrarError(
                    $errores,
                    'ApPatPer'
                ); ?>

            </div>

            <div class="col-12 col-md-4">

                <label
                    for="ApMatPer"
                    class="form-label"
                >
                    Apellido materno
                </label>

                <input
                    type="text"
                    id="ApMatPer"
                    name="ApMatPer"
                    class="form-control <?= tieneError(
                        $errores,
                        'ApMatPer'
                    )
                        ? 'is-invalid'
                        : '';
                    ?>"
                    value="<?= valorFormulario($apMatPer); ?>"
                    maxlength="50"
                >

                <?php mostrarError(
                    $errores,
                    'ApMatPer'
                ); ?>

            </div>

            <div class="col-12 col-md-6">

                <label
                    for="FechaNacimiento"
                    class="form-label"
                >
                    Fecha de nacimiento
                </label>

                <input
                    type="date"
                    id="FechaNacimiento"
                    name="FechaNacimiento"
                    class="form-control <?= tieneError(
                        $errores,
                        'FechaNacimiento'
                    )
                        ? 'is-invalid'
                        : '';
                    ?>"
                    value="<?= valorFormulario($fechaNacimiento); ?>"
                    max="<?= htmlspecialchars(
                        date('Y-m-d'),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                    required
                >

                <?php mostrarError(
                    $errores,
                    'FechaNacimiento'
                ); ?>

            </div>

            <div class="col-12 col-md-6">

                <label
                    for="GeneroPer"
                    class="form-label"
                >
                    Género
                </label>

                <?php

                $generoSeleccionado = trim(
                    (string) $generoPer
                );

                ?>

                <select
                    id="GeneroPer"
                    name="GeneroPer"
                    class="form-select <?= tieneError(
                        $errores,
                        'GeneroPer'
                    )
                        ? 'is-invalid'
                        : '';
                    ?>"
                    required
                >
                    <option value="">
                        Selecciona una opción
                    </option>

                    <option
                        value="Femenino"
                        <?= $generoSeleccionado === 'Femenino'
                            ? 'selected'
                            : '';
                        ?>
                    >
                        Femenino
                    </option>

                    <option
                        value="Masculino"
                        <?= $generoSeleccionado === 'Masculino'
                            ? 'selected'
                            : '';
                        ?>
                    >
                        Masculino
                    </option>

                    <option
                        value="Otro"
                        <?= $generoSeleccionado === 'Otro'
                            ? 'selected'
                            : '';
                        ?>
                    >
                        Otro
                    </option>
                </select>

                <?php mostrarError(
                    $errores,
                    'GeneroPer'
                ); ?>

            </div>

        </div>

    </div>

</div>
       <!-- CUENTA DE ACCESO -->

<div class="card shadow-sm border-0 mb-4">

    <div class="card-header bg-white py-3">
        <h2 class="h5 mb-0">
            Cuenta de acceso
        </h2>
    </div>

    <div class="card-body">

        <?php if (!$modoEdicion): ?>

            <div class="alert alert-info">
                El sistema generará una contraseña temporal.
                El responsable deberá cambiarla al iniciar sesión.
            </div>

        <?php endif; ?>

        <div class="row g-3">

            <div class="col-12 col-md-6">

                <label
                    for="CorreoUsu"
                    class="form-label"
                >
                    Correo de acceso
                    <span class="text-danger">*</span>
                </label>

                <input
                    type="email"
                    id="CorreoUsu"
                    name="CorreoUsu"
                    class="form-control <?= tieneError(
                        $errores,
                        'CorreoUsu'
                    )
                        ? 'is-invalid'
                        : '';
                    ?>"
                    value="<?= valorFormulario($correoUsu); ?>"
                    maxlength="120"
                    autocomplete="email"
                    required
                >

                <?php mostrarError(
                    $errores,
                    'CorreoUsu'
                ); ?>

            </div>

            <div class="col-12 col-md-6">

                <label
                    for="TelefonoUsu"
                    class="form-label"
                >
                    Teléfono del responsable
                    <span class="text-danger">*</span>
                </label>

                <input
                    type="tel"
                    id="TelefonoUsu"
                    name="TelefonoUsu"
                    class="form-control <?= tieneError(
                        $errores,
                        'TelefonoUsu'
                    )
                        ? 'is-invalid'
                        : '';
                    ?>"
                    value="<?= valorFormulario($telefonoUsu); ?>"
                    maxlength="10"
                    pattern="[0-9]{10}"
                    inputmode="numeric"
                    required
                >

                <?php mostrarError(
                    $errores,
                    'TelefonoUsu'
                ); ?>

                <div class="form-text">
                    Debe contener 10 dígitos.
                </div>

            </div>

        </div>

    </div>

</div>

<div
    class="d-flex flex-column flex-sm-row
           justify-content-end gap-2"
>

    <a
        href="<?= Helper::baseUrl(
            'administrador/consultorios'
        ); ?>"
        class="btn btn-outline-secondary"
    >
        Cancelar
    </a>

    <button
        type="submit"
        class="btn btn-primary"
    >
        <?= $modoEdicion
            ? 'Guardar cambios'
            : 'Registrar consultorio';
        ?>
    </button>

</div>

</form>

</div>