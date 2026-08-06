<?php

use App\Core\Session;
use App\Helpers\Helper;
use App\Services\CompletarInformacionPacienteService;
use App\Services\EdadService;

$paciente = is_array($paciente ?? null) ? $paciente : [];
$faltantes = is_array($faltantes ?? null) ? $faltantes : ['persona' => [], 'direccion' => []];
$valores = is_array($valores ?? null) ? $valores : [];
$valoresOld = is_array($valoresOld ?? null) ? $valoresOld : [];
$errores = is_array($errores ?? null) ? $errores : [];
$retorno = (string) ($retorno ?? 'detalle');
$rutaCancelar = (string) ($rutaCancelar ?? 'psicologo/pacientes');
$mensajeError = $mensajeError ?? null;

$clvPac = (string) ($paciente['ClvPac'] ?? '');
$nombre = trim((string) ($paciente['NombrePaciente'] ?? ''));
$csrf = Session::csrfToken();
$limitesEdad = (new EdadService())->limitesInput('paciente');

$h = static function ($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
};

$valorCampo = static function (string $campo) use ($valoresOld, $valores): string {
    if (array_key_exists($campo, $valoresOld)) {
        return trim((string) $valoresOld[$campo]);
    }

    $actual = trim((string) ($valores[$campo] ?? ''));

    if ($campo === 'PaisDir' && $actual === '') {
        return 'México';
    }

    return $actual;
};

