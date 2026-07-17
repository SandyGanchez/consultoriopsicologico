<?php

use App\Helpers\Helper;

$datos = $datos ?? [];
$errores = $errores ?? [];
$modoEdicion = $modoEdicion ?? false;

function valorCampo(array $datos, string $campo): string
{
    return htmlspecialchars(
        (string) ($datos[$campo] ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function claseInvalida(
    array $errores,
    string $campo
): string {
    return isset($errores[$campo])
        ? ' is-invalid'
        : '';
}

?>

<section class="consultorio-psicologo-form">

    <div class="consultorio-page-header">

        <span class="consultorio-page-eyebrow">
            Gestión de especialistas
        </span>

        <h1>
    <?= $modoEdicion
        ? 'Editar especialista'
        : 'Registrar especialista'; ?>
</h1>

        <p>
            Captura los datos personales, de contacto y
            profesionales del psicólogo.
        </p>

    </div>

    <?php if (!empty($errores['general'])): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert"
        >
            <i class="bi bi-exclamation-triangle-fill me-2"></i>

            <?= htmlspecialchars(
                $errores['general'],
                ENT_QUOTES,
                'UTF-8'
            ); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>
        </div>

    <?php endif; ?>

   <form
    action="<?= Helper::baseUrl(
        $modoEdicion
            ? 'consultorio/psicologos/actualizar'
            : 'consultorio/psicologos/guardar'
    ); ?>"
    method="POST"
    id="formPsicologo"
    novalidate
>
<?php if ($modoEdicion): ?>

    <input
        type="hidden"
        name="clvPsi"
        value="<?= valorCampo(
            $datos,
            'clvPsi'
        ); ?>"
    >

<?php endif; ?>

        <!-- Datos personales -->

        <div class="consultorio-dashboard-panel mb-4">

            <div class="psicologo-form-section-header">

                <div class="psicologo-form-icon">
                    <i class="bi bi-person-vcard"></i>
                </div>

                <div>
                    <h2>Datos personales</h2>

                    <p>
                        Información general del especialista.
                    </p>
                </div>

            </div>

            <div class="row g-4">

                <div class="col-12 col-md-4">

                    <label
                        for="nombre"
                        class="form-label"
                    >
                        Nombre(s)
                        <span class="text-danger">*</span>
                    </label>

                    <input
                        type="text"
                        name="nombre"
                        id="nombre"
                        class="form-control<?= claseInvalida(
                            $errores,
                            'nombre'
                        ); ?>"
                        value="<?= valorCampo(
                            $datos,
                            'nombre'
                        ); ?>"
                        maxlength="50"
                        autocomplete="given-name"
                        required
                    >

                    <?php if (isset($errores['nombre'])): ?>

                        <div class="invalid-feedback">
                            <?= htmlspecialchars(
                                $errores['nombre']
                            ); ?>
                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-12 col-md-4">

                    <label
                        for="apellidoPaterno"
                        class="form-label"
                    >
                        Apellido paterno
                        <span class="text-danger">*</span>
                    </label>

                    <input
                        type="text"
                        name="apellidoPaterno"
                        id="apellidoPaterno"
                        class="form-control<?= claseInvalida(
                            $errores,
                            'apellidoPaterno'
                        ); ?>"
                        value="<?= valorCampo(
                            $datos,
                            'apellidoPaterno'
                        ); ?>"
                        maxlength="50"
                        autocomplete="family-name"
                        required
                    >

                    <?php if (
                        isset($errores['apellidoPaterno'])
                    ): ?>

                        <div class="invalid-feedback">
                            <?= htmlspecialchars(
                                $errores['apellidoPaterno']
                            ); ?>
                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-12 col-md-4">

                    <label
                        for="apellidoMaterno"
                        class="form-label"
                    >
                        Apellido materno
                        <span class="text-danger">*</span>
                    </label>

                    <input
                        type="text"
                        name="apellidoMaterno"
                        id="apellidoMaterno"
                        class="form-control<?= claseInvalida(
                            $errores,
                            'apellidoMaterno'
                        ); ?>"
                        value="<?= valorCampo(
                            $datos,
                            'apellidoMaterno'
                        ); ?>"
                        maxlength="50"
                        required
                    >

                    <?php if (
                        isset($errores['apellidoMaterno'])
                    ): ?>

                        <div class="invalid-feedback">
                            <?= htmlspecialchars(
                                $errores['apellidoMaterno']
                            ); ?>
                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-12 col-md-6">

                    <label
                        for="fechaNacimiento"
                        class="form-label"
                    >
                        Fecha de nacimiento
                        <span class="text-danger">*</span>
                    </label>

                    <input
                        type="date"
                        name="fechaNacimiento"
                        id="fechaNacimiento"
                        class="form-control<?= claseInvalida(
                            $errores,
                            'fechaNacimiento'
                        ); ?>"
                        value="<?= valorCampo(
                            $datos,
                            'fechaNacimiento'
                        ); ?>"
                        max="<?= date('Y-m-d'); ?>"
                        required
                    >

                    <?php if (
                        isset($errores['fechaNacimiento'])
                    ): ?>

                        <div class="invalid-feedback">
                            <?= htmlspecialchars(
                                $errores['fechaNacimiento']
                            ); ?>
                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-12 col-md-6">

                    <label
                        for="genero"
                        class="form-label"
                    >
                        Género
                        <span class="text-danger">*</span>
                    </label>

                    <select
                        name="genero"
                        id="genero"
                        class="form-select<?= claseInvalida(
                            $errores,
                            'genero'
                        ); ?>"
                        required
                    >

                        <option value="">
                            Selecciona una opción
                        </option>

                        <option
                            value="Femenino"
                            <?= (
                                ($datos['genero'] ?? '') ===
                                'Femenino'
                            ) ? 'selected' : ''; ?>
                        >
                            Femenino
                        </option>

                        <option
                            value="Masculino"
                            <?= (
                                ($datos['genero'] ?? '') ===
                                'Masculino'
                            ) ? 'selected' : ''; ?>
                        >
                            Masculino
                        </option>

                        <option
                            value="Otro"
                            <?= (
                                ($datos['genero'] ?? '') ===
                                'Otro'
                            ) ? 'selected' : ''; ?>
                        >
                            Otro
                        </option>

                    </select>

                    <?php if (isset($errores['genero'])): ?>

                        <div class="invalid-feedback">
                            <?= htmlspecialchars(
                                $errores['genero']
                            ); ?>
                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <!-- Contacto -->

        <div class="consultorio-dashboard-panel mb-4">

            <div class="psicologo-form-section-header">

                <div class="psicologo-form-icon">
                    <i class="bi bi-envelope-at"></i>
                </div>

                <div>
                    <h2>Datos de contacto</h2>

                    <p>
                        El correo será utilizado para iniciar
                        sesión y recibir los datos de acceso.
                    </p>
                </div>

            </div>

            <div class="row g-4">

                <div class="col-12 col-md-7">

                    <label
                        for="correo"
                        class="form-label"
                    >
                        Correo electrónico
                        <span class="text-danger">*</span>
                    </label>

                    <div class="input-group">

                        <span class="input-group-text">
                            <i class="bi bi-envelope"></i>
                        </span>

                        <input
                            type="email"
                            name="correo"
                            id="correo"
                            class="form-control<?= claseInvalida(
                                $errores,
                                'correo'
                            ); ?>"
                            value="<?= valorCampo(
                                $datos,
                                'correo'
                            ); ?>"
                            maxlength="100"
                            autocomplete="email"
                            placeholder="especialista@correo.com"
                            required
                        >

                        <?php if (
                            isset($errores['correo'])
                        ): ?>

                            <div class="invalid-feedback">
                                <?= htmlspecialchars(
                                    $errores['correo']
                                ); ?>
                            </div>

                        <?php endif; ?>

                    </div>

                </div>

                <div class="col-12 col-md-5">

                    <label
                        for="telefono"
                        class="form-label"
                    >
                        Teléfono
                        <span class="text-danger">*</span>
                    </label>

                    <div class="input-group">

                        <span class="input-group-text">
                            <i class="bi bi-telephone"></i>
                        </span>

                        <input
                            type="tel"
                            name="telefono"
                            id="telefono"
                            class="form-control<?= claseInvalida(
                                $errores,
                                'telefono'
                            ); ?>"
                            value="<?= valorCampo(
                                $datos,
                                'telefono'
                            ); ?>"
                            maxlength="10"
                            minlength="10"
                            inputmode="numeric"
                            pattern="[0-9]{10}"
                            autocomplete="tel"
                            placeholder="7221234567"
                            required
                        >

                        <?php if (
                            isset($errores['telefono'])
                        ): ?>

                            <div class="invalid-feedback">
                                <?= htmlspecialchars(
                                    $errores['telefono']
                                ); ?>
                            </div>

                        <?php endif; ?>

                    </div>

                    <div class="form-text">
                        Debe contener exactamente 10 dígitos.
                    </div>

                </div>

            </div>

        </div>

        <!-- Datos profesionales -->

        <div class="consultorio-dashboard-panel mb-4">

            <div class="psicologo-form-section-header">

                <div class="psicologo-form-icon">
                    <i class="bi bi-award"></i>
                </div>

                <div>
                    <h2>Datos profesionales</h2>

                    <p>
                        Información que identifica la formación
                        y experiencia del especialista.
                    </p>
                </div>

            </div>

            <div class="row g-4">

                <div class="col-12 col-md-5">

                    <label
                        for="cedulaProfesional"
                        class="form-label"
                    >
                        Cédula profesional
                        <span class="text-danger">*</span>
                    </label>

                    <input
                        type="text"
                        name="cedulaProfesional"
                        id="cedulaProfesional"
                        class="form-control<?= claseInvalida(
                            $errores,
                            'cedulaProfesional'
                        ); ?>"
                        value="<?= valorCampo(
                            $datos,
                            'cedulaProfesional'
                        ); ?>"
                        maxlength="20"
                        required
                    >

                    <?php if (
                        isset($errores['cedulaProfesional'])
                    ): ?>

                        <div class="invalid-feedback">
                            <?= htmlspecialchars(
                                $errores[
                                    'cedulaProfesional'
                                ]
                            ); ?>
                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-12 col-md-7">

                    <label
                        for="especialidad"
                        class="form-label"
                    >
                        Especialidad
                        <span class="text-danger">*</span>
                    </label>

                    <input
                        type="text"
                        name="especialidad"
                        id="especialidad"
                        class="form-control<?= claseInvalida(
                            $errores,
                            'especialidad'
                        ); ?>"
                        value="<?= valorCampo(
                            $datos,
                            'especialidad'
                        ); ?>"
                        maxlength="100"
                        placeholder="Ej. Psicología clínica"
                        required
                    >

                    <?php if (
                        isset($errores['especialidad'])
                    ): ?>

                        <div class="invalid-feedback">
                            <?= htmlspecialchars(
                                $errores['especialidad']
                            ); ?>
                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-12">

                    <label
                        for="descripcionProfesional"
                        class="form-label"
                    >
                        Descripción profesional
                    </label>

                    <textarea
                        name="descripcionProfesional"
                        id="descripcionProfesional"
                        class="form-control<?= claseInvalida(
                            $errores,
                            'descripcionProfesional'
                        ); ?>"
                        rows="5"
                        maxlength="1000"
                        placeholder="Describe su experiencia, enfoque terapéutico y áreas de atención."
                    ><?= valorCampo(
                        $datos,
                        'descripcionProfesional'
                    ); ?></textarea>

                    <div
                        class="d-flex justify-content-between form-text"
                    >
                        <span>
                            Este texto podrá mostrarse en la
                            página pública.
                        </span>

                        <span id="contadorDescripcion">
                            0 / 1000
                        </span>
                    </div>

                    <?php if (
                        isset(
                            $errores[
                                'descripcionProfesional'
                            ]
                        )
                    ): ?>

                        <div class="invalid-feedback d-block">
                            <?= htmlspecialchars(
                                $errores[
                                    'descripcionProfesional'
                                ]
                            ); ?>
                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <!-- Visibilidad y acceso -->

        <div class="consultorio-dashboard-panel mb-4">

            <div class="psicologo-form-section-header">

                <div class="psicologo-form-icon">
                    <i class="bi bi-eye"></i>
                </div>

                <div>
                    <h2>Visibilidad del perfil</h2>

                    <p>
                        Define si el especialista aparecerá en
                        la página pública del consultorio.
                    </p>
                </div>

            </div>

            <div class="psicologo-visibility-box">

                <div>

                    <strong>
                        Mostrar especialista en la página pública
                    </strong>

                    <p>
                        Los pacientes podrán consultar su nombre,
                        especialidad y descripción profesional.
                    </p>

                </div>

                <div class="form-check form-switch">

                    <input
                        class="form-check-input"
                        type="checkbox"
                        role="switch"
                        name="mostrarEnPagina"
                        id="mostrarEnPagina"
                        value="1"
                        <?= (
                            !array_key_exists(
                                'mostrarEnPagina',
                                $datos
                            ) ||
                            (int) (
                                $datos['mostrarEnPagina'] ?? 0
                            ) === 1
                        ) ? 'checked' : ''; ?>
                    >

                    <label
                        class="form-check-label"
                        for="mostrarEnPagina"
                    >
                        Visible
                    </label>

                </div>

            </div>

            <div class="psicologo-access-notice mt-4">

                <i class="bi bi-shield-lock"></i>

                <div>
                    <strong>
                        Cuenta de acceso
                    </strong>

                    <p>
                        El sistema generará una contraseña
                        temporal y la enviará al correo del
                        especialista. En su primer acceso deberá
                        cambiarla.
                    </p>
                </div>

            </div>

        </div>

        <!-- Acciones -->

        <div class="psicologo-form-actions">

            <a
                href="<?= Helper::baseUrl(
                    'consultorio/psicologos'
                ); ?>"
                class="btn agenda-clear-button"
            >
                <i class="bi bi-x-lg"></i>
                Cancelar
            </a>

            <button
    type="submit"
    class="btn agenda-filter-button"
    id="btnGuardarPsicologo"
