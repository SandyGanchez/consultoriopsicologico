<?php

use App\Helpers\Helper;

$especialistas = $especialistas ?? [];

?>

<section
    id="especialistas"
    class="py-5"
    style="background-color: #f7f9f8;"
>

    <div class="container">

        <div class="text-center mb-5">

            <span
                class="d-inline-block fw-bold text-uppercase mb-2"
                style="
                    color: #657166;
                    font-size: .75rem;
                    letter-spacing: .1em;
                "
            >
                Atención profesional
            </span>

            <h2
                class="fw-bold"
                style="color: #657166;"
            >
                Nuestros especialistas
            </h2>

            <p
                class="mx-auto"
                style="
                    max-width: 680px;
                    color: rgba(101, 113, 102, .8);
                "
            >
                Conoce a los profesionales disponibles,
                consulta sus servicios y agenda una cita
                con el especialista de tu elección.
            </p>

        </div>

        <?php if (empty($especialistas)): ?>

            <div
                class="text-center p-5 rounded-4"
                style="background-color: #ffffff;"
            >

                <i
                    class="bi bi-person-heart d-block mb-3"
                    style="
                        color: #99CDD8;
                        font-size: 3rem;
                    "
                ></i>

                <h3
                    class="h5 fw-bold"
                    style="color: #657166;"
                >
                    No hay especialistas disponibles
                </h3>

                <p class="text-muted mb-0">
                    Próximamente podrás consultar a nuestros
                    profesionales.
                </p>

            </div>

        <?php else: ?>

            <div class="row g-4">

                <?php foreach (
                    $especialistas as $especialista
                ): ?>

                    <?php
                    $nombreFoto = trim(
                        (string) (
                            $especialista[
                                'FotoPerfilPer'
                            ] ?? ''
                        )
                    );

                    $rutaFoto = $nombreFoto !== ''
                        ? Helper::baseUrl(
                            'uploads/perfiles/' .
                            rawurlencode($nombreFoto)
                        )
                        : Helper::baseUrl(
                            'assets/img/default.png'
                        );
                    ?>

                    <div class="col-12 col-md-6 col-xl-4">

                        <article
                            class="especialista-card h-100"
                        >

                            <div class="especialista-card-header">

                                <img
                                    src="<?= htmlspecialchars(
                                        $rutaFoto,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>"
                                    alt="Fotografía de <?= htmlspecialchars(
                                        $especialista[
                                            'NombreCompleto'
                                        ],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>"
                                    class="especialista-foto"
                                    onerror="
                                        this.onerror = null;
                                        this.src = '<?= Helper::baseUrl(
                                            'assets/img/default.png'
                                        ); ?>';
                                    "
                                >

                            </div>

                            <div class="especialista-card-body">

                                <h3>
                                    <?= htmlspecialchars(
                                        $especialista[
                                            'NombreCompleto'
                                        ]
                                    ); ?>
                                </h3>

                                <span class="especialista-especialidad">

                                    <?= htmlspecialchars(
                                        $especialista[
                                            'EspecialidadPsi'
                                        ]
                                    ); ?>

                                </span>

                                <?php if (
                                    !empty(
                                        $especialista[
                                            'DescripcionProfesional'
                                        ]
                                    )
                                ): ?>

                                    <p class="especialista-descripcion">

                                        <?= htmlspecialchars(
                                            $especialista[
                                                'DescripcionProfesional'
                                            ]
                                        ); ?>

                                    </p>

                                <?php endif; ?>

                                <div class="especialista-servicios">

                                    <strong>
                                        Servicios:
                                    </strong>

                                    <?php if (
                                        empty(
                                            $especialista['servicios']
                                        )
                                    ): ?>

                                        <span class="text-muted small">
                                            Sin servicios publicados.
                                        </span>

                                    <?php else: ?>

                                        <div class="d-flex flex-wrap gap-2 mt-2">

                                            <?php foreach (
                                                array_slice(
                                                    $especialista[
                                                        'servicios'
                                                    ],
                                                    0,
                                                    3
                                                ) as $servicio
                                            ): ?>

                                                <span
                                                    class="especialista-servicio-badge"
                                                >
                                                    <?= htmlspecialchars(
                                                        $servicio[
                                                            'NombreServicio'
                                                        ]
                                                    ); ?>
                                                </span>

                                            <?php endforeach; ?>

                                        </div>

                                    <?php endif; ?>

                                </div>

                            </div>

                            <div class="especialista-card-footer">

                                <a
                                    href="<?= Helper::baseUrl(
                                        'especialista/perfil'
                                    ); ?>?id=<?= urlencode(
                                        $especialista['ClvPsi']
                                    ); ?>"
                                    class="btn especialista-btn-perfil"
                                >
                                    <i class="bi bi-person-vcard"></i>
                                    Ver perfil
                                </a>

                                <a
                                    href="<?= Helper::baseUrl(
                                        'agendar-cita'
                                    ); ?>?psicologo=<?= urlencode(
                                        $especialista['ClvPsi']
                                    ); ?>"
                                    class="btn especialista-btn-agendar"
                                >
                                    <i class="bi bi-calendar-check"></i>
                                    Agendar cita
                                </a>

                            </div>

                        </article>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</section>