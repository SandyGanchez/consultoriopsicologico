<?php

use App\Helpers\Helper;

$esc = static function (?string $valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
};

$expedientes = is_array($expedientes ?? null) ? $expedientes : [];
$resumen = is_array($resumen ?? null) ? $resumen : [];
$q = (string) ($q ?? '');
$filtroActividad = (string) ($filtroActividad ?? 'TODOS');
$filtroCita = (string) ($filtroCita ?? 'TODOS');
$filtroPendiente = (string) ($filtroPendiente ?? 'TODOS');
$orden = (string) ($orden ?? 'NOMBRE_ASC');
$paginaActual = max(1, (int) ($paginaActual ?? 1));
$totalPaginas = max(1, (int) ($totalPaginas ?? 1));
$totalExpedientes = (int) ($totalExpedientes ?? 0);
$desde = (int) ($desde ?? 0);
$hasta = (int) ($hasta ?? 0);
$filtrosActivos = !empty($filtrosActivos);
$errorCarga = !empty($errorCarga);

$urlCatalogo = static function (
    array $overrides = [],
    ?int $pagina = null
) use (
    $q,
    $filtroActividad,
    $filtroCita,
    $filtroPendiente,
    $orden
): string {
    $params = array_merge([
        'q' => $q,
        'actividad' => $filtroActividad,
        'cita' => $filtroCita,
        'pendiente' => $filtroPendiente,
        'orden' => $orden
    ], $overrides);

    if ($pagina !== null && $pagina > 1) {
        $params['pagina'] = $pagina;
    }

    foreach ($params as $clave => $valor) {
        if ($valor === '' || $valor === null) {
            unset($params[$clave]);
            continue;
        }
        if (
            in_array($clave, ['actividad', 'cita', 'pendiente'], true)
            && $valor === 'TODOS'
        ) {
            unset($params[$clave]);
        }
        if ($clave === 'orden' && $valor === 'NOMBRE_ASC') {
            unset($params[$clave]);
        }
    }

    $base = Helper::baseUrl('psicologo/expedientes');
    if ($params === []) {
        return $base;
    }

    return $base . '?' . http_build_query($params);
};

$formatearFecha = static function (
    ?string $fecha,
    ?string $hora = null,
    string $vacio = 'Sin registro'
): string {
    $fecha = trim((string) $fecha);
    if ($fecha === '') {
        return $vacio;
    }

    try {
        $dt = new DateTimeImmutable($fecha);
        $texto = $dt->format('d/m/Y');
    } catch (Throwable $e) {
        return $vacio;
    }

    $hora = trim((string) $hora);
    if ($hora !== '') {
        $texto .= ' · ' . substr($hora, 0, 5);
    }

    return $texto;
};

?>

