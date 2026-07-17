<?php

use App\Helpers\Helper;

?>

<section class="psicologo-dashboard">

    <div class="psicologo-page-header">

        <div>

            <span class="psicologo-page-eyebrow">
                Catálogo personal
            </span>

            <h1>
                Mis servicios
            </h1>

            <p>
                Administra los servicios que ofreces.
            </p>

        </div>

        <a
            href="<?= Helper::baseUrl(
                'psicologo/servicios/nuevo'
            ); ?>"
            class="btn btn-primary"
        >
            <i class="bi bi-plus-circle"></i>

            Nuevo servicio

        </a>

    </div>

    <div class="psicologo-panel">

        <div class="table-responsive">

            <table
                class="table align-middle"
            >

                <thead>

                    <tr>

                        <th>
                            Servicio
                        </th>

                        <th>
                            Precio
                        </th>

                        <th>
                            Duración
                        </th>

                        <th>
                            Estado
                        </th>

                        <th width="170">
                            Acciones
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if(
                    empty($servicios)
                ): ?>

                    <tr>

                        <td
                            colspan="5"
                            class="text-center py-5"
                        >

                            No tienes servicios registrados.

                        </td>

                    </tr>

                <?php else: ?>

                <?php foreach(
                    $servicios as $servicio
                ): ?>

                    <tr>

                        <td>

                            <strong>

                                <?= htmlspecialchars(
                                    $servicio[
                                        'NombreServicio'
                                    ]
                                ); ?>

                            </strong>

                            <br>

                            <small>

                                <?= htmlspecialchars(
                                    $servicio[
                                        'DescripcionServicio'
                                    ]
                                ); ?>

                            </small>

                        </td>

                        <td>

                            $
                            <?= number_format(
                                $servicio[
                                    'PrecioServicio'
                                ],
                                2
                            ); ?>

                        </td>

                        <td>

                            <?= $servicio[
                                'DuracionMinutos'
                            ]; ?>

                            min

                        </td>

                        <td>

                            <span
                                class="badge bg-success"
                            >

                                <?= htmlspecialchars(
                                    $servicio[
                                        'EstatusAsignacion'
                                    ]
                                ); ?>

                            </span>

                        </td>

                        <td>

                            <a
                                href="#"
                                class="btn btn-warning btn-sm"
                            >

                                <i class="bi bi-pencil"></i>

                            </a>

                            <a
                                href="#"
                                class="btn btn-secondary btn-sm"
                            >

                                <i class="bi bi-toggle-on"></i>

                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</section>