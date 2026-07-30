<?php

use App\Helpers\Helper;

$nombreCompleto =
    trim(
        ($perfil['NombrePer'] ?? '') . ' ' .
        ($perfil['ApPatPer'] ?? '') . ' ' .
        ($perfil['ApMatPer'] ?? '')
    );

$foto = 'assets/img/default.png';

if (!empty($perfil['FotoPerfilPac'])) {

    $foto = 'uploads/perfiles/' . $perfil['FotoPerfilPac'];

} elseif (!empty($perfil['FotoPerfilPer'])) {

    $foto = 'uploads/perfiles/' . $perfil['FotoPerfilPer'];

}

$estado = !empty($perfil['EstadoActivoPac'])
    ? 'PACIENTE ACTIVO'
    : 'PACIENTE INACTIVO';

$colorEstado = !empty($perfil['EstadoActivoPac'])
    ? 'success'
    : 'danger';

$fechaNacimiento = '';

if (!empty($perfil['FechaNacimiento'])) {

    $fechaNacimiento = date(
        'd/m/Y',
        strtotime($perfil['FechaNacimiento'])
    );

}

?>

<!-- ==========================================
        MODAL EDITAR PERFIL
========================================== -->

<div
    class="modal fade"
    id="modalEditarPerfil"
    tabindex="-1">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <form
                method="POST"
                action="<?= \App\Helpers\Helper::baseUrl('paciente/perfil/actualizar') ?>">

                <div class="modal-header">

                    <h5 class="modal-title">

                        Editar perfil

                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <div class="row">

                        <div class="col-md-4 mb-3">

                            <label class="form-label">

                                Nombre

                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="NombrePer"
                                required
                                value="<?= htmlspecialchars($perfil['NombrePer']) ?>">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">

                                Apellido paterno

                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="ApPatPer"
                                required
                                value="<?= htmlspecialchars($perfil['ApPatPer']) ?>">

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">

                                Apellido materno

                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="ApMatPer"
                                required
                                value="<?= htmlspecialchars($perfil['ApMatPer']) ?>">

                        </div>

                    </div>

                    <div class="row">

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Correo

                            </label>

                            <input
                                type="email"
                                class="form-control"
                                name="CorreoUsu"
                                required
                                value="<?= htmlspecialchars($perfil['CorreoUsu']) ?>">

                        </div>

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Teléfono

                            </label>

                            <input
                                type="text"
                                maxlength="10"
                                class="form-control"
                                name="TelefonoUsu"
                                value="<?= htmlspecialchars($perfil['TelefonoUsu']) ?>">

                        </div>

                    </div>

                    <div class="row">

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Fecha de nacimiento

                            </label>

                            <input
                                type="date"
                                class="form-control"
                                name="FechaNacimiento"
                                value="<?= htmlspecialchars($perfil['FechaNacimiento'] ?? '') ?>">

                        </div>

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Sexo

                            </label>

                            <select
                                class="form-select"
                                name="GeneroPer">

                                <option
                                    value="Masculino"
                                    <?= $perfil['GeneroPer']=='Masculino'?'selected':'' ?>>

                                    Masculino

                                </option>

                                <option
                                    value="Femenino"
                                    <?= $perfil['GeneroPer']=='Femenino'?'selected':'' ?>>

                                    Femenino

                                </option>

                                <option
                                    value="Otro"
                                    <?= $perfil['GeneroPer']=='Otro'?'selected':'' ?>>

                                    Otro

                                </option>

                            </select>

                        </div>

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">

                        Cancelar

                    </button>

                    <button
                        type="submit"
                        class="btn btn-success">

                        Guardar cambios

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<div class="container-fluid">

    <div class="row justify-content-center">

        <div class="col-xl-6 col-lg-8">

            <div class="card shadow-lg border-0">

                <div class="card-body p-5">

                    <!-- FOTO -->

                    <div class="text-center mb-4">

                        <img
                            src="<?= Helper::baseUrl($foto); ?>"
                            class="rounded-circle shadow"
                            style="
                                width:160px;
                                height:160px;
                                object-fit:cover;
                            "
                            alt="Foto de perfil"
                        >

                    </div>

                    <!-- NOMBRE -->

                    <div class="text-center">

                        <h2 class="fw-bold">

                            <?= htmlspecialchars($nombreCompleto); ?>

                        </h2>

                        <span class="badge bg-<?= $colorEstado; ?> fs-6 mt-2">

                            <?= $estado; ?>

                        </span>

                    </div>

                    <hr class="my-4">

                    <!-- CORREO -->

                    <div class="mb-4">

                        <small class="text-muted">

                            📧 Correo

                        </small>

                        <h5>

                            <?= htmlspecialchars($perfil['CorreoUsu']); ?>

                        </h5>

                    </div>

                    <!-- TELÉFONO -->

                    <div class="mb-4">

                        <small class="text-muted">

                            📱 Teléfono

                        </small>

                        <h5>

                            <?= htmlspecialchars($perfil['TelefonoUsu']); ?>

                        </h5>

                    </div>

                    <!-- SEXO -->

                    <div class="mb-4">

                        <small class="text-muted">

                            👤 Sexo

                        </small>

                        <h5>

                            <?= htmlspecialchars($perfil['GeneroPer']); ?>

                        </h5>

                    </div>

                    <!-- FECHA -->

                    <div class="mb-4">

                        <small class="text-muted">

                            🎂 Fecha de nacimiento

                        </small>

                        <h5>

                            <?= htmlspecialchars($fechaNacimiento); ?>

                        </h5>

                    </div>

                    <!-- CLAVE -->

                    <div class="mb-4">

                        <small class="text-muted">

                            🩺 Clave del paciente

                        </small>

                        <h5>

                            <?= htmlspecialchars($perfil['ClvPac']); ?>

                        </h5>

                    </div>

                    <hr>

                    <div class="d-grid gap-3">

                        <button
    type="button"
    class="btn btn-primary"
    data-bs-toggle="modal"
    data-bs-target="#modalEditarPerfil">

    Editar perfil

