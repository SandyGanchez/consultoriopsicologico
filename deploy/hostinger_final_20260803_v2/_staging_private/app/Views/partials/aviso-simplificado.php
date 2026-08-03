<?php

use App\Helpers\Helper;

$versionAviso = $versionAviso ?? null;
$idPrefijo = $idPrefijo ?? 'priv';

?>

<section class="pm-aviso-simplificado" aria-labelledby="<?= htmlspecialchars($idPrefijo, ENT_QUOTES, 'UTF-8'); ?>AvisoTitulo">
    <h3 id="<?= htmlspecialchars($idPrefijo, ENT_QUOTES, 'UTF-8'); ?>AvisoTitulo">
        Aviso de privacidad y consentimiento
    </h3>

    <p>
        El responsable del tratamiento de tus datos personales es el
        <strong>consultorio</strong> que opera esta instalación.
        PsicoMatch es únicamente el sistema utilizado para gestionar la información.
        Se tratarán datos de identificación y, con tu consentimiento expreso,
        datos personales sensibles relacionados con tu atención psicológica.
        Puedes consultar el aviso completo. Para ejercer derechos sobre tus
        datos personales o solicitar la revocación del consentimiento,
        comunícate directamente con el responsable del consultorio.
    </p>

    <p class="mb-2">
        <a
            href="<?= Helper::baseUrl('aviso-de-privacidad'); ?>"
            target="_blank"
            rel="noopener noreferrer"
        >
            Leer el Aviso de Privacidad Integral
            <?php if ($versionAviso): ?>
                (versión <?= htmlspecialchars((string) $versionAviso, ENT_QUOTES, 'UTF-8'); ?>)
            <?php endif; ?>
        </a>
    </p>

    <div class="form-check mb-2">
        <input
            class="form-check-input"
            type="checkbox"
            name="aviso_leido"
            id="<?= htmlspecialchars($idPrefijo, ENT_QUOTES, 'UTF-8'); ?>AvisoLeido"
            value="1"
            required
        >
        <label
            class="form-check-label"
            for="<?= htmlspecialchars($idPrefijo, ENT_QUOTES, 'UTF-8'); ?>AvisoLeido"
        >
            He leído el Aviso de Privacidad Integral.
        </label>
    </div>

    <div class="form-check mb-3">
        <input
            class="form-check-input"
            type="checkbox"
            name="consentimiento_sensibles"
            id="<?= htmlspecialchars($idPrefijo, ENT_QUOTES, 'UTF-8'); ?>ConsentimientoSensibles"
            value="1"
            required
        >
        <label
            class="form-check-label"
            for="<?= htmlspecialchars($idPrefijo, ENT_QUOTES, 'UTF-8'); ?>ConsentimientoSensibles"
        >
            Otorgo mi consentimiento expreso para el tratamiento de datos
            personales sensibles relacionados con mi atención psicológica.
        </label>
    </div>
</section>
