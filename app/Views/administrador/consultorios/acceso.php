<?php

$resultado = $resultado ?? [];

$correo = $resultado['correo'] ?? '';
$contrasenaTemporal =
    $resultado['contrasenaTemporal'] ?? '';

$operacion = $resultado['operacion'] ?? 'restablecer';

$esRegistro = $operacion === 'registro';

$titulo = $esRegistro
    ? 'Consultorio registrado'
    : 'Acceso restablecido';

$mensaje = $esRegistro
    ? 'El consultorio y la cuenta de su responsable se registraron correctamente.'
    : 'Se generó una nueva contraseña temporal para la cuenta responsable del consultorio.';

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
                        class="alert alert-warning"
                        role="alert"
                    >
                        Esta contraseña debe proporcionarse
                        únicamente al responsable autorizado.
                        Al iniciar sesión, deberá cambiarla.
                    </div>

                    <?php if (
                        $correo === ''
                        || $contrasenaTemporal === ''
                    ): ?>

                        <div
                            class="alert alert-danger"
                            role="alert"
                        >
                            No fue posible mostrar las credenciales
                            temporales. Regresa al listado e intenta
                            restablecer el acceso nuevamente.
                        </div>

                    <?php else: ?>

                        <div class="mb-3">

                            <label
                                for="correoAcceso"
                                class="form-label"
                            >
                                Correo de acceso
                            </label>

                            <div class="input-group">

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

                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    onclick="copiarCampo(
                                        'correoAcceso',
                                        'Correo copiado.'
                                    )"
                                >
                                    Copiar
                                </button>

                            </div>

                        </div>

                        <div class="mb-4">

                            <label
                                for="contrasenaTemporal"
                                class="form-label"
                            >
                                Contraseña temporal
                            </label>

                            <div class="input-group">

                                <input
                                    type="text"
                                    id="contrasenaTemporal"
                                    class="form-control"
                                    readonly
                                    value="<?= htmlspecialchars(
                                        $contrasenaTemporal,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >

                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    onclick="copiarCampo(
                                        'contrasenaTemporal',
                                        'Contraseña temporal copiada.'
                                    )"
                                >
                                    Copiar
                                </button>

                            </div>

                        </div>

                        <div
                            id="mensajeCopiado"
                            class="alert alert-success d-none"
                            role="alert"
                        ></div>

                    <?php endif; ?>

                    <a
                        href="<?= url(
                            'administrador/consultorios'
                        ) ?>"
                        class="btn btn-primary w-100"
                    >
                        Regresar al listado
                    </a>

                </div>

            </div>

        </div>

    </div>

</div>

<script>
async function copiarCampo(idCampo, mensaje) {
    const campo = document.getElementById(idCampo);
    const mensajeCopiado = document.getElementById(
        'mensajeCopiado'
    );

    if (!campo) {
        return;
    }

    try {
        await navigator.clipboard.writeText(
            campo.value
        );
    } catch (error) {
        campo.focus();
        campo.select();
        campo.setSelectionRange(
            0,
            campo.value.length
        );

        document.execCommand('copy');
    }

    if (mensajeCopiado) {
        mensajeCopiado.textContent = mensaje;
        mensajeCopiado.classList.remove('d-none');

        window.setTimeout(() => {
            mensajeCopiado.classList.add('d-none');
        }, 2500);
    }
}
</script>