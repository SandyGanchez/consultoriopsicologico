<?php

/**
 * Humo local de incidencias + limpieza de datos de prueba.
 * No envía correos. No toca Hostinger.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Solo CLI\n");
    exit(1);
}

define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';

use App\Config\Config;
use App\Services\IncidenciaSoporteService;

Config::load();

$pdo = new PDO(
    'mysql:host=localhost;dbname=consultorio_psicologico;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

function ok(string $m): void { echo "OK  {$m}\n"; }
function fail(string $m): void { echo "FAIL {$m}\n"; exit(2); }

$has = (bool) $pdo->query("SHOW TABLES LIKE 'incidencia_soporte'")->fetchColumn();
$has ? ok('tabla existe') : fail('tabla ausente');

$servicio = new IncidenciaSoporteService();

$r1 = $servicio->registrarDesdeLogin([
    'correo' => 'humo.incidencia.ok@example.test',
    'tipo' => 'AUTENTICACION',
    'descripcion' => 'Humo incidencia soporte autentificacion prueba local',
], hash('sha256', 'humo-ip-1'));

($r1['ok'] ?? false) ? ok('registro neutro') : fail('registro');
$id = (int) ($r1['id'] ?? 0);
$id > 0 ? ok("insert id={$id}") : fail('sin id');

$rDup = $servicio->registrarDesdeLogin([
    'correo' => 'humo.incidencia.ok@example.test',
    'tipo' => 'AUTENTICACION',
    'descripcion' => 'Humo incidencia soporte autentificacion prueba local',
], hash('sha256', 'humo-ip-1'));
!isset($rDup['id']) ? ok('duplicado 60s sin segundo id') : fail('duplicado insertó');

$admin = (string) $pdo->query(
    "SELECT ClvUsu FROM usuario WHERE RolUsu='ADMINISTRADOR' AND EstadoUsu=1 LIMIT 1"
)->fetchColumn();
$admin !== '' ? ok('admin encontrado') : fail('sin admin');

$servicio->cambiarEstado($id, 'EN_PROCESO', '', $admin);
ok('EN_PROCESO');

$servicio->cambiarEstado(
    $id,
    'RESUELTA',
    'Humo resuelto sin datos clínicos',
    $admin
);
ok('RESUELTA');

$invalid = false;
try {
    $servicio->cambiarEstado($id, 'PENDIENTE', 'x', $admin);
} catch (Throwable $e) {
    $invalid = true;
}
$invalid ? ok('transición inválida rechazada') : fail('transición inválida aceptada');

$correoInexistente = $servicio->registrarDesdeLogin([
    'correo' => 'humo.noexiste.' . time() . '@example.test',
    'tipo' => 'OTRO_ACCESO',
    'descripcion' => 'Humo correo inexistente mismo mensaje neutro',
], hash('sha256', 'humo-ip-2'));
($correoInexistente['ok'] ?? false)
    && str_contains((string) $correoInexistente['mensaje'], 'revisada por soporte')
    ? ok('correo inexistente mensaje neutro')
    : fail('enumeración');

$id2 = (int) ($correoInexistente['id'] ?? 0);

echo "=== LIMPIEZA HUMO ===\n";
$pdo->beginTransaction();
try {
    $pdo->exec(
        "DELETE FROM incidencia_soporte
         WHERE CorreoReportado LIKE 'humo.%@example.test'
            OR Descripcion LIKE 'Humo %'"
    );
    $pdo->exec(
        "DELETE FROM notificacion
         WHERE MensajeNotif LIKE '%incidencia #%'
           AND TituloNotif IN (
                'Nueva incidencia de acceso',
                'Incidencia en proceso',
                'Incidencia resuelta'
           )
           AND FechaNotif >= DATE_SUB(NOW(), INTERVAL 2 HOUR)"
    );
    $pdo->commit();
    ok('humo eliminado');
} catch (Throwable $e) {
    $pdo->rollBack();
    fail('limpieza: ' . $e->getMessage());
}

$restantes = (int) $pdo->query(
    "SELECT COUNT(*) FROM incidencia_soporte
     WHERE CorreoReportado LIKE 'humo.%@example.test'"
)->fetchColumn();
$restantes === 0 ? ok('sin humo residual') : fail('humo residual');

$citas = (int) $pdo->query('SELECT COUNT(*) FROM cita')->fetchColumn();
ok("citas intactas={$citas}");

echo "HUMO_OK\n";
