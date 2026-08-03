const contextoAgenda = {
    calendar: null,
    fechaCalendario: '',
    horaSeleccionada: '',
    cargandoHorarios: false,
    enviando: false,
    refs: null
};

let toastAgendaActivo = null;

document.addEventListener('DOMContentLoaded', () => {
    iniciarCalendarioPsicologo();
    iniciarModalNuevaCita();
    iniciarAsistenciaCitaPsi();
});

let contextoDetalleCitaPsi = null;
let enviandoAsistenciaPsi = false;

function obtenerConfigAgenda() {
    return window.psicologoAgenda ?? {};
}

function obtenerFechaMinimaServidor() {
    const config = obtenerConfigAgenda();

    return config.fechaMinima || config.fechaActual || '';
}

function obtenerFechaActualServidor() {
    const config = obtenerConfigAgenda();

    return config.fechaActual || obtenerFechaMinimaServidor();
}

function obtenerHoraActualServidor() {
    const config = obtenerConfigAgenda();

    return config.horaActual || '00:00:00';
}

function formatearIsoFecha(fecha) {
    const anio = fecha.getFullYear();
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const dia = String(fecha.getDate()).padStart(2, '0');

    return `${anio}-${mes}-${dia}`;
}

function obtenerIsoDesdeCalendario(info) {
    const dateStr = String(info?.dateStr ?? '').trim();

    if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
        return dateStr;
    }

    if (dateStr.length >= 10) {
        const prefijo = dateStr.substring(0, 10);

        if (/^\d{4}-\d{2}-\d{2}$/.test(prefijo)) {
            return prefijo;
        }
    }

    if (info?.date instanceof Date) {
        return formatearIsoFecha(info.date);
    }

    return '';
}

function parsearFechaLocal(iso) {
    if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
        return null;
    }

    const partes = iso.split('-').map(Number);

    return new Date(partes[0], partes[1] - 1, partes[2]);
}

function normalizarHoraComparacion(hora) {
    if (!hora) {
        return '';
    }

    const valor = String(hora).trim();

    if (valor.length === 5) {
        return `${valor}:00`;
    }

    return valor.substring(0, 8);
}

function validarFechaNoPasada(iso) {
    if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
        return {
            ok: false,
            codigo: 'FECHA_INVALIDA',
            mensaje: 'La fecha seleccionada no es válida.'
        };
    }

    const fechaMinima = obtenerFechaMinimaServidor();

    if (fechaMinima && iso < fechaMinima) {
        return {
            ok: false,
            codigo: 'FECHA_PASADA',
            mensaje:
                'No puedes programar una cita en una fecha anterior.'
        };
    }

    return { ok: true };
}

function validarHoraNoPasada(iso, hora) {
    const validacionFecha = validarFechaNoPasada(iso);

    if (!validacionFecha.ok) {
        return validacionFecha;
    }

    if (iso !== obtenerFechaActualServidor()) {
        return { ok: true };
    }

    const horaNormalizada = normalizarHoraComparacion(hora);
    const horaActual = normalizarHoraComparacion(
        obtenerHoraActualServidor()
    );

    if (
        horaNormalizada !== '' &&
        horaNormalizada <= horaActual
    ) {
        return {
            ok: false,
            codigo: 'HORA_PASADA',
            mensaje:
                'Ese horario ya transcurrió. Selecciona una hora futura.'
        };
    }

    return { ok: true };
}

function esFechaPasada(iso) {
    return !validarFechaNoPasada(iso).ok;
}

function obtenerFechaSeleccionada() {
    return contextoAgenda.fechaCalendario || '';
}

function formatearFechaLegible(iso) {
    const fecha = parsearFechaLocal(iso);

    if (!fecha) {
        return '';
    }

    return new Intl.DateTimeFormat('es-MX', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    }).format(fecha);
}

function formatearHoraCorta(valor) {
    if (!valor) {
        return '';
    }

    return String(valor).substring(0, 5);
}

function calcularHoraFin(horaInicio, duracionMinutos) {
    const partes = horaInicio.split(':').map(Number);
    const minutosTotales =
        partes[0] * 60 + partes[1] + duracionMinutos;
    const horas = Math.floor(minutosTotales / 60);
    const minutos = minutosTotales % 60;

    return `${String(horas).padStart(2, '0')}:${String(minutos).padStart(2, '0')}`;
}

function formatearMoneda(valor) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(Number(valor) || 0);
}

