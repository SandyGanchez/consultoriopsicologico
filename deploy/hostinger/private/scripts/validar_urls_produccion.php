<?php

/**
 * Valida generación de URLs con APP_URL de producción (sin necesidad de DNS).
 * Uso: php deploy/hostinger/scripts/validar_urls_produccion.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', $root);
}
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', $root . '/public');
}

use App\Config\Config;
use App\Config\Paths;
use App\Helpers\Helper;

// Simular producción sin tocar el .env real: override en memoria.
$ref = new ReflectionClass(Config::class);
$prop = $ref->getProperty('config');
$prop->setAccessible(true);
Config::load();
$config = $prop->getValue();
$config['APP_ENV'] = 'production';
$config['APP_DEBUG'] = 'false';
$config['APP_URL'] = 'https://consultoriospsicologicospsicomatch.com';
$config['SESSION_SECURE_COOKIE'] = 'true';
$prop->setValue(null, $config);

$esperado = 'https://consultoriospsicologicospsicomatch.com';
$casos = [
    '' => $esperado,
    'login' => $esperado . '/login',
    'registro' => $esperado . '/registro',
    'forgot-password' => $esperado . '/forgot-password',
    'activar-cuenta?token=demo' => $esperado . '/activar-cuenta?token=demo',
    'administrador' => $esperado . '/administrador',
    'consultorio' => $esperado . '/consultorio',
    'psicologo' => $esperado . '/psicologo',
    'paciente' => $esperado . '/paciente',
    'consultorios/CON001' => $esperado . '/consultorios/CON001',
    'consultorios/CON001/especialistas/PSI001' => $esperado . '/consultorios/CON001/especialistas/PSI001',
    'assets/css/style.css' => $esperado . '/assets/css/style.css',
];

$fallos = 0;

foreach ($casos as $path => $want) {
    $got = Helper::baseUrl($path);
    $ok = $got === $want;
    if (!$ok) {
        $fallos++;
        echo "FAIL [{$path}]\n  want: {$want}\n  got:  {$got}\n";
    } else {
        echo "OK   {$got}\n";
    }
}

$prohibidos = ['localhost', '127.0.0.1', 'consultorio_psicologico', '/public/', 'http://consultorios'];
foreach ($casos as $path => $_) {
    $got = Helper::baseUrl($path);
    foreach ($prohibidos as $bad) {
        if (str_contains($got, $bad)) {
            $fallos++;
            echo "FAIL contiene '{$bad}': {$got}\n";
        }
    }
    if (str_contains($got, '//assets') || str_contains($got, '.com//')) {
        $fallos++;
        echo "FAIL doble slash: {$got}\n";
    }
}

$basePath = Paths::urlBasePath();
if ($basePath !== '') {
    $fallos++;
    echo "FAIL urlBasePath debería ser vacío en dominio raíz, got: {$basePath}\n";
} else {
    echo "OK   Paths::urlBasePath() vacío (dominio raíz)\n";
}

echo $fallos === 0 ? "\nRESULTADO: TODAS LAS PRUEBAS OK\n" : "\nRESULTADO: {$fallos} fallos\n";
exit($fallos === 0 ? 0 : 1);