$etiquetas = [
    'NombrePer' => 'Nombre',
    'ApPatPer' => 'Apellido paterno',
    'ApMatPer' => 'Apellido materno',
    'FechaNacimiento' => 'Fecha de nacimiento',
    'GeneroPer' => 'Género',
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

$ayudas = [
    'NombrePer' => 'Solo letras, sin números.',
    'ApPatPer' => 'Solo letras, sin números.',
    'ApMatPer' => 'Solo letras, sin números.',
    'FechaNacimiento' => 'Selecciona una fecha válida. Los pacientes menores requieren autorización de su representante legal.',
    'GeneroPer' => 'Selecciona una opción válida.',
    'PaisDir' => 'País de residencia.',
    'EstadoDir' => 'Estado o entidad federativa.',
    'MunicipioDir' => 'Municipio o alcaldía.',
    'ColoniaDir' => 'Colonia o asentamiento.',
    'CalleDir' => 'Nombre de la calle (opcional).',
    'CodPostDir' => 'Exactamente 5 dígitos.',
    'NumExtDir' => 'Número exterior (opcional).',
    'NumIntDir' => 'Número interior (opcional).',
    'ReferenciaDir' => 'Referencia para ubicar el domicilio (opcional).'
];

$fotoUrl = '';
if (!empty($paciente['TieneFoto']) && !empty($paciente['FotoArchivo'])) {
    $fotoUrl = Helper::baseUrl(
        'uploads/perfiles/' . rawurlencode((string) $paciente['FotoArchivo'])
    );
}

$faltantesPersona = $faltantes['persona'] ?? [];
$faltantesDireccion = $faltantes['direccion'] ?? [];
$primerError = $errores !== [] ? (string) array_key_first($errores) : '';

?>

<section class="psychologist-patients-page psi-completar-info">

    <div class="psychologist-patient-detail__nav">
        <a
            href="<?= $h(Helper::baseUrl($rutaCancelar)); ?>"
            class="psychologist-patients-back"
        >
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Cancelar y volver
        </a>
    </div>

    <?php if (!empty($mensajeError)): ?>
        <div class="alert alert-danger" role="alert">
            <?= $h((string) $mensajeError); ?>
        </div>
    <?php endif; ?>

    <article class="psi-completar-info__card">

        <header class="psi-completar-info__header">
            <div class="psychologist-patient-avatar psychologist-patient-avatar--lg">
                <?php if ($fotoUrl !== ''): ?>
                    <img
                        src="<?= $h($fotoUrl); ?>"
                        alt="Foto de <?= $h($nombre); ?>"
                    >
                <?php else: ?>
                    <span><?= $h((string) ($paciente['Iniciales'] ?? 'P')); ?></span>
                <?php endif; ?>
            </div>
            <div>
                <h1>Completar información del paciente</h1>
                <p class="psi-completar-info__nombre"><?= $h($nombre); ?></p>
                <p class="psi-completar-info__meta">
                    Paciente <?= $h($clvPac); ?>
                </p>
                <p class="psi-completar-info__nota">
                    Solo se muestran los datos que aún no han sido registrados.
                </p>
            </div>
        </header>

        <form
            method="post"
            action="<?= $h(Helper::baseUrl('psicologo/pacientes/completar-informacion')); ?>"
            class="psi-completar-info__form"
            id="formCompletarInformacionPaciente"
            novalidate
        >
            <input type="hidden" name="csrf_token" value="<?= $h($csrf); ?>">
            <input type="hidden" name="ClvPac" value="<?= $h($clvPac); ?>">
            <input type="hidden" name="retorno" value="<?= $h($retorno); ?>">

            <?php if ($faltantesPersona !== []): ?>
                <fieldset class="psi-completar-info__section">
                    <legend>Datos personales faltantes</legend>

                    <?php foreach ($faltantesPersona as $campo): ?>
                        <?php
                        $id = 'campo_' . $campo;
                        $tieneError = isset($errores[$campo]);
                        $valor = $valorCampo($campo);
                        ?>
                        <div class="psi-completar-info__field<?= $tieneError ? ' has-error' : ''; ?>">
                            <label for="<?= $h($id); ?>">
                                <?= $h($etiquetas[$campo] ?? $campo); ?>
                            </label>

                            <?php if ($campo === 'GeneroPer'): ?>
                                <select
                                    id="<?= $h($id); ?>"
                                    name="<?= $h($campo); ?>"
                                    required
                                    aria-invalid="<?= $tieneError ? 'true' : 'false'; ?>"
                                    <?= $tieneError ? 'aria-describedby="' . $h($id) . '_error"' : ''; ?>
                                >
                                    <option value="">Selecciona…</option>
                                    <?php foreach (CompletarInformacionPacienteService::GENEROS as $genero): ?>
                                        <option
                                            value="<?= $h($genero); ?>"
                                            <?= $valor === $genero ? ' selected' : ''; ?>
                                        >
                                            <?= $h($genero); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($campo === 'FechaNacimiento'): ?>
                                <input
                                    type="date"
                                    id="<?= $h($id); ?>"
                                    name="<?= $h($campo); ?>"
                                    value="<?= $h($valor); ?>"
                                    min="<?= $h($limitesEdad['min']); ?>"
                                    max="<?= $h($limitesEdad['max']); ?>"
                                    required
                                    aria-invalid="<?= $tieneError ? 'true' : 'false'; ?>"
                                >
                            <?php else: ?>
                                <input
                                    type="text"
                                    id="<?= $h($id); ?>"
                                    name="<?= $h($campo); ?>"
                                    value="<?= $h($valor); ?>"
                                    maxlength="50"
                                    required
                                    autocomplete="off"
                                    aria-invalid="<?= $tieneError ? 'true' : 'false'; ?>"
                                >
                            <?php endif; ?>

                            <small class="psi-completar-info__help">
                                <?= $h($ayudas[$campo] ?? ''); ?>
                            </small>

                            <?php if ($tieneError): ?>
                                <p
                                    class="psi-completar-info__error"
                                    id="<?= $h($id); ?>_error"
                                >
                                    <?= $h((string) $errores[$campo]); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </fieldset>
            <?php endif; ?>

            <?php if ($faltantesDireccion !== []): ?>
                <fieldset class="psi-completar-info__section">
                    <legend>Dirección faltante</legend>

                    <?php foreach ($faltantesDireccion as $campo): ?>
                        <?php
                        $id = 'campo_' . $campo;
                        $tieneError = isset($errores[$campo]);
                        $valor = $valorCampo($campo);
                        $obligatorio = in_array(
                            $campo,
                            CompletarInformacionPacienteService::CAMPOS_DIRECCION_OBLIGATORIOS_ALTA,
                            true
                        );
                        $max = match ($campo) {
                            'CalleDir' => 70,
                            'CodPostDir' => 5,
                            'NumExtDir', 'NumIntDir' => 10,
                            'ReferenciaDir' => 255,
                            default => 50
                        };
                        ?>
                        <div class="psi-completar-info__field<?= $tieneError ? ' has-error' : ''; ?>">
                            <label for="<?= $h($id); ?>">
                                <?= $h($etiquetas[$campo] ?? $campo); ?>
                                <?= $obligatorio ? '' : ' <span class="psi-completar-info__optional">(opcional)</span>'; ?>
                            </label>

                            <?php if ($campo === 'ReferenciaDir'): ?>
                                <textarea
                                    id="<?= $h($id); ?>"
                                    name="<?= $h($campo); ?>"
                                    maxlength="<?= (int) $max; ?>"
                                    rows="3"
                                    aria-invalid="<?= $tieneError ? 'true' : 'false'; ?>"
                                ><?= $h($valor); ?></textarea>
                            <?php else: ?>
                                <input
                                    type="text"
                                    id="<?= $h($id); ?>"
                                    name="<?= $h($campo); ?>"
                                    value="<?= $h($valor); ?>"
                                    maxlength="<?= (int) $max; ?>"
                                    <?= $obligatorio ? 'required' : ''; ?>
                                    inputmode="<?= $campo === 'CodPostDir' ? 'numeric' : 'text'; ?>"
                                    autocomplete="off"
                                    aria-invalid="<?= $tieneError ? 'true' : 'false'; ?>"
                                >
                            <?php endif; ?>

                            <small class="psi-completar-info__help">
                                <?= $h($ayudas[$campo] ?? ''); ?>
                            </small>

                            <?php if ($tieneError): ?>
                                <p
                                    class="psi-completar-info__error"
                                    id="<?= $h($id); ?>_error"
                                >
                                    <?= $h((string) $errores[$campo]); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </fieldset>
            <?php endif; ?>

            <div class="psi-completar-info__actions">
                <a
                    href="<?= $h(Helper::baseUrl($rutaCancelar)); ?>"
                    class="btn psychologist-patients-secondary-btn"
                >
                    Cancelar
                </a>
                <button
                    type="submit"
                    class="btn psychologist-patients-primary-btn"
                >
                    Guardar información
                </button>
            </div>
        </form>
    </article>
</section>

<script>
(function () {
    var form = document.getElementById('formCompletarInformacionPaciente');
    var btn = form ? form.querySelector('button[type="submit"]') : null;
    var enviando = false;

    if (form) {
        form.addEventListener('submit', function (event) {
            if (enviando) {
                event.preventDefault();
                return;
            }
            enviando = true;
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Guardando…';
            }
        });
    }

    <?php if ($primerError !== ''): ?>
    var campo = document.getElementById(<?= json_encode('campo_' . $primerError); ?>);
    if (campo && typeof campo.focus === 'function') {
        campo.focus();
    }
    <?php endif; ?>
})();
</script>
