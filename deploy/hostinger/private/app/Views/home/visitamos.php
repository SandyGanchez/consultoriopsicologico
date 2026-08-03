<?php

use App\Helpers\Helper;

$consultorio = $consultorio ?? null;
$modoVistaPrevia = !empty($modoVistaPrevia);

if (!$consultorio) {
    return;
}

$logoUrl = Helper::logotipoConsultorioUrl(
    $consultorio['LogotipoCons'] ?? ''
);

$direccionLegible = Helper::direccionPublicaLegible($consultorio);

$referencia = trim((string) ($consultorio['ReferenciaDir'] ?? ''));

$telefono = trim((string) ($consultorio['TelefonoCons'] ?? ''));

$correo = trim((string) ($consultorio['CorreoElectronico'] ?? ''));

$limiteHoras = (int) ($consultorio['LimiteCancHoras'] ?? 0);

$politicaCancelacion =
    Helper::textoPoliticaCancelacionPublica($limiteHoras);

$mapaDisponible = !empty($mapaDisponible);

$caracteristicas = $caracteristicas ?? [];

$horariosResumen = array_filter(
    $horarios ?? [],
    static function (array $dia): bool {
        return ($dia['EstatusHorario'] ?? '') === 'ACTIVO';
    }
);

$mapaDatos = [
    'nombre' => $consultorio['NombreCons'] ?? '',
    'direccion' => Helper::direccionPublicaResumida($consultorio),
    'telefono' => $telefono,
    'latitud' => $mapaDisponible
        ? (float) $consultorio['LatitudDir']
        : null,
    'longitud' => $mapaDisponible
        ? (float) $consultorio['LongitudDir']
        : null
];

?>

<section
    class="public-clinic-info py-5"
    id="ubicacion"
    aria-labelledby="encuentranos-heading"
