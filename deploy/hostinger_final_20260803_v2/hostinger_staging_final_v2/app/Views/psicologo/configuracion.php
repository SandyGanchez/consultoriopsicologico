<?php

use App\Core\Session;
use App\Helpers\Helper;

$csrf = Session::csrfToken();

$toastTipo = $toastTipo ?? '';
$toastMensaje = $toastMensaje ?? '';
$solicitudCorreo = is_array($solicitudCorreo ?? null) ? $solicitudCorreo : null;
$correoActual = trim((string) ($usuario['CorreoUsu'] ?? ''));
$telefonoActual = trim((string) ($usuario['TelefonoUsu'] ?? ''));
$segundosReenvio = (int) ($solicitudCorreo['segundos_para_reenviar'] ?? 0);
$puedeReenviar = !empty($solicitudCorreo['puede_reenviar']);
$envioEnProceso = !empty($solicitudCorreo['envio_en_proceso']);
$reenvioPermitidoVisual = ((int) ($solicitudCorreo['reenvios_restantes'] ?? 0)) > 0;

?>

<section
    class="psicologo-config-page"
    data-cambio-correo-root
    data-toast-tipo="<?= htmlspecialchars(
        (string) $toastTipo,
        ENT_QUOTES,
        'UTF-8'
    ); ?>"
    data-toast-mensaje="<?= htmlspecialchars(
        (string) $toastMensaje,
        ENT_QUOTES,
        'UTF-8'
    ); ?>"
