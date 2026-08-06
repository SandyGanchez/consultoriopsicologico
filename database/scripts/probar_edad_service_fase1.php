<?php

/**
 * Pruebas controladas de EdadService (Fase 1).
 * Ejecutar: php database/scripts/probar_edad_service_fase1.php
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\EdadService;

$zona = new DateTimeZone('America/Mexico_City');
$hoy = new DateTimeImmutable('today', $zona);
$svc = new EdadService($zona);

$casos = [];
$fallos = 0;

$assert = static function (
    bool $cond,
    string $nombre,
    string $detalle = ''
) use (&$casos, &$fallos): void {
    $casos[] = [
        'ok' => $cond,
        'nombre' => $nombre,
        'detalle' => $detalle
    ];

    if (!$cond) {
        $fallos++;
    }
};

// 1. Fecha futura
$futura = $hoy->modify('+1 day')->format('Y-m-d');
$r = $svc->validarFechaNacimiento($futura, 'paciente');
$assert(
    empty($r['ok']) && ($r['mensaje'] ?? '') === EdadService::MENSAJE_FUTURA,
    '1. Fecha futura',
    (string) ($r['mensaje'] ?? '')
);

// 2. Formato incorrecto
$r = $svc->validarFechaNacimiento('31/07/2000', 'paciente');
$assert(
    empty($r['ok']) && ($r['mensaje'] ?? '') === EdadService::MENSAJE_FORMATO,
    '2. Formato incorrecto',
    (string) ($r['mensaje'] ?? '')
);

// 3. Día inexistente
$r = $svc->validarFechaNacimiento('2023-02-30', 'paciente');
$assert(
    empty($r['ok']) && ($r['mensaje'] ?? '') === EdadService::MENSAJE_NO_REAL,
    '3. Día inexistente',
    (string) ($r['mensaje'] ?? '')
);

// 4. 29 feb inválido (año no bisiesto)
$r = $svc->validarFechaNacimiento('2023-02-29', 'paciente');
$assert(
    empty($r['ok']) && ($r['mensaje'] ?? '') === EdadService::MENSAJE_NO_REAL,
    '4. 29 febrero inválido',
    (string) ($r['mensaje'] ?? '')
);

// 5. 29 feb válido
$r = $svc->validarFechaNacimiento('2000-02-29', 'paciente');
$assert(
    !empty($r['ok']),
    '5. 29 febrero válido',
    (string) ($r['mensaje'] ?? 'ok')
);

// 6. Cumple 18 hoy
$cumpleHoy = $hoy->modify('-18 years')->format('Y-m-d');
$r = $svc->validarFechaNacimiento($cumpleHoy, 'adulto');
$assert(
    !empty($r['ok'])
    && ($r['clasificacion'] ?? '') === EdadService::CLASIFICACION_MAYOR
    && (int) ($r['edad'] ?? 0) === 18,
    '6. Cumple 18 hoy',
    json_encode($r, JSON_UNESCAPED_UNICODE) ?: ''
);

// 7. Cumple 18 mañana
$cumpleManana = $hoy->modify('-18 years')->modify('+1 day')->format('Y-m-d');
$r = $svc->validarFechaNacimiento($cumpleManana, 'adulto');
$assert(
    empty($r['ok'])
    && ($r['clasificacion'] ?? '') === EdadService::CLASIFICACION_MENOR,
    '7. Cumple 18 mañana',
    json_encode($r, JSON_UNESCAPED_UNICODE) ?: ''
);

// 8. Exactamente 120 años
$exacta120 = $hoy->modify('-120 years')->format('Y-m-d');
$r = $svc->validarFechaNacimiento($exacta120, 'paciente');
$assert(
    !empty($r['ok']) && (int) ($r['edad'] ?? -1) === 120,
    '8. Exactamente 120 años',
    json_encode($r, JSON_UNESCAPED_UNICODE) ?: ''
);

// 9. Más de 120 años
$mas120 = $hoy->modify('-120 years')->modify('-1 day')->format('Y-m-d');
$r = $svc->validarFechaNacimiento($mas120, 'paciente');
$assert(
    empty($r['ok']) && ($r['mensaje'] ?? '') === EdadService::MENSAJE_LIMITE,
    '9. Más de 120 años',
    (string) ($r['mensaje'] ?? '')
);

// 10. Psicólogo menor
$menor = $hoy->modify('-17 years')->format('Y-m-d');
$r = $svc->validarFechaNacimiento($menor, 'adulto');
$assert(
    empty($r['ok']) && ($r['mensaje'] ?? '') === EdadService::MENSAJE_MAYORIA,
    '10. Psicólogo menor',
    (string) ($r['mensaje'] ?? '')
);

// 11. Consultorio menor (misma política adulto)
$r = $svc->validarFechaNacimiento($menor, 'adulto');
$assert(
    empty($r['ok']),
    '11. Consultorio menor',
    (string) ($r['mensaje'] ?? '')
);

// 12. Paciente menor por psicólogo (permitido)
$r = $svc->validarFechaNacimiento($menor, 'paciente');
$assert(
    !empty($r['ok'])
    && ($r['clasificacion'] ?? '') === EdadService::CLASIFICACION_MENOR,
    '12. Paciente menor por psicólogo',
    json_encode($r, JSON_UNESCAPED_UNICODE) ?: ''
);

// 13. Paciente menor por registro público (rechazado)
$r = $svc->validarFechaNacimiento($menor, 'registro_publico');
$assert(
    empty($r['ok'])
    && ($r['mensaje'] ?? '') === EdadService::MENSAJE_REGISTRO_PUBLICO_MENOR,
    '13. Paciente menor registro público',
    (string) ($r['mensaje'] ?? '')
);

// 14. Edición adulto → menor (paciente permite; clasificación MENOR)
$r = $svc->validarFechaNacimiento($menor, 'paciente');
$assert(
    !empty($r['ok'])
    && ($r['clasificacion'] ?? '') === EdadService::CLASIFICACION_MENOR,
    '14. Edición adulto a menor (clasificación)',
    (string) ($r['clasificacion'] ?? '')
);

// 15. Manipulación min/max (fecha fuera de límites adultos)
$r = $svc->validarFechaNacimiento($hoy->format('Y-m-d'), 'adulto');
$assert(
    empty($r['ok']),
    '15. Manipulación min/max (hoy como adulto)',
    (string) ($r['mensaje'] ?? '')
);

// 16/17. POST esMenor/edad alterada: servicio no acepta esos campos
$r = $svc->validarFechaNacimiento($menor, 'paciente');
$assert(
    ($r['clasificacion'] ?? '') === EdadService::CLASIFICACION_MENOR
    && ($r['es_mayor'] ?? true) === false,
    '16-17. Clasificación solo desde fecha (ignora POST)',
    json_encode($r, JSON_UNESCAPED_UNICODE) ?: ''
);

// 18. Zona America/Mexico_City
$assert(
    $svc->obtenerFechaMaximaGeneral() === $hoy->format('Y-m-d'),
    '18. Zona America/Mexico_City',
    $svc->obtenerFechaMaximaGeneral()
);

// Límites input
$limAdulto = $svc->limitesInput('adulto');
$limPac = $svc->limitesInput('paciente');
$assert(
    $limAdulto['max'] === $svc->obtenerFechaMaximaAdulto()
    && $limPac['max'] === $svc->obtenerFechaMaximaGeneral()
    && $limAdulto['min'] === $svc->obtenerFechaMinimaPermitida(),
    'Límites dinámicos input',
    json_encode([$limAdulto, $limPac], JSON_UNESCAPED_UNICODE) ?: ''
);

echo "Pruebas EdadService Fase 1\n";
echo "Hoy (CDMX): " . $hoy->format('Y-m-d') . "\n\n";

foreach ($casos as $caso) {
    echo ($caso['ok'] ? '[OK] ' : '[FAIL] ')
        . $caso['nombre']
        . ($caso['detalle'] !== '' ? ' — ' . $caso['detalle'] : '')
        . "\n";
}

echo "\nTotal: " . count($casos) . " | Fallos: {$fallos}\n";
exit($fallos > 0 ? 1 : 0);
