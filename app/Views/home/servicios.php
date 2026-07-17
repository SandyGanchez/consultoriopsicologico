<section class="py-5" id="servicios">

    <div class="container">

        <div class="text-center mb-5">

            <h2 class="fw-bold">Nuestros Servicios</h2>

            <p class="text-muted">
                Atención psicológica profesional para todas las etapas de la vida.
            </p>

        </div>

        <?php
        $iconos = [
            'Terapia Individual'      => 'bi-person-heart',
            'Terapia de Pareja'       => 'bi-heart',
            'Terapia Familiar'        => 'bi-people',
            'Evaluación Psicológica'  => 'bi-clipboard2-pulse'
        ];
        ?>

        <div class="row g-4">

            <?php foreach($servicios as $servicio): ?>

                <div class="col-md-6 col-lg-3">

                    <div class="card servicio-card h-100 border-0 shadow-sm">

                        <div class="card-body text-center p-4">

                            <div class="icono-servicio mb-3">

                                <i class="bi <?= $iconos[$servicio['NombreServicio']] ?? 'bi-stars'; ?>"></i>

                            </div>

                            <h5 class="fw-bold">

                                <?= htmlspecialchars($servicio['NombreServicio']); ?>

                            </h5>

                            <p class="text-muted">

                                <?= htmlspecialchars($servicio['Descripcion']); ?>

                            </p>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

</section>  