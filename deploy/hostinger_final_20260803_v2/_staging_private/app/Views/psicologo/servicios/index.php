<?php

use App\Core\Session;
use App\Helpers\Helper;

$csrf = Session::csrfToken();
$misServicios = $misServicios ?? [];

$totalServicios = count($misServicios);
$serviciosActivos = 0;
$serviciosInactivos = 0;
$pendientesConfig = 0;

foreach ($misServicios as $servicioAsignado) {
    $precio = (float) ($servicioAsignado['PrecioServicio'] ?? 0);
    $duracion = (int) ($servicioAsignado['DuracionMinutos'] ?? 0);
    $activo = ($servicioAsignado['EstatusAsignacion'] ?? '') === 'ACTIVA';

    if ($activo && $precio > 0 && $duracion > 0) {
        $serviciosActivos++;
    } else {
        $serviciosInactivos++;
    }

    if ($precio <= 0 || $duracion <= 0) {
        $pendientesConfig++;
    }
}

function resumirTextoServicio(string $texto, int $limite = 110): string
{
    $texto = trim($texto);

    if ($texto === '') {
        return '';
    }

    if (mb_strlen($texto, 'UTF-8') <= $limite) {
        return $texto;
    }

    return mb_substr($texto, 0, $limite - 3, 'UTF-8') . '...';
}

?>

