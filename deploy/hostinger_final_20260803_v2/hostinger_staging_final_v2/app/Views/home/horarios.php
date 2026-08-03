<?php

use App\Helpers\Helper;

$horarios = $horarios ?? [];
$modoVistaPrevia = !empty($modoVistaPrevia);

?>

<section class="py-5 public-schedule-section" id="horarios">

    <div class="container">

        <div class="text-center mb-5">

            <h2 class="fw-bold public-schedule-section__title">
                Horarios de Atención
            </h2>

            <p class="text-muted">
                Estamos para ayudarte en los siguientes horarios.
            </p>

        </div>

        <?php if ($horarios === []): ?>

            <div class="text-center text-muted">
                <?= $modoVistaPrevia
                    ? 'Aún no hay horarios disponibles.'
                    : 'Los horarios aún no están disponibles.'; ?>
            </div>

        <?php else: ?>

            <div class="row justify-content-center">

                <div class="col-lg-8">

                    <div class="card border-0 shadow-sm rounded-4 public-schedule-section__card">

                        <div class="card-body p-4">

                            <table class="table table-borderless align-middle public-schedule-list mb-0">

                                <tbody>

                                <?php foreach ($horarios as $horario): ?>

                                    <?php
                                        $activo =
                                            ($horario['EstatusHorario']
                                                ?? '') === 'ACTIVO';
                                    ?>

                                    <tr>

                                        <td width="60">

                                            <i
                                                class="bi bi-clock fs-4"
                                                style="color:#99CDD8;"
                                                aria-hidden="true"
                                            ></i>

                                        </td>

                                        <td>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $horario['Etiqueta']
                                                    ?? Helper::etiquetaDiaHorario(
                                                        $horario['DiaSemana']
                                                        ?? ''
                                                    )
                                                ); ?>

                                            </strong>

                                        </td>

                                        <td class="text-end">

                                            <?php if ($activo): ?>

                                                <?= htmlspecialchars(
                                                    Helper::formatearHoraPublica(
                                                        $horario['HoraInicio']
                                                        ?? ''
                                                    )
                                                ); ?>

                                                –

                                                <?= htmlspecialchars(
                                                    Helper::formatearHoraPublica(
                                                        $horario['HoraFin']
                                                        ?? ''
                                                    )
                                                ); ?>

                                            <?php else: ?>

                                                <span class="text-muted">
                                                    Cerrado
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    </div>

                </div>

            </div>

        <?php endif; ?>

    </div>

</section>
