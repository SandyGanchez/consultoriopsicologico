<?php

use App\Core\Session;
use App\Helpers\Helper;
use App\Services\EdadService;

$error = Session::get('error');
$success = Session::get('success');

Session::remove('error');
Session::remove('success');

$limitesEdad = (new EdadService())->limitesInput('adulto');

$rutaFoto = Helper::fotoPerfilUrl(
    (string) ($perfil['FotoPerfilPer'] ?? '')
);

$usarInicialesPerfil = $rutaFoto === null;

$inicialesPerfil = Helper::inicialesPersona(
    (string) ($perfil['NombrePer'] ?? ''),
    (string) ($perfil['ApPatPer'] ?? ''),
    (string) ($perfil['ApMatPer'] ?? '')
);

?>

<section class="psicologo-dashboard">

    <div class="psicologo-page-header">

        <h1>Mi perfil</h1>

        <p>
            Actualiza tu información personal y consulta
            tus datos profesionales.
        </p>

    </div>

    <?php if ($error): ?>

        <div class="alert alert-danger">

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ); ?>

        </div>

    <?php endif; ?>

    <?php if ($success): ?>

        <div class="alert alert-success">

            <?= htmlspecialchars(
                $success,
                ENT_QUOTES,
                'UTF-8'
            ); ?>

        </div>

    <?php endif; ?>

    <div class="psicologo-panel">

        <form
            method="POST"
            action="<?= Helper::baseUrl(
                'psicologo/perfil/actualizar'
            ); ?>"
            enctype="multipart/form-data"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    Session::csrfToken(),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >

            <!-- Fotografía -->

            <div class="text-center mb-5">

                <div
                    id="contenedorVistaPreviaFoto"
                    class="d-inline-block"
                >

                    <?php if (!$usarInicialesPerfil && $rutaFoto !== null): ?>

                        <img
                            src="<?= htmlspecialchars(
                                $rutaFoto,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            alt="Foto de perfil"
                            id="vistaPreviaFoto"
                            class="rounded-circle shadow"
                            width="160"
                            height="160"
                            style="
                                object-fit: cover;
                                border: 5px solid #DAEBE3;
                            "
                        >

                    <?php else: ?>

                        <div
                            id="vistaPreviaIniciales"
                            class="rounded-circle shadow d-inline-flex align-items-center justify-content-center"
                            style="
                                width: 160px;
                                height: 160px;
                                background: #DAEBE3;
                                color: #657166;
                                font-size: 2.4rem;
                                font-weight: 800;
                                border: 5px solid #DAEBE3;
                            "
                            aria-hidden="true"
                        >
                            <?= htmlspecialchars(
                                $inicialesPerfil,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </div>

                        <img
                            src=""
                            alt="Foto de perfil"
                            id="vistaPreviaFoto"
                            class="rounded-circle shadow d-none"
                            width="160"
                            height="160"
                            style="
                                object-fit: cover;
                                border: 5px solid #DAEBE3;
                            "
                        >

                    <?php endif; ?>

                </div>

                <div
                    class="mx-auto mt-3"
                    style="max-width: 420px;"
                >

                    <label
                        for="FotoPerfilPer"
                        class="form-label"
                    >
                        Cambiar foto de perfil
                    </label>

                    <input
                        type="file"
                        class="form-control"
                        name="FotoPerfilPer"
                        id="FotoPerfilPer"
                        accept="image/jpeg,image/png,image/webp"
                    >

                    <div class="form-text">
                        Formatos permitidos: JPG, PNG y WEBP.
                        Tamaño máximo recomendado: 3 MB.
                    </div>

                </div>

            </div>

            <!-- Datos personales -->

            <div class="row g-4">

                <div class="col-12">

                    <h5 class="mb-0">
                        Datos personales
                    </h5>

                </div>

                <div class="col-12 col-md-4">

                    <label
                        for="NombrePer"
                        class="form-label"
                    >
                        Nombre
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        name="NombrePer"
                        id="NombrePer"
                        value="<?= htmlspecialchars(
                            $perfil['NombrePer'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        maxlength="50"
                        required
                    >

                </div>

                <div class="col-12 col-md-4">

                    <label
                        for="ApPatPer"
                        class="form-label"
                    >
                        Apellido paterno
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        name="ApPatPer"
                        id="ApPatPer"
                        value="<?= htmlspecialchars(
                            $perfil['ApPatPer'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        maxlength="50"
                        required
                    >

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
                        class="form-control"
                        name="ApMatPer"
                        id="ApMatPer"
                        value="<?= htmlspecialchars(
                            $perfil['ApMatPer'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        maxlength="50"
                        required
                    >

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
                        class="form-control"
                        name="FechaNacimiento"
                        id="FechaNacimiento"
                        value="<?= htmlspecialchars(
                            $perfil['FechaNacimiento'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        min="<?= htmlspecialchars($limitesEdad['min'], ENT_QUOTES, 'UTF-8'); ?>"
                        max="<?= htmlspecialchars($limitesEdad['max'], ENT_QUOTES, 'UTF-8'); ?>"
                        required
                    >
                    <div class="form-text">
                        Debes tener al menos 18 años.
                    </div>

                </div>

                <div class="col-12 col-md-6">

                    <label
                        for="GeneroPer"
                        class="form-label"
                    >
                        Género
                    </label>

                    <select
                        class="form-select"
                        name="GeneroPer"
                        id="GeneroPer"
                        required
                    >

                        <?php
                        $generos = [
                            'Masculino',
                            'Femenino',
                            'Otro'
                        ];
                        ?>

                        <?php foreach ($generos as $genero): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $genero,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>"
                                <?= (
                                    ($perfil['GeneroPer'] ?? '') ===
                                    $genero
                                ) ? 'selected' : ''; ?>
                            >
                                <?= htmlspecialchars(
                                    $genero,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-12 col-md-6">

                    <label
                        for="CorreoUsu"
                        class="form-label"
                    >
                        Correo electrónico
                    </label>

                    <input
                        type="email"
                        class="form-control"
                        id="CorreoUsu"
                        value="<?= htmlspecialchars(
                            $perfil['CorreoUsu'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        readonly
                    >

                </div>

                <div class="col-12 col-md-6">

                    <label
                        for="TelefonoUsu"
                        class="form-label"
                    >
                        Teléfono
                    </label>

                    <input
                        type="tel"
                        class="form-control"
                        id="TelefonoUsu"
                        value="<?= htmlspecialchars(
                            $perfil['TelefonoUsu'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        readonly
                    >

                    <div class="form-text">
                        El teléfono y el correo se actualizan en
                        <a href="<?= Helper::baseUrl('psicologo/configuracion'); ?>">
                            Configuración
                        </a>
                        con verificación de seguridad.
                    </div>

                </div>

                <!-- Datos profesionales -->

                <div class="col-12 mt-5">

                    <h5 class="mb-1">
                        Datos profesionales
                    </h5>

                    <p class="text-muted mb-0">
                        Esta información es administrada por el
                        consultorio.
                    </p>

                </div>

                <div class="col-12 col-md-6">

                    <label
                        for="CedulaProfesional"
                        class="form-label"
                    >
                        Cédula profesional
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="CedulaProfesional"
                        value="<?= htmlspecialchars(
                            $perfil['CedulaProfesional'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        readonly
                    >

                </div>

                <div class="col-12 col-md-6">

                    <label
                        for="EspecialidadPsi"
                        class="form-label"
                    >
                        Especialidad
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="EspecialidadPsi"
                        value="<?= htmlspecialchars(
                            $perfil['EspecialidadPsi'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        readonly
                    >

                </div>

                <div class="col-12">

                    <label
                        for="DescripcionProfesional"
                        class="form-label"
                    >
                        Descripción profesional
                    </label>

                    <textarea
                        class="form-control"
                        id="DescripcionProfesional"
                        rows="4"
                        readonly
                    ><?= htmlspecialchars(
                        $perfil['DescripcionProfesional'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?></textarea>

                </div>

                <!-- Botón -->

                <div class="col-12 text-end mt-4">

                    <button
                        type="submit"
                        class="btn btn-primary px-4"
                    >
                        <i class="bi bi-check-circle-fill me-1"></i>
                        Guardar cambios
                    </button>

                </div>

            </div>

        </form>

    </div>

    <?php require __DIR__ . '/partials/redes-profesionales.php'; ?>

</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const formPerfil = document.querySelector(
        'form[action*="psicologo/perfil"]'
    );
    const botonGuardar = formPerfil?.querySelector(
        'button[type="submit"]'
    );
    let enviandoPerfil = false;

    formPerfil?.addEventListener('submit', (event) => {
        if (enviandoPerfil) {
            event.preventDefault();
            return;
        }

        enviandoPerfil = true;

        if (botonGuardar) {
            botonGuardar.disabled = true;
            botonGuardar.textContent = 'Guardando…';
        }
    });

    const inputFoto = document.getElementById(
        'FotoPerfilPer'
    );

    const vistaPrevia = document.getElementById(
        'vistaPreviaFoto'
    );

    inputFoto?.addEventListener('change', () => {
        const archivo = inputFoto.files?.[0];

        if (!archivo || !vistaPrevia) {
            return;
        }

        const tiposPermitidos = [
            'image/jpeg',
            'image/png',
            'image/webp'
        ];

        if (!tiposPermitidos.includes(archivo.type)) {
            inputFoto.value = '';

            alert(
                'Selecciona una imagen JPG, PNG o WEBP.'
            );

            return;
        }

        const tamanioMaximo =
            3 * 1024 * 1024;

        if (archivo.size > tamanioMaximo) {
            inputFoto.value = '';

            alert(
                'La imagen no debe superar 3 MB.'
            );

            return;
        }

        const urlTemporal =
            URL.createObjectURL(archivo);

        const iniciales = document.getElementById(
            'vistaPreviaIniciales'
        );

        if (iniciales) {
            iniciales.classList.add('d-none');
        }

        vistaPrevia.classList.remove('d-none');
        vistaPrevia.src = urlTemporal;

        vistaPrevia.onload = () => {
            URL.revokeObjectURL(urlTemporal);
        };
    });
});
</script>