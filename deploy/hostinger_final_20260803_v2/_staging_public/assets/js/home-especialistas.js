(function () {
    'use strict';

    if (document.documentElement.getAttribute('data-home-especialistas-ready') === '1') {
        return;
    }

    document.documentElement.setAttribute('data-home-especialistas-ready', '1');

    var modales = document.querySelectorAll('.specialist-modal');

    if (!modales.length || typeof bootstrap === 'undefined') {
        return;
    }

    modales.forEach(function (modalEl) {
        modalEl.addEventListener('shown.bs.modal', function () {
            var closeBtn = modalEl.querySelector('[data-bs-dismiss="modal"]');
            if (closeBtn instanceof HTMLElement) {
                closeBtn.focus();
            }
        });
    });
})();
