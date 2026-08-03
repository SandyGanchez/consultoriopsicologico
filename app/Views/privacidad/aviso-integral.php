<?php

use App\Helpers\Helper;

$r = is_array($responsable ?? null) ? $responsable : [];
$version = (string) ($version ?? '1.0');
$fecha = (string) ($fecha ?? '');
$contenido = trim((string) ($contenido ?? ''));
$mensaje = (string) ($mensaje ?? '');

?>

<div class="container py-4 py-md-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">

            <p class="small text-muted mb-2">
                Versión <?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8'); ?>
                · Fecha <?= htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8'); ?>
            </p>

            <h1 class="h3 mb-3">Aviso de Privacidad Integral</h1>

            <?php if ($mensaje !== ''): ?>
                <div class="alert alert-info py-2">
                    <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if ($contenido !== ''): ?>
                <article class="pm-aviso-contenido" style="white-space: pre-wrap; line-height: 1.55;">
<?= htmlspecialchars($contenido, ENT_QUOTES, 'UTF-8'); ?>
                </article>
            <?php else: ?>
                <p class="text-muted">
                    No hay contenido publicado del aviso.
                </p>
            <?php endif; ?>

            <p class="small text-muted mt-4">
                Responsable del tratamiento:
                <?= htmlspecialchars((string) ($r['nombre_consultorio'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>.
                Sistema: PsicoMatch (crédito de desarrollo).
            </p>

            <a href="<?= Helper::baseUrl(''); ?>" class="btn btn-outline-secondary btn-sm mt-2">
                Volver al inicio
            </a>
        </div>
    </div>
</div>