function mostrarToast(tipo, mensaje) {
    const contenedor = document.getElementById(
        'psiAgendaToastContainer'
    );

    if (!contenedor || typeof bootstrap === 'undefined') {
        return;
    }

    if (toastAgendaActivo) {
        toastAgendaActivo.hide();
        toastAgendaActivo._element?.remove();
        toastAgendaActivo = null;
    }

    const clases = {
        error: 'psi-toast-error',
        warning: 'psi-toast-warning',
        info: 'psi-toast-info',
        success: 'psi-toast-success'
    };

    const iconos = {
        error: 'bi-exclamation-triangle-fill',
        warning: 'bi-exclamation-circle-fill',
        info: 'bi-info-circle-fill',
        success: 'bi-check-circle-fill'
    };

    const id = `psiToast_${Date.now()}`;
    const tipoClase = clases[tipo] || clases.info;
    const icono = iconos[tipo] || iconos.info;

    const toastElement = document.createElement('div');
    toastElement.id = id;
    toastElement.className = `toast ${tipoClase}`;
    toastElement.setAttribute('role', 'alert');
    toastElement.setAttribute('aria-live', 'assertive');
    toastElement.setAttribute('aria-atomic', 'true');
    toastElement.dataset.bsDelay = '5000';

    const header = document.createElement('div');
    header.className = 'toast-header';

    const iconHeader = document.createElement('i');
    iconHeader.className = `bi ${icono} me-2`;

    const titulo = document.createElement('strong');
    titulo.className = 'me-auto';
    titulo.textContent = 'PsicoMatch';

    const btnCerrar = document.createElement('button');
    btnCerrar.type = 'button';
    btnCerrar.className = 'btn-close';
    btnCerrar.dataset.bsDismiss = 'toast';
    btnCerrar.setAttribute('aria-label', 'Cerrar');

    const body = document.createElement('div');
    body.className = 'toast-body';
    body.textContent = String(mensaje ?? '');

    header.append(iconHeader, titulo, btnCerrar);
    toastElement.append(header, body);
    contenedor.appendChild(toastElement);

    const toast = new bootstrap.Toast(toastElement, {
        delay: 5000
    });

    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });

    toastAgendaActivo = toast;
    toast.show();
}

function iniciarCalendarioPsicologo() {
    const calendarElement = document.getElementById(
        'calendarioPsicologo'
    );

    if (
        !calendarElement ||
        typeof FullCalendar === 'undefined'
    ) {
        return;
    }

    const configuracion = obtenerConfigAgenda();
    const filtroEstado =
        document.getElementById('estadoAgendaPsi');
    const formulario =
        document.getElementById('formFiltrosAgendaPsi');
    const botonLimpiar =
        document.getElementById('limpiarFiltrosAgendaPsi');

    const calendar = new FullCalendar.Calendar(
        calendarElement,
        {
            locale: 'es',
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            buttonText: {
                today: 'Hoy',
                month: 'Mes',
                week: 'Semana',
                day: 'Día'
            },
            validRange: {
                start: obtenerFechaMinimaServidor()
            },
            allDaySlot: false,
            nowIndicator: true,
            expandRows: true,
            height: 'auto',
            slotMinTime: '08:00:00',
            slotMaxTime: '21:00:00',
            slotDuration: '00:30:00',
            events: {
                url: configuracion.eventosUrl,
                method: 'GET',
                extraParams: () => ({
                    estado: filtroEstado
                        ? filtroEstado.value
                        : ''
                })
            },
            dateClick(info) {
                const iso = obtenerIsoDesdeCalendario(info);
                const validacion = validarFechaNoPasada(iso);

                if (!validacion.ok) {
                    mostrarToast('error', validacion.mensaje);

                    return;
                }

                contextoAgenda.fechaCalendario = iso;
                calendar.render();
            },
            dayCellClassNames(arg) {
                const clases = [];
                const iso = obtenerIsoDesdeCalendario(arg);

                if (esFechaPasada(iso)) {
                    clases.push('psi-day-past');
                }

                if (
                    contextoAgenda.fechaCalendario === iso &&
                    !esFechaPasada(iso)
                ) {
                    clases.push('psi-day-selected');
                }

                return clases;
            },
            dayCellDidMount(arg) {
                const iso = obtenerIsoDesdeCalendario(arg);

                if (esFechaPasada(iso)) {
                    arg.el.setAttribute('aria-disabled', 'true');
                } else {
                    arg.el.removeAttribute('aria-disabled');
                }
            },
            eventClick(info) {
                mostrarDetalleCitaPsi(info.event);
            }
        }
    );

    calendar.render();
    contextoAgenda.calendar = calendar;

    if (formulario) {
        formulario.addEventListener('submit', e => {
            e.preventDefault();
            calendar.refetchEvents();
        });
    }

    if (botonLimpiar && filtroEstado) {
        botonLimpiar.addEventListener('click', () => {
            filtroEstado.value = '';
            calendar.refetchEvents();
        });
    }
}

