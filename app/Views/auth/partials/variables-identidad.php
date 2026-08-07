<?php

use App\Helpers\Helper;

$identidadPlataforma = !empty($identidadPlataforma)
    || !empty($esNavbarGlobal)
    || !empty($esPortadaPlataforma);

$consultorioData = is_array($consultorio ?? null) ? $consultorio : [];

if ($identidadPlataforma) {
    $nombreCons = 'PsicoMatch';
    $slogan = 'Tu bienestar emocional es nuestra prioridad';
    $logoUrl = Helper::logotipoPlataformaUrl();
    $portadaUrl = null;
    $iniciales = 'PM';
    $esNavbarGlobal = true;
    $esPortadaPlataforma = true;
    $consultorio = null;
} else {
    $nombreCons = trim((string) ($consultorioData['NombreCons'] ?? ''));

    if ($nombreCons === '') {
        $nombreCons = 'Consultorio';
    }

    $slogan = trim((string) ($consultorioData['Slogan'] ?? ''));

    $logoUrl = Helper::logotipoConsultorioUrl(
        $consultorioData['LogotipoCons'] ?? null,
        true
    );

    $portadaUrl = Helper::imagenPortadaConsultorioUrl(
        $consultorioData['ImagenPortada'] ?? null
    );

    $palabrasNombre = preg_split('/\s+/u', $nombreCons) ?: [];
    $iniciales = '';

    foreach (array_slice($palabrasNombre, 0, 2) as $palabra) {
        $iniciales .= mb_strtoupper(mb_substr($palabra, 0, 1));
    }

    if ($iniciales === '') {
        $iniciales = 'C';
    }
}
