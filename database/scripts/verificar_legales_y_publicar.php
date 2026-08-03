<?php

/**
 * Verifica datos legales y publica aviso 1.0 en la BD configurada (.env).
 * Uso: php database/scripts/verificar_legales_y_publicar.php
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Services\PrivacidadService;

Config::load();
date_default_timezone_set((string) Config::get('APP_TIMEZONE', 'America/Mexico_City'));

$db = (string) Config::get('DB_NAME', '');
$env = strtolower((string) Config::get('APP_ENV', ''));

fwrite(STDOUT, "DB={$db}\nAPP_ENV={$env}\n");

if ($env === 'production' || $env === 'prod') {
    fwrite(STDERR, "BLOQUEADO en production.\n");
    exit(99);
}

if ($db !== 'consultorio_psicologico') {
    fwrite(STDERR, "Este script solo publica en consultorio_psicologico local. DB actual={$db}\n");
    exit(99);
}

$servicio = new PrivacidadService();
$responsable = $servicio->obtenerDatosResponsable();

fwrite(STDOUT, "Responsable: {$responsable['nombre_responsable']}\n");
fwrite(STDOUT, "Consultorio: {$responsable['nombre_consultorio']}\n");
fwrite(STDOUT, "Domicilio: {$responsable['domicilio']}\n");
fwrite(STDOUT, "Correo: {$responsable['correo_privacidad']}\n");
fwrite(STDOUT, "Teléfono: {$responsable['telefono']}\n");

if (!$responsable['completo']) {
    fwrite(STDERR, "NO SE PUBLICA. Faltantes:\n");
    foreach ($responsable['faltantes'] as $f) {
        fwrite(STDERR, " - {$f}\n");
    }
    exit(2);
}

$marcadores = ['[NOMBRE', '[DOMICILIO]', '[CORREO]', 'prueba.local', 'FALTA:'];
$blob = implode(' ', [
    $responsable['nombre_responsable'],
    $responsable['nombre_consultorio'],
    $responsable['domicilio'],
    $responsable['correo_privacidad'],
    $responsable['telefono']
]);
foreach ($marcadores as $m) {
    if (stripos($blob, $m) !== false) {
        fwrite(STDERR, "NO SE PUBLICA: dato con marcador/ejemplo detectado ({$m}).\n");
        exit(2);
    }
}

$resultado = $servicio->publicarVersionAviso('1.0');
if (empty($resultado['ok'])) {
    fwrite(STDERR, (string) ($resultado['mensaje'] ?? 'Error al publicar') . PHP_EOL);
    exit(2);
}

$aviso = $servicio->obtenerAvisoVigente();
$pdo = Database::connect();
$totalVigente = (int) $pdo->query(
    "SELECT COUNT(*) FROM aviso_privacidad_version WHERE EstadoAviso='VIGENTE'"
)->fetchColumn();
$total11 = (int) $pdo->query(
    "SELECT COUNT(*) FROM aviso_privacidad_version WHERE VersionAviso='1.1'"
)->fetchColumn();

fwrite(STDOUT, 'PUBLICADO id=' . (int) ($resultado['id'] ?? 0) . "\n");
fwrite(STDOUT, 'creado=' . (!empty($resultado['creado']) ? '1' : '0') . "\n");
fwrite(STDOUT, 'hash=' . (string) ($resultado['hash'] ?? '') . "\n");
fwrite(STDOUT, 'version_vigente=' . (string) ($aviso['VersionAviso'] ?? '') . "\n");
fwrite(STDOUT, 'estado=' . (string) ($aviso['EstadoAviso'] ?? '') . "\n");
fwrite(STDOUT, 'fecha=' . (string) ($aviso['FechaPublicacion'] ?? '') . "\n");
fwrite(STDOUT, "total_vigente={$totalVigente}\n");
fwrite(STDOUT, "total_1_1={$total11}\n");

if ($totalVigente !== 1 || ($aviso['VersionAviso'] ?? '') !== '1.0' || $total11 !== 0) {
    fwrite(STDERR, "Estado de versiones inesperado.\n");
    exit(3);
}

// Idempotencia
$segunda = $servicio->publicarVersionAviso('1.0');
if (empty($segunda['ok']) || !empty($segunda['creado'])) {
    fwrite(STDERR, "Fallo idempotencia: " . json_encode($segunda) . PHP_EOL);
    exit(4);
}
fwrite(STDOUT, "IDEMPOTENTE_OK\n");

$contenido = (string) ($aviso['ContenidoAviso'] ?? '');
$rehash = $servicio->calcularHashContenidoAviso(
    $servicio->normalizarContenidoAviso($contenido)
);
if ($rehash !== strtolower((string) ($aviso['HashContenidoAviso'] ?? ''))) {
    fwrite(STDERR, "Hash no coincide con contenido almacenado.\n");
    exit(5);
}
fwrite(STDOUT, "HASH_CONTENIDO_OK\n");
exit(0);
