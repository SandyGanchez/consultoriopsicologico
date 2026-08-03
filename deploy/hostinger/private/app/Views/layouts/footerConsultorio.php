<?php

use App\Helpers\Helper;

?>

<footer class="consultorio-footer">

    <span>
        © <?= date('Y'); ?> PsicoMatch
    </span>

    <small>
        Panel del consultorio
    </small>

</footer>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>
<script
    src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/index.global.min.js">
</script>

<script
    src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/locales-all.global.min.js">
</script>
<script
    src="<?= Helper::baseUrl('assets/js/consultorio.js'); ?>">
</script>

<script
    src="<?= Helper::baseUrl('assets/js/notificaciones-campana.js'); ?>">
</script>

<?php if (!empty($cargarConfigJs)): ?>

    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""
    ></script>

    <script
        src="<?= Helper::baseUrl(
            'assets/js/consultorio-configuracion.js'
        ); ?>"
    ></script>

<?php endif; ?>

</body>

</html>