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

</body>

</html>