<?php

use App\Helpers\Helper;

$escapar = static function ($valor): string {
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
};

$perfil = is_array($perfil ?? null) ? $perfil : [];

$partesNombre = array_filter(
    [
        trim((string) ($perfil['NombrePer'] ?? '')),
        trim((string) ($perfil['ApPatPer'] ?? '')),
        trim((string) ($perfil['ApMatPer'] ?? ''))
    ],
    static fn(string $parte): bool => $parte !== ''
);

$nombreCompleto = trim(implode(' ', $partesNombre));

if ($nombreCompleto === '') {
    $nombreCompleto = 'Paciente';
}

$iniciales = '';

foreach (array_slice($partesNombre, 0, 2) as $parte) {
    $iniciales .= mb_strtoupper(mb_substr($parte, 0, 1));
}

if ($iniciales === '') {
    $iniciales = 'P';
}

$urlFoto = Helper::fotoPerfilUrl(
    (string) ($perfil['FotoPerfilPer'] ?? '')
) ?? '';

$correo = trim((string) ($perfil['CorreoUsu'] ?? ''));
$telefono = trim((string) ($perfil['TelefonoUsu'] ?? ''));
$genero = trim((string) ($perfil['GeneroPer'] ?? ''));
$fechaNacimientoRaw = trim((string) ($perfil['FechaNacimiento'] ?? ''));
$fechaNacimiento = '';

if ($fechaNacimientoRaw !== '') {
    try {
        $dt = new DateTimeImmutable(
            $fechaNacimientoRaw,
            new DateTimeZone('America/Mexico_City')
        );
        $fechaNacimiento = $dt->format('d/m/Y');
    } catch (Throwable $e) {
        $fechaNacimiento = $fechaNacimientoRaw;
    }
}

$cuentaActiva = !empty($perfil['EstadoActivoPac']);

$tieneDireccion = trim((string) ($perfil['ClvDir'] ?? '')) !== ''
    && (
        trim((string) ($perfil['CalleDir'] ?? '')) !== ''
        || trim((string) ($perfil['ColoniaDir'] ?? '')) !== ''
        || trim((string) ($perfil['MunicipioDir'] ?? '')) !== ''
    );

$lineasDireccion = [];

if ($tieneDireccion) {
    $calle = trim((string) ($perfil['CalleDir'] ?? ''));
    $numExt = trim((string) ($perfil['NumExtDir'] ?? ''));
    $numInt = trim((string) ($perfil['NumIntDir'] ?? ''));
    $lineaCalle = $calle;

    if ($numExt !== '') {
        $lineaCalle .= ($lineaCalle !== '' ? ' #' : '#') . $numExt;
    }

    if ($numInt !== '') {
        $lineaCalle .= ($lineaCalle !== '' ? ', Int. ' : 'Int. ') . $numInt;
    }

    if ($lineaCalle !== '') {
        $lineasDireccion[] = $lineaCalle;
    }

    $colonia = trim((string) ($perfil['ColoniaDir'] ?? ''));

    if ($colonia !== '') {
        $lineasDireccion[] = 'Col. ' . $colonia;
    }

    $municipio = trim((string) ($perfil['MunicipioDir'] ?? ''));
    $estadoDir = trim((string) ($perfil['EstadoDir'] ?? ''));
    $cp = trim((string) ($perfil['CodPostDir'] ?? ''));
    $ciudad = trim(implode(', ', array_filter([$municipio, $estadoDir])));

    if ($cp !== '') {
        $ciudad = trim($ciudad . ($ciudad !== '' ? ' ' : '') . 'C.P. ' . $cp);
    }

    if ($ciudad !== '') {
        $lineasDireccion[] = $ciudad;
    }

    $pais = trim((string) ($perfil['PaisDir'] ?? ''));

    if ($pais !== '') {
        $lineasDireccion[] = $pais;
    }

    $referencia = trim((string) ($perfil['ReferenciaDir'] ?? ''));

    if ($referencia !== '') {
        $lineasDireccion[] = 'Ref.: ' . $referencia;
    }
}