>

    <div class="container">

        <header class="text-center mb-5">

            <span class="public-clinic-info__eyebrow">
                Visítanos
            </span>

            <h2
                id="encuentranos-heading"
                class="public-clinic-info__title"
            >
                Encuéntranos
            </h2>

            <p class="public-clinic-info__intro mx-auto">
                Conoce nuestra ubicación, horarios y formas de contacto.
            </p>

        </header>

        <div class="row g-4 align-items-start">

            <div class="col-lg-6 public-clinic-location" id="nosotros">

                <article class="public-address-card" id="contacto">

                    <?php if ($logoUrl !== ''): ?>

                        <img
                            src="<?= htmlspecialchars($logoUrl); ?>"
                            alt="Logotipo de <?= htmlspecialchars(
                                $consultorio['NombreCons'] ?? ''
                            ); ?>"
                            class="public-clinic-info__logo mb-3"
                        >

                    <?php endif; ?>

                    <h3 class="public-address-card__name">
                        <?= htmlspecialchars(
                            $consultorio['NombreCons'] ?? ''
                        ); ?>
                    </h3>

                    <?php if (
                        !empty($consultorio['Slogan'])
                    ): ?>

                        <p class="public-address-card__slogan">
                            <?= htmlspecialchars(
                                $consultorio['Slogan']
                            ); ?>
                        </p>

                    <?php endif; ?>

                    <?php if (
                        !empty($consultorio['Descripcion'])
                    ): ?>

                        <p class="public-address-card__description">
                            <?= htmlspecialchars(
                                $consultorio['Descripcion']
                            ); ?>
                        </p>

                    <?php elseif ($modoVistaPrevia): ?>

                        <p class="public-address-card__description fst-italic text-muted">
                            Agrega una descripción del consultorio.
                        </p>

                    <?php endif; ?>

                    <ul class="public-contact-list list-unstyled">

                        <?php if ($direccionLegible !== ''): ?>

                            <li>

                                <i
                                    class="bi bi-geo-alt-fill"
                                    aria-hidden="true"
                                ></i>

                                <span>
                                    <?= htmlspecialchars(
                                        $direccionLegible
                                    ); ?>
                                </span>

                            </li>

                        <?php elseif ($modoVistaPrevia): ?>

                            <li>

                                <i
                                    class="bi bi-geo-alt-fill"
                                    aria-hidden="true"
                                ></i>

                                <span class="fst-italic text-muted">
                                    Configura la dirección.
                                </span>

                            </li>

                        <?php endif; ?>

                        <?php if ($referencia !== ''): ?>

                            <li>

                                <i
                                    class="bi bi-signpost-split-fill"
                                    aria-hidden="true"
                                ></i>

                                <span>
                                    <?= htmlspecialchars($referencia); ?>
                                </span>

                            </li>

                        <?php endif; ?>

                        <?php if ($telefono !== ''): ?>

                            <li>

                                <i
                                    class="bi bi-telephone-fill"
                                    aria-hidden="true"
                                ></i>

                                <a href="tel:<?= htmlspecialchars(
                                    preg_replace('/\D+/', '', $telefono)
                                ); ?>">
                                    <?= htmlspecialchars($telefono); ?>
                                </a>

                            </li>

                        <?php endif; ?>

                        <?php if ($correo !== ''): ?>

                            <li>

                                <i
                                    class="bi bi-envelope-fill"
                                    aria-hidden="true"
                                ></i>

                                <a href="mailto:<?= htmlspecialchars(
                                    $correo
                                ); ?>">
                                    <?= htmlspecialchars($correo); ?>
                                </a>

                            </li>

                        <?php endif; ?>

                    </ul>

                    <?php if ($horariosResumen !== []): ?>

                        <div class="public-schedule-list mt-4">

                            <h4 class="public-schedule-list__title">
                                Horario resumido
                            </h4>

                            <ul class="list-unstyled mb-0">

                                <?php foreach (
                                    $horariosResumen as $dia
                                ): ?>

                                    <li class="public-schedule-list__item">

                                        <span>
                                            <?= htmlspecialchars(
                                                $dia['Etiqueta']
                                                ?? Helper::etiquetaDiaHorario(
                                                    $dia['DiaSemana'] ?? ''
                                                )
                                            ); ?>
                                        </span>

                                        <span>
                                            <?= htmlspecialchars(
                                                Helper::formatearHoraPublica(
                                                    $dia['HoraInicio'] ?? ''
                                                )
                                            ); ?>
                                            –
                                            <?= htmlspecialchars(
                                                Helper::formatearHoraPublica(
                                                    $dia['HoraFin'] ?? ''
                                                )
                                            ); ?>
                                        </span>

                                    </li>

                                <?php endforeach; ?>

                            </ul>

                        </div>

                    <?php endif; ?>

                    <div class="public-cancellation-policy mt-4">

                        <h4 class="public-cancellation-policy__title">
                            Política de cancelación
                        </h4>

                        <p class="mb-0">
                            <?= htmlspecialchars($politicaCancelacion); ?>
                        </p>

                    </div>

                </article>

            </div>

            <div class="col-lg-6">

                <?php if ($mapaDisponible): ?>

                    <div
                        id="publicClinicMap"
                        class="public-clinic-map"
                        role="application"
                        aria-label="Mapa con la ubicación del consultorio"
                    ></div>

                    <script
                        type="application/json"
                        id="publicClinicMapData"
                    ><?= json_encode(
                        $mapaDatos,
                        JSON_HEX_TAG
                        | JSON_HEX_APOS
                        | JSON_HEX_QUOT
                        | JSON_HEX_AMP
                    ); ?></script>

                    <div class="public-location-actions">

                        <button
                            type="button"
                            class="btn public-location-actions__btn"
                            id="btnPublicUsarUbicacion"
                        >
                            <i class="bi bi-crosshair"></i>
                            Usar mi ubicación
                        </button>

                        <a
                            href="https://www.openstreetmap.org/directions?to=<?= rawurlencode(
                                (string) $mapaDatos['latitud']
                                . ','
                                . (string) $mapaDatos['longitud']
                            ); ?>"
                            class="btn public-location-actions__btn"
                            id="btnPublicComoLlegar"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <i class="bi bi-sign-turn-right-fill"></i>
                            Cómo llegar
                        </a>

                    </div>

                    <p
                        id="publicMapMessage"
                        class="public-map-message d-none"
                        role="status"
                    ></p>

                <?php else: ?>

                    <div class="public-clinic-map public-clinic-map--fallback">

                        <p class="mb-0">
                            La ubicación en el mapa aún no está disponible.
                        </p>

                        <?php if ($direccionLegible !== ''): ?>

                            <p class="mt-3 mb-0">
                                <?= htmlspecialchars($direccionLegible); ?>
                            </p>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>

        <?php if ($caracteristicas !== []): ?>

            <div class="public-clinic-features mt-5">

                <h3 class="public-clinic-features__title text-center">
                    ¿Por qué elegirnos?
                </h3>

                <div class="row g-3 justify-content-center">

                    <?php foreach ($caracteristicas as $car): ?>

                        <div class="col-md-6 col-lg-4">

                            <article class="public-clinic-features__item">

                                <i
                                    class="bi <?= htmlspecialchars(
                                        Helper::iconoBootstrapSeguro(
                                            $car['Icono'] ?? ''
                                        )
                                    ); ?>"
                                    aria-hidden="true"
                                ></i>

                                <h4>
                                    <?= htmlspecialchars(
                                        $car['Titulo'] ?? ''
                                    ); ?>
                                </h4>

                                <p class="mb-0">
                                    <?= htmlspecialchars(
                                        $car['Descripcion'] ?? ''
                                    ); ?>
                                </p>

                            </article>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        <?php endif; ?>

    </div>

</section>