>
    <i class="bi <?= $modoEdicion
        ? 'bi-check-circle-fill'
        : 'bi-person-plus-fill'; ?>"></i>

    <?= $modoEdicion
        ? 'Guardar cambios'
        : 'Guardar especialista'; ?>
</button>
        </div>

    </form>

</section>

<script>
document.addEventListener('DOMContentLoaded', () => {

    const formulario = document.getElementById(
        'formPsicologo'
    );

    const telefono = document.getElementById(
        'telefono'
    );

    const descripcion = document.getElementById(
        'descripcionProfesional'
    );

    const contador = document.getElementById(
        'contadorDescripcion'
    );

    const botonGuardar = document.getElementById(
        'btnGuardarPsicologo'
    );

    telefono?.addEventListener('input', () => {
        telefono.value = telefono.value
            .replace(/\D/g, '')
            .slice(0, 10);
    });

    const actualizarContador = () => {
        if (!descripcion || !contador) {
            return;
        }

        contador.textContent =
            `${descripcion.value.length} / 1000`;
    };

    descripcion?.addEventListener(
        'input',
        actualizarContador
    );

    actualizarContador();

    formulario?.addEventListener(
        'submit',
        event => {
            if (!formulario.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();

                formulario.classList.add(
                    'was-validated'
                );

                return;
            }

            botonGuardar.disabled = true;

            botonGuardar.innerHTML =
                '<span class="spinner-border ' +
                'spinner-border-sm me-2"></span>' +
                'Guardando...';
        }
    );

});
</script>