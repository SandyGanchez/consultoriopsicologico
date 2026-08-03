<section class="hero" id="inicio">

    <?php
        $modoVistaPrevia = !empty($modoVistaPrevia);
    ?>

    <?php if (empty($consultorio)): ?>

        <div class="container py-5 text-center">
            <p class="lead text-muted">
                Este consultorio todavía no se encuentra disponible públicamente.
            </p>
        </div>

    <?php else: ?>

    <div class="container">

        <div class="row align-items-center min-vh-100">

            <!-- COLUMNA IZQUIERDA -->
            <div class="col-lg-6">

                <?php
                    $logoHero = \App\Helpers\Helper::logotipoConsultorioUrl(
                        $consultorio['LogotipoCons'] ?? ''
                    );
                ?>

                <?php if ($logoHero !== ''): ?>

                    <img
                        src="<?= htmlspecialchars($logoHero); ?>"
                        alt="Logotipo de <?= htmlspecialchars(
                            $consultorio['NombreCons'] ?? ''
                        ); ?>"
                        class="logo-home mb-3"
                    >

                <?php endif; ?>

                <span class="badge rounded-pill mb-3 px-3 py-2"
                      style="background:#FDE8D3;color:#657166;">
                    Tu bienestar emocional es nuestra prioridad
                </span>

                <h1 class="display-4 fw-bold mb-3">
                    <?= htmlspecialchars($consultorio['NombreCons'] ?? ''); ?>
                </h1>

                <?php if (!empty($consultorio['Slogan'])): ?>

                    <h3 class="mb-4" style="color:#99CDD8;">
                        <?= htmlspecialchars($consultorio['Slogan']); ?>
                    </h3>

                <?php endif; ?>

                <?php if (!empty($consultorio['Descripcion'])): ?>

                    <p class="lead text-secondary">
                        <?= htmlspecialchars($consultorio['Descripcion']); ?>
                    </p>

                <?php elseif ($modoVistaPrevia): ?>

                    <p class="lead text-secondary fst-italic">
                        Agrega una descripción del consultorio.
                    </p>

                <?php endif; ?>

                <div class="mt-4">

                    <a href="<?= \App\Helpers\Helper::baseUrl('registro'); ?>"
                       class="btn btn-lg rounded-pill px-4 me-3"
                       style="background:#99CDD8;color:white;">

                        Crear cuenta

                    </a>

                    <a href="<?= \App\Helpers\Helper::baseUrl('login'); ?>"
                       class="btn btn-lg rounded-pill px-4"
                       style="border:2px solid #657166;color:#657166;">

                        Iniciar sesión

                    </a>

                </div>

                <!-- ESTADÍSTICAS -->
                <div class="row mt-5">

                    <div class="col-4 text-center">

                        <h3 class="fw-bold" style="color:#99CDD8;">
                            <?= count($servicios ?? []); ?>+
                        </h3>

                        <small>Servicios</small>

                    </div>

                    <div class="col-4 text-center">

                        <h3 class="fw-bold" style="color:#99CDD8;">
                            <?= (int) ($diasAtencion ?? 0); ?>
                        </h3>

                        <small>Días de atención</small>

                    </div>

                    <div class="col-4 text-center">

                        <h3 class="fw-bold" style="color:#99CDD8;">
                            100%
                        </h3>

                        <small>Compromiso</small>

                    </div>

                </div>

            </div>

            <!-- COLUMNA DERECHA -->
            <div class="col-lg-6 text-center">

                <?php
                    $portadaUrl = \App\Helpers\Helper::imagenPortadaConsultorioUrl(
                        $consultorio['ImagenPortada'] ?? null
                    );

                    $heroAlt =
                        'Portada de '
                        . ($consultorio['NombreCons'] ?? 'PsicoMatch');
                ?>

                <?php if ($portadaUrl !== null): ?>

                    <img
                        src="<?= htmlspecialchars(
                            $portadaUrl,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        class="img-fluid hero-img"
                        alt="<?= htmlspecialchars(
                            $heroAlt,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                    >

                <?php else: ?>

                    <div
                        class="hero-psychology-fallback"
                        role="img"
                        aria-label="Ilustración relacionada con atención psicológica"
                    >
                        <div class="hero-fallback-circle">
                            <i
                                class="bi bi-chat-heart"
                                aria-hidden="true"
                            ></i>
                        </div>
                        <div
                            class="hero-fallback-decoration hero-fallback-decoration-one"
                        ></div>
                        <div
                            class="hero-fallback-decoration hero-fallback-decoration-two"
                        ></div>
                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

    <?php endif; ?>

</section>