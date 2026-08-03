(function () {
    'use strict';

    var SCALE = {
        normal: '1',
        large: '1.10',
        xlarge: '1.20'
    };

    var scaleKey = 'psico_font_scale';
    var boldKey = 'psico_bold_text';

    function leer() {
        var scale = 'normal';
        var bold = 'false';

        try {
            scale = String(localStorage.getItem(scaleKey) || 'normal');
            bold = String(localStorage.getItem(boldKey) || 'false');
        } catch (e) {
            /* ignore */
        }

        if (!Object.prototype.hasOwnProperty.call(SCALE, scale)) {
            scale = 'normal';
        }

        if (bold !== 'true' && bold !== 'false') {
            bold = 'false';
        }

        return { scale: scale, bold: bold };
    }

    function aplicar(prefs) {
        var root = document.documentElement;
        root.setAttribute('data-font-size', prefs.scale);
        root.setAttribute('data-bold-text', prefs.bold);
        root.style.setProperty('--app-font-scale', SCALE[prefs.scale]);
        root.style.setProperty(
            '--app-font-weight',
            prefs.bold === 'true' ? '600' : '400'
        );
        root.style.setProperty('--app-heading-weight', '700');

        document.querySelectorAll('[data-apariencia-scale]').forEach(function (el) {
            el.value = prefs.scale;
        });

        document.querySelectorAll('[data-apariencia-bold]').forEach(function (el) {
            el.value = prefs.bold;
        });
    }

    function guardar(prefs) {
        try {
            localStorage.setItem(scaleKey, prefs.scale);
            localStorage.setItem(boldKey, prefs.bold);
        } catch (e) {
            /* ignore */
        }

        aplicar(prefs);
    }

    function bind() {
        document.querySelectorAll('[data-apariencia-scale]').forEach(function (el) {
            el.addEventListener('change', function () {
                var prefs = leer();
                prefs.scale = el.value;
                guardar(prefs);
            });
        });

        document.querySelectorAll('[data-apariencia-bold]').forEach(function (el) {
            el.addEventListener('change', function () {
                var prefs = leer();
                prefs.bold = el.value;
                guardar(prefs);
            });
        });

        document.querySelectorAll('[data-apariencia-reset]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                guardar({ scale: 'normal', bold: 'false' });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        aplicar(leer());
        bind();
    });
})();
