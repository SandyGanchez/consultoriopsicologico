<section class="hero">

    <div class="container">

        <div class="row align-items-center min-vh-100">

            <!-- COLUMNA IZQUIERDA -->
            <div class="col-lg-6">

                <span class="badge rounded-pill mb-3 px-3 py-2"
                      style="background:#FDE8D3;color:#657166;">
                    Tu bienestar emocional es nuestra prioridad
                </span>

                <h1 class="display-4 fw-bold mb-3">
                    <?= htmlspecialchars($consultorio['NombreCons']); ?>
                </h1>

                <h3 class="mb-4" style="color:#99CDD8;">
                    <?= htmlspecialchars($consultorio['Slogan']); ?>
                </h3>

                <p class="lead text-secondary">
                    <?= htmlspecialchars($consultorio['Descripcion']); ?>
                </p>

                <div class="mt-4">

                    <a href="#" class="btn btn-lg rounded-pill px-4 me-3"
                       style="background:#99CDD8;color:white;">

                        Crear cuenta

                    </a>

                    <a href="#" class="btn btn-lg rounded-pill px-4"
                       style="border:2px solid #657166;color:#657166;">

                        Iniciar sesión

                    </a>

                </div>

                <!-- ESTADÍSTICAS -->
                <div class="row mt-5">

                    <div class="col-4 text-center">

                        <h3 class="fw-bold" style="color:#99CDD8;">
                            <?= count($servicios); ?>+
                        </h3>

                        <small>Servicios</small>

                    </div>

                    <div class="col-4 text-center">

                        <h3 class="fw-bold" style="color:#99CDD8;">
                            <?= count($horarios); ?>
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

                <img
                    src="<?= \App\Helpers\Helper::baseUrl('assets/img/portada/' . ($consultorio['ImagenPortada'] ?: 'hero-temporal.png')); ?>"
                    class="img-fluid hero-img"
                    alt="Portada">

            </div>

        </div>

    </div>

</section>