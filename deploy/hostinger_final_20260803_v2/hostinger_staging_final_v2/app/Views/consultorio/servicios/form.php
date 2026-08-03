<?php

use App\Core\Session;
use App\Helpers\Helper;

$datos = $datos ?? [];
$errores = $errores ?? [];
$modoEdicion = $modoEdicion ?? false;
$csrf = Session::csrfToken();

function valorServicio(array $datos, string $campo): string
{
    return htmlspecialchars(
        (string) ($datos[$campo] ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function claseInvalidaServicio(array $errores, string $campo): string
{
    return isset($errores[$campo]) ? ' is-invalid' : '';
}

?>

<section class="clinic-services-page clinic-services-header">

    <div class="consultorio-page-header">

        <span class="consultorio-page-eyebrow">
            Catálogo general
        </span>

        <h1>
            <?= $modoEdicion
                ? 'Editar servicio'
                : 'Registrar servicio'; ?>
        </h1>

        <p>
            Define el servicio institucional: nombre, descripción y estado.
            Cada especialista configurará después su propio precio y duración.
        </p>

    </div>

    <?php if (!empty($errores['general'])): ?>

        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($errores['general'], ENT_QUOTES, 'UTF-8'); ?>
        </div>

    <?php endif; ?>

    <div class="clinic-services-card">

        <form
            method="POST"
            action="<?= Helper::baseUrl(
                $modoEdicion
                    ? 'consultorio/servicios/actualizar'
                    : 'consultorio/servicios/guardar'
            ); ?>"
            class="clinic-service-form"
            novalidate
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
            >

            <?php if ($modoEdicion): ?>

                <input
                    type="hidden"
                    name="clvServ"
                    value="<?= valorServicio($datos, 'ClvServ'); ?>"
                >

            <?php endif; ?>

            <?php if (!empty($idSugerencia)): ?>
                <input
                    type="hidden"
                    name="idSugerencia"
                    value="<?= (int) $idSugerencia; ?>"
                >
            <?php endif; ?>

            <div class="clinic-service-form-note">

                El catálogo institucional no define tarifas individuales.
                Precio y duración los configura cada especialista en Mis servicios.

            </div>

            <div class="row g-3">

                <div class="col-md-8">

                    <label for="nombreServicio" class="form-label">
                        Nombre del servicio
                    </label>

                    <input
                        type="text"
                        name="nombreServicio"
                        id="nombreServicio"
                        class="form-control<?= claseInvalidaServicio($errores, 'nombreServicio'); ?>"
                        maxlength="60"
                        required
                        value="<?= valorServicio($datos, 'NombreServicio'); ?>"
                    >

                    <?php if (!empty($errores['nombreServicio'])): ?>

                        <div class="invalid-feedback d-block">
                            <?= htmlspecialchars(
                                $errores['nombreServicio'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </div>

                    <?php endif; ?>

                </div>

                <?php if (!$modoEdicion): ?>

                    <div class="col-md-4">

                        <label for="estatusServicio" class="form-label">
                            Estatus inicial
                        </label>

                        <select
                            name="estatusServicio"
                            id="estatusServicio"
                            class="form-select<?= claseInvalidaServicio($errores, 'estatusServicio'); ?>"
                        >

                            <option
                                value="ACTIVO"
                                <?= ($datos['EstatusServicio'] ?? 'ACTIVO') === 'ACTIVO'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Activo
                            </option>

                            <option
                                value="INACTIVO"
                                <?= ($datos['EstatusServicio'] ?? '') === 'INACTIVO'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Inactivo
                            </option>

                        </select>

                        <?php if (!empty($errores['estatusServicio'])): ?>

                            <div class="invalid-feedback d-block">
                                <?= htmlspecialchars(
                                    $errores['estatusServicio'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </div>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

                <div class="col-12">

                    <label for="descripcion" class="form-label">
                        Descripción
                    </label>

                    <textarea
                        name="descripcion"
                        id="descripcion"
                        class="form-control<?= claseInvalidaServicio($errores, 'descripcion'); ?>"
                        rows="3"
                        maxlength="255"
                        required
                    ><?= valorServicio($datos, 'Descripcion'); ?></textarea>

                    <?php if (!empty($errores['descripcion'])): ?>

                        <div class="invalid-feedback d-block">
                            <?= htmlspecialchars(
                                $errores['descripcion'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </div>

                    <?php endif; ?>

                </div>

            </div>

            <div class="d-flex flex-wrap gap-2 mt-2">

                <button type="submit" class="btn btn-clinic-primary">
                    Guardar
                </button>

                <a
                    href="<?= Helper::baseUrl('consultorio/servicios'); ?>"
                    class="btn btn-clinic-secondary"
                >
                    Cancelar
                </a>

            </div>

        </form>

    </div>

</section>
