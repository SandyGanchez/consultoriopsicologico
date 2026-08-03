<?php

use App\Helpers\Helper;

$especialistas = is_array($especialistas ?? null) ? $especialistas : [];
$especialidadesFiltro = is_array($especialidadesFiltro ?? null)
    ? $especialidadesFiltro
    : [];
$modoVistaPrevia = !empty($modoVistaPrevia);
$busquedaEspecialistas = trim((string) ($busquedaEspecialistas ?? ''));
$filtroEspecialidad = trim((string) ($filtroEspecialidad ?? ''));
$filtrosActivos = !empty($filtrosActivos);
$totalEspecialistas = (int) ($totalEspecialistas ?? count($especialistas));
$rutaBusqueda = trim((string) ($rutaBusquedaEspecialistas ?? ''));

if ($rutaBusqueda === '') {
    $rutaBusqueda = Helper::baseUrl('');
}

$iconosRed = [
    'Facebook' => 'bi-facebook',
    'Instagram' => 'bi-instagram',
    'WhatsApp' => 'bi-whatsapp',
    'TikTok' => 'bi-tiktok',
    'YouTube' => 'bi-youtube',
    'LinkedIn' => 'bi-linkedin',
    'Página Web' => 'bi-globe',
];

$prepararEspecialista = static function (array $especialista) use ($iconosRed): array {
    $nombreCompleto = trim((string) ($especialista['NombreCompleto'] ?? ''));
    $especialidadPsi = trim((string) ($especialista['EspecialidadPsi'] ?? ''));
    $cedulaProf = trim((string) ($especialista['CedulaProfesional'] ?? ''));
    $descripcionProf = trim((string) ($especialista['DescripcionProfesional'] ?? ''));
    $clvPsi = trim((string) ($especialista['ClvPsi'] ?? ''));

    $fotoUrl = Helper::fotoPerfilUrl($especialista['FotoPerfilPer'] ?? null);

    $iniciales = mb_strtoupper(
        mb_substr((string) ($especialista['NombrePer'] ?? 'P'), 0, 1)
        . mb_substr((string) ($especialista['ApPatPer'] ?? 'S'), 0, 1)
    );

    $servicios = is_array($especialista['servicios'] ?? null)
        ? $especialista['servicios']
        : [];
    $redesPsi = is_array($especialista['redes'] ?? null)
        ? $especialista['redes']
        : [];

    $descripcionCorta = $descripcionProf;
    if (mb_strlen($descripcionCorta) > 120) {
        $descripcionCorta = mb_substr($descripcionCorta, 0, 117) . '...';
    }

    $modalId = 'modalEspecialista'
        . preg_replace('/[^A-Za-z0-9_-]/', '', $clvPsi);

    return [
        'nombreCompleto' => $nombreCompleto,
        'especialidadPsi' => $especialidadPsi,
        'cedulaProf' => $cedulaProf,
        'descripcionProf' => $descripcionProf,
        'descripcionCorta' => $descripcionCorta,
        'clvPsi' => $clvPsi,
        'fotoUrl' => $fotoUrl,
        'iniciales' => $iniciales,
        'servicios' => $servicios,
        'redesPsi' => $redesPsi,
        'serviciosVisibles' => array_slice($servicios, 0, 3),
        'serviciosRestantes' => max(0, count($servicios) - 3),
        'urlPerfil' => Helper::baseUrl('especialistas/' . rawurlencode($clvPsi)),
        'urlAgendar' => Helper::baseUrl(
            'agendar-cita?psicologo=' . rawurlencode($clvPsi)
        ),
        'modalId' => $modalId,
        'tituloModalId' => $modalId . 'Titulo',
        'iconosRed' => $iconosRed,
    ];
};

?>

<section
    id="especialistas"
    class="specialists-section py-5"
    aria-labelledby="specialists-heading"
