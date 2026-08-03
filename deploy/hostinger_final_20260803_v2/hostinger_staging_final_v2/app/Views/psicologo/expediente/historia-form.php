<?php

use App\Core\Session;
use App\Helpers\Helper;

$paciente = $paciente ?? [];
$modo = $modo ?? 'crear';
$completo = $completo ?? null;
$historial = $historial ?? null;
$citaHabilitadora = $citaHabilitadora ?? null;
$psicologo = $psicologo ?? [];
$usuario = is_array($usuario ?? null) ? $usuario : [];
$mensajeError = $mensajeError ?? null;
$datosFormulario = isset($datosFormulario) && is_array($datosFormulario) ? $datosFormulario : null;
$pasoInicial = (int) ($pasoInicial ?? 1);

if ($pasoInicial < 1 || $pasoInicial > 8) {
    $pasoInicial = 1;
}

$esEdicion = $modo === 'editar';
$clvPac = (string) ($paciente['ClvPac'] ?? '');
$nombre = trim((string) ($paciente['NombrePaciente'] ?? ''));
$csrf = Session::csrfToken();

$h = static function ($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
};
$chk = static function ($condicion): string {
    return !empty($condicion) ? ' checked' : '';
};
$sel = static function ($valorActual, $opcion): string {
    return ((string) $valorActual === (string) $opcion) ? ' selected' : '';
};
$etq = static function (string $valor): string {
    return str_replace('_', ' ', $valor);
};

if ($datosFormulario !== null) {
    $estado = is_array($datosFormulario['estado'] ?? null) ? $datosFormulario['estado'] : [];
    $psico = is_array($datosFormulario['psicoanamnesis'] ?? null) ? $datosFormulario['psicoanamnesis'] : [];
    $actitud = is_array($datosFormulario['actitud'] ?? null) ? $datosFormulario['actitud'] : [];
    $vida = is_array($datosFormulario['vida_social'] ?? null) ? $datosFormulario['vida_social'] : [];
    $examen = is_array($datosFormulario['examen_mental'] ?? null) ? $datosFormulario['examen_mental'] : [];
    $apreciacion = is_array($datosFormulario['apreciacion'] ?? null) ? $datosFormulario['apreciacion'] : [];
    $antsPat = array_values(array_filter(
        is_array($datosFormulario['antecedentes_patologicos'] ?? null) ? $datosFormulario['antecedentes_patologicos'] : [],
        'is_array'
    ));
    $antsFam = array_values(array_filter(
        is_array($datosFormulario['antecedentes_familiares'] ?? null) ? $datosFormulario['antecedentes_familiares'] : [],
        'is_array'
    ));
    $adicciones = array_values(array_filter(
        is_array($datosFormulario['adicciones'] ?? null) ? $datosFormulario['adicciones'] : [],
        'is_array'
    ));
    $reactivos = array_values(array_filter(
        is_array($datosFormulario['reactivos'] ?? null) ? $datosFormulario['reactivos'] : [],
        'is_array'
    ));
    $fechaEntrevistaPost = trim((string) ($datosFormulario['FechaEntrevistaInicial'] ?? ''));
} else {
    $estado = $completo['estado'] ?? [];
    $psico = $completo['psicoanamnesis'] ?? [];
    $actitud = $completo['actitud'] ?? [];
    $vida = $completo['vida_social'] ?? [];
    $examen = $completo['examen_mental'] ?? [];
    $apreciacion = $completo['apreciaciones'][0] ?? [];
    $antsPat = $completo['antecedentes_patologicos'] ?? [];
    $antsFam = $completo['antecedentes_familiares'] ?? [];
    $adicciones = $completo['adicciones'] ?? [];
    $reactivos = $completo['reactivos'] ?? [];
    $fechaEntrevistaPost = null;
}

if ($antsPat === []) {
    $antsPat = [['TipoAntecedente' => '']];
}
if ($antsFam === []) {
    $antsFam = [['TipoAntecedenteFam' => '']];
}
if ($adicciones === []) {
    $adicciones = [['TipoAdiccion' => '']];
}
if ($reactivos === []) {
    $reactivos = [['NombreReactivo' => '', 'FechaAplicacion' => date('Y-m-d')]];
}

$tiposPat = [
    'CARDIOVASCULAR', 'PULMONAR', 'RENAL', 'GASTROINTESTINAL', 'HEMATOLOGICO',
    'ENDOCRINO', 'MENTAL', 'DERMATOLOGICO', 'NEUROLOGICO', 'METABOLICO',
    'MARCAPASOS', 'CARDIOPATIA', 'NEUROPATIA', 'IMPLANTE_DENTAL', 'CANCER',
    'CONVULSIONES', 'ENFERMEDAD_INFANCIA', 'OTRO'
];

$tiposFam = [
    'ALTERACION_PERSONALIDAD', 'DROGADICCION', 'ALCOHOLISMO', 'PSICOSIS',
    'NEUROSIS', 'TRASTORNO_CONVULSIVO', 'PSICOPATIA', 'OTRO'
];

$opcionesActitudPadres = ['AFECTUOSA', 'SOBREPROTECTORA', 'INDIFERENTE', 'HOSTIL', 'INEXISTENTE', 'OTRA'];
$opcionesRelacionHermanos = ['AFECTUOSA', 'SOBREPROTECTORA', 'APATICA', 'AGRESIVA', 'INEXISTENTE', 'OTRA'];
$opcionesCantidadAmigos = ['MUCHOS', 'POCOS', 'NINGUNO'];
$opcionesGrupoSocial = ['DISOCIAL', 'MIXTO', 'SANO', 'SIN_GRUPO'];
$opcionesEstabilidadLaboral = ['ESTABLE', 'INESTABLE', 'NO_APLICA'];
$opcionesSatisfaccionLaboral = ['SATISFECHO', 'PARCIALMENTE_SATISFECHO', 'INSATISFECHO', 'NO_APLICA'];
$opcionesAdaptacionLaboral = ['ADECUADA', 'REGULAR', 'INADECUADA', 'NO_APLICA'];
$opcionesSituacionLaboral = [
    'REALIZADO', 'FRUSTRADO', 'DESEMPLEADO', 'DESPEDIDO', 'SANCIONADO',
    'REUBICADO', 'REINGRESADO', 'NO_APLICA', 'OTRO'
];
$opcionesFrecuenciaAdiccion = ['FRECUENTE', 'POCO_FRECUENTE', 'OCASIONAL', 'NO_ESPECIFICADA'];
$opcionesEstadoConsumo = ['CONTROLADO', 'DESCONTROLADO', 'EN_ABSTINENCIA', 'EN_TRATAMIENTO', 'NO_ESPECIFICADO'];
$opcionesSistemaClasificacion = ['DSM5', 'CIE10', 'CIE11', 'OTRO'];

