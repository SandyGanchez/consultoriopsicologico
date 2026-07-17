document.addEventListener('DOMContentLoaded', () => {
    iniciarCalendarioConsultorio();
});

function iniciarCalendarioConsultorio() {

    const calendarElement = document.getElementById(
        'calendarioConsultorio'
    );

    if (
        !calendarElement ||
        typeof FullCalendar === 'undefined'
    ) {
        return;
    }

    const configuracion =
        window.consultorioAgenda ?? {};

    const filtroPsicologo =
        document.getElementById('psicologo');

    const filtroEstado =
        document.getElementById('estado');

    const formulario =
        document.getElementById('formFiltrosAgenda');

    const botonLimpiar =
        document.getElementById('limpiarFiltrosAgenda');

    const calendar =
        new FullCalendar.Calendar(
            calendarElement,
            {

                locale: 'es',

                initialView: 'timeGridWeek',

                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'timeGridWeek,dayGridMonth'
                },

                buttonText: {
                    today: 'Hoy',
                    week: 'Semana',
                    month: 'Mes'
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
                        psicologo:
                            filtroPsicologo.value,

                        estado:
                            filtroEstado.value
                    })

                },

                eventContent: info => {
                    return crearContenidoEvento(info);
                },

                eventClick: info => {
                    mostrarDetalleCita(info.event);
                }

            }
        );

    calendar.render();

    formulario.addEventListener(
        'submit',
        e => {

            e.preventDefault();

            calendar.refetchEvents();

        }
    );

    botonLimpiar.addEventListener(
        'click',
        () => {

            filtroPsicologo.value = '';

            filtroEstado.value = '';

            calendar.refetchEvents();

        }
    );

    setInterval(
        () => calendar.refetchEvents(),
        30000
    );

}
function crearContenidoEvento(info) {

    const estado =
        info.event.extendedProps.estado;

    const iconos = {
        PROGRAMADA: 'bi-clock',
        ASISTIDA: 'bi-check-circle-fill',
        CANCELADA: 'bi-x-circle-fill',
        INASISTENCIA: 'bi-person-x-fill'
    };

    const contenedor =
        document.createElement('div');

    contenedor.className =
        'cita-contenido';

    const encabezado =
        document.createElement('div');

    encabezado.className =
        'cita-encabezado';

    const icono =
        document.createElement('i');

    icono.className =
        `bi ${iconos[estado] ?? 'bi-calendar-event'}`;

    const hora =
        document.createElement('span');

    hora.className =
        'cita-hora';

    hora.textContent =
        info.timeText;

    encabezado.append(
        icono,
        hora
    );

    const paciente =
        document.createElement('strong');

    paciente.className =
        'cita-paciente';

    paciente.textContent =
        info.event.title;

    const estadoTexto =
        document.createElement('small');

    estadoTexto.className =
        'cita-estado-texto';

    estadoTexto.textContent =
        normalizarEstado(estado);

    contenedor.append(
        encabezado,
        paciente,
        estadoTexto
    );

    return {
        domNodes: [contenedor]
    };
}
function mostrarDetalleCita(evento) {

    const props = evento.extendedProps;

    colocarTexto(
        'detallePaciente',
        props.paciente
    );

    colocarTexto(
        'detallePsicologo',
        props.psicologo
    );

    colocarTexto(
        'detalleServicio',
        props.servicio
    );

    colocarTexto(
        'detalleFecha',
        formatearFecha(evento.start)
    );

    colocarTexto(
        'detalleHorario',
        formatearHorario(
            evento.start,
            evento.end
        )
    );

    colocarTexto(
        'detalleNotas',
        props.notas || 'Sin notas'
    );

    const estadoElement =
        document.getElementById(
            'detalleEstado'
        );

    if (estadoElement) {

        estadoElement.textContent =
            normalizarEstado(props.estado);

        estadoElement.className =
            'agenda-status ' +
            `status-${String(
                props.estado
            ).toLowerCase()}`;

    }

    const esCancelada =
        props.estado === 'CANCELADA';

    alternarBloque(
        'bloqueCanceladaPor',
        esCancelada
    );

    alternarBloque(
        'bloqueFechaCancelacion',
        esCancelada
    );

    alternarBloque(
        'bloqueMotivoCancelacion',
        esCancelada
    );

    colocarTexto(
        'detalleCanceladaPor',
        props.canceladaPor || 'Paciente'
    );

    colocarTexto(
        'detalleFechaCancelacion',
        props.fechaCancelacion
            ? formatearFechaHora(
                props.fechaCancelacion
            )
            : 'No registrada'
    );

    colocarTexto(
        'detalleMotivoCancelacion',
        props.motivoCancelacion ||
        'Sin motivo especificado'
    );

    const modalElement =
        document.getElementById(
            'modalDetalleCita'
        );

    if (!modalElement) {
        return;
    }

    const modal =
        bootstrap.Modal.getOrCreateInstance(
            modalElement
        );

    modal.show();
}
function colocarTexto(id, valor) {

    const elemento = document.getElementById(id);

    if (!elemento) {
        return;
    }

    elemento.textContent =
        valor && String(valor).trim() !== ''
            ? valor
            : '—';
}

function alternarBloque(id, mostrar) {

    const bloque = document.getElementById(id);

    if (!bloque) {
        return;
    }

    bloque.classList.toggle(
        'd-none',
        !mostrar
    );
}
function formatearFecha(fecha) {

    if (!fecha) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        'es-MX',
        {
            weekday: 'long',
            day: '2-digit',
            month: 'long',
            year: 'numeric'
        }
    ).format(fecha);
}

function formatearHorario(inicio, fin) {

    if (!inicio) {
        return '—';
    }

    const formatoHora = new Intl.DateTimeFormat(
        'es-MX',
        {
            hour: '2-digit',
            minute: '2-digit'
        }
    );

    const horaInicio =
        formatoHora.format(inicio);

    if (!fin) {
        return horaInicio;
    }

    const horaFin =
        formatoHora.format(fin);

    return `${horaInicio} - ${horaFin}`;
}
function formatearFechaHora(valor) {

    if (!valor) {
        return '—';
    }

    const fechaNormalizada =
        String(valor).replace(' ', 'T');

    const fecha =
        new Date(fechaNormalizada);

    if (Number.isNaN(fecha.getTime())) {
        return valor;
    }

    return new Intl.DateTimeFormat(
        'es-MX',
        {
            day: '2-digit',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }
    ).format(fecha);
}
function normalizarEstado(estado) {

    const estados = {
        PROGRAMADA: 'Programada',
        ASISTIDA: 'Asistida',
        CANCELADA: 'Cancelada',
        INASISTENCIA: 'Inasistencia'
    };

    return estados[estado] ?? estado ?? '—';
}