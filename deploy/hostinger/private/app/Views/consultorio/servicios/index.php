<?php

use App\Core\Session;
use App\Helpers\Helper;

$csrf = Session::csrfToken();

function resumirDescripcion(string $texto, int $limite = 70): string
{
    if (strlen($texto) <= $limite) {
        return $texto;
    }

    return substr($texto, 0, $limite - 3) . '...';
}

?>

<section class="clinic-services-page clinic-services-header">

    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">

        <div>

            <span class="consultorio-page-eyebrow">
                Catálogo general
            </span>

            <h1>Servicios del consultorio</h1>

            <p>
                Administra el catálogo de servicios que pueden ofrecerse
                dentro de tus instalaciones. Los valores aquí son referencia;
                cada especialista configurará después su precio y duración.
            </p>

        </div>

        <a
            href="<?= Helper::baseUrl('consultorio/servicios/nuevo'); ?>"
            class="btn btn-clinic-primary"
        >
            <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>
            Registrar servicio
        </a>

    </div>

    <?php if (!empty($_SESSION['success'])): ?>

        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>

        <?php unset($_SESSION['success']); ?>

    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>

        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>

        <?php unset($_SESSION['error']); ?>

    <?php endif; ?>

    <?php if (empty($servicios)): ?>

        <div class="clinic-services-card clinic-service-empty">

            <i class="bi bi-clipboard2-heart fs-1" aria-hidden="true"></i>

            <h2 class="h5 mt-3">Aún no hay servicios registrados</h2>

            <p class="mb-4">
                Crea el primer servicio del catálogo general de tu consultorio.
            </p>

            <a
                href="<?= Helper::baseUrl('consultorio/servicios/nuevo'); ?>"
                class="btn btn-clinic-primary"
            >
                Registrar servicio
            </a>

        </div>

    <?php else: ?>

        <div class="clinic-services-card">

            <div class="clinic-services-toolbar">

                <div class="clinic-services-search">

                    <label for="buscarServicios" class="visually-hidden">
                        Buscar servicio
                    </label>

                    <input
                        type="search"
                        id="buscarServicios"
                        class="form-control"
                        placeholder="Buscar por nombre o descripción…"
                        autocomplete="off"
                    >

                </div>

            </div>

            <div class="table-responsive">

                <table
                    class="table clinic-services-table align-middle"
                    id="tablaServiciosConsultorio"
                >

                    <thead>

                        <tr>
                            <th>Servicio</th>
                            <th>Duración sugerida</th>
                            <th>Precio sugerido</th>
                            <th>Estatus</th>
                            <th>Especialistas</th>
                            <th class="text-end">Acciones</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($servicios as $servicio): ?>

                            <?php
                            $activo =
                                ($servicio['EstatusServicio'] ?? '') === 'ACTIVO';
                            $textoBusqueda = strtolower(
                                ($servicio['NombreServicio'] ?? '') . ' ' .
                                ($servicio['Descripcion'] ?? '')
                            );
                            ?>

                            <tr data-busqueda="<?= htmlspecialchars($textoBusqueda, ENT_QUOTES, 'UTF-8'); ?>">

                                <td>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $servicio['NombreServicio'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    </strong>

                                    <div
                                        class="clinic-service-desc text-muted small"
                                        title="<?= htmlspecialchars(
                                            $servicio['Descripcion'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>"
                                    >
                                        <?= htmlspecialchars(
                                            resumirDescripcion(
                                                (string) ($servicio['Descripcion'] ?? '')
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    </div>

                                </td>

                                <td>
                                    <span class="clinic-service-duration">
                                        <?= (int) ($servicio['DuracionMinutos'] ?? 0); ?> min
                                    </span>
                                </td>

                                <td>
                                    <span class="clinic-service-price">
                                        $<?= number_format(
                                            (float) ($servicio['CostoServicio'] ?? 0),
                                            2
                                        ); ?>
                                    </span>
                                </td>

                                <td>

                                    <?php if ($activo): ?>

                                        <span class="clinic-service-status clinic-service-status--active">
                                            Activo
                                        </span>

                                    <?php else: ?>

                                        <span class="clinic-service-status clinic-service-status--inactive">
                                            Inactivo
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?= (int) ($servicio['TotalPsicologos'] ?? 0); ?>
                                </td>

                                <td>

                                    <div class="clinic-service-actions">

                                        <a
                                            href="<?= Helper::baseUrl(
                                                'consultorio/servicios/ver'
                                            ); ?>?id=<?= urlencode(
                                                $servicio['ClvServ'] ?? ''
                                            ); ?>"
                                            class="btn btn-sm btn-clinic-secondary"
                                            title="Ver"
                                        >
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                            <span class="visually-hidden">Ver</span>
                                        </a>

                                        <a
                                            href="<?= Helper::baseUrl(
                                                'consultorio/servicios/editar'
                                            ); ?>?id=<?= urlencode(
                                                $servicio['ClvServ'] ?? ''
                                            ); ?>"
                                            class="btn btn-sm btn-clinic-secondary"
                                            title="Editar"
                                        >
                                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                            <span class="visually-hidden">Editar</span>
                                        </a>

                                        <form
                                            method="POST"
                                            action="<?= Helper::baseUrl(
                                                'consultorio/servicios/cambiar-estatus'
                                            ); ?>"
                                            class="d-inline"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="clvServ"
                                                value="<?= htmlspecialchars(
                                                    $servicio['ClvServ'] ?? '',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="accion"
                                                value="<?= $activo ? 'inactivar' : 'activar'; ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-clinic-secondary"
                                                title="<?= $activo ? 'Desactivar' : 'Activar'; ?>"
                                            >
                                                <?php if ($activo): ?>

                                                    <i class="bi bi-toggle2-off" aria-hidden="true"></i>

                                                <?php else: ?>

                                                    <i class="bi bi-toggle2-on" aria-hidden="true"></i>

                                                <?php endif; ?>

                                                <span class="visually-hidden">
                                                    <?= $activo ? 'Desactivar' : 'Activar'; ?>
                                                </span>
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    <?php endif; ?>

</section>

<script>
(function () {
    'use strict';

    var input = document.getElementById('buscarServicios');
    var tabla = document.getElementById('tablaServiciosConsultorio');

    if (!input || !tabla) {
        return;
    }

    input.addEventListener('input', function () {
        var termino = input.value.trim().toLowerCase();

        tabla.querySelectorAll('tbody tr').forEach(function (fila) {
            var texto = fila.getAttribute('data-busqueda') || '';
            fila.classList.toggle(
                'd-none',
                termino !== '' && !texto.includes(termino)
            );
        });
    });
})();
</script>
