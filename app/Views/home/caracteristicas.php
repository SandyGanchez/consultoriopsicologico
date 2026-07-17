<section class="py-5" style="background:#F8F9FA;">

    <div class="container">

        <div class="text-center mb-5">

            <h2 class="fw-bold">

                ¿Por qué elegirnos?

            </h2>

            <p class="text-muted">

                Nuestro compromiso es brindarte una atención profesional y humana.

            </p>

        </div>

        <div class="row g-4">

            <?php foreach($caracteristicas as $car): ?>

                <div class="col-md-4">

                    <div class="card border-0 shadow-sm h-100 rounded-4">

                        <div class="card-body text-center p-4">

                            <div class="icono-servicio mb-3">

                                <i class="bi <?= htmlspecialchars($car['Icono']); ?>"></i>

                            </div>

                            <h4>

                                <?= htmlspecialchars($car['Titulo']); ?>

                            </h4>

                            <p class="text-muted">

                                <?= htmlspecialchars($car['Descripcion']); ?>

                            </p>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

</section>