function iniciarModalNuevaCita() {
    const botonNueva =
        document.getElementById('btnNuevaCitaAgenda');
    const modalElement =
        document.getElementById('modalNuevaCitaPsi');
    const formulario =
        modalElement?.querySelector('form');

    if (!botonNueva || !modalElement || !formulario) {
        return;
    }

    contextoAgenda.refs = {
        modalElement,
        formulario,
        modal: bootstrap.Modal.getOrCreateInstance(modalElement),
        pacienteSelect: document.getElementById('pacienteNuevaCita'),
        servicioSelect: document.getElementById('servicioNuevaCita'),
        fechaInput: document.getElementById('fechaNuevaCita'),
        horaInput: document.getElementById('horaNuevaCita'),
        btnGuardar: document.getElementById('btnGuardarNuevaCita'),
        btnGuardarTexto: document.getElementById(
            'btnGuardarNuevaCitaTexto'
        ),
        btnGuardarLoading: document.getElementById(
            'btnGuardarNuevaCitaLoading'
        ),
        fechaLegible: document.getElementById('fechaNuevaCitaLegible'),
        fechaAyuda: document.getElementById('fechaNuevaCitaAyuda'),
        horariosEstado: document.getElementById(
            'horariosNuevaCitaEstado'
        ),
        horariosContenedor: document.getElementById(
            'horariosNuevaCita'
        ),
        resumen: document.getElementById('nuevaCitaResumen')
    };

    botonNueva.addEventListener('click', () => {
        abrirModalNuevaCita(obtenerFechaSeleccionada());
    });

    const configInicial = obtenerConfigAgenda();

    if (configInicial.errorPacienteAgenda) {
        mostrarToast('error', configInicial.errorPacienteAgenda);
    }

    if (
        configInicial.pacientePreseleccionado &&
        contextoAgenda.refs.pacienteSelect
    ) {
        const existeOpcion = Array.from(
            contextoAgenda.refs.pacienteSelect.options
        ).some(
            opcion =>
                opcion.value === configInicial.pacientePreseleccionado
        );

        if (existeOpcion) {
            contextoAgenda.refs.pacienteSelect.value =
                configInicial.pacientePreseleccionado;

            abrirModalNuevaCita(obtenerFechaSeleccionada());
        }
    }

    contextoAgenda.refs.pacienteSelect?.addEventListener(
        'change',
        () => {
            actualizarAyudaFechaModal();
            cargarHorariosDisponibles();
            actualizarResumen();
        }
    );

    contextoAgenda.refs.servicioSelect?.addEventListener(
        'change',
        () => {
            limpiarHorarioSeleccionado();
            actualizarAyudaFechaModal();
            cargarHorariosDisponibles();
        }
    );

    contextoAgenda.refs.fechaInput?.addEventListener(
        'change',
        manejarCambioFechaModal
    );

    formulario.addEventListener('submit', manejarEnvioNuevaCita);

    modalElement.addEventListener('hidden.bs.modal', () => {
        limpiarHorarioSeleccionado();
        limpiarHorariosDisponibles();
        marcarFechaInvalida(false);
        contextoAgenda.enviando = false;
        restaurarBotonGuardar();
        habilitarEnvioSiCompleto();

        if (contextoAgenda.refs.fechaLegible) {
            contextoAgenda.refs.fechaLegible.textContent = '';
        }

        if (contextoAgenda.refs.fechaAyuda) {
            contextoAgenda.refs.fechaAyuda.textContent =
                'Selecciona una fecha para consultar los horarios disponibles.';
        }

        contextoAgenda.refs.resumen?.classList.add('d-none');
    });
}

function abrirModalNuevaCita(fecha) {
    const refs = contextoAgenda.refs;

    if (!refs) {
        return;
    }

    limpiarHorarioSeleccionado();
    limpiarHorariosDisponibles();
    marcarFechaInvalida(false);
    contextoAgenda.enviando = false;
    restaurarBotonGuardar();

    let fechaValida = '';

    if (fecha) {
        const validacion = validarFechaNoPasada(fecha);

        if (validacion.ok) {
            fechaValida = fecha;
        }
    }

    precargarFechaModal(fechaValida);
    actualizarResumen();
    habilitarEnvioSiCompleto();
    refs.modal.show();
}

function precargarFechaModal(iso) {
    const refs = contextoAgenda.refs;

    if (!refs?.fechaInput) {
        return;
    }

    refs.fechaInput.min = obtenerFechaMinimaServidor();

    if (iso) {
        refs.fechaInput.value = iso;

        if (refs.fechaLegible) {
            refs.fechaLegible.textContent =
                formatearFechaLegible(iso);
        }

        marcarFechaInvalida(false);
    } else {
        refs.fechaInput.value = '';

        if (refs.fechaLegible) {
            refs.fechaLegible.textContent = '';
        }
    }

    actualizarAyudaFechaModal();
    cargarHorariosDisponibles();
}

