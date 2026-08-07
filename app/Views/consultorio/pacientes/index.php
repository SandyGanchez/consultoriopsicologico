<?php

use App\Core\Session;
use App\Helpers\Helper;

$csrf = Session::csrfToken();
$listado = is_array($listado ?? null) ? $listado : [];
$items = is_array($listado['items'] ?? null) ? $listado['items'] : [];
$filtros = is_array($listado['filtros'] ?? null) ? $listado['filtros'] : [];
$pagina = (int) ($listado['pagina'] ?? 1);
$totalPaginas = (int) ($listado['totalPaginas'] ?? 1);
$total = (int) ($listado['total'] ?? 0);
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$queryBase = static function (array $extra = []) use ($filtros): string {
    $params = array_merge([
        'q' => $filtros['q'] ?? '',
        'estado' => $filtros['estado'] ?? 'todos',
        'actividad' => $filtros['actividad'] ?? 'todos',
    ], $extra);
    return http_build_query(array_filter(
        $params,
        static fn ($v) => $v !== '' && $v !== null
    ));
};

?>

<section class="consultorio-psicologos">

    <div class="consultorio-page-header d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <span class="consultorio-page-eyebrow">Gestión administrativa</span>
            <h1>Pacientes</h1>
            <p>
                Pacientes relacionados con este consultorio. Solo información administrativa;
                sin acceso a expediente clínico.
            </p>
        </div>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $esc($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($warning)): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <?= $esc($warning); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $esc($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="consultorio-dashboard-panel mb-3">
        <form method="GET" action="<?= Helper::baseUrl('consultorio/pacientes'); ?>" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="filtroQ">Buscar</label>
                <input
                    type="search"
                    class="form-control"
                    id="filtroQ"
                    name="q"
                    value="<?= $esc($filtros['q'] ?? ''); ?>"
                    placeholder="Nombre, correo o ClvPac"
                >
            </div>
            <div class="col-md-3">
                <label class="form-label" for="filtroEstado">Estado</label>
                <select class="form-select" id="filtroEstado" name="estado">
                    <?php
                    $estados = [
                        'todos' => 'Todos',
                        'activo' => 'Activo',
                        'inactivo' => 'Inactivo',
                    ];
                    foreach ($estados as $val => $label):
                    ?>
                        <option value="<?= $esc($val); ?>"
                            <?= ($filtros['estado'] ?? '') === $val ? 'selected' : ''; ?>>
                            <?= $esc($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="filtroActividad">Actividad</label>
                <select class="form-select" id="filtroActividad" name="actividad">
                    <?php
                    $acts = [
                        'todos' => 'Todos',
                        'sin_actividad' => 'Sin actividad',
                        'con_citas' => 'Con citas',
                        'con_expediente' => 'Con expediente',
                    ];
                    foreach ($acts as $val => $label):
                    ?>
                        <option value="<?= $esc($val); ?>"
                            <?= ($filtros['actividad'] ?? '') === $val ? 'selected' : ''; ?>>
                            <?= $esc($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn agenda-filter-button w-100">Filtrar</button>
            </div>
        </form>
    </div>

    <?php if ($items === []): ?>
        <div class="consultorio-dashboard-panel text-center py-5">
            <i class="bi bi-people fs-1"></i>
            <h2 class="h5 mt-3">No hay pacientes en este ámbito</h2>
            <p class="mb-0 text-muted">
                Aparecen pacientes afiliados a este consultorio o con citas/expediente aquí.
            </p>
        </div>
    <?php else: ?>
        <div class="consultorio-dashboard-panel">
            <p class="text-muted small mb-3">
                <?= (int) $total; ?> paciente(s) · Página <?= (int) $pagina; ?> de <?= (int) $totalPaginas; ?>
            </p>
            <div class="table-responsive">
                <table class="table consultorio-table align-middle">
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Correo</th>
                            <th>Teléfono</th>
                            <th>Estado</th>
                            <th>Citas</th>
                            <th>Expediente</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $clvPac = (string) ($item['ClvPac'] ?? '');
                            $puedeEliminar = !empty($item['puedeEliminarFisicamente']);
                            $puedeInactivar = !empty($item['puedeInactivar']);
                            $puedeReactivar = !empty($item['puedeReactivar']);
                            $dataDeps = $esc(json_encode([
                                'totalCitas' => (int) ($item['TotalCitasGlobales'] ?? 0),
                                'citasConsultorio' => (int) ($item['TotalCitasConsultorio'] ?? 0),
                                'tieneExpediente' => !empty($item['TieneExpediente']),
                            ], JSON_UNESCAPED_UNICODE));
                            ?>
                            <tr>
                                <td>
                                    <strong><?= $esc($item['NombreCompleto'] ?? ''); ?></strong>
                                    <small class="d-block text-muted"><?= $esc($clvPac); ?></small>
                                </td>
                                <td><?= $esc($item['CorreoUsu'] ?? ''); ?></td>
                                <td><?= $esc($item['TelefonoUsu'] ?? ''); ?></td>
                                <td>
                                    <span class="badge rounded-pill bg-secondary">
                                        <?= $esc($item['EstadoEtiqueta'] ?? ''); ?>
                                    </span>
                                </td>
                                <td><?= (int) ($item['TotalCitasConsultorio'] ?? 0); ?></td>
                                <td><?= $esc($item['ExpedienteEtiqueta'] ?? 'No'); ?></td>
                                <td class="text-end">
                                    <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                        <a
                                            class="btn btn-sm btn-outline-secondary"
                                            href="<?= Helper::baseUrl('consultorio/pacientes/ver/' . rawurlencode($clvPac)); ?>"
                                        >Ver</a>
                                        <a
                                            class="btn btn-sm btn-outline-secondary"
                                            href="<?= Helper::baseUrl('consultorio/pacientes/editar/' . rawurlencode($clvPac)); ?>"
                                        >Editar</a>

                                        <?php if ($puedeEliminar): ?>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalEliminarPac"
                                                data-clvpac="<?= $esc($clvPac); ?>"
                                                data-nombre="<?= $esc($item['NombreCompleto'] ?? ''); ?>"
                                            >Eliminar registro</button>
                                        <?php else: ?>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalInfoPac"
                                                data-clvpac="<?= $esc($clvPac); ?>"
                                                data-nombre="<?= $esc($item['NombreCompleto'] ?? ''); ?>"
                                                data-deps="<?= $dataDeps; ?>"
                                                data-puedeinactivar="<?= $puedeInactivar ? '1' : '0'; ?>"
                                            >Información relacionada</button>
                                            <?php if ($puedeInactivar): ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-warning"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalInactivarPac"
                                                    data-clvpac="<?= $esc($clvPac); ?>"
                                                    data-nombre="<?= $esc($item['NombreCompleto'] ?? ''); ?>"
                                                >Inactivar</button>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if ($puedeReactivar): ?>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-success"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalReactivarPac"
                                                data-clvpac="<?= $esc($clvPac); ?>"
                                                data-nombre="<?= $esc($item['NombreCompleto'] ?? ''); ?>"
                                            >Reactivar</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPaginas > 1): ?>
                <nav class="mt-3" aria-label="Paginación de pacientes">
                    <ul class="pagination mb-0">
                        <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                            <li class="page-item <?= $p === $pagina ? 'active' : ''; ?>">
                                <a
                                    class="page-link"
                                    href="<?= Helper::baseUrl('consultorio/pacientes?' . $queryBase(['pagina' => $p])); ?>"
                                ><?= $p; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<div class="modal fade" id="modalEliminarPac" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= Helper::baseUrl('consultorio/pacientes/eliminar'); ?>">
                <input type="hidden" name="csrf_token" value="<?= $esc($csrf); ?>">
                <input type="hidden" name="ClvPac" id="eliminarClvPac" value="">
                <div class="modal-header">
                    <h2 class="modal-title fs-5">Eliminar paciente</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p>Este paciente no tiene citas ni expediente clínico registrado.</p>
                    <p>Esta acción eliminará su registro administrativo de forma permanente.</p>
                    <p class="text-danger mb-0">Esta acción no se puede deshacer.</p>
                    <p class="text-muted mt-2 mb-0" id="eliminarNombrePac"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar definitivamente</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalInfoPac" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5">No es posible eliminar este paciente</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p id="infoNombrePac" class="mb-2"></p>
                <p>Este paciente tiene información histórica relacionada que debe conservarse.</p>
                <ul id="infoListaPac" class="mb-3"></ul>
                <p class="mb-0">
                    Por integridad y conservación del historial, esta cuenta no puede eliminarse definitivamente.
                </p>
            </div>
            <div class="modal-footer flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form
                    method="POST"
                    action="<?= Helper::baseUrl('consultorio/pacientes/inactivar'); ?>"
                    class="d-none"
                    id="infoFormInactivar"
                >
                    <input type="hidden" name="csrf_token" value="<?= $esc($csrf); ?>">
                    <input type="hidden" name="ClvPac" id="infoInactivarClvPac" value="">
                    <button type="submit" class="btn btn-warning">Inactivar paciente</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalInactivarPac" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= Helper::baseUrl('consultorio/pacientes/inactivar'); ?>">
                <input type="hidden" name="csrf_token" value="<?= $esc($csrf); ?>">
                <input type="hidden" name="ClvPac" id="inactivarClvPac" value="">
                <div class="modal-header">
                    <h2 class="modal-title fs-5">Inactivar paciente</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p id="inactivarNombrePac" class="mb-2"></p>
                    <p class="mb-0">
                        Se conservará el historial. La cuenta dejará de poder iniciar sesión
                        (solo si es exclusiva de este consultorio).
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Inactivar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalReactivarPac" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= Helper::baseUrl('consultorio/pacientes/reactivar'); ?>">
                <input type="hidden" name="csrf_token" value="<?= $esc($csrf); ?>">
                <input type="hidden" name="ClvPac" id="reactivarClvPac" value="">
                <div class="modal-header">
                    <h2 class="modal-title fs-5">Reactivar paciente</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p id="reactivarNombrePac" class="mb-0"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Reactivar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEliminar = document.getElementById('modalEliminarPac');
    if (modalEliminar) {
        modalEliminar.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            document.getElementById('eliminarClvPac').value = btn.getAttribute('data-clvpac') || '';
            document.getElementById('eliminarNombrePac').textContent = btn.getAttribute('data-nombre') || '';
        });
    }

    var modalInfo = document.getElementById('modalInfoPac');
    if (modalInfo) {
        modalInfo.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            var deps = {};
            try { deps = JSON.parse(btn.getAttribute('data-deps') || '{}'); } catch (e) { deps = {}; }
            document.getElementById('infoNombrePac').textContent = btn.getAttribute('data-nombre') || '';
            var ul = document.getElementById('infoListaPac');
            ul.innerHTML = '';
            var li1 = document.createElement('li');
            li1.textContent = 'Citas registradas: ' + (deps.totalCitas || 0);
            ul.appendChild(li1);
            var li2 = document.createElement('li');
            li2.textContent = 'Expediente registrado: ' + (deps.tieneExpediente ? 'Sí' : 'No');
            ul.appendChild(li2);
            var form = document.getElementById('infoFormInactivar');
            var puede = btn.getAttribute('data-puedeinactivar') === '1';
            form.classList.toggle('d-none', !puede);
            document.getElementById('infoInactivarClvPac').value = btn.getAttribute('data-clvpac') || '';
        });
    }

    var modalInactivar = document.getElementById('modalInactivarPac');
    if (modalInactivar) {
        modalInactivar.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            document.getElementById('inactivarClvPac').value = btn.getAttribute('data-clvpac') || '';
            document.getElementById('inactivarNombrePac').textContent = btn.getAttribute('data-nombre') || '';
        });
    }

    var modalReactivar = document.getElementById('modalReactivarPac');
    if (modalReactivar) {
        modalReactivar.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            document.getElementById('reactivarClvPac').value = btn.getAttribute('data-clvpac') || '';
            document.getElementById('reactivarNombrePac').textContent = btn.getAttribute('data-nombre') || '';
        });
    }
});
</script>
