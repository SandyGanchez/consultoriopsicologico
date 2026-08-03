(function () {
    'use strict';

    var STORAGE_FONT = 'psicologo_accesibilidad_fuente';
    var STORAGE_CONTRAST = 'psicologo_accesibilidad_contraste';
    var root = document.documentElement;

    function applyAccessibility() {
        var font = localStorage.getItem(STORAGE_FONT) || 'normal';
        var contrast = localStorage.getItem(STORAGE_CONTRAST) || 'normal';

        root.classList.toggle(
            'psicologo-access-font-large',
            font === 'large'
        );
        root.classList.toggle(
            'psicologo-access-contrast',
            contrast === 'high'
        );

        document
            .querySelectorAll('[data-access-font]')
            .forEach(function (btn) {
                btn.classList.toggle(
                    'is-active',
                    btn.getAttribute('data-access-font') === font
                );
            });

        var contrastBtn = document.getElementById('btnAltoContraste');
        if (contrastBtn) {
            contrastBtn.classList.toggle(
                'is-active',
                contrast === 'high'
            );
        }
    }

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

    document
        .querySelectorAll('[data-access-font]')
        .forEach(function (btn) {
            btn.addEventListener('click', function () {
                localStorage.setItem(
                    STORAGE_FONT,
                    btn.getAttribute('data-access-font') || 'normal'
                );
                applyAccessibility();
            });
        });

    var contrastBtn = document.getElementById('btnAltoContraste');
    if (contrastBtn) {
        contrastBtn.addEventListener('click', function () {
            var actual = localStorage.getItem(STORAGE_CONTRAST) || 'normal';
            localStorage.setItem(
                STORAGE_CONTRAST,
                actual === 'high' ? 'normal' : 'high'
            );
            applyAccessibility();
        });
    }

    var resetBtn = document.getElementById('btnRestablecerAccesibilidad');
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            localStorage.removeItem(STORAGE_FONT);
            localStorage.removeItem(STORAGE_CONTRAST);
            applyAccessibility();
        });
    }

    applyAccessibility();

    var page = document.querySelector('.psicologo-config-page');
    if (page) {
        showToast(
            page.getAttribute('data-toast-tipo') || '',
            page.getAttribute('data-toast-mensaje') || ''
        );
    }
})();
