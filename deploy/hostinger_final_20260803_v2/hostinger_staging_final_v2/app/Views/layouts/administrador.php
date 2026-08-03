<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        <?= htmlspecialchars(
            $titulo ?? 'Panel administrativo',
            ENT_QUOTES,
            'UTF-8'
        ); ?>
    </title>

    <link
        rel="stylesheet"
        href="<?= \App\Helpers\Helper::baseUrl(
            'assets/css/bootstrap.min.css'
        ); ?>"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >
</head>

<body class="bg-light">

    <?php require __DIR__ . '/navbar_admin.php'; ?>

    <main>
        <?= $contenido ?>
    </main>

    <script
        src="<?= \App\Helpers\Helper::baseUrl(
            'assets/js/bootstrap.bundle.min.js'
        ); ?>"
    ></script>

</body>

</html>