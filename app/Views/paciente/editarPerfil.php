<?php

use App\Helpers\Helper;
use App\Services\EdadService;

$escapar = static function ($valor): string {
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
};

$limitesEdad = (new EdadService())->limitesInput('paciente');

$perfil = is_array($perfil ?? null) ? $perfil : [];
$errores = is_array($errores ?? null) ? $errores : [];
$old = is_array($old ?? null) ? $old : [];
$csrf = (string) ($csrf ?? '');

$valor = static function (string $campo) use ($old, $perfil): string {
    if (array_key_exists($campo, $old)) {
        return (string) $old[$campo];
    }

    return (string) ($perfil[$campo] ?? '');
};

$partesNombre = array_filter(
    [
        trim($valor('NombrePer')),
        trim($valor('ApPatPer')),
        trim($valor('ApMatPer'))
    ],
    static fn(string $p): bool => $p !== ''
);

$iniciales = '';

foreach (array_slice($partesNombre, 0, 2) as $parte) {
    $iniciales .= mb_strtoupper(mb_substr($parte, 0, 1));
}

if ($iniciales === '') {
    $iniciales = 'P';
}

$urlFoto = Helper::fotoPerfilUrl(
    (string) ($perfil['FotoPerfilPer'] ?? '')
) ?? '';

$flashError = trim((string) ($_SESSION['error'] ?? ''));
unset($_SESSION['error']);

?>

