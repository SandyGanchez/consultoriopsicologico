<?php

use App\Helpers\Helper;

$nombrePagina = $titulo ?? 'Panel del consultorio';

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        <?= htmlspecialchars($nombrePagina); ?>
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
    href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/index.global.min.css"
>
    <link
        rel="stylesheet"
        href="<?= Helper::baseUrl('assets/css/style.css'); ?>"
    >

    <link
        rel="stylesheet"
        href="<?= Helper::baseUrl('assets/css/consultorio.css'); ?>"
    >

    <link
        rel="stylesheet"
        href="<?= Helper::baseUrl('assets/css/notificaciones-campana.css'); ?>"
    >

    <?php if (!empty($cargarConfigCss)): ?>

        <link
            rel="stylesheet"
            href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
            integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
            crossorigin=""
        >

        <link
            rel="stylesheet"
            href="<?= Helper::baseUrl(
                'assets/css/consultorio-configuracion.css'
            ); ?>"
        >

    <?php endif; ?>

    <?php if (!empty($cargarServiciosCss)): ?>

        <link
            rel="stylesheet"
            href="<?= Helper::baseUrl(
                'assets/css/consultorio-servicios.css'
            ); ?>"
        >

    <?php endif; ?>

</head>

<body>