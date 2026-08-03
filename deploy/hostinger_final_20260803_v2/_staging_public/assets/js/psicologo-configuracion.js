(function () {
    'use strict';

    function showToast(tipo, mensaje) {
        var toastEl = document.getElementById('psicologoConfigToast');
        var titleEl = document.getElementById('psicologoConfigToastTitle');
        var bodyEl = document.getElementById('psicologoConfigToastBody');

        if (!toastEl || !titleEl || !bodyEl || !mensaje) {
            return;
        }

        toastEl.classList.remove(
            'psicologo-config-toast--success',
            'psicologo-config-toast--error'
        );

        if (tipo === 'success') {
            toastEl.classList.add('psicologo-config-toast--success');
            titleEl.textContent = 'Éxito';
        } else {
            toastEl.classList.add('psicologo-config-toast--error');
            titleEl.textContent = 'Atención';
        }

        bodyEl.textContent = mensaje;

        if (window.bootstrap && bootstrap.Toast) {
            var toast = bootstrap.Toast.getOrCreateInstance(toastEl, {
                autohide: true,
                delay: 5000
            });
            toast.show();
        }
    }

    document
        .querySelectorAll('.psicologo-config-toggle-pass')
        .forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = btn.getAttribute('data-target');
                var input = document.getElementById(targetId);
                if (!input) {
                    return;
                }

                var mostrar = input.type === 'password';
                input.type = mostrar ? 'text' : 'password';

                var icon = btn.querySelector('i');
                if (icon) {
                    icon.classList.toggle('bi-eye', !mostrar);
                    icon.classList.toggle('bi-eye-slash', mostrar);
                }

                btn.setAttribute(
                    'aria-label',
                    mostrar
                        ? 'Ocultar contraseña'
                        : 'Mostrar contraseña'
                );
            });
        });

    var page = document.querySelector('.psicologo-config-page');
    if (page) {
        showToast(
            page.getAttribute('data-toast-tipo') || '',
            page.getAttribute('data-toast-mensaje') || ''
        );
    }
})();