>

    <header class="psicologo-config-header">
        <span class="psicologo-config-header__eyebrow">
            Cuenta
        </span>
        <h1>Configuración</h1>
        <p>
            Administra correo, teléfono, contraseña, preferencias
            de accesibilidad y accesos rápidos. Los datos personales
            se editan en Mi perfil.
        </p>
    </header>

    <div class="psicologo-config-grid">

        <article class="psicologo-config-card">
            <div class="psicologo-config-card__head">
                <i class="bi bi-envelope"></i>
                <div>
                    <h2>Correo electrónico</h2>
                    <p>
                        Cambia tu correo de acceso con verificación
                        por código.
                    </p>
                </div>
            </div>

            <p class="psicologo-config-current">
                Correo actual:
                <strong>
                    <?= htmlspecialchars($correoActual, ENT_QUOTES, 'UTF-8'); ?>
                </strong>
            </p>

            <?php if ($solicitudCorreo): ?>
                <div class="psicologo-config-verify">
                    <?php if ($envioEnProceso): ?>
                        <p class="psicologo-config-hint" role="status">
                            El código se está enviando. No recargues ni pulses
                            de nuevo.
                        </p>
                    <?php else: ?>
                        <p>
                            Enviamos un código a
                            <strong>
                                <?= htmlspecialchars(
                                    (string) ($solicitudCorreo['correo_enmascarado'] ?? ''),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </strong>
                        </p>
                    <?php endif; ?>

                    <p class="psicologo-config-hint">
                        Podrás reenviar en
                        <span data-reenvio-timer>
                            <?= htmlspecialchars((string) $segundosReenvio, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        segundos.
                    </p>

                    <form
                        method="POST"
                        action="<?= Helper::baseUrl(
                            'psicologo/configuracion/verificar-cambio-correo'
                        ); ?>"
                        class="psicologo-config-form"
                        data-cambio-correo-accion="verificar"
                    >
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
                        >
                        <div class="psicologo-config-field">
                            <label for="codigo_correo">Código de verificación</label>
                            <input
                                type="text"
                                id="codigo_correo"
                                name="codigo"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                maxlength="6"
                                pattern="[0-9]{6}"
                                required
                                <?= $envioEnProceso ? 'disabled' : ''; ?>
                            >
                        </div>
                        <div class="psicologo-config-actions">
                            <button
                                type="submit"
                                class="psicologo-config-btn-primary"
                                <?= $envioEnProceso ? 'disabled' : ''; ?>
                            >
                                Verificar y actualizar
                            </button>
                        </div>
                    </form>

                    <div class="psicologo-config-inline-actions">
                        <form
                            method="POST"
                            action="<?= Helper::baseUrl(
                                'psicologo/configuracion/reenviar-codigo-correo'
                            ); ?>"
                            data-cambio-correo-accion="reenviar"
                        >
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                            <button
                                type="submit"
                                class="psicologo-config-btn-secondary"
                                data-reenvio-cooldown="<?= htmlspecialchars((string) $segundosReenvio, ENT_QUOTES, 'UTF-8'); ?>"
                                data-reenvio-permitido="<?= $reenvioPermitidoVisual ? '1' : '0'; ?>"
                                <?= (!$puedeReenviar || $envioEnProceso) ? 'disabled' : ''; ?>
                            >
                                Reenviar código
                            </button>
                        </form>
                        <form
                            method="POST"
                            action="<?= Helper::baseUrl(
                                'psicologo/configuracion/cancelar-cambio-correo'
                            ); ?>"
                            data-cambio-correo-accion="cancelar"
                        >
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                            <button
                                type="submit"
                                class="psicologo-config-btn-secondary"
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
                    action="<?= Helper::baseUrl(
                        'psicologo/configuracion/solicitar-cambio-correo'
                    ); ?>"
                    class="psicologo-config-form"
                    autocomplete="off"
                    data-cambio-correo-accion="solicitar"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
                    >
                    <div class="psicologo-config-field">
                        <label for="correo_nuevo">Nuevo correo</label>
                        <input
                            type="email"
                            id="correo_nuevo"
                            name="correo_nuevo"
                            maxlength="100"
                            required
                            autocomplete="email"
                        >
                    </div>
                    <div class="psicologo-config-field">
                        <label for="contrasena_correo">Contraseña actual</label>
                        <input
                            type="password"
                            id="contrasena_correo"
                            name="contrasena_actual"
                            required
                            autocomplete="current-password"
                        >
                    </div>
                    <div class="psicologo-config-actions">
                        <button type="submit" class="psicologo-config-btn-primary">
                            Enviar código
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </article>

        <article class="psicologo-config-card">
            <div class="psicologo-config-card__head">
                <i class="bi bi-telephone"></i>
                <div>
                    <h2>Número telefónico</h2>
                    <p>Actualiza tu teléfono con confirmación de contraseña.</p>
                </div>
            </div>

            <p class="psicologo-config-current">
                Teléfono actual:
                <strong>
                    <?= htmlspecialchars(
                        $telefonoActual !== '' ? $telefonoActual : 'No registrado',
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </strong>
            </p>

            <form
                method="POST"
                action="<?= Helper::baseUrl(
                    'psicologo/configuracion/actualizar-telefono'
                ); ?>"
                class="psicologo-config-form"
                autocomplete="off"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
                >
                <div class="psicologo-config-field">
                    <label for="telefono_config">Nuevo teléfono</label>
                    <input
                        type="text"
                        id="telefono_config"
                        name="telefono"
                        inputmode="numeric"
                        maxlength="14"
                        required
                        value="<?= htmlspecialchars($telefonoActual, ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>
                <div class="psicologo-config-field">
                    <label for="contrasena_telefono">Contraseña actual</label>
                    <input
                        type="password"
                        id="contrasena_telefono"
                        name="contrasena_actual"
                        required
                        autocomplete="current-password"
                    >
                </div>
                <div class="psicologo-config-actions">
                    <button type="submit" class="psicologo-config-btn-primary">
                        Guardar teléfono
                    </button>
                </div>
            </form>
        </article>

        <!-- A. Seguridad -->
        <article class="psicologo-config-card">
            <div class="psicologo-config-card__head">
                <i class="bi bi-shield-lock"></i>
                <div>
                    <h2>Seguridad de la cuenta</h2>
                    <p>
                        Actualiza tu contraseña. No compartas
                        tus credenciales con nadie.
                    </p>
                </div>
            </div>

            <form
                method="POST"
                action="<?= Helper::baseUrl(
                    'psicologo/configuracion/cambiar-contrasena'
                ); ?>"
                class="psicologo-config-form"
                autocomplete="off"
                id="formCambioContrasena"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $csrf,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                >

                <div class="psicologo-config-field">
                    <label for="contrasena_actual">
                        Contraseña actual
                    </label>
                    <div class="psicologo-config-input-group">
                        <input
                            type="password"
                            id="contrasena_actual"
                            name="contrasena_actual"
                            required
                            autocomplete="current-password"
                        >
                        <button
                            type="button"
                            class="psicologo-config-toggle-pass"
                            data-target="contrasena_actual"
                            aria-label="Mostrar contraseña actual"
                        >
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="psicologo-config-field">
                    <label for="nueva_contrasena">
                        Nueva contraseña
                    </label>
                    <div class="psicologo-config-input-group">
                        <input
                            type="password"
                            id="nueva_contrasena"
                            name="nueva_contrasena"
                            required
                            minlength="8"
                            autocomplete="new-password"
                        >
                        <button
                            type="button"
                            class="psicologo-config-toggle-pass"
                            data-target="nueva_contrasena"
                            aria-label="Mostrar nueva contraseña"
                        >
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="psicologo-config-field">
                    <label for="confirmar_contrasena">
                        Confirmar nueva contraseña
                    </label>
                    <div class="psicologo-config-input-group">
                        <input
                            type="password"
                            id="confirmar_contrasena"
                            name="confirmar_contrasena"
                            required
                            minlength="8"
                            autocomplete="new-password"
                        >
                        <button
                            type="button"
                            class="psicologo-config-toggle-pass"
                            data-target="confirmar_contrasena"
                            aria-label="Mostrar confirmación de contraseña"
                        >
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <ul class="psicologo-config-requirements">
                    <li>Mínimo 8 caracteres</li>
                    <li>Al menos una letra</li>
                    <li>Al menos un número</li>
                    <li>Debe ser diferente a la contraseña actual</li>
                </ul>

                <p class="psicologo-config-hint">
                    Al guardar, se cerrará la validez de la
                    sesión anterior y se liberará el aviso de
                    cambio temporal de contraseña, si aplica.
                </p>

                <div class="psicologo-config-actions">
                    <button
                        type="submit"
                        class="psicologo-config-btn-primary"
                    >
                        Guardar contraseña
                    </button>
                </div>
            </form>
        </article>

        <!-- B. Accesibilidad y visualización (partial compartido, una sola vez) -->
        <article class="psicologo-config-card">
            <?php require __DIR__ . '/../partials/apariencia-controles.php'; ?>
        </article>

        <!-- C. Accesos rápidos -->
        <article class="psicologo-config-card psicologo-config-card--full">
            <div class="psicologo-config-card__head">
                <i class="bi bi-lightning-charge"></i>
                <div>
                    <h2>Accesos rápidos</h2>
                    <p>
                        Atajos a los módulos que ya tienes
                        disponibles.
                    </p>
                </div>
            </div>

            <div class="psicologo-config-shortcuts">
                <a
                    href="<?= Helper::baseUrl('psicologo/perfil'); ?>"
                    class="psicologo-config-shortcut"
                >
                    <i class="bi bi-person-badge"></i>
                    <span>Mi perfil</span>
                </a>
                <a
                    href="<?= Helper::baseUrl(
                        'psicologo/disponibilidad'
                    ); ?>"
                    class="psicologo-config-shortcut"
                >
                    <i class="bi bi-clock-history"></i>
                    <span>Mi disponibilidad</span>
                </a>
                <a
                    href="<?= Helper::baseUrl(
                        'psicologo/servicios'
                    ); ?>"
                    class="psicologo-config-shortcut"
                >
                    <i class="bi bi-journal-medical"></i>
                    <span>Mis servicios</span>
                </a>
                <a
                    href="<?= Helper::baseUrl('psicologo/agenda'); ?>"
                    class="psicologo-config-shortcut"
                >
                    <i class="bi bi-calendar-week"></i>
                    <span>Mi agenda</span>
                </a>
            </div>
        </article>

    </div>

    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div
            id="psicologoConfigToast"
            class="toast psicologo-config-toast"
            role="status"
            aria-live="polite"
            aria-atomic="true"
        >
            <div class="toast-header">
                <strong
                    class="me-auto"
                    id="psicologoConfigToastTitle"
                >
                    Aviso
                </strong>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="toast"
                    aria-label="Cerrar"
                ></button>
            </div>
            <div
                class="toast-body"
                id="psicologoConfigToastBody"
            ></div>
        </div>
    </div>

</section>