</button>

                        <button

    type="button"

    class="btn btn-outline-success btn-lg"

    data-bs-toggle="modal"

    data-bs-target="#modalCambiarFoto">

    <i class="bi bi-camera-fill me-2"></i>

    Cambiar fotografía

</button>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- ==========================================
        MODAL CAMBIAR FOTOGRAFÍA
========================================== -->

<div
    class="modal fade"
    id="modalCambiarFoto"
    tabindex="-1">

    <div class="modal-dialog">

        <div class="modal-content">

            <form

                method="POST"

                action="<?= Helper::baseUrl('paciente/perfil/foto'); ?>"

                enctype="multipart/form-data">

                <div class="modal-header">

                    <h5 class="modal-title">

                        Cambiar fotografía

                    </h5>

                    <button

                        type="button"

                        class="btn-close"

                        data-bs-dismiss="modal">

                    </button>

                </div>

                <div class="modal-body">

                    <div class="text-center mb-4">

                        <img
    src="<?= Helper::baseUrl($foto) . '?v=' . time(); ?>"
    class="rounded-circle shadow"
    style="
        width:160px;
        height:160px;
        object-fit:cover;
    "
>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">

                            Selecciona una imagen

                        </label>

                        <input

                            type="file"

                            class="form-control"

                            name="foto"

                            accept=".jpg,.jpeg,.png"

                            required>

                    </div>

                    <small class="text-muted">

                        Formatos permitidos:

                        JPG, JPEG y PNG.

                        Tamaño máximo:

                        2 MB.

                    </small>

                </div>

                <div class="modal-footer">

                    <button

                        type="button"

                        class="btn btn-secondary"

                        data-bs-dismiss="modal">

                        Cancelar

                    </button>

                    <button

                        type="submit"

                        class="btn btn-success">

                        Guardar fotografía

                    </button>

                </div>

            </form>


        </div>

    </div>

</div>