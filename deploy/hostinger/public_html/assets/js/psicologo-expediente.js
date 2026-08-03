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
            container.appendChild(wrap.firstElementChild);
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

        event.preventDefault();
        event.returnValue = '';
    });
})();
