<section class="py-5"  id="informacion">

    <div class="container">

        <div class="text-center mb-5">

            <h2 class="fw-bold">
                Información del Consultorio
            </h2>

        </div>

        <div class="row">

            <div class="col-md-4 text-center">

                <i class="bi bi-geo-alt-fill fs-1 text-primary"></i>

                <h5 class="mt-3">Dirección</h5>

                <p>

                    <?= htmlspecialchars($consultorio['CalleDir']); ?>

                    <?= htmlspecialchars($consultorio['NumExtDir']); ?>

                    <br>

                    <?= htmlspecialchars($consultorio['ColoniaDir']); ?>

                    <br>

                    <?= htmlspecialchars($consultorio['MunicipioDir']); ?>,
                    <?= htmlspecialchars($consultorio['EstadoDir']); ?>

                </p>

            </div>

            <div class="col-md-4 text-center">

                <i class="bi bi-telephone-fill fs-1 text-primary"></i>

                <h5 class="mt-3">Teléfono</h5>

                <p>

                    <?= htmlspecialchars($consultorio['TelefonoCons']); ?>

                </p>

            </div>

            <div class="col-md-4 text-center">

                <i class="bi bi-envelope-fill fs-1 text-primary"></i>

                <h5 class="mt-3">Correo</h5>

                <p>

                    <?= htmlspecialchars($consultorio['CorreoElectronico']); ?>

                </p>

            </div>

        </div>

    </div>

</section>