function manejarCambioFechaModal() {
    const refs = contextoAgenda.refs;
    const fecha = refs?.fechaInput?.value || '';
    const validacion = validarFechaNoPasada(fecha);

    if (!validacion.ok) {
        marcarFechaInvalida(true);
        limpiarHorarioSeleccionado();
        limpiarHorariosDisponibles();
        mostrarToast('error', validacion.mensaje);
        refs.fechaInput?.focus();

        if (refs.fechaLegible) {
            refs.fechaLegible.textContent = '';
        }

        habilitarEnvioSiCompleto();

        return;
    }

    marcarFechaInvalida(false);

    if (refs.fechaLegible) {
        refs.fechaLegible.textContent =
            formatearFechaLegible(fecha);
    }

    contextoAgenda.fechaCalendario = fecha;
    contextoAgenda.calendar?.render();

    actualizarAyudaFechaModal();
    cargarHorariosDisponibles();
}

function limpiarHorarioSeleccionado() {
    contextoAgenda.horaSeleccionada = '';

    const refs = contextoAgenda.refs;

    if (refs?.horaInput) {
        refs.horaInput.value = '';
    }

    refs?.horariosContenedor
        ?.querySelectorAll('.psi-agenda-slot')
        .forEach(boton => {
            boton.classList.remove('psi-agenda-slot-selected');
            boton.setAttribute('aria-pressed', 'false');
            boton.querySelector('.bi-check-lg')?.remove();
        });

    habilitarEnvioSiCompleto();
    actualizarResumen();
}

function limpiarHorariosDisponibles(mensaje = '') {
    const refs = contextoAgenda.refs;

    if (refs?.horariosContenedor) {
        refs.horariosContenedor.replaceChildren();
    }

    if (refs?.horariosEstado) {
        if (mensaje) {
            refs.horariosEstado.textContent = mensaje;
            refs.horariosEstado.classList.remove('d-none');
        } else {
            refs.horariosEstado.textContent = '';
            refs.horariosEstado.classList.add('d-none');
        }
    }
}

function marcarFechaInvalida(esInvalida) {
    contextoAgenda.refs?.fechaInput?.classList.toggle(
        'is-invalid',
        esInvalida
    );
}

function actualizarAyudaFechaModal() {
    const refs = contextoAgenda.refs;

    if (!refs?.fechaAyuda) {
        return;
    }

    const fecha = refs.fechaInput?.value || '';
    const servicio = refs.servicioSelect?.value || '';

    if (!fecha) {
        refs.fechaAyuda.textContent =
            'Selecciona una fecha para consultar los horarios disponibles.';

        return;
    }

    if (!servicio) {
        refs.fechaAyuda.textContent =
            'Selecciona un servicio para consultar los horarios de esta fecha.';

        return;
    }

    if (!refs.pacienteSelect?.value) {
        refs.fechaAyuda.textContent =
            'Selecciona un paciente para continuar.';

        return;
    }

    refs.fechaAyuda.textContent = '';
}

function obtenerServicioSeleccionadoModal() {
    const refs = contextoAgenda.refs;
    const opcion = refs?.servicioSelect?.selectedOptions[0];

    if (!opcion || !opcion.value) {
        return null;
    }

    return {
        clvServ: opcion.value,
        nombre: opcion.dataset.nombre || opcion.textContent.trim(),
        duracion: parseInt(opcion.dataset.duracion || '0', 10),
        precio: parseFloat(opcion.dataset.precio || '0')
    };
}

function actualizarResumen() {
    const refs = contextoAgenda.refs;

    if (!refs?.resumen) {
        return;
    }

    const pacienteOpcion =
        refs.pacienteSelect?.selectedOptions[0];
    const servicio = obtenerServicioSeleccionadoModal();
    const fecha = refs.fechaInput?.value || '';
    const config = obtenerConfigAgenda();

    const mostrarResumen =
        pacienteOpcion?.value &&
        servicio &&
        fecha &&
        contextoAgenda.horaSeleccionada &&
        validarFechaNoPasada(fecha).ok;

    refs.resumen.classList.toggle('d-none', !mostrarResumen);

    if (!mostrarResumen) {
        return;
    }

    const horaInicio = formatearHoraCorta(
        contextoAgenda.horaSeleccionada
    );
    const horaFin = calcularHoraFin(
        horaInicio,
        servicio.duracion
    );

    colocarTextoPsi(
        'resumenPaciente',
        pacienteOpcion.textContent.trim()
    );
    colocarTextoPsi('resumenServicio', servicio.nombre);
    colocarTextoPsi(
        'resumenFecha',
        formatearFechaLegible(fecha)
    );
    colocarTextoPsi(
        'resumenHorario',
        `${horaInicio}–${horaFin}`
    );
    colocarTextoPsi(
        'resumenDuracion',
        `${servicio.duracion} minutos`
    );
    colocarTextoPsi(
        'resumenCosto',
        formatearMoneda(servicio.precio)
    );
    colocarTextoPsi(
        'resumenConsultorio',
        config.consultorioNombre || 'Consultorio'
    );
}

