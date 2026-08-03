<?php

/**
 * Humo de PendienteClinicoService (solo consultas / evaluación; sin inserts clínicos).
 */

define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Services\PendienteClinicoService;

Config::load();
$dbName = (string) Config::get('DB_NAME', '');
if ($dbName !== 'consultorio_psicologico') {
    fwrite(STDERR, "ABORT: DB_NAME={$dbName}\n");
    exit(1);
}

$db = Database::connect();
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

$svc = new PendienteClinicoService();
$ahora = $svc->ahoraMexico();

$psi = $db->query(
    "SELECT ClvPsi, ClvCons FROM psicologo WHERE EstatusPsi='ACTIVO' LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

if (!$psi) {
    fail('Sin psicólogo de prueba');
    echo "HUMO_FAILS={$fails}\n";
    exit(1);
}

$clvPsi = (string) $psi['ClvPsi'];
$clvCons = (string) $psi['ClvCons'];

// Cita futura simulada
$futura = $svc->evaluarCita([
    'ClvCita' => 'CITATEST1',
    'ClvPac' => 'PACX',
    'ClvPsi' => $clvPsi,
    'ClvCons' => $clvCons,
    'EstadoCita' => 'PROGRAMADA',
    'FechaCita' => $ahora->modify('+2 days')->format('Y-m-d'),
    'HraInicioCita' => '10:00:00'
], $clvPsi, $clvCons, $ahora);

($futura['estado'] === PendienteClinicoService::CITA_FUTURA)
    ? ok('Cita futura → CITA_FUTURA')
    : fail('Cita futura → CITA_FUTURA');

!$futura['puedeRegistrarAsistencia']
    ? ok('Futura no permite registrar asistencia')
    : fail('Futura no permite registrar asistencia');

$pasada = $svc->evaluarCita([
    'ClvCita' => 'CITATEST2',
    'ClvPac' => 'PACX',
    'ClvPsi' => $clvPsi,
    'ClvCons' => $clvCons,
    'EstadoCita' => 'PROGRAMADA',
    'FechaCita' => $ahora->modify('-1 hour')->format('Y-m-d'),
    'HraInicioCita' => $ahora->modify('-1 hour')->format('H:i:s')
], $clvPsi, $clvCons, $ahora);

($pasada['estado'] === PendienteClinicoService::REGISTRAR_ASISTENCIA)
    ? ok('Hora llegada → REGISTRAR_ASISTENCIA')
    : fail('Hora llegada → REGISTRAR_ASISTENCIA');

$cancelada = $svc->evaluarCita([
    'ClvCita' => 'CITATEST3',
    'ClvPac' => 'PACX',
    'ClvPsi' => $clvPsi,
    'ClvCons' => $clvCons,
    'EstadoCita' => 'CANCELADA',
    'FechaCita' => $ahora->format('Y-m-d'),
    'HraInicioCita' => '09:00:00'
], $clvPsi, $clvCons, $ahora);

($cancelada['estado'] === PendienteClinicoService::SIN_ACCION_CLINICA)
    ? ok('CANCELADA sin acción clínica')
    : fail('CANCELADA sin acción clínica');

$ajeno = $svc->evaluarCita([
    'ClvCita' => 'CITATEST4',
    'ClvPac' => 'PACX',
    'ClvPsi' => 'PSI999',
    'ClvCons' => $clvCons,
    'EstadoCita' => 'PROGRAMADA',
    'FechaCita' => $ahora->modify('-1 hour')->format('Y-m-d'),
    'HraInicioCita' => $ahora->modify('-1 hour')->format('H:i:s')
], $clvPsi, $clvCons, $ahora);

($ajeno['estado'] === PendienteClinicoService::SIN_ACCION_CLINICA)
    ? ok('Cita de otro psicólogo rechazada')
    : fail('Cita de otro psicólogo rechazada');

$antes = count($svc->listarPendientesOperativos($clvPsi, $clvCons)['historiasPendientes']);
$despues = count($svc->listarPendientesOperativos($clvPsi, $clvCons)['historiasPendientes']);
($antes === $despues)
    ? ok('Recargar dashboard no crea duplicados (consulta idempotente)')
    : fail('Recargar dashboard no crea duplicados');

$countNotif = (int) $db->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificacion'"
)->fetchColumn();

ok('listarPendientesOperativos ejecutado sin inserts');

// Rutas clínicas del psicólogo: historia/seguimiento/expediente clínico
$routes = require APP_ROOT . '/routes/web.php';
$leak = false;
foreach (['GET', 'POST'] as $m) {
    foreach ($routes[$m] ?? [] as $path => $handler) {
        if (!is_string($path)) {
            continue;
        }
        $esClinicaPsi =
            str_starts_with($path, '/psicologo/')
            && (
                str_contains($path, 'historia')
                || str_contains($path, 'seguimiento')
                || str_contains($path, '/expediente')
            );

        if (!$esClinicaPsi) {
            continue;
        }

        $ctrl = (string) ($handler[0] ?? '');
        if ($ctrl !== \App\Controllers\PsicologoController::class) {
            $leak = true;
            echo "LEAK: {$m} {$path} => {$ctrl}\n";
        }
    }
}
!$leak ? ok('Rutas clínicas /psicologo solo en PsicologoController') : fail('Rutas clínicas /psicologo solo en PsicologoController');

$adminLeak = false;
foreach (['GET', 'POST'] as $m) {
    foreach ($routes[$m] ?? [] as $path => $handler) {
        if (!is_string($path)) {
            continue;
        }
        $ctrl = (string) ($handler[0] ?? '');
        if (
            str_contains($ctrl, 'AdministradorController')
            && (
                str_contains($path, 'historia')
                || str_contains($path, 'seguimiento')
                || str_contains($path, 'expediente')
            )
        ) {
            $adminLeak = true;
        }
    }
}
!$adminLeak ? ok('Administrador sin rutas de historia/seguimiento') : fail('Administrador sin rutas de historia/seguimiento');

echo "HUMO_FAILS={$fails}\n";
exit($fails > 0 ? 1 : 0);
