<?php

?>

<div class="auth-brand-header text-center text-lg-start mb-4">

    <?php if ($logoUrl !== ''): ?>

        <img
            src="<?= htmlspecialchars(
                $logoUrl,
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
            class="auth-brand-logo"
            alt="<?= htmlspecialchars(
                'Logotipo de ' . $nombreCons,
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
        >

    <?php else: ?>

        <div
            class="auth-brand-logo-fallback"
            aria-hidden="true"
        >
            <?= htmlspecialchars(
                $iniciales,
                ENT_QUOTES,
                'UTF-8'
            ); ?>
        </div>

    <?php endif; ?>

    <h2 class="auth-brand-name">
        <?= htmlspecialchars(
            $nombreCons,
            ENT_QUOTES,
            'UTF-8'
        ); ?>
    </h2>

</div>
