<?php

$erroresHorario = $erroresHorario ?? [];
$horarioOld = $horarioOld ?? [];
$returnTo = 'configuracion';
$variante = 'cards';

?>

<div
    class="settings-card clinic-schedule-card"
    id="horario-atencion"
>

    <div class="settings-card__header">
        <i class="bi bi-clock" aria-hidden="true"></i>
        <span>Horario de atención</span>
    </div>

    <p class="small text-muted mb-3">
        Los siete días se guardan juntos. Los cambios respetan las
        disponibilidades activas de los especialistas.
    </p>

    <?php require __DIR__ . '/../../partials/form-horario-semana.php'; ?>

</div>

<script>
(function () {
    var form = document.querySelector('#horario-atencion [data-horario-semana]');
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
