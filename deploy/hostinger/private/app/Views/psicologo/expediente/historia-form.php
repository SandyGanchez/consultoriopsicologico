<?php

use App\Core\Session;
use App\Helpers\Helper;

$paciente = $paciente ?? [];
$modo = $modo ?? 'crear';
$completo = $completo ?? null;
$historial = $historial ?? null;
$citaHabilitadora = $citaHabilitadora ?? null;
$esEdicion = $modo === 'editar';
$clvPac = (string) ($paciente['ClvPac'] ?? '');
$nombre = trim((string) ($paciente['NombrePaciente'] ?? ''));
$csrf = Session::csrfToken();

$estado = $completo['estado'] ?? [];
$psico = $completo['psicoanamnesis'] ?? [];
$actitud = $completo['actitud'] ?? [];
$vida = $completo['vida_social'] ?? [];
$examen = $completo['examen_mental'] ?? [];
$apreciacion = $completo['apreciaciones'][0] ?? [];
$antsPat = $completo['antecedentes_patologicos'] ?? [['TipoAntecedente' => '']];
$antsFam = $completo['antecedentes_familiares'] ?? [['TipoAntecedenteFam' => '']];
$adicciones = $completo['adicciones'] ?? [['TipoAdiccion' => '']];
$reactivos = $completo['reactivos'] ?? [['NombreReactivo' => '', 'FechaAplicacion' => '']];

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

$action = $esEdicion
    ? Helper::baseUrl('psicologo/pacientes/historia/actualizar')
    : Helper::baseUrl('psicologo/pacientes/historia/guardar');

$volver = Helper::baseUrl(
    'psicologo/pacientes/ver/' .
    rawurlencode($clvPac) .
    '/expediente?tab=historia'
);

$fechaEntrevista = '';
if (!empty($historial['FechaEntrevistaInicial'])) {
    $fechaEntrevista = date(
        'Y-m-d',
        strtotime((string) $historial['FechaEntrevistaInicial'])
    );
} elseif (!empty($citaHabilitadora['FechaCita'])) {
    $fechaEntrevista = (string) $citaHabilitadora['FechaCita'];
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
    'FugaHogar' => 'Fuga del hogar',
    'SintomasNeuroticos' => 'Síntomas neuróticos',
    'ProblemasEscolares' => 'Problemas escolares'
];

?>

<section
    class="psi-expediente-page psi-historia-form-page"
    data-unsaved-guard="1"