<section class="expedientes-page" aria-labelledby="expedientes-titulo">

    <header class="expedientes-header">
        <h1 id="expedientes-titulo">Expedientes</h1>
        <p class="expedientes-header__desc">
            Consulta los expedientes de los pacientes asignados a tu atención.
        </p>
    </header>

    <?php if ($errorCarga): ?>
        <div class="expedientes-alert" role="alert">
            No fue posible cargar los expedientes.
        </div>
    <?php else: ?>

        <form
            method="GET"
            action="<?= $esc(Helper::baseUrl('psicologo/expedientes')); ?>"
            class="expedientes-filters"
            role="search"
        >
            <div class="row g-3">
                <div class="col-12 col-lg-4">
                    <label class="form-label" for="exp-q">Buscar</label>
                    <input
                        type="search"
                        class="form-control"
                        id="exp-q"
                        name="q"
                        value="<?= $esc($q); ?>"
                        maxlength="80"
                        placeholder="Nombre, apellido o folio"
                        autocomplete="off"
                    >
                </div>

                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label" for="exp-actividad">Actividad</label>
                    <select class="form-select" id="exp-actividad" name="actividad">
                        <option value="TODOS" <?= $filtroActividad === 'TODOS' ? 'selected' : ''; ?>>
                            Todas
                        </option>
                        <option value="ACTIVIDAD_RECIENTE" <?= $filtroActividad === 'ACTIVIDAD_RECIENTE' ? 'selected' : ''; ?>>
                            Actividad reciente
                        </option>
                        <option value="SIN_ACTIVIDAD_RECIENTE" <?= $filtroActividad === 'SIN_ACTIVIDAD_RECIENTE' ? 'selected' : ''; ?>>
                            Sin actividad reciente
                        </option>
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label" for="exp-cita">Próxima cita</label>
                    <select class="form-select" id="exp-cita" name="cita">
                        <option value="TODOS" <?= $filtroCita === 'TODOS' ? 'selected' : ''; ?>>
                            Todas
                        </option>
                        <option value="CON_CITA_PROXIMA" <?= $filtroCita === 'CON_CITA_PROXIMA' ? 'selected' : ''; ?>>
                            Con cita próxima
                        </option>
                        <option value="SIN_CITA_PROXIMA" <?= $filtroCita === 'SIN_CITA_PROXIMA' ? 'selected' : ''; ?>>
                            Sin cita próxima
                        </option>
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label" for="exp-pendiente">Pendiente</label>
                    <select class="form-select" id="exp-pendiente" name="pendiente">
                        <option value="TODOS" <?= $filtroPendiente === 'TODOS' ? 'selected' : ''; ?>>
                            Todos
                        </option>
                        <option value="CON_PENDIENTE" <?= $filtroPendiente === 'CON_PENDIENTE' ? 'selected' : ''; ?>>
                            Con pendiente
                        </option>
                        <option value="SIN_PENDIENTE" <?= $filtroPendiente === 'SIN_PENDIENTE' ? 'selected' : ''; ?>>
                            Sin pendiente
                        </option>
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label" for="exp-orden">Orden</label>
                    <select class="form-select" id="exp-orden" name="orden">
                        <option value="NOMBRE_ASC" <?= $orden === 'NOMBRE_ASC' ? 'selected' : ''; ?>>
                            Nombre A–Z
                        </option>
                        <option value="NOMBRE_DESC" <?= $orden === 'NOMBRE_DESC' ? 'selected' : ''; ?>>
                            Nombre Z–A
                        </option>
                        <option value="ACTIVIDAD_RECIENTE" <?= $orden === 'ACTIVIDAD_RECIENTE' ? 'selected' : ''; ?>>
                            Actividad reciente
                        </option>
                        <option value="ACTIVIDAD_ANTIGUA" <?= $orden === 'ACTIVIDAD_ANTIGUA' ? 'selected' : ''; ?>>
                            Actividad antigua
                        </option>
                    </select>
                </div>

                <div class="col-12">
                    <div class="expedientes-filters__actions">
                        <button type="submit" class="btn expedientes-btn-primary">
                            Aplicar
                        </button>
                        <a
                            href="<?= $esc(Helper::baseUrl('psicologo/expedientes')); ?>"
                            class="btn expedientes-btn-secondary"
                        >
                            Limpiar filtros
                        </a>
                    </div>
                </div>
            </div>
        </form>

        <div class="expedientes-summary" aria-label="Resumen general de expedientes">
            <p class="expedientes-summary__note">
                Resumen general de tu cartera (no cambia con los filtros).
            </p>
            <div class="expedientes-summary__grid">
                <div class="expedientes-summary__item">
                    <span class="expedientes-summary__label">Total</span>
                    <strong><?= (int) ($resumen['total'] ?? 0); ?></strong>
                </div>
                <div class="expedientes-summary__item expedientes-summary__item--cita">
                    <span class="expedientes-summary__label">Con cita próxima</span>
                    <strong><?= (int) ($resumen['conCitaProxima'] ?? 0); ?></strong>
                </div>
                <div class="expedientes-summary__item expedientes-summary__item--pendiente">
                    <span class="expedientes-summary__label">Con pendiente</span>
                    <strong><?= (int) ($resumen['conPendiente'] ?? 0); ?></strong>
                </div>
                <div class="expedientes-summary__item expedientes-summary__item--actividad">
                    <span class="expedientes-summary__label">Actividad reciente</span>
                    <strong><?= (int) ($resumen['actividadReciente'] ?? 0); ?></strong>
                </div>
            </div>
        </div>

        <?php if ($q !== ''): ?>
            <p class="expedientes-search-term">
                Resultados para:
                <strong>“<?= $esc($q); ?>”</strong>
            </p>
        <?php endif; ?>

        <?php if ($expedientes === []): ?>
            <div class="expedientes-empty" role="status">
                <div class="expedientes-empty__icon" aria-hidden="true">
                    <i class="bi bi-folder2"></i>
                </div>
                <?php if ($filtrosActivos): ?>
                    <h2 class="expedientes-empty__title">
                        No se encontraron expedientes con los filtros seleccionados.
                    </h2>
                    <p class="expedientes-empty__desc">
                        Prueba con otros criterios o limpia los filtros.
                    </p>
                    <a
                        href="<?= $esc(Helper::baseUrl('psicologo/expedientes')); ?>"
                        class="btn expedientes-btn-primary"
                    >
                        Limpiar filtros
                    </a>
                <?php else: ?>
                    <h2 class="expedientes-empty__title">
                        No hay expedientes disponibles.
                    </h2>
                    <p class="expedientes-empty__desc">
                        Cuando tengas pacientes con citas asignadas, sus
                        expedientes aparecerán aquí.
                    </p>
                    <a
                        href="<?= $esc(Helper::baseUrl('psicologo/pacientes')); ?>"
                        class="btn expedientes-btn-secondary"
                    >
                        Ir a Mis pacientes
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>

            <p class="expedientes-range">
                Mostrando <?= (int) $desde; ?>–<?= (int) $hasta; ?>
                de <?= (int) $totalExpedientes; ?> expedientes
            </p>

            <div class="expedientes-grid">
                <?php foreach ($expedientes as $item): ?>
                    <?php
                    $clvPac = (string) ($item['ClvPac'] ?? '');
                    $nombre = trim((string) ($item['NombrePaciente'] ?? ''));
                    if ($nombre === '') {
                        $nombre = trim(
                            (string) ($item['NombrePer'] ?? '') . ' ' .
                            (string) ($item['ApPatPer'] ?? '')
                        );
                    }
                    $iniciales = (string) ($item['Iniciales'] ?? 'P');
                    $fotoUrl = null;
                    if (!empty($item['TieneFoto']) && !empty($item['FotoArchivo'])) {
                        $fotoUrl = Helper::fotoPerfilUrl(
                            (string) $item['FotoArchivo']
                        );
                    }
                    $urlAbrir = Helper::baseUrl(
                        'psicologo/pacientes/ver/' . rawurlencode($clvPac) . '/expediente'
                    );
                    ?>
                    <article class="expediente-folder">
                        <div class="expediente-folder__tab" aria-hidden="true"></div>
                        <div class="expediente-folder__body">
                            <header class="expediente-folder__patient">
                                <?php if ($fotoUrl !== null): ?>
                                    <img
                                        class="expediente-folder__photo"
                                        src="<?= $esc($fotoUrl); ?>"
                                        alt="Foto de <?= $esc($nombre); ?>"
                                        width="56"
                                        height="56"
                                        loading="lazy"
                                    >
                                <?php else: ?>
                                    <span
                                        class="expediente-folder__avatar"
                                        aria-hidden="true"
                                    >
                                        <?= $esc($iniciales); ?>
                                    </span>
                                <?php endif; ?>
                                <div class="expediente-folder__identity">
                                    <h2 class="expediente-folder__name">
                                        <?= $esc($nombre); ?>
                                    </h2>
                                    <p class="expediente-folder__folio">
                                        Folio: <?= $esc($clvPac); ?>
                                    </p>
                                </div>
                            </header>

                            <dl class="expediente-folder__meta">
                                <div>
                                    <dt>Última sesión</dt>
                                    <dd>
                                        <?= $esc($formatearFecha(
                                            $item['UltimaFecha'] ?? null,
                                            $item['UltimaHora'] ?? null,
                                            'Sin sesiones registradas'
                                        )); ?>
                                    </dd>
                                </div>
                                <div>
                                    <dt>Próxima cita</dt>
                                    <dd>
                                        <?= $esc($formatearFecha(
                                            $item['ProximaFecha'] ?? null,
                                            $item['ProximaHora'] ?? null,
                                            'Sin próxima cita'
                                        )); ?>
                                    </dd>
                                </div>
                                <div>
                                    <dt>Sesiones</dt>
                                    <dd><?= (int) ($item['TotalAsistidas'] ?? 0); ?></dd>
                                </div>
                            </dl>

                            <ul class="expediente-folder__badges">
                                <?php if (!empty($item['TieneCitaProxima'])): ?>
                                    <li class="expediente-badge expediente-badge--cita">
                                        Cita próxima
                                    </li>
                                <?php endif; ?>
                                <?php if (!empty($item['SeguimientoPendiente'])): ?>
                                    <li class="expediente-badge expediente-badge--pendiente">
                                        Seguimiento pendiente
                                    </li>
                                <?php elseif (!empty($item['HistoriaPendiente'])): ?>
                                    <li class="expediente-badge expediente-badge--pendiente">
                                        Pendiente
                                    </li>
                                <?php endif; ?>
                                <?php if (!empty($item['ActividadReciente'])): ?>
                                    <li class="expediente-badge expediente-badge--actividad">
                                        Actividad reciente
                                    </li>
                                <?php else: ?>
                                    <li class="expediente-badge expediente-badge--sin-actividad">
                                        Sin actividad reciente
                                    </li>
                                <?php endif; ?>
                            </ul>

                            <div class="expediente-folder__actions">
                                <a
                                    class="btn expedientes-btn-primary"
                                    href="<?= $esc($urlAbrir); ?>"
                                >
                                    Abrir expediente
                                    <span class="visually-hidden">
                                        de <?= $esc($nombre); ?>
                                    </span>
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPaginas > 1): ?>
                <nav
                    class="expedientes-pagination"
                    aria-label="Paginación de expedientes"
                >
                    <a
                        class="expedientes-pagination__link<?= $paginaActual <= 1 ? ' is-disabled' : ''; ?>"
                        href="<?= $paginaActual <= 1
                            ? '#'
                            : $esc($urlCatalogo([], $paginaActual - 1)); ?>"
                        <?= $paginaActual <= 1
                            ? 'aria-disabled="true" tabindex="-1"'
                            : ''; ?>
                    >
                        Anterior
                    </a>

                    <span class="expedientes-pagination__status">
                        Página
                        <span aria-current="page"><?= (int) $paginaActual; ?></span>
                        de <?= (int) $totalPaginas; ?>
                    </span>

                    <a
                        class="expedientes-pagination__link<?= $paginaActual >= $totalPaginas ? ' is-disabled' : ''; ?>"
                        href="<?= $paginaActual >= $totalPaginas
                            ? '#'
                            : $esc($urlCatalogo([], $paginaActual + 1)); ?>"
                        <?= $paginaActual >= $totalPaginas
                            ? 'aria-disabled="true" tabindex="-1"'
                            : ''; ?>
                    >
                        Siguiente
                    </a>
                </nav>
            <?php endif; ?>

        <?php endif; ?>

    <?php endif; ?>

</section>
