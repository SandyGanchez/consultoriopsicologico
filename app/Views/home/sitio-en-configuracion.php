<?php

use App\Helpers\Helper;

$esAdministrador = !empty($esAdministrador);

?>

<section class="py-5">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-7 text-center">
                <?php if ($esAdministrador): ?>
                    <h1 class="h3 mb-3">Configura el consultorio para comenzar</h1>
                    <p class="text-muted mb-4">
                        Esta instalación aún no tiene un consultorio configurado,
                        o su página pública no está disponible.
                    </p>
                    <a
                        href="<?= Helper::baseUrl('administrador/consultorios'); ?>"
                        class="btn btn-primary rounded-pill px-4"
                    >
                        Configurar consultorio
                    </a>
                <?php else: ?>
                    <h1 class="h3 mb-3">El sitio se encuentra en proceso de configuración.</h1>
                    <p class="text-muted mb-0">
                        Vuelve más tarde o inicia sesión si ya tienes una cuenta.
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
                        <a
                            href="<?= Helper::baseUrl('login'); ?>"
                            class="btn btn-outline-secondary rounded-pill px-4"
                        >
                            Iniciar sesión
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
