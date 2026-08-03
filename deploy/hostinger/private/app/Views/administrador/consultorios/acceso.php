<?php

use App\Helpers\Helper;

$resultado = $resultado ?? [];

$correo = $resultado['correo'] ?? '';
$correoEnviado = !empty($resultado['correoEnviado']);
$operacion = $resultado['operacion'] ?? 'restablecer';
$esRegistro = $operacion === 'registro';

$titulo = $esRegistro
    ? 'Consultorio registrado'
    : 'Acceso restablecido';

$mensaje = trim((string) ($resultado['mensaje'] ?? ''));

if ($mensaje === '') {
    $mensaje = $esRegistro
        ? 'El consultorio y la cuenta de su responsable se registraron correctamente.'
        : 'Se envió un enlace de activación al responsable del consultorio.';
}

?>

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-12 col-md-9 col-lg-6">

            <div class="card shadow-sm border-0">

                <div class="card-body p-4 p-md-5">

                    <h1 class="h4 mb-3">
                        <?= htmlspecialchars(
                            $titulo,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </h1>

                    <p class="text-muted">
                        <?= htmlspecialchars(
                            $mensaje,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>

                    <div
                        class="alert alert-info"
                        role="alert"
                    >
                        El responsable debe activar su cuenta mediante el
                        enlace enviado por correo (válido 24 horas, un solo uso).
                        No se envían ni muestran contraseñas.
                    </div>

                    <?php if ($correo === ''): ?>

                        <div
                            class="alert alert-danger"
                            role="alert"
                        >
                            No fue posible identificar el correo del responsable.
                        </div>

                    <?php else: ?>

                        <div class="mb-3">

                            <label
                                for="correoAcceso"
                                class="form-label"
                            >
                                Correo de acceso
                            </label>

                            <input
                                type="text"
                                id="correoAcceso"
                                class="form-control"
                                readonly
                                value="<?= htmlspecialchars(
                                    $correo,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                        </div>

                        <?php if (!$correoEnviado): ?>

                            <div
                                class="alert alert-warning"
                                role="alert"
                            >
                                La cuenta quedó pendiente de activación, pero el
                                correo no pudo enviarse. Usa «Restablecer acceso»
                                para reintentar el envío del enlace.
                            </div>

                        <?php endif; ?>

                    <?php endif; ?>

                    <a
                        href="<?= Helper::baseUrl(
                            'administrador/consultorios'
                        ); ?>"
                        class="btn btn-primary"
                    >
                        Volver al listado
                    </a>

                </div>

            </div>

        </div>

    </div>

</div>
