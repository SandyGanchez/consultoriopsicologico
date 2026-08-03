(function () {
    'use strict';

    if (window.__pmNotifCampanaInit) {
        return;
    }
    window.__pmNotifCampanaInit = true;

    var abierta = null;

    function qs(root, sel) {
        return root.querySelector(sel);
    }

    function cerrar(root) {
        if (!root) {
            return;
        }

        var toggle = qs(root, '[data-notif-toggle]');
        var dropdown = qs(root, '[data-notif-dropdown]');

        if (!toggle || !dropdown) {
            return;
        }

        dropdown.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');

        if (abierta === root) {
            abierta = null;
        }
    }

    function abrir(root) {
        if (abierta && abierta !== root) {
            cerrar(abierta);
        }

        var toggle = qs(root, '[data-notif-toggle]');
        var dropdown = qs(root, '[data-notif-dropdown]');

        if (!toggle || !dropdown) {
            return;
        }

        dropdown.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        abierta = root;
    }

    function actualizarBadge(root, total) {
        var badge = qs(root, '[data-notif-badge]');
        var btnMarcarTodas = qs(root, '[data-notif-marcar-todas]');

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

    function initCampana(root) {
        if (root.getAttribute('data-notif-ready') === '1') {
            return;
        }

        root.setAttribute('data-notif-ready', '1');

        var toggle = qs(root, '[data-notif-toggle]');
        var dropdown = qs(root, '[data-notif-dropdown]');
        var btnMarcarTodas = qs(root, '[data-notif-marcar-todas]');

        if (!toggle || !dropdown) {
            return;
        }

        if (!toggle.getAttribute('aria-controls')) {
            var id = dropdown.id || ('pmNotifDropdown_' + Math.random().toString(36).slice(2, 8));
            dropdown.id = id;
            toggle.setAttribute('aria-controls', id);
        }

        toggle.setAttribute('aria-haspopup', 'true');
        toggle.setAttribute('aria-expanded', dropdown.hidden ? 'false' : 'true');

        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (dropdown.hidden) {
                abrir(root);
            } else {
                cerrar(root);
                toggle.focus();
            }
        });

        if (btnMarcarTodas) {
            var enviando = false;

            btnMarcarTodas.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                if (enviando) {
                    return;
                }

                var url = root.getAttribute('data-url-marcar-todas');
                var csrf = root.getAttribute('data-csrf') || '';

                if (!url) {
                    return;
                }

                enviando = true;
                btnMarcarTodas.disabled = true;

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
                            if (window.PmToast) {
                                window.PmToast.mostrar(
                                    'No fue posible marcar las notificaciones.',
                                    'error'
                                );
                            }
                            return;
                        }

                        actualizarBadge(root, 0);

                        root.querySelectorAll('.pm-notif-item').forEach(
                            function (item) {
                                item.classList.remove('is-unread');
                                var estado = item.querySelector('.pm-notif-item-estado');
                                if (estado) {
                                    estado.textContent = 'Leída';
                                }
                            }
                        );
                    })
                    .catch(function () {
                        if (window.PmToast) {
                            window.PmToast.mostrar(
                                'No fue posible marcar las notificaciones.',
                                'error'
                            );
                        }
                    })
                    .finally(function () {
                        enviando = false;
                        btnMarcarTodas.disabled = false;
                    });
            });
        }
    }

    document.addEventListener('click', function (e) {
        if (!abierta) {
            return;
        }

        if (!abierta.contains(e.target)) {
            var toggle = qs(abierta, '[data-notif-toggle]');
            cerrar(abierta);
            if (toggle) {
                toggle.focus();
            }
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || !abierta) {
            return;
        }

        var toggle = qs(abierta, '[data-notif-toggle]');
        cerrar(abierta);
        if (toggle) {
            toggle.focus();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        document
            .querySelectorAll('[data-notif-campana]')
            .forEach(initCampana);
    });
})();