$action = $esEdicion
    ? Helper::baseUrl('psicologo/pacientes/historia/actualizar')
    : Helper::baseUrl('psicologo/pacientes/historia/guardar');

$volver = Helper::baseUrl(
    'psicologo/pacientes/ver/' .
    rawurlencode($clvPac) .
    '/expediente?tab=historia'
);

$verPacienteUrl = Helper::baseUrl(
    'psicologo/pacientes/ver/' . rawurlencode($clvPac)
);

$fechaEntrevista = $fechaEntrevistaPost ?? '';

if ($fechaEntrevista === '') {
    if (!empty($historial['FechaEntrevistaInicial'])) {
        $fechaEntrevista = date(
            'Y-m-d',
            strtotime((string) $historial['FechaEntrevistaInicial'])
        );
    } elseif (!empty($citaHabilitadora['FechaCita'])) {
        $fechaEntrevista = (string) $citaHabilitadora['FechaCita'];
    }
}

$flagsEstado = [
    'Ansiedad' => 'Ansiedad',
    'Angustia' => 'Angustia',
    'AutoestimaBaja' => 'Autoestima baja',
    'Indiferencia' => 'Indiferencia',
    'Confusion' => 'Confusión',
    'Descontrol' => 'Descontrol',
    'Desorientacion' => 'Desorientación',
    'Incoherencia' => 'Incoherencia',
    'Sobrevaloracion' => 'Sobrevaloración'
];

$flagsActitud = [
    'Independiente' => 'Independiente',
    'Dependiente' => 'Dependiente',
    'Timida' => 'Tímida',
    'Expansiva' => 'Expansiva',
    'Agresiva' => 'Agresiva',
    'Controlada' => 'Controlada',
    'Frustrada' => 'Frustrada',
    'Deprimida' => 'Deprimida',
    'Alegre' => 'Alegre',
    'ConductaPsicopatica' => 'Conducta psicopática',
    'ProblemasConductuales' => 'Problemas conductuales',
    'TrabajoPrecoz' => 'Trabajo precoz',
    'SintomasNeuroticos' => 'Síntomas neuróticos',
    'ProblemasEscolares' => 'Problemas escolares'
];

$edadPaciente = $paciente['Edad'] ?? null;
$generoPaciente = trim((string) ($paciente['GeneroPer'] ?? ''));
$infoPersonalIncompleta = !empty($infoPersonalIncompleta);
$faltantesPersonales = is_array($faltantesPersonales ?? null)
    ? $faltantesPersonales
    : ['persona' => [], 'direccion' => []];
$mensajeExito = $mensajeExito ?? null;
$inicialesPaciente = (string) ($paciente['Iniciales'] ?? Helper::inicialesPersona(
    (string) ($paciente['NombrePer'] ?? ''),
    (string) ($paciente['ApPatPer'] ?? '')
));

$faltantesEtiquetas = [
    'NombrePer' => 'Nombre',
    'ApPatPer' => 'Ap. paterno',
    'ApMatPer' => 'Ap. materno',
    'FechaNacimiento' => 'Fecha nac.',
    'GeneroPer' => 'Género',
    'PaisDir' => 'País',
    'EstadoDir' => 'Estado',
    'MunicipioDir' => 'Municipio',
    'ColoniaDir' => 'Colonia',
    'CalleDir' => 'Calle',
    'CodPostDir' => 'C.P.',
    'NumExtDir' => 'Núm. ext.',
    'NumIntDir' => 'Núm. int.',
    'ReferenciaDir' => 'Referencia'
];
$listaFaltantesHistoria = array_merge(
    $faltantesPersonales['persona'] ?? [],
    $faltantesPersonales['direccion'] ?? []
);

// Pestaña nueva: no pierde datos clínicos no guardados de esta historia.
$urlCompletarInfo = Helper::baseUrl(
    'psicologo/pacientes/ver/'
    . rawurlencode($clvPac)
    . '/completar-informacion?retorno='
    . rawurlencode($esEdicion ? 'expediente' : 'historia_externa')
);

$fotoPacienteUrl = '';
if (!empty($paciente['TieneFoto']) && !empty($paciente['FotoArchivo'])) {
    $fotoPacienteUrl = Helper::baseUrl(
        'uploads/perfiles/' . rawurlencode((string) $paciente['FotoArchivo'])
    );
}

$nombrePsicologo = trim(implode(' ', array_filter([
    $psicologo['NombrePer'] ?? ($usuario['NombrePer'] ?? ''),
    $psicologo['ApPatPer'] ?? ($usuario['ApPatPer'] ?? ''),
])));

$resumenPrimeraSesion = '';
if (!empty($citaHabilitadora['FechaCita'])) {
    $resumenPrimeraSesion = 'Primera sesión: ' . date(
        'd/m/Y',
        strtotime((string) $citaHabilitadora['FechaCita'])
    );
    if (!empty($citaHabilitadora['HraInicioCita'])) {
        $resumenPrimeraSesion .= ' · ' . substr((string) $citaHabilitadora['HraInicioCita'], 0, 5);
    }
    if (!empty($citaHabilitadora['NombreServicio'])) {
        $resumenPrimeraSesion .= ' · ' . (string) $citaHabilitadora['NombreServicio'];
    }
} elseif (!empty($historial['FechaAperturaHist'])) {
    $resumenPrimeraSesion = 'Expediente abierto: ' . date(
        'd/m/Y',
        strtotime((string) $historial['FechaAperturaHist'])
    );
    if (!empty($historial['NumeroExpediente'])) {
        $resumenPrimeraSesion .= ' · Núm. ' . (string) $historial['NumeroExpediente'];
    }
}

if ($nombrePsicologo !== '') {
    $resumenPrimeraSesion = trim(
        $resumenPrimeraSesion
        . ($resumenPrimeraSesion !== '' ? ' · ' : '')
        . 'Psicólogo: '
        . $nombrePsicologo
    );
}

$pasosWizard = [
    1 => 'Inicio',
    2 => 'Antecedentes',
    3 => 'Contexto familiar',
    4 => 'Conducta y entorno',
    5 => 'Examen mental',
    6 => 'Reactivos',
    7 => 'Apreciación',
    8 => 'Revisión'
];

?>

<section
    class="psi-expediente-page psi-historia-form-page"
    data-unsaved-guard="1"
