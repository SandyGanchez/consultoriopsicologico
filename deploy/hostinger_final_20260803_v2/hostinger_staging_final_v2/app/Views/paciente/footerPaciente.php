<footer class="footer-paciente">

    <small>

        © <?= date('Y'); ?> Consultorio Psicológico
        <span aria-hidden="true"> · </span>
        <a href="<?= \App\Helpers\Helper::baseUrl('aviso-de-privacidad'); ?>">
            Aviso de privacidad
        </a>
        <span aria-hidden="true"> · </span>
        <a href="<?= \App\Helpers\Helper::baseUrl('paciente/configuracion'); ?>#privacidad">
            Privacidad y datos personales
        </a>
        <span aria-hidden="true"> · </span>
        Sistema: PsicoMatch

    </small>

</footer>