function renderHorariosDisponibles(espacios) {
    const refs = contextoAgenda.refs;

    if (!refs?.horariosContenedor) {
        return;
    }

    refs.horariosContenedor.replaceChildren();
    limpiarHorariosDisponibles('');

    if (!espacios.length) {
        limpiarHorariosDisponibles(
            'No hay horarios disponibles para esta fecha. Selecciona otro día.'
        );
        mostrarToast(
            'info',
            'No existen horarios disponibles para la fecha seleccionada.'
        );

        return;
    }

    espacios.forEach(espacio => {
        const boton = document.createElement('button');
        const seleccionado =
            contextoAgenda.horaSeleccionada === espacio.valor;

        boton.type = 'button';
        boton.className = 'psi-agenda-slot';
        boton.textContent = espacio.texto;
        boton.dataset.valor = espacio.valor;
        boton.setAttribute(
            'aria-label',
            `Horario ${espacio.texto}`
        );
        boton.setAttribute(
            'aria-pressed',
            seleccionado ? 'true' : 'false'
        );

        if (seleccionado) {
            boton.classList.add('psi-agenda-slot-selected');

            const icono = document.createElement('i');
            icono.className = 'bi bi-check-lg ms-1';
            icono.setAttribute('aria-hidden', 'true');
            boton.appendChild(icono);
        }

        boton.addEventListener('click', () => {
            seleccionarHorarioDisponible(espacio.valor, espacio.texto);
        });

        refs.horariosContenedor.appendChild(boton);
    });
}

function seleccionarHorarioDisponible(valor, texto) {
    const refs = contextoAgenda.refs;
    const fecha = refs?.fechaInput?.value || '';
    const validacionHora = validarHoraNoPasada(fecha, valor);

    if (!validacionHora.ok) {
        limpiarHorarioSeleccionado();
        mostrarToast('error', validacionHora.mensaje);

        return;
    }

    contextoAgenda.horaSeleccionada = valor;

    if (refs?.horaInput) {
        refs.horaInput.value = valor;
    }

    refs?.horariosContenedor
        ?.querySelectorAll('.psi-agenda-slot')
        .forEach(btn => {
            const activo = btn.dataset.valor === valor;

            btn.classList.toggle(
                'psi-agenda-slot-selected',
                activo
            );
            btn.setAttribute(
                'aria-pressed',
                activo ? 'true' : 'false'
            );

            btn.querySelector('.bi-check-lg')?.remove();

            if (activo) {
                const check = document.createElement('i');
                check.className = 'bi bi-check-lg ms-1';
                check.setAttribute('aria-hidden', 'true');
                btn.appendChild(check);
            }
        });

    habilitarEnvioSiCompleto();
    actualizarResumen();
}

async function cargarHorariosDisponibles() {
    const refs = contextoAgenda.refs;
    const config = obtenerConfigAgenda();
    const fecha = refs?.fechaInput?.value || '';
    const servicio = refs?.servicioSelect?.value || '';

    limpiarHorarioSeleccionado();
    actualizarAyudaFechaModal();

    if (!fecha) {
        limpiarHorariosDisponibles();

        return;
    }

    const validacionFecha = validarFechaNoPasada(fecha);

    if (!validacionFecha.ok) {
        marcarFechaInvalida(true);
        limpiarHorariosDisponibles();
        mostrarToast('error', validacionFecha.mensaje);
        refs?.fechaInput?.focus();
        habilitarEnvioSiCompleto();

        return;
    }

    marcarFechaInvalida(false);

    if (!servicio) {
        limpiarHorariosDisponibles();

        return;
    }

    if (!refs?.pacienteSelect?.value) {
        limpiarHorariosDisponibles();

        return;
    }

    contextoAgenda.cargandoHorarios = true;
    habilitarEnvioSiCompleto();
    limpiarHorariosDisponibles(
        'Consultando horarios disponibles…'
    );

    try {
        const url = new URL(config.horariosUrl);

        url.searchParams.set('servicio', servicio);
        url.searchParams.set('fecha', fecha);

        const respuesta = await fetch(url.toString(), {
            headers: {
                Accept: 'application/json'
            }
        });

        const datos = await respuesta.json();

        if (!respuesta.ok || !datos.ok) {
            if (datos.codigo === 'FECHA_PASADA') {
                marcarFechaInvalida(true);
                refs?.fechaInput?.focus();
                mostrarToast('error', datos.mensaje);
            }

            limpiarHorariosDisponibles(
                datos.mensaje ||
                    'No fue posible consultar los horarios.'
            );

            return;
        }

        renderHorariosDisponibles(datos.espacios || []);
    } catch (error) {
        limpiarHorariosDisponibles(
            'No fue posible consultar los horarios.'
        );
    } finally {
        contextoAgenda.cargandoHorarios = false;
        habilitarEnvioSiCompleto();
    }
}

function formularioNuevaCitaCompleto() {
    const refs = contextoAgenda.refs;
    const fecha = refs?.fechaInput?.value || '';

    return Boolean(
        refs?.pacienteSelect?.value &&
        refs?.servicioSelect?.value &&
        fecha &&
        contextoAgenda.horaSeleccionada &&
        validarFechaNoPasada(fecha).ok &&
        validarHoraNoPasada(
            fecha,
            contextoAgenda.horaSeleccionada
        ).ok
    );
}

