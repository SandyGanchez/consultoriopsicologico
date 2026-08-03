<?php

use App\Core\Session;
use App\Helpers\Helper;

$version = (string) ($version ?? '1.0');
$error = $error ?? null;
$estadoGate = (string) ($estadoGate ?? 'requiere_aceptacion');
$mensajeGate = (string) ($mensajeGate ?? '');
$puedeAceptar = !empty($puedeAceptar);

?>

<div class="container py-4 py-md-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">

            <h1 class="h4 mb-3">Privacidad y consentimiento</h1>

            <?php if ($estadoGate === 'en_configuracion' || $estadoGate === 'error_temporal'): ?>
                <div class="alert alert-warning" role="alert">
                    <?= htmlspecialchars(
                        $mensajeGate !== ''
                            ? $mensajeGate
                            : 'El aviso de privacidad se encuentra en proceso de configuración.',
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </div>
                <p>
                    Puedes consultar el aviso cuando esté disponible o cerrar sesión.
                    Mientras tanto no es posible continuar al panel.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a
                        class="btn btn-outline-secondary"
                        href="<?= Helper::baseUrl('aviso-de-privacidad'); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Abrir aviso integral
                    </a>
                    <a class="btn btn-link" href="<?= Helper::baseUrl('logout'); ?>">
                        Cerrar sesión
                    </a>
                </div>
            <?php else: ?>

                <p>
                    Para continuar usando el panel de paciente debes aceptar la
                    versión vigente del Aviso de Privacidad y otorgar tu
                    consentimiento expreso para el tratamiento de datos personales
                    sensibles.
                </p>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2">
                        <?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <?php if ($puedeAceptar): ?>
                    <form
                        method="POST"
                        action="<?= Helper::baseUrl('privacidad/consentimiento'); ?>"
                        id="formConsentimientoPaciente"
                    >
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(Session::csrfToken(), ENT_QUOTES, 'UTF-8'); ?>"
                        >

                        <?php
                        $versionAviso = $version;
                        $idPrefijo = 'gate';
                        require __DIR__ . '/../partials/aviso-simplificado.php';
                        ?>

                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <button type="submit" class="btn btn-primary">
                                Guardar consentimiento y continuar
                            </button>
                            <a
                                class="btn btn-outline-secondary"
                                href="<?= Helper::baseUrl('aviso-de-privacidad'); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Abrir aviso integral
                            </a>
                            <a class="btn btn-link" href="<?= Helper::baseUrl('logout'); ?>">
                                Cerrar sesión
                            </a>
                        </div>
                    </form>

                    <script>
                    (function () {
                        var form = document.getElementById('formConsentimientoPaciente');
                        if (!form) return;
                        form.addEventListener('submit', function (e) {
                            var a = form.querySelector('[name="aviso_leido"]');
                            var s = form.querySelector('[name="consentimiento_sensibles"]');
                            if (!a || !a.checked || !s || !s.checked) {
                                e.preventDefault();
                                alert('Debes marcar ambas confirmaciones para continuar.');
                            }
                        });
                    })();
                    </script>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    </div>
</div>
