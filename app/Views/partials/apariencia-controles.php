<?php

$tituloApariencia = $tituloApariencia ?? 'Accesibilidad y visualización';

?>

<section class="pm-apariencia-panel" aria-labelledby="aparienciaTitulo">
    <h3 id="aparienciaTitulo"><?= htmlspecialchars((string) $tituloApariencia); ?></h3>

    <p class="small text-muted mb-3">
        Estas preferencias se guardan en este navegador y no se sincronizan
        entre dispositivos.
    </p>

    <div class="pm-apariencia-preview" aria-live="polite">
        Así se verá el texto del sistema.
    </div>

    <div class="pm-apariencia-controls">
        <div class="pm-apariencia-field">
            <label for="aparienciaEscala">Tamaño del texto</label>
            <select id="aparienciaEscala" data-apariencia-scale>
                <option value="normal">Predeterminado</option>
                <option value="large">Grande</option>
                <option value="xlarge">Muy grande</option>
            </select>
        </div>

        <div class="pm-apariencia-field">
            <label for="aparienciaNegrita">Texto reforzado</label>
            <select id="aparienciaNegrita" data-apariencia-bold>
                <option value="false">Desactivado</option>
                <option value="true">Activado</option>
            </select>
        </div>

        <div>
            <button
                type="button"
                class="btn btn-outline-secondary btn-sm"
                data-apariencia-reset
            >
                Restablecer apariencia
            </button>
        </div>
    </div>
</section>
