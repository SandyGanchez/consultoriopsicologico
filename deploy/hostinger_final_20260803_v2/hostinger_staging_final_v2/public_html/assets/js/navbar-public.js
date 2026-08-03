(function () {
    'use strict';

    var nav = document.getElementById('navbarPublico');
    var menu = document.getElementById('menuPublico');
    var toggler = document.getElementById('btnMenuPublico');

    if (!nav || !menu || !toggler || typeof bootstrap === 'undefined') {
        return;
    }

    if (nav.getAttribute('data-navbar-public-ready') === '1') {
        return;
    }

    nav.setAttribute('data-navbar-public-ready', '1');

    var collapse = bootstrap.Collapse.getOrCreateInstance(menu, {
        toggle: false
    });

    function menuAbierto() {
        return menu.classList.contains('show');
    }

    function cerrarMenu() {
        if (menuAbierto()) {
            collapse.hide();
        }
    }

    function marcarActivo(sectionId) {
        var links = nav.querySelectorAll('[data-nav-section]');

        links.forEach(function (link) {
            var esActivo = link.getAttribute('data-nav-section') === sectionId;

            if (esActivo) {
                link.classList.add('is-active');
                link.setAttribute('aria-current', 'true');
            } else {
                link.classList.remove('is-active');
                link.removeAttribute('aria-current');
            }
        });
    }

    function actualizarOffsetNavbar() {
        var altura = Math.ceil(nav.getBoundingClientRect().height) || 88;
        document.documentElement.style.setProperty(
            '--navbar-public-offset',
            (altura + 12) + 'px'
        );
    }

    menu.addEventListener('show.bs.collapse', function () {
        toggler.setAttribute('aria-expanded', 'true');
        toggler.setAttribute('aria-label', 'Cerrar menú de navegación');
    });

    menu.addEventListener('hide.bs.collapse', function () {
        toggler.setAttribute('aria-expanded', 'false');
        toggler.setAttribute('aria-label', 'Abrir menú de navegación');
    });

    menu.addEventListener('shown.bs.collapse', actualizarOffsetNavbar);
    menu.addEventListener('hidden.bs.collapse', actualizarOffsetNavbar);

    nav.querySelectorAll('a[href]').forEach(function (link) {
        link.addEventListener('click', function () {
            var sectionId = link.getAttribute('data-nav-section') || '';

            if (sectionId !== '') {
                marcarActivo(sectionId);
            }

            if (window.innerWidth < 992) {
                cerrarMenu();
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            cerrarMenu();
        }
    });

    document.addEventListener('click', function (event) {
        if (!menuAbierto()) {
            return;
        }

        var target = event.target;

        if (!(target instanceof Node)) {
            return;
        }

        if (!nav.contains(target)) {
            cerrarMenu();
        }
    });

    window.addEventListener('resize', function () {
        actualizarOffsetNavbar();

        if (window.innerWidth >= 992) {
            cerrarMenu();
        }
    });

    if (window.location.hash) {
        var hash = window.location.hash.replace('#', '');
        if (hash !== '') {
            marcarActivo(hash);
        }
    }

    var scrolled = false;

    window.addEventListener(
        'scroll',
        function () {
            var debeTenerSombra = window.scrollY > 8;

            if (debeTenerSombra === scrolled) {
                return;
            }

            scrolled = debeTenerSombra;
            nav.classList.toggle('navbar-public--scrolled', scrolled);
        },
        { passive: true }
    );

    actualizarOffsetNavbar();
})();
