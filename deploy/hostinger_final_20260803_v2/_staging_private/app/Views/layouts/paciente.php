<?php

use App\Helpers\Helper;

$titulo = $titulo ?? 'Panel del paciente';

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title><?= htmlspecialchars($titulo); ?></title>

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
        href="<?= Helper::baseUrl('assets/css/paciente.css'); ?>"
    >

    <?php require __DIR__ . '/../partials/apariencia-head.php'; ?>

    <link
        rel="stylesheet"
        href="<?= Helper::baseUrl('assets/css/notificaciones-campana.css'); ?>"
    >

    <?php if (!empty($cargarDashboardCss)): ?>

        <link
            rel="stylesheet"
            href="<?= Helper::baseUrl('assets/css/paciente-dashboard.css'); ?>"
        >

    <?php endif; ?>

    <?php if (!empty($cargarAgendarCss)): ?>

        <link
            rel="stylesheet"
            href="<?= Helper::baseUrl('assets/css/paciente-agendar.css'); ?>"
        >

    <?php endif; ?>

    <?php if (!empty($cargarPerfilCss)): ?>

        <link
            rel="stylesheet"
            href="<?= Helper::baseUrl('assets/css/paciente-perfil.css'); ?>"
        >

    <?php endif; ?>

    <?php if (!empty($cargarCitasCss)): ?>

        <link
            rel="stylesheet"
            href="<?= Helper::baseUrl('assets/css/paciente-citas.css'); ?>"
        >

    <?php endif; ?>

    <?php if (!empty($cargarConfiguracionCss)): ?>

        <link
            rel="stylesheet"
            href="<?= Helper::baseUrl('assets/css/paciente-configuracion.css'); ?>"
        >

    <?php endif; ?>

</head>

<body>

<div class="paciente-layout">

    <?php require __DIR__ . '/../paciente/sidebarPaciente.php'; ?>

    <main class="paciente-main">

        <?php require __DIR__ . '/../paciente/navbarPaciente.php'; ?>

        <div class="paciente-content">

            <?php require $content; ?>

        </div>

        <?php require __DIR__ . '/../paciente/footerPaciente.php'; ?>

    </main>

</div>

<div
    class="paciente-sidebar-overlay"
    id="pacienteSidebarOverlay"
></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="<?= Helper::baseUrl('assets/js/paciente.js'); ?>"></script>

<script src="<?= Helper::baseUrl('assets/js/notificaciones-campana.js'); ?>"></script>
<script src="<?= Helper::baseUrl('assets/js/apariencia.js'); ?>"></script>
<script src="<?= Helper::baseUrl('assets/js/pm-toasts.js'); ?>"></script>

<?php if (!empty($cargarAgendarJs)): ?>

<script src="<?= Helper::baseUrl('assets/js/paciente-agendar.js'); ?>"></script>

<?php endif; ?>

<?php if (!empty($cargarConfiguracionCss)): ?>

<script src="<?= Helper::baseUrl('assets/js/cambio-correo-guard.js'); ?>"></script>

<?php endif; ?>

</body>

</html>
