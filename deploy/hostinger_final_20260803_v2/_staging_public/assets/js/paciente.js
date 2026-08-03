document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('pacienteSidebar');
    const boton = document.getElementById('pacienteSidebarToggle');
    const overlay = document.getElementById('pacienteSidebarOverlay');

    const abrirSidebar = () => {
        sidebar?.classList.add('show');
        overlay?.classList.add('show');
        boton?.setAttribute('aria-expanded', 'true');
        boton?.setAttribute('aria-label', 'Cerrar menú');
    };

    const cerrarSidebar = () => {
        sidebar?.classList.remove('show');
        overlay?.classList.remove('show');
        boton?.setAttribute('aria-expanded', 'false');
        boton?.setAttribute('aria-label', 'Abrir menú');
    };

    boton?.addEventListener('click', () => {
        if (sidebar?.classList.contains('show')) {
            cerrarSidebar();
        } else {
            abrirSidebar();
        }
    });

    overlay?.addEventListener('click', cerrarSidebar);

    document.querySelectorAll('.paciente-menu-link').forEach((enlace) => {
        enlace.addEventListener('click', () => {
            if (window.innerWidth < 992) {
                cerrarSidebar();
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            cerrarSidebar();
        }
    });
});