>

    <a href="<?= $h($volver); ?>" class="psi-expediente-back">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
        Volver al expediente
    </a>

    <header class="psi-expediente-form-header">
        <h1>
            <?= $esEdicion
                ? 'Editar historia clínica inicial'
                : 'Crear historia clínica inicial'; ?>
        </h1>
        <p>Completa los 8 pasos para registrar la historia clínica inicial del paciente.</p>
    </header>

    <div class="psi-patient-sticky">
        <div class="psi-patient-sticky__id">
            <div class="psi-expediente-avatar psi-patient-sticky__avatar">
                <?php if ($fotoPacienteUrl !== ''): ?>
                    <img src="<?= $h($fotoPacienteUrl); ?>" alt="Foto de <?= $h($nombre); ?>">
                <?php else: ?>
                    <span><?= $h($inicialesPaciente); ?></span>
                <?php endif; ?>
            </div>
            <div class="psi-patient-sticky__info">
                <strong><?= $h($nombre); ?></strong>
                <span class="psi-patient-sticky__meta">
                    <?php if ($edadPaciente !== null): ?>
                        <?= (int) $edadPaciente; ?> años
                    <?php else: ?>
                        <span class="psi-faltante-chip">Edad pendiente</span>
                    <?php endif; ?>
                    <?php if ($generoPaciente !== ''): ?>
                        · <?= $h($generoPaciente); ?>
                    <?php else: ?>
                        · <span class="psi-faltante-chip">Género pendiente</span>
                    <?php endif; ?>
                </span>
                <?php if ($listaFaltantesHistoria !== []): ?>
                    <span class="psi-faltantes-lista" aria-label="Datos personales faltantes">
                        <?php foreach (array_slice($listaFaltantesHistoria, 0, 4) as $campoFaltante): ?>
                            <span class="psi-faltante-chip">
                                <?= $h($faltantesEtiquetas[$campoFaltante] ?? $campoFaltante); ?>
                            </span>
                        <?php endforeach; ?>
                        <?php if (count($listaFaltantesHistoria) > 4): ?>
                            <span class="psi-faltante-chip">
                                +<?= count($listaFaltantesHistoria) - 4; ?>
                            </span>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="psi-patient-sticky__extra">
            <?php if ($resumenPrimeraSesion !== ''): ?>
                <span class="psi-patient-sticky__session"><?= $h($resumenPrimeraSesion); ?></span>
            <?php endif; ?>
            <?php if ($infoPersonalIncompleta): ?>
                <a
                    href="<?= $h($urlCompletarInfo); ?>"
                    class="psi-patient-sticky__link psi-patient-sticky__link--warn"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <i class="bi bi-person-vcard" aria-hidden="true"></i>
                    Completar información del paciente
                </a>
            <?php endif; ?>
            <a href="<?= $h($verPacienteUrl); ?>" class="psi-patient-sticky__link">
                <i class="bi bi-person-lines-fill" aria-hidden="true"></i>
                Ver datos del paciente
            </a>
        </div>
    </div>

    <?php if (!empty($mensajeExito)): ?>
        <div class="psi-expediente-alert psi-expediente-alert--success">
            <?= $h((string) $mensajeExito); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($mensajeError)): ?>
        <div class="psi-expediente-alert psi-expediente-alert--error">
            <?= $h((string) $mensajeError); ?>
        </div>
    <?php endif; ?>

    <?php if ($infoPersonalIncompleta): ?>
        <div class="psi-expediente-alert" role="status">
            “Completar información del paciente” se abre en una pestaña nueva
            para no perder los datos de esta historia que aún no hayas guardado.
            Cuando termines, regresa aquí y actualiza la pantalla para refrescar
            el encabezado del paciente.
        </div>
    <?php endif; ?>

    <div id="historiaErroresResumen" class="psi-historia-errores is-hidden" role="alert" aria-live="assertive"></div>

    <div
        class="psi-historia-wizard"
        id="wizardHistoriaClinica"
        data-paso-inicial="<?= (int) $pasoInicial; ?>"
    >

        <nav class="psi-historia-progress" aria-label="Progreso de la historia clínica">
            <ol>
                <?php foreach ($pasosWizard as $numero => $etiqueta): ?>
                    <li
                        class="psi-historia-progress__step<?= $numero === $pasoInicial ? ' is-active' : ''; ?>"
                        data-progress-step="<?= (int) $numero; ?>"
                    >
                        <span class="psi-historia-progress__index"><?= (int) $numero; ?></span>
                        <span class="psi-historia-progress__label"><?= $h($etiqueta); ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>

        <div class="psi-expediente-notice psi-historia-note">
            El guardado parcial como borrador no está disponible en el esquema actual.
        </div>

        <form
            method="POST"
            action="<?= $h($action); ?>"
            class="psi-historia-form"
            id="formHistoriaClinica"
            autocomplete="off"
            novalidate
        >
            <input type="hidden" name="csrf_token" value="<?= $h($csrf); ?>">
            <input type="hidden" name="ClvPac" value="<?= $h($clvPac); ?>">
            <input type="hidden" name="_paso_actual" id="campoPasoActual" value="<?= (int) $pasoInicial; ?>">

            <?php if ($esEdicion): ?>
                <input
                    type="hidden"
                    name="ClvHist"
                    value="<?= $h((string) ($historial['ClvHist'] ?? '')); ?>"
                >
            <?php else: ?>
                <input
                    type="hidden"
                    name="ClvCita"
                    value="<?= $h((string) ($citaHabilitadora['ClvCita'] ?? '')); ?>"
                >
            <?php endif; ?>

            <!-- Paso 1: Inicio -->
            <div class="psi-historia-step<?= $pasoInicial === 1 ? ' is-active' : ''; ?>" data-step="1">
                <h2 class="psi-historia-step__title">1. Inicio</h2>
                <p class="psi-historia-step__intro">Datos generales de la entrevista y motivo de consulta.</p>

                <label class="psi-field">
                    <span>Fecha de entrevista inicial</span>
                    <input type="date" name="FechaEntrevistaInicial" value="<?= $h($fechaEntrevista); ?>">
                </label>

                <label class="psi-field">
                    <span>Motivo de consulta *</span>
                    <small class="psi-field-tip">Campo obligatorio para poder avanzar.</small>
                    <textarea name="estado[MotivoConsulta]" rows="3" required><?= $h((string) ($estado['MotivoConsulta'] ?? '')); ?></textarea>
                </label>

                <label class="psi-field">
                    <span>Síntomas referidos</span>
                    <textarea name="estado[SintomasReferidos]" rows="2"><?= $h((string) ($estado['SintomasReferidos'] ?? '')); ?></textarea>
                </label>

                <div class="psi-check-grid">
                    <?php foreach ($flagsEstado as $campo => $etiqueta): ?>
                        <label>
                            <input
                                type="checkbox"
                                name="estado[<?= $h($campo); ?>]"
                                value="1"
                                <?= $chk($estado[$campo] ?? null); ?>
                            >
                            <?= $h($etiqueta); ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <label class="psi-field">
                    <span>Otros estados</span>
                    <textarea name="estado[OtrosEstados]" rows="2"><?= $h((string) ($estado['OtrosEstados'] ?? '')); ?></textarea>
                </label>

                <label class="psi-field">
                    <span>Observaciones iniciales</span>
                    <textarea name="estado[ObservacionesIniciales]" rows="2"><?= $h((string) ($estado['ObservacionesIniciales'] ?? '')); ?></textarea>
                </label>
            </div>

            <!-- Paso 2: Antecedentes -->
            <div class="psi-historia-step<?= $pasoInicial === 2 ? ' is-active' : ''; ?>" data-step="2">
                <h2 class="psi-historia-step__title">2. Antecedentes</h2>
                <p class="psi-historia-step__intro">Antecedentes patológicos y familiares relevantes.</p>

                <h3 class="psi-historia-substep">Antecedentes patológicos</h3>
                <div id="listaAntecedentesPat" data-dynamic-list="antecedentes_patologicos">
                    <?php foreach ($antsPat as $i => $ant): ?>
                        <?php $persistidoPat = !empty($ant['ClvAntPat']); ?>
                        <div class="psi-dynamic-row<?= $persistidoPat ? ' psi-dynamic-row--persistida' : ''; ?>">
                            <?php if ($persistidoPat): ?>
                                <input type="hidden" name="antecedentes_patologicos[<?= (int) $i; ?>][ClvAntPat]" value="<?= $h((string) $ant['ClvAntPat']); ?>">
                            <?php endif; ?>
                            <label class="psi-field">
                                <span>Tipo</span>
                                <select name="antecedentes_patologicos[<?= (int) $i; ?>][TipoAntecedente]">
                                    <option value="">Selecciona…</option>
                                    <?php foreach ($tiposPat as $tipo): ?>
                                        <option value="<?= $tipo; ?>"<?= $sel($ant['TipoAntecedente'] ?? '', $tipo); ?>>
                                            <?= $h($etq($tipo)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="psi-inline-check psi-cond-toggle">
                                <input
                                    type="checkbox"
                                    class="psi-toggle-presenta"
                                    name="antecedentes_patologicos[<?= (int) $i; ?>][PresentaAntecedente]"
                                    value="1"
                                    <?= $chk($ant['PresentaAntecedente'] ?? null); ?>
                                >
                                Presenta actualmente
                            </label>
                            <div class="psi-cond-detail<?= empty($ant['PresentaAntecedente']) ? ' is-hidden' : ''; ?>">
                                <label class="psi-field">
                                    <span>Descripción</span>
                                    <textarea name="antecedentes_patologicos[<?= (int) $i; ?>][DescripcionAntecedente]" rows="2"><?= $h((string) ($ant['DescripcionAntecedente'] ?? '')); ?></textarea>
                                </label>
                                <label class="psi-field">
                                    <span>Tratamiento actual</span>
                                    <textarea name="antecedentes_patologicos[<?= (int) $i; ?>][TratamientoActual]" rows="2"><?= $h((string) ($ant['TratamientoActual'] ?? '')); ?></textarea>
                                </label>
                            </div>
                            <?php if (!$persistidoPat): ?>
                                <button type="button" class="psi-expediente-btn-secondary psi-rec-remove" data-remove-new-row>
                                    Quitar fila nueva
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="psi-expediente-btn-secondary" data-add-row="listaAntecedentesPat">
                    Agregar antecedente patológico
                </button>

                <h3 class="psi-historia-substep">Antecedentes familiares</h3>
                <div id="listaAntecedentesFam" data-dynamic-list="antecedentes_familiares">
                    <?php foreach ($antsFam as $i => $ant): ?>
                        <?php $persistidoFam = !empty($ant['ClvAntFam']); ?>
                        <div class="psi-dynamic-row<?= $persistidoFam ? ' psi-dynamic-row--persistida' : ''; ?>">
                            <?php if ($persistidoFam): ?>
                                <input type="hidden" name="antecedentes_familiares[<?= (int) $i; ?>][ClvAntFam]" value="<?= $h((string) $ant['ClvAntFam']); ?>">
                            <?php endif; ?>
                            <label class="psi-field">
                                <span>Tipo</span>
                                <select name="antecedentes_familiares[<?= (int) $i; ?>][TipoAntecedenteFam]">
                                    <option value="">Selecciona…</option>
                                    <?php foreach ($tiposFam as $tipo): ?>
                                        <option value="<?= $tipo; ?>"<?= $sel($ant['TipoAntecedenteFam'] ?? '', $tipo); ?>>
                                            <?= $h($etq($tipo)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="psi-inline-check psi-cond-toggle">
                                <input
                                    type="checkbox"
                                    class="psi-toggle-presenta-fam"
                                    name="antecedentes_familiares[<?= (int) $i; ?>][PresentaAntecedenteFam]"
                                    value="1"
                                    <?= $chk($ant['PresentaAntecedenteFam'] ?? null); ?>
                                >
                                Presenta actualmente
                            </label>
                            <div class="psi-cond-detail<?= empty($ant['PresentaAntecedenteFam']) ? ' is-hidden' : ''; ?>">
                                <label class="psi-field">
                                    <span>Familiar relacionado</span>
                                    <input type="text" maxlength="100" name="antecedentes_familiares[<?= (int) $i; ?>][FamiliarRelacionado]" value="<?= $h((string) ($ant['FamiliarRelacionado'] ?? '')); ?>">
                                </label>
                                <label class="psi-field">
                                    <span>Descripción</span>
                                    <textarea name="antecedentes_familiares[<?= (int) $i; ?>][DescripcionAntFam]" rows="2"><?= $h((string) ($ant['DescripcionAntFam'] ?? '')); ?></textarea>
                                </label>
                            </div>
                            <?php if (!$persistidoFam): ?>
                                <button type="button" class="psi-expediente-btn-secondary psi-rec-remove" data-remove-new-row>
                                    Quitar fila nueva
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="psi-expediente-btn-secondary" data-add-row="listaAntecedentesFam">
                    Agregar antecedente familiar
                </button>
            </div>

            <!-- Paso 3: Contexto familiar -->
            <div class="psi-historia-step<?= $pasoInicial === 3 ? ' is-active' : ''; ?>" data-step="3">
                <h2 class="psi-historia-step__title">3. Contexto familiar</h2>
                <p class="psi-historia-step__intro">Psicoanamnesis familiar del paciente.</p>

                <div class="psi-check-grid">
                    <label><input type="checkbox" name="psicoanamnesis[PadresJuntos]" value="1" <?= $chk($psico['PadresJuntos'] ?? null); ?>> Padres juntos</label>
                    <label><input type="checkbox" name="psicoanamnesis[PadreFallecido]" value="1" <?= $chk($psico['PadreFallecido'] ?? null); ?>> Padre fallecido</label>
                    <label><input type="checkbox" name="psicoanamnesis[MadreFallecida]" value="1" <?= $chk($psico['MadreFallecida'] ?? null); ?>> Madre fallecida</label>
                    <label><input type="checkbox" name="psicoanamnesis[ConflictoPadre]" value="1" <?= $chk($psico['ConflictoPadre'] ?? null); ?>> Conflicto con padre</label>
                    <label><input type="checkbox" name="psicoanamnesis[ConflictoMadre]" value="1" <?= $chk($psico['ConflictoMadre'] ?? null); ?>> Conflicto con madre</label>
                </div>

                <label class="psi-field">
                    <span>Conflicto con otros familiares</span>
                    <textarea name="psicoanamnesis[ConflictoOtrosFamiliares]" rows="2"><?= $h((string) ($psico['ConflictoOtrosFamiliares'] ?? '')); ?></textarea>
                </label>

                <div class="psi-form-grid">
                    <label class="psi-field">
                        <span>Actitud de los padres</span>
                        <select name="psicoanamnesis[ActitudPadres]">
                            <option value="">—</option>
                            <?php foreach ($opcionesActitudPadres as $op): ?>
                                <option value="<?= $op; ?>"<?= $sel($psico['ActitudPadres'] ?? '', $op); ?>><?= $h($etq($op)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="psi-field">
                        <span>Relación entre hermanos</span>
                        <select name="psicoanamnesis[RelacionHermanos]">
                            <option value="">—</option>
                            <?php foreach ($opcionesRelacionHermanos as $op): ?>
                                <option value="<?= $op; ?>"<?= $sel($psico['RelacionHermanos'] ?? '', $op); ?>><?= $h($etq($op)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="psi-field">
                        <span>Número de hermanos</span>
                        <input type="number" min="0" name="psicoanamnesis[NumeroHermanos]" value="<?= $h((string) ($psico['NumeroHermanos'] ?? '')); ?>">
                    </label>
                    <label class="psi-field">
                        <span>Hermanos varones</span>
                        <input type="number" min="0" name="psicoanamnesis[NumeroHermanosVarones]" value="<?= $h((string) ($psico['NumeroHermanosVarones'] ?? '')); ?>">
                    </label>
                    <label class="psi-field">
                        <span>Hermanas mujeres</span>
                        <input type="number" min="0" name="psicoanamnesis[NumeroHermanasMujeres]" value="<?= $h((string) ($psico['NumeroHermanasMujeres'] ?? '')); ?>">
                    </label>
                </div>

                <label class="psi-field">
                    <span>Observaciones familiares</span>
                    <textarea name="psicoanamnesis[ObservacionesFamiliares]" rows="2"><?= $h((string) ($psico['ObservacionesFamiliares'] ?? '')); ?></textarea>
                </label>
            </div>

            <!-- Paso 4: Conducta y entorno -->
            <div class="psi-historia-step<?= $pasoInicial === 4 ? ' is-active' : ''; ?>" data-step="4">
                <h2 class="psi-historia-step__title">4. Conducta y entorno</h2>
                <p class="psi-historia-step__intro">Actitud inicial, vida social/laboral y adicciones.</p>

                <h3 class="psi-historia-substep">Actitud y conducta inicial</h3>
                <div class="psi-check-grid">
                    <?php foreach ($flagsActitud as $campo => $etiqueta): ?>
                        <label>
                            <input type="checkbox" name="actitud[<?= $h($campo); ?>]" value="1" <?= $chk($actitud[$campo] ?? null); ?>>
                            <?= $h($etiqueta); ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <label class="psi-inline-check psi-cond-toggle">
                    <input type="checkbox" id="toggleFugaHogar" name="actitud[FugaHogar]" value="1" <?= $chk($actitud['FugaHogar'] ?? null); ?>>
                    Fuga del hogar
                </label>
                <div class="psi-cond-detail<?= empty($actitud['FugaHogar']) ? ' is-hidden' : ''; ?>" id="detalleFugaHogar">
                    <label class="psi-field">
                        <span>Edad de fuga del hogar</span>
                        <input type="number" min="0" name="actitud[EdadFugaHogar]" value="<?= $h((string) ($actitud['EdadFugaHogar'] ?? '')); ?>">
                    </label>
                </div>

                <label class="psi-field">
                    <span>Otros (actitud)</span>
                    <textarea name="actitud[Otros]" rows="2"><?= $h((string) ($actitud['Otros'] ?? '')); ?></textarea>
                </label>

                <h3 class="psi-historia-substep">Vida social y laboral</h3>
                <div class="psi-form-grid">
                    <label class="psi-field">
                        <span>Cantidad de amigos</span>
                        <select name="vida_social[CantidadAmigos]">
                            <option value="">—</option>
                            <?php foreach ($opcionesCantidadAmigos as $op): ?>
                                <option value="<?= $op; ?>"<?= $sel($vida['CantidadAmigos'] ?? '', $op); ?>><?= $h($etq($op)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="psi-field">
                        <span>Grupo social</span>
                        <select name="vida_social[TipoGrupoSocial]">
                            <option value="">—</option>
                            <?php foreach ($opcionesGrupoSocial as $op): ?>
                                <option value="<?= $op; ?>"<?= $sel($vida['TipoGrupoSocial'] ?? '', $op); ?>><?= $h($etq($op)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="psi-field">
                        <span>Situación laboral</span>
                        <select name="vida_social[SituacionLaboral]">
                            <option value="">—</option>
                            <?php foreach ($opcionesSituacionLaboral as $op): ?>
                                <option value="<?= $op; ?>"<?= $sel($vida['SituacionLaboral'] ?? '', $op); ?>><?= $h($etq($op)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="psi-field">
                        <span>Estabilidad laboral</span>
                        <select name="vida_social[EstabilidadLaboral]">
                            <option value="">—</option>
                            <?php foreach ($opcionesEstabilidadLaboral as $op): ?>
                                <option value="<?= $op; ?>"<?= $sel($vida['EstabilidadLaboral'] ?? '', $op); ?>><?= $h($etq($op)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="psi-field">
                        <span>Satisfacción laboral</span>
                        <select name="vida_social[SatisfaccionLaboral]">
                            <option value="">—</option>
                            <?php foreach ($opcionesSatisfaccionLaboral as $op): ?>
                                <option value="<?= $op; ?>"<?= $sel($vida['SatisfaccionLaboral'] ?? '', $op); ?>><?= $h($etq($op)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="psi-field">
                        <span>Adaptación laboral</span>
                        <select name="vida_social[AdaptacionLaboral]">
                            <option value="">—</option>
                            <?php foreach ($opcionesAdaptacionLaboral as $op): ?>
                                <option value="<?= $op; ?>"<?= $sel($vida['AdaptacionLaboral'] ?? '', $op); ?>><?= $h($etq($op)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <label class="psi-inline-check">
                    <input type="checkbox" name="vida_social[ManejoDineroAdecuado]" value="1" <?= $chk($vida['ManejoDineroAdecuado'] ?? null); ?>>
                    Manejo del dinero adecuado
                </label>

                <label class="psi-field">
                    <span>Actividades de tiempo libre</span>
                    <textarea name="vida_social[ActividadesTiempoLibre]" rows="2"><?= $h((string) ($vida['ActividadesTiempoLibre'] ?? '')); ?></textarea>
                </label>

                <label class="psi-field">
                    <span>Observaciones de vida social</span>
                    <textarea name="vida_social[ObservacionesVidaSocial]" rows="2"><?= $h((string) ($vida['ObservacionesVidaSocial'] ?? '')); ?></textarea>
                </label>

                <h3 class="psi-historia-substep">Adicciones</h3>
                <div id="listaAdicciones" data-dynamic-list="adicciones">
                    <?php foreach ($adicciones as $i => $adi): ?>
                        <?php $persistidoAdi = !empty($adi['ClvAdiccion']); ?>
                        <div class="psi-dynamic-row<?= $persistidoAdi ? ' psi-dynamic-row--persistida' : ''; ?>">
                            <?php if ($persistidoAdi): ?>
                                <input type="hidden" name="adicciones[<?= (int) $i; ?>][ClvAdiccion]" value="<?= $h((string) $adi['ClvAdiccion']); ?>">
                            <?php endif; ?>
                            <label class="psi-field">
                                <span>Tipo de adicción</span>
                                <input type="text" maxlength="50" name="adicciones[<?= (int) $i; ?>][TipoAdiccion]" value="<?= $h((string) ($adi['TipoAdiccion'] ?? '')); ?>">
                            </label>
                            <label class="psi-field">
                                <span>Edad de inicio</span>
                                <input type="number" min="0" name="adicciones[<?= (int) $i; ?>][EdadInicio]" value="<?= $h((string) ($adi['EdadInicio'] ?? '')); ?>">
                            </label>
                            <label class="psi-field">
                                <span>Frecuencia</span>
                                <select name="adicciones[<?= (int) $i; ?>][Frecuencia]">
                                    <option value="">—</option>
                                    <?php foreach ($opcionesFrecuenciaAdiccion as $op): ?>
                                        <option value="<?= $op; ?>"<?= $sel($adi['Frecuencia'] ?? '', $op); ?>><?= $h($etq($op)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="psi-field">
                                <span>Estado de consumo</span>
                                <select name="adicciones[<?= (int) $i; ?>][EstadoConsumo]">
                                    <option value="">—</option>
                                    <?php foreach ($opcionesEstadoConsumo as $op): ?>
                                        <option value="<?= $op; ?>"<?= $sel($adi['EstadoConsumo'] ?? '', $op); ?>><?= $h($etq($op)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="psi-field">
                                <span>Conflictos asociados</span>
                                <textarea name="adicciones[<?= (int) $i; ?>][ConflictosAsociados]" rows="2"><?= $h((string) ($adi['ConflictosAsociados'] ?? '')); ?></textarea>
                            </label>
                            <label class="psi-inline-check psi-cond-toggle">
                                <input
                                    type="checkbox"
                                    class="psi-toggle-tratamiento"
                                    name="adicciones[<?= (int) $i; ?>][TratamientoRecibido]"
                                    value="1"
                                    <?= $chk($adi['TratamientoRecibido'] ?? null); ?>
                                >
                                Recibió tratamiento
                            </label>
                            <div class="psi-cond-detail<?= empty($adi['TratamientoRecibido']) ? ' is-hidden' : ''; ?>">
                                <label class="psi-field">
                                    <span>Descripción del tratamiento</span>
                                    <textarea name="adicciones[<?= (int) $i; ?>][DescripcionTratamiento]" rows="2"><?= $h((string) ($adi['DescripcionTratamiento'] ?? '')); ?></textarea>
                                </label>
                            </div>
                            <label class="psi-field">
                                <span>Observaciones</span>
                                <textarea name="adicciones[<?= (int) $i; ?>][ObservacionesAdiccion]" rows="2"><?= $h((string) ($adi['ObservacionesAdiccion'] ?? '')); ?></textarea>
                            </label>
                            <?php if (!$persistidoAdi): ?>
                                <button type="button" class="psi-expediente-btn-secondary psi-rec-remove" data-remove-new-row>
                                    Quitar fila nueva
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="psi-expediente-btn-secondary" data-add-row="listaAdicciones">
                    Agregar adicción
                </button>
            </div>

            <!-- Paso 5: Examen mental -->
            <div class="psi-historia-step<?= $pasoInicial === 5 ? ' is-active' : ''; ?>" data-step="5">
                <h2 class="psi-historia-step__title">5. Examen mental</h2>
                <p class="psi-historia-step__intro">Examen mental inicial del paciente.</p>

                <div class="psi-form-grid">
                    <label class="psi-field">
                        <span>Conciencia</span>
                        <input type="text" maxlength="100" name="examen_mental[Conciencia]" value="<?= $h((string) ($examen['Conciencia'] ?? '')); ?>">
                    </label>
                    <label class="psi-field">
                        <span>Orientación</span>
                        <input type="text" maxlength="100" name="examen_mental[Orientacion]" value="<?= $h((string) ($examen['Orientacion'] ?? '')); ?>">
                    </label>
                    <label class="psi-field">
                        <span>Inteligencia</span>
                        <input type="text" maxlength="100" name="examen_mental[Inteligencia]" value="<?= $h((string) ($examen['Inteligencia'] ?? '')); ?>">
                    </label>
                    <label class="psi-field">
                        <span>Atención</span>
                        <input type="text" maxlength="100" name="examen_mental[Atencion]" value="<?= $h((string) ($examen['Atencion'] ?? '')); ?>">
                    </label>
                    <label class="psi-field">
                        <span>Memoria</span>
                        <input type="text" maxlength="150" name="examen_mental[Memoria]" value="<?= $h((string) ($examen['Memoria'] ?? '')); ?>">
                    </label>
                    <label class="psi-field">
                        <span>Lenguaje</span>
                        <small class="psi-field-tip">Fluidez, articulación, comprensión.</small>
                        <textarea name="examen_mental[Lenguaje]" rows="2"><?= $h((string) ($examen['Lenguaje'] ?? '')); ?></textarea>
                    </label>
                </div>

                <label class="psi-inline-check">
                    <input type="checkbox" name="examen_mental[InstintosConservados]" value="1" <?= $chk($examen['InstintosConservados'] ?? null); ?>>
                    Instintos conservados
                </label>

                <label class="psi-field psi-field--full">
                    <span>Pensamiento</span>
                    <textarea name="examen_mental[Pensamiento]" rows="2"><?= $h((string) ($examen['Pensamiento'] ?? '')); ?></textarea>
                </label>
                <label class="psi-field psi-field--full">
                    <span>Afectividad</span>
                    <textarea name="examen_mental[Afectividad]" rows="2"><?= $h((string) ($examen['Afectividad'] ?? '')); ?></textarea>
                </label>
                <label class="psi-field psi-field--full">
                    <span>Sensopercepción</span>
                    <textarea name="examen_mental[Sensopercepcion]" rows="2"><?= $h((string) ($examen['Sensopercepcion'] ?? '')); ?></textarea>
                </label>
                <label class="psi-field psi-field--full">
                    <span>Psicomotricidad</span>
                    <textarea name="examen_mental[Psicomotricidad]" rows="2"><?= $h((string) ($examen['Psicomotricidad'] ?? '')); ?></textarea>
                </label>
                <label class="psi-field psi-field--full">
                    <span>Hábitos</span>
                    <textarea name="examen_mental[Habitos]" rows="2"><?= $h((string) ($examen['Habitos'] ?? '')); ?></textarea>
                </label>
                <label class="psi-field psi-field--full">
                    <span>Observaciones del examen</span>
                    <textarea name="examen_mental[ObservacionesExamen]" rows="2"><?= $h((string) ($examen['ObservacionesExamen'] ?? '')); ?></textarea>
                </label>
            </div>

            <!-- Paso 6: Reactivos -->
            <div class="psi-historia-step<?= $pasoInicial === 6 ? ' is-active' : ''; ?>" data-step="6">
                <h2 class="psi-historia-step__title">6. Reactivos</h2>
                <p class="psi-historia-step__intro">Reactivos psicológicos aplicados y sus resultados.</p>
                <small class="psi-field-tip">Todo reactivo con nombre debe incluir su fecha de aplicación.</small>

                <div id="listaReactivos" data-dynamic-list="reactivos">
                    <?php foreach ($reactivos as $i => $rea): ?>
                        <?php $persistidoRea = !empty($rea['ClvReact']); ?>
                        <div class="psi-dynamic-row<?= $persistidoRea ? ' psi-dynamic-row--persistida' : ''; ?>">
                            <?php if ($persistidoRea): ?>
                                <input type="hidden" name="reactivos[<?= (int) $i; ?>][ClvReact]" value="<?= $h((string) $rea['ClvReact']); ?>">
                            <?php endif; ?>
                            <label class="psi-field">
                                <span>Nombre del reactivo</span>
                                <input type="text" maxlength="100" name="reactivos[<?= (int) $i; ?>][NombreReactivo]" value="<?= $h((string) ($rea['NombreReactivo'] ?? '')); ?>">
                            </label>
                            <label class="psi-field">
                                <span>Fecha de aplicación</span>
                                <input type="date" name="reactivos[<?= (int) $i; ?>][FechaAplicacion]" value="<?= $h((string) ($rea['FechaAplicacion'] ?? date('Y-m-d'))); ?>">
                            </label>
                            <label class="psi-field">
                                <span>Resultado</span>
                                <textarea name="reactivos[<?= (int) $i; ?>][ResultadoReactivo]" rows="2"><?= $h((string) ($rea['ResultadoReactivo'] ?? '')); ?></textarea>
                            </label>
                            <label class="psi-field">
                                <span>Interpretación</span>
                                <textarea name="reactivos[<?= (int) $i; ?>][InterpretacionReactivo]" rows="2"><?= $h((string) ($rea['InterpretacionReactivo'] ?? '')); ?></textarea>
                            </label>
                            <?php if (!$persistidoRea): ?>
                                <button type="button" class="psi-expediente-btn-secondary psi-rec-remove" data-remove-new-row>
                                    Quitar fila nueva
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="psi-expediente-btn-secondary" data-add-row="listaReactivos">
                    Agregar reactivo
                </button>
            </div>

            <!-- Paso 7: Apreciación diagnóstica -->
            <div class="psi-historia-step<?= $pasoInicial === 7 ? ' is-active' : ''; ?>" data-step="7">
                <h2 class="psi-historia-step__title">7. Apreciación diagnóstica</h2>
                <p class="psi-historia-step__intro">Impresión clínica y plan de tratamiento inicial.</p>

                <label class="psi-field">
                    <span>Apreciación de la personalidad</span>
                    <textarea name="apreciacion[ApreciacionPersonalidad]" rows="2"><?= $h((string) ($apreciacion['ApreciacionPersonalidad'] ?? '')); ?></textarea>
                </label>

                <label class="psi-field">
                    <span>Diagnóstico inicial</span>
                    <textarea name="apreciacion[DiagnosticoInicial]" rows="3"><?= $h((string) ($apreciacion['DiagnosticoInicial'] ?? '')); ?></textarea>
                </label>

                <div class="psi-form-grid">
                    <label class="psi-field">
                        <span>Código diagnóstico</span>
                        <input type="text" maxlength="20" name="apreciacion[CodigoDiagnostico]" value="<?= $h((string) ($apreciacion['CodigoDiagnostico'] ?? '')); ?>">
                    </label>
                    <label class="psi-field">
                        <span>Sistema de clasificación</span>
                        <select name="apreciacion[SistemaClasificacion]">
                            <option value="">—</option>
                            <?php foreach ($opcionesSistemaClasificacion as $op): ?>
                                <option value="<?= $op; ?>"<?= $sel($apreciacion['SistemaClasificacion'] ?? '', $op); ?>><?= $op; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <label class="psi-field">
                    <span>Plan de tratamiento</span>
                    <textarea name="apreciacion[PlanTratamiento]" rows="2"><?= $h((string) ($apreciacion['PlanTratamiento'] ?? '')); ?></textarea>
                </label>
                <label class="psi-field">
                    <span>Recomendaciones iniciales</span>
                    <textarea name="apreciacion[RecomendacionesIniciales]" rows="2"><?= $h((string) ($apreciacion['RecomendacionesIniciales'] ?? '')); ?></textarea>
                </label>
                <label class="psi-field">
                    <span>Pronóstico inicial</span>
                    <textarea name="apreciacion[PronosticoInicial]" rows="2"><?= $h((string) ($apreciacion['PronosticoInicial'] ?? '')); ?></textarea>
                </label>
                <label class="psi-field">
                    <span>Observaciones diagnósticas</span>
                    <textarea name="apreciacion[ObservacionesDiagnosticas]" rows="2"><?= $h((string) ($apreciacion['ObservacionesDiagnosticas'] ?? '')); ?></textarea>
                </label>
            </div>

            <!-- Paso 8: Revisión -->
            <div class="psi-historia-step<?= $pasoInicial === 8 ? ' is-active' : ''; ?>" data-step="8">
                <h2 class="psi-historia-step__title">8. Revisión</h2>
                <p class="psi-historia-step__intro">Verifica el resumen antes de guardar la historia clínica.</p>

                <div id="resumenRevision" class="psi-resumen-grid" aria-live="polite">
                    <p class="psi-resumen-loading">Generando resumen…</p>
                </div>

                <div class="psi-historia-confirm-note">
                    Antes de guardar, verifica que la información capturada sea correcta. Al presionar
                    "<?= $esEdicion ? 'Actualizar historia' : 'Guardar historia clínica'; ?>" se registrará
                    de forma permanente en el expediente clínico del paciente.
                </div>
            </div>

            <div class="psi-historia-nav">
                <a href="<?= $h($volver); ?>" class="psi-expediente-btn-secondary">Cancelar</a>
                <span class="psi-historia-nav__spacer"></span>
                <button type="button" class="psi-expediente-btn-secondary" id="btnPasoAnterior">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                    Anterior
                </button>
                <button type="button" class="psi-expediente-btn" id="btnPasoSiguiente">
                    Siguiente
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </button>
                <button type="submit" class="psi-expediente-btn" id="btnGuardarHistoria">
                    <?= $esEdicion ? 'Actualizar historia' : 'Guardar historia clínica'; ?>
                </button>
            </div>
        </form>

    </div>

    <template id="tplAntecedentePat">
        <div class="psi-dynamic-row">
            <label class="psi-field">
                <span>Tipo</span>
                <select name="antecedentes_patologicos[__INDEX__][TipoAntecedente]">
                    <option value="">Selecciona…</option>
                    <?php foreach ($tiposPat as $tipo): ?>
                        <option value="<?= $tipo; ?>"><?= $h($etq($tipo)); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="psi-inline-check psi-cond-toggle">
                <input type="checkbox" class="psi-toggle-presenta" name="antecedentes_patologicos[__INDEX__][PresentaAntecedente]" value="1">
                Presenta actualmente
            </label>
            <div class="psi-cond-detail is-hidden">
                <label class="psi-field">
                    <span>Descripción</span>
                    <textarea name="antecedentes_patologicos[__INDEX__][DescripcionAntecedente]" rows="2"></textarea>
                </label>
                <label class="psi-field">
                    <span>Tratamiento actual</span>
                    <textarea name="antecedentes_patologicos[__INDEX__][TratamientoActual]" rows="2"></textarea>
                </label>
            </div>
            <button type="button" class="psi-expediente-btn-secondary psi-rec-remove" data-remove-new-row>
                Quitar fila nueva
            </button>
        </div>
    </template>

    <template id="tplAntecedenteFam">
        <div class="psi-dynamic-row">
            <label class="psi-field">
                <span>Tipo</span>
                <select name="antecedentes_familiares[__INDEX__][TipoAntecedenteFam]">
                    <option value="">Selecciona…</option>
                    <?php foreach ($tiposFam as $tipo): ?>
                        <option value="<?= $tipo; ?>"><?= $h($etq($tipo)); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="psi-inline-check psi-cond-toggle">
                <input type="checkbox" class="psi-toggle-presenta-fam" name="antecedentes_familiares[__INDEX__][PresentaAntecedenteFam]" value="1">
                Presenta actualmente
            </label>
            <div class="psi-cond-detail is-hidden">
                <label class="psi-field">
                    <span>Familiar relacionado</span>
                    <input type="text" maxlength="100" name="antecedentes_familiares[__INDEX__][FamiliarRelacionado]">
                </label>
                <label class="psi-field">
                    <span>Descripción</span>
                    <textarea name="antecedentes_familiares[__INDEX__][DescripcionAntFam]" rows="2"></textarea>
                </label>
            </div>
            <button type="button" class="psi-expediente-btn-secondary psi-rec-remove" data-remove-new-row>
                Quitar fila nueva
            </button>
        </div>
    </template>

    <template id="tplAdiccion">
        <div class="psi-dynamic-row">
            <label class="psi-field">
                <span>Tipo de adicción</span>
                <input type="text" maxlength="50" name="adicciones[__INDEX__][TipoAdiccion]">
            </label>
            <label class="psi-field">
                <span>Edad de inicio</span>
                <input type="number" min="0" name="adicciones[__INDEX__][EdadInicio]">
            </label>
            <label class="psi-field">
                <span>Frecuencia</span>
                <select name="adicciones[__INDEX__][Frecuencia]">
                    <option value="">—</option>
                    <?php foreach ($opcionesFrecuenciaAdiccion as $op): ?>
                        <option value="<?= $op; ?>"><?= $h($etq($op)); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="psi-field">
                <span>Estado de consumo</span>
                <select name="adicciones[__INDEX__][EstadoConsumo]">
                    <option value="">—</option>
                    <?php foreach ($opcionesEstadoConsumo as $op): ?>
                        <option value="<?= $op; ?>"><?= $h($etq($op)); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="psi-field">
                <span>Conflictos asociados</span>
                <textarea name="adicciones[__INDEX__][ConflictosAsociados]" rows="2"></textarea>
            </label>
            <label class="psi-inline-check psi-cond-toggle">
                <input type="checkbox" class="psi-toggle-tratamiento" name="adicciones[__INDEX__][TratamientoRecibido]" value="1">
                Recibió tratamiento
            </label>
            <div class="psi-cond-detail is-hidden">
                <label class="psi-field">
                    <span>Descripción del tratamiento</span>
                    <textarea name="adicciones[__INDEX__][DescripcionTratamiento]" rows="2"></textarea>
                </label>
            </div>
            <label class="psi-field">
                <span>Observaciones</span>
                <textarea name="adicciones[__INDEX__][ObservacionesAdiccion]" rows="2"></textarea>
            </label>
            <button type="button" class="psi-expediente-btn-secondary psi-rec-remove" data-remove-new-row>
                Quitar fila nueva
            </button>
        </div>
    </template>

    <template id="tplReactivo">
        <div class="psi-dynamic-row">
            <label class="psi-field">
                <span>Nombre del reactivo</span>
                <input type="text" maxlength="100" name="reactivos[__INDEX__][NombreReactivo]">
            </label>
            <label class="psi-field">
                <span>Fecha de aplicación</span>
                <input type="date" name="reactivos[__INDEX__][FechaAplicacion]">
            </label>
            <label class="psi-field">
                <span>Resultado</span>
                <textarea name="reactivos[__INDEX__][ResultadoReactivo]" rows="2"></textarea>
            </label>
            <label class="psi-field">
                <span>Interpretación</span>
                <textarea name="reactivos[__INDEX__][InterpretacionReactivo]" rows="2"></textarea>
            </label>
            <button type="button" class="psi-expediente-btn-secondary psi-rec-remove" data-remove-new-row>
                Quitar fila nueva
            </button>
        </div>
    </template>

</section>
