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
                Encuentra al especialista ideal para ti
            </h2>

            <span
                class="specialists-section__divider"
                aria-hidden="true"
            ></span>

            <p class="specialists-section__intro mx-auto">
                Busca por nombre o especialidad y conoce los servicios disponibles.
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
                        Por el momento no hay especialistas disponibles para mostrar.
                    </p>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <div class="row g-4 specialists-section__grid">

                <?php foreach ($especialistas as $especialista): ?>

                    <?php
                        $nombreCompleto = trim(
                            (string) ($especialista['NombreCompleto'] ?? '')
                        );
                        $especialidadPsi = trim(
                            (string) ($especialista['EspecialidadPsi'] ?? '')
                        );
                        $descripcionProf = trim(
                            (string) ($especialista['DescripcionProfesional'] ?? '')
                        );
                        $nombreCons = trim(
                            (string) ($especialista['NombreCons'] ?? '')
                        );
                        $clvPsi = trim(
                            (string) ($especialista['ClvPsi'] ?? '')
                        );
                        $clvConsEsp = trim(
                            (string) (
                                $especialista['ClvCons']
                                ?? ($clvConsPublico ?? '')
                            )
                        );

                        $fotoUrl = Helper::fotoPerfilUrl(
                            $especialista['FotoPerfilPer'] ?? null
                        );

                        $iniciales = mb_strtoupper(
                            mb_substr(
                                (string) ($especialista['NombrePer'] ?? 'P'),
                                0,
                                1
                            )
                            . mb_substr(
                                (string) ($especialista['ApPatPer'] ?? 'S'),
                                0,
                                1
                            )
                        );

                        $servicios = is_array($especialista['servicios'] ?? null)
                            ? $especialista['servicios']
                            : [];
                        $serviciosVisibles = array_slice($servicios, 0, 3);
                        $serviciosRestantes = max(0, count($servicios) - 3);

                        $urlPerfil = Helper::baseUrl(
                            'especialistas/' . rawurlencode($clvPsi)
                        );
                        $urlAgendar = Helper::baseUrl(
                            'agendar-cita?psicologo=' . rawurlencode($clvPsi)
                        );
                    ?>

                    <div class="col-12 col-md-6 col-lg-4">

                        <article
                            class="specialist-card h-100"
                            aria-label="<?= htmlspecialchars(
                                'Perfil de ' . $nombreCompleto,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                        >

                            <div class="specialist-card__photo-wrap">
                                <?php if ($fotoUrl !== null): ?>
                                    <img
                                        src="<?= htmlspecialchars($fotoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                        alt="Fotografía de <?= htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8'); ?>"
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
                                        <?= htmlspecialchars($iniciales, ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php else: ?>
                                    <div
                                        class="specialist-photo specialist-photo--initials"
                                        aria-hidden="true"
                                    >
                                        <?= htmlspecialchars($iniciales, ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="specialist-card__body">

                                <h3 class="specialist-card__name">
                                    <?= htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8'); ?>
                                </h3>

                                <?php if ($especialidadPsi !== ''): ?>
                                    <span class="specialist-specialty">
                                        <?= htmlspecialchars($especialidadPsi, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($nombreCons !== ''): ?>
                                    <p class="specialist-card__clinic">
                                        <i class="bi bi-building" aria-hidden="true"></i>
                                        <?= htmlspecialchars($nombreCons, ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                <?php endif; ?>

                                <?php if ($descripcionProf !== ''): ?>
                                    <p class="specialist-card__description">
                                        <?= htmlspecialchars($descripcionProf, ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                <?php endif; ?>

                                <div class="specialist-services">
                                    <span class="specialist-services__label">Servicios</span>

                                    <?php if ($servicios === []): ?>
                                        <span class="specialist-services__empty">
                                            Sin servicios publicados.
                                        </span>
                                    <?php else: ?>
                                        <div class="specialist-services__tags">
                                            <?php foreach ($serviciosVisibles as $servicio): ?>
                                                <span class="specialist-services__tag">
                                                    <?= htmlspecialchars(
                                                        (string) ($servicio['NombreServicio'] ?? ''),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>
                                                </span>
                                            <?php endforeach; ?>

                                            <?php if ($serviciosRestantes > 0): ?>
                                                <span class="specialist-services__more">
                                                    +<?= $serviciosRestantes; ?>
                                                    servicio<?= $serviciosRestantes === 1 ? '' : 's'; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            </div>

                            <div class="specialist-actions specialist-actions--dual">
                                <a
                                    href="<?= htmlspecialchars($urlPerfil, ENT_QUOTES, 'UTF-8'); ?>"
                                    class="btn specialist-actions__secondary"
                                >
                                    Ver perfil
                                </a>

                                <?php if ($modoVistaPrevia): ?>
                                    <span
                                        class="btn specialist-actions__primary specialist-actions__primary--disabled"
                                        title="Disponible al publicar"
                                        aria-disabled="true"
                                    >
                                        Disponible al publicar
                                    </span>
                                <?php else: ?>
                                    <a
                                        href="<?= htmlspecialchars($urlAgendar, ENT_QUOTES, 'UTF-8'); ?>"
                                        class="btn specialist-actions__primary"
                                    >
                                        <i class="bi bi-calendar-check" aria-hidden="true"></i>
                                        Agendar cita
                                    </a>
                                <?php endif; ?>
                            </div>

                        </article>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</section>