?>

<<<<<<< HEAD
<section class="paciente-profile">

    <?php
        $flashSuccess = trim((string) ($_SESSION['success'] ?? ''));
        $flashError = trim((string) ($_SESSION['error'] ?? ''));
        unset($_SESSION['success'], $_SESSION['error']);
    ?>

    <header class="paciente-page-header">
        <div class="paciente-page-header-icon" aria-hidden="true">
            <i class="bi bi-person-vcard"></i>
        </div>
        <div class="paciente-page-header-copy">
            <h1>Mi perfil</h1>
            <p>Consulta y actualiza tu información personal.</p>
        </div>
        <a
            class="paciente-btn paciente-btn-primary paciente-page-header-action"
            href="<?= $escapar(Helper::baseUrl('paciente/perfil/editar')); ?>"
        >
            Editar perfil
        </a>
    </header>

    <?php if ($flashSuccess !== ''): ?>
        <div class="paciente-profile-alert is-success" role="status">
            <?= $escapar($flashSuccess); ?>
        </div>
    <?php endif; ?>

    <?php if ($flashError !== ''): ?>
        <div class="paciente-profile-alert" role="alert">
            <?= $escapar($flashError); ?>
        </div>
    <?php endif; ?>

    <article class="paciente-profile-hero">
        <div class="paciente-profile-hero-band" aria-hidden="true"></div>

        <div class="paciente-profile-hero-body">
            <div class="paciente-profile-avatar">
                <?php if ($urlFoto !== ''): ?>
                    <img
                        src="<?= $escapar($urlFoto); ?>"
                        alt="Fotografía de <?= $escapar($nombreCompleto); ?>"
                    >
                <?php else: ?>
                    <span aria-hidden="true"><?= $escapar($iniciales); ?></span>
                    <span class="visually-hidden">
                        Iniciales de <?= $escapar($nombreCompleto); ?>
                    </span>
                <?php endif; ?>
=======
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

