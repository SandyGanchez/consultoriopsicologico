<?php

/**
 * Seeder controlado: publica aviso_privacidad_version.
 *
 * Uso:
 *   php database/seeders/publicar_aviso_privacidad.php [version] [--database=nombre]
 *
 * Reglas:
 * - Solo publica si existen datos legales reales del consultorio único.
 * - No inserta marcadores.
 * - Normaliza contenido y calcula SHA-256.
 * - Transacción + bloqueo de versión vigente.
 * - Idempotente si versión+contenido son idénticos.
 * - Rechaza misma versión con contenido diferente.
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 2));

require APP_ROOT . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Services\PrivacidadService;

Config::load();

$timezone = trim((string) Config::get('APP_TIMEZONE', 'America/Mexico_City'));
date_default_timezone_set($timezone !== '' ? $timezone : 'America/Mexico_City');

$version = '1.0';
$database = null;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--database=')) {
        $database = trim(substr($arg, strlen('--database=')));
        continue;
    }

    if (!str_starts_with($arg, '--') && trim($arg) !== '') {
        $version = trim($arg);
    }
}

if ($database !== null && $database !== '') {
    // Guardrail: no permitir apuntar accidentalmente a Hostinger por este seeder.
    $prohibidas = [
        'consultorio_psicologico_prod',
        'production'
    ];

    if (in_array(strtolower($database), $prohibidas, true)) {
        fwrite(STDERR, "Base de datos no permitida para este seeder.\n");
        exit(1);
    }

    Config::override(['DB_NAME' => $database]);
    Database::resetConnection();
}

if ($version === '') {
    fwrite(STDERR, "Versión inválida.\n");
    exit(1);
}

$servicio = new PrivacidadService();
$responsable = $servicio->obtenerDatosResponsable();

if (!$responsable['completo']) {
    fwrite(STDERR, "NO SE PUBLICA la versión {$version}. Faltan datos legales:\n");
    foreach ($responsable['faltantes'] as $faltante) {
        fwrite(STDERR, " - {$faltante}\n");
    }
    exit(2);
}

$resultado = $servicio->publicarVersionAviso($version);

if (empty($resultado['ok'])) {
    fwrite(STDERR, (string) ($resultado['mensaje'] ?? 'No se pudo publicar.') . PHP_EOL);
    exit(2);
}

$creado = array_key_exists('creado', $resultado)
    ? (!empty($resultado['creado']) ? 'creada' : 'idempotente')
    : 'ok';

fwrite(
    STDOUT,
    'DB='
    . (string) Config::get('DB_NAME')
    . " | Publicado ({$creado}) IdAvisoPrivacidad="
    . (int) ($resultado['id'] ?? 0)
    . ' Version='
    . $version
    . ' Hash='
    . (string) ($resultado['hash'] ?? '')
    . PHP_EOL
);

exit(0);
