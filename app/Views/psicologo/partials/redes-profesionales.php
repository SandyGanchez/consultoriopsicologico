<?php

use App\Core\Session;
use App\Helpers\Helper;

$csrf = Session::csrfToken();
$redesProfesionales = is_array($redesProfesionales ?? null) ? $redesProfesionales : [];
$plataformasRed = is_array($plataformasRed ?? null) ? $plataformasRed : [];

?>

<div class="psicologo-panel mt-4" id="redes-profesionales">
    <h2 class="h5 mb-2">Redes profesionales</h2>
    <p class="text-muted small">
        Enlaces que se muestran en tu perfil público cuando estás activo y visible en la página.
    </p>

    <?php if ($redesProfesionales === []): ?>
        <p class="text-muted">Aún no has registrado redes profesionales.</p>
    <?php else: ?>
        <div class="table-responsive mb-3">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Plataforma</th>
                        <th>URL</th>
                        <th>Orden</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($redesProfesionales as $red): ?>
                        <?php
                        $activa = ($red['EstadoRed'] ?? '') === 'ACTIVA';
                        $id = (int) ($red['IdRedSocialPsi'] ?? 0);
                        ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars((string) ($red['TipoRed'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                <?php if (!empty($red['EtiquetaRed'])): ?>
                                    <div class="small text-muted"><?= htmlspecialchars((string) $red['EtiquetaRed'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="small text-break"><?= htmlspecialchars((string) ($red['URLRed'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= (int) ($red['OrdenRed'] ?? 1); ?></td>
                            <td><?= $activa ? 'ACTIVA' : 'INACTIVA'; ?></td>
                            <td class="text-end">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#editRedPsi<?= $id; ?>"
                                >
                                    Editar
                                </button>
                                <form
                                    method="POST"
                                    action="<?= Helper::baseUrl('psicologo/perfil/redes/estado'); ?>"
                                    class="d-inline"
                                >
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="idRedSocialPsi" value="<?= $id; ?>">
                                    <input type="hidden" name="accion" value="<?= $activa ? 'inactivar' : 'activar'; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                        <?= $activa ? 'Inactivar' : 'Activar'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <tr class="collapse" id="editRedPsi<?= $id; ?>">
                            <td colspan="5">
                                <form
                                    method="POST"
                                    action="<?= Helper::baseUrl('psicologo/perfil/redes/actualizar'); ?>"
                                    class="row g-2"
                                >
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="idRedSocialPsi" value="<?= $id; ?>">
                                    <div class="col-md-3">
                                        <label class="form-label">Plataforma</label>
                                        <select name="tipoRed" class="form-select" required>
                                            <?php foreach ($plataformasRed as $plat): ?>
                                                <option value="<?= htmlspecialchars($plat, ENT_QUOTES, 'UTF-8'); ?>"
                                                    <?= ($red['TipoRed'] ?? '') === $plat ? 'selected' : ''; ?>>
                                                    <?= htmlspecialchars($plat, ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">URL</label>
                                        <input type="url" name="urlRed" class="form-control" required maxlength="255"
                                            value="<?= htmlspecialchars((string) ($red['URLRed'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Etiqueta</label>
                                        <input type="text" name="etiquetaRed" class="form-control" maxlength="60"
                                            value="<?= htmlspecialchars((string) ($red['EtiquetaRed'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">Orden</label>
                                        <input type="number" name="ordenRed" class="form-control" min="1" max="9999" required
                                            value="<?= (int) ($red['OrdenRed'] ?? 1); ?>">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">Estado</label>
                                        <select name="estadoRed" class="form-select">
                                            <option value="ACTIVA" <?= $activa ? 'selected' : ''; ?>>ACTIVA</option>
                                            <option value="INACTIVA" <?= !$activa ? 'selected' : ''; ?>>INACTIVA</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-sm btn-primary">Guardar cambios</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <h3 class="h6">Registrar red profesional</h3>
    <form
        method="POST"
        action="<?= Helper::baseUrl('psicologo/perfil/redes/guardar'); ?>"
        class="row g-2"
    >
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="col-md-3">
            <label class="form-label" for="tipoRedPsi">Plataforma</label>
            <select name="tipoRed" id="tipoRedPsi" class="form-select" required>
                <?php foreach ($plataformasRed as $plat): ?>
                    <option value="<?= htmlspecialchars($plat, ENT_QUOTES, 'UTF-8'); ?>">
                        <?= htmlspecialchars($plat, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label" for="urlRedPsi">URL</label>
            <input type="url" name="urlRed" id="urlRedPsi" class="form-control" required maxlength="255" placeholder="https://">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="etiquetaRedPsi">Etiqueta</label>
            <input type="text" name="etiquetaRed" id="etiquetaRedPsi" class="form-control" maxlength="60">
        </div>
        <div class="col-md-1">
            <label class="form-label" for="ordenRedPsi">Orden</label>
            <input type="number" name="ordenRed" id="ordenRedPsi" class="form-control" min="1" max="9999" value="1" required>
        </div>
        <div class="col-md-1">
            <label class="form-label" for="estadoRedPsi">Estado</label>
            <select name="estadoRed" id="estadoRedPsi" class="form-select">
                <option value="ACTIVA">ACTIVA</option>
                <option value="INACTIVA">INACTIVA</option>
            </select>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Registrar red</button>
        </div>
    </form>
</div>
