(function () {
    'use strict';

    var form = document.getElementById('formHistoriaClinica');
    var dirty = false;
    var submitting = false;

    var templates = {
        listaAntecedentesPat: 'tplAntecedentePat',
        listaAntecedentesFam: 'tplAntecedenteFam',
        listaAdicciones: 'tplAdiccion',
        listaReactivos: 'tplReactivo',
        listaRecomendaciones: 'tplRecomendacion'
    };

    function nextIndex(container) {
        return container.querySelectorAll('.psi-dynamic-row').length;
    }

    function syncConditionalRow(row) {
        if (!row) {
            return;
        }

        var detail = row.querySelector('.psi-cond-detail');
        var toggle = row.querySelector(
            '.psi-toggle-presenta, .psi-toggle-presenta-fam, .psi-toggle-tratamiento'
        );

        if (detail && toggle) {
            detail.classList.toggle('is-hidden', !toggle.checked);
        }
    }

    function syncAllConditionals(scope) {
        var root = scope || document;

        root.querySelectorAll('.psi-dynamic-row').forEach(syncConditionalRow);

        var fugaToggle = root.querySelector('#toggleFugaHogar');
        var fugaDetalle = document.getElementById('detalleFugaHogar');

        if (fugaToggle && fugaDetalle) {
            fugaDetalle.classList.toggle('is-hidden', !fugaToggle.checked);
        }
    }

    document.addEventListener('change', function (event) {
        var target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        if (
            target.classList.contains('psi-toggle-presenta') ||
            target.classList.contains('psi-toggle-presenta-fam') ||
            target.classList.contains('psi-toggle-tratamiento')
        ) {
            syncConditionalRow(target.closest('.psi-dynamic-row'));
        }

        if (target.id === 'toggleFugaHogar') {
            var fugaDetalle = document.getElementById('detalleFugaHogar');
            if (fugaDetalle) {
                fugaDetalle.classList.toggle('is-hidden', !target.checked);
            }
        }
    });

    document.querySelectorAll('[data-add-row]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var listId = btn.getAttribute('data-add-row');
            var container = document.getElementById(listId);
            var tplId = templates[listId];
            var tpl = tplId ? document.getElementById(tplId) : null;

            if (!container || !tpl) {
                return;
            }

            var index = nextIndex(container);
            var html = tpl.innerHTML.replace(/__INDEX__/g, String(index));
            var wrap = document.createElement('div');
            wrap.innerHTML = html.trim();
            var nuevaFila = wrap.firstElementChild;
            container.appendChild(nuevaFila);
            syncConditionalRow(nuevaFila);
            dirty = true;
        });
    });

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        var removeBtn = target.closest('[data-remove-new-row]');
        if (!removeBtn) {
            return;
        }

        var row = removeBtn.closest('.psi-dynamic-row');
        if (!row || row.classList.contains('psi-dynamic-row--persistida')) {
            return;
        }

        row.remove();
        dirty = true;
    });

    if (form) {
        form.addEventListener('input', function () {
            dirty = true;
        });
        form.addEventListener('change', function () {
            dirty = true;
        });

        form.addEventListener('submit', function (event) {
            if (submitting) {
                event.preventDefault();
                return;
            }

            if (wizard && !runFullValidation()) {
                event.preventDefault();
                return;
            }

            submitting = true;
            dirty = false;

            var btn = document.getElementById('btnGuardarHistoria');
            if (btn) {
                btn.disabled = true;
                btn.classList.add('is-loading');
                btn.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Guardando…';
            }
        });
    }

    window.addEventListener('beforeunload', function (event) {
        if (!dirty || submitting) {
            return;
        }

        var mensaje = 'Hay información clínica sin guardar. ¿Deseas salir?';
        event.preventDefault();
        event.returnValue = mensaje;
        return mensaje;
    });

    document.addEventListener('click', function (event) {
        var link = event.target.closest('[data-confirm-unsaved="1"]');

        if (!link || !dirty || submitting) {
            return;
        }

        var ok = window.confirm(
            'Si sales ahora, los datos de la historia clínica que aún no hayas guardado se perderán. ¿Deseas continuar?'
        );

        if (!ok) {
            event.preventDefault();
        }
    });

    syncAllConditionals(document);

    var wizard = document.getElementById('wizardHistoriaClinica');

    if (!wizard) {
        return;
    }

    var steps = Array.prototype.slice.call(
        wizard.querySelectorAll('.psi-historia-step')
    );
    var progressSteps = Array.prototype.slice.call(
        wizard.querySelectorAll('[data-progress-step]')
    );
    var totalSteps = steps.length;
    var pasoInicial = parseInt(wizard.getAttribute('data-paso-inicial'), 10) || 1;
    var currentStep = pasoInicial >= 1 && pasoInicial <= totalSteps ? pasoInicial : 1;
    var maxStepReached = currentStep;

    var btnAnterior = document.getElementById('btnPasoAnterior');
    var btnSiguiente = document.getElementById('btnPasoSiguiente');
    var btnGuardar = document.getElementById('btnGuardarHistoria');
    var erroresBox = document.getElementById('historiaErroresResumen');

    function stepNumber(el) {
        return parseInt(el.getAttribute('data-step'), 10);
    }

    function limpiarErrores() {
        if (!erroresBox) {
            return;
        }
        erroresBox.innerHTML = '';
        erroresBox.classList.add('is-hidden');
    }

    function mostrarErrores(mensajes) {
        if (!erroresBox || mensajes.length === 0) {
            return;
        }

        var lista = document.createElement('ul');
        mensajes.forEach(function (mensaje) {
            var item = document.createElement('li');
            item.textContent = mensaje;
            lista.appendChild(item);
        });

        erroresBox.innerHTML = '';
        erroresBox.appendChild(lista);
        erroresBox.classList.remove('is-hidden');
    }

    function focoEnCampo(campo) {
        if (campo && typeof campo.focus === 'function') {
            campo.focus();
            if (typeof campo.scrollIntoView === 'function') {
                campo.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }

    function validarPaso1() {
        var errores = [];
        var motivo = form.querySelector('[name="estado[MotivoConsulta]"]');

        if (!motivo || motivo.value.trim() === '') {
            errores.push('El motivo de consulta es obligatorio.');
            focoEnCampo(motivo);
        }

        return errores;
    }

    function validarPaso6() {
        var errores = [];
        var filas = document.querySelectorAll(
            '#listaReactivos .psi-dynamic-row'
        );
        var primerCampoInvalido = null;

        filas.forEach(function (fila) {
            var nombre = fila.querySelector('[name*="[NombreReactivo]"]');
            var fecha = fila.querySelector('[name*="[FechaAplicacion]"]');

            if (
                nombre &&
                nombre.value.trim() !== '' &&
                fecha &&
                fecha.value.trim() === ''
            ) {
                errores.push(
                    'El reactivo "' +
                        nombre.value.trim() +
                        '" debe indicar su fecha de aplicación.'
                );
                if (!primerCampoInvalido) {
                    primerCampoInvalido = fecha;
                }
            }
        });

        if (primerCampoInvalido) {
            focoEnCampo(primerCampoInvalido);
        }

        return errores;
    }

    function validarPaso(numero) {
        if (numero === 1) {
            return validarPaso1();
        }
        if (numero === 6) {
            return validarPaso6();
        }
        return [];
    }

    function runFullValidation() {
        var erroresPaso1 = validarPaso1();
        var erroresPaso6 = validarPaso6();
        var errores = erroresPaso1.concat(erroresPaso6);

        if (errores.length > 0) {
            mostrarErrores(errores);
            irAPaso(erroresPaso1.length > 0 ? 1 : 6);
            return false;
        }

        limpiarErrores();
        return true;
    }

    function actualizarNav() {
        if (btnAnterior) {
            btnAnterior.classList.toggle('is-hidden', currentStep === 1);
        }
        if (btnSiguiente) {
            btnSiguiente.classList.toggle(
                'is-hidden',
                currentStep === totalSteps
            );
        }
        if (btnGuardar) {
            btnGuardar.classList.toggle(
                'is-hidden',
                currentStep !== totalSteps
            );
        }
    }

    function actualizarProgreso() {
        progressSteps.forEach(function (li) {
            var numero = parseInt(li.getAttribute('data-progress-step'), 10);
            li.classList.toggle('is-active', numero === currentStep);
            li.classList.toggle('is-done', numero < currentStep);
        });
    }

    function mostrarPaso(numero) {
        steps.forEach(function (stepEl) {
            stepEl.classList.toggle('is-active', stepNumber(stepEl) === numero);
        });
        actualizarProgreso();
        actualizarNav();

        var campoPaso = document.getElementById('campoPasoActual');
        if (campoPaso) {
            campoPaso.value = String(numero);
        }

        if (numero === totalSteps) {
            construirResumen();
        }
    }

    function irAPaso(numero) {
        if (numero < 1 || numero > totalSteps) {
            return;
        }
        currentStep = numero;
        if (numero > maxStepReached) {
            maxStepReached = numero;
        }
        mostrarPaso(numero);
    }

    if (btnSiguiente) {
        btnSiguiente.addEventListener('click', function () {
            var errores = validarPaso(currentStep);

            if (errores.length > 0) {
                mostrarErrores(errores);
                return;
            }

            limpiarErrores();
            irAPaso(currentStep + 1);
        });
    }

    if (btnAnterior) {
        btnAnterior.addEventListener('click', function () {
            limpiarErrores();
            irAPaso(currentStep - 1);
        });
    }

    progressSteps.forEach(function (li) {
        li.addEventListener('click', function () {
            var numero = parseInt(li.getAttribute('data-progress-step'), 10);
            if (numero <= maxStepReached) {
                limpiarErrores();
                irAPaso(numero);
            }
        });
    });

    function contarConValor(selector, extractor) {
        var total = 0;
        document.querySelectorAll(selector).forEach(function (el) {
            if (extractor(el)) {
                total += 1;
            }
        });
        return total;
    }

    function campoTieneValor(nombreSelector) {
        var campo = form.querySelector(nombreSelector);
        return !!campo && campo.value.trim() !== '';
    }

    function crearTarjetaResumen(etiqueta, valor) {
        var tarjeta = document.createElement('div');
        tarjeta.className = 'psi-resumen-card';

        var spanEtiqueta = document.createElement('span');
        spanEtiqueta.className = 'psi-resumen-card__label';
        spanEtiqueta.textContent = etiqueta;

        var spanValor = document.createElement('strong');
        spanValor.className = 'psi-resumen-card__value';
        spanValor.textContent = valor;

        tarjeta.appendChild(spanEtiqueta);
        tarjeta.appendChild(spanValor);

        return tarjeta;
    }

    function construirResumen() {
        var contenedor = document.getElementById('resumenRevision');
        if (!contenedor) {
            return;
        }

        var motivoRegistrado = campoTieneValor('[name="estado[MotivoConsulta]"]');

        var antPatConTipo = contarConValor(
            '#listaAntecedentesPat .psi-dynamic-row',
            function (fila) {
                var tipo = fila.querySelector('[name*="[TipoAntecedente]"]');
                return !!tipo && tipo.value.trim() !== '';
            }
        );

        var antFamConTipo = contarConValor(
            '#listaAntecedentesFam .psi-dynamic-row',
            function (fila) {
                var tipo = fila.querySelector('[name*="[TipoAntecedenteFam]"]');
                return !!tipo && tipo.value.trim() !== '';
            }
        );

        var adiccionesConTipo = contarConValor(
            '#listaAdicciones .psi-dynamic-row',
            function (fila) {
                var tipo = fila.querySelector('[name*="[TipoAdiccion]"]');
                return !!tipo && tipo.value.trim() !== '';
            }
        );

        var reactivosConNombre = contarConValor(
            '#listaReactivos .psi-dynamic-row',
            function (fila) {
                var nombre = fila.querySelector('[name*="[NombreReactivo]"]');
                return !!nombre && nombre.value.trim() !== '';
            }
        );

        var apreciacionRegistrada = campoTieneValor(
            '[name="apreciacion[DiagnosticoInicial]"]'
        );

        var fechaEntrevista = form.querySelector('[name="FechaEntrevistaInicial"]');
        var fechaTexto =
            fechaEntrevista && fechaEntrevista.value
                ? fechaEntrevista.value
                : 'No especificada';

        contenedor.innerHTML = '';
        contenedor.appendChild(
            crearTarjetaResumen(
                'Fecha de entrevista inicial',
                fechaTexto
            )
        );
        contenedor.appendChild(
            crearTarjetaResumen(
                'Motivo de consulta',
                motivoRegistrado ? 'Registrado' : 'Pendiente'
            )
        );
        contenedor.appendChild(
            crearTarjetaResumen(
                'Antecedentes patológicos',
                antPatConTipo + ' registrado(s)'
            )
        );
        contenedor.appendChild(
            crearTarjetaResumen(
                'Antecedentes familiares',
                antFamConTipo + ' registrado(s)'
            )
        );
        contenedor.appendChild(
            crearTarjetaResumen(
                'Adicciones',
                adiccionesConTipo + ' registrada(s)'
            )
        );
        contenedor.appendChild(
            crearTarjetaResumen(
                'Reactivos aplicados',
                reactivosConNombre + ' registrado(s)'
            )
        );
        contenedor.appendChild(
            crearTarjetaResumen(
                'Apreciación diagnóstica',
                apreciacionRegistrada ? 'Registrada' : 'Pendiente'
            )
        );
    }

    mostrarPaso(currentStep);
})();
