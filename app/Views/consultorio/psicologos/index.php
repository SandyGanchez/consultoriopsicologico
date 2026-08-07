<?php

use App\Core\Session;
use App\Helpers\Helper;

$csrf = Session::csrfToken();

?>

<section class="consultorio-psicologos">

    <div class="consultorio-page-header d-flex justify-content-between align-items-start gap-3 flex-wrap">

        <div>

            <span class="consultorio-page-eyebrow">
                Gestión de especialistas
            </span>

            <h1>Psicólogos</h1>

            <p>
                Administra los especialistas vinculados al consultorio.
            </p>

        </div>

        <a
            href="<?= Helper::baseUrl(
                'consultorio/psicologos/nuevo'
            ); ?>"
            class="btn agenda-filter-button"
        >
            <i class="bi bi-person-plus-fill"></i>
            Agregar psicólogo
        </a>

    </div>

    <?php if (!empty($_SESSION['success'])): ?>

        <div class="alert alert-success alert-dismissible fade show">

            <?= htmlspecialchars($_SESSION['success']); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>

        </div>

        <?php unset($_SESSION['success']); ?>

    <?php endif; ?>

    <?php if (!empty($_SESSION['warning'])): ?>

        <div class="alert alert-warning alert-dismissible fade show">

            <?= htmlspecialchars($_SESSION['warning']); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>

        </div>

        <?php unset($_SESSION['warning']); ?>

    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>

        <div class="alert alert-danger alert-dismissible fade show">

            <?= htmlspecialchars($_SESSION['error']); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>

        </div>

        <?php unset($_SESSION['error']); ?>

    <?php endif; ?>

    <?php if (empty($psicologos)): ?>

        <div class="consultorio-dashboard-panel text-center py-5">

            <i class="bi bi-person-workspace fs-1"></i>

            <h2 class="h5 mt-3">
                No hay especialistas registrados
            </h2>

            <p class="mb-4">
                Registra al primer psicólogo del consultorio.
            </p>

            <a
                href="<?= Helper::baseUrl(
                    'consultorio/psicologos/nuevo'
                ); ?>"
                class="btn agenda-filter-button"
            >
                Agregar psicólogo
            </a>

        </div>

    <?php else: ?>

        <div class="consultorio-dashboard-panel">

            <div class="table-responsive">

                <table class="table consultorio-table align-middle">

                    <thead>

                        <tr>
                            <th>Especialista</th>
                            <th>Contacto</th>
                            <th>Especialidad</th>
                            <th>Cédula</th>
                            <th>Estado</th>
                            <th>Página pública</th>
                            <th class="text-end">Acciones</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($psicologos as $psicologo): ?>

                            <?php
                            $estadoAct =
                                (string) ($psicologo['EstadoActivacion'] ?? '');
                            $etiquetaAct =
                                (string) ($psicologo['EstadoActivacionEtiqueta'] ?? '');
                            $claseEstado = match ($estadoAct) {
                                'ACTIVO' => 'status-asistida',
                                'PENDIENTE_ACTIVACION' => 'status-programada',
                                'ACTIVACION_VENCIDA' => 'status-inasistencia',
                                default => 'status-cancelada'
                            };

                            $deps = is_array($psicologo['Dependencias'] ?? null)
                                ? $psicologo['Dependencias']
                                : [];
                            $citasFuturas = (int) ($psicologo['CitasFuturasProgramadas'] ?? 0);
                            $tieneHist = !empty($psicologo['TieneActividadHistorica']);
                            $puedeEliminar = !empty($psicologo['PuedeEliminarFisicamente']);
                            $puedeDesactivar = !empty($psicologo['PuedeDesactivar']);
                            $puedeReactivar = !empty($psicologo['PuedeReactivar']);
                            $pendiente = in_array(
                                $estadoAct,
                                ['PENDIENTE_ACTIVACION', 'ACTIVACION_VENCIDA'],
                                true
                            );
                            $urlCitas = Helper::baseUrl(
                                (string) ($psicologo['UrlCitasPendientes'] ?? 'consultorio/agenda')
                            );

                            $dataDeps = htmlspecialchars(json_encode([
                                'totalCitas' => (int) ($deps['totalCitas'] ?? 0),
                                'citasFuturas' => $citasFuturas,
                                'pacientesRelacionados' => (int) ($deps['pacientesRelacionados'] ?? 0),
                                'expedientes' => (int) ($deps['expedientes'] ?? 0),
                                'seguimientos' => (int) ($deps['seguimientos'] ?? 0),
                                'diagnosticos' => (int) ($deps['diagnosticos'] ?? 0),
                                'apreciaciones' => (int) ($deps['apreciaciones'] ?? 0),
                                'puedeDesactivar' => $puedeDesactivar,
                                'urlCitas' => $urlCitas,
                            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                            ?>

                            <tr>

                                <td>

                                    <div class="d-flex align-items-center gap-3">

                                        <div class="consultorio-user-avatar">

                                            <i class="bi bi-person-heart"></i>

                                        </div>

                                        <div>

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $psicologo['NombreCompleto']
                                                ); ?>
                                            </strong>

                                            <small class="d-block text-muted">
                                                Registrado:
                                                <?= htmlspecialchars(
                                                    date(
                                                        'd/m/Y',
                                                        strtotime(
                                                            $psicologo[
                                                                'FechaRegistroPsi'
                                                            ]
                                                        )
                                                    )
                                                ); ?>
                                            </small>

                                        </div>

                                    </div>

                                </td>

                                <td>

                                    <div>
                                        <?= htmlspecialchars(
                                            $psicologo['CorreoUsu']
                                        ); ?>
                                    </div>

                                    <small class="text-muted">
                                        <?= htmlspecialchars(
                                            $psicologo['TelefonoUsu']
                                        ); ?>
                                    </small>

                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $psicologo['EspecialidadPsi']
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $psicologo['CedulaProfesional']
                                    ); ?>
                                </td>

                                <td>

                                    <span class="agenda-status <?= htmlspecialchars($claseEstado); ?>">
                                        <?= htmlspecialchars($etiquetaAct); ?>
                                    </span>

                                    <?php if ($pendiente): ?>
                                        <div class="small text-muted mt-1">
                                            Activación pendiente
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($citasFuturas > 0): ?>
                                        <div class="small text-warning mt-1">
                                            <?= $citasFuturas; ?> cita(s) futura(s)
                                        </div>
                                    <?php elseif ($tieneHist): ?>
                                        <div class="small text-muted mt-1">
                                            Con información histórica
                                        </div>
                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php if (
                                        (int) $psicologo['MostrarEnPagina'] === 1
                                    ): ?>

                                        <span class="badge text-bg-success">
                                            Visible
                                        </span>

                                    <?php else: ?>

                                        <span class="badge text-bg-secondary">
                                            Oculto
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td class="text-end text-nowrap">

                                    <a
                                       href="<?= Helper::baseUrl(
    'consultorio/psicologos/editar'
); ?>?id=<?= urlencode(
    $psicologo['ClvPsi']
); ?>"
                                        class="btn btn-sm btn-outline-secondary"
                                        title="Editar"
                                    >
                                        <i class="bi bi-pencil-square"></i>
                                        <span class="d-none d-xl-inline">Editar</span>
                                    </a>

                                    <?php if (
                                        !empty($psicologo['PuedeReenviarActivacion'])
                                    ): ?>

                                        <form
                                            method="POST"
                                            action="<?= Helper::baseUrl(
                                                'consultorio/psicologos/reenviar-activacion'
                                            ); ?>"
                                            class="d-inline"
                                            onsubmit="this.querySelector('button').disabled=true;"
                                        >
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="clvUsu"
                                                value="<?= htmlspecialchars(
                                                    (string) $psicologo['ClvUsu'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"
                                            >
                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-primary"
                                                title="Reenviar activación"
                                            >
                                                <i class="bi bi-envelope-arrow-up"></i>
                                                <span class="d-none d-xl-inline">Reenviar</span>
                                            </button>
                                        </form>

                                    <?php endif; ?>

                                    <?php if ($pendiente && $puedeEliminar): ?>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            title="Cancelar registro"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEliminarPsi"
                                            data-clvpsi="<?= htmlspecialchars(
                                                (string) $psicologo['ClvPsi'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>"
                                            data-nombre="<?= htmlspecialchars(
                                                (string) $psicologo['NombreCompleto'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>"
                                            data-modo="cancelar"
                                        >
                                            <i class="bi bi-person-x"></i>
                                            <span class="d-none d-xl-inline">Cancelar registro</span>
                                        </button>

                                    <?php elseif ($estadoAct === 'ACTIVO' && $puedeEliminar): ?>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            title="Eliminar registro"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEliminarPsi"
                                            data-clvpsi="<?= htmlspecialchars(
                                                (string) $psicologo['ClvPsi'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>"
                                            data-nombre="<?= htmlspecialchars(
                                                (string) $psicologo['NombreCompleto'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>"
                                            data-modo="eliminar"
                                        >
                                            <i class="bi bi-trash"></i>
                                            <span class="d-none d-xl-inline">Eliminar registro</span>
                                        </button>

                                    <?php elseif ($tieneHist): ?>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            title="Información relacionada"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalInfoRelacionada"
                                            data-clvpsi="<?= htmlspecialchars(
                                                (string) $psicologo['ClvPsi'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>"
                                            data-nombre="<?= htmlspecialchars(
                                                (string) $psicologo['NombreCompleto'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>"
                                            data-deps="<?= $dataDeps; ?>"
                                        >
                                            <i class="bi bi-info-circle"></i>
                                            <span class="d-none d-xl-inline">Información relacionada</span>
                                        </button>

                                        <?php if ($citasFuturas > 0 && $estadoAct === 'ACTIVO'): ?>
                                            <a
                                                href="<?= htmlspecialchars($urlCitas, ENT_QUOTES, 'UTF-8'); ?>"
                                                class="btn btn-sm btn-outline-warning"
                                                title="Ver citas pendientes"
                                            >
                                                <i class="bi bi-calendar2-week"></i>
                                                <span class="d-none d-xl-inline">Ver citas pendientes</span>
                                            </a>
                                        <?php elseif ($puedeDesactivar): ?>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-warning"
                                                title="Desactivar especialista"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalDesactivarPsi"
                                                data-clvpsi="<?= htmlspecialchars(
                                                    (string) $psicologo['ClvPsi'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"
                                                data-nombre="<?= htmlspecialchars(
                                                    (string) $psicologo['NombreCompleto'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"
                                            >
                                                <i class="bi bi-person-dash"></i>
                                                <span class="d-none d-xl-inline">Desactivar</span>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($puedeReactivar): ?>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-success"
                                                title="Reactivar"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalReactivarPsi"
                                                data-clvpsi="<?= htmlspecialchars(
                                                    (string) $psicologo['ClvPsi'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"
                                                data-nombre="<?= htmlspecialchars(
                                                    (string) $psicologo['NombreCompleto'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"
                                            >
                                                <i class="bi bi-person-check"></i>
                                                <span class="d-none d-xl-inline">Reactivar</span>
                                            </button>
                                        <?php endif; ?>

                                    <?php elseif ($puedeReactivar): ?>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-success"
                                            title="Reactivar"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalReactivarPsi"
                                            data-clvpsi="<?= htmlspecialchars(
                                                (string) $psicologo['ClvPsi'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>"
                                            data-nombre="<?= htmlspecialchars(
                                                (string) $psicologo['NombreCompleto'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>"
                                        >
                                            <i class="bi bi-person-check"></i>
                                            <span class="d-none d-xl-inline">Reactivar</span>
                                        </button>
                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    <?php endif; ?>

</section>

<?php if (!empty($psicologos)): ?>

<div class="modal fade" id="modalEliminarPsi" tabindex="-1" aria-labelledby="modalEliminarPsiLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= Helper::baseUrl('consultorio/psicologos/eliminar'); ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="clvPsi" id="eliminarClvPsi" value="">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="modalEliminarPsiLabel">Eliminar especialista</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2" id="eliminarIntro">
                        Este especialista no tiene citas, expedientes ni información clínica relacionada.
                    </p>
                    <p class="mb-2">
                        Se eliminarán los datos administrativos asociados a esta alta que no tengan
                        actividad histórica.
                    </p>
                    <p class="mb-2 text-danger">
                        Esta acción no se puede deshacer.
                    </p>
                    <p class="mb-0 text-muted" id="eliminarNombrePsi"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger" id="eliminarSubmit">Eliminar definitivamente</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalInfoRelacionada" tabindex="-1" aria-labelledby="modalInfoRelacionadaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="modalInfoRelacionadaLabel">No se puede eliminar este especialista</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" id="infoNombrePsi"></p>
                <p class="mb-2">
                    Este especialista tiene información relacionada que debe conservarse:
                </p>
                <ul id="infoListaConteos" class="mb-3"></ul>
                <p class="mb-2">
                    Por integridad del historial, estos registros no pueden eliminarse automáticamente.
                </p>
                <p class="mb-0 text-danger d-none" id="infoAlertaFuturas"></p>
            </div>
            <div class="modal-footer flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <a href="#" class="btn btn-outline-warning d-none" id="infoVerCitas">Ver citas pendientes</a>
                <form
                    method="POST"
                    action="<?= Helper::baseUrl('consultorio/psicologos/desactivar'); ?>"
                    class="d-none"
                    id="infoFormDesactivar"
                >
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="clvPsi" id="infoDesactivarClvPsi" value="">
                    <button type="submit" class="btn btn-warning">Desactivar especialista</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDesactivarPsi" tabindex="-1" aria-labelledby="modalDesactivarPsiLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= Helper::baseUrl('consultorio/psicologos/desactivar'); ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="clvPsi" id="desactivarClvPsi" value="">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="modalDesactivarPsiLabel">Desactivar especialista</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        El registro se conservará por historial, pero dejará de estar activo.
                    </p>
                    <p class="mb-0 text-muted" id="desactivarNombrePsi"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Desactivar especialista</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalReactivarPsi" tabindex="-1" aria-labelledby="modalReactivarPsiLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= Helper::baseUrl('consultorio/psicologos/reactivar'); ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="clvPsi" id="reactivarClvPsi" value="">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="modalReactivarPsiLabel">Reactivar especialista</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        El especialista volverá a estar activo. No se publicará automáticamente ni se
                        reactivarán disponibilidad o servicios.
                    </p>
                    <p class="mb-0 text-muted" id="reactivarNombrePsi"></p>
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
(function () {
    function bindSimple(modalId, clvInputId, nombreElId) {
        var modal = document.getElementById(modalId);
        if (!modal) return;
        modal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            var clvInput = document.getElementById(clvInputId);
            var nombreEl = document.getElementById(nombreElId);
            if (clvInput) clvInput.value = btn.getAttribute('data-clvpsi') || '';
            if (nombreEl) nombreEl.textContent = btn.getAttribute('data-nombre') || '';
        });
    }

    var modalEliminar = document.getElementById('modalEliminarPsi');
    if (modalEliminar) {
        modalEliminar.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            document.getElementById('eliminarClvPsi').value = btn.getAttribute('data-clvpsi') || '';
            document.getElementById('eliminarNombrePsi').textContent = btn.getAttribute('data-nombre') || '';
            var modo = btn.getAttribute('data-modo') || 'eliminar';
            var titulo = document.getElementById('modalEliminarPsiLabel');
            var submit = document.getElementById('eliminarSubmit');
            if (modo === 'cancelar') {
                if (titulo) titulo.textContent = 'Cancelar registro';
                if (submit) submit.textContent = 'Cancelar registro';
            } else {
                if (titulo) titulo.textContent = 'Eliminar especialista';
                if (submit) submit.textContent = 'Eliminar definitivamente';
            }
        });
    }

    var modalInfo = document.getElementById('modalInfoRelacionada');
    if (modalInfo) {
        modalInfo.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            var nombre = btn.getAttribute('data-nombre') || '';
            var depsRaw = btn.getAttribute('data-deps') || '{}';
            var deps = {};
            try { deps = JSON.parse(depsRaw); } catch (e) { deps = {}; }

            document.getElementById('infoNombrePsi').textContent = nombre;

            var lista = document.getElementById('infoListaConteos');
            lista.innerHTML = '';
            var items = [
                [(deps.totalCitas || 0) + ' citas registradas', deps.totalCitas],
                [(deps.pacientesRelacionados || 0) + ' pacientes relacionados', deps.pacientesRelacionados],
                [(deps.expedientes || 0) + ' expedientes', deps.expedientes],
                [(deps.seguimientos || 0) + ' seguimientos', deps.seguimientos],
                [(deps.diagnosticos || 0) + ' diagnósticos', deps.diagnosticos]
            ];
            items.forEach(function (pair) {
                if (!pair[1]) return;
                var li = document.createElement('li');
                li.textContent = pair[0];
                lista.appendChild(li);
            });
            if (!lista.children.length) {
                var li0 = document.createElement('li');
                li0.textContent = 'Información histórica relacionada';
                lista.appendChild(li0);
            }

            var futuras = parseInt(deps.citasFuturas || 0, 10);
            var alerta = document.getElementById('infoAlertaFuturas');
            var verCitas = document.getElementById('infoVerCitas');
            var formDes = document.getElementById('infoFormDesactivar');

            if (futuras > 0) {
                alerta.classList.remove('d-none');
                alerta.textContent = 'No puedes eliminar ni desactivar este especialista porque tiene '
                    + futuras + ' cita(s) futura(s) programada(s). Resuelve primero esas citas.';
                verCitas.classList.remove('d-none');
                verCitas.href = deps.urlCitas || '#';
                formDes.classList.add('d-none');
            } else if (deps.puedeDesactivar) {
                alerta.classList.add('d-none');
                verCitas.classList.add('d-none');
                formDes.classList.remove('d-none');
                document.getElementById('infoDesactivarClvPsi').value = btn.getAttribute('data-clvpsi') || '';
            } else {
                alerta.classList.add('d-none');
                verCitas.classList.add('d-none');
                formDes.classList.add('d-none');
            }
        });
    }

    bindSimple('modalDesactivarPsi', 'desactivarClvPsi', 'desactivarNombrePsi');
    bindSimple('modalReactivarPsi', 'reactivarClvPsi', 'reactivarNombrePsi');
})();
</script>
<?php endif; ?>
