<?php

use App\Helpers\Helper;

$titulo = $titulo ?? 'Panel del especialista';

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars($titulo); ?>
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= Helper::assetUrl(
            'assets/css/psicologo.css'
        ); ?>"
    >

    <link
        rel="stylesheet"
        href="<?= Helper::assetUrl(
            'assets/css/notificaciones-campana.css'
        ); ?>"
    >

    <?php if (!empty($cargarAgendaPsicologo)): ?>

        <link
            rel="stylesheet"
            href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/index.global.min.css"
        >

        <link
            rel="stylesheet"
            href="<?= Helper::baseUrl(
                'assets/css/psicologo-agenda.css'
            ); ?>"
        >

    <?php endif; ?>

    <?php if (!empty($cargarPacientesPsicologo)): ?>

        <link
            rel="stylesheet"
            href="<?= Helper::baseUrl(
                'assets/css/psicologo-pacientes.css'
            ); ?>"
        >

    <?php endif; ?>

    <?php if (!empty($cargarServiciosPsicologoCss)): ?>

        <link
            rel="stylesheet"
            href="<?= Helper::baseUrl(
                'assets/css/psicologo-servicios.css'
            ); ?>"
        >

    <?php endif; ?>

    <?php if (!empty($cargarConfiguracionPsicologo)): ?>

        <link
            rel="stylesheet"
            href="<?= Helper::baseUrl(
                'assets/css/psicologo-configuracion.css'
            ); ?>"
        >

    <?php endif; ?>

    <?php if (!empty($cargarExpedientePsicologo)): ?>

        <link
            rel="stylesheet"
            href="<?= Helper::baseUrl(
                'assets/css/psicologo-expediente.css'
            ); ?>"
        >

    <?php endif; ?>

</head>

<body>

    <div class="psicologo-layout">

        <?php require __DIR__ .
            '/../psicologo/partials/sidebar.php'; ?>

        <main class="psicologo-main">

            <?php require __DIR__ .
                '/../psicologo/partials/navbar.php'; ?>

           <div class="psicologo-content">

    <?php require $content; ?>

</div>

            <?php require __DIR__ .
                '/../psicologo/partials/footer.php'; ?>

        </main>

    </div>

    <div
        class="psicologo-sidebar-overlay"
        id="psicologoSidebarOverlay"
    ></div>

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    ></script>

    <script
        src="<?= Helper::baseUrl(
            'assets/js/psicologo.js'
        ); ?>"
    ></script>

    <script
        src="<?= Helper::baseUrl(
            'assets/js/notificaciones-campana.js'
        ); ?>"
    ></script>

    <?php if (!empty($cargarAgendaPsicologo)): ?>

        <script
            src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/index.global.min.js"
        ></script>

        <script
            src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/locales-all.global.min.js"
        ></script>

        <script
            src="<?= Helper::baseUrl(
                'assets/js/psicologo-agenda.js'
            ); ?>"
        ></script>

    <?php endif; ?>

    <?php if (!empty($cargarConfiguracionPsicologo)): ?>

        <script
            src="<?= Helper::baseUrl(
                'assets/js/psicologo-configuracion.js'
            ); ?>"
        ></script>

        <script
            src="<?= Helper::baseUrl(
                'assets/js/cambio-correo-guard.js'
            ); ?>"
        ></script>

    <?php endif; ?>

    <?php if (!empty($cargarExpedientePsicologo)): ?>

        <script
            src="<?= Helper::baseUrl(
                'assets/js/psicologo-expediente.js'
            ); ?>"
        ></script>

    <?php endif; ?>

</body>

</html>