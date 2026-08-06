<?php

use App\Helpers\Helper;

$escaparAviso = static function ($valor): string {
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
};

$perfilIncompleto = !empty($perfilIncompleto);
$seccionesPerfilPendientes = is_array($seccionesPerfilPendientes ?? null)
    ? array_values(array_filter(
        $seccionesPerfilPendientes,
        static fn($s): bool => trim((string) $s) !== ''
    ))
    : [];
$clavesSeccionesPendientes = is_array($clavesSeccionesPendientes ?? null)
    ? $clavesSeccionesPendientes
    : [];
$ctaPerfil = trim((string) ($ctaPerfil ?? 'paciente/perfil/editar'));
$mostrarNotaContacto = in_array('CONTACTO', $clavesSeccionesPendientes, true)
    || in_array('Información de contacto', $seccionesPerfilPendientes, true);

if (!$perfilIncompleto) {
    return;
}

?>

<aside
    class="paciente-perfil-aviso"
    role="status"
    aria-labelledby="paciente-perfil-aviso-titulo"
>
    <div class="paciente-perfil-aviso-icon" aria-hidden="true">
        <i class="bi bi-info-circle"></i>
    </div>

    <div class="paciente-perfil-aviso-body">
        <h2 id="paciente-perfil-aviso-titulo">
            Completa tu información
        </h2>

        <p>
            Faltan algunos datos importantes en tu perfil.
        </p>

        <?php if ($seccionesPerfilPendientes !== []): ?>
            <ul class="paciente-perfil-aviso-secciones">
                <?php foreach ($seccionesPerfilPendientes as $seccion): ?>
                    <li><?= $escaparAviso($seccion); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($mostrarNotaContacto): ?>
            <p class="paciente-perfil-aviso-nota">
                El teléfono se actualiza en
                <a href="<?= $escaparAviso(Helper::baseUrl('paciente/configuracion')); ?>">
                    Configuración
                </a>.
            </p>
        <?php endif; ?>

        <div class="paciente-perfil-aviso-actions">
            <a
                class="paciente-perfil-aviso-btn"
                href="<?= $escaparAviso(Helper::baseUrl($ctaPerfil)); ?>"
            >
                Completar perfil
            </a>
        </div>
    </div>
</aside>
