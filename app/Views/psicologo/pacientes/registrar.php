<?php

use App\Core\Session;
use App\Helpers\Helper;

$datos = $datos ?? [];
$errores = $errores ?? [];
$servicios = $servicios ?? [];
$csrf = Session::csrfToken();

$valor = static function (string $campo) use ($datos): string {
    return htmlspecialchars(
        (string) ($datos[$campo] ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
};

$urlHorarios = Helper::baseUrl(
    'psicologo/agenda/horarios-disponibles'
);

?>

<section class="psychologist-patients-page">

    <header class="psychologist-patients-header">
        <div>
            <h1>Registrar paciente y agendar</h1>
            <p>
                Crea la cuenta pendiente y su primera cita. El paciente activará
                su acceso con un enlace seguro.
            </p>
        </div>
        <a
            href="<?= Helper::baseUrl('psicologo/pacientes'); ?>"
            class="btn psychologist-patients-search-btn"
        >
            Volver
        </a>
    </header>

    <?php if (!empty($errores['general'])): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars((string) $errores['general']); ?>
        </div>
    <?php endif; ?>

    <form
        method="POST"
        action="<?= Helper::baseUrl('psicologo/pacientes/guardar-nuevo'); ?>"
        id="formPacienteNuevo"
        class="consultorio-dashboard-panel"
        style="padding:1.5rem;"
        novalidate
    >
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
        >

        <div id="paso1PacienteNuevo">
            <h2 class="h5 mb-3">Paso 1 · Datos esenciales</h2>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="nombre">Nombre</label>
                    <input
                        class="form-control<?= isset($errores['nombre']) ? ' is-invalid' : ''; ?>"
                        type="text"
                        name="nombre"
                        id="nombre"
                        value="<?= $valor('nombre'); ?>"
                        required
                    >
                    <?php if (isset($errores['nombre'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errores['nombre']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="apellidoPaterno">Apellido paterno</label>
                    <input
                        class="form-control<?= isset($errores['apellidoPaterno']) ? ' is-invalid' : ''; ?>"
                        type="text"
                        name="apellidoPaterno"
                        id="apellidoPaterno"
                        value="<?= $valor('apellidoPaterno'); ?>"
                        required
                    >
                    <?php if (isset($errores['apellidoPaterno'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errores['apellidoPaterno']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="apellidoMaterno">Apellido materno (opcional)</label>
                    <input
                        class="form-control"
                        type="text"
                        name="apellidoMaterno"
                        id="apellidoMaterno"
                        value="<?= $valor('apellidoMaterno'); ?>"
                    >
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="fechaNacimiento">Fecha de nacimiento</label>
                    <input
                        class="form-control<?= isset($errores['fechaNacimiento']) ? ' is-invalid' : ''; ?>"
                        type="date"
                        name="fechaNacimiento"
                        id="fechaNacimiento"
                        value="<?= $valor('fechaNacimiento'); ?>"
                        required
                    >
                    <?php if (isset($errores['fechaNacimiento'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errores['fechaNacimiento']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="genero">Género</label>
                    <select
                        class="form-select<?= isset($errores['genero']) ? ' is-invalid' : ''; ?>"
                        name="genero"
                        id="genero"
                        required
                    >
                        <option value="">Selecciona</option>
                        <?php foreach (['Masculino', 'Femenino', 'Otro'] as $generoOpt): ?>
                            <option
                                value="<?= $generoOpt; ?>"
                                <?= ($datos['genero'] ?? '') === $generoOpt ? ' selected' : ''; ?>
                            >
                                <?= $generoOpt; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errores['genero'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errores['genero']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="correo">Correo</label>
                    <input
                        class="form-control<?= isset($errores['correo']) ? ' is-invalid' : ''; ?>"
                        type="email"
                        name="correo"
                        id="correo"
                        value="<?= $valor('correo'); ?>"
                        required
                    >
                    <?php if (isset($errores['correo'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errores['correo']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="telefono">Teléfono</label>
                    <input
                        class="form-control<?= isset($errores['telefono']) ? ' is-invalid' : ''; ?>"
                        type="text"
                        name="telefono"
                        id="telefono"
                        maxlength="10"
                        inputmode="numeric"
                        value="<?= $valor('telefono'); ?>"
                        required
                    >
                    <?php if (isset($errores['telefono'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errores['telefono']); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-4 d-flex justify-content-end">
                <button type="button" class="btn psychologist-patients-primary-btn" id="btnPaso2">
                    Continuar a la cita
                </button>
            </div>
        </div>

        <div id="paso2PacienteNuevo" hidden>
            <h2 class="h5 mb-3">Paso 2 · Primera cita</h2>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="servicio">Servicio</label>
                    <select
                        class="form-select<?= isset($errores['servicio']) ? ' is-invalid' : ''; ?>"
                        name="servicio"
                        id="servicio"
                        required
                    >
                        <option value="">Selecciona un servicio</option>
                        <?php foreach ($servicios as $servicio): ?>
                            <?php
                            $selected =
                                ($datos['servicio'] ?? '') === ($servicio['ClvServ'] ?? '')
                                    ? ' selected'
                                    : '';
                            $duracion = (int) (
                                $servicio['DuracionMinutos']
                                ?? $servicio['DuracionSugerida']
                                ?? 0
                            );
                            $costo = (string) (
                                $servicio['PrecioServicio']
                                ?? $servicio['CostoServicio']
                                ?? '0'
                            );
                            ?>
                            <option
                                value="<?= htmlspecialchars((string) $servicio['ClvServ']); ?>"
                                data-duracion="<?= htmlspecialchars((string) $duracion); ?>"
                                data-costo="<?= htmlspecialchars($costo); ?>"
                                <?= $selected; ?>
                            >
                                <?= htmlspecialchars((string) ($servicio['NombreServicio'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errores['servicio'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errores['servicio']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="fecha">Fecha</label>
                    <input
                        class="form-control<?= isset($errores['fecha']) ? ' is-invalid' : ''; ?>"
                        type="date"
                        name="fecha"
                        id="fecha"
                        value="<?= $valor('fecha'); ?>"
                        required
                    >
                    <?php if (isset($errores['fecha'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errores['fecha']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="hora">Horario</label>
                    <select
                        class="form-select<?= isset($errores['hora']) ? ' is-invalid' : ''; ?>"
                        name="hora"
                        id="hora"
                        required
                    >
                        <option value="">Selecciona un horario</option>
                    </select>
                    <?php if (isset($errores['hora'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errores['hora']); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div
                class="mt-4 p-3 rounded-3"
                style="background:#DAEBE3;"
                id="resumenCitaPaciente"
            >
                <strong>Resumen</strong>
                <div id="resumenTexto">Completa servicio, fecha y horario.</div>
                <div class="mt-2">
                    Duración: <span id="resumenDuracion">—</span>
                    · Costo: <span id="resumenCosto">—</span>
                </div>
            </div>

            <div class="mt-4 d-flex justify-content-between gap-2 flex-wrap">
                <button type="button" class="btn psychologist-patients-search-btn" id="btnPaso1">
                    Regresar
                </button>
                <button
                    type="submit"
                    class="btn psychologist-patients-primary-btn"
                    id="btnConfirmarPacienteNuevo"
                >
                    <span class="btn-label">Confirmar registro y cita</span>
                    <span class="btn-spinner" hidden aria-hidden="true">Registrando…</span>
                </button>
            </div>
        </div>
    </form>
</section>

<script>
(function () {
    var paso1 = document.getElementById('paso1PacienteNuevo');
    var paso2 = document.getElementById('paso2PacienteNuevo');
    var btnPaso2 = document.getElementById('btnPaso2');
    var btnPaso1 = document.getElementById('btnPaso1');
    var servicio = document.getElementById('servicio');
    var fecha = document.getElementById('fecha');
    var hora = document.getElementById('hora');
    var form = document.getElementById('formPacienteNuevo');
    var btnSubmit = document.getElementById('btnConfirmarPacienteNuevo');
    var urlHorarios = <?= json_encode($urlHorarios, JSON_UNESCAPED_SLASHES); ?>;
    var horaSeleccionada = <?= json_encode((string) ($datos['hora'] ?? ''), JSON_UNESCAPED_UNICODE); ?>;

    function irPaso2() {
        var campos = ['nombre', 'apellidoPaterno', 'fechaNacimiento', 'genero', 'correo', 'telefono'];
        for (var i = 0; i < campos.length; i++) {
            var el = document.getElementById(campos[i]);
            if (!el || !el.value.trim()) {
                el && el.focus();
                return;
            }
        }
        paso1.hidden = true;
        paso2.hidden = false;
        actualizarResumen();
        cargarHorarios();
    }

    function irPaso1() {
        paso2.hidden = true;
        paso1.hidden = false;
    }

    function actualizarResumen() {
        var opt = servicio.options[servicio.selectedIndex];
        var nombreServ = opt && opt.value ? opt.textContent.trim() : '—';
        var duracion = opt && opt.dataset.duracion ? opt.dataset.duracion + ' min' : '—';
        var costo = opt && opt.dataset.costo ? ('$' + opt.dataset.costo) : '—';
        document.getElementById('resumenDuracion').textContent = duracion;
        document.getElementById('resumenCosto').textContent = costo;
        document.getElementById('resumenTexto').textContent =
            nombreServ +
            (fecha.value ? (' · ' + fecha.value) : '') +
            (hora.value ? (' · ' + hora.value.substring(0, 5)) : '');
    }

    function cargarHorarios() {
        hora.innerHTML = '<option value="">Cargando…</option>';
        if (!servicio.value || !fecha.value) {
            hora.innerHTML = '<option value="">Selecciona un horario</option>';
            return;
        }

        var url = urlHorarios +
            '?servicio=' + encodeURIComponent(servicio.value) +
            '&fecha=' + encodeURIComponent(fecha.value);

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                hora.innerHTML = '<option value="">Selecciona un horario</option>';
                var slots = (data && data.espacios) || (data && data.horarios) || [];
                if (!Array.isArray(slots)) {
                    slots = [];
                }
                slots.forEach(function (slot) {
                    var valor = typeof slot === 'string'
                        ? slot
                        : (slot.valor || slot.horaInicio || slot.hora || slot.inicio || '');
                    if (!valor) return;
                    var option = document.createElement('option');
                    option.value = valor.length === 5 ? valor + ':00' : valor;
                    option.textContent = (slot.texto || valor).toString().substring(0, 5);
                    if (
                        horaSeleccionada &&
                        (option.value === horaSeleccionada ||
                            option.value.substring(0, 5) === horaSeleccionada.substring(0, 5))
                    ) {
                        option.selected = true;
                    }
                    hora.appendChild(option);
                });
                actualizarResumen();
            })
            .catch(function () {
                hora.innerHTML = '<option value="">No fue posible cargar horarios</option>';
            });
    }

    btnPaso2 && btnPaso2.addEventListener('click', irPaso2);
    btnPaso1 && btnPaso1.addEventListener('click', irPaso1);
    servicio && servicio.addEventListener('change', function () {
        actualizarResumen();
        cargarHorarios();
    });
    fecha && fecha.addEventListener('change', function () {
        actualizarResumen();
        cargarHorarios();
    });
    hora && hora.addEventListener('change', actualizarResumen);

    var enviando = false;
    form && form.addEventListener('submit', function (ev) {
        if (enviando) {
            ev.preventDefault();
            return;
        }
        enviando = true;
        if (btnSubmit) {
            btnSubmit.disabled = true;
            var label = btnSubmit.querySelector('.btn-label');
            var spinner = btnSubmit.querySelector('.btn-spinner');
            if (label) label.hidden = true;
            if (spinner) spinner.hidden = false;
            else btnSubmit.textContent = 'Registrando…';
        }
    });

    <?php if (!empty($errores)): ?>
    irPaso2();
    <?php endif; ?>
})();
</script>
