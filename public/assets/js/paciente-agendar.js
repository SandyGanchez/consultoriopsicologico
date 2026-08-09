(function () {
    'use strict';

    var app = document.getElementById('appointmentApp');

    if (!app) {
        return;
    }

    var urlServicios = app.dataset.urlServicios || '';
    var urlEspacios = app.dataset.urlEspacios || '';
    var urlDias = app.dataset.urlDias || '';
    var psicologoPreseleccionado = (app.dataset.psicologoPreseleccionado || '').trim();
    var servicioPreseleccionado = (app.dataset.servicioPreseleccionado || '').trim();

    var psicologoSelect = document.getElementById('psicologo');
    var servicioInput = document.getElementById('servicio');
    var fechaInput = document.getElementById('fecha');
    var horaInput = document.getElementById('hora');
    var formAgendar = document.getElementById('formAgendarCita');
    var btnConfirmar = document.getElementById('btnConfirmar');

    var serviceCard = document.getElementById('serviceCard');
    var serviceCardName = document.getElementById('serviceCardName');
    var serviceCardMeta = document.getElementById('serviceCardMeta');
    var serviceCardPlaceholder = document.getElementById('serviceCardPlaceholder');
    var btnChangeService = document.getElementById('btnChangeService');

    var slotsContainer = document.getElementById('slotsContainer');
    var slotsStatus = document.getElementById('slotsStatus');
    var servicesModalList = document.getElementById('servicesModalList');
    var servicesModalEl = document.getElementById('servicesModal');

    var calendarHeader = document.getElementById('calendarHeader');
    var calendarGrid = document.getElementById('calendarGrid');
    var calendarStatus = document.getElementById('calendarStatus');
    var calendarPrevMonth = document.getElementById('calendarPrevMonth');
    var calendarNextMonth = document.getElementById('calendarNextMonth');

    var summarySpecialistPhoto = document.getElementById('summarySpecialistPhoto');
    var summarySpecialistInitials = document.getElementById('summarySpecialistInitials');
    var summarySpecialistName = document.getElementById('summarySpecialistName');
    var summarySpecialistMeta = document.getElementById('summarySpecialistMeta');
    var summarySpecialistClinic = document.getElementById('summarySpecialistClinic');
    var summaryDateTime = document.getElementById('summaryDateTime');
    var summaryDateTimeSub = document.getElementById('summaryDateTimeSub');
    var summaryService = document.getElementById('summaryService');
    var summaryServiceSub = document.getElementById('summaryServiceSub');
    var summaryLocation = document.getElementById('summaryLocation');
    var summaryReference = document.getElementById('summaryReference');
    var summaryPolicy = document.getElementById('summaryPolicy');
    var btnChangeDate = document.getElementById('btnChangeDate');

    var MESES = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];

    var DIAS_SEMANA = [
        'domingo', 'lunes', 'martes', 'miércoles',
        'jueves', 'viernes', 'sábado'
    ];

    var hoyIso = isoHoy();

    var state = {
        servicios: [],
        psicologo: null,
        consultorio: null,
        servicioSeleccionado: null,
        duracion: 0,
        precio: 0,
        horaSeleccionada: '',
        cargandoServicios: false,
        cargandoHorarios: false,
        cargandoCalendario: false,
        mesVisible: hoyIso.slice(0, 7),
        diasDisponibles: {},
        fechaSeleccionada: ''
    };

    var servicesModal = null;

    function obtenerModalServicios() {
        if (!servicesModalEl || !window.bootstrap) {
            return null;
        }

        if (!servicesModal) {
            servicesModal = new window.bootstrap.Modal(servicesModalEl);
        }

        return servicesModal;
    }

    function abrirModalServicios() {
        renderModalServicios();

        var modal = obtenerModalServicios();

        if (modal) {
            modal.show();
        }
    }

    function isoHoy() {
        var ahora = new Date();
        var mes = String(ahora.getMonth() + 1).padStart(2, '0');
        var dia = String(ahora.getDate()).padStart(2, '0');

        return ahora.getFullYear() + '-' + mes + '-' + dia;
    }

    function escaparTexto(texto) {
        return String(texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatearMoneda(valor) {
        return '$' + Number(valor || 0).toFixed(2);
    }

    function formatearFechaResumen(fechaIso) {
        if (!fechaIso) {
            return 'Selecciona una fecha';
        }

        var partes = fechaIso.split('-');

        if (partes.length !== 3) {
            return fechaIso;
        }

        var fecha = new Date(
            Number(partes[0]),
            Number(partes[1]) - 1,
            Number(partes[2])
        );

        return fecha.toLocaleDateString('es-MX', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }

    function formatearFechaLarga(fechaIso) {
        if (!fechaIso) {
            return 'Selecciona una fecha';
        }

        var partes = fechaIso.split('-');

        if (partes.length !== 3) {
            return fechaIso;
        }

        var fecha = new Date(
            Number(partes[0]),
            Number(partes[1]) - 1,
            Number(partes[2])
        );

        return fecha.toLocaleDateString('es-MX', {
            weekday: 'long',
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }

    function formatearHora(hora) {
        if (!hora) {
            return '';
        }

        var partes = hora.split(':');

        return partes[0].padStart(2, '0') + ':' + partes[1].padStart(2, '0');
    }

    function calcularHoraFin(horaInicio, duracionMinutos) {
        if (!horaInicio || !duracionMinutos) {
            return '';
        }

        var partes = horaInicio.split(':');
        var horas = Number(partes[0] || 0);
        var minutos = Number(partes[1] || 0);
        var total = horas * 60 + minutos + Number(duracionMinutos);
        var horaFin = Math.floor(total / 60);
        var minFin = total % 60;

        return String(horaFin).padStart(2, '0') + ':' + String(minFin).padStart(2, '0');
    }

    function obtenerIniciales(nombre, apPat) {
        var inicialNombre = (nombre || 'P').charAt(0);
        var inicialAp = (apPat || 'S').charAt(0);

        return (inicialNombre + inicialAp).toUpperCase();
    }

    function textoPoliticaCancelacion(limiteHoras) {
        var limite = Number(limiteHoras || 0);

        if (limite > 0) {
            var textoHoras = limite === 1 ? '1 hora' : limite + ' horas';

            return 'Puedes cancelar con al menos ' + textoHoras + ' de anticipación.';
        }

        return 'Para conocer las condiciones de cancelación, comunícate con el consultorio.';
    }

    function calendarioHabilitado() {
        return psicologoSelect.value !== '' && servicioInput.value !== '';
    }

    function mostrarEstadoCalendario(mensaje, tipo) {
        calendarStatus.className = tipo === 'empty'
            ? 'appointment-empty-state mt-2'
            : 'appointment-loading-state mt-2';
        calendarStatus.textContent = mensaje;
        calendarStatus.classList.remove('d-none');
    }

    function ocultarEstadoCalendario() {
        calendarStatus.classList.add('d-none');
        calendarStatus.textContent = '';
    }

    function limpiarFechaSeleccionada() {
        state.fechaSeleccionada = '';
        fechaInput.value = '';
    }

    function limpiarHorario(mensaje) {
        state.horaSeleccionada = '';
        horaInput.value = '';
        slotsContainer.innerHTML = '';
        mostrarEstadoHorarios(mensaje || 'Selecciona una fecha disponible.');
        actualizarBotonConfirmar();
        actualizarResumen();
    }

    function limpiarCalendario(mensaje) {
        limpiarFechaSeleccionada();
        state.diasDisponibles = {};
        state.mesVisible = hoyIso.slice(0, 7);
        limpiarHorario('Selecciona una fecha disponible.');
        renderCalendario();
        actualizarNavegacionCalendario();

        if (mensaje) {
            mostrarEstadoCalendario(mensaje);
        } else if (!calendarioHabilitado()) {
            mostrarEstadoCalendario(
                'Selecciona un especialista y un servicio para consultar las fechas disponibles.'
            );
        } else {
            ocultarEstadoCalendario();
        }
    }

    function limpiarServicio() {
        state.servicioSeleccionado = null;
        state.duracion = 0;
        state.precio = 0;
        servicioInput.value = '';
        serviceCard.classList.remove('is-selected');
        serviceCardName.textContent = '';
        serviceCardMeta.textContent = '';
        serviceCardPlaceholder.classList.remove('d-none');
        serviceCardName.classList.add('d-none');
        serviceCardMeta.classList.add('d-none');
        limpiarCalendario(
            'Selecciona un especialista y un servicio para consultar las fechas disponibles.'
        );
    }

    function mostrarEstadoHorarios(mensaje, tipo) {
        slotsStatus.className = tipo === 'empty'
            ? 'appointment-empty-state'
            : 'appointment-loading-state';
        slotsStatus.textContent = mensaje;
        slotsStatus.classList.remove('d-none');
    }

    function ocultarEstadoHorarios() {
        slotsStatus.classList.add('d-none');
        slotsStatus.textContent = '';
    }

    function actualizarBotonConfirmar() {
        var completo =
            psicologoSelect.value !== '' &&
            servicioInput.value !== '' &&
            fechaInput.value !== '' &&
            horaInput.value !== '';

        btnConfirmar.disabled = !completo;
        btnChangeService.disabled = psicologoSelect.value === '' || state.cargandoServicios;

        document.querySelectorAll('.btn-confirm-appointment').forEach(function (boton) {
            boton.disabled = !completo;
        });
    }

    function actualizarTarjetaServicio(servicio) {
        if (!servicio) {
            limpiarServicio();
            return;
        }

        state.servicioSeleccionado = servicio;
        servicioInput.value = servicio.ClvServ;
        serviceCard.classList.add('is-selected');
        serviceCardPlaceholder.classList.add('d-none');
        serviceCardName.classList.remove('d-none');
        serviceCardMeta.classList.remove('d-none');
        serviceCardName.textContent = servicio.NombreServicio;
        serviceCardMeta.textContent =
            servicio.DuracionMinutos + ' minutos · ' +
            formatearMoneda(servicio.PrecioServicio);
        state.duracion = Number(servicio.DuracionMinutos || 0);
        state.precio = Number(servicio.PrecioServicio || 0);
        actualizarBotonConfirmar();
        actualizarResumen();
    }

    function actualizarResumen() {
        var psi = state.psicologo;
        var cons = state.consultorio;
        var serv = state.servicioSeleccionado;

        if (!psi) {
            summarySpecialistName.textContent = 'Selecciona un especialista';
            summarySpecialistMeta.textContent = '';
            summarySpecialistClinic.textContent = '';
            summarySpecialistPhoto.classList.add('d-none');
            summarySpecialistInitials.classList.add('d-none');
        } else {
            summarySpecialistName.textContent = psi.NombrePsicologo || '';
            summarySpecialistMeta.textContent = psi.EspecialidadPsi || 'Psicólogo';
            summarySpecialistClinic.textContent = cons
                ? cons.NombreCons || psi.NombreCons || ''
                : psi.NombreCons || '';

            var foto = psi.FotoPerfilPer || '';

            if (foto !== '') {
                summarySpecialistPhoto.src =
                    app.dataset.urlFotos + encodeURIComponent(foto);
                summarySpecialistPhoto.alt =
                    'Fotografía de ' + (psi.NombrePsicologo || 'especialista');
                summarySpecialistPhoto.classList.remove('d-none');
                summarySpecialistInitials.classList.add('d-none');
            } else {
                summarySpecialistPhoto.classList.add('d-none');
                summarySpecialistInitials.classList.remove('d-none');
                summarySpecialistInitials.textContent = obtenerIniciales(
                    psi.NombrePer,
                    psi.ApPatPer
                );
            }
        }

        if (fechaInput.value && horaInput.value && state.duracion) {
            var horaFin = calcularHoraFin(horaInput.value, state.duracion);

            summaryDateTime.textContent =
                formatearFechaResumen(fechaInput.value);
            summaryDateTimeSub.textContent =
                formatearHora(horaInput.value) + ' – ' + formatearHora(horaFin);
        } else if (fechaInput.value) {
            summaryDateTime.textContent = formatearFechaResumen(fechaInput.value);
            summaryDateTimeSub.textContent = 'Selecciona un horario disponible';
        } else {
            summaryDateTime.textContent = 'Selecciona fecha y horario';
            summaryDateTimeSub.textContent = '';
        }

        if (serv) {
            summaryService.textContent = serv.NombreServicio;
            summaryServiceSub.textContent =
                serv.DuracionMinutos + ' min · ' +
                formatearMoneda(serv.PrecioServicio);
        } else {
            summaryService.textContent = 'Sin servicio seleccionado';
            summaryServiceSub.textContent = '';
        }

        if (cons && cons.Direccion) {
            summaryLocation.textContent = cons.Direccion;
        } else {
            summaryLocation.textContent =
                'La dirección del consultorio está pendiente de configurar.';
        }

        if (cons && cons.ReferenciaDir) {
            summaryReference.textContent = cons.ReferenciaDir;
            summaryReference.classList.remove('d-none');
        } else {
            summaryReference.textContent = '';
            summaryReference.classList.add('d-none');
        }

        summaryPolicy.textContent = textoPoliticaCancelacion(
            cons ? cons.LimiteCancHoras : 0
        );
    }

    function tituloMes(mesIso) {
        var partes = mesIso.split('-');
        var anio = Number(partes[0]);
        var mes = Number(partes[1]) - 1;

        return MESES[mes] + ' ' + anio;
    }

    function esMesPasado(mesIso) {
        return mesIso < hoyIso.slice(0, 7);
    }

    function actualizarNavegacionCalendario() {
        var habilitado = calendarioHabilitado() && !state.cargandoCalendario;
        var mesActual = hoyIso.slice(0, 7);

        calendarPrevMonth.disabled =
            !habilitado || state.mesVisible <= mesActual;
        calendarNextMonth.disabled = !habilitado;
    }

    function etiquetaDia(fechaIso, totalEspacios) {
        var partes = fechaIso.split('-');
        var fecha = new Date(
            Number(partes[0]),
            Number(partes[1]) - 1,
            Number(partes[2])
        );
        var diaSemana = DIAS_SEMANA[fecha.getDay()];
        var dia = Number(partes[2]);
        var mes = MESES[Number(partes[1]) - 1].toLowerCase();
        var anio = partes[0];
        var espaciosTexto = totalEspacios === 1
            ? '1 horario disponible'
            : totalEspacios + ' horarios disponibles';

        return diaSemana.charAt(0).toUpperCase() + diaSemana.slice(1) +
            ' ' + dia + ' de ' + mes + ' de ' + anio + ', ' + espaciosTexto;
    }

    function crearCeldaVacia() {
        var celda = document.createElement('div');
        celda.className = 'appointment-calendar-day appointment-calendar-day--outside';
        celda.setAttribute('aria-hidden', 'true');

        return celda;
    }

    function renderCalendario() {
        calendarHeader.textContent = tituloMes(state.mesVisible);
        calendarGrid.innerHTML = '';

        if (!calendarioHabilitado()) {
            actualizarNavegacionCalendario();
            return;
        }

        var partesMes = state.mesVisible.split('-');
        var anio = Number(partesMes[0]);
        var mes = Number(partesMes[1]) - 1;
        var primerDia = new Date(anio, mes, 1);
        var ultimoDia = new Date(anio, mes + 1, 0);
        var offsetLunes = (primerDia.getDay() + 6) % 7;

        var i;

        for (i = 0; i < offsetLunes; i += 1) {
            calendarGrid.appendChild(crearCeldaVacia());
        }

        for (var dia = 1; dia <= ultimoDia.getDate(); dia += 1) {
            var mesStr = String(mes + 1).padStart(2, '0');
            var diaStr = String(dia).padStart(2, '0');
            var fechaIso = anio + '-' + mesStr + '-' + diaStr;
            var esPasado = fechaIso < hoyIso;
            var esHoy = fechaIso === hoyIso;
            var esSeleccionado = fechaIso === state.fechaSeleccionada;
            var totalEspacios = state.diasDisponibles[fechaIso] || 0;
            var esDisponible = !esPasado && totalEspacios > 0;

            var celda;

            if (esDisponible) {
                celda = document.createElement('button');
                celda.type = 'button';
                celda.className = 'appointment-calendar-day appointment-day-available';

                if (esSeleccionado) {
                    celda.classList.add('appointment-day-selected');
                    celda.setAttribute('aria-selected', 'true');
                }

                celda.setAttribute('aria-label', etiquetaDia(fechaIso, totalEspacios));
                celda.addEventListener('click', function (fecha) {
                    return function () {
                        seleccionarFecha(fecha);
                    };
                }(fechaIso));
            } else {
                celda = document.createElement('div');
                celda.className = 'appointment-calendar-day';

                if (esPasado) {
                    celda.classList.add('appointment-day-past');
                    celda.setAttribute('aria-disabled', 'true');
                } else {
                    celda.classList.add('appointment-day-unavailable');
                    celda.setAttribute('aria-disabled', 'true');
                }
            }

            if (esHoy) {
                celda.classList.add('appointment-day-today');
            }

            var numero = document.createElement('span');
            numero.className = 'appointment-calendar-day__number';
            numero.textContent = String(dia);
            celda.appendChild(numero);

            if (esHoy) {
                var etiquetaHoy = document.createElement('span');
                etiquetaHoy.className = 'appointment-calendar-day__today-label';
                etiquetaHoy.textContent = 'Hoy';
                celda.appendChild(etiquetaHoy);
            }

            if (esSeleccionado && esDisponible) {
                var indicador = document.createElement('span');
                indicador.className = 'appointment-calendar-day__selected-label';
                indicador.textContent = 'Seleccionado';
                celda.appendChild(indicador);
            }

            calendarGrid.appendChild(celda);
        }

        actualizarNavegacionCalendario();
    }

    async function cargarDiasDelMes(mesIso) {
        if (!calendarioHabilitado()) {
            limpiarCalendario(
                'Selecciona un especialista y un servicio para consultar las fechas disponibles.'
            );
            return;
        }

        state.cargandoCalendario = true;
        state.mesVisible = mesIso;
        limpiarFechaSeleccionada();
        limpiarHorario('Selecciona una fecha disponible.');
        renderCalendario();
        actualizarNavegacionCalendario();
        mostrarEstadoCalendario('Consultando fechas disponibles…');

        try {
            var parametros = new URLSearchParams({
                psicologo: psicologoSelect.value,
                servicio: servicioInput.value,
                mes: mesIso
            });

            var respuesta = await fetch(
                urlDias + '?' + parametros.toString(),
                {
                    method: 'GET',
                    headers: { Accept: 'application/json' }
                }
            );

            var datos = await respuesta.json();

            if (!datos.ok) {
                state.diasDisponibles = {};
                renderCalendario();
                mostrarEstadoCalendario(
                    datos.mensaje ||
                    'No fue posible cargar la información. Inténtalo nuevamente.',
                    'empty'
                );
                return;
            }

            state.diasDisponibles = {};

            (datos.diasDisponibles || []).forEach(function (item) {
                state.diasDisponibles[item.fecha] = Number(item.totalEspacios || 0);
            });

            renderCalendario();

            if ((datos.diasDisponibles || []).length === 0) {
                mostrarEstadoCalendario(
                    datos.mensaje ||
                    'No hay fechas disponibles en este mes. Prueba otro mes.',
                    'empty'
                );
            } else {
                ocultarEstadoCalendario();
            }
        } catch (error) {
            state.diasDisponibles = {};
            renderCalendario();
            mostrarEstadoCalendario(
                'No fue posible cargar la información. Inténtalo nuevamente.',
                'empty'
            );
        } finally {
            state.cargandoCalendario = false;
            actualizarNavegacionCalendario();
        }
    }

    function seleccionarFecha(fechaIso) {
        if (!state.diasDisponibles[fechaIso]) {
            return;
        }

        state.fechaSeleccionada = fechaIso;
        fechaInput.value = fechaIso;
        renderCalendario();
        limpiarHorario('Consultando horarios disponibles…');
        actualizarResumen();
        actualizarBotonConfirmar();
        cargarEspacios();

        if (slotsContainer) {
            slotsContainer.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }
    }

    function renderModalServicios() {
        servicesModalList.innerHTML = '';

        if (state.servicios.length === 0) {
            servicesModalList.innerHTML =
                '<div class="p-4 text-center text-muted">' +
                escaparTexto('No hay servicios disponibles.') +
                '</div>';
            return;
        }

        state.servicios.forEach(function (servicio) {
            var seleccionado =
                state.servicioSeleccionado &&
                state.servicioSeleccionado.ClvServ === servicio.ClvServ;

            var fila = document.createElement('div');
            fila.className = 'service-option' +
                (seleccionado ? ' service-option-selected' : '');

            var info = document.createElement('div');
            info.className = 'service-option__info';

            var nombre = document.createElement('p');
            nombre.className = 'service-option__name';
            nombre.textContent = servicio.NombreServicio;

            info.appendChild(nombre);

            if (servicio.Descripcion) {
                var desc = document.createElement('p');
                desc.className = 'service-option__desc';
                desc.textContent = servicio.Descripcion;
                info.appendChild(desc);
            }

            var meta = document.createElement('p');
            meta.className = 'service-option__meta';
            meta.textContent =
                servicio.DuracionMinutos + ' minutos · ' +
                formatearMoneda(servicio.PrecioServicio);
            info.appendChild(meta);

            fila.appendChild(info);

            if (seleccionado) {
                var etiqueta = document.createElement('span');
                etiqueta.className = 'service-option-selected-label';
                etiqueta.innerHTML =
                    '<i class="bi bi-check-circle-fill" aria-hidden="true"></i> Seleccionado';
                fila.appendChild(etiqueta);
            } else {
                var boton = document.createElement('button');
                boton.type = 'button';
                boton.className = 'btn-select-service';
                boton.textContent = 'Seleccionar';
                boton.dataset.clvServ = servicio.ClvServ;
                boton.addEventListener('click', function () {
                    seleccionarServicio(servicio.ClvServ);
                    var modal = obtenerModalServicios();
                    if (modal) {
                        modal.hide();
                    }
                });
                fila.appendChild(boton);
            }

            servicesModalList.appendChild(fila);
        });
    }

    function seleccionarServicio(clvServ) {
        var servicio = state.servicios.find(function (item) {
            return item.ClvServ === clvServ;
        });

        if (!servicio) {
            return;
        }

        actualizarTarjetaServicio(servicio);
        renderModalServicios();
        limpiarCalendario();
        cargarDiasDelMes(state.mesVisible);
    }

    async function cargarServicios() {
        limpiarServicio();
        state.servicios = [];
        state.psicologo = null;
        state.consultorio = null;
        renderModalServicios();
        actualizarResumen();

        if (psicologoSelect.value === '') {
            mostrarEstadoHorarios(
                'Selecciona un especialista para continuar.'
            );
            return;
        }

        state.cargandoServicios = true;
        actualizarBotonConfirmar();
        mostrarEstadoHorarios('Cargando servicios disponibles…');

        try {
            var parametros = new URLSearchParams({
                psicologo: psicologoSelect.value
            });
            var urlConsulta = urlServicios + '?' + parametros.toString();

            var respuesta = await fetch(
                urlConsulta,
                {
                    method: 'GET',
                    headers: { Accept: 'application/json' }
                }
            );

            var cuerpo = await respuesta.text();
            var datos;

            try {
                datos = JSON.parse(cuerpo);
            } catch (parseError) {
                console.error(
                    'Agendamiento: respuesta no JSON',
                    {
                        url: urlConsulta,
                        status: respuesta.status,
                        body: cuerpo.slice(0, 300)
                    }
                );

                mostrarEstadoHorarios(
                    'No fue posible cargar los servicios del especialista.',
                    'empty'
                );

                return;
            }

            if (!respuesta.ok || !datos.ok) {
                console.error(
                    'Agendamiento: error al cargar servicios',
                    {
                        url: urlConsulta,
                        status: respuesta.status,
                        respuesta: datos
                    }
                );

                mostrarEstadoHorarios(
                    datos.mensaje ||
                    'No fue posible cargar los servicios del especialista.',
                    'empty'
                );

                return;
            }

            if (!Array.isArray(datos.servicios)) {
                console.error(
                    'Agendamiento: servicios no es un arreglo',
                    datos
                );

                mostrarEstadoHorarios(
                    'No fue posible cargar los servicios del especialista.',
                    'empty'
                );

                return;
            }

            state.servicios = datos.servicios;
            state.psicologo = datos.psicologo || null;
            state.consultorio = datos.consultorio || null;

            renderModalServicios();
            actualizarResumen();

            if (state.servicios.length === 0) {
                mostrarEstadoHorarios(
                    datos.mensaje ||
                    'Este especialista no tiene servicios disponibles actualmente.',
                    'empty'
                );
                return;
            }

            var servicioInicial = null;

            if (servicioPreseleccionado !== '') {
                servicioInicial = state.servicios.find(function (item) {
                    return String(item.ClvServ) === servicioPreseleccionado;
                }) || null;
                servicioPreseleccionado = '';
            }

            if (servicioInicial) {
                seleccionarServicio(servicioInicial.ClvServ);
            } else if (state.servicios.length === 1) {
                seleccionarServicio(state.servicios[0].ClvServ);
            } else {
                ocultarEstadoHorarios();
                mostrarEstadoHorarios(
                    'Selecciona un servicio para ver fechas disponibles.',
                    'empty'
                );
                abrirModalServicios();
            }
        } catch (error) {
            console.error('Agendamiento: fallo AJAX servicios', error);

            mostrarEstadoHorarios(
                'No fue posible cargar los servicios del especialista.',
                'empty'
            );
        } finally {
            state.cargandoServicios = false;
            actualizarBotonConfirmar();
        }
    }

    function renderHorarios(espacios) {
        slotsContainer.innerHTML = '';
        ocultarEstadoHorarios();

        var ayuda = document.createElement('p');
        ayuda.className = 'small text-muted mb-2';
        ayuda.textContent =
            'Los horarios mostrados son opciones de inicio. Cuando una cita se reserva, las opciones que se superponen dejan de estar disponibles.';
        slotsContainer.appendChild(ayuda);

        espacios.forEach(function (espacio) {
            var boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'appointment-time-slot';
            boton.textContent = espacio.texto;
            boton.dataset.valor = espacio.valor;
            boton.setAttribute('aria-label', 'Horario ' + espacio.texto);

            if (horaInput.value === espacio.valor) {
                boton.classList.add('appointment-time-slot-selected');
                boton.setAttribute('aria-pressed', 'true');
            } else {
                boton.setAttribute('aria-pressed', 'false');
            }

            boton.addEventListener('click', function () {
                horaInput.value = espacio.valor;
                state.horaSeleccionada = espacio.valor;

                slotsContainer.querySelectorAll('.appointment-time-slot').forEach(function (btn) {
                    var seleccionado = btn.dataset.valor === espacio.valor;
                    btn.classList.toggle('appointment-time-slot-selected', seleccionado);
                    btn.setAttribute('aria-pressed', seleccionado ? 'true' : 'false');
                });

                actualizarBotonConfirmar();
                actualizarResumen();
            });

            slotsContainer.appendChild(boton);
        });
    }

    function renderHorariosVacios(mensaje) {
        slotsContainer.innerHTML = '';
        mostrarEstadoHorarios(mensaje, 'empty');

        var acciones = document.createElement('div');
        acciones.className = 'appointment-empty-state__actions';

        var btnFecha = document.createElement('button');
        btnFecha.type = 'button';
        btnFecha.textContent = 'Cambiar fecha';
        btnFecha.addEventListener('click', function () {
            var calendario = document.getElementById('appointmentCalendar');
            if (calendario) {
                calendario.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        var btnServicio = document.createElement('button');
        btnServicio.type = 'button';
        btnServicio.textContent = 'Cambiar servicio';
        btnServicio.addEventListener('click', function () {
            abrirModalServicios();
        });

        var btnPsi = document.createElement('button');
        btnPsi.type = 'button';
        btnPsi.textContent = 'Cambiar especialista';
        btnPsi.addEventListener('click', function () {
            psicologoSelect.focus();
        });

        acciones.appendChild(btnFecha);
        acciones.appendChild(btnServicio);
        acciones.appendChild(btnPsi);
        slotsStatus.appendChild(acciones);
    }

    async function cargarEspacios() {
        state.cargandoHorarios = true;
        actualizarBotonConfirmar();

        if (
            psicologoSelect.value === '' ||
            servicioInput.value === '' ||
            fechaInput.value === ''
        ) {
            state.cargandoHorarios = false;
            return;
        }

        try {
            var parametros = new URLSearchParams({
                psicologo: psicologoSelect.value,
                servicio: servicioInput.value,
                fecha: fechaInput.value
            });

            var respuesta = await fetch(
                urlEspacios + '?' + parametros.toString(),
                {
                    method: 'GET',
                    headers: { Accept: 'application/json' }
                }
            );

            var datos = await respuesta.json();

            if (!datos.ok) {
                renderHorariosVacios(
                    datos.mensaje ||
                    'No fue posible cargar la información. Inténtalo nuevamente.'
                );
                return;
            }

            state.duracion = Number(datos.duracion || state.duracion || 0);
            state.precio = Number(datos.precio || state.precio || 0);

            if (!datos.espacios || datos.espacios.length === 0) {
                renderHorariosVacios(
                    'Este día ya no cuenta con horarios disponibles. Selecciona otra fecha.'
                );
                return;
            }

            renderHorarios(datos.espacios);
            actualizarResumen();
        } catch (error) {
            renderHorariosVacios(
                'No fue posible cargar la información. Inténtalo nuevamente.'
            );
        } finally {
            state.cargandoHorarios = false;
            actualizarBotonConfirmar();
        }
    }

    function cambiarMes(delta) {
        if (!calendarioHabilitado() || state.cargandoCalendario) {
            return;
        }

        var partes = state.mesVisible.split('-');
        var fecha = new Date(Number(partes[0]), Number(partes[1]) - 1 + delta, 1);
        var mes = String(fecha.getMonth() + 1).padStart(2, '0');
        var nuevoMes = fecha.getFullYear() + '-' + mes;

        if (delta < 0 && esMesPasado(nuevoMes)) {
            return;
        }

        cargarDiasDelMes(nuevoMes);
    }

    psicologoSelect.addEventListener('change', function () {
        limpiarCalendario(
            'Selecciona un especialista y un servicio para consultar las fechas disponibles.'
        );
        cargarServicios();
    });

    btnChangeService.addEventListener('click', function () {
        abrirModalServicios();
    });

    if (btnChangeDate) {
        btnChangeDate.addEventListener('click', function () {
            var calendario = document.getElementById('appointmentCalendar');
            if (calendario) {
                calendario.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }

    calendarPrevMonth.addEventListener('click', function () {
        cambiarMes(-1);
    });

    calendarNextMonth.addEventListener('click', function () {
        cambiarMes(1);
    });

    formAgendar.addEventListener('submit', function (ev) {
        if (
            psicologoSelect.value === '' ||
            servicioInput.value === '' ||
            fechaInput.value === '' ||
            horaInput.value === ''
        ) {
            return;
        }

        var destinoRadio = formAgendar.querySelector('input[name="destino_cita"]:checked');
        var destino = destinoRadio ? destinoRadio.value : 'yo';
        var clvPacInput = document.getElementById('clvPacDestino');

        if (destino === 'dependiente') {
            var clvDep = (destinoRadio.getAttribute('data-clv-pac') || '').trim();
            if (!clvDep) {
                ev.preventDefault();
                window.alert('Selecciona un dependiente válido.');
                return;
            }
            if (clvPacInput) {
                clvPacInput.value = clvDep;
            }
        }

        if (destino === 'nuevo') {
            var reqIds = [
                'dep_nombre',
                'dep_apPat',
                'dep_fechaNacimiento',
                'dep_genero',
                'dep_parentesco'
            ];
            for (var i = 0; i < reqIds.length; i++) {
                var el = document.getElementById(reqIds[i]);
                if (!el || String(el.value || '').trim() === '') {
                    ev.preventDefault();
                    window.alert('Completa los datos de la persona para quien agendas.');
                    return;
                }
            }
            var aviso = document.getElementById('dep_aviso_leido');
            var cons = document.getElementById('dep_consentimiento_sensibles');
            if (!aviso || !aviso.checked || !cons || !cons.checked) {
                ev.preventDefault();
                window.alert('Debes aceptar el aviso de privacidad y el consentimiento.');
                return;
            }
        }

        document.querySelectorAll('.btn-confirm-appointment').forEach(function (boton) {
            boton.disabled = true;
            boton.textContent = 'Procesando…';
        });
    });

    (function initForWhom() {
        var radios = formAgendar.querySelectorAll('input[name="destino_cita"]');
        var panelNuevo = document.getElementById('formNuevoDependiente');
        var clvPacInput = document.getElementById('clvPacDestino');
        var clvPacPropio = clvPacInput ? clvPacInput.value : '';
        var fechaNac = document.getElementById('dep_fechaNacimiento');
        var tutorWrap = document.getElementById('depTutorWrap');

        function syncForWhom() {
            var seleccionado = formAgendar.querySelector('input[name="destino_cita"]:checked');
            var modo = seleccionado ? seleccionado.value : 'yo';

            if (panelNuevo) {
                var esNuevo = modo === 'nuevo';
                panelNuevo.classList.toggle('d-none', !esNuevo);
                panelNuevo.hidden = !esNuevo;
                panelNuevo.querySelectorAll('input, select').forEach(function (campo) {
                    if (esNuevo) {
                        if (campo.id === 'dep_nombre' || campo.id === 'dep_apPat'
                            || campo.id === 'dep_fechaNacimiento' || campo.id === 'dep_genero'
                            || campo.id === 'dep_parentesco'
                            || campo.id === 'dep_aviso_leido'
                            || campo.id === 'dep_consentimiento_sensibles') {
                            campo.required = true;
                        }
                    } else {
                        campo.required = false;
                    }
                });
            }

            if (clvPacInput) {
                if (modo === 'yo') {
                    clvPacInput.value = clvPacPropio;
                } else if (modo === 'dependiente' && seleccionado) {
                    clvPacInput.value = (seleccionado.getAttribute('data-clv-pac') || '').trim();
                } else {
                    clvPacInput.value = '';
                }
            }
        }

        function syncTutorPorEdad() {
            if (!fechaNac || !tutorWrap) {
                return;
            }
            var v = (fechaNac.value || '').trim();
            if (!/^\d{4}-\d{2}-\d{2}$/.test(v)) {
                return;
            }
            var parts = v.split('-');
            var nac = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
            var hoy = new Date();
            var edad = hoy.getFullYear() - nac.getFullYear();
            var m = hoy.getMonth() - nac.getMonth();
            if (m < 0 || (m === 0 && hoy.getDate() < nac.getDate())) {
                edad--;
            }
            tutorWrap.classList.toggle('d-none', edad >= 18);
        }

        radios.forEach(function (r) {
            r.addEventListener('change', syncForWhom);
        });
        if (fechaNac) {
            fechaNac.addEventListener('change', syncTutorPorEdad);
            fechaNac.addEventListener('input', syncTutorPorEdad);
        }
        syncForWhom();
        syncTutorPorEdad();
    })();

    renderCalendario();
    actualizarResumen();
    actualizarBotonConfirmar();
    mostrarEstadoHorarios('Selecciona un especialista para comenzar.');
    mostrarEstadoCalendario(
        'Selecciona un especialista y un servicio para consultar las fechas disponibles.'
    );

    if (
        psicologoPreseleccionado !== ''
        && psicologoSelect
        && psicologoSelect.value === psicologoPreseleccionado
    ) {
        cargarServicios();
    } else if (psicologoSelect && psicologoSelect.value !== '') {
        cargarServicios();
    }
})();
