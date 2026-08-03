<?php

use App\Helpers\Helper;

$mensaje = (string) ($mensaje ?? '');
$faltantes = is_array($faltantes ?? null) ? $faltantes : [];

?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="h3 mb-3">Aviso de Privacidad no disponible</h1>
            <p class="text-muted">
                <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
            </p>

            <?php if ($faltantes !== []): ?>
                <p class="mb-2">Información faltante:</p>
                <ul>
                    <?php foreach ($faltantes as $item): ?>
                        <li><?= htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <a href="<?= Helper::baseUrl(''); ?>" class="btn btn-outline-secondary btn-sm">
                Volver al inicio
            </a>
        </div>
    </div>
</div>
