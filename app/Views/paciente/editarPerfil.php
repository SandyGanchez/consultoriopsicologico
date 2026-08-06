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

    <?php
        $clavesSeccionesPendientes = is_array($clavesSeccionesPendientes ?? null)
            ? $clavesSeccionesPendientes
            : [];
        $pendientePersonal = in_array('DATOS_PERSONALES', $clavesSeccionesPendientes, true);
        $pendienteContacto = in_array('CONTACTO', $clavesSeccionesPendientes, true);
        $pendienteDireccion = in_array('DIRECCION', $clavesSeccionesPendientes, true);

        require __DIR__ . '/partials/aviso-perfil-incompleto.php';
    ?>

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

        <section
            class="paciente-profile-card<?= $pendientePersonal ? ' is-pending' : ''; ?>"
            aria-labelledby="personal-titulo"
            id="seccion-datos-personales"
        >
            <h2 id="personal-titulo">
                <i class="bi bi-person" aria-hidden="true"></i>
                Información personal
            </h2>
            <?php if ($pendientePersonal): ?>
                <p class="paciente-profile-card-help">
                    Completa fecha de nacimiento y género para dejar esta sección al día.
                </p>
            <?php endif; ?>

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

        <?php if ($pendienteContacto): ?>
            <section
                class="paciente-profile-card is-pending"
                aria-labelledby="contacto-titulo"
                id="seccion-contacto"
            >
                <h2 id="contacto-titulo">
                    <i class="bi bi-telephone" aria-hidden="true"></i>
                    Información de contacto
                </h2>
                <p class="paciente-profile-card-help">
                    Tu teléfono está pendiente. Actualízalo en
                    <a href="<?= $escapar(Helper::baseUrl('paciente/configuracion')); ?>">
                        Configuración
                    </a>
                    para completar tu perfil.
                </p>
            </section>
        <?php endif; ?>

        <section
            class="paciente-profile-card<?= $pendienteDireccion ? ' is-pending' : ''; ?>"
            aria-labelledby="direccion-titulo"
            id="seccion-direccion"
        >
            <h2 id="direccion-titulo">
                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                Dirección
            </h2>
            <p class="paciente-profile-card-help">
                <?php if ($pendienteDireccion): ?>
                    Registra país, estado, municipio, colonia, calle, código postal
                    y número exterior. El número interior y la referencia son opcionales.
                <?php else: ?>
                    Completa estos campos solo si deseas registrar o actualizar
                    tu dirección. Si dejas todo vacío, no se modifica.
                <?php endif; ?>
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
    if (input && avatar) {
        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            if (!file) return;

            const url = URL.createObjectURL(file);
            const img = document.createElement('img');
            img.src = url;
            img.alt = 'Vista previa';
            img.id = 'previewFoto';
            avatar.replaceChildren(img);
        });
    }

    const form = document.querySelector('.paciente-profile-form');
    if (!form) return;

    const primerError = form.querySelector('.paciente-field-error');
    if (primerError) {
        const campo = primerError.closest('.paciente-field');
        const control = campo
            ? campo.querySelector('input, select, textarea')
            : null;
        if (control && typeof control.focus === 'function') {
            control.focus();
            return;
        }
    }

    const pendiente = form.querySelector('.paciente-profile-card.is-pending');
    if (!pendiente) return;
    const primerCampo = pendiente.querySelector('input, select, textarea');
    if (primerCampo && typeof primerCampo.focus === 'function') {
        primerCampo.focus();
    }
})();
</script>
