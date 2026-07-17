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
        href="<?= Helper::baseUrl(
            'assets/css/psicologo.css'
        ); ?>"
    >

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

</body>

</html>