<?php

use App\Helpers\Helper;

?>

<footer class="psychologist-footer">

    <span>
        © <?= date('Y'); ?>
        Consultorio Psicológico
    </span>

    <small>
        Panel del psicólogo
    </small>

</footer>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

<script
    src="<?= Helper::baseUrl('assets/js/app.js'); ?>">
</script>

<script
    src="<?= Helper::baseUrl('assets/js/psicologo.js'); ?>">
</script>

</body>

</html>