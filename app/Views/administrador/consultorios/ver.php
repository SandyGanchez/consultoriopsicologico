<?php
use App\Helpers\Helper;

$consultorio = $consultorio ?? null;

if (!$consultorio) {
    echo '
        <div class="container py-4">
            <div class="alert alert-danger">
                No se encontró el consultorio.
            </div>
        </div>
    ';

    return;
}

$clvCons = $consultorio['ClvCons'] ?? '';

$nombreResponsable = trim(
    implode(' ', array_filter([
        $consultorio['NombrePer'] ?? '',
        $consultorio['ApPatPer'] ?? '',
        $consultorio['ApMatPer'] ?? ''
    ]))
);

$direccion = trim(
    implode(' ', array_filter([
        $consultorio['CalleDir'] ?? '',
        $consultorio['NumExtDir'] ?? ''
    ]))
);

if (!empty($consultorio['NumIntDir'])) {
    $direccion .= ' Int. '
        . $consultorio['NumIntDir'];
}

$estatusConsultorio = strtoupper(
    $consultorio['EstatusCons'] ?? 'INACTIVO'
);

$activo = $estatusConsultorio === 'ACTIVO';

$estadoUsuario = $consultorio['EstadoUsu'] ?? null;

$cuentaActiva =
    $estadoUsuario === 1
    || $estadoUsuario === '1'
    || $estadoUsuario === 'ACTIVO';

$ubicacion = implode(', ', array_filter([
    $consultorio['MunicipioDir'] ?? '',
    $consultorio['EstadoDir'] ?? '',
    $consultorio['PaisDir'] ?? ''
]));

?>

<div class="container py-4">

    <div class="mb-4">

       <a
    href="<?= Helper::baseUrl(
        'administrador/consultorios'
    ); ?>"
    class="text-decoration-none"
>
    ← Regresar a consultorios