<section class="psychologist-services-page">

    <header class="psychologist-services-header">

        <span class="psychologist-services-header__eyebrow">
            Configuración profesional
        </span>

        <h1>Mis servicios</h1>

        <p>
            Los servicios institucionales del consultorio aparecen aquí automáticamente.
            Configura tu precio, duración y disponibilidad para citas.
        </p>

        <div class="d-flex flex-wrap gap-2 mt-3">
            <?php if (!empty($sugerenciasHabilitadas)): ?>
                <button
                    type="button"
                    class="btn btn-psychologist-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#modalSugerirServicio"
                >
                    <i class="bi bi-lightbulb" aria-hidden="true"></i>
                    Sugerir servicio
                </button>
            <?php else: ?>
                <button type="button" class="btn btn-psychologist-secondary" disabled title="Pendiente de habilitación en base de datos">
                    Sugerir servicio
                </button>
            <?php endif; ?>
        </div>

    </header>

    <div class="psychologist-services-summary" aria-label="Resumen de servicios">

        <article class="psychologist-services-stat">
            <span>Total</span>
            <strong><?= $totalServicios; ?></strong>
        </article>

        <article class="psychologist-services-stat">
            <span>Disponibles para citas</span>
            <strong><?= $serviciosActivos; ?></strong>
        </article>

        <article class="psychologist-services-stat">
            <span>No ofrecidos</span>
            <strong><?= $serviciosInactivos; ?></strong>
        </article>

        <article class="psychologist-services-stat">
            <span>Pendientes de configurar</span>
            <strong><?= $pendientesConfig; ?></strong>
        </article>

    </div>

    <?php if (!empty($_SESSION['success'])): ?>

        <div
            class="alert psychologist-services-alert psychologist-services-alert--success alert-dismissible fade show"
            role="alert"
        >
            <i class="bi bi-check-circle" aria-hidden="true"></i>
            <span>
                <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>
        </div>

        <?php unset($_SESSION['success']); ?>

    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>

        <div
            class="alert psychologist-services-alert psychologist-services-alert--error alert-dismissible fade show"
            role="alert"
        >
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            <span>
                <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>
        </div>

        <?php unset($_SESSION['error']); ?>

    <?php endif; ?>

    <section class="psychologist-services-section" aria-labelledby="mis-servicios-titulo">

        <div class="psychologist-services-section__head">

            <h2 id="mis-servicios-titulo">Servicios del consultorio</h2>

            <p>
                Puedes editar precio, duración y el estado de tu oferta.
                El nombre y la descripción institucionales no se modifican aquí.
            </p>

        </div>

        <?php if ($misServicios === []): ?>

            <div class="psychologist-services-empty">

                <i class="bi bi-briefcase" aria-hidden="true"></i>

                <h3>Aún no hay servicios institucionales</h3>

                <p>
                    Cuando el consultorio registre un servicio activo, aparecerá
                    aquí para que configures tu oferta.
                </p>

            </div>

        <?php else: ?>

            <div class="psychologist-services-grid">

                <?php foreach ($misServicios as $servicio): ?>

                    <?php
                    $activo =
                        ($servicio['EstatusAsignacion'] ?? '') === 'ACTIVA';
                    $precio = (float) ($servicio['PrecioServicio'] ?? 0);
                    $duracion = (int) ($servicio['DuracionMinutos'] ?? 0);
                    $institucionalActivo =
                        ($servicio['EstatusServicio'] ?? '') === 'ACTIVO';
                    $disponiblePublico =
                        $activo
                        && $institucionalActivo
                        && $precio > 0
                        && $duracion > 0;
                    $descripcion = resumirTextoServicio(
                        (string) ($servicio['Descripcion'] ?? '')
                    );
                    $nombreServicio = (string) ($servicio['NombreServicio'] ?? '');
                    $estadoInstitucional = $institucionalActivo ? 'ACTIVO' : 'INACTIVO';
                    ?>

                    <article
                        class="psychologist-service-card<?= $disponiblePublico
                            ? ''
                            : ' psychologist-service-card--inactive'; ?>"
                    >

                        <div class="psychologist-service-card__top">

                            <div class="psychologist-service-icon" aria-hidden="true">
                                <i class="bi bi-heart-pulse"></i>
                            </div>

                            <span
                                class="psychologist-service-status <?= $disponiblePublico
                                    ? 'psychologist-service-status--active'
                                    : 'psychologist-service-status--inactive'; ?>"
                            >
                                <i
                                    class="bi <?= $disponiblePublico
                                        ? 'bi-check-circle'
                                        : 'bi-pause-circle'; ?>"
                                    aria-hidden="true"
                                ></i>
                                <?php if (!$institucionalActivo): ?>
                                    Servicio institucional inactivo
                                <?php elseif ($disponiblePublico): ?>
                                    Disponible para citas
                                <?php elseif ($activo): ?>
                                    Oferta incompleta
                                <?php else: ?>
                                    No ofrecido
                                <?php endif; ?>
                            </span>

                        </div>

                        <h3 class="psychologist-service-title">
                            <?= htmlspecialchars($nombreServicio, ENT_QUOTES, 'UTF-8'); ?>
                        </h3>

                        <p class="psychologist-service-help mb-2">
                            Estado institucional:
                            <?= htmlspecialchars($estadoInstitucional, ENT_QUOTES, 'UTF-8'); ?>
                        </p>

                        <?php if ($descripcion !== ''): ?>

                            <p class="psychologist-service-description">
                                <?= htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8'); ?>
                            </p>

                        <?php else: ?>

                            <p class="psychologist-service-description psychologist-service-description--empty">
                                Sin descripción disponible.
                            </p>

                        <?php endif; ?>

                        <div class="psychologist-service-meta">

                            <div class="psychologist-service-price">
                                <i class="bi bi-cash-coin" aria-hidden="true"></i>
                                <span>
                                    <?php if ($precio > 0): ?>
                                        $<?= number_format($precio, 2); ?>
                                    <?php else: ?>
                                        Pendiente de configurar
                                    <?php endif; ?>
                                </span>
                            </div>

                            <div class="psychologist-service-duration">
                                <i class="bi bi-clock" aria-hidden="true"></i>
                                <span>
                                    <?php if ($duracion > 0): ?>
                                        <?= $duracion; ?>
                                        minuto<?= $duracion === 1 ? '' : 's'; ?>
                                    <?php else: ?>
                                        Pendiente de configurar
                                    <?php endif; ?>
                                </span>
                            </div>

                        </div>

                        <?php if ($precio <= 0 || $duracion <= 0): ?>

                            <p class="psychologist-service-help">
                                Define precio y duración válidos antes de activar la oferta.
                            </p>

                        <?php endif; ?>

                        <div class="psychologist-service-actions">

                            <a
                                href="<?= Helper::baseUrl(
                                    'psicologo/servicios/editar'
                                ); ?>?id=<?= urlencode(
                                    $servicio['ClvServ'] ?? ''
                                ); ?>"
                                class="btn btn-psychologist-secondary"
                                aria-label="Editar configuración de <?= htmlspecialchars($nombreServicio, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                Editar precio y duración
                            </a>

                            <form
                                method="POST"
                                action="<?= Helper::baseUrl(
                                    'psicologo/servicios/cambiar-estatus'
                                ); ?>"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
                                >

                                <input
                                    type="hidden"
                                    name="clvServ"
                                    value="<?= htmlspecialchars(
                                        $servicio['ClvServ'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>"
                                >

                                <input
                                    type="hidden"
                                    name="accion"
                                    value="<?= $activo ? 'inactivar' : 'activar'; ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn <?= $activo
                                        ? 'btn-psychologist-deactivate'
                                        : 'btn-psychologist-primary'; ?>"
                                    aria-label="<?= $activo
                                        ? 'Dejar de ofrecer'
                                        : 'Ofrecer'; ?> <?= htmlspecialchars($nombreServicio, ENT_QUOTES, 'UTF-8'); ?>"
                                    <?= !$institucionalActivo ? 'disabled' : ''; ?>
                                >
                                    <i
                                        class="bi <?= $activo
                                            ? 'bi-pause-circle'
                                            : 'bi-play-circle'; ?>"
                                        aria-hidden="true"
                                    ></i>
                                    <?= $activo ? 'Dejar de ofrecer' : 'Ofrecer servicio'; ?>
                                </button>

                            </form>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

    <?php
    $sugerencias = is_array($sugerencias ?? null) ? $sugerencias : [];
    ?>

    <?php if ($sugerencias !== []): ?>
        <section class="psychologist-services-section mt-4" aria-labelledby="historial-sugerencias">
            <div class="psychologist-services-section__head">
                <h2 id="historial-sugerencias">Historial de sugerencias</h2>
                <p>Estado de las propuestas enviadas al consultorio.</p>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sugerencias as $sug): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($sug['NombreSugerido'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars((string) ($sug['EstadoSugerencia'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars((string) ($sug['FechaSolicitud'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($sugerenciasHabilitadas)): ?>
        <div
            class="modal fade"
            id="modalSugerirServicio"
            tabindex="-1"
            aria-labelledby="modalSugerirServicioTitulo"
            aria-hidden="true"
        >
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="<?= Helper::baseUrl('psicologo/servicios/sugerir'); ?>">
                        <div class="modal-header">
                            <h2 class="modal-title h5" id="modalSugerirServicioTitulo">
                                Sugerir servicio
                            </h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="mb-3">
                                <label for="nombreSugerido" class="form-label">Nombre sugerido</label>
                                <input type="text" class="form-control" id="nombreSugerido" name="nombreSugerido" maxlength="60" required>
                            </div>
                            <div class="mb-3">
                                <label for="descripcionSugerida" class="form-label">Descripción sugerida</label>
                                <textarea class="form-control" id="descripcionSugerida" name="descripcionSugerida" rows="3" maxlength="255" required></textarea>
                            </div>
                            <div class="mb-0">
                                <label for="justificacion" class="form-label">Justificación</label>
                                <textarea class="form-control" id="justificacion" name="justificacion" rows="3" maxlength="500" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-psychologist-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-psychologist-primary">Enviar sugerencia</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

</section>
