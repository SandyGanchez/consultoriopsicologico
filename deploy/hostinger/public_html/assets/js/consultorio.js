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
                    right: 'timeGridWeek,dayGridMonth,timeGridDay'
                },

                buttonText: {
                    today: 'Hoy',
                    week: 'Semana',
                    month: 'Mes',
                    day: 'Día'
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

    if (formulario) {
        formulario.addEventListener(
            'submit',
            e => {

                e.preventDefault();

                calendar.refetchEvents();

            }
        );
    }

    if (botonLimpiar) {
        botonLimpiar.addEventListener(
            'click',
            () => {

                filtroPsicologo.value = '';

                filtroEstado.value = '';

                calendar.refetchEvents();

            }
        );
    }

    setInterval(
        () => calendar.refetchEvents(),
        30000
    );

}

function crearContenidoEvento(info) {

    const props = info.event.extendedProps ?? {};
    const estado = props.estado;

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

    const titulo =
        document.createElement('strong');

    titulo.className =
        'cita-titulo';

    titulo.textContent =
        props.servicio || 'Cita ocupada';

    const subtitulo =
        document.createElement('small');

    subtitulo.className =
        'cita-especialista';

    subtitulo.textContent =
        props.psicologo
            ? `Psic. ${props.psicologo}`
            : '';

    const estadoTexto =
        document.createElement('small');

    estadoTexto.className =
        'cita-estado-texto';

    estadoTexto.textContent =
        normalizarEstado(estado);

    contenedor.append(
        encabezado,
        titulo,
        subtitulo,
        estadoTexto
    );

    return {
        domNodes: [contenedor]
    };
}

function mostrarDetalleCita(evento) {

    const props = evento.extendedProps ?? {};

    colocarTexto(
        'detallePsicologo',
        props.psicologo
    );

    colocarTexto(
        'detalleEspecialidad',
        props.especialidad || '—'
    );

    colocarTexto(
        'detalleServicio',
        props.servicio || 'Cita ocupada'
    );

    colocarTexto(
        'detalleConsultorio',
        props.consultorio
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

    const duracion = parseInt(
        props.duracionMinutos,
        10
    );

    colocarTexto(
        'detalleDuracion',
        duracion > 0
            ? `${duracion} min`
            : '—'
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

function normalizarEstado(estado) {

    const estados = {
        PROGRAMADA: 'Programada',
        ASISTIDA: 'Asistida',
        CANCELADA: 'Cancelada',
        INASISTENCIA: 'Inasistencia'
    };

    return estados[estado] ?? estado ?? '—';
}
