<?php

use App\Core\Session;
use App\Helpers\Helper;

$csrf = Session::csrfToken();
$redesSociales = is_array($redesSociales ?? null) ? $redesSociales : [];
$plataformasRed = is_array($plataformasRed ?? null) ? $plataformasRed : [];

$iconos = [
    'Facebook' => 'bi-facebook',
    'Instagram' => 'bi-instagram',
    'WhatsApp' => 'bi-whatsapp',
    'TikTok' => 'bi-tiktok',
    'YouTube' => 'bi-youtube',
    'LinkedIn' => 'bi-linkedin',
    'Página Web' => 'bi-globe'
];

?>

<div
    class="settings-card social-settings-section"
    id="redes-sociales"
>
    <div class="social-settings-section__intro">
        <div class="settings-card__header mb-0">
            <i class="bi bi-share" aria-hidden="true"></i>
            <span>Redes sociales</span>
        </div>

        <p class="social-settings-section__desc">
            Administra los enlaces públicos del consultorio.
            Solo se publican los que estén activos.
        </p>

        <div class="social-settings-section__toolbar">
            <button
                type="button"
                class="btn btn-settings-primary social-btn-primary"
                data-bs-toggle="collapse"
                data-bs-target="#formNuevaRedSocial"
                aria-expanded="false"
                aria-controls="formNuevaRedSocial"
            >
                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                Agregar red social
            </button>
        </div>
    </div>

    <div class="collapse" id="formNuevaRedSocial">
        <div class="social-form-card" aria-labelledby="tituloNuevaRedSocial">
            <h3 class="social-form-card__title" id="tituloNuevaRedSocial">
                Nueva red social
            </h3>

            <form
                method="POST"
                action="<?= Helper::baseUrl('consultorio/configuracion/redes/guardar'); ?>"
                class="social-network-form"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
                >

                <div class="row g-4">
                    <div class="col-12 col-md-5">
                        <label class="form-label mb-2" for="tipoRedCons">
                            Plataforma
                        </label>
                        <select
                            name="tipoRed"
                            id="tipoRedCons"
                            class="form-select"
                            required
                        >
                            <?php foreach ($plataformasRed as $plat): ?>
                                <option
                                    value="<?= htmlspecialchars($plat, ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    <?= htmlspecialchars($plat, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text mt-1">
                            Selecciona la red social que deseas mostrar.
                        </div>
                    </div>

                    <div class="col-12 col-md-7">
                        <label class="form-label mb-2" for="urlRedCons">
                            URL del perfil
                        </label>
                        <input
                            type="url"
                            name="urlRed"
                            id="urlRedCons"
                            class="form-control"
                            required
                            maxlength="255"
                            placeholder="https://"
                            autocomplete="url"
                        >
                        <div class="form-text mt-1">
                            Ingresa el enlace completo, por ejemplo:
                            https://www.facebook.com/tuconsultorio
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-md-4">
                        <label class="form-label mb-2" for="etiquetaRedCons">
                            Etiqueta
                        </label>
                        <input
                            type="text"
                            name="etiquetaRed"
                            id="etiquetaRedCons"
                            class="form-control"
                            maxlength="60"
                        >
                        <div class="form-text mt-1">
                            Texto opcional visible junto a la plataforma.
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-md-3">
                        <label class="form-label mb-2" for="ordenRedCons">
                            Orden de aparición
                        </label>
                        <input
                            type="number"
                            name="ordenRed"
                            id="ordenRedCons"
                            class="form-control"
                            min="1"
                            max="9999"
                            value="1"
                            required
                        >
                        <div class="form-text mt-1">
                            Los números menores aparecen primero.
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-md-3">
                        <label class="form-label mb-2" for="estadoRedCons">
                            Estado
                        </label>
                        <select
                            name="estadoRed"
                            id="estadoRedCons"
                            class="form-select"
                        >
                            <option value="ACTIVA">ACTIVA</option>
                            <option value="INACTIVA">INACTIVA</option>
                        </select>
                        <div class="form-text mt-1">
                            Define si la red se mostrará en la página pública.
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="social-network-actions social-network-actions--form">
                            <button
                                type="submit"
                                class="btn btn-settings-primary social-btn-primary"
                            >
                                Guardar
                            </button>
                            <button
                                type="button"
                                class="btn btn-settings-secondary social-btn-neutral"
                                data-bs-toggle="collapse"
                                data-bs-target="#formNuevaRedSocial"
                                aria-expanded="true"
                                aria-controls="formNuevaRedSocial"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($redesSociales === []): ?>
        <div class="social-empty-state" role="status">
            <div class="social-empty-state__icon" aria-hidden="true">
                <i class="bi bi-share"></i>
            </div>
            <h3 class="social-empty-state__title">
                Aún no has agregado redes sociales.
            </h3>
            <p class="social-empty-state__desc">
                Agrega los perfiles que deseas mostrar en la página pública
                del consultorio.
            </p>
            <button
                type="button"
                class="btn btn-settings-primary social-btn-primary"
                data-bs-toggle="collapse"
                data-bs-target="#formNuevaRedSocial"
                aria-expanded="false"
                aria-controls="formNuevaRedSocial"
            >
                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                Agregar red social
            </button>
        </div>
    <?php else: ?>
        <ul class="social-network-list list-unstyled mb-0">
            <?php foreach ($redesSociales as $red): ?>
                <?php
                $activa = ($red['EstadoRed'] ?? '') === 'ACTIVA';
                $clvRed = (string) ($red['ClvRed'] ?? '');
                $tipoRed = (string) ($red['TipoRed'] ?? '');
                $icono = $iconos[$tipoRed] ?? 'bi-globe';
                $editId = 'editRed' . $clvRed;
                $estadoClase = $activa
                    ? 'social-status-badge--activa'
                    : 'social-status-badge--inactiva';
                ?>
                <li class="social-network-item">
                    <div class="social-network-header">
                        <div class="social-network-identity">
                            <span
                                class="social-network-icon"
                                aria-hidden="true"
                            >
                                <i class="bi <?= htmlspecialchars($icono, ENT_QUOTES, 'UTF-8'); ?>"></i>
                            </span>
                            <div class="social-network-meta">
                                <div class="social-network-title-row">
                                    <h3 class="social-network-name">
                                        <?= htmlspecialchars($tipoRed, ENT_QUOTES, 'UTF-8'); ?>
                                    </h3>
                                    <span class="social-status-badge <?= $estadoClase; ?>">
                                        <?= $activa ? 'Activa' : 'Inactiva'; ?>
                                    </span>
                                </div>
                                <?php if (!empty($red['EtiquetaRed'])): ?>
                                    <p class="social-network-label">
                                        <?= htmlspecialchars((string) $red['EtiquetaRed'], ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                <?php endif; ?>
                                <p class="social-url">
                                    <?= htmlspecialchars((string) ($red['URLRed'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                                <p class="social-network-order">
                                    Orden: <?= (int) ($red['OrdenRed'] ?? 1); ?>
                                </p>
                            </div>
                        </div>

                        <div class="social-network-actions">
                            <button
                                type="button"
                                class="btn btn-sm btn-settings-secondary social-btn-edit"
                                data-bs-toggle="collapse"
                                data-bs-target="#<?= htmlspecialchars($editId, ENT_QUOTES, 'UTF-8'); ?>"
                                aria-expanded="false"
                                aria-controls="<?= htmlspecialchars($editId, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                Editar
                            </button>
                            <form
                                method="POST"
                                action="<?= Helper::baseUrl('consultorio/configuracion/redes/estado'); ?>"
                                class="social-network-state-form"
                            >
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                <input
                                    type="hidden"
                                    name="clvRed"
                                    value="<?= htmlspecialchars($clvRed, ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                <input
                                    type="hidden"
                                    name="accion"
                                    value="<?= $activa ? 'inactivar' : 'activar'; ?>"
                                >
                                <button
                                    type="submit"
                                    class="btn btn-sm <?= $activa ? 'social-btn-warn' : 'btn-settings-primary social-btn-primary'; ?>"
                                >
                                    <?= $activa ? 'Desactivar' : 'Activar'; ?>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div
                        class="collapse social-network-edit"
                        id="<?= htmlspecialchars($editId, ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <div class="social-form-card social-form-card--edit">
                            <h3 class="social-form-card__title">
                                Editar red social
                            </h3>

                            <form
                                method="POST"
                                action="<?= Helper::baseUrl('consultorio/configuracion/redes/actualizar'); ?>"
                                class="social-network-form"
                            >
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                <input
                                    type="hidden"
                                    name="clvRed"
                                    value="<?= htmlspecialchars($clvRed, ENT_QUOTES, 'UTF-8'); ?>"
                                >

                                <div class="row g-4">
                                    <div class="col-12 col-md-5">
                                        <label
                                            class="form-label mb-2"
                                            for="tipoRedEdit<?= htmlspecialchars($clvRed, ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            Plataforma
                                        </label>
                                        <select
                                            name="tipoRed"
                                            id="tipoRedEdit<?= htmlspecialchars($clvRed, ENT_QUOTES, 'UTF-8'); ?>"
                                            class="form-select"
                                            required
                                        >
                                            <?php foreach ($plataformasRed as $plat): ?>
                                                <option
                                                    value="<?= htmlspecialchars($plat, ENT_QUOTES, 'UTF-8'); ?>"
                                                    <?= $tipoRed === $plat ? 'selected' : ''; ?>
                                                >
                                                    <?= htmlspecialchars($plat, ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text mt-1">
                                            Selecciona la red social que deseas mostrar.
                                        </div>
                                    </div>

                                    <div class="col-12 col-md-7">
                                        <label
                                            class="form-label mb-2"
                                            for="urlRedEdit<?= htmlspecialchars($clvRed, ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            URL del perfil
                                        </label>
                                        <input
                                            type="url"
                                            name="urlRed"
                                            id="urlRedEdit<?= htmlspecialchars($clvRed, ENT_QUOTES, 'UTF-8'); ?>"
                                            class="form-control"
                                            required
                                            maxlength="255"
                                            value="<?= htmlspecialchars((string) ($red['URLRed'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                        <div class="form-text mt-1">
                                            Ingresa el enlace completo del perfil.
                                        </div>
                                    </div>

                                    <div class="col-12 col-sm-6 col-md-4">
                                        <label
                                            class="form-label mb-2"
                                            for="etiquetaRedEdit<?= htmlspecialchars($clvRed, ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            Etiqueta
                                        </label>
                                        <input
                                            type="text"
                                            name="etiquetaRed"
                                            id="etiquetaRedEdit<?= htmlspecialchars($clvRed, ENT_QUOTES, 'UTF-8'); ?>"
                                            class="form-control"
                                            maxlength="60"
                                            value="<?= htmlspecialchars((string) ($red['EtiquetaRed'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                    </div>

                                    <div class="col-12 col-sm-6 col-md-3">
                                        <label
                                            class="form-label mb-2"
                                            for="ordenRedEdit<?= htmlspecialchars($clvRed, ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            Orden de aparición
                                        </label>
                                        <input
                                            type="number"
                                            name="ordenRed"
                                            id="ordenRedEdit<?= htmlspecialchars($clvRed, ENT_QUOTES, 'UTF-8'); ?>"
                                            class="form-control"
                                            min="1"
                                            max="9999"
                                            required
                                            value="<?= (int) ($red['OrdenRed'] ?? 1); ?>"
                                        >
                                        <div class="form-text mt-1">
                                            Los números menores aparecen primero.
                                        </div>
                                    </div>

                                    <div class="col-12 col-sm-6 col-md-3">
                                        <label
                                            class="form-label mb-2"
                                            for="estadoRedEdit<?= htmlspecialchars($clvRed, ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            Estado
                                        </label>
                                        <select
                                            name="estadoRed"
                                            id="estadoRedEdit<?= htmlspecialchars($clvRed, ENT_QUOTES, 'UTF-8'); ?>"
                                            class="form-select"
                                        >
                                            <option value="ACTIVA" <?= $activa ? 'selected' : ''; ?>>
                                                ACTIVA
                                            </option>
                                            <option value="INACTIVA" <?= !$activa ? 'selected' : ''; ?>>
                                                INACTIVA
                                            </option>
                                        </select>
                                        <div class="form-text mt-1">
                                            Define si la red se mostrará en la página pública.
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="social-network-actions social-network-actions--form">
                                            <button
                                                type="submit"
                                                class="btn btn-settings-primary social-btn-primary"
                                            >
                                                Guardar cambios
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-settings-secondary social-btn-neutral"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#<?= htmlspecialchars($editId, ENT_QUOTES, 'UTF-8'); ?>"
                                                aria-expanded="true"
                                                aria-controls="<?= htmlspecialchars($editId, ENT_QUOTES, 'UTF-8'); ?>"
                                            >
                                                Cancelar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
