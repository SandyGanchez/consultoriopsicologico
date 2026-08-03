<?php

use App\Helpers\Helper;

$especialista = is_array($especialista ?? null) ? $especialista : [];
$servicios = is_array($servicios ?? null) ? $servicios : [];
$modoVistaPrevia = !empty($modoVistaPrevia);
$bannerVistaPrevia = trim((string) ($bannerVistaPrevia ?? ''));
$rutaPaginaPublica = trim((string) ($rutaPaginaPublica ?? ''));

$nombreCompleto = trim((string) ($especialista['NombreCompleto'] ?? ''));
$especialidad = trim((string) ($especialista['EspecialidadPsi'] ?? ''));
$descripcion = trim((string) ($especialista['DescripcionProfesional'] ?? ''));
$nombreCons = trim((string) ($especialista['NombreCons'] ?? ''));
$clvPsi = trim((string) ($especialista['ClvPsi'] ?? ''));

$fotoUrl = Helper::fotoPerfilUrl(
    $especialista['FotoPerfilPer'] ?? null
);

$iniciales = mb_strtoupper(
    mb_substr((string) ($especialista['NombrePer'] ?? 'E'), 0, 1)
    . mb_substr((string) ($especialista['ApPatPer'] ?? ''), 0, 1)
);

$ubicacionCons = trim(implode(', ', array_filter([
    trim((string) ($especialista['MunicipioDir'] ?? '')),
    trim((string) ($especialista['EstadoDir'] ?? ''))
])));

$clvConsPerfil = trim((string) ($especialista['ClvCons'] ?? ''));

$urlVolver = $rutaPaginaPublica !== ''
    ? Helper::baseUrl($rutaPaginaPublica) . '#especialistas'
    : Helper::baseUrl('') . '#especialistas';

$urlAgendar = Helper::baseUrl(
    'agendar-cita?psicologo=' . rawurlencode($clvPsi)
);

?>

