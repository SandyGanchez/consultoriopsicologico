/**
 * Refresco operativo de pendientes clínicos (dashboard).
 * Intervalo mínimo 60s + setTimeout hasta la próxima cita (reloj servidor).
 */
(function () {
    if (window.__psiPendientesVivoInit) {
        return;
    }
    window.__psiPendientesVivoInit = true;

    const config = window.psicologoPendientes || {};
    const url = String(config.url || '');
    const loginUrl = String(config.loginUrl || '');
    const intervaloMs = Math.max(60000, Number(config.intervaloMs) || 60000);
    const contenedor = document.getElementById('psiPendientesVivo');

    if (!url || !contenedor) {
        return;
    }

    contenedor.setAttribute('aria-live', 'polite');
    contenedor.setAttribute('aria-atomic', 'false');
    contenedor.setAttribute('aria-relevant', 'additions text');

    let timerIntervalo = null;
    let timerProxima = null;
    let peticionPendientesEnCurso = false;
    let secuenciaSolicitud = 0;
    let abortController = null;
    let activo = true;
    let avisoSesionMostrado = false;
    let ultimoFirmaAvisos = '';

    function escapar(texto) {
        return String(texto ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function urlAccionPermitida(urlCruda) {
        if (!urlCruda || typeof urlCruda !== 'string') {
            return '';
        }

        let u;
        try {
            u = new URL(urlCruda, window.location.href);
        } catch (e) {
            return '';
        }

        if (u.origin !== window.location.origin) {
            return '';
        }

        if (u.protocol !== 'http:' && u.protocol !== 'https:') {
            return '';
        }

        if (u.pathname.includes('..')) {
            return '';
        }

        const permitida =
            /\/psicologo\/agenda(?:\/|$)/.test(u.pathname) ||
            /\/psicologo\/pacientes\//.test(u.pathname) ||
            /\/psicologo\/expediente\//.test(u.pathname);

        return permitida ? u.href : '';
    }

    function avisoHtml(tipo, titulo, mensaje, btnTexto, btnUrl, secTexto, secUrl) {
        const iconos = {
            asistencia: 'bi-clock-history',
            historia: 'bi-journal-medical',
            seguimiento: 'bi-clipboard2-pulse',
            datos: 'bi-person-lines-fill'
        };
        const icono = iconos[tipo] || 'bi-info-circle';
        let acciones = '';
        const urlBtn = urlAccionPermitida(btnUrl);
        const urlSec = urlAccionPermitida(secUrl);

        if (urlBtn && btnTexto) {
            acciones +=
                '<a href="' +
                escapar(urlBtn) +
                '" class="btn psi-pendiente-aviso__btn">' +
                escapar(btnTexto) +
                '</a>';
        }

        if (urlSec && secTexto) {
            acciones +=
                '<a href="' +
                escapar(urlSec) +
                '" class="btn psi-pendiente-aviso__btn-sec">' +
                escapar(secTexto) +
                '</a>';
        }

        return (
            '<div class="psi-pendiente-aviso psi-pendiente-aviso--' +
            escapar(tipo) +
            '" role="status">' +
            '<div class="psi-pendiente-aviso__body">' +
            '<span class="psi-pendiente-aviso__icon" aria-hidden="true">' +
            '<i class="bi ' +
            icono +
            '"></i></span><div><strong>' +
            escapar(titulo) +
            '</strong>' +
            (mensaje
                ? '<p class="mb-0 mt-1">' + escapar(mensaje) + '</p>'
                : '') +
            '</div></div><div class="psi-pendiente-aviso__acciones">' +
            acciones +
            '</div></div>'
        );
    }

    function formatearFechaCorta(fecha, hora) {
        if (!fecha) {
            return '';
        }

        const partes = String(fecha).split('-');
        if (partes.length !== 3) {
            return String(fecha);
        }

        const texto = partes[2] + '/' + partes[1] + '/' + partes[0];
        const h = String(hora || '').substring(0, 5);

        return h ? texto + ' ' + h : texto;
    }

    function detenerActualizaciones() {
        activo = false;

        if (timerIntervalo) {
            clearInterval(timerIntervalo);
            timerIntervalo = null;
        }

        if (timerProxima) {
            clearTimeout(timerProxima);
            timerProxima = null;
        }

        if (abortController) {
            try {
                abortController.abort();
            } catch (e) {
                // ignore
            }
            abortController = null;
        }

        peticionPendientesEnCurso = false;
    }

    function avisarSesionExpirada() {
        if (avisoSesionMostrado) {
            return;
        }

        avisoSesionMostrado = true;
        detenerActualizaciones();

        let aviso = document.getElementById('psiPendientesSesionAviso');
        if (!aviso) {
            aviso = document.createElement('div');
            aviso.id = 'psiPendientesSesionAviso';
            aviso.className = 'alert alert-warning mt-3';
            aviso.setAttribute('role', 'status');
            aviso.setAttribute('aria-live', 'polite');
            contenedor.parentNode?.insertBefore(aviso, contenedor);
        }

        aviso.textContent =
            'Tu sesión expiró o ya no es válida. Inicia sesión nuevamente para continuar.';

        if (loginUrl) {
            const enlace = document.createElement('a');
            enlace.href = loginUrl;
            enlace.className = 'alert-link ms-2';
            enlace.textContent = 'Ir al inicio de sesión';
            aviso.appendChild(document.createTextNode(' '));
            aviso.appendChild(enlace);

            window.setTimeout(() => {
                window.location.assign(loginUrl);
            }, 2500);
        }
    }

    function renderSnapshot(datos) {
        const asistencia = Array.isArray(datos.registrarAsistencia)
            ? datos.registrarAsistencia
            : [];
        const historias = Array.isArray(datos.historiasPendientes)
            ? datos.historiasPendientes
            : [];
        const seguimientos = Array.isArray(datos.seguimientosPendientes)
            ? datos.seguimientosPendientes
            : [];

        const firma = JSON.stringify({
            a: asistencia.map((i) => i.clvCita || i.clvPac),
            h: historias.map((i) => i.clvPac),
            s: seguimientos.map((i) => i.clvCita || i.clvHist)
        });

        let html = '';

        if (asistencia.length) {
            html += '<div class="psi-pendiente-bloque mb-4" data-bloque="asistencia">';
            asistencia.forEach((item) => {
                const mensaje =
                    'La cita ya comenzó. Registra si el paciente asistió para continuar con la documentación clínica. ' +
                    (item.nombrePaciente || 'Paciente') +
                    ' · ' +
                    formatearFechaCorta(item.fechaCita, item.hraInicioCita);
                html += avisoHtml(
                    'asistencia',
                    'Registrar asistencia',
                    mensaje,
                    'Registrar asistencia',
                    item.urlAgenda || '',
                    'Ver paciente',
                    item.urlVerPaciente || ''
                );
            });
            html += '</div>';
        }

        if (historias.length) {
            html += '<div class="psi-pendiente-bloque mb-4" data-bloque="historia">';
            historias.forEach((item) => {
                html += avisoHtml(
                    'historia',
                    'Historia clínica inicial pendiente',
                    item.nombrePaciente || 'Paciente',
                    'Crear historia clínica inicial',
                    item.urlHistoria || '',
                    '',
                    ''
                );
            });
            html += '</div>';
        }

        if (seguimientos.length) {
            html += '<div class="psi-pendiente-bloque mb-4" data-bloque="seguimiento">';
            seguimientos.forEach((item) => {
                html += avisoHtml(
                    'seguimiento',
                    'Seguimiento terapéutico pendiente',
                    (item.nombrePaciente || 'Paciente') +
                        (item.fechaCita
                            ? ' · ' + formatearFechaCorta(item.fechaCita, '')
                            : ''),
                    'Registrar seguimiento',
                    item.urlSeguimiento || '',
                    '',
                    ''
                );
            });
            html += '</div>';
        }

        // Solo mutar DOM si cambió el conjunto de pendientes (evita ruido aria-live).
        if (firma !== ultimoFirmaAvisos) {
            contenedor.innerHTML = html;
            ultimoFirmaAvisos = firma;
        }

        programarProxima(
            datos.proximaEvaluacionIso,
            datos.ahora
        );
    }

    function programarProxima(isoProxima, isoAhoraServidor) {
        if (timerProxima) {
            clearTimeout(timerProxima);
            timerProxima = null;
        }

        if (!activo || !isoProxima || !isoAhoraServidor) {
            return;
        }

        const objetivo = Date.parse(isoProxima);
        const ahoraSrv = Date.parse(isoAhoraServidor);

        if (!Number.isFinite(objetivo) || !Number.isFinite(ahoraSrv)) {
            return;
        }

        // Duración relativa al reloj del servidor (no al del dispositivo).
        const espera = Math.max(1000, objetivo - ahoraSrv + 250);

        timerProxima = setTimeout(() => {
            refrescar();
        }, espera);
    }

    async function refrescar() {
        if (!activo || peticionPendientesEnCurso) {
            return;
        }

        peticionPendientesEnCurso = true;
        const miSecuencia = ++secuenciaSolicitud;
        abortController = new AbortController();
        const signal = abortController.signal;

        try {
            const respuesta = await fetch(url, {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal,
                redirect: 'follow'
            });

            if (miSecuencia !== secuenciaSolicitud) {
                return;
            }

            if (respuesta.status === 401 || respuesta.status === 403) {
                let codigo = '';
                try {
                    const cuerpo = await respuesta.clone().json();
                    codigo = String(cuerpo?.codigo || '');
                } catch (e) {
                    codigo = '';
                }

                // 403 por ClvPsi manipulado: no es sesión expirada.
                if (
                    respuesta.status === 403 &&
                    codigo === 'PSI_NO_PERMITIDO'
                ) {
                    return;
                }

                avisarSesionExpirada();
                return;
            }

            const tipo = String(
                respuesta.headers.get('content-type') || ''
            ).toLowerCase();

            if (!respuesta.ok) {
                // 5xx / error temporal: conservar avisos actuales.
                return;
            }

            if (!tipo.includes('application/json')) {
                avisarSesionExpirada();
                return;
            }

            const datos = await respuesta.json();

            if (miSecuencia !== secuenciaSolicitud) {
                return;
            }

            if (datos && datos.ok === false) {
                const codigo = String(datos.codigo || '');
                if (
                    codigo === 'SESION_INVALIDA' ||
                    codigo === 'NO_AUTORIZADO'
                ) {
                    avisarSesionExpirada();
                }
                return;
            }

            if (datos && datos.ok) {
                renderSnapshot(datos);
            }
        } catch (e) {
            if (e && e.name === 'AbortError') {
                return;
            }
            // Fallo de red: conservar información ya mostrada.
        } finally {
            if (miSecuencia === secuenciaSolicitud) {
                peticionPendientesEnCurso = false;
            }
        }
    }

    function limpiarAlDescartar() {
        detenerActualizaciones();
    }

    timerIntervalo = setInterval(() => {
        if (activo) {
            refrescar();
        }
    }, intervaloMs);

    refrescar();

    window.addEventListener('pagehide', limpiarAlDescartar);
    window.addEventListener('beforeunload', limpiarAlDescartar);
})();
