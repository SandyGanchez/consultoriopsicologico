<?php

?>

<div class="auth-brand-panel order-2 order-lg-1">

    <div class="auth-brand-cover">

        <?php if ($portadaUrl !== null): ?>

            <img
                src="<?= htmlspecialchars(
                    $portadaUrl,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
                class="auth-brand-cover__image"
                alt="<?= htmlspecialchars(
                    'Portada de ' . $nombreCons,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >

            <div class="auth-brand-overlay" aria-hidden="true"></div>

        <?php else: ?>

            <div
                class="auth-cover-fallback"
                role="img"
                aria-label="Ilustración relacionada con atención psicológica"
            >
                <div class="auth-cover-fallback__circle">
                    <i
                        class="bi bi-chat-heart"
                        aria-hidden="true"
                    ></i>
                </div>
                <div
                    class="auth-cover-fallback__decoration auth-cover-fallback__decoration-one"
                ></div>
                <div
                    class="auth-cover-fallback__decoration auth-cover-fallback__decoration-two"
                ></div>
            </div>

        <?php endif; ?>

        <div class="auth-brand-cover__content auth-brand-content">

            <?php if ($slogan !== ''): ?>

                <p class="auth-brand-slogan">
                    <?= htmlspecialchars(
                        $slogan,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </p>

            <?php else: ?>

                <p class="auth-brand-slogan">
                    Tu bienestar emocional es nuestra prioridad
                </p>

            <?php endif; ?>

        </div>

    </div>

</div>
