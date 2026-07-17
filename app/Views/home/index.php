<?php require __DIR__.'/hero.php'; ?>

<?php require __DIR__.'/caracteristicas.php'; ?>

<?php require __DIR__.'/servicios.php'; ?>

<?php require __DIR__.'/horarios.php'; ?>

<?php require __DIR__.'/informacion.php'; ?>

<?php require __DIR__.'/redes.php'; ?>
<?php require __DIR__ . '/especialistas.php'; ?>

<div class="container py-5">

    <h1 class="display-5 fw-bold">
        <?= $consultorio['NombreCons']; ?>
    </h1>

    <p class="lead">
        <?= $consultorio['Descripcion']; ?>
    </p>

    <hr>

    <h2 class="mb-4">
        Servicios
    </h2>

    <div class="row">

        <?php foreach($servicios as $servicio): ?>

            <div class="col-md-4 mb-3">

                <div class="card shadow-sm">

                    <div class="card-body">

                        <h5>
                            <?= $servicio['NombreServicio']; ?>
                        </h5>

                        <p>
                            <?= $servicio['Descripcion']; ?>
                        </p>

                    </div>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

</div>