<?php

use App\Helpers\Helper;

$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$filtros = is_array($filtros ?? null) ? $filtros : [];
$resumen = is_array($resumen ?? null) ? $resumen : [];
$filas = is_array($filas ?? null) ? $filas : [];
$psicologos = is_array($psicologos ?? null) ? $psicologos : [];
$servicios = is_array($servicios ?? null) ? $servicios : [];

$formatearHora = static function (?string $hora): string {
    $hora = trim((string) $hora);
    if ($hora === '') {
        return '';
    }
    return substr($hora, 0, 5);
};

?>

<section class="consultorio-page">
    <header class="consultorio-page-header mb-4">
        <p class="consultorio-page-eyebrow mb-1">Supervisión operativa</p>
        <h1 class="h3 mb-2">Actividad de especialistas</h1>
        <p class="mb-0 text-muted">
            Indicadores de citas y tarifas registradas. No evalúa calidad clínica
            ni representa pagos cobrados.
        </p>
    </header>

    <div class="alert alert-light border mb-4" role="note">
        <p class="mb-1">
            Los importes corresponden a tarifas registradas en las citas.
        </p>
        <p class="mb-0">
            No representan necesariamente pagos cobrados.
        </p>
    </div>

    <form method="GET" action="<?= Helper::baseUrl('consultorio/actividad-especialistas'); ?>" class="row g-3 mb-4">
        <div class="col-md-3">
            <label class="form-label" for="filtroDesde">Desde</label>
            <input
                type="date"
                class="form-control"
                id="filtroDesde"
                name="desde"
                value="<?= $esc($filtros['desde'] ?? ''); ?>"
            >
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filtroHasta">Hasta</label>
            <input
                type="date"
                class="form-control"
                id="filtroHasta"
                name="hasta"
                value="<?= $esc($filtros['hasta'] ?? ''); ?>"
            >
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filtroPsi">Especialista</label>
            <select class="form-select" id="filtroPsi" name="psicologo">
                <option value="">Todos</option>
                <?php foreach ($psicologos as $psi): ?>
                    <?php $clv = (string) ($psi['ClvPsi'] ?? ''); ?>
                    <option
                        value="<?= $esc($clv); ?>"
                        <?= ($filtros['psicologo'] ?? '') === $clv ? 'selected' : ''; ?>
                    >
                        <?= $esc(
                            trim((string) ($psi['NombrePsicologo'] ?? $clv))
                        ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filtroServ">Servicio</label>
            <select class="form-select" id="filtroServ" name="servicio">
                <option value="">Todos</option>
                <?php foreach ($servicios as $serv): ?>
                    <?php $clvS = (string) ($serv['ClvServ'] ?? ''); ?>
                    <option
                        value="<?= $esc($clvS); ?>"
                        <?= ($filtros['servicio'] ?? '') === $clvS ? 'selected' : ''; ?>
                    >
                        <?= $esc($serv['NombreServicio'] ?? $clvS); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filtroEstado">Estado</label>
            <select class="form-select" id="filtroEstado" name="estado">
                <option value="">Todos</option>
                <?php foreach (['PROGRAMADA', 'ASISTIDA', 'CANCELADA', 'INASISTENCIA'] as $est): ?>
                    <option
                        value="<?= $est; ?>"
                        <?= ($filtros['estado'] ?? '') === $est ? 'selected' : ''; ?>
                    >
                        <?= $est; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-9 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a
                class="btn btn-outline-secondary"
                href="<?= Helper::baseUrl('consultorio/actividad-especialistas'); ?>"
            >
                Limpiar
            </a>
            <a
                class="btn btn-outline-secondary ms-auto"
                href="<?= Helper::baseUrl('consultorio/servicios'); ?>"
            >
                Consultar tarifas configuradas
            </a>
        </div>
    </form>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="border rounded-3 p-3 bg-white h-100">
                <div class="small text-muted">Programadas</div>
                <strong class="fs-4"><?= (int) ($resumen['programadas'] ?? 0); ?></strong>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="border rounded-3 p-3 bg-white h-100">
                <div class="small text-muted">Asistidas</div>
                <strong class="fs-4"><?= (int) ($resumen['asistidas'] ?? 0); ?></strong>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="border rounded-3 p-3 bg-white h-100">
                <div class="small text-muted">Inasistencias</div>
                <strong class="fs-4"><?= (int) ($resumen['inasistencias'] ?? 0); ?></strong>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="border rounded-3 p-3 bg-white h-100">
                <div class="small text-muted">Cancelaciones</div>
                <strong class="fs-4"><?= (int) ($resumen['canceladas'] ?? 0); ?></strong>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="border rounded-3 p-3 bg-white h-100">
                <div class="small text-muted">Servicios activos</div>
                <strong class="fs-4"><?= (int) ($serviciosActivos ?? 0); ?></strong>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="border rounded-3 p-3 bg-white h-100">
                <div class="small text-muted">Disponibilidad configurada</div>
                <strong class="fs-4"><?= (int) ($bloquesDisponibilidad ?? 0); ?></strong>
                <div class="small text-muted">bloques activos</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="border rounded-3 p-3 bg-white h-100">
                <div class="small text-muted">Importe programado</div>
                <strong class="fs-5">
                    <?= $esc(Helper::formatearMonedaMxn($resumen['importeProgramado'] ?? 0)); ?>
                </strong>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="border rounded-3 p-3 bg-white h-100">
                <div class="small text-muted">Importe asociado a asistidas</div>
                <strong class="fs-5">
                    <?= $esc(Helper::formatearMonedaMxn($resumen['importeAsistidas'] ?? 0)); ?>
                </strong>
            </div>
        </div>
    </div>

    <div class="table-responsive bg-white border rounded-3 p-2">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Especialista</th>
                    <th>Servicio</th>
                    <th>Fecha y hora</th>
                    <th>Duración</th>
                    <th>Tarifa aplicada</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($filas === []): ?>
                    <tr>
                        <td colspan="6" class="text-muted">
                            No hay citas con los filtros seleccionados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($filas as $fila): ?>
                        <tr>
                            <td><?= $esc($fila['NombrePsicologo'] ?? ''); ?></td>
                            <td><?= $esc($fila['NombreServicio'] ?? ''); ?></td>
                            <td>
                                <?= $esc($fila['FechaCita'] ?? ''); ?>
                                <?= $esc($formatearHora($fila['HraInicioCita'] ?? null)); ?>
                            </td>
                            <td>
                                <?php $d = (int) ($fila['DuracionAplicadaMin'] ?? 0); ?>
                                <?= $d > 0 ? $esc($d) . ' min' : '—'; ?>
                            </td>
                            <td>
                                <?= $esc(
                                    Helper::formatearMonedaMxn($fila['CostoAplicado'] ?? 0)
                                ); ?>
                            </td>
                            <td><?= $esc($fila['EstadoCita'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
