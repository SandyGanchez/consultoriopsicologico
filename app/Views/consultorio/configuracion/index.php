<?php

use App\Core\Session;
use App\Helpers\Helper;

$datos = $datosConsultorio ?? [];
$erroresConfig = $erroresConfig ?? [];
$csrf = Session::csrfToken();

$logoUrl = Helper::logotipoConsultorioUrl(
    $datos['LogotipoCons'] ?? ''
);

$portadaPreviewUrl = Helper::imagenPortadaConsultorioUrl(
    $datos['ImagenPortada'] ?? null,
    false
);

$latitud = $datos['LatitudDir'] ?? '';
$longitud = $datos['LongitudDir'] ?? '';
$coordsValidas = Helper::coordenadasPublicasValidas($latitud, $longitud);
$direccionInicial = Helper::construirDireccionCompleta($datos);

?>

<section class="clinic-settings-page">

    <p class="clinic-settings-page__breadcrumb mb-0">
        Gestión / Configuración
    </p>

    <h1 class="clinic-settings-page__title">
        Configuración del Consultorio
    </h1>

    <p class="clinic-settings-page__intro">
        Personaliza la información pública, ubicación, horarios de atención
        y políticas de tu consultorio.
    </p>

    <?php if (!empty($success)): ?>

        <div class="alert alert-success settings-alert" role="status">
            <?= htmlspecialchars($success); ?>
        </div>
        <div hidden data-pm-toast="success"><?= htmlspecialchars((string) $success); ?></div>

    <?php endif; ?>

    <?php if (!empty($error)): ?>

        <div class="alert alert-danger settings-alert" role="alert">
            <?= htmlspecialchars($error); ?>
        </div>
        <div hidden data-pm-toast="error"><?= htmlspecialchars((string) $error); ?></div>

    <?php endif; ?>

    <div class="clinic-settings-grid">

        <div class="clinic-settings-grid__main">

            <form
                method="POST"
                action="<?= Helper::baseUrl(
                    'consultorio/configuracion/actualizar'
                ); ?>"
                id="formConfiguracionConsultorio"
                class="settings-card general-info-card"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrf); ?>"
                >

                <div class="settings-card__header">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    <span>Información general</span>
                </div>

                <div class="settings-field">
                    <label for="NombreCons">
                        Nombre del consultorio *
                    </label>
                    <input
                        type="text"
                        class="form-control"
                        id="NombreCons"
                        name="NombreCons"
                        maxlength="100"
                        required
                        value="<?= htmlspecialchars(
                            $datos['NombreCons'] ?? ''
                        ); ?>"
                    >
                    <?php if (!empty($erroresConfig['NombreCons'])): ?>
                        <div class="settings-field__error">
                            <?= htmlspecialchars(
                                $erroresConfig['NombreCons']
                            ); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="settings-field">
                    <label for="Slogan">Eslogan</label>
                    <input
                        type="text"
                        class="form-control"
                        id="Slogan"
                        name="Slogan"
                        maxlength="150"
                        value="<?= htmlspecialchars(
                            $datos['Slogan'] ?? ''
                        ); ?>"
                    >
                </div>

                <div class="settings-field">
                    <label for="Descripcion">Descripción</label>
                    <textarea
                        class="form-control"
                        id="Descripcion"
                        name="Descripcion"
                        rows="4"
                    ><?= htmlspecialchars(
                        $datos['Descripcion'] ?? ''
                    ); ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 settings-field">
                        <label for="TelefonoCons">
                            Teléfono de contacto *
                        </label>
                        <input
                            type="tel"
                            class="form-control"
                            id="TelefonoCons"
                            name="TelefonoCons"
                            maxlength="10"
                            pattern="\d{10}"
                            required
                            value="<?= htmlspecialchars(
                                $datos['TelefonoCons'] ?? ''
                            ); ?>"
                        >
                        <?php if (!empty($erroresConfig['TelefonoCons'])): ?>
                            <div class="settings-field__error">
                                <?= htmlspecialchars(
                                    $erroresConfig['TelefonoCons']
                                ); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6 settings-field">
                        <label for="CorreoElectronico">
                            Correo institucional
                        </label>
                        <input
                            type="email"
                            class="form-control"
                            id="CorreoElectronico"
                            name="CorreoElectronico"
                            maxlength="100"
                            value="<?= htmlspecialchars(
                                $datos['CorreoElectronico'] ?? ''
                            ); ?>"
                        >
                    </div>
                </div>

                <div class="settings-card clinic-address-card mt-3">

                    <div class="settings-card__header">
                        <i class="bi bi-geo-alt" aria-hidden="true"></i>
                        <span>Dirección</span>
                    </div>

                    <div class="row">
                        <div class="col-md-8 settings-field">
                            <label for="CalleDir">Calle</label>
                            <input
                                type="text"
                                class="form-control"
                                id="CalleDir"
                                name="CalleDir"
                                maxlength="70"
                                value="<?= htmlspecialchars(
                                    $datos['CalleDir'] ?? ''
                                ); ?>"
                            >
                        </div>

                        <div class="col-md-4 settings-field">
                            <label for="NumExtDir">Número exterior</label>
                            <input
                                type="text"
                                class="form-control"
                                id="NumExtDir"
                                name="NumExtDir"
                                maxlength="10"
                                value="<?= htmlspecialchars(
                                    $datos['NumExtDir'] ?? ''
                                ); ?>"
                            >
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 settings-field">
                            <label for="NumIntDir">Número interior</label>
                            <input
                                type="text"
                                class="form-control"
                                id="NumIntDir"
                                name="NumIntDir"
                                maxlength="10"
                                value="<?= htmlspecialchars(
                                    $datos['NumIntDir'] ?? ''
                                ); ?>"
                            >
                        </div>

                        <div class="col-md-8 settings-field">
                            <label for="ColoniaDir">Colonia *</label>
                            <input
                                type="text"
                                class="form-control"
                                id="ColoniaDir"
                                name="ColoniaDir"
                                maxlength="50"
                                required
                                value="<?= htmlspecialchars(
                                    $datos['ColoniaDir'] ?? ''
                                ); ?>"
                            >
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 settings-field">
                            <label for="MunicipioDir">Municipio *</label>
                            <input
                                type="text"
                                class="form-control"
                                id="MunicipioDir"
                                name="MunicipioDir"
                                maxlength="50"
                                required
                                value="<?= htmlspecialchars(
                                    $datos['MunicipioDir'] ?? ''
                                ); ?>"
                            >
                        </div>

                        <div class="col-md-4 settings-field">
                            <label for="EstadoDir">Estado *</label>
                            <input
                                type="text"
                                class="form-control"
                                id="EstadoDir"
                                name="EstadoDir"
                                maxlength="50"
                                required
                                value="<?= htmlspecialchars(
                                    $datos['EstadoDir'] ?? ''
                                ); ?>"
                            >
                        </div>

                        <div class="col-md-4 settings-field">
                            <label for="CodPostDir">Código postal *</label>
                            <input
                                type="text"
                                class="form-control"
                                id="CodPostDir"
                                name="CodPostDir"
                                maxlength="5"
                                pattern="\d{5}"
                                required
                                value="<?= htmlspecialchars(
                                    $datos['CodPostDir'] ?? ''
                                ); ?>"
                            >
                        </div>
                    </div>

                    <input
                        type="hidden"
                        id="PaisDir"
                        name="PaisDir"
                        value="<?= htmlspecialchars(
                            $datos['PaisDir'] ?? 'México'
                        ); ?>"
                    >

                    <div class="settings-field">
                        <label for="ReferenciaDir">Referencia</label>
                        <input
                            type="text"
                            class="form-control"
                            id="ReferenciaDir"
                            name="ReferenciaDir"
                            maxlength="255"
                            value="<?= htmlspecialchars(
                                $datos['ReferenciaDir'] ?? ''
                            ); ?>"
                        >
                    </div>

                    <div class="settings-field">
                        <label>Ubicación en mapa</label>
                        <p class="small text-muted mb-2">
                            La dirección escrita y el marcador deben corresponder
                            al mismo lugar. Busca la dirección, usa tu ubicación
                            o coloca el marcador manualmente. Los cambios se
                            guardan al pulsar «Guardar configuración».
                        </p>

                        <?php if ($direccionInicial !== ''): ?>
                            <p class="clinic-map-address-preview mb-2">
                                <strong>Dirección registrada:</strong>
                                <?= htmlspecialchars($direccionInicial, ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        <?php endif; ?>

                        <div
                            id="clinicMap"
                            class="clinic-map"
                            role="application"
                            aria-label="Mapa interactivo para seleccionar ubicación"
                            data-has-coords="<?= $coordsValidas ? '1' : '0'; ?>"
                            data-initial-lat="<?= $coordsValidas
                                ? htmlspecialchars(
                                    number_format((float) $latitud, 6, '.', ''),
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                                : ''; ?>"
                            data-initial-lng="<?= $coordsValidas
                                ? htmlspecialchars(
                                    number_format((float) $longitud, 6, '.', ''),
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                                : ''; ?>"
                        ></div>

                        <div class="clinic-map-tools">
                            <button
                                type="button"
                                class="btn btn-settings-secondary btn-sm"
                                id="btnUsarUbicacion"
                            >
                                <i class="bi bi-crosshair"></i>
                                Usar mi ubicación
                            </button>

                            <button
                                type="button"
                                class="btn btn-settings-secondary btn-sm"
                                id="btnBuscarDireccion"
                            >
                                <i class="bi bi-search"></i>
                                Buscar dirección
                            </button>
                        </div>

                        <div
                            id="clinicMapSearchResults"
                            class="clinic-map-results d-none"
                            role="listbox"
                            aria-label="Resultados de búsqueda de dirección"
                        ></div>

                        <div
                            id="clinicMapDetected"
                            class="clinic-map-detected d-none"
                            role="region"
                            aria-live="polite"
                        >
                            <p class="clinic-map-detected__title mb-1" id="clinicMapDetectedTitle">
                                Ubicación seleccionada en el mapa
                            </p>
                            <p class="clinic-map-detected__text mb-2" id="clinicMapDetectedText"></p>
                            <div class="clinic-map-detected__actions" id="clinicMapDetectedActions"></div>
                        </div>

                        <div
                            id="clinicMapMessage"
                            class="settings-alert alert alert-info d-none"
                            role="status"
                        ></div>

                        <div
                            id="clinicMapCoords"
                            class="clinic-map-coords"
                        >
                            Coordenadas actuales:
                            <?= $coordsValidas
                                ? htmlspecialchars(
                                    number_format((float) $latitud, 6, '.', '')
                                    . ', '
                                    . number_format((float) $longitud, 6, '.', ''),
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                                : 'sin definir'; ?>
                        </div>

                        <input
                            type="hidden"
                            id="LatitudDir"
                            name="LatitudDir"
                            value="<?= $coordsValidas
                                ? htmlspecialchars(
                                    number_format((float) $latitud, 6, '.', ''),
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                                : ''; ?>"
                            data-initial="<?= $coordsValidas
                                ? htmlspecialchars(
                                    number_format((float) $latitud, 6, '.', ''),
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                                : ''; ?>"
                        >

                        <input
                            type="hidden"
                            id="LongitudDir"
                            name="LongitudDir"
                            value="<?= $coordsValidas
                                ? htmlspecialchars(
                                    number_format((float) $longitud, 6, '.', ''),
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                                : ''; ?>"
                            data-initial="<?= $coordsValidas
                                ? htmlspecialchars(
                                    number_format((float) $longitud, 6, '.', ''),
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                                : ''; ?>"
                        >
                    </div>
                </div>

                <?php if (!empty($caracteristicas)): ?>

                    <div class="settings-card clinic-features mt-3">

                        <div class="settings-card__header">
                            <i class="bi bi-stars" aria-hidden="true"></i>
                            <span>Características del consultorio</span>
                        </div>

                        <?php foreach ($caracteristicas as $car): ?>

                            <?php
                                $clave = $car['ClvCar'] ?? '';
                                $activa =
                                    (int) ($car['EstadoCar'] ?? 0) === 1;
                            ?>

                            <div class="clinic-feature-item">

                                <div class="clinic-feature-item__head">

                                    <span>
                                        <i
                                            class="bi <?= htmlspecialchars(
                                                $car['Icono'] ?? 'bi-check2'
                                            ); ?> clinic-feature-item__icon"
                                            aria-hidden="true"
                                        ></i>
                                        Característica
                                    </span>

                                    <div class="form-check form-switch m-0">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            role="switch"
                                            id="carActiva<?= htmlspecialchars(
                                                $clave
                                            ); ?>"
                                            name="caracteristicas[<?= htmlspecialchars(
                                                $clave
                                            ); ?>][EstadoCar]"
                                            value="1"
                                            <?= $activa ? 'checked' : ''; ?>
                                        >
                                        <label
                                            class="form-check-label"
                                            for="carActiva<?= htmlspecialchars(
                                                $clave
                                            ); ?>"
                                        >
                                            Activa
                                        </label>
                                    </div>

                                </div>

                                <div class="settings-field mb-2">
                                    <label
                                        for="carTitulo<?= htmlspecialchars(
                                            $clave
                                        ); ?>"
                                    >
                                        Título
                                    </label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="carTitulo<?= htmlspecialchars(
                                            $clave
                                        ); ?>"
                                        name="caracteristicas[<?= htmlspecialchars(
                                            $clave
                                        ); ?>][Titulo]"
                                        maxlength="60"
                                        value="<?= htmlspecialchars(
                                            $car['Titulo'] ?? ''
                                        ); ?>"
                                    >
                                </div>

                                <div class="settings-field mb-0">
                                    <label
                                        for="carDesc<?= htmlspecialchars(
                                            $clave
                                        ); ?>"
                                    >
                                        Descripción
                                    </label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="carDesc<?= htmlspecialchars(
                                            $clave
                                        ); ?>"
                                        name="caracteristicas[<?= htmlspecialchars(
                                            $clave
                                        ); ?>][Descripcion]"
                                        maxlength="150"
                                        value="<?= htmlspecialchars(
                                            $car['Descripcion'] ?? ''
                                        ); ?>"
                                    >
                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </form>

            <div class="settings-card general-info-card">

                <div class="settings-card__header">
                    <i class="bi bi-image" aria-hidden="true"></i>
                    <span>Logotipo del consultorio</span>
                </div>

                <form
                    method="POST"
                    action="<?= Helper::baseUrl(
                        'consultorio/configuracion/logo'
                    ); ?>"
                    enctype="multipart/form-data"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($csrf); ?>"
                    >

                    <div
                        class="clinic-logo-upload"
                        id="clinicLogoDrop"
                    >

                        <?php if ($logoUrl !== ''): ?>

                            <img
                                src="<?= htmlspecialchars($logoUrl); ?>"
                                alt="Logotipo actual del consultorio"
                                class="clinic-logo-upload__preview"
                                id="clinicLogoPreview"
                            >

                        <?php else: ?>

                            <div
                                class="clinic-logo-upload__placeholder"
                                aria-hidden="true"
                            >
                                <i class="bi bi-building"></i>
                            </div>

                            <img
                                src=""
                                alt=""
                                class="clinic-logo-upload__preview d-none"
                                id="clinicLogoPreview"
                            >

                        <?php endif; ?>

                        <p class="clinic-logo-upload__hint">
                            Formatos permitidos: JPG, JPEG, PNG o WEBP.
                            Tamaño máximo: 2 MB.
                        </p>

                        <input
                            type="file"
                            class="form-control"
                            id="logotipoInput"
                            name="logotipo"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            required
                        >

                    </div>

                    <button
                        type="submit"
                        class="btn btn-settings-secondary w-100 mt-3"
                    >
                        <i class="bi bi-upload me-2"></i>
                        Subir logotipo
                    </button>

                </form>

            </div>

            <div class="settings-card general-info-card">

                <div class="settings-card__header">
                    <i class="bi bi-card-image" aria-hidden="true"></i>
                    <span>Imagen de portada</span>
                </div>

                <p class="clinic-cover-upload__intro">
                    Esta imagen se mostrará en la página principal del
                    consultorio.
                </p>

                <form
                    method="POST"
                    action="<?= Helper::baseUrl(
                        'consultorio/configuracion/portada'
                    ); ?>"
                    enctype="multipart/form-data"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($csrf); ?>"
                    >

                    <div
                        class="clinic-cover-upload"
                        id="clinicCoverDrop"
                    >

                        <?php if ($portadaPreviewUrl !== null): ?>

                            <img
                                src="<?= htmlspecialchars(
                                    $portadaPreviewUrl
                                ); ?>"
                                alt="Portada actual del consultorio"
                                class="clinic-cover-upload__preview"
                                id="clinicCoverPreview"
                            >

                        <?php else: ?>

                            <div
                                class="clinic-cover-upload__placeholder"
                                aria-hidden="true"
                            >
                                <i class="bi bi-image"></i>
                            </div>

                            <img
                                src=""
                                alt=""
                                class="clinic-cover-upload__preview d-none"
                                id="clinicCoverPreview"
                            >

                        <?php endif; ?>

                        <p class="clinic-cover-upload__hint">
                            Formatos permitidos: JPG, JPEG, PNG o WEBP.
                            Tamaño máximo: 3 MB. Dimensiones recomendadas:
                            1200×800 px o similar.
                        </p>

                        <input
                            type="file"
                            class="form-control"
                            id="portadaInput"
                            name="portada"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            required
                        >

                    </div>

                    <button
                        type="submit"
                        class="btn btn-settings-secondary w-100 mt-3"
                    >
                        <i class="bi bi-upload me-2"></i>
                        Actualizar portada
                    </button>

                </form>

            </div>

            <?php
                require __DIR__ . '/partials/redes.php';
            ?>

        </div>

        <aside class="clinic-settings-grid__aside">

            <?php
                require __DIR__ . '/partials/horario.php';
            ?>

            <div class="settings-card mt-3">
                <?php require __DIR__ . '/../../partials/apariencia-controles.php'; ?>
            </div>

            <div class="settings-card cancellation-policy-card">

                <div class="settings-card__header">
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    <span>Política de cancelación</span>
                </div>

                <div class="settings-field mb-0">
                    <label for="LimiteCancHoras">
                        Tiempo máximo de cancelación
                    </label>
                    <div class="input-group">
                        <input
                            type="number"
                            class="form-control"
                            id="LimiteCancHoras"
                            name="LimiteCancHoras"
                            form="formConfiguracionConsultorio"
                            min="0"
                            max="168"
                            step="1"
                            required
                            value="<?= htmlspecialchars(
                                (string) (
                                    $datos['LimiteCancHoras'] ?? 24
                                )
                            ); ?>"
                        >
                        <span class="input-group-text">horas</span>
                    </div>
                    <?php if (!empty($erroresConfig['LimiteCancHoras'])): ?>
                        <div class="settings-field__error">
                            <?= htmlspecialchars(
                                $erroresConfig['LimiteCancHoras']
                            ); ?>
                        </div>
                    <?php endif; ?>
                    <p class="cancellation-policy-card__hint">
                        Los pacientes podrán cancelar una cita antes de que
                        falten las horas configuradas para su inicio.
                    </p>
                </div>

            </div>

            <button
                type="submit"
                class="settings-submit-button"
                form="formConfiguracionConsultorio"
            >
                <i class="bi bi-save me-2" aria-hidden="true"></i>
                Actualizar configuración
            </button>

        </aside>

    </div>

</section>
