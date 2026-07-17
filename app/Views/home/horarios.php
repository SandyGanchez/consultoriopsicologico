<section class="py-5" style="background:#F8F9FA;" id="horarios">

    <div class="container">

        <div class="text-center mb-5">

            <h2 class="fw-bold">Horarios de Atención</h2>

            <p class="text-muted">
                Estamos para ayudarte en los siguientes horarios.
            </p>

        </div>

        <div class="row justify-content-center">

            <div class="col-lg-8">

                <div class="card border-0 shadow-sm rounded-4">

                    <div class="card-body p-4">

                        <table class="table table-borderless align-middle">

                            <tbody>

                            <?php foreach($horarios as $horario): ?>

                                <tr>

                                    <td width="60">

                                        <i class="bi bi-clock fs-4 text-primary"></i>

                                    </td>

                                    <td>

                                        <strong>

                                            <?= htmlspecialchars($horario['DiaSemana']); ?>

                                        </strong>

                                    </td>

                                    <td class="text-end">

                                        <?= substr($horario['HoraInicio'],0,5); ?>

                                        -

                                        <?= substr($horario['HoraFin'],0,5); ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>