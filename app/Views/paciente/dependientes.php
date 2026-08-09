<?php

use App\Core\Session;
use App\Helpers\Helper;

$esc = static function (mixed $v): string {
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
};

$dependientes = is_array($dependientes ?? null) ? $dependientes : [];
$limitesEdad = is_array($limitesEdad ?? null) ? $limitesEdad : ['min' => '1900-01-01', 'max' => date('Y-m-d')];
$error = Session::get('error');
$success = Session::get('success');
Session::remove('error');
Session::remove('success');
?>

<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Dependientes</h1>
            <p class="text-muted small mb-0">
                Personas a tu cargo sin cuenta propia. No incluye acceso a su expediente clínico.
            </p>
        </div>
        <button
            class="btn btn-primary btn-sm"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#formAltaDependiente"
        >
            Agregar dependiente
        </button>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger py-2"><?= $esc($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success py-2"><?= $esc($success); ?></div>
    <?php endif; ?>

    <div class="collapse mb-4" id="formAltaDependiente">
        <div class="border rounded p-3 bg-white">
            <h2 class="h6 mb-3">Nuevo dependiente</h2>
            <form method="POST" action="<?= Helper::baseUrl('paciente/dependientes/crear'); ?>">
                <input type="hidden" name="csrf_token" value="<?= $esc(Session::csrfToken()); ?>">
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label small">Nombre</label>
                        <input type="text" name="nombre" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Apellido paterno</label>
                        <input type="text" name="apPat" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Apellido materno</label>
                        <input type="text" name="apMat" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Fecha de nacimiento</label>
                        <input
                            type="date"
                            name="fechaNacimiento"
                            class="form-control form-control-sm"
                            min="<?= $esc($limitesEdad['min'] ?? ''); ?>"
                            max="<?= $esc($limitesEdad['max'] ?? ''); ?>"
                            required
                        >
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Género</label>
                        <select name="genero" class="form-select form-select-sm" required>
                            <option value="">Selecciona</option>
                            <option value="Masculino">Masculino</option>
                            <option value="Femenino">Femenino</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Parentesco</label>
                        <input type="text" name="parentesco" class="form-control form-control-sm" placeholder="Ej. Hijo/a, Tutor" required>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="EsTutorLegal" value="1" id="altaTutor">
                            <label class="form-check-label small" for="altaTutor">
                                Declaro ser tutor legal del dependiente (obligatorio si es menor de 18).
                                Esta es una declaración del usuario; el sistema no verifica la tutela jurídicamente.
                            </label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="aviso_leido" value="1" id="altaAviso" required>
                            <label class="form-check-label small" for="altaAviso">
                                He leído el Aviso de Privacidad aplicable a los datos del dependiente.
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="consentimiento_sensibles" value="1" id="altaSens" required>
                            <label class="form-check-label small" for="altaSens">
                                Consiento el tratamiento de datos personales (incluidos sensibles) del dependiente.
                            </label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-sm">Guardar dependiente</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($dependientes === []): ?>
        <div class="alert alert-light border">Aún no tienes dependientes registrados.</div>
    <?php else: ?>
        <div class="table-responsive bg-white border rounded">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Parentesco</th>
                        <th>Nacimiento</th>
                        <th>Edad</th>
                        <th>Tutor (decl.)</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($dependientes as $dep): ?>
                    <?php
                    $id = (int) ($dep['IdRelacion'] ?? 0);
                    $estado = (string) ($dep['EstadoRelacion'] ?? '');
                    $collapseId = 'editDep' . $id;
                    ?>
                    <tr>
                        <td><?= $esc($dep['NombreCompleto'] ?? ''); ?></td>
                        <td><?= $esc($dep['Parentesco'] ?? ''); ?></td>
                        <td><?= $esc($dep['FechaNacimiento'] ?? ''); ?></td>
                        <td><?= $esc($dep['Edad'] ?? '—'); ?></td>
                        <td><?= !empty($dep['EsTutorLegal']) ? 'Sí' : 'No'; ?></td>
                        <td>
                            <span class="badge text-bg-<?= $estado === 'ACTIVA' ? 'success' : 'secondary'; ?>">
                                <?= $esc($estado); ?>
                            </span>
                        </td>
                        <td class="text-nowrap">
                            <button
                                class="btn btn-outline-secondary btn-sm"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#<?= $esc($collapseId); ?>"
                            >Editar</button>
                            <form
                                method="POST"
                                action="<?= Helper::baseUrl('paciente/dependientes/cambiar-estado'); ?>"
                                class="d-inline"
                            >
                                <input type="hidden" name="csrf_token" value="<?= $esc(Session::csrfToken()); ?>">
                                <input type="hidden" name="idRelacion" value="<?= $id; ?>">
                                <input
                                    type="hidden"
                                    name="estado"
                                    value="<?= $estado === 'ACTIVA' ? 'INACTIVA' : 'ACTIVA'; ?>"
                                >
                                <button type="submit" class="btn btn-outline-warning btn-sm">
                                    <?= $estado === 'ACTIVA' ? 'Inactivar' : 'Reactivar'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <tr class="collapse" id="<?= $esc($collapseId); ?>">
                        <td colspan="7">
                            <form
                                method="POST"
                                action="<?= Helper::baseUrl('paciente/dependientes/editar'); ?>"
                                class="p-2 border rounded bg-light"
                            >
                                <input type="hidden" name="csrf_token" value="<?= $esc(Session::csrfToken()); ?>">
                                <input type="hidden" name="idRelacion" value="<?= $id; ?>">
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <label class="form-label small">Nombre</label>
                                        <input type="text" name="nombre" class="form-control form-control-sm"
                                               value="<?= $esc($dep['NombrePer'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">Apellido paterno</label>
                                        <input type="text" name="apPat" class="form-control form-control-sm"
                                               value="<?= $esc($dep['ApPatPer'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">Apellido materno</label>
                                        <input type="text" name="apMat" class="form-control form-control-sm"
                                               value="<?= $esc($dep['ApMatPer'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">Parentesco</label>
                                        <input type="text" name="parentesco" class="form-control form-control-sm"
                                               value="<?= $esc($dep['Parentesco'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">Fecha nacimiento</label>
                                        <input type="date" name="fechaNacimiento" class="form-control form-control-sm"
                                               value="<?= $esc($dep['FechaNacimiento'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">Género</label>
                                        <?php $g = (string) ($dep['GeneroPer'] ?? ''); ?>
                                        <select name="genero" class="form-select form-select-sm" required>
                                            <option value="Masculino" <?= $g === 'Masculino' ? 'selected' : ''; ?>>Masculino</option>
                                            <option value="Femenino" <?= $g === 'Femenino' ? 'selected' : ''; ?>>Femenino</option>
                                            <option value="Otro" <?= $g === 'Otro' ? 'selected' : ''; ?>>Otro</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 d-flex align-items-end">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="EsTutorLegal" value="1"
                                                   id="tutor<?= $id; ?>"
                                                <?= !empty($dep['EsTutorLegal']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label small" for="tutor<?= $id; ?>">
                                                Declaro tutoría legal
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary btn-sm">Guardar cambios</button>
                                    </div>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
