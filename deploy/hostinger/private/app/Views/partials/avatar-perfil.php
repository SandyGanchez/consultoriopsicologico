<?php

use App\Helpers\Helper;

/**
 * Avatar circular de perfil (foto o iniciales).
 *
 * Variables esperadas:
 * - array $usuarioAvatar (NombrePer, ApPatPer, ApMatPer, FotoPerfilPer)
 * - string $avatarClass (clase CSS del contenedor)
 * - string|null $avatarFallback (opcional, inicial por defecto)
 * - string|null $avatarImageClass (opcional, clase del img; no afecta paciente)
 * - string|null $avatarEmptyClass (opcional, clase extra sin foto)
 */

$usuarioAvatar = is_array($usuarioAvatar ?? null)
    ? $usuarioAvatar
    : [];

$avatarClass = trim((string) ($avatarClass ?? 'user-avatar'));
$avatarFallback = trim((string) ($avatarFallback ?? '?'));
$avatarImageClass = trim((string) ($avatarImageClass ?? ''));
$avatarEmptyClass = trim((string) ($avatarEmptyClass ?? ''));

$nombreCompleto = trim(implode(' ', array_filter([
    trim((string) ($usuarioAvatar['NombrePer'] ?? '')),
    trim((string) ($usuarioAvatar['ApPatPer'] ?? '')),
    trim((string) ($usuarioAvatar['ApMatPer'] ?? ''))
], static fn(string $p): bool => $p !== '')));

if ($nombreCompleto === '') {
    $nombreCompleto = trim(
        (string) ($usuarioAvatar['CorreoUsu'] ?? 'Usuario')
    );
}

$urlFoto = Helper::fotoPerfilUrl(
    (string) ($usuarioAvatar['FotoPerfilPer'] ?? '')
);

$iniciales = Helper::inicialesPersona(
    (string) ($usuarioAvatar['NombrePer'] ?? ''),
    (string) ($usuarioAvatar['ApPatPer'] ?? ''),
    (string) ($usuarioAvatar['ApMatPer'] ?? '')
);

if ($iniciales === '?' && $avatarFallback !== '') {
    $iniciales = $avatarFallback;
}

$alt = 'Foto de perfil de ' . $nombreCompleto;

$clasesContenedor = $avatarClass;

if ($urlFoto === null && $avatarEmptyClass !== '') {
    $clasesContenedor .= ' ' . $avatarEmptyClass;
}

?>

<div class="<?= htmlspecialchars($clasesContenedor, ENT_QUOTES, 'UTF-8'); ?>">

    <?php if ($urlFoto !== null): ?>

        <img
            src="<?= htmlspecialchars($urlFoto, ENT_QUOTES, 'UTF-8'); ?>"
            alt="<?= htmlspecialchars($alt, ENT_QUOTES, 'UTF-8'); ?>"
            <?php if ($avatarImageClass !== ''): ?>
                class="<?= htmlspecialchars(
                    $avatarImageClass,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            <?php endif; ?>
        >

    <?php else: ?>

        <span aria-hidden="true">
            <?= htmlspecialchars($iniciales, ENT_QUOTES, 'UTF-8'); ?>
        </span>

    <?php endif; ?>

</div>
