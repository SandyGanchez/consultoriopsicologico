<?php

$modoVistaPrevia = !empty($modoVistaPrevia);
$bannerVistaPrevia = trim((string) ($bannerVistaPrevia ?? ''));

if ($modoVistaPrevia && $bannerVistaPrevia === '') {
    $bannerVistaPrevia =
        'Vista previa privada. Esta página todavía no es visible al público.';
}

?>

<?php if ($modoVistaPrevia && $bannerVistaPrevia !== ''): ?>
    <div
        class="alert alert-warning text-center rounded-0 mb-0 border-0"
        role="status"
        style="background:#FFF3CD;color:#664d03;"
    >
        <i class="bi bi-eye me-1" aria-hidden="true"></i>
        <?= htmlspecialchars($bannerVistaPrevia, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php require __DIR__.'/hero.php'; ?>

<?php require __DIR__.'/servicios.php'; ?>

<?php require __DIR__.'/horarios.php'; ?>

<?php require __DIR__.'/visitamos.php'; ?>

<?php require __DIR__.'/redes.php'; ?>

<?php require __DIR__ . '/especialistas.php'; ?>

<?php if (!empty($filtrosActivos)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var seccion = document.getElementById('especialistas');
    if (seccion) {
        seccion.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});
</script>
<?php endif; ?>
