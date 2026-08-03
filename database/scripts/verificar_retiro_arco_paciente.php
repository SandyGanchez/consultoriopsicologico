<?php

/**
 * Verificación de retiro del canal ARCO in-app (solo lectura + llamadas sin INSERT).
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Services\PrivacidadService;

Config::load();
$pdo = Database::connect();
$svc = new PrivacidadService();
$fails = 0;

function ok(string $m): void
{
    echo "PASS: {$m}\n";
}

function fail(string $m): void
{
    global $fails;
    $fails++;
    echo "FAIL: {$m}\n";
}

$antes = (int) $pdo->query('SELECT COUNT(*) FROM solicitud_privacidad')->fetchColumn();

$res = $svc->solicitarRevocacionOArco('U009', 'ARCO_ACCESO', 'intento-post-retiro');
empty($res['ok']) ? ok('solicitarRevocacionOArco no crea') : fail('solicitarRevocacionOArco bloqueado');

$despues = (int) $pdo->query('SELECT COUNT(*) FROM solicitud_privacidad')->fetchColumn();
$antes === $despues
    ? ok("solicitud_privacidad intacta (count={$despues})")
    : fail('COUNT cambió');

$resumen = $svc->resumenPrivacidadPaciente('U009');
!array_key_exists('solicitudes', $resumen)
    ? ok('resumen sin clave solicitudes')
    : fail('resumen aún trae solicitudes');

!empty($resumen['version_aceptada']) || ($resumen['estado'] ?? '') !== ''
    ? ok('resumen conserva versión/estado')
    : fail('resumen vacío');

$listaPac = $svc->consultasSolicitudesPorRol('PACIENTE', 'U009');
(($listaPac['data'] ?? null) === [])
    ? ok('PACIENTE sin listado ARCO')
    : fail('PACIENTE aún lista solicitudes');

$listaCons = $svc->consultasSolicitudesPorRol('CONSULTORIO', 'USU001');
!empty($listaCons['ok']) && is_array($listaCons['data'] ?? null)
    ? ok('CONSULTORIO conserva histórico count=' . count($listaCons['data']))
    : fail('CONSULTORIO histórico');

empty($svc->consultasSolicitudesPorRol('PSICOLOGO', 'U009')['ok'])
    ? ok('PSICOLOGO sin acceso')
    : fail('PSICOLOGO acceso');

$aviso = $svc->obtenerAvisoVigente();
(($aviso['VersionAviso'] ?? '') === '1.0')
    ? ok('Aviso 1.0 vigente intacto')
    : fail('Aviso vigente inesperado');

$resp = $svc->obtenerDatosResponsable();
(
    ($resp['nombre_responsable'] ?? '') === 'Laura Mendoza Ruiz'
    && str_contains((string) ($resp['correo_privacidad'] ?? ''), 'contacto.centro.integral@gmail.com')
    && ($resp['telefono'] ?? '') === '7225550101'
)
    ? ok('Datos responsables reales')
    : fail('Datos responsables');

echo "FAILS={$fails}\n";
exit($fails > 0 ? 1 : 0);