>

    <a href="<?= htmlspecialchars($volver, ENT_QUOTES, 'UTF-8'); ?>" class="psi-expediente-back">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
        Volver al expediente
    </a>

    <header class="psi-expediente-form-header">
        <h1>
            <?= $esEdicion
                ? 'Editar historia clínica inicial'
                : 'Crear historia clínica inicial'; ?>
        </h1>
        <p>
            Paciente:
            <?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>
            <?php if (!$esEdicion && $citaHabilitadora): ?>
                · Cita habilitadora:
                <?= htmlspecialchars(
                    date('d/m/Y', strtotime((string) $citaHabilitadora['FechaCita'])),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
                ·
                <?= htmlspecialchars(
                    (string) ($citaHabilitadora['NombreServicio'] ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            <?php endif; ?>
        </p>
    </header>

    <?php if (!empty($mensajeError)): ?>
        <div class="psi-expediente-alert psi-expediente-alert--error">
            <?= htmlspecialchars((string) $mensajeError, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form
        method="POST"
        action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>"
        class="psi-historia-form"
        id="formHistoriaClinica"
        autocomplete="off"
    >
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="ClvPac" value="<?= htmlspecialchars($clvPac, ENT_QUOTES, 'UTF-8'); ?>">

        <?php if ($esEdicion): ?>
            <input
                type="hidden"
                name="ClvHist"
                value="<?= htmlspecialchars(
                    (string) ($historial['ClvHist'] ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >
        <?php else: ?>
            <input
                type="hidden"
                name="ClvCita"
                value="<?= htmlspecialchars(
                    (string) ($citaHabilitadora['ClvCita'] ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >
        <?php endif; ?>

        <div class="accordion psi-historia-accordion" id="accordionHistoria">

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#secGeneral">
                        1. Datos generales de la evaluación
                    </button>
                </h2>
                <div id="secGeneral" class="accordion-collapse collapse show" data-bs-parent="#accordionHistoria">
                    <div class="accordion-body">
                        <label class="psi-field">
                            <span>Fecha de entrevista inicial</span>
                            <input type="date" name="FechaEntrevistaInicial" value="<?= htmlspecialchars($fechaEntrevista, ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secEstado">
                        2–3. Motivo de consulta y estado psicológico inicial
                    </button>
                </h2>
                <div id="secEstado" class="accordion-collapse collapse" data-bs-parent="#accordionHistoria">
                    <div class="accordion-body">
                        <label class="psi-field">
                            <span>Motivo de consulta *</span>
                            <textarea name="estado[MotivoConsulta]" rows="3" required><?= htmlspecialchars((string) ($estado['MotivoConsulta'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Síntomas referidos</span>
                            <textarea name="estado[SintomasReferidos]" rows="2"><?= htmlspecialchars((string) ($estado['SintomasReferidos'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <div class="psi-check-grid">
                            <?php foreach ($flagsEstado as $campo => $label): ?>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="estado[<?= htmlspecialchars($campo, ENT_QUOTES, 'UTF-8'); ?>]"
                                        value="1"
                                        <?= !empty($estado[$campo]) ? 'checked' : ''; ?>
                                    >
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <label class="psi-field">
                            <span>Otros estados</span>
                            <textarea name="estado[OtrosEstados]" rows="2"><?= htmlspecialchars((string) ($estado['OtrosEstados'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Observaciones iniciales</span>
                            <textarea name="estado[ObservacionesIniciales]" rows="2"><?= htmlspecialchars((string) ($estado['ObservacionesIniciales'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secAntPat">
                        4. Antecedentes patológicos
                    </button>
                </h2>
                <div id="secAntPat" class="accordion-collapse collapse" data-bs-parent="#accordionHistoria">
                    <div class="accordion-body">
                        <div id="listaAntecedentesPat" data-dynamic-list="antecedentes_patologicos">
                            <?php foreach ($antsPat as $i => $ant): ?>
                                <div class="psi-dynamic-row">
                                    <?php if (!empty($ant['ClvAntPat'])): ?>
                                        <input type="hidden" name="antecedentes_patologicos[<?= (int) $i; ?>][ClvAntPat]" value="<?= htmlspecialchars((string) $ant['ClvAntPat'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php endif; ?>
                                    <label class="psi-field">
                                        <span>Tipo</span>
                                        <select name="antecedentes_patologicos[<?= (int) $i; ?>][TipoAntecedente]">
                                            <option value="">Selecciona…</option>
                                            <?php foreach ($tiposPat as $tipo): ?>
                                                <option value="<?= $tipo; ?>" <?= (($ant['TipoAntecedente'] ?? '') === $tipo) ? 'selected' : ''; ?>>
                                                    <?= htmlspecialchars(str_replace('_', ' ', $tipo), ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label class="psi-inline-check">
                                        <input type="checkbox" name="antecedentes_patologicos[<?= (int) $i; ?>][PresentaAntecedente]" value="1" <?= !empty($ant['PresentaAntecedente']) ? 'checked' : ''; ?>>
                                        Presenta
                                    </label>
                                    <label class="psi-field">
                                        <span>Descripción</span>
                                        <textarea name="antecedentes_patologicos[<?= (int) $i; ?>][DescripcionAntecedente]" rows="2"><?= htmlspecialchars((string) ($ant['DescripcionAntecedente'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </label>
                                    <label class="psi-field">
                                        <span>Tratamiento actual</span>
                                        <textarea name="antecedentes_patologicos[<?= (int) $i; ?>][TratamientoActual]" rows="2"><?= htmlspecialchars((string) ($ant['TratamientoActual'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="psi-expediente-btn-secondary" data-add-row="listaAntecedentesPat">
                            Agregar antecedente
                        </button>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secAntFam">
                        5. Antecedentes familiares
                    </button>
                </h2>
                <div id="secAntFam" class="accordion-collapse collapse" data-bs-parent="#accordionHistoria">
                    <div class="accordion-body">
                        <div id="listaAntecedentesFam" data-dynamic-list="antecedentes_familiares">
                            <?php foreach ($antsFam as $i => $ant): ?>
                                <div class="psi-dynamic-row">
                                    <?php if (!empty($ant['ClvAntFam'])): ?>
                                        <input type="hidden" name="antecedentes_familiares[<?= (int) $i; ?>][ClvAntFam]" value="<?= htmlspecialchars((string) $ant['ClvAntFam'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php endif; ?>
                                    <label class="psi-field">
                                        <span>Tipo</span>
                                        <select name="antecedentes_familiares[<?= (int) $i; ?>][TipoAntecedenteFam]">
                                            <option value="">Selecciona…</option>
                                            <?php foreach ($tiposFam as $tipo): ?>
                                                <option value="<?= $tipo; ?>" <?= (($ant['TipoAntecedenteFam'] ?? '') === $tipo) ? 'selected' : ''; ?>>
                                                    <?= htmlspecialchars(str_replace('_', ' ', $tipo), ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label class="psi-inline-check">
                                        <input type="checkbox" name="antecedentes_familiares[<?= (int) $i; ?>][PresentaAntecedenteFam]" value="1" <?= !empty($ant['PresentaAntecedenteFam']) ? 'checked' : ''; ?>>
                                        Presenta
                                    </label>
                                    <label class="psi-field">
                                        <span>Familiar relacionado</span>
                                        <input type="text" name="antecedentes_familiares[<?= (int) $i; ?>][FamiliarRelacionado]" maxlength="100" value="<?= htmlspecialchars((string) ($ant['FamiliarRelacionado'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    </label>
                                    <label class="psi-field">
                                        <span>Descripción</span>
                                        <textarea name="antecedentes_familiares[<?= (int) $i; ?>][DescripcionAntFam]" rows="2"><?= htmlspecialchars((string) ($ant['DescripcionAntFam'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="psi-expediente-btn-secondary" data-add-row="listaAntecedentesFam">
                            Agregar antecedente
                        </button>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secPsico">
                        6. Psicoanamnesis familiar
                    </button>
                </h2>
                <div id="secPsico" class="accordion-collapse collapse" data-bs-parent="#accordionHistoria">
                    <div class="accordion-body psi-check-grid">
                        <label><input type="checkbox" name="psicoanamnesis[PadresJuntos]" value="1" <?= !empty($psico['PadresJuntos']) ? 'checked' : ''; ?>> Padres juntos</label>
                        <label><input type="checkbox" name="psicoanamnesis[PadreFallecido]" value="1" <?= !empty($psico['PadreFallecido']) ? 'checked' : ''; ?>> Padre fallecido</label>
                        <label><input type="checkbox" name="psicoanamnesis[MadreFallecida]" value="1" <?= !empty($psico['MadreFallecida']) ? 'checked' : ''; ?>> Madre fallecida</label>
                        <label><input type="checkbox" name="psicoanamnesis[ConflictoPadre]" value="1" <?= !empty($psico['ConflictoPadre']) ? 'checked' : ''; ?>> Conflicto con padre</label>
                        <label><input type="checkbox" name="psicoanamnesis[ConflictoMadre]" value="1" <?= !empty($psico['ConflictoMadre']) ? 'checked' : ''; ?>> Conflicto con madre</label>
                        <label class="psi-field">
                            <span>Actitud de los padres</span>
                            <select name="psicoanamnesis[ActitudPadres]">
                                <option value="">—</option>
                                <?php foreach (['AFECTUOSA','SOBREPROTECTORA','INDIFERENTE','HOSTIL','INEXISTENTE','OTRA'] as $op): ?>
                                    <option value="<?= $op; ?>" <?= (($psico['ActitudPadres'] ?? '') === $op) ? 'selected' : ''; ?>><?= $op; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="psi-field">
                            <span>Número de hermanos</span>
                            <input type="number" min="0" name="psicoanamnesis[NumeroHermanos]" value="<?= htmlspecialchars((string) ($psico['NumeroHermanos'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label class="psi-field">
                            <span>Observaciones familiares</span>
                            <textarea name="psicoanamnesis[ObservacionesFamiliares]" rows="2"><?= htmlspecialchars((string) ($psico['ObservacionesFamiliares'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secActitud">
                        7. Actitud y conducta inicial
                    </button>
                </h2>
                <div id="secActitud" class="accordion-collapse collapse" data-bs-parent="#accordionHistoria">
                    <div class="accordion-body">
                        <div class="psi-check-grid">
                            <?php foreach ($flagsActitud as $campo => $label): ?>
                                <label>
                                    <input type="checkbox" name="actitud[<?= htmlspecialchars($campo, ENT_QUOTES, 'UTF-8'); ?>]" value="1" <?= !empty($actitud[$campo]) ? 'checked' : ''; ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <label class="psi-field">
                            <span>Edad de fuga del hogar</span>
                            <input type="number" min="0" name="actitud[EdadFugaHogar]" value="<?= htmlspecialchars((string) ($actitud['EdadFugaHogar'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label class="psi-field">
                            <span>Otros</span>
                            <textarea name="actitud[Otros]" rows="2"><?= htmlspecialchars((string) ($actitud['Otros'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secVida">
                        8. Vida social y laboral
                    </button>
                </h2>
                <div id="secVida" class="accordion-collapse collapse" data-bs-parent="#accordionHistoria">
                    <div class="accordion-body psi-form-grid">
                        <label class="psi-field">
                            <span>Cantidad de amigos</span>
                            <select name="vida_social[CantidadAmigos]">
                                <option value="">—</option>
                                <?php foreach (['MUCHOS','POCOS','NINGUNO'] as $op): ?>
                                    <option value="<?= $op; ?>" <?= (($vida['CantidadAmigos'] ?? '') === $op) ? 'selected' : ''; ?>><?= $op; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="psi-field">
                            <span>Grupo social</span>
                            <select name="vida_social[TipoGrupoSocial]">
                                <option value="">—</option>
                                <?php foreach (['DISOCIAL','MIXTO','SANO','SIN_GRUPO'] as $op): ?>
                                    <option value="<?= $op; ?>" <?= (($vida['TipoGrupoSocial'] ?? '') === $op) ? 'selected' : ''; ?>><?= $op; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="psi-field">
                            <span>Situación laboral</span>
                            <select name="vida_social[SituacionLaboral]">
                                <option value="">—</option>
                                <?php foreach (['REALIZADO','FRUSTRADO','DESEMPLEADO','DESPEDIDO','SANCIONADO','REUBICADO','REINGRESADO','NO_APLICA','OTRO'] as $op): ?>
                                    <option value="<?= $op; ?>" <?= (($vida['SituacionLaboral'] ?? '') === $op) ? 'selected' : ''; ?>><?= $op; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="psi-field">
                            <span>Actividades de tiempo libre</span>
                            <textarea name="vida_social[ActividadesTiempoLibre]" rows="2"><?= htmlspecialchars((string) ($vida['ActividadesTiempoLibre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secAdic">
                        9. Adicciones
                    </button>
                </h2>
                <div id="secAdic" class="accordion-collapse collapse" data-bs-parent="#accordionHistoria">
                    <div class="accordion-body">
                        <div id="listaAdicciones" data-dynamic-list="adicciones">
                            <?php foreach ($adicciones as $i => $adi): ?>
                                <div class="psi-dynamic-row">
                                    <?php if (!empty($adi['ClvAdiccion'])): ?>
                                        <input type="hidden" name="adicciones[<?= (int) $i; ?>][ClvAdiccion]" value="<?= htmlspecialchars((string) $adi['ClvAdiccion'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php endif; ?>
                                    <label class="psi-field">
                                        <span>Tipo de adicción</span>
                                        <input type="text" maxlength="50" name="adicciones[<?= (int) $i; ?>][TipoAdiccion]" value="<?= htmlspecialchars((string) ($adi['TipoAdiccion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    </label>
                                    <label class="psi-field">
                                        <span>Edad de inicio</span>
                                        <input type="number" min="0" name="adicciones[<?= (int) $i; ?>][EdadInicio]" value="<?= htmlspecialchars((string) ($adi['EdadInicio'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    </label>
                                    <label class="psi-field">
                                        <span>Frecuencia</span>
                                        <select name="adicciones[<?= (int) $i; ?>][Frecuencia]">
                                            <option value="">—</option>
                                            <?php foreach (['FRECUENTE','POCO_FRECUENTE','OCASIONAL','NO_ESPECIFICADA'] as $op): ?>
                                                <option value="<?= $op; ?>" <?= (($adi['Frecuencia'] ?? '') === $op) ? 'selected' : ''; ?>><?= $op; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label class="psi-field">
                                        <span>Observaciones</span>
                                        <textarea name="adicciones[<?= (int) $i; ?>][ObservacionesAdiccion]" rows="2"><?= htmlspecialchars((string) ($adi['ObservacionesAdiccion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="psi-expediente-btn-secondary" data-add-row="listaAdicciones">
                            Agregar adicción
                        </button>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secExamen">
                        10. Examen mental inicial
                    </button>
                </h2>
                <div id="secExamen" class="accordion-collapse collapse" data-bs-parent="#accordionHistoria">
                    <div class="accordion-body psi-form-grid">
                        <?php
                        $camposExamen = [
                            'Conciencia' => 'Conciencia',
                            'Orientacion' => 'Orientación',
                            'Inteligencia' => 'Inteligencia',
                            'Atencion' => 'Atención',
                            'Memoria' => 'Memoria'
                        ];
                        foreach ($camposExamen as $campo => $label):
                        ?>
                            <label class="psi-field">
                                <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                                <input type="text" name="examen_mental[<?= htmlspecialchars($campo, ENT_QUOTES, 'UTF-8'); ?>]" value="<?= htmlspecialchars((string) ($examen[$campo] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            </label>
                        <?php endforeach; ?>
                        <label class="psi-field psi-field--full">
                            <span>Pensamiento</span>
                            <textarea name="examen_mental[Pensamiento]" rows="2"><?= htmlspecialchars((string) ($examen['Pensamiento'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field psi-field--full">
                            <span>Afectividad</span>
                            <textarea name="examen_mental[Afectividad]" rows="2"><?= htmlspecialchars((string) ($examen['Afectividad'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field psi-field--full">
                            <span>Observaciones del examen</span>
                            <textarea name="examen_mental[ObservacionesExamen]" rows="2"><?= htmlspecialchars((string) ($examen['ObservacionesExamen'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secReact">
                        11–12. Reactivos psicológicos y resultados
                    </button>
                </h2>
                <div id="secReact" class="accordion-collapse collapse" data-bs-parent="#accordionHistoria">
                    <div class="accordion-body">
                        <div id="listaReactivos" data-dynamic-list="reactivos">
                            <?php foreach ($reactivos as $i => $rea): ?>
                                <div class="psi-dynamic-row">
                                    <?php if (!empty($rea['ClvReact'])): ?>
                                        <input type="hidden" name="reactivos[<?= (int) $i; ?>][ClvReact]" value="<?= htmlspecialchars((string) $rea['ClvReact'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php endif; ?>
                                    <label class="psi-field">
                                        <span>Nombre del reactivo</span>
                                        <input type="text" maxlength="100" name="reactivos[<?= (int) $i; ?>][NombreReactivo]" value="<?= htmlspecialchars((string) ($rea['NombreReactivo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    </label>
                                    <label class="psi-field">
                                        <span>Fecha de aplicación</span>
                                        <input type="date" name="reactivos[<?= (int) $i; ?>][FechaAplicacion]" value="<?= htmlspecialchars((string) ($rea['FechaAplicacion'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8'); ?>">
                                    </label>
                                    <label class="psi-field">
                                        <span>Resultado</span>
                                        <textarea name="reactivos[<?= (int) $i; ?>][ResultadoReactivo]" rows="2"><?= htmlspecialchars((string) ($rea['ResultadoReactivo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </label>
                                    <label class="psi-field">
                                        <span>Interpretación</span>
                                        <textarea name="reactivos[<?= (int) $i; ?>][InterpretacionReactivo]" rows="2"><?= htmlspecialchars((string) ($rea['InterpretacionReactivo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="psi-expediente-btn-secondary" data-add-row="listaReactivos">
                            Agregar reactivo
                        </button>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secDiag">
                        13. Apreciación diagnóstica
                    </button>
                </h2>
                <div id="secDiag" class="accordion-collapse collapse" data-bs-parent="#accordionHistoria">
                    <div class="accordion-body">
                        <label class="psi-field">
                            <span>Diagnóstico inicial</span>
                            <textarea name="apreciacion[DiagnosticoInicial]" rows="3"><?= htmlspecialchars((string) ($apreciacion['DiagnosticoInicial'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Código diagnóstico</span>
                            <input type="text" maxlength="20" name="apreciacion[CodigoDiagnostico]" value="<?= htmlspecialchars((string) ($apreciacion['CodigoDiagnostico'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label class="psi-field">
                            <span>Sistema de clasificación</span>
                            <select name="apreciacion[SistemaClasificacion]">
                                <option value="">—</option>
                                <?php foreach (['DSM5','CIE10','CIE11','OTRO'] as $op): ?>
                                    <option value="<?= $op; ?>" <?= (($apreciacion['SistemaClasificacion'] ?? '') === $op) ? 'selected' : ''; ?>><?= $op; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="psi-field">
                            <span>Plan de tratamiento</span>
                            <textarea name="apreciacion[PlanTratamiento]" rows="2"><?= htmlspecialchars((string) ($apreciacion['PlanTratamiento'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Recomendaciones iniciales</span>
                            <textarea name="apreciacion[RecomendacionesIniciales]" rows="2"><?= htmlspecialchars((string) ($apreciacion['RecomendacionesIniciales'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                        <label class="psi-field">
                            <span>Pronóstico inicial</span>
                            <textarea name="apreciacion[PronosticoInicial]" rows="2"><?= htmlspecialchars((string) ($apreciacion['PronosticoInicial'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                    </div>
                </div>
            </div>

        </div>

        <div class="psi-historia-actions">
            <a href="<?= htmlspecialchars($volver, ENT_QUOTES, 'UTF-8'); ?>" class="psi-expediente-btn-secondary">
                Cancelar
            </a>
            <button type="submit" class="psi-expediente-btn" id="btnGuardarHistoria">
                <?= $esEdicion ? 'Actualizar historia' : 'Guardar historia clínica'; ?>
            </button>
        </div>
    </form>

    <template id="tplAntecedentePat">
        <div class="psi-dynamic-row">
            <label class="psi-field">
                <span>Tipo</span>
                <select name="antecedentes_patologicos[__INDEX__][TipoAntecedente]">
                    <option value="">Selecciona…</option>
                    <?php foreach ($tiposPat as $tipo): ?>
                        <option value="<?= $tipo; ?>"><?= htmlspecialchars(str_replace('_', ' ', $tipo), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="psi-inline-check">
                <input type="checkbox" name="antecedentes_patologicos[__INDEX__][PresentaAntecedente]" value="1">
                Presenta
            </label>
            <label class="psi-field">
                <span>Descripción</span>
                <textarea name="antecedentes_patologicos[__INDEX__][DescripcionAntecedente]" rows="2"></textarea>
            </label>
            <label class="psi-field">
                <span>Tratamiento actual</span>
                <textarea name="antecedentes_patologicos[__INDEX__][TratamientoActual]" rows="2"></textarea>
            </label>
        </div>
    </template>

    <template id="tplAntecedenteFam">
        <div class="psi-dynamic-row">
            <label class="psi-field">
                <span>Tipo</span>
                <select name="antecedentes_familiares[__INDEX__][TipoAntecedenteFam]">
                    <option value="">Selecciona…</option>
                    <?php foreach ($tiposFam as $tipo): ?>
                        <option value="<?= $tipo; ?>"><?= htmlspecialchars(str_replace('_', ' ', $tipo), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="psi-inline-check">
                <input type="checkbox" name="antecedentes_familiares[__INDEX__][PresentaAntecedenteFam]" value="1">
                Presenta
            </label>
            <label class="psi-field">
                <span>Familiar relacionado</span>
                <input type="text" maxlength="100" name="antecedentes_familiares[__INDEX__][FamiliarRelacionado]">
            </label>
            <label class="psi-field">
                <span>Descripción</span>
                <textarea name="antecedentes_familiares[__INDEX__][DescripcionAntFam]" rows="2"></textarea>
            </label>
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
                    <?php foreach (['FRECUENTE','POCO_FRECUENTE','OCASIONAL','NO_ESPECIFICADA'] as $op): ?>
                        <option value="<?= $op; ?>"><?= $op; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="psi-field">
                <span>Observaciones</span>
                <textarea name="adicciones[__INDEX__][ObservacionesAdiccion]" rows="2"></textarea>
            </label>
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
        </div>
    </template>

</section>
