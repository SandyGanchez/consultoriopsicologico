<?php

use App\Core\Session;
use App\Helpers\Helper;

$busqueda = $busqueda ?? '';
$filtro = $filtro ?? 'todos';
$pacientes = $pacientes ?? [];
$totalPacientes = (int) ($totalPacientes ?? 0);
$csrf = Session::csrfToken();

$filtros = [
    'todos' => 'Todos',
    'proxima' => 'Con cita próxima',
    'atendidos' => 'Atendidos',
    'historicos' => 'Históricos'
];

$urlBase = Helper::baseUrl('psicologo/pacientes');

?>

<section class="psychologist-patients-page">

    <?php if (!empty($_SESSION['success'])): ?>

        <div class="alert alert-success">
            <?= htmlspecialchars((string) $_SESSION['success']); ?>
        </div>

        <?php unset($_SESSION['success']); ?>

    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars((string) $_SESSION['error']); ?>
        </div>

        <?php unset($_SESSION['error']); ?>

    <?php endif; ?>

    <?php if (!empty($_SESSION['warning'])): ?>

        <div class="alert alert-warning">
            <?= htmlspecialchars((string) $_SESSION['warning']); ?>
        </div>

        <?php unset($_SESSION['warning']); ?>

    <?php endif; ?>

    <header class="psychologist-patients-header">

        <div>

            <h1>Mis pacientes</h1>

            <p>
                Consulta los pacientes que han agendado una cita contigo.
            </p>

        </div>

        <div class="d-flex align-items-center gap-3 flex-wrap">

            <a
                href="<?= Helper::baseUrl('psicologo/pacientes/registrar'); ?>"
                class="btn psychologist-patients-primary-btn"
            >
                Registrar paciente y agendar
            </a>

            <div class="psychologist-patients-total">

                <span><?= $totalPacientes; ?></span>

                paciente<?= $totalPacientes === 1 ? '' : 's'; ?>

            </div>

        </div>

    </header>

    <form
        method="GET"
        action="<?= htmlspecialchars($urlBase); ?>"
        class="psychologist-patients-toolbar"
    >

        <div class="psychologist-patients-search">

            <i class="bi bi-search" aria-hidden="true"></i>

            <input
                type="search"
                name="q"
                value="<?= htmlspecialchars($busqueda); ?>"
                placeholder="Buscar por nombre, correo o teléfono"
                aria-label="Buscar pacientes"
            >

        </div>

        <input
            type="hidden"
            name="filtro"
            value="<?= htmlspecialchars($filtro); ?>"
            id="filtroPacientesHidden"
        >

        <button
            type="submit"
            class="btn psychologist-patients-search-btn"
        >
            Buscar
        </button>

    </form>

    <div
        class="psychologist-patients-filters"
        role="group"
        aria-label="Filtros de pacientes"
    >

        <?php foreach ($filtros as $clave => $etiqueta): ?>

            <?php
            $params = [];

            if ($busqueda !== '') {
                $params['q'] = $busqueda;
            }

            if ($clave !== 'todos') {
                $params['filtro'] = $clave;
            }

            $href = $urlBase;

            if ($params !== []) {
                $href .= '?' . http_build_query($params);
            }

            $activo = $filtro === $clave ? ' is-active' : '';
            ?>

            <a
                href="<?= htmlspecialchars($href); ?>"
                class="psychologist-patients-filter-chip<?= $activo; ?>"
            >
                <?= htmlspecialchars($etiqueta); ?>
            </a>

        <?php endforeach; ?>

    </div>

    <?php if ($pacientes === []): ?>

        <div class="psychologist-patients-empty">

            <i class="bi bi-people" aria-hidden="true"></i>

            <h2>Aún no tienes pacientes relacionados</h2>

            <p>
                Cuando un paciente agenda una cita contigo, aparecerá aquí.
                También puedes registrar un paciente nuevo y su primera cita.
            </p>

            <div class="d-flex gap-2 flex-wrap justify-content-center">
                <a
                    href="<?= Helper::baseUrl('psicologo/pacientes/registrar'); ?>"
                    class="btn psychologist-patients-primary-btn"
                >
                    Registrar paciente y agendar
                </a>
                <a
                    href="<?= Helper::baseUrl('psicologo/agenda'); ?>"
                    class="btn psychologist-patients-secondary-btn"
                >
                    Ir a mi agenda
                </a>
            </div>

        </div>

    <?php else: ?>

        <div class="psychologist-patients-grid">

            <?php foreach ($pacientes as $paciente): ?>

                <?php
                $clvPac = (string) ($paciente['ClvPac'] ?? '');
                $nombre = trim((string) ($paciente['NombrePaciente'] ?? ''));
                $estado = (string) ($paciente['EstadoRelacion'] ?? 'HISTORICO');
                $estadoClase = strtolower(str_replace('_', '-', $estado));
                $fotoUrl = '';

                if (!empty($paciente['TieneFoto']) && !empty($paciente['FotoArchivo'])) {
                    $fotoUrl = Helper::baseUrl(
                        'uploads/perfiles/' . $paciente['FotoArchivo']
                    );
                }

                $proximaTexto = 'Sin próxima cita';

                if (!empty($paciente['ProximaFecha'])) {
                    $proximaTexto =
                        date(
                            'd/m/Y',
                            strtotime((string) $paciente['ProximaFecha'])
                        )
                        . (
                            !empty($paciente['ProximaHora'])
                                ? ' · ' . substr(
                                    (string) $paciente['ProximaHora'],
                                    0,
                                    5
                                )
                                : ''
                        );
                }

                $ultimaTexto = 'Sin citas pasadas';

                if (!empty($paciente['UltimaFecha'])) {
                    $ultimaTexto =
                        date(
                            'd/m/Y',
                            strtotime((string) $paciente['UltimaFecha'])
                        )
                        . (
                            !empty($paciente['UltimaHora'])
                                ? ' · ' . substr(
                                    (string) $paciente['UltimaHora'],
                                    0,
                                    5
                                )
                                : ''
                        );
                }
                ?>

                <article class="psychologist-patient-card">

                    <div class="psychologist-patient-card__top">

                        <div class="psychologist-patient-avatar">

                            <?php if ($fotoUrl !== ''): ?>

                                <img
                                    src="<?= htmlspecialchars($fotoUrl); ?>"
                                    alt="Foto de <?= htmlspecialchars($nombre); ?>"
                                >

                            <?php else: ?>

                                <span>
                                    <?= htmlspecialchars(
                                        (string) ($paciente['Iniciales'] ?? 'P')
                                    ); ?>
                                </span>

                            <?php endif; ?>

                        </div>

                        <div class="psychologist-patient-card__identity">

                            <h2>
                                <?= htmlspecialchars($nombre); ?>
                            </h2>

                            <span
                                class="psychologist-patient-status psychologist-patient-status--<?= htmlspecialchars($estadoClase); ?>"
                            >
                                <?= htmlspecialchars(
                                    (string) ($paciente['EstadoEtiqueta'] ?? '')
                                ); ?>
                            </span>

                        </div>

                    </div>

                    <div class="psychologist-patient-contact">

                        <p>
                            <i class="bi bi-envelope" aria-hidden="true"></i>
                            <?= htmlspecialchars(
                                (string) ($paciente['CorreoUsu'] ?? '—')
                            ); ?>
                        </p>

                        <p>
                            <i class="bi bi-telephone" aria-hidden="true"></i>
                            <?= htmlspecialchars(
                                (string) ($paciente['TelefonoUsu'] ?? '—')
                            ); ?>
                        </p>

                    </div>

                    <div class="psychologist-patient-appointments">

                        <div>
                            <span>Próxima cita</span>
                            <strong>
                                <?= htmlspecialchars($proximaTexto); ?>
                            </strong>
                        </div>

                        <div>
                            <span>Última cita</span>
                            <strong>
                                <?= htmlspecialchars($ultimaTexto); ?>
                            </strong>
                        </div>

                        <div>
                            <span>Total de citas</span>
                            <strong>
                                <?= (int) ($paciente['TotalCitas'] ?? 0); ?>
                            </strong>
                        </div>

                    </div>

                    <div class="psychologist-patient-actions">

                        <a
                            href="<?= Helper::baseUrl(
                                'psicologo/pacientes/ver/' .
                                rawurlencode($clvPac)
                            ); ?>"
                            class="btn psychologist-patients-secondary-btn"
                        >
                            Ver paciente
                        </a>

                        <?php if ($estado === 'CUENTA_PENDIENTE' && !empty($paciente['ClvUsu'])): ?>

                            <form
                                method="POST"
                                action="<?= Helper::baseUrl(
                                    'psicologo/pacientes/reenviar-activacion'
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
                                        (string) $paciente['ClvUsu'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>"
                                >
                                <button
                                    type="submit"
                                    class="btn psychologist-patients-primary-btn"
                                >
                                    Reenviar activación
                                </button>
                            </form>

                        <?php else: ?>

                        <a
                            href="<?= Helper::baseUrl(
                                'psicologo/agenda?paciente=' .
                                rawurlencode($clvPac)
                            ); ?>"
                            class="btn psychologist-patients-primary-btn"
                        >
                            Agendar cita
                        </a>

                        <?php endif; ?>

                        <a
                            href="<?= Helper::baseUrl(
                                'psicologo/pacientes/ver/' .
                                rawurlencode($clvPac)
                            ); ?>#historial-citas"
                            class="btn psychologist-patients-link-btn"
                        >
                            Ver citas
                        </a>

                        <a
                            href="<?= Helper::baseUrl(
                                'psicologo/pacientes/ver/' .
                                rawurlencode($clvPac) .
                                '/expediente'
                            ); ?>"
                            class="btn psychologist-patients-link-btn"
                        >
                            Expediente clínico
                        </a>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>
