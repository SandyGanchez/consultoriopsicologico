<?php

require __DIR__ . '/headerConsultorio.php';

?>

<div class="consultorio-layout">

    <?php require __DIR__ . '/sidebarConsultorio.php'; ?>

    <div
        id="consultorioSidebarBackdrop"
        class="consultorio-sidebar-backdrop"
        hidden
    ></div>

    <div class="consultorio-main">

        <?php require __DIR__ . '/navbarConsultorio.php'; ?>

        <main class="consultorio-content">

            <?php require $content; ?>

        </main>

        <?php require __DIR__ . '/footerConsultorio.php'; ?>

    </div>

</div>