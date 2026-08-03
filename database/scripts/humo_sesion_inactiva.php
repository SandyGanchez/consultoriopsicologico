<?php

/**
 * Verifica invalidación de sesión ante EstadoUsu=0.
 * Restaura siempre el estado original (finally).
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Core\Session;
use App\Services\AccesoSesionService;
use App\Services\AdministradorService;
use App\Services\InstalacionConsultorioService;

Config::load();
$pdo = Database::connect();
$fails = 0;

function ok(string $m): void { echo "PASS: {$m}\n"; }
function fail(string $m): void { global $fails; $fails++; echo "FAIL: {$m}\n"; }

$inst = (new InstalacionConsultorioService())->resolver();
if (($inst['estado'] ?? '') !== 'unico') {
    fail('Instalación única requerida');
    echo "FAILS={$fails}\n";
    exit(1);
}

$admin = new AdministradorService();
$cuenta = $admin->resolverCuentaPrincipalUnica(
    (string) $inst['consultorio']['ClvCons']
);
$clv = (string) $cuenta['ClvUsu'];
$estadoOriginal = (int) $pdo->query(
    'SELECT EstadoUsu FROM usuario WHERE ClvUsu=' . $pdo->quote($clv)
)->fetchColumn();
$reqOriginal = (int) $pdo->query(
    'SELECT RequiereCambioContrasena FROM usuario WHERE ClvUsu=' . $pdo->quote($clv)
)->fetchColumn();
$estatusOriginal = (string) $pdo->query(
    'SELECT EstatusCons FROM consultorio WHERE ClvCons='
    . $pdo->quote((string) $inst['consultorio']['ClvCons'])
)->fetchColumn();

try {
    Session::start();
    Session::set('usuario', [
        'ClvUsu' => $clv,
        'RolUsu' => 'CONSULTORIO',
        'EstadoUsu' => 1,
        'CorreoUsu' => $cuenta['CorreoUsu'] ?? ''
    ]);

    AccesoSesionService::resetCache();
    $okAntes = (new AccesoSesionService())->evaluarSesionActiva('CONSULTORIO');
    !empty($okAntes['ok']) ? ok('Sesión válida con cuenta activa') : fail('Sesión activa');

    $pdo->prepare(
        "UPDATE usuario SET EstadoUsu=0 WHERE ClvUsu=:u AND RolUsu='CONSULTORIO'"
    )->execute(['u' => $clv]);

    AccesoSesionService::resetCache();
    $eval = (new AccesoSesionService())->evaluarSesionActiva('CONSULTORIO');
    (empty($eval['ok']) && ($eval['motivo'] ?? '') === 'inactiva')
        ? ok('Inactivación detectada (motivo=inactiva)')
        : fail('Detección inactiva: ' . json_encode($eval));

    $estatusAhora = (string) $pdo->query(
        'SELECT EstatusCons FROM consultorio WHERE ClvCons='
        . $pdo->quote((string) $inst['consultorio']['ClvCons'])
    )->fetchColumn();
    ($estatusAhora === $estatusOriginal)
        ? ok('EstatusCons no cambió')
        : fail('EstatusCons cambió');
} finally {
    $pdo->prepare(
        "UPDATE usuario
         SET EstadoUsu=:e, RequiereCambioContrasena=:r
         WHERE ClvUsu=:u AND RolUsu='CONSULTORIO'"
    )->execute([
        'e' => $estadoOriginal,
        'r' => $reqOriginal,
        'u' => $clv
    ]);
}

$rest = (int) $pdo->query(
    'SELECT EstadoUsu FROM usuario WHERE ClvUsu=' . $pdo->quote($clv)
)->fetchColumn();
$rest === $estadoOriginal
    ? ok('EstadoUsu restaurado')
    : fail('EstadoUsu no restaurado');

echo "FAILS={$fails}\n";
exit($fails > 0 ? 1 : 0);