<?php if ($modoVistaPrevia && $bannerVistaPrevia !== ''): ?>
    <div
        class="alert alert-warning text-center rounded-0 mb-0 border-0"
        role="status"
        style="background:#FFF3CD;color:#664d03;"
    >
        <i class="bi bi-eye me-1" aria-hidden="true"></i>
        <?= htmlspecialchars($bannerVistaPrevia, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<section class="public-profile py-5">
    <div class="container">

        <a href="<?= htmlspecialchars($urlVolver, ENT_QUOTES, 'UTF-8'); ?>" class="public-profile__back">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Volver a especialistas
        </a>

        <header class="public-profile__hero">

            <div class="public-profile__photo-wrap">
                <?php if ($fotoUrl !== null): ?>
                    <img
                        src="<?= htmlspecialchars($fotoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                        alt="Fotografía de <?= htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8'); ?>"
                        class="public-profile__photo"
                        width="140"
                        height="140"
                    >
                <?php else: ?>
                    <div class="public-profile__photo public-profile__photo--initials" aria-hidden="true">
                        <?= htmlspecialchars($iniciales, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="public-profile__intro">
                <h1 class="public-profile__name">
                    <?= htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8'); ?>
                </h1>

                <?php if ($especialidad !== ''): ?>
                    <span class="public-profile__specialty">
                        <?= htmlspecialchars($especialidad, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                <?php endif; ?>

                <?php if ($nombreCons !== ''): ?>
                    <p class="public-profile__clinic">
                        <i class="bi bi-building" aria-hidden="true"></i>
                        <?= htmlspecialchars($nombreCons, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                <?php endif; ?>

                <span class="public-profile__badge">
                    Especialista disponible
                </span>

                <div class="public-profile__actions mt-3">
                    <?php if ($modoVistaPrevia): ?>
                        <span class="btn rounded-pill px-4 navbar-public__cta--disabled text-white" aria-disabled="true">
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
            </div>
        </header>

        <div class="row g-4 mt-2">

            <div class="col-lg-7">
                <article class="public-profile__card">
                    <h2 class="h5 mb-3" style="color:#657166;">Sobre el especialista</h2>
                    <?php if ($descripcion !== ''): ?>
                        <p class="mb-0 text-secondary" style="line-height:1.7;">
                            <?= nl2br(htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8')); ?>
                        </p>
                    <?php else: ?>
                        <p class="mb-0 text-muted fst-italic">
                            Aún no se ha agregado una descripción profesional.
                        </p>
                    <?php endif; ?>
                </article>

                <?php if ($especialidad !== ''): ?>
                    <article class="public-profile__card mt-4">
                        <h2 class="h5 mb-3" style="color:#657166;">Especialidad</h2>
                        <p class="mb-0">
                            <?= htmlspecialchars($especialidad, ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    </article>
                <?php endif; ?>
            </div>

            <div class="col-lg-5">
                <article class="public-profile__card">
                    <h2 class="h5 mb-3" style="color:#657166;">Consultorio donde atiende</h2>
                    <p class="mb-2">
                        <strong><?= htmlspecialchars($nombreCons, ENT_QUOTES, 'UTF-8'); ?></strong>
                    </p>
                    <?php if ($ubicacionCons !== ''): ?>
                        <p class="text-muted mb-3">
                            <i class="bi bi-geo-alt" aria-hidden="true"></i>
                            <?= htmlspecialchars($ubicacionCons, ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    <?php endif; ?>
                    <p class="text-muted small mb-0">
                        Consulta horarios disponibles al agendar tu cita.
                    </p>
                </article>
            </div>

        </div>

        <?php
        $redesProfesionales = is_array($redesProfesionales ?? null) ? $redesProfesionales : [];
        $iconosRed = [
            'Facebook' => 'bi-facebook',
            'Instagram' => 'bi-instagram',
            'WhatsApp' => 'bi-whatsapp',
            'TikTok' => 'bi-tiktok',
            'YouTube' => 'bi-youtube',
            'LinkedIn' => 'bi-linkedin',
            'Página Web' => 'bi-globe'
        ];
        ?>

        <?php if ($redesProfesionales !== []): ?>
            <section class="mt-4" aria-labelledby="redes-especialista-heading">
                <h2 id="redes-especialista-heading" class="h5 mb-3" style="color:#657166;">
                    Redes profesionales
                </h2>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($redesProfesionales as $redProf): ?>
                        <?php
                        $tipoR = (string) ($redProf['TipoRed'] ?? '');
                        $urlR = (string) ($redProf['URLRed'] ?? '');
                        $etiR = trim((string) ($redProf['EtiquetaRed'] ?? ''));
                        $labelR = $etiR !== '' ? $etiR : $tipoR;
                        ?>
                        <a
                            href="<?= htmlspecialchars($urlR, ENT_QUOTES, 'UTF-8'); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn btn-sm rounded-pill px-3"
                            style="background:#99CDD8;color:#1f2a24;"
                            aria-label="<?= htmlspecialchars('Abrir ' . $labelR . ' en una pestaña nueva', ENT_QUOTES, 'UTF-8'); ?>"
                        >
                            <i class="bi <?= htmlspecialchars($iconosRed[$tipoR] ?? 'bi-globe', ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                            <?= htmlspecialchars($labelR, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="mt-5" aria-labelledby="servicios-especialista-heading">
            <h2 id="servicios-especialista-heading" class="h4 mb-4" style="color:#657166;">
                Servicios disponibles
            </h2>

            <?php if ($servicios === []): ?>
                <div class="public-profile__card text-muted">
                    Aún no hay servicios publicados para este especialista.
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($servicios as $servicio): ?>
                        <?php
                            $nombreServ = trim((string) ($servicio['NombreServicio'] ?? ''));
                            $descServ = trim((string) (
                                $servicio['Descripcion']
                                ?? $servicio['DescripcionServicio']
                                ?? ''
                            ));
                            $precio = (float) ($servicio['PrecioServicio'] ?? 0);
                            $duracion = (int) ($servicio['DuracionMinutos'] ?? 0);
                            $clvServ = trim((string) ($servicio['ClvServ'] ?? ''));
                            $urlServicio = Helper::baseUrl(
                                'agendar-cita?psicologo='
                                . rawurlencode($clvPsi)
                                . '&servicio='
                                . rawurlencode($clvServ)
                            );
                        ?>
                        <div class="col-md-6">
                            <article class="public-profile__service h-100">
                                <h3 class="h6 mb-2" style="color:#657166;">
                                    <?= htmlspecialchars($nombreServ, ENT_QUOTES, 'UTF-8'); ?>
                                </h3>
                                <?php if ($descServ !== ''): ?>
                                    <p class="small text-muted mb-3">
                                        <?= htmlspecialchars($descServ, ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                <?php endif; ?>
                                <p class="mb-3 small">
                                    <span class="me-3">
                                        <i class="bi bi-cash-coin" aria-hidden="true"></i>
                                        $<?= number_format($precio, 2); ?>
                                    </span>
                                    <span>
                                        <i class="bi bi-clock" aria-hidden="true"></i>
                                        <?= (int) $duracion; ?> min
                                    </span>
                                </p>
                                <?php if ($modoVistaPrevia): ?>
                                    <span class="btn btn-sm rounded-pill px-3 navbar-public__cta--disabled text-white">
                                        Disponible al publicar
                                    </span>
                                <?php else: ?>
                                    <a
                                        href="<?= htmlspecialchars($urlServicio, ENT_QUOTES, 'UTF-8'); ?>"
                                        class="btn btn-sm rounded-pill px-3 text-white"
                                        style="background:#657166;"
                                    >
                                        Agendar
                                    </a>
                                <?php endif; ?>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    </div>
</section>
