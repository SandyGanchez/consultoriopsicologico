<?php

use App\Helpers\Helper;

$esAdministrador = !empty($esAdministrador);

?>

<section class="py-5">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-7 text-center">
                <?php if ($esAdministrador): ?>
                    <h1 class="h3 mb-3">Error de configuración de la instalación</h1>
                    <p class="text-muted mb-4">
                        Se detectó más de un consultorio en la base de datos.
                        Esta instalación admite exactamente uno. El incidente quedó
                        registrado en el log del servidor.
                    </p>
                    <a
                        href="<?= Helper::baseUrl('administrador'); ?>"
                        class="btn btn-primary rounded-pill px-4"
                    >
                        Ir al panel
                    </a>
                <?php else: ?>
                    <h1 class="h3 mb-3">El sitio se encuentra en proceso de configuración.</h1>
                    <p class="text-muted mb-0">
                        Vuelve más tarde.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