function habilitarEnvioSiCompleto() {
    const refs = contextoAgenda.refs;

    if (!refs?.btnGuardar || contextoAgenda.enviando) {
        return;
    }

    const puedeGuardar =
        formularioNuevaCitaCompleto() &&
        !contextoAgenda.cargandoHorarios;

    refs.btnGuardar.disabled = !puedeGuardar;
}

function bloquearEnvio() {
    const refs = contextoAgenda.refs;

    if (!refs?.btnGuardar) {
        return;
    }

    contextoAgenda.enviando = true;
    refs.btnGuardar.disabled = true;
    refs.btnGuardarTexto?.classList.add('d-none');
    refs.btnGuardarLoading?.classList.remove('d-none');
}

function restaurarBotonGuardar() {
    const refs = contextoAgenda.refs;

    if (!refs?.btnGuardar) {
        return;
    }

    contextoAgenda.enviando = false;
    refs.btnGuardarTexto?.classList.remove('d-none');
    refs.btnGuardarLoading?.classList.add('d-none');
    habilitarEnvioSiCompleto();
}

function limpiarModalNuevaCitaExito() {
    const refs = contextoAgenda.refs;

    refs?.formulario?.reset();
    contextoAgenda.fechaCalendario = '';
    contextoAgenda.horaSeleccionada = '';
    contextoAgenda.enviando = false;

    limpiarHorarioSeleccionado();
    limpiarHorariosDisponibles();
    marcarFechaInvalida(false);

    if (refs?.fechaLegible) {
        refs.fechaLegible.textContent = '';
    }

    if (refs?.fechaAyuda) {
        refs.fechaAyuda.textContent =
            'Selecciona una fecha para consultar los horarios disponibles.';
    }

    refs?.resumen?.classList.add('d-none');
    restaurarBotonGuardar();
}

function manejarErrorGuardadoNuevaCita(datos) {
    const refs = contextoAgenda.refs;
    const codigo = datos?.codigo || '';

    if (codigo === 'FECHA_PASADA' || codigo === 'HORA_PASADA') {
        if (codigo === 'FECHA_PASADA') {
            marcarFechaInvalida(true);
            refs?.fechaInput?.focus();
        } else if (codigo === 'HORA_PASADA') {
            refs?.horariosContenedor?.focus();
        }

        limpiarHorarioSeleccionado();
        limpiarHorariosDisponibles();
    }

    mostrarToast(
        'error',
        datos?.mensaje || 'No fue posible registrar la cita.'
    );

    restaurarBotonGuardar();
}

async function manejarEnvioNuevaCita(event) {
    event.preventDefault();

    const refs = contextoAgenda.refs;

    if (!refs?.formulario || contextoAgenda.enviando) {
        return;
    }

    const fecha = refs.fechaInput?.value || '';
    const validacionFecha = validarFechaNoPasada(fecha);

    if (!validacionFecha.ok) {
        marcarFechaInvalida(true);
        limpiarHorarioSeleccionado();
        limpiarHorariosDisponibles();
        mostrarToast('error', validacionFecha.mensaje);
        refs.fechaInput?.focus();
        habilitarEnvioSiCompleto();

        return;
    }

    if (!contextoAgenda.horaSeleccionada) {
        mostrarToast(
            'info',
            'Selecciona un horario disponible.'
        );

        return;
    }

    const validacionHora = validarHoraNoPasada(
        fecha,
        contextoAgenda.horaSeleccionada
    );

    if (!validacionHora.ok) {
        limpiarHorarioSeleccionado();
        limpiarHorariosDisponibles();
        mostrarToast('error', validacionHora.mensaje);
        habilitarEnvioSiCompleto();

        return;
    }

    if (!formularioNuevaCitaCompleto()) {
        habilitarEnvioSiCompleto();

        return;
    }

    const config = obtenerConfigAgenda();
    const formData = new FormData(refs.formulario);

    bloquearEnvio();

    try {
        const respuesta = await fetch(config.guardarUrl, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json'
            },
            body: formData
        });

        const datos = await respuesta.json();

        if (!datos.ok) {
            manejarErrorGuardadoNuevaCita(datos);

            return;
        }

        mostrarToast(
            'success',
            datos.mensaje ||
                'La cita fue creada correctamente.'
        );

        refs.modal.hide();
        limpiarModalNuevaCitaExito();
        contextoAgenda.calendar?.refetchEvents();
    } catch (error) {
        mostrarToast(
            'error',
            'No fue posible registrar la cita.'
        );
        restaurarBotonGuardar();
    }
}

