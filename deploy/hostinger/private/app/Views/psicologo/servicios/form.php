<?php

use App\Core\Session;
use App\Helpers\Helper;

$datos = $datos ?? [];
$errores = $errores ?? [];
$modoEdicion = $modoEdicion ?? false;
$servicio = $servicio ?? [];
$csrf = Session::csrfToken();

function valorAsignacion(array $datos, string $campo): string
{
    return htmlspecialchars(
        (string) ($datos[$campo] ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function claseInvalidaAsignacion(array $errores, string $campo): string
{
    return isset($errores[$campo]) ? ' is-invalid' : '';
}

$precioSugerido = (float) ($servicio['CostoServicio'] ?? 0);
$duracionSugerida = (int) (
    $servicio['DuracionMinutos']
    ?? $servicio['DuracionSugerida']
    ?? 0
);
$estadoAsignacion = (string) ($servicio['EstatusAsignacion'] ?? '');
$estadoActivo = $estadoAsignacion === 'ACTIVA';

?>

<section class="psychologist-services-page">

    <header class="psychologist-services-header">

        <span class="psychologist-services-header__eyebrow">
            Configuración profesional
        </span>

        <h1>
            <?= $modoEdicion
                ? 'Configurar servicio'
                : 'Agregar servicio'; ?>
        </h1>

        <p>
            <?= $modoEdicion
                ? 'Actualiza el precio y la duración que aplicas a este servicio.'
                : 'Revisa el servicio del catálogo y define tu precio y duración.'; ?>
        </p>

    </header>

    <?php if (!empty($errores['general'])): ?>

        <div
            class="alert psychologist-services-alert psychologist-services-alert--error"
            role="alert"
        >
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            <span>
                <?= htmlspecialchars($errores['general'], ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </div>

    <?php endif; ?>

    <div class="psychologist-service-card psychologist-service-form-shell">

        <form
            method="POST"
            action="<?= Helper::baseUrl(
                $modoEdicion
                    ? 'psicologo/servicios/actualizar'
                    : 'psicologo/servicios/guardar'
            ); ?>"
            class="psychologist-service-form"
            novalidate
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
            >

            <input
                type="hidden"
                name="clvServ"
                value="<?= valorAsignacion($servicio, 'ClvServ'); ?>"
            >

            <div class="psychologist-service-form-readonly">

                <span>Servicio</span>

                <strong>
                    <?= valorAsignacion($servicio, 'NombreServicio'); ?>
                </strong>

                <?php if (trim((string) ($servicio['Descripcion'] ?? '')) !== ''): ?>

                    <p class="mt-2 mb-0">
                        <?= htmlspecialchars(
                            (string) $servicio['Descripcion'],
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </p>

                <?php endif; ?>

            </div>

            <?php if ($modoEdicion && $estadoAsignacion !== ''): ?>

                <div class="psychologist-service-form-readonly">

                    <span>Estado actual</span>

                    <strong>
                        <span
                            class="psychologist-service-status <?= $estadoActivo
                                ? 'psychologist-service-status--active'
                                : 'psychologist-service-status--inactive'; ?>"
                        >
                            <i
                                class="bi <?= $estadoActivo
                                    ? 'bi-check-circle'
                                    : 'bi-pause-circle'; ?>"
                                aria-hidden="true"
                            ></i>
                            <?= $estadoActivo ? 'Activo' : 'Inactivo'; ?>
                        </span>
                    </strong>

                </div>

            <?php endif; ?>

            <?php if (!$modoEdicion): ?>

                <div class="psychologist-service-meta">

                    <div class="psychologist-service-form-readonly">

                        <span>Precio sugerido</span>

                        <strong class="psychologist-service-price">
                            <i class="bi bi-cash-coin" aria-hidden="true"></i>
                            $<?= number_format($precioSugerido, 2); ?>
                        </strong>

                    </div>

                    <div class="psychologist-service-form-readonly">

                        <span>Duración sugerida</span>

                        <strong class="psychologist-service-duration">
                            <i class="bi bi-clock" aria-hidden="true"></i>
                            <?= $duracionSugerida; ?>
                            minuto<?= $duracionSugerida === 1 ? '' : 's'; ?>
                        </strong>

                    </div>

                </div>

                <p class="psychologist-service-help">
                    El precio y la duración configurados se aplicarán únicamente a tus nuevas
                    citas.
                </p>

            <?php else: ?>

                <p class="psychologist-service-form-note">
                    Los cambios se aplicarán solamente a nuevas citas. Las citas anteriores
                    conservarán el precio y duración registrados al momento de agendar.
                </p>

            <?php endif; ?>

            <div class="psychologist-service-form-fields">

                <div>

                    <label for="precioServicio" class="form-label">
                        Precio del servicio
                    </label>

                    <div class="input-group">

                        <span class="input-group-text" aria-hidden="true">$</span>

                        <input
                            type="number"
                            name="precioServicio"
                            id="precioServicio"
                            class="form-control<?= claseInvalidaAsignacion($errores, 'precioServicio'); ?>"
                            min="0"
                            max="99999999.99"
                            step="0.01"
                            required
                            value="<?= valorAsignacion($datos, 'PrecioServicio'); ?>"
                            aria-describedby="ayudaPrecioServicio"
                        >

                    </div>

                    <?php if (!empty($errores['precioServicio'])): ?>

                        <div class="invalid-feedback d-block">
                            <?= htmlspecialchars(
                                $errores['precioServicio'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </div>

                    <?php endif; ?>

                    <small
                        class="psychologist-service-help"
                        id="ayudaPrecioServicio"
                    >
                        Puedes registrar 0.00 si el servicio es gratuito o el precio está pendiente.
                    </small>

                </div>

                <div>

                    <label for="duracionMinutos" class="form-label">
                        Duración (minutos)
                    </label>

                    <input
                        type="number"
                        name="duracionMinutos"
                        id="duracionMinutos"
                        class="form-control<?= claseInvalidaAsignacion($errores, 'duracionMinutos'); ?>"
                        min="1"
                        max="480"
                        step="1"
                        required
                        value="<?= valorAsignacion($datos, 'DuracionMinutos'); ?>"
                        aria-describedby="ayudaDuracionMinutos"
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

                    <small
                        class="psychologist-service-help"
                        id="ayudaDuracionMinutos"
                    >
                        Ejemplos: 30, 45, 50, 60, 90 o 120 minutos.
                    </small>

                </div>

            </div>

            <div class="psychologist-service-form-actions">

                <button type="submit" class="btn btn-psychologist-primary">
                    <i
                        class="bi <?= $modoEdicion
                            ? 'bi-check2-circle'
                            : 'bi-plus-circle'; ?>"
                        aria-hidden="true"
                    ></i>
                    <?= $modoEdicion
                        ? 'Guardar cambios'
                        : 'Agregar servicio'; ?>
                </button>

                <a
                    href="<?= Helper::baseUrl('psicologo/servicios'); ?>"
                    class="btn btn-psychologist-secondary"
                >
                    Cancelar
                </a>

            </div>

        </form>

    </div>

</section>
