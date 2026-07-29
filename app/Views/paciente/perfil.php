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
                            class="btn btn-success btn-lg"
                        >

                            <i class="bi bi-pencil-square me-2"></i>

                            Editar perfil

                        </button>

                        <button
                            class="btn btn-outline-success btn-lg"
                        >

                            <i class="bi bi-camera-fill me-2"></i>

                            Cambiar fotografía

                        </button>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>