<?php

use App\Helpers\Helper;

$mensaje = trim((string) ($mensaje ?? 'El contenido solicitado no está disponible.'));
$esNavbarGlobal = true;
$esPortadaPlataforma = true;
$identidadPlataforma = true;
$consultorio = null;

?>

<section class="py-5">
    <div class="container py-5 text-center">
        <div class="mx-auto" style="max-width:560px;">
            <i class="bi bi-shield-lock fs-1 text-muted d-block mb-3" aria-hidden="true"></i>
            <h1 class="h3" style="color:#657166;">
                No disponible
            </h1>
            <p class="lead text-muted">
                <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
            </p>
            <a
                href="<?= Helper::baseUrl(''); ?>"
                class="btn rounded-pill px-4 text-white"
                style="background:#99CDD8;"
            >
                Volver al inicio
            </a>
        </div>
    </div>
</section>
