<?php

use App\Helpers\Helper;

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <?php
        $tituloPagina = trim((string) ($titulo ?? ''));
        if ($tituloPagina === '' && !empty($consultorio['NombreCons'])) {
            $tituloPagina = trim((string) $consultorio['NombreCons']);
        }
        if ($tituloPagina === '') {
            $tituloPagina = 'PsicoMatch';
        }
    ?>
    <title>
        <?= htmlspecialchars($tituloPagina, ENT_QUOTES, 'UTF-8'); ?>
    </title>

    <!-- Bootstrap -->

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <!-- Bootstrap Icons -->

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Google Fonts -->

    <link rel="preconnect"
          href="https://fonts.googleapis.com">

    <link rel="preconnect"
          href="https://fonts.gstatic.com"
          crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
          rel="stylesheet">

<link rel="stylesheet"
      href="<?= \App\Helpers\Helper::baseUrl('assets/css/style.css'); ?>">

<link rel="stylesheet"
      href="<?= \App\Helpers\Helper::baseUrl('assets/css/home.css'); ?>">

<link rel="stylesheet"
      href="<?= \App\Helpers\Helper::baseUrl('assets/css/navbar.css'); ?>">

<?php if (!empty($cargarMapaHome)): ?>

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""
    >

<?php endif; ?>
</head>

<body>