>

    <div class="container">

        <header class="specialists-section__header text-center">

            <span class="specialists-section__eyebrow">
                Equipo profesional
            </span>

            <h2
                id="specialists-heading"
                class="specialists-section__title"
            >
                Nuestros especialistas
            </h2>

            <span
                class="specialists-section__divider"
                aria-hidden="true"
            ></span>

            <p class="specialists-section__intro mx-auto">
                Conoce al equipo y consulta su información profesional.
            </p>

        </header>

        <form
            method="GET"
            action="<?= htmlspecialchars($rutaBusqueda, ENT_QUOTES, 'UTF-8'); ?>"
            class="specialists-search card border-0 shadow-sm mb-4"
            role="search"
        >
            <div class="card-body p-3 p-md-4">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="busquedaEspecialista" class="form-label">
                            Buscar especialista
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-search" aria-hidden="true"></i>
                            </span>
                            <input
                                type="search"
                                class="form-control"
                                id="busquedaEspecialista"
                                name="busqueda"
                                value="<?= htmlspecialchars($busquedaEspecialistas, ENT_QUOTES, 'UTF-8'); ?>"
                                placeholder="Nombre, apellido o especialidad"
                                maxlength="80"
                                autocomplete="off"
                            >
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label for="filtroEspecialidad" class="form-label">
                            Especialidad
                        </label>
                        <select
                            class="form-select"
                            id="filtroEspecialidad"
                            name="especialidad"
                        >
                            <option value="">Todas las especialidades</option>
                            <?php foreach ($especialidadesFiltro as $esp): ?>
                                <?php $esp = trim((string) $esp); ?>
                                <option
                                    value="<?= htmlspecialchars($esp, ENT_QUOTES, 'UTF-8'); ?>"
                                    <?= $filtroEspecialidad === $esp ? 'selected' : ''; ?>
                                >
                                    <?= htmlspecialchars($esp, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn text-white flex-grow-1" style="background:#657166;">
                            Buscar
                        </button>
                        <a
                            href="<?= htmlspecialchars($rutaBusqueda, ENT_QUOTES, 'UTF-8'); ?>#especialistas"
                            class="btn btn-outline-secondary flex-grow-1"
                        >
                            Limpiar filtros
                        </a>
                    </div>
                </div>
            </div>
        </form>

        <?php if ($filtrosActivos): ?>
            <div class="specialists-results-meta mb-4">
                <p class="mb-1" style="color:#657166;">
                    <strong><?= (int) $totalEspecialistas; ?></strong>
                    especialista<?= $totalEspecialistas === 1 ? '' : 's'; ?>
                    encontrado<?= $totalEspecialistas === 1 ? '' : 's'; ?>
                </p>
                <p class="small text-muted mb-0">
                    Filtros activos:
                    <?php if ($busquedaEspecialistas !== ''): ?>
                        búsqueda “<?= htmlspecialchars($busquedaEspecialistas, ENT_QUOTES, 'UTF-8'); ?>”
                    <?php endif; ?>
                    <?php if ($busquedaEspecialistas !== '' && $filtroEspecialidad !== ''): ?>
                        ·
                    <?php endif; ?>
                    <?php if ($filtroEspecialidad !== ''): ?>
                        especialidad “<?= htmlspecialchars($filtroEspecialidad, ENT_QUOTES, 'UTF-8'); ?>”
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if ($especialistas === []): ?>

            <div
                class="specialists-section__empty text-center"
                role="status"
            >
                <i
                    class="bi bi-person-heart specialists-section__empty-icon"
                    aria-hidden="true"
                ></i>

                <?php if ($filtrosActivos): ?>
                    <p class="specialists-section__empty-text mb-3">
                        No encontramos especialistas con esos criterios.
                    </p>
                    <a
                        href="<?= htmlspecialchars($rutaBusqueda, ENT_QUOTES, 'UTF-8'); ?>#especialistas"
                        class="btn rounded-pill px-4 text-white"
                        style="background:#99CDD8;"
                    >
                        Limpiar búsqueda
                    </a>
                <?php elseif ($modoVistaPrevia): ?>
                    <p class="specialists-section__empty-text mb-0">
                        Aún no tienes especialistas visibles. Activa una cuenta,
                        completa su perfil y habilita su publicación.
                    </p>
                <?php else: ?>
                    <p class="specialists-section__empty-text mb-0">
                        No hay especialistas disponibles por el momento.
                    </p>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <?php
            $especialistasVista = [];
            foreach ($especialistas as $especialistaRaw) {
                $especialistasVista[] = $prepararEspecialista(
                    is_array($especialistaRaw) ? $especialistaRaw : []
                );
            }
            ?>

            <div class="row g-4 specialists-section__grid">

                <?php foreach ($especialistasVista as $esp): ?>

                    <div class="col-12 col-md-6 col-lg-4">

                        <article
                            class="specialist-card h-100"
                            aria-label="<?= htmlspecialchars(
                                'Especialista ' . $esp['nombreCompleto'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                        >

                            <div class="specialist-card__photo-wrap">
                                <?php if ($esp['fotoUrl'] !== null): ?>
                                    <img
                                        src="<?= htmlspecialchars($esp['fotoUrl'], ENT_QUOTES, 'UTF-8'); ?>"
                                        alt="Fotografía de <?= htmlspecialchars($esp['nombreCompleto'], ENT_QUOTES, 'UTF-8'); ?>"
                                        class="specialist-photo"
                                        loading="lazy"
                                        width="112"
                                        height="112"
                                        onerror="this.style.display='none';this.nextElementSibling.classList.remove('d-none');"
                                    >
                                    <div
                                        class="specialist-photo specialist-photo--initials d-none"
                                        aria-hidden="true"
                                    >
                                        <?= htmlspecialchars($esp['iniciales'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php else: ?>
                                    <div
                                        class="specialist-photo specialist-photo--initials"
                                        aria-hidden="true"
                                    >
                                        <?= htmlspecialchars($esp['iniciales'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="specialist-card__body">

                                <h3 class="specialist-card__name">
                                    <?= htmlspecialchars($esp['nombreCompleto'], ENT_QUOTES, 'UTF-8'); ?>
                                </h3>

                                <?php if ($esp['especialidadPsi'] !== ''): ?>
                                    <span class="specialist-specialty">
                                        <?= htmlspecialchars($esp['especialidadPsi'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($esp['cedulaProf'] !== ''): ?>
                                    <p class="specialist-card__clinic mb-1">
                                        <i class="bi bi-card-text" aria-hidden="true"></i>
                                        Cédula <?= htmlspecialchars($esp['cedulaProf'], ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                <?php endif; ?>

                                <?php if ($esp['descripcionCorta'] !== ''): ?>
                                    <p class="specialist-card__description">
                                        <?= htmlspecialchars($esp['descripcionCorta'], ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                <?php endif; ?>

                                <div class="specialist-services">
                                    <span class="specialist-services__label">Servicios</span>

                                    <?php if ($esp['servicios'] === []): ?>
                                        <span class="specialist-services__empty">
                                            Consulta la información completa
                                        </span>
                                    <?php else: ?>
                                        <div class="specialist-services__tags">
                                            <?php foreach ($esp['serviciosVisibles'] as $servicio): ?>
                                                <span class="specialist-services__tag">
                                                    <?= htmlspecialchars(
                                                        (string) ($servicio['NombreServicio'] ?? ''),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>
                                                </span>
                                            <?php endforeach; ?>

                                            <?php if ($esp['serviciosRestantes'] > 0): ?>
                                                <span class="specialist-services__more">
                                                    y más
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            </div>

                            <div class="specialist-actions">
                                <button
                                    type="button"
                                    class="btn specialist-actions__primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#<?= htmlspecialchars($esp['modalId'], ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    Ver información
                                </button>
                            </div>

                        </article>

                    </div>

                <?php endforeach; ?>

            </div>

            <?php foreach ($especialistasVista as $esp): ?>

                <div
                    class="modal fade specialist-modal"
                    id="<?= htmlspecialchars($esp['modalId'], ENT_QUOTES, 'UTF-8'); ?>"
                    tabindex="-1"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="<?= htmlspecialchars($esp['tituloModalId'], ENT_QUOTES, 'UTF-8'); ?>"
                    aria-hidden="true"
                >
                    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                        <div class="modal-content specialist-modal__content">

                            <div class="modal-header specialist-modal__header">
                                <h2
                                    class="modal-title h5 specialist-modal__title"
                                    id="<?= htmlspecialchars($esp['tituloModalId'], ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    Información del especialista
                                </h2>
                                <button
                                    type="button"
                                    class="btn-close"
                                    data-bs-dismiss="modal"
                                    aria-label="Cerrar"
                                ></button>
                            </div>

                            <div class="modal-body specialist-modal__body">

                                <div class="specialist-modal__intro">
                                    <div class="specialist-modal__photo-wrap">
                                        <?php if ($esp['fotoUrl'] !== null): ?>
                                            <img
                                                src="<?= htmlspecialchars($esp['fotoUrl'], ENT_QUOTES, 'UTF-8'); ?>"
                                                alt="Fotografía de <?= htmlspecialchars($esp['nombreCompleto'], ENT_QUOTES, 'UTF-8'); ?>"
                                                class="specialist-modal__photo"
                                                width="96"
                                                height="96"
                                            >
                                        <?php else: ?>
                                            <div
                                                class="specialist-modal__photo specialist-modal__photo--initials"
                                                aria-hidden="true"
                                            >
                                                <?= htmlspecialchars($esp['iniciales'], ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="specialist-modal__identity">
                                        <p class="specialist-modal__name mb-1">
                                            <?= htmlspecialchars($esp['nombreCompleto'], ENT_QUOTES, 'UTF-8'); ?>
                                        </p>

                                        <?php if ($esp['especialidadPsi'] !== ''): ?>
                                            <span class="specialist-specialty">
                                                <?= htmlspecialchars($esp['especialidadPsi'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($esp['cedulaProf'] !== ''): ?>
                                            <p class="specialist-modal__cedula mb-0 mt-2">
                                                Cédula profesional:
                                                <?= htmlspecialchars($esp['cedulaProf'], ENT_QUOTES, 'UTF-8'); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($esp['descripcionProf'] !== ''): ?>
                                    <section class="specialist-modal__block" aria-label="Descripción profesional">
                                        <h3 class="specialist-modal__heading">Descripción profesional</h3>
                                        <p class="specialist-modal__text mb-0">
                                            <?= nl2br(htmlspecialchars($esp['descripcionProf'], ENT_QUOTES, 'UTF-8')); ?>
                                        </p>
                                    </section>
                                <?php endif; ?>

                                <section class="specialist-modal__block" aria-label="Servicios disponibles">
                                    <h3 class="specialist-modal__heading">Servicios disponibles</h3>

                                    <?php if ($esp['servicios'] === []): ?>
                                        <p class="specialist-modal__empty mb-0">
                                            No hay servicios disponibles para agendamiento actualmente.
                                        </p>
                                    <?php else: ?>
                                        <ul class="specialist-modal__services list-unstyled mb-0">
                                            <?php foreach ($esp['servicios'] as $servicio): ?>
                                                <?php
                                                $nombreServ = trim((string) ($servicio['NombreServicio'] ?? ''));
                                                $precioServ = (float) ($servicio['PrecioServicio'] ?? 0);
                                                $duracionServ = (int) ($servicio['DuracionMinutos'] ?? 0);
                                                ?>
                                                <li class="specialist-modal__service">
                                                    <div class="specialist-modal__service-main">
                                                        <span class="specialist-modal__service-name">
                                                            <?= htmlspecialchars($nombreServ, ENT_QUOTES, 'UTF-8'); ?>
                                                        </span>
                                                        <span class="specialist-modal__service-price">
                                                            $<?= number_format($precioServ, 2); ?>
                                                        </span>
                                                    </div>
                                                    <?php if ($duracionServ > 0): ?>
                                                        <span class="specialist-modal__duration">
                                                            <?= (int) $duracionServ; ?> min
                                                        </span>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </section>

                                <?php if ($esp['redesPsi'] !== []): ?>
                                    <section class="specialist-modal__block" aria-label="Redes profesionales">
                                        <h3 class="specialist-modal__heading">Redes profesionales</h3>
                                        <div class="specialist-modal__redes">
                                            <?php foreach ($esp['redesPsi'] as $redProf): ?>
                                                <?php
                                                $tipoR = (string) ($redProf['TipoRed'] ?? '');
                                                $urlR = (string) ($redProf['URLRed'] ?? '');
                                                $etiR = trim((string) ($redProf['EtiquetaRed'] ?? ''));
                                                $labelR = $etiR !== '' ? $etiR : $tipoR;
                                                if ($urlR === '' || $labelR === '') {
                                                    continue;
                                                }
                                                $iconoR = $esp['iconosRed'][$tipoR] ?? 'bi-globe';
                                                ?>
                                                <a
                                                    href="<?= htmlspecialchars($urlR, ENT_QUOTES, 'UTF-8'); ?>"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="specialist-modal__red"
                                                    aria-label="<?= htmlspecialchars(
                                                        'Abrir ' . $labelR . ' en una pestaña nueva',
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>"
                                                >
                                                    <i class="bi <?= htmlspecialchars($iconoR, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                                                    <span><?= htmlspecialchars($labelR, ENT_QUOTES, 'UTF-8'); ?></span>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>
                                <?php endif; ?>

                            </div>

                            <div class="modal-footer specialist-modal__footer">
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary rounded-pill px-3"
                                    data-bs-dismiss="modal"
                                >
                                    Cerrar
                                </button>

                                <?php if ($modoVistaPrevia): ?>
                                    <span
                                        class="btn rounded-pill px-4 text-white specialist-actions__primary--disabled"
                                        title="Disponible al publicar"
                                        aria-disabled="true"
                                    >
                                        Agendar cita
                                    </span>
                                <?php else: ?>
                                    <a
                                        href="<?= htmlspecialchars($esp['urlAgendar'], ENT_QUOTES, 'UTF-8'); ?>"
                                        class="btn specialist-actions__primary"
                                    >
                                        <i class="bi bi-calendar-check" aria-hidden="true"></i>
                                        Agendar cita
                                    </a>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</section>
