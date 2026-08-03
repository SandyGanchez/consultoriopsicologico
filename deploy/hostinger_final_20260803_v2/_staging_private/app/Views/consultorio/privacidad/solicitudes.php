<?php

use App\Helpers\Helper;

$solicitudes = is_array($solicitudes ?? null) ? $solicitudes : [];
$csrf = (string) ($csrf ?? '');

?>

<section class="container py-4">
    <h1 class="h4 mb-3">Solicitudes de privacidad (histórico)</h1>
    <p class="text-muted">
        Vista temporal de solicitudes registradas previamente en el sistema.
        Las nuevas solicitudes ARCO y de revocación se reciben directamente
        por los medios del Aviso de Privacidad, no desde la aplicación.
        El psicólogo y el administrador no tienen acceso a este módulo.
    </p>

    <?php if ($solicitudes === []): ?>
        <p>No hay solicitudes registradas.</p>
    <?php else: ?>
        <?php foreach ($solicitudes as $sol): ?>
            <article class="border rounded p-3 mb-3">
                <div class="small text-muted mb-2">
                    #<?= (int) ($sol['IdSolicitudPrivacidad'] ?? 0); ?>
                    · <?= htmlspecialchars((string) ($sol['TipoSolicitud'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    · <?= htmlspecialchars((string) ($sol['EstadoSolicitud'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    · <?= htmlspecialchars((string) ($sol['FechaSolicitud'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <p class="mb-1">
                    <strong><?= htmlspecialchars((string) ($sol['NombreSolicitante'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                    · <?= htmlspecialchars((string) ($sol['CorreoSolicitante'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    · <?= htmlspecialchars((string) ($sol['TelefonoSolicitante'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <p class="mb-2">
                    <?= nl2br(htmlspecialchars((string) ($sol['DetalleSolicitud'] ?? ''), ENT_QUOTES, 'UTF-8')); ?>
                </p>

                <form
                    method="POST"
                    action="<?= Helper::baseUrl('consultorio/privacidad/solicitudes/responder'); ?>"
                >
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <input
                        type="hidden"
                        name="id_solicitud"
                        value="<?= (int) ($sol['IdSolicitudPrivacidad'] ?? 0); ?>"
                    >

                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label small">Estado</label>
                            <select name="estado_solicitud" class="form-select form-select-sm" required>
                                <?php foreach (['RECIBIDA','EN_REVISION','ATENDIDA','RECHAZADA'] as $est): ?>
                                    <option
                                        value="<?= $est; ?>"
                                        <?= ((string) ($sol['EstadoSolicitud'] ?? '') === $est) ? 'selected' : ''; ?>
                                    >
                                        <?= $est; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label small">Respuesta al titular</label>
                            <textarea
                                name="respuesta_titular"
                                class="form-control form-control-sm"
                                rows="2"
                            ><?= htmlspecialchars((string) ($sol['RespuestaTitular'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Notas internas</label>
                            <textarea
                                name="notas_internas"
                                class="form-control form-control-sm"
                                rows="2"
                            ><?= htmlspecialchars((string) ($sol['NotasInternas'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-sm btn-primary mt-2">
                        Guardar respuesta
                    </button>
                </form>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
