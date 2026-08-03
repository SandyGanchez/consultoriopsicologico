<?php

$redes = is_array($redes ?? null) ? $redes : [];

$iconos = [
    'Facebook' => 'bi-facebook',
    'Instagram' => 'bi-instagram',
    'WhatsApp' => 'bi-whatsapp',
    'TikTok' => 'bi-tiktok',
    'YouTube' => 'bi-youtube',
    'LinkedIn' => 'bi-linkedin',
    'Página Web' => 'bi-globe'
];

if ($redes === []) {
    return;
}

?>

<section class="py-5" style="background:#DAEBE3;" id="redes" aria-labelledby="redes-titulo">

    <div class="container text-center">

        <h2 id="redes-titulo" class="fw-bold mb-4">
            Síguenos
        </h2>

        <div class="d-flex flex-wrap justify-content-center gap-2">
            <?php foreach ($redes as $red): ?>
                <?php
                $tipo = (string) ($red['TipoRed'] ?? '');
                $url = (string) ($red['URLRed'] ?? '');
                $etiqueta = trim((string) ($red['EtiquetaRed'] ?? ''));
                $label = $etiqueta !== '' ? $etiqueta : $tipo;
                $icono = $iconos[$tipo] ?? 'bi-globe';
                ?>
                <a
                    href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn btn-light rounded-circle mx-1"
                    aria-label="<?= htmlspecialchars('Abrir ' . $label . ' en una pestaña nueva', ENT_QUOTES, 'UTF-8'); ?>"
                >
                    <i class="bi <?= htmlspecialchars($icono, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                </a>
            <?php endforeach; ?>
        </div>

    </div>

</section>
