document.addEventListener('DOMContentLoaded', () => {
    iniciarSidebarPsicologo();
});

function iniciarSidebarPsicologo() {
    if (window.__psicologoSidebarIniciado) {
        return;
    }

    const sidebar = document.getElementById('psicologoSidebar');
    const boton = document.getElementById('psicologoSidebarToggle');
    const overlay = document.getElementById('psicologoSidebarOverlay');

    if (!sidebar || !boton) {
        return;
    }

    window.__psicologoSidebarIniciado = true;

    const esMovil = () => window.innerWidth < 992;

    const sincronizarDesktop = () => {
        sidebar.classList.remove('show');
        overlay?.classList.remove('show');
        document.body.classList.remove('psicologo-sidebar-open');
        boton.setAttribute('aria-expanded', 'false');
        boton.setAttribute('aria-label', 'Abrir menú');
        sidebar.setAttribute('aria-hidden', esMovil() ? 'true' : 'false');
    };

    const abrirSidebar = () => {
        if (!esMovil()) {
            return;
        }

        sidebar.classList.add('show');
        overlay?.classList.add('show');
        document.body.classList.add('psicologo-sidebar-open');
        boton.setAttribute('aria-expanded', 'true');
        boton.setAttribute('aria-label', 'Cerrar menú');
        sidebar.setAttribute('aria-hidden', 'false');
    };

    const cerrarSidebar = (devolverFoco = false) => {
        sidebar.classList.remove('show');
        overlay?.classList.remove('show');
        document.body.classList.remove('psicologo-sidebar-open');
        boton.setAttribute('aria-expanded', 'false');
        boton.setAttribute('aria-label', 'Abrir menú');
        sidebar.setAttribute('aria-hidden', esMovil() ? 'true' : 'false');

        if (devolverFoco && esMovil()) {
            boton.focus();
        }
    };

    const alternar = () => {
        if (sidebar.classList.contains('show')) {
            cerrarSidebar();
        } else {
            abrirSidebar();
        }
    };

    boton.addEventListener('click', (event) => {
        event.preventDefault();
        alternar();
    });

    overlay?.addEventListener('click', () => {
        cerrarSidebar();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar.classList.contains('show')) {
            cerrarSidebar(true);
        }
    });

    sidebar
        .querySelectorAll(
            '.psicologo-menu-link, .psicologo-home-link, .psicologo-sidebar__logout'
        )
        .forEach((enlace) => {
            enlace.addEventListener('click', () => {
                if (esMovil()) {
                    cerrarSidebar();
                }
            });
        });

    window.addEventListener('resize', () => {
        if (!esMovil()) {
            sincronizarDesktop();
        } else if (!sidebar.classList.contains('show')) {
            sidebar.setAttribute('aria-hidden', 'true');
            boton.setAttribute('aria-expanded', 'false');
        }
    });

    sincronizarDesktop();

    const enlaceActivo = sidebar.querySelector(
        '.psicologo-sidebar__menu .psicologo-menu-link.active'
    );
    if (enlaceActivo && typeof enlaceActivo.scrollIntoView === 'function') {
        enlaceActivo.scrollIntoView({
            block: 'nearest',
            inline: 'nearest',
            behavior: 'auto'
        });
    }
}
