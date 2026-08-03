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

<div class="settings-card mt-3" id="redes-sociales">
    <div class="settings-card__header">
        <i class="bi bi-share" aria-hidden="true"></i>
        <span>Redes sociales y enlaces</span>
    </div>

    <p class="text-muted small mb-3">
        Enlaces institucionales del consultorio. Solo se publican los que estén activos.
    </p>

    <?php if ($redesSociales === []): ?>
        <p class="text-muted">Aún no hay redes registradas.</p>
    <?php else: ?>
        <div class="table-responsive mb-4">
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
                    <?php foreach ($redesSociales as $red): ?>
                        <?php
                        $activa = ($red['EstadoRed'] ?? '') === 'ACTIVA';
                        $clvRed = (string) ($red['ClvRed'] ?? '');
                        ?>
                        <tr>
                            <td>
                                <i class="bi <?= htmlspecialchars($iconos[$red['TipoRed'] ?? ''] ?? 'bi-globe', ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
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
                                    data-bs-target="#editRed<?= htmlspecialchars($clvRed, ENT_QUOTES, 'UTF-8'); ?>"
                                    aria-expanded="false"
                                >
                                    Editar
                                </button>
                                <form
                                    method="POST"
                                    action="<?= Helper::baseUrl('consultorio/configuracion/redes/estado'); ?>"
                                    class="d-inline"
                                >
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="clvRed" value="<?= htmlspecialchars($clvRed, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="accion" value="<?= $activa ? 'inactivar' : 'activar'; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                        <?= $activa ? 'Inactivar' : 'Activar'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <tr class="collapse" id="editRed<?= htmlspecialchars($clvRed, ENT_QUOTES, 'UTF-8'); ?>">
                            <td colspan="5">
                                <form
                                    method="POST"
                                    action="<?= Helper::baseUrl('consultorio/configuracion/redes/actualizar'); ?>"
                                    class="row g-2"
                                >
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="clvRed" value="<?= htmlspecialchars($clvRed, ENT_QUOTES, 'UTF-8'); ?>">
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

    <h3 class="h6">Registrar red</h3>
    <form
        method="POST"
        action="<?= Helper::baseUrl('consultorio/configuracion/redes/guardar'); ?>"
        class="row g-2"
    >
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="col-md-3">
            <label class="form-label" for="tipoRedCons">Plataforma</label>
            <select name="tipoRed" id="tipoRedCons" class="form-select" required>
                <?php foreach ($plataformasRed as $plat): ?>
                    <option value="<?= htmlspecialchars($plat, ENT_QUOTES, 'UTF-8'); ?>">
                        <?= htmlspecialchars($plat, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label" for="urlRedCons">URL</label>
            <input type="url" name="urlRed" id="urlRedCons" class="form-control" required maxlength="255" placeholder="https://">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="etiquetaRedCons">Etiqueta</label>
            <input type="text" name="etiquetaRed" id="etiquetaRedCons" class="form-control" maxlength="60">
        </div>
        <div class="col-md-1">
            <label class="form-label" for="ordenRedCons">Orden</label>
            <input type="number" name="ordenRed" id="ordenRedCons" class="form-control" min="1" max="9999" value="1" required>
        </div>
        <div class="col-md-1">
            <label class="form-label" for="estadoRedCons">Estado</label>
            <select name="estadoRed" id="estadoRedCons" class="form-select">
                <option value="ACTIVA">ACTIVA</option>
                <option value="INACTIVA">INACTIVA</option>
            </select>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Registrar red</button>
        </div>
    </form>
</div>
