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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>