(function () {
    'use strict';

    function qs(root, sel) {
        return root.querySelector(sel);
    }

    function initCampana(root) {
        var toggle = qs(root, '[data-notif-toggle]');
        var dropdown = qs(root, '[data-notif-dropdown]');
        var badge = qs(root, '[data-notif-badge]');
        var btnMarcarTodas = qs(root, '[data-notif-marcar-todas]');

        if (!toggle || !dropdown) {
            return;
        }

        function cerrar() {
            dropdown.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
        }

        function abrir() {
            dropdown.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
        }

        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (dropdown.hidden) {
                abrir();
            } else {
                cerrar();
            }
        });

        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) {
                cerrar();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                cerrar();
            }
        });

        function actualizarBadge(total) {
            if (!badge) {
                return;
            }

            var n = parseInt(total, 10) || 0;

            if (n <= 0) {
                badge.textContent = '0';
                badge.classList.add('is-hidden');
                badge.setAttribute('aria-hidden', 'true');
                badge.removeAttribute('aria-label');

                if (btnMarcarTodas) {
                    btnMarcarTodas.hidden = true;
                    btnMarcarTodas.classList.add('is-hidden');
                }

                return;
            }

            badge.textContent = n > 99 ? '99+' : String(n);
            badge.classList.remove('is-hidden');
            badge.removeAttribute('aria-hidden');
            badge.setAttribute(
                'aria-label',
                n + (n === 1 ? ' no leída' : ' no leídas')
            );

            if (btnMarcarTodas) {
                btnMarcarTodas.hidden = false;
                btnMarcarTodas.classList.remove('is-hidden');
            }
        }

        if (btnMarcarTodas) {
            btnMarcarTodas.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                var url = root.getAttribute('data-url-marcar-todas');
                var csrf = root.getAttribute('data-csrf') || '';

                if (!url) {
                    return;
                }

                var body = new FormData();
                body.append('csrf_token', csrf);

                fetch(url, {
                    method: 'POST',
                    body: body,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf
                    }
                })
                    .then(function (res) {
                        return res.json().then(function (data) {
                            return {
                                status: res.status,
                                data: data || { ok: false }
                            };
                        }).catch(function () {
                            return {
                                status: res.status,
                                data: { ok: false }
                            };
                        });
                    })
                    .then(function (result) {
                        var data = result.data || {};

                        if (
                            result.status === 401 ||
                            data.codigo === 'SESION_EXPIRADA'
                        ) {
                            window.location.href = (
                                root.getAttribute('data-url-login') ||
                                '/login'
                            );
                            return;
                        }

                        if (!data.ok) {
                            return;
                        }

                        actualizarBadge(0);

                        root.querySelectorAll('.pm-notif-item').forEach(
                            function (item) {
                                item.classList.remove('is-unread');
                            }
                        );
                    })
                    .catch(function () {
                        /* silencio intencional */
                    });
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document
            .querySelectorAll('[data-notif-campana]')
            .forEach(initCampana);
    });
})();
