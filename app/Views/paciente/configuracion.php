<?php

use App\Helpers\Helper;

$escapar = static function ($valor): string {
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
};

$usuario = is_array($usuario ?? null) ? $usuario : [];
$solicitudCorreo = is_array($solicitudCorreo ?? null) ? $solicitudCorreo : null;
$csrf = (string) ($csrf ?? '');
$toastTipo = (string) ($toastTipo ?? '');
$toastMensaje = (string) ($toastMensaje ?? '');

$correoActual = trim((string) ($usuario['CorreoUsu'] ?? ''));
$telefonoActual = trim((string) ($usuario['TelefonoUsu'] ?? ''));

?>

<?php
    $segundosReenvio = (int) ($solicitudCorreo['segundos_para_reenviar'] ?? 0);
    $puedeReenviar = !empty($solicitudCorreo['puede_reenviar']);
    $envioEnProceso = !empty($solicitudCorreo['envio_en_proceso']);
    $reenvioPermitidoVisual = ((int) ($solicitudCorreo['reenvios_restantes'] ?? 0)) > 0;
?>

<section
    class="paciente-config-page"
    data-cambio-correo-root
    data-toast-tipo="<?= $escapar($toastTipo); ?>"
    data-toast-mensaje="<?= $escapar($toastMensaje); ?>"
