<?php

use App\Helpers\Helper;

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;

unset(
    $_SESSION['success'],
    $_SESSION['error']
);

?>

<div class="container-fluid">

    <!-- ENCABEZADO -->

    <div class="card shadow-sm mb-4 paciente-card-header">

        <div class="card-body p-4">

            <h2>

                <i class="bi bi-calendar-plus-fill me-2"></i>

                Agendar una cita

            </h2>

            <p class="mb-0">

                Selecciona el psicólogo, servicio, fecha y horario
                para programar tu próxima sesión.

            </p>

        </div>

    </div>


    <!-- MENSAJES -->

    <?php if ($success): ?>

        <div class="alert alert-success">

            <i class="bi bi-check-circle-fill me-2"></i>

            <?= htmlspecialchars($success); ?>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-triangle-fill me-2"></i>

            <?= htmlspecialchars($error); ?>

        </div>

    <?php endif; ?>


    <!-- FORMULARIO -->

    <div class="card shadow-sm">

        <div class="card-header paciente-card-title">

            <i class="bi bi-journal-medical me-2"></i>

            Información de la cita

        </div>


        <div class="card-body">

            <form
                action="<?= Helper::baseUrl('paciente/guardar-cita'); ?>"
                method="POST"
                id="formAgendarCita"
            >

                <div class="row g-4">


                    <!-- PSICÓLOGO -->

                    <div class="col-md-6">

                        <label
                            for="psicologo"
                            class="form-label fw-semibold"
                        >

                            Psicólogo

                        </label>

                        <select
                            name="psicologo"
                            id="psicologo"
                            class="form-select"
                            required
                        >

                            <option value="">

                                Selecciona un psicólogo

                            </option>

                            <?php foreach ($psicologos as $psicologo): ?>

                                <option
                                    value="<?= htmlspecialchars($psicologo['ClvPsi']); ?>"
                                >

                                    <?= htmlspecialchars(
                                        $psicologo['NombrePsicologo']
                                    ); ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- SERVICIO -->

                    <div class="col-md-6">

                        <label
                            for="servicio"
                            class="form-label fw-semibold"
                        >

                            Servicio

                        </label>

                        <select
                            name="servicio"
                            id="servicio"
                            class="form-select"
                            required
                        >

                            <option value="">

                                Selecciona un servicio

                            </option>

                            <?php foreach ($servicios as $servicio): ?>

                                <option
                                    value="<?= htmlspecialchars($servicio['ClvServ']); ?>"
                                >

                                    <?= htmlspecialchars(
                                        $servicio['NombreServicio']
                                    ); ?>

                                    -

                                    $<?= number_format(
                                        $servicio['CostoServicio'],
                                        2
                                    ); ?>

                                    -
                                    <?= (int) $servicio['DuracionMinutos']; ?>
                                    min.

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- FECHA -->

                    <div class="col-md-6">

                        <label
                            for="fecha"
                            class="form-label fw-semibold"
                        >

                            Fecha

                        </label>

                        <input
                            type="date"
                            name="fecha"
                            id="fecha"
                            class="form-control"
                            min="<?= date('Y-m-d'); ?>"
                            required
                        >

                        <small
                            class="text-muted"
                            id="mensajeFecha"
                        >

                            Primero selecciona un psicólogo y una fecha.

                        </small>

                    </div>


                    <!-- HORA -->

                    <div class="col-md-6">

                        <label
                            for="hora"
                            class="form-label fw-semibold"
                        >

                            Hora

                        </label>

                        <select
                            name="hora"
                            id="hora"
                            class="form-select"
                            required
                            disabled
                        >

                            <option value="">

                                Selecciona primero psicólogo y fecha

                            </option>

                        </select>

                        <small
                            class="text-muted"
                            id="mensajeHorario"
                        >

                            Los horarios aparecerán automáticamente.

                        </small>

                    </div>

                </div>


                <!-- INFORMACIÓN -->

                <div
                    id="infoCita"
                    class="alert alert-light border mt-4 d-none"
                >

                    <div class="d-flex align-items-center">

                        <i class="bi bi-info-circle-fill me-2"></i>

                        <span id="textoInfoCita"></span>

                    </div>

                </div>


                <hr class="my-4">


                <!-- BOTONES -->

                <div class="d-flex justify-content-end gap-3">

                    <a
                        href="<?= Helper::baseUrl('paciente'); ?>"
                        class="btn btn-outline-secondary px-4"
                    >

                        <i class="bi bi-x-circle me-2"></i>

                        Cancelar

                    </a>


                    <button
                        type="submit"
                        id="btnConfirmar"
                        class="btn btn-success px-4"
                        disabled
                    >

                        <i class="bi bi-check-circle me-2"></i>

                        Confirmar cita

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const psicologo =
            document.getElementById('psicologo');

        const servicio =
            document.getElementById('servicio');

        const fecha =
            document.getElementById('fecha');

        const hora =
            document.getElementById('hora');

        const btnConfirmar =
            document.getElementById('btnConfirmar');

        const mensajeHorario =
            document.getElementById('mensajeHorario');

        const mensajeFecha =
            document.getElementById('mensajeFecha');

        const infoCita =
            document.getElementById('infoCita');

        const textoInfoCita =
            document.getElementById('textoInfoCita');


        /*
        =====================================
                URL DE CONSULTA
        =====================================
        */

        const urlHorarios =
            '<?= Helper::baseUrl('paciente/horarios-disponibles'); ?>';


        /*
        =====================================
                CARGAR HORARIOS
        =====================================
        */

        async function cargarHorarios() {

            hora.innerHTML = '';

            hora.disabled = true;

            btnConfirmar.disabled = true;

            infoCita.classList.add('d-none');


            if (
                psicologo.value === '' ||
                fecha.value === '' ||
                servicio.value === ''
            ) {

                hora.innerHTML = `
                    <option value="">
                        Selecciona psicólogo, servicio y fecha
                    </option>
                `;

                mensajeHorario.textContent =
                    'Los horarios aparecerán automáticamente.';

                return;
            }


            /*
            =====================================
                    CARGANDO
            =====================================
            */

            hora.innerHTML = `
                <option value="">
                    Consultando horarios...
                </option>
            `;

            mensajeHorario.textContent =
                'Consultando disponibilidad...';


            try {

                const parametros =
                    new URLSearchParams({

                        psicologo:
                            psicologo.value,

                        fecha:
                            fecha.value,

                        servicio:
                            servicio.value

                    });


                const respuesta =
                    await fetch(
                        urlHorarios + '?' + parametros.toString(),
                        {
                            method: 'GET',
                            headers: {
                                'Accept':
                                    'application/json'
                            }
                        }
                    );


                if (!respuesta.ok) {

                    throw new Error(
                        'No fue posible consultar los horarios.'
                    );

                }


                const datos =
                    await respuesta.json();


                hora.innerHTML = '';


                if (
                    !datos.success ||
                    !datos.horarios ||
                    datos.horarios.length === 0
                ) {

                    hora.innerHTML = `
                        <option value="">
                            No hay horarios disponibles
                        </option>
                    `;

                    mensajeHorario.textContent =
                        datos.mensaje ||
                        'No hay horarios disponibles para esa fecha.';

                    return;
                }


                /*
                =====================================
                        OPCIÓN INICIAL
                =====================================
                */

                const opcionInicial =
                    document.createElement('option');

                opcionInicial.value = '';

                opcionInicial.textContent =
                    'Selecciona un horario';

                hora.appendChild(
                    opcionInicial
                );


                /*
                =====================================
                        HORARIOS
                =====================================
                */

                datos.horarios.forEach(
                    function (horarioDisponible) {

                        const opcion =
                            document.createElement('option');

                        opcion.value =
                            horarioDisponible.valor;

                        opcion.textContent =
                            horarioDisponible.texto;

                        hora.appendChild(
                            opcion
                        );

                    }
                );


                hora.disabled = false;

                mensajeHorario.textContent =
                    datos.horarios.length +
                    ' horario(s) disponible(s).';


                /*
                =====================================
                        INFORMACIÓN
                =====================================
                */

                textoInfoCita.textContent =
                    'Selecciona uno de los horarios disponibles para continuar.';

                infoCita.classList.remove(
                    'd-none'
                );

            } catch (error) {

                console.error(error);

                hora.innerHTML = `
                    <option value="">
                        Error al consultar horarios
                    </option>
                `;

                mensajeHorario.textContent =
                    'No fue posible consultar los horarios. Intenta nuevamente.';

            }

        }


        /*
        =====================================
                CAMBIO DE PSICÓLOGO
        =====================================
        */

        psicologo.addEventListener(
            'change',
            function () {

                cargarHorarios();

            }
        );


        /*
        =====================================
                CAMBIO DE SERVICIO
        =====================================
        */

        servicio.addEventListener(
            'change',
            function () {

                cargarHorarios();

            }
        );


        /*
        =====================================
                CAMBIO DE FECHA
        =====================================
        */

        fecha.addEventListener(
            'change',
            function () {

                cargarHorarios();

            }
        );


        /*
        =====================================
                CAMBIO DE HORA
        =====================================
        */

        hora.addEventListener(
            'change',
            function () {

                if (hora.value !== '') {

                    btnConfirmar.disabled =
                        false;

                    mensajeHorario.textContent =
                        'Horario seleccionado correctamente.';

                } else {

                    btnConfirmar.disabled =
                        true;

                }

            }
        );


        /*
        =====================================
                VALIDACIÓN FINAL
        =====================================
        */

        document
            .getElementById('formAgendarCita')
            .addEventListener(
                'submit',
                function (evento) {

                    if (
                        psicologo.value === '' ||
                        servicio.value === '' ||
                        fecha.value === '' ||
                        hora.value === ''
                    ) {

                        evento.preventDefault();

                        alert(
                            'Completa todos los campos antes de confirmar la cita.'
                        );

                    }

                }
            );

    }
);

</script>