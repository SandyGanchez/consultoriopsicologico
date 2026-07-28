<?php

$usuario = $_SESSION['usuario'] ?? [];

$nombre = trim(
    ($usuario['NombrePer'] ?? '') . ' ' .
    ($usuario['ApPatPer'] ?? '')
);

?>

<nav class="navbar-paciente">

    <div>

        <h4 class="m-0">
            Bienvenido, <?= htmlspecialchars($nombre); ?>
        </h4>

        <small class="text-muted">
            Esperamos que tengas un excelente día.
        </small>

    </div>

    <div class="navbar-icons">

        <button class="btn btn-light position-relative">

            <i class="bi bi-bell-fill"></i>

            <span class="notification-badge">
                0
            </span>

        </button>

    </div>

</nav>