function mostrarDetalleCitaPsi(evento) {
    const props = evento.extendedProps ?? {};
    const estado = String(props.estado || '').toUpperCase();

    contextoDetalleCitaPsi = {
        clvCita: props.clvCita || evento.id || '',
        paciente: props.paciente || '',
        servicio: props.servicio || '',
        estado,
        puedeRegistrarResultado: !!props.puedeRegistrarResultado,
        accionClinica: props.accionClinica || '',
        etiquetaClinica: props.etiquetaClinica || '',
        urlClinica: props.urlClinica || '',
        fechaTexto: formatearFechaPsi(evento.start),
        horarioTexto: formatearHorarioPsi(evento.start, evento.end)
    };

    colocarTextoPsi('detallePacientePsi', props.paciente);
    colocarTextoPsi('detalleServicioPsi', props.servicio);
    colocarTextoPsi(
        'detalleFechaPsi',
        contextoDetalleCitaPsi.fechaTexto
    );
    colocarTextoPsi(
        'detalleHorarioPsi',
        contextoDetalleCitaPsi.horarioTexto
    );

    const duracion = parseInt(props.duracionMinutos, 10);

    colocarTextoPsi(
        'detalleDuracionPsi',
        duracion > 0 ? `${duracion} min` : '—'
    );

    actualizarEstadoDetallePsi(estado);
    actualizarBloquesAsistenciaPsi(contextoDetalleCitaPsi);

    const modalElement =
        document.getElementById('modalDetalleCitaPsi');

    if (!modalElement) {
        return;
    }

    bootstrap.Modal
        .getOrCreateInstance(modalElement)
        .show();
}

function actualizarEstadoDetallePsi(estado) {
    const estadoElement =
        document.getElementById('detalleEstadoPsi');

    if (!estadoElement) {
        return;
    }

    estadoElement.textContent = normalizarEstadoPsi(estado);
    estadoElement.className =
        'agenda-status status-' +
        String(estado || '').toLowerCase();
}

function actualizarBloquesAsistenciaPsi(ctx) {
    const mensaje = document.getElementById(
        'detalleAsistenciaMensajePsi'
    );
    const acciones = document.getElementById(
        'detalleAccionesAsistenciaPsi'
    );
    const clinica = document.getElementById(
        'detalleAccionClinicaPsi'
    );
    const enlace = document.getElementById(
        'enlaceAccionClinicaPsi'
    );
    const textoClinica = document.getElementById(
        'textoAccionClinicaPsi'
    );

    mensaje?.classList.add('d-none');
    acciones?.classList.add('d-none');
    clinica?.classList.add('d-none');
    textoClinica?.classList.add('d-none');

    if (!ctx) {
        return;
    }

    if (ctx.estado === 'PROGRAMADA') {
        if (ctx.puedeRegistrarResultado) {
            acciones?.classList.remove('d-none');
        } else if (mensaje) {
            mensaje.textContent =
                'Podrás registrar la asistencia cuando comience la cita.';
            mensaje.classList.remove('d-none');
        }
        return;
    }

    if (ctx.estado === 'INASISTENCIA') {
        if (mensaje) {
            mensaje.textContent = 'Inasistencia registrada.';
            mensaje.classList.remove('d-none');
        }
        return;
    }

    if (ctx.estado === 'ASISTIDA' && clinica) {
        if (ctx.accionClinica === 'inasistencia') {
            return;
        }

        if (ctx.urlClinica && enlace) {
            enlace.href = ctx.urlClinica;
            enlace.textContent =
                ctx.etiquetaClinica || 'Abrir expediente';
            enlace.classList.remove('d-none');
            textoClinica?.classList.add('d-none');
            clinica.classList.remove('d-none');
        } else if (ctx.etiquetaClinica && textoClinica) {
            textoClinica.textContent = ctx.etiquetaClinica;
            textoClinica.classList.remove('d-none');
            enlace?.classList.add('d-none');
            clinica.classList.remove('d-none');
        }
    }
}

function iniciarAsistenciaCitaPsi() {
    document
        .getElementById('btnAbrirAsistidaPsi')
        ?.addEventListener('click', () => {
            abrirConfirmacionAsistenciaPsi('ASISTIDA');
        });

    document
        .getElementById('btnAbrirInasistenciaPsi')
        ?.addEventListener('click', () => {
            abrirConfirmacionAsistenciaPsi('INASISTENCIA');
        });

    document
        .getElementById('btnConfirmarAsistidaPsi')
        ?.addEventListener('click', () => {
            enviarRegistroAsistenciaPsi('ASISTIDA');
        });

    document
        .getElementById('btnConfirmarInasistenciaPsi')
        ?.addEventListener('click', () => {
            enviarRegistroAsistenciaPsi('INASISTENCIA');
        });
}

