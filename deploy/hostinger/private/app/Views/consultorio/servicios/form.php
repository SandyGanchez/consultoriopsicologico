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
            Captura la información general del servicio. Estos valores
            funcionan como referencia para el consultorio.
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

            <div class="clinic-service-form-note">

                Estos valores funcionan como referencia. Cada especialista
                podrá configurar posteriormente el precio y la duración que
                aplicará al impartir este servicio.

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

                <div class="col-md-6">

                    <label for="duracionMinutos" class="form-label">
                        Duración sugerida (minutos)
                    </label>

                    <input
                        type="number"
                        name="duracionMinutos"
                        id="duracionMinutos"
                        class="form-control clinic-service-duration<?= claseInvalidaServicio($errores, 'duracionMinutos'); ?>"
                        min="1"
                        max="480"
                        step="1"
                        required
                        value="<?= valorServicio($datos, 'DuracionMinutos'); ?>"
                    >

                    <?php if (!empty($errores['duracionMinutos'])): ?>

                        <div class="invalid-feedback d-block">
                            <?= htmlspecialchars(
                                $errores['duracionMinutos'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-md-6">

                    <label for="costoServicio" class="form-label">
                        Precio sugerido
                    </label>

                    <div class="input-group">

                        <span class="input-group-text">$</span>

                        <input
                            type="number"
                            name="costoServicio"
                            id="costoServicio"
                            class="form-control clinic-service-price<?= claseInvalidaServicio($errores, 'costoServicio'); ?>"
                            min="0"
                            max="99999999.99"
                            step="0.01"
                            required
                            value="<?= valorServicio($datos, 'CostoServicio'); ?>"
                        >

                    </div>

                    <?php if (!empty($errores['costoServicio'])): ?>

                        <div class="invalid-feedback d-block">
                            <?= htmlspecialchars(
                                $errores['costoServicio'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </div>

                    <?php endif; ?>

                    <small class="text-muted">
                        Puedes registrar 0.00 si el precio aún está pendiente.
                    </small>

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