>

    <header class="paciente-page-header">
        <div class="paciente-page-header-icon" aria-hidden="true">
            <i class="bi bi-gear"></i>
        </div>
        <div class="paciente-page-header-copy">
            <h1>Configuración</h1>
            <p>
                Administra el correo de acceso, el teléfono y la contraseña
                de tu cuenta.
            </p>
        </div>
    </header>

    <div class="paciente-config-grid">

        <article class="paciente-config-card">
            <div class="paciente-config-card-head">
                <span aria-hidden="true"><i class="bi bi-envelope"></i></span>
                <div>
                    <h2>Correo electrónico</h2>
                    <p>El correo se utiliza para iniciar sesión.</p>
                </div>
            </div>

            <p class="paciente-config-current">
                Correo actual:
                <strong><?= $escapar($correoActual); ?></strong>
            </p>

            <?php if ($solicitudCorreo): ?>

                <div class="paciente-config-verify">
                    <?php if ($envioEnProceso): ?>
                        <p class="paciente-config-hint" role="status">
                            El código se está enviando. No recargues ni pulses
                            de nuevo.
                        </p>
                    <?php else: ?>
                        <p>
                            Enviamos un código a
                            <strong>
                                <?= $escapar(
                                    $solicitudCorreo['correo_enmascarado'] ?? ''
                                ); ?>
                            </strong>
                        </p>
                    <?php endif; ?>

                    <p class="paciente-config-hint">
                        Podrás reenviar en
                        <span data-reenvio-timer>
                            <?= $escapar($segundosReenvio); ?>
                        </span>
                        segundos.
                    </p>

                    <form
                        method="POST"
                        action="<?= $escapar(
                            Helper::baseUrl(
                                'paciente/configuracion/verificar-cambio-correo'
                            )
                        ); ?>"
                        class="paciente-config-form"
                        data-cambio-correo-accion="verificar"
                    >
                        <input type="hidden" name="csrf_token" value="<?= $escapar($csrf); ?>">
                        <label for="codigo">Código de verificación</label>
                        <input
                            type="text"
                            id="codigo"
                            name="codigo"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            required
                            <?= $envioEnProceso ? 'disabled' : ''; ?>
                        >
                        <button
                            type="submit"
                            class="paciente-btn paciente-btn-primary"
                            <?= $envioEnProceso ? 'disabled' : ''; ?>
                        >
                            Verificar y actualizar
                        </button>
                    </form>

                    <div class="paciente-config-inline-actions">
                        <form
                            method="POST"
                            action="<?= $escapar(
                                Helper::baseUrl(
                                    'paciente/configuracion/reenviar-codigo-correo'
                                )
                            ); ?>"
                            data-cambio-correo-accion="reenviar"
                        >
                            <input type="hidden" name="csrf_token" value="<?= $escapar($csrf); ?>">
                            <button
                                type="submit"
                                class="paciente-btn paciente-btn-secondary"
                                data-reenvio-cooldown="<?= $escapar($segundosReenvio); ?>"
                                data-reenvio-permitido="<?= $reenvioPermitidoVisual ? '1' : '0'; ?>"
                                <?= (!$puedeReenviar || $envioEnProceso) ? 'disabled' : ''; ?>
                            >
                                Reenviar código
                            </button>
                        </form>

                        <form
                            method="POST"
                            action="<?= $escapar(
                                Helper::baseUrl(
                                    'paciente/configuracion/cancelar-cambio-correo'
                                )
                            ); ?>"
                            data-cambio-correo-accion="cancelar"
                        >
                            <input type="hidden" name="csrf_token" value="<?= $escapar($csrf); ?>">
                            <button
                                type="submit"
                                class="paciente-btn paciente-btn-secondary"
                                <?= $envioEnProceso ? 'disabled' : ''; ?>
                            >
                                Cancelar cambio
                            </button>
                        </form>
                    </div>
                </div>

            <?php else: ?>

                <form
                    method="POST"
                    action="<?= $escapar(
                        Helper::baseUrl(
                            'paciente/configuracion/solicitar-cambio-correo'
                        )
                    ); ?>"
                    class="paciente-config-form"
                    autocomplete="off"
                    data-cambio-correo-accion="solicitar"
                >
                    <input type="hidden" name="csrf_token" value="<?= $escapar($csrf); ?>">

                    <label for="correo_nuevo">Nuevo correo</label>
                    <input
                        type="email"
                        id="correo_nuevo"
                        name="correo_nuevo"
                        maxlength="100"
                        required
                        autocomplete="email"
                    >

                    <label for="contrasena_correo">Contraseña actual</label>
                    <input
                        type="password"
                        id="contrasena_correo"
                        name="contrasena_actual"
                        required
                        autocomplete="current-password"
                    >

                    <button type="submit" class="paciente-btn paciente-btn-primary">
                        Enviar código
                    </button>
                </form>

            <?php endif; ?>
        </article>

        <article class="paciente-config-card">
            <div class="paciente-config-card-head">
                <span aria-hidden="true"><i class="bi bi-telephone"></i></span>
                <div>
                    <h2>Número telefónico</h2>
                    <p>Actualiza tu teléfono de contacto.</p>
                </div>
            </div>

            <p class="paciente-config-current">
                Teléfono actual:
                <strong>
                    <?= $escapar(
                        $telefonoActual !== '' ? $telefonoActual : 'No registrado'
                    ); ?>
                </strong>
            </p>

            <form
                method="POST"
                action="<?= $escapar(
                    Helper::baseUrl(
                        'paciente/configuracion/actualizar-telefono'
                    )
                ); ?>"
                class="paciente-config-form"
                autocomplete="off"
            >
                <input type="hidden" name="csrf_token" value="<?= $escapar($csrf); ?>">

                <label for="telefono">Nuevo teléfono</label>
                <input
                    type="text"
                    id="telefono"
                    name="telefono"
                    inputmode="numeric"
                    maxlength="14"
                    required
                    value="<?= $escapar($telefonoActual); ?>"
                >
                <small>Exactamente 10 dígitos.</small>

                <label for="contrasena_telefono">Contraseña actual</label>
                <input
                    type="password"
                    id="contrasena_telefono"
                    name="contrasena_actual"
                    required
                    autocomplete="current-password"
                >

                <button type="submit" class="paciente-btn paciente-btn-primary">
                    Guardar teléfono
                </button>
            </form>
        </article>

        <article class="paciente-config-card paciente-config-card--full">
            <div class="paciente-config-card-head">
                <span aria-hidden="true"><i class="bi bi-shield-lock"></i></span>
                <div>
                    <h2>Contraseña</h2>
                    <p>Cambia tu contraseña de acceso de forma segura.</p>
                </div>
            </div>

            <form
                method="POST"
                action="<?= $escapar(
                    Helper::baseUrl(
                        'paciente/configuracion/cambiar-contrasena'
                    )
                ); ?>"
                class="paciente-config-form paciente-config-form--password"
                autocomplete="off"
            >
                <input type="hidden" name="csrf_token" value="<?= $escapar($csrf); ?>">

                <div class="paciente-config-form-grid">
                    <div>
                        <label for="contrasena_actual">Contraseña actual</label>
                        <input
                            type="password"
                            id="contrasena_actual"
                            name="contrasena_actual"
                            required
                            autocomplete="current-password"
                        >
                    </div>
                    <div>
                        <label for="nueva_contrasena">Nueva contraseña</label>
                        <input
                            type="password"
                            id="nueva_contrasena"
                            name="nueva_contrasena"
                            required
                            minlength="8"
                            autocomplete="new-password"
                        >
                    </div>
                    <div>
                        <label for="confirmar_contrasena">Confirmar nueva contraseña</label>
                        <input
                            type="password"
                            id="confirmar_contrasena"
                            name="confirmar_contrasena"
                            required
                            minlength="8"
                            autocomplete="new-password"
                        >
                    </div>
                </div>

                <ul class="paciente-config-requirements">
                    <li>Mínimo 8 caracteres</li>
                    <li>Al menos una letra</li>
                    <li>Al menos un número</li>
                    <li>Debe ser distinta a la actual</li>
                </ul>

                <button type="submit" class="paciente-btn paciente-btn-primary">
                    Guardar contraseña
                </button>
            </form>
        </article>

    </div>

    <div class="mt-4">
        <?php require __DIR__ . '/partials/privacidad-datos.php'; ?>
    </div>

    <div class="mt-4">
        <?php require __DIR__ . '/../partials/apariencia-controles.php'; ?>
    </div>

    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div
            id="pacienteConfigToast"
            class="toast"
            role="status"
            aria-live="polite"
            aria-atomic="true"
        >
            <div class="toast-header">
                <strong class="me-auto" id="pacienteConfigToastTitle">Aviso</strong>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="toast"
                    aria-label="Cerrar"
                ></button>
            </div>
            <div class="toast-body" id="pacienteConfigToastBody"></div>
        </div>
    </div>

</section>

<script>
(() => {
    const page = document.querySelector('.paciente-config-page');
    if (!page || page.getAttribute('data-toast-inicializado') === '1') return;
    page.setAttribute('data-toast-inicializado', '1');

    const tipo = page.getAttribute('data-toast-tipo') || '';
    const mensaje = page.getAttribute('data-toast-mensaje') || '';
    const toastEl = document.getElementById('pacienteConfigToast');

    if (tipo && mensaje && toastEl && window.bootstrap) {
        document.getElementById('pacienteConfigToastTitle').textContent =
            tipo === 'success' ? 'Listo' : 'Aviso';
        document.getElementById('pacienteConfigToastBody').textContent = mensaje;
        bootstrap.Toast.getOrCreateInstance(toastEl).show();
    }
})();
</script>