</a>

    </div>

    <div
        class="d-flex flex-column flex-md-row
               justify-content-between
               align-items-md-center
               gap-3 mb-4"
    >

        <div>

            <h1 class="h3 mb-1">
                <?= htmlspecialchars(
                    $consultorio['NombreCons']
                        ?? 'Consultorio',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </h1>

            <span
                class="badge <?= $activo
                    ? 'bg-success'
                    : 'bg-secondary'
                ?>"
            >
                <?= htmlspecialchars(
                    $estatusConsultorio,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </span>

        </div>

        <?php if ($clvCons !== ''): ?>

            <a
    href="<?= Helper::baseUrl(
        'administrador/consultorios/editar/'
        . rawurlencode($clvCons)
    ); ?>"
    class="btn btn-primary"
>
    Editar consultorio
</a>

        <?php endif; ?>

    </div>

    <div class="row g-4">

        <div class="col-lg-7">

            <div class="card shadow-sm border-0 mb-4">

                <div class="card-header bg-white py-3">

                    <h2 class="h5 mb-0">
                        Información del consultorio
                    </h2>

                </div>

                <div class="card-body">

                    <dl class="row mb-0">

                        <dt class="col-sm-4">
                            Clave
                        </dt>

                        <dd class="col-sm-8">

                            <?= htmlspecialchars(
                                $clvCons !== ''
                                    ? $clvCons
                                    : 'Sin clave',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </dd>

                        <dt class="col-sm-4">
                            Nombre
                        </dt>

                        <dd class="col-sm-8">

                            <?= htmlspecialchars(
                                $consultorio['NombreCons']
                                    ?? 'Sin nombre',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </dd>

                        <dt class="col-sm-4">
    Eslogan
</dt>

<dd class="col-sm-8">
    <?= htmlspecialchars(
        !empty($consultorio['Slogan'])
            ? $consultorio['Slogan']
            : 'Sin eslogan',
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</dd>
                       <dt class="col-sm-4">
    Correo
</dt>

<dd class="col-sm-8">
    <?= htmlspecialchars(
        $consultorio['CorreoElectronico']
            ?? 'Sin correo',
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</dd>

                        <dt class="col-sm-4">
                            Teléfono
                        </dt>

                        <dd class="col-sm-8">

                            <?= htmlspecialchars(
                                $consultorio['TelefonoCons']
                                    ?? 'Sin teléfono',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </dd>

                        <dt class="col-sm-4">
                            Cancelación
                        </dt>

                        <dd class="col-sm-8">

                            <?php if (
                                isset(
                                    $consultorio[
                                        'LimiteCancHoras'
                                    ]
                                )
                                && $consultorio[
                                    'LimiteCancHoras'
                                ] !== null
                            ): ?>

                                <?= (int) $consultorio[
                                    'LimiteCancHoras'
                                ] ?>
                                horas antes

                            <?php else: ?>

                                Sin configuración

                            <?php endif; ?>

                        </dd>

                        <dt class="col-sm-4">
    Descripción
</dt>

<dd class="col-sm-8">
    <?= nl2br(
        htmlspecialchars(
            !empty($consultorio['Descripcion'])
                ? $consultorio['Descripcion']
                : 'Sin descripción',
            ENT_QUOTES,
            'UTF-8'
        )
    ) ?>
</dd>

                    </dl>

                </div>

            </div>

            <div class="card shadow-sm border-0">

                <div class="card-header bg-white py-3">

                    <h2 class="h5 mb-0">
                        Dirección
                    </h2>

                </div>

                <div class="card-body">

                    <p class="mb-1">

                        <?= htmlspecialchars(
                            $direccion !== ''
                                ? $direccion
                                : 'Sin calle registrada',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </p>

                    <p class="mb-1">

                        <?php

                        $colonia =
                            $consultorio['ColoniaDir']
                            ?? '';

                        $codigoPostal =
                            $consultorio['CodPostDir']
                            ?? '';

                        ?>

                        <?= htmlspecialchars(
                            $colonia !== ''
                                ? $colonia
                                : 'Sin colonia',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                        <?php if (
                            $codigoPostal !== ''
                        ): ?>

                            , C.P.

                            <?= htmlspecialchars(
                                $codigoPostal,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        <?php endif; ?>

                    </p>

                    <p class="mb-0">

                        <?= htmlspecialchars(
                            $ubicacion !== ''
                                ? $ubicacion
                                : 'Sin ubicación registrada',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </p>

                </div>

            </div>

        </div>

        <div class="col-lg-5">

            <div class="card shadow-sm border-0 mb-4">

                <div class="card-header bg-white py-3">

                    <h2 class="h5 mb-0">
                        Responsable
                    </h2>

                </div>

                <div class="card-body">

                    <dl class="mb-0">

                        <dt>Nombre</dt>

                        <dd>

                            <?= htmlspecialchars(
                                $nombreResponsable !== ''
                                    ? $nombreResponsable
                                    : 'Sin responsable',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </dd>

                        <dt>Correo de acceso</dt>

                        <dd>

                            <?= htmlspecialchars(
                                $consultorio['CorreoUsu']
                                    ?? 'Sin cuenta',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </dd>

                        <dt>Teléfono</dt>

                        <dd>

                            <?= htmlspecialchars(
                                $consultorio['TelefonoUsu']
                                    ?? 'Sin teléfono',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </dd>

                        <dt>Estado de la cuenta</dt>

                        <dd>

                            <?php if (
                                $estadoUsuario === null
                            ): ?>

                                <span class="badge bg-secondary">
                                    Sin cuenta
                                </span>

                            <?php else: ?>

                                <span
                                    class="badge <?= $cuentaActiva
                                        ? 'bg-success'
                                        : 'bg-secondary'
                                    ?>"
                                >
                                    <?= $cuentaActiva
                                        ? 'ACTIVA'
                                        : 'INACTIVA'
                                    ?>
                                </span>

                            <?php endif; ?>

                        </dd>

                        <dt>Cambio de contraseña pendiente</dt>

                        <dd>

                            <?php

                            $requiereCambio =
                                (int) (
                                    $consultorio[
                                        'RequiereCambioContrasena'
                                    ]
                                    ?? 0
                                ) === 1;

                            ?>

                            <span
                                class="badge <?= $requiereCambio
                                    ? 'bg-warning text-dark'
                                    : 'bg-success'
                                ?>"
                            >
                                <?= $requiereCambio
                                    ? 'PENDIENTE'
                                    : 'COMPLETADO'
                                ?>
                            </span>

                        </dd>

                    </dl>

                </div>

            </div>

            <div class="card shadow-sm border-0">

                <div class="card-header bg-white py-3">

                    <h2 class="h5 mb-0">
                        Acciones administrativas
                    </h2>

                </div>

                <div class="card-body">

                    <div class="d-grid gap-2">

                        <?php if (
                            !empty(
                                $consultorio['ClvUsu']
                            )
                        ): ?>

                            <form
                                method="POST"
                                action="<?= Helper::baseUrl(
                                    'administrador/'
                                    . 'consultorios/'
                                    . 'restablecer-acceso/'
                                    . rawurlencode($clvCons)
                                ) ?>"
                                onsubmit="
                                    return confirm(
                                        '¿Deseas restablecer la contraseña del responsable?'
                                    );
                                "
                            >

                                <button
                                    type="submit"
                                    class="btn btn-outline-secondary w-100"
                                >
                                    Restablecer contraseña
                                </button>

                            </form>

                        <?php else: ?>

                            <button
                                type="button"
                                class="btn btn-outline-secondary w-100"
                                disabled
                            >
                                No existe cuenta responsable
                            </button>

                        <?php endif; ?>

                        <?php if ($activo): ?>

                            <form
                                method="POST"
                                action="<?= Helper::baseUrl(
                                    'administrador/'
                                    . 'consultorios/'
                                    . 'desactivar/'
                                    . rawurlencode($clvCons)
                                ) ?>"
                                onsubmit="
                                    return confirm(
                                        '¿Deseas desactivar este consultorio?'
                                    );
                                "
                            >

                                <button
                                    type="submit"
                                    class="btn btn-outline-warning w-100"
                                >
                                    Desactivar consultorio
                                </button>

                            </form>

                        <?php else: ?>

                            <form
                                method="POST"
                                action="<?= Helper::baseUrl(
                                    'administrador/'
                                    . 'consultorios/'
                                    . 'activar/'
                                    . rawurlencode($clvCons)
                                ) ?>"
                                onsubmit="
                                    return confirm(
                                        '¿Deseas activar este consultorio?'
                                    );
                                "
                            >

                                <button
                                    type="submit"
                                    class="btn btn-outline-success w-100"
                                >
                                    Activar consultorio
                                </button>

                            </form>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>