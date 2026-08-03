(function () {
    'use strict';

    function host() {
        var el = document.getElementById('pmToastHost');

        if (el) {
            return el;
        }

        el = document.createElement('div');
        el.id = 'pmToastHost';
        el.className = 'pm-toast-host';
        el.setAttribute('aria-live', 'polite');
        document.body.appendChild(el);

        return el;
    }

    function mostrar(mensaje, tipo) {
        var texto = String(mensaje || '').trim();

        if (texto === '') {
            return;
        }

        var toast = document.createElement('div');
        toast.className = 'pm-toast is-' + (tipo === 'error' ? 'error' : 'success');
        toast.setAttribute('role', tipo === 'error' ? 'alert' : 'status');

        var body = document.createElement('div');
        body.textContent = texto;

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'pm-toast__close';
        close.setAttribute('aria-label', 'Cerrar aviso');
        close.innerHTML = '&times;';

        var timer = null;
        var paused = false;
        var remaining = 6000;

        function cerrar() {
            if (timer) {
                window.clearTimeout(timer);
            }
            toast.remove();
        }

        function programar() {
            timer = window.setTimeout(cerrar, remaining);
        }

        close.addEventListener('click', cerrar);
        toast.addEventListener('mouseenter', function () {
            paused = true;
            if (timer) {
                window.clearTimeout(timer);
            }
        });
        toast.addEventListener('mouseleave', function () {
            if (paused) {
                paused = false;
                programar();
            }
        });

        toast.appendChild(body);
        toast.appendChild(close);
        host().appendChild(toast);
        programar();

        while (host().children.length > 3) {
            host().removeChild(host().firstChild);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-pm-toast]').forEach(function (el) {
            var tipo = el.getAttribute('data-pm-toast') || 'success';
            var mensaje = el.textContent || '';
            mostrar(mensaje, tipo);
            el.hidden = true;
        });
    });

    window.PmToast = { mostrar: mostrar };
})();
