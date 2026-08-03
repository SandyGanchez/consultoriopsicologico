/**
 * Anti doble-submit para formularios de cambio de correo.
 * Una sola estrategia: submit HTML nativo (sin fetch paralelo).
 */
(function () {
    'use strict';

    function inicializar() {
        var root = document.querySelector('[data-cambio-correo-root]');

        if (!root || root.getAttribute('data-cambio-correo-inicializado') === '1') {
            return;
        }

        root.setAttribute('data-cambio-correo-inicializado', '1');

        var formularios = root.querySelectorAll('form[data-cambio-correo-accion]');

        formularios.forEach(function (form) {
            if (form.getAttribute('data-cambio-correo-bound') === '1') {
                return;
            }

            form.setAttribute('data-cambio-correo-bound', '1');

            form.addEventListener('submit', function (event) {
                if (form.getAttribute('data-enviando') === '1') {
                    event.preventDefault();
                    return;
                }

                form.setAttribute('data-enviando', '1');

                var botones = form.querySelectorAll(
                    'button[type="submit"], input[type="submit"]'
                );

                botones.forEach(function (boton) {
                    boton.disabled = true;

                    if (boton.tagName === 'BUTTON') {
                        if (!boton.getAttribute('data-texto-original')) {
                            boton.setAttribute(
                                'data-texto-original',
                                boton.textContent || ''
                            );
                        }

                        var accion = form.getAttribute('data-cambio-correo-accion');
                        var texto = 'Enviando…';

                        if (accion === 'verificar') {
                            texto = 'Verificando…';
                        } else if (accion === 'cancelar') {
                            texto = 'Cancelando…';
                        } else if (accion === 'reenviar') {
                            texto = 'Reenviando…';
                        }

                        boton.textContent = texto;
                    }
                });
            });
        });

        var reenviarBtn = root.querySelector('[data-reenvio-cooldown]');
        var timerLabel = root.querySelector('[data-reenvio-timer]');

        if (!reenviarBtn || !timerLabel) {
            return;
        }

        var segundos = parseInt(
            reenviarBtn.getAttribute('data-reenvio-cooldown') || '0',
            10
        );

        if (!Number.isFinite(segundos) || segundos <= 0) {
            return;
        }

        reenviarBtn.disabled = true;

        var id = window.setInterval(function () {
            segundos -= 1;

            if (segundos <= 0) {
                window.clearInterval(id);
                timerLabel.textContent = '0';
                // Solo habilitación visual; el backend sigue siendo la fuente de verdad.
                if (reenviarBtn.getAttribute('data-reenvio-permitido') === '1') {
                    reenviarBtn.disabled = false;
                }
                return;
            }

            timerLabel.textContent = String(segundos);
        }, 1000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializar);
    } else {
        inicializar();
    }
})();
