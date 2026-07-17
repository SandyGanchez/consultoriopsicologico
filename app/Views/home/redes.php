<section class="py-5" style="background:#DAEBE3;" id="redes">

    <div class="container text-center">

        <h2 class="fw-bold mb-4">

            Síguenos

        </h2>

        <?php

        $iconos = [
            'Facebook'=>'bi-facebook',
            'Instagram'=>'bi-instagram',
            'WhatsApp'=>'bi-whatsapp',
            'TikTok'=>'bi-tiktok',
            'Página Web'=>'bi-globe'
        ];

        ?>

        <?php foreach($redes as $red): ?>

            <a
                href="<?= htmlspecialchars($red['URLRed']); ?>"
                target="_blank"
                class="btn btn-light rounded-circle mx-2">

                <i class="bi <?= $iconos[$red['TipoRed']] ?? 'bi-globe'; ?>"></i>

            </a>

        <?php endforeach; ?>

    </div>

</section>