function abrirConfirmacionAsistenciaPsi(accion) {
    if (!contextoDetalleCitaPsi) {
        return;
    }

    const prefijo =
        accion === 'ASISTIDA' ? 'confirmAsistida' : 'confirmInasistencia';

    colocarTextoPsi(
        `${prefijo}Paciente`,
        contextoDetalleCitaPsi.paciente
    );
    colocarTextoPsi(
        `${prefijo}Servicio`,
        contextoDetalleCitaPsi.servicio
    );
    colocarTextoPsi(
        `${prefijo}Fecha`,
        contextoDetalleCitaPsi.fechaTexto
    );
    colocarTextoPsi(
        `${prefijo}Horario`,
        contextoDetalleCitaPsi.horarioTexto
    );

    const modalId =
        accion === 'ASISTIDA'
            ? 'modalConfirmarAsistidaPsi'
            : 'modalConfirmarInasistenciaPsi';

    const modalElement = document.getElementById(modalId);

    if (!modalElement) {
        return;
    }

    bootstrap.Modal.getOrCreateInstance(modalElement).show();
}

async function enviarRegistroAsistenciaPsi(accion) {
    if (enviandoAsistenciaPsi || !contextoDetalleCitaPsi) {
        return;
    }

    const config = obtenerConfigAgenda();
    const url = config.asistenciaUrl;

    if (!url) {
        return;
    }

    const botonId =
        accion === 'ASISTIDA'
            ? 'btnConfirmarAsistidaPsi'
            : 'btnConfirmarInasistenciaPsi';
    const boton = document.getElementById(botonId);
    const textoOriginal = boton?.innerHTML;

    enviandoAsistenciaPsi = true;

    if (boton) {
        boton.disabled = true;
        boton.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Guardando…';
    }

    const cuerpo = new FormData();
    cuerpo.append('csrf_token', config.csrfToken || '');
    cuerpo.append('ClvCita', contextoDetalleCitaPsi.clvCita);
    cuerpo.append('accion', accion);

    try {
        const respuesta = await fetch(url, {
            method: 'POST',
            body: cuerpo,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });

        const datos = await respuesta.json();

        if (!datos?.ok) {
            const tipoToast =
                datos?.codigo === 'CITA_NO_INICIADA'
                    ? 'warning'
                    : 'error';

            mostrarToast(
                tipoToast,
                datos?.mensaje ||
                    'No fue posible actualizar la cita.'
            );

            if (boton && textoOriginal) {
                boton.disabled = false;
                boton.innerHTML = textoOriginal;
            }

            enviandoAsistenciaPsi = false;
            return;
        }

        mostrarToast(
            'success',
            datos.mensaje ||
                (accion === 'ASISTIDA'
                    ? 'La cita fue marcada como asistida.'
                    : 'La inasistencia fue registrada.')
        );

        contextoDetalleCitaPsi.estado = datos.estado || accion;
        contextoDetalleCitaPsi.puedeRegistrarResultado = false;
        contextoDetalleCitaPsi.accionClinica =
            datos.accionClinica || '';
        contextoDetalleCitaPsi.etiquetaClinica =
            datos.etiquetaClinica || '';
        contextoDetalleCitaPsi.urlClinica = datos.urlClinica || '';

        actualizarEstadoDetallePsi(contextoDetalleCitaPsi.estado);
        actualizarBloquesAsistenciaPsi(contextoDetalleCitaPsi);

        const modalAsistida = document.getElementById(
            'modalConfirmarAsistidaPsi'
        );
        const modalInasistencia = document.getElementById(
            'modalConfirmarInasistenciaPsi'
        );

        if (modalAsistida) {
            bootstrap.Modal.getOrCreateInstance(modalAsistida).hide();
        }

        if (modalInasistencia) {
            bootstrap.Modal
                .getOrCreateInstance(modalInasistencia)
                .hide();
        }

        contextoAgenda.calendar?.refetchEvents();
    } catch (error) {
        mostrarToast(
            'error',
            'No fue posible actualizar el estado de la cita.'
        );
    } finally {
        enviandoAsistenciaPsi = false;

        if (boton && textoOriginal) {
            boton.disabled = false;
            boton.innerHTML = textoOriginal;
        }
    }
}

function colocarTextoPsi(id, valor) {
    const elemento = document.getElementById(id);

    if (!elemento) {
        return;
    }

    elemento.textContent =
        valor && String(valor).trim() !== ''
            ? valor
            : '—';
}

function formatearFechaPsi(fecha) {
    if (!fecha) {
        return '—';
    }

    return new Intl.DateTimeFormat('es-MX', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric'
    }).format(fecha);
}

function formatearHorarioPsi(inicio, fin) {
    if (!inicio) {
        return '—';
    }

    const formatoHora = new Intl.DateTimeFormat('es-MX', {
        hour: '2-digit',
        minute: '2-digit'
    });

    const horaInicio = formatoHora.format(inicio);

    if (!fin) {
        return horaInicio;
    }

    return `${horaInicio} - ${formatoHora.format(fin)}`;
}

function normalizarEstadoPsi(estado) {
    const estados = {
        PROGRAMADA: 'Programada',
        ASISTIDA: 'Asistida',
        CANCELADA: 'Cancelada',
        INASISTENCIA: 'Inasistencia'
    };

    return estados[estado] ?? estado ?? '—';
}
