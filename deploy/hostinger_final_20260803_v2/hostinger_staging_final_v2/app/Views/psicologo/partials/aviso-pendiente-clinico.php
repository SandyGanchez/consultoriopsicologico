<?php

/**
 * Aviso operativo de pendiente clínico (sin contenido clínico).
 *
 * Variables esperadas:
 * - $tipo: asistencia|historia|seguimiento|datos
 * - $titulo
 * - $mensaje (opcional)
 * - $etiquetaBoton
 * - $urlBoton
 * - $urlSecundaria / $etiquetaSecundaria (opcionales)
 */

use App\Helpers\Helper;

$tipo = (string) ($tipo ?? 'historia');
$titulo = (string) ($titulo ?? '');
$mensaje = trim((string) ($mensaje ?? ''));
$etiquetaBoton = (string) ($etiquetaBoton ?? '');
$urlBoton = (string) ($urlBoton ?? '');
$etiquetaSecundaria = (string) ($etiquetaSecundaria ?? '');
$urlSecundaria = (string) ($urlSecundaria ?? '');

$iconos = [
    'asistencia' => 'bi-clock-history',
    'historia' => 'bi-journal-medical',
    'seguimiento' => 'bi-clipboard2-pulse',
    'datos' => 'bi-person-lines-fill'
];
$icono = $iconos[$tipo] ?? 'bi-info-circle';
$modificador = 'psi-pendiente-aviso--' . preg_replace('/[^a-z]/', '', $tipo);

?>

<div
    class="psi-pendiente-aviso <?= htmlspecialchars($modificador, ENT_QUOTES, 'UTF-8'); ?>"
    role="status"
>
    <div class="psi-pendiente-aviso__body">
        <span class="psi-pendiente-aviso__icon" aria-hidden="true">
            <i class="bi <?= htmlspecialchars($icono, ENT_QUOTES, 'UTF-8'); ?>"></i>
        </span>
        <div>
            <strong><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?></strong>
            <?php if ($mensaje !== ''): ?>
                <p class="mb-0 mt-1"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <div class="psi-pendiente-aviso__acciones">
        <?php if ($urlBoton !== '' && $etiquetaBoton !== ''): ?>
            <a
                href="<?= htmlspecialchars(
                    str_starts_with($urlBoton, 'http')
                        ? $urlBoton
                        : Helper::baseUrl($urlBoton),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
                class="btn psi-pendiente-aviso__btn"
            >
                <?= htmlspecialchars($etiquetaBoton, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        <?php endif; ?>
        <?php if ($urlSecundaria !== '' && $etiquetaSecundaria !== ''): ?>
            <a
                href="<?= htmlspecialchars(
                    str_starts_with($urlSecundaria, 'http')
                        ? $urlSecundaria
                        : Helper::baseUrl($urlSecundaria),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
                class="btn psi-pendiente-aviso__btn-sec"
            >
                <?= htmlspecialchars($etiquetaSecundaria, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        <?php endif; ?>
    </div>
</div>
