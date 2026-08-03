<footer class="py-4 mt-5 bg-light">

    <div class="container text-center">

        <p class="mb-0">

            © <?php echo date('Y'); ?>

            Consultorio Psicológico

        </p>

    </div>

</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= \App\Helpers\Helper::baseUrl('assets/js/app.js'); ?>"></script>
<script src="<?= \App\Helpers\Helper::assetUrl('assets/js/navbar-public.js'); ?>"></script>

<?php if (!empty($cargarMapaHome)): ?>

    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""
    ></script>

    <script src="<?= \App\Helpers\Helper::baseUrl(
        'assets/js/home-ubicacion.js'
    ); ?>"></script>

<?php endif; ?>

</body>

</html>