<?php

use App\Helpers\Helper;

$erroresHorario = is_array($errores ?? null) ? $errores : [];
$horarioOld = is_array($horarioOld ?? null) ? $horarioOld : [];

?>

<section class="consultorio-horario">

    <div class="consultorio-page-header">
        <div>
            <span class="consultorio-page-eyebrow">
                Operación del consultorio
            </span>
            <h1>Horario general</h1>
            <p>
                Consulta y administra los días y horarios de atención
                del establecimiento.
            </p>
        </div>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="status">
            <?= htmlspecialchars((string) $success); ?>
            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>
        </div>
        <div hidden data-pm-toast="success"><?= htmlspecialchars((string) $success); ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars((string) $error); ?>
            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>
        </div>
        <div hidden data-pm-toast="error"><?= htmlspecialchars((string) $error); ?></div>
    <?php endif; ?>

    <div class="consultorio-dashboard-panel">
        <p class="small text-muted mb-3">
            Los siete días se guardan juntos. Un día inactivo no exige horas;
            si hay disponibilidades activas incompatibles, el sistema lo indicará.
        </p>

        <?php
        $returnTo = 'horario';
        $variante = 'tabla';
        require __DIR__ . '/../partials/form-horario-semana.php';
        ?>
    </div>

</section>

<script>
(function () {
    var form = document.getElementById('formHorarioSemana');
    if (!form) {
        return;
    }
    form.addEventListener('submit', function () {
        var btn = form.querySelector('[data-horario-submit]');
        if (!btn || btn.disabled) {
            return;
        }
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        btn.textContent = 'Guardando…';
    });
})();
</script>