<section class="paciente-profile paciente-profile-edit">

    <header class="paciente-page-header">
        <div class="paciente-page-header-icon" aria-hidden="true">
            <i class="bi bi-pencil-square"></i>
        </div>
        <div class="paciente-page-header-copy">
            <h1>Editar mi perfil</h1>
            <p>Actualiza tu fotografía, datos personales y dirección.</p>
        </div>
    </header>

    <?php if ($flashError !== ''): ?>
        <div class="paciente-profile-alert" role="alert">
            <?= $escapar($flashError); ?>
        </div>
    <?php endif; ?>

    <form
        class="paciente-profile-form"
        method="POST"
        action="<?= $escapar(Helper::baseUrl('paciente/perfil/actualizar')); ?>"
        enctype="multipart/form-data"
        novalidate
    >
        <input type="hidden" name="csrf_token" value="<?= $escapar($csrf); ?>">

        <section class="paciente-profile-card" aria-labelledby="foto-titulo">
            <h2 id="foto-titulo">
                <i class="bi bi-camera" aria-hidden="true"></i>
                Fotografía
            </h2>

            <div class="paciente-profile-photo-edit">
                <div class="paciente-profile-avatar" id="previewAvatar">
                    <?php if ($urlFoto !== ''): ?>
                        <img
                            src="<?= $escapar($urlFoto); ?>"
                            alt="Vista previa"
                            id="previewFoto"
                        >
                    <?php else: ?>
                        <span id="previewIniciales" aria-hidden="true">
                            <?= $escapar($iniciales); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="FotoPerfilPer">Seleccionar imagen</label>
                    <input
                        type="file"
                        name="FotoPerfilPer"
                        id="FotoPerfilPer"
                        accept="image/jpeg,image/png,image/webp"
                    >
                    <small>JPG, PNG o WEBP. Máximo 2 MB.</small>
                    <?php if (!empty($errores['FotoPerfilPer'])): ?>
                        <em class="paciente-field-error">
                            <?= $escapar($errores['FotoPerfilPer']); ?>
                        </em>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="paciente-profile-card" aria-labelledby="personal-titulo">
            <h2 id="personal-titulo">
                <i class="bi bi-person" aria-hidden="true"></i>
                Información personal
            </h2>

            <div class="paciente-profile-form-grid">
                <div class="paciente-field">
                    <label for="NombrePer">Nombre</label>
                    <input
                        type="text"
                        id="NombrePer"
                        name="NombrePer"
                        maxlength="50"
                        required
                        value="<?= $escapar($valor('NombrePer')); ?>"
                    >
                    <?php if (!empty($errores['NombrePer'])): ?>
                        <em class="paciente-field-error">
                            <?= $escapar($errores['NombrePer']); ?>
                        </em>
                    <?php endif; ?>
                </div>

                <div class="paciente-field">
                    <label for="ApPatPer">Apellido paterno</label>
                    <input
                        type="text"
                        id="ApPatPer"
                        name="ApPatPer"
                        maxlength="50"
                        required
                        value="<?= $escapar($valor('ApPatPer')); ?>"
                    >
                    <?php if (!empty($errores['ApPatPer'])): ?>
                        <em class="paciente-field-error">
                            <?= $escapar($errores['ApPatPer']); ?>
                        </em>
                    <?php endif; ?>
                </div>

                <div class="paciente-field">
                    <label for="ApMatPer">Apellido materno</label>
                    <input
                        type="text"
                        id="ApMatPer"
                        name="ApMatPer"
                        maxlength="50"
                        value="<?= $escapar($valor('ApMatPer')); ?>"
                    >
                    <small>Opcional si no aplica.</small>
                    <?php if (!empty($errores['ApMatPer'])): ?>
                        <em class="paciente-field-error">
                            <?= $escapar($errores['ApMatPer']); ?>
                        </em>
                    <?php endif; ?>
                </div>

                <div class="paciente-field">
                    <label for="FechaNacimiento">Fecha de nacimiento</label>
                    <input
                        type="date"
                        id="FechaNacimiento"
                        name="FechaNacimiento"
                        required
                        min="<?= $escapar($limitesEdad['min']); ?>"
                        max="<?= $escapar($limitesEdad['max']); ?>"
                        value="<?= $escapar($valor('FechaNacimiento')); ?>"
                    >
                    <small class="paciente-field-help">
                        Selecciona una fecha válida. Los pacientes menores requieren autorización de su representante legal.
                    </small>
                    <?php if (!empty($errores['FechaNacimiento'])): ?>
                        <em class="paciente-field-error">
                            <?= $escapar($errores['FechaNacimiento']); ?>
                        </em>
                    <?php endif; ?>
                </div>

                <div class="paciente-field">
                    <label for="GeneroPer">Género</label>
                    <select id="GeneroPer" name="GeneroPer" required>
                        <?php
                            $generoActual = $valor('GeneroPer');
                            foreach (['Masculino', 'Femenino', 'Otro'] as $genero):
                        ?>
                            <option
                                value="<?= $escapar($genero); ?>"
                                <?= $generoActual === $genero ? 'selected' : ''; ?>
                            >
                                <?= $escapar($genero); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errores['GeneroPer'])): ?>
                        <em class="paciente-field-error">
                            <?= $escapar($errores['GeneroPer']); ?>
                        </em>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="paciente-profile-card" aria-labelledby="direccion-titulo">
            <h2 id="direccion-titulo">
                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                Dirección
            </h2>
            <p class="paciente-profile-card-help">
                Completa estos campos solo si deseas registrar o actualizar
                tu dirección. Si dejas todo vacío, no se modifica.
            </p>

            <div class="paciente-profile-form-grid">
                <?php
                    $camposDir = [
                        'PaisDir' => 'País',
                        'EstadoDir' => 'Estado',
                        'MunicipioDir' => 'Municipio',
                        'ColoniaDir' => 'Colonia',
                        'CalleDir' => 'Calle',
                        'CodPostDir' => 'Código postal',
                        'NumExtDir' => 'Número exterior',
                        'NumIntDir' => 'Número interior',
                        'ReferenciaDir' => 'Referencia'
                    ];

                    foreach ($camposDir as $name => $label):
                ?>
                    <div class="paciente-field<?= $name === 'ReferenciaDir' ? ' is-full' : ''; ?>">
                        <label for="<?= $escapar($name); ?>">
                            <?= $escapar($label); ?>
                        </label>
                        <input
                            type="text"
                            id="<?= $escapar($name); ?>"
                            name="<?= $escapar($name); ?>"
                            value="<?= $escapar($valor($name)); ?>"
                            <?= $name === 'CodPostDir' ? 'maxlength="5" inputmode="numeric"' : ''; ?>
                        >
                        <?php if (!empty($errores[$name])): ?>
                            <em class="paciente-field-error">
                                <?= $escapar($errores[$name]); ?>
                            </em>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="paciente-profile-actions">
            <a
                class="paciente-btn paciente-btn-secondary"
                href="<?= $escapar(Helper::baseUrl('paciente/perfil')); ?>"
            >
                Cancelar
            </a>
            <button
                type="submit"
                class="paciente-btn paciente-btn-primary"
            >
                Guardar cambios
            </button>
        </div>
    </form>

</section>

<script>
(() => {
    const input = document.getElementById('FotoPerfilPer');
    const avatar = document.getElementById('previewAvatar');
    if (!input || !avatar) return;

    input.addEventListener('change', () => {
        const file = input.files && input.files[0];
        if (!file) return;

        const url = URL.createObjectURL(file);
        avatar.innerHTML = '<img src="' + url + '" alt="Vista previa" id="previewFoto">';
    });
})();
</script>