>>>>>>> 695ed54fd236f51b2874f2535f59aee5e67c49e3
            </div>

            <div class="paciente-profile-hero-info">
                <h2><?= $escapar($nombreCompleto); ?></h2>

                <p class="paciente-profile-role">
                    <i class="bi bi-heart-pulse" aria-hidden="true"></i>
                    Paciente
                </p>

                <?php if ($correo !== ''): ?>
                    <p class="paciente-profile-meta">
                        <i class="bi bi-envelope" aria-hidden="true"></i>
                        <?= $escapar($correo); ?>
                    </p>
                <?php endif; ?>

                <span class="paciente-profile-status <?= $cuentaActiva ? 'is-active' : 'is-inactive'; ?>">
                    <i
                        class="bi <?= $cuentaActiva ? 'bi-check-circle' : 'bi-pause-circle'; ?>"
                        aria-hidden="true"
                    ></i>
                    <?= $cuentaActiva ? 'Cuenta activa' : 'Cuenta inactiva'; ?>
                </span>
            </div>
        </div>
    </article>

    <div class="paciente-profile-grid">

        <section
            class="paciente-profile-card"
            aria-labelledby="perfil-personal-titulo"
        >
            <h2 id="perfil-personal-titulo">
                <i class="bi bi-person" aria-hidden="true"></i>
                Información personal
            </h2>

            <dl class="paciente-profile-fields">
                <div>
                    <dt>Nombre</dt>
                    <dd>
                        <?= $escapar(
                            trim((string) ($perfil['NombrePer'] ?? '')) !== ''
                                ? $perfil['NombrePer']
                                : 'No registrado'
                        ); ?>
                    </dd>
                </div>
                <div>
                    <dt>Apellido paterno</dt>
                    <dd>
                        <?= $escapar(
                            trim((string) ($perfil['ApPatPer'] ?? '')) !== ''
                                ? $perfil['ApPatPer']
                                : 'No registrado'
                        ); ?>
                    </dd>
                </div>
                <?php if (trim((string) ($perfil['ApMatPer'] ?? '')) !== ''): ?>
                    <div>
                        <dt>Apellido materno</dt>
                        <dd><?= $escapar($perfil['ApMatPer'] ?? ''); ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($fechaNacimiento !== ''): ?>
                    <div>
                        <dt>Fecha de nacimiento</dt>
                        <dd><?= $escapar($fechaNacimiento); ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($genero !== ''): ?>
                    <div>
                        <dt>Género</dt>
                        <dd><?= $escapar($genero); ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        </section>

        <section
            class="paciente-profile-card"
            aria-labelledby="perfil-contacto-titulo"
        >
            <h2 id="perfil-contacto-titulo">
                <i class="bi bi-telephone" aria-hidden="true"></i>
                Información de contacto
            </h2>

            <ul class="paciente-profile-contact">
                <li>
                    <span class="paciente-profile-contact-icon" aria-hidden="true">
                        <i class="bi bi-envelope"></i>
                    </span>
                    <div>
                        <strong>Correo electrónico</strong>
                        <span>
                            <?= $escapar(
                                $correo !== '' ? $correo : 'No registrado'
                            ); ?>
                        </span>
                        <?php if ($correo !== ''): ?>
                            <small>El correo se utiliza para iniciar sesión.</small>
                        <?php endif; ?>
                        <a
                            class="paciente-profile-edit-link"
                            href="<?= $escapar(
                                Helper::baseUrl('paciente/configuracion')
                            ); ?>"
                        >
                            Editar en Configuración
                        </a>
                    </div>
                </li>
                <li>
                    <span class="paciente-profile-contact-icon" aria-hidden="true">
                        <i class="bi bi-telephone"></i>
                    </span>
                    <div>
                        <strong>Teléfono</strong>
                        <span>
                            <?= $escapar(
                                $telefono !== '' ? $telefono : 'No registrado'
                            ); ?>
                        </span>
                        <a
                            class="paciente-profile-edit-link"
                            href="<?= $escapar(
                                Helper::baseUrl('paciente/configuracion')
                            ); ?>"
                        >
                            Editar en Configuración
                        </a>
                    </div>
                </li>
            </ul>
        </section>

        <section
            class="paciente-profile-card"
            aria-labelledby="perfil-direccion-titulo"
        >
            <h2 id="perfil-direccion-titulo">
                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                Dirección
            </h2>

            <?php if ($tieneDireccion && !empty($lineasDireccion)): ?>
                <address class="paciente-profile-address">
                    <?php foreach ($lineasDireccion as $linea): ?>
                        <span><?= $escapar($linea); ?></span>
                    <?php endforeach; ?>
                </address>
            <?php else: ?>
                <p class="paciente-profile-empty">
                    Aún no has registrado una dirección.
                </p>
            <?php endif; ?>
        </section>

        <section
            class="paciente-profile-card paciente-profile-card--security"
            aria-labelledby="perfil-seguridad-titulo"
        >
            <h2 id="perfil-seguridad-titulo">
                <i class="bi bi-shield-lock" aria-hidden="true"></i>
                Seguridad y cuenta
            </h2>

            <p>
                El correo, el teléfono y la contraseña se administran en
                Configuración. Ahí puedes solicitar el cambio de correo con
                verificación por código.
            </p>

            <div class="paciente-profile-actions">
                <a
                    class="paciente-btn paciente-btn-primary"
                    href="<?= $escapar(
                        Helper::baseUrl('paciente/configuracion')
                    ); ?>"
                >
                    Ir a configuración
                </a>
                <a
                    class="paciente-btn paciente-btn-secondary"
                    href="<?= $escapar(
                        Helper::baseUrl('paciente/perfil/editar')
                    ); ?>"
                >
                    Editar perfil
                </a>
            </div>
        </section>

    </div>

<<<<<<< HEAD
</section>
=======
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
>>>>>>> 695ed54fd236f51b2874f2535f59aee5e67c49e3
