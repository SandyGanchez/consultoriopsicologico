document.addEventListener('DOMContentLoaded', () => {

    const sidebar = document.getElementById(
        'psicologoSidebar'
    );

    const boton = document.getElementById(
        'psicologoSidebarToggle'
    );

    const overlay = document.getElementById(
        'psicologoSidebarOverlay'
    );

    const abrirSidebar = () => {
        sidebar?.classList.add('show');
        overlay?.classList.add('show');
    };

    const cerrarSidebar = () => {
        sidebar?.classList.remove('show');
        overlay?.classList.remove('show');
    };

    boton?.addEventListener(
        'click',
        abrirSidebar
    );

    overlay?.addEventListener(
        'click',
        cerrarSidebar
    );

    document
        .querySelectorAll('.psicologo-menu-link')
        .forEach(enlace => {
            enlace.addEventListener(
                'click',
                () => {
                    if (window.innerWidth < 992) {
                        cerrarSidebar();
                    }
                }
            );
        });

});