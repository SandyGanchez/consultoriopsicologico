<?php

use App\Helpers\Helper;

$p = is_array($privacidad ?? null) ? $privacidad : [];
$versionAceptada = (string) ($p['version_aceptada'] ?? '—');
$fechaAceptacion = (string) ($p['fecha_aceptacion'] ?? '—');
$estado = (string) ($p['estado'] ?? 'SIN_REGISTRO');
$correo = (string) ($p['correo_privacidad'] ?? '');
$telefono = (string) ($p['telefono'] ?? '');
$consultorio = (string) ($p['nombre_consultorio'] ?? 'el consultorio');

?>

<article class="paciente-config-card" id="privacidad">
    <div class="paciente-config-card__head">
        <i class="bi bi-shield-lock"></i>
        <div>
            <h2>Privacidad y datos personales</h2>
            <p>
                Consulta el estado de tu consentimiento y el Aviso de Privacidad
                de <?= htmlspecialchars($consultorio, ENT_QUOTES, 'UTF-8'); ?>.
            </p>
        </div>
    </div>

    <dl class="row mb-3">
        <dt class="col-sm-4">Versión del aviso aceptada</dt>
        <dd class="col-sm-8"><?= htmlspecialchars($versionAceptada, ENT_QUOTES, 'UTF-8'); ?></dd>

        <dt class="col-sm-4">Fecha de aceptación</dt>
        <dd class="col-sm-8"><?= htmlspecialchars($fechaAceptacion, ENT_QUOTES, 'UTF-8'); ?></dd>

        <dt class="col-sm-4">Estado del consentimiento</dt>
        <dd class="col-sm-8"><?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8'); ?></dd>
    </dl>

    <p class="mb-2">
        <a
            href="<?= Helper::baseUrl('aviso-de-privacidad'); ?>"
            target="_blank"
            rel="noopener noreferrer"
        >
            Ver Aviso de Privacidad Integral
        </a>
    </p>

    <p class="small mb-2">
        <?php if ($correo !== ''): ?>
            Correo del responsable:
            <strong><?= htmlspecialchars($correo, ENT_QUOTES, 'UTF-8'); ?></strong>
        <?php endif; ?>
        <?php if ($telefono !== ''): ?>
            <?php if ($correo !== ''): ?> · <?php endif; ?>
            Teléfono:
            <strong><?= htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8'); ?></strong>
        <?php endif; ?>
    </p>

    <p class="small text-muted mb-0">
        Para ejercer derechos relacionados con tus datos personales o solicitar la
        revocación de tu consentimiento, comunícate directamente con el responsable del
        consultorio mediante los datos indicados en el Aviso de Privacidad.
    </p>
</article>
