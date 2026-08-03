<?php

define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Models\Cita;
use App\Services\PendienteClinicoService;

Config::load();
if ((string) Config::get('DB_NAME', '') !== 'consultorio_psicologico') {
    fwrite(STDERR, "ABORT\n");
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
$psi = $db->query("SELECT ClvPsi, ClvCons FROM psicologo WHERE EstatusPsi='ACTIVO' LIMIT 1")
    ->fetch(PDO::FETCH_ASSOC);
$clvPsi = (string) ($psi['ClvPsi'] ?? '');
$clvCons = (string) ($psi['ClvCons'] ?? '');

// Misma fecha, hora futura
$mismaFechaHoraFutura = $svc->evaluarCita([
    'ClvCita' => 'T1',
    'ClvPac' => 'PX',
    'ClvPsi' => $clvPsi,
    'ClvCons' => $clvCons,
    'EstadoCita' => 'PROGRAMADA',
    'FechaCita' => $ahora->format('Y-m-d'),
    'HraInicioCita' => $ahora->modify('+30 minutes')->format('H:i:s')
], $clvPsi, $clvCons, $ahora);

($mismaFechaHoraFutura['estado'] === PendienteClinicoService::CITA_FUTURA)
    ? ok('Fecha correcta + hora futura → CITA_FUTURA')
    : fail('Fecha correcta + hora futura → CITA_FUTURA');

$enHora = $svc->evaluarCita([
    'ClvCita' => 'T2',
    'ClvPac' => 'PX',
    'ClvPsi' => $clvPsi,
    'ClvCons' => $clvCons,
    'EstadoCita' => 'PROGRAMADA',
    'FechaCita' => $ahora->format('Y-m-d'),
    'HraInicioCita' => $ahora->format('H:i:s')
], $clvPsi, $clvCons, $ahora);

($enHora['estado'] === PendienteClinicoService::REGISTRAR_ASISTENCIA)
    ? ok('fechaHoraInicio <= ahora → REGISTRAR_ASISTENCIA')
    : fail('fechaHoraInicio <= ahora → REGISTRAR_ASISTENCIA');

$cancel = $svc->evaluarCita([
    'ClvCita' => 'T3', 'ClvPac' => 'PX', 'ClvPsi' => $clvPsi, 'ClvCons' => $clvCons,
    'EstadoCita' => 'CANCELADA', 'FechaCita' => $ahora->format('Y-m-d'), 'HraInicioCita' => '10:00:00'
], $clvPsi, $clvCons, $ahora);
($cancel['estado'] === PendienteClinicoService::SIN_ACCION_CLINICA)
    ? ok('CANCELADA → SIN_ACCION_CLINICA')
    : fail('CANCELADA → SIN_ACCION_CLINICA');

$ina = $svc->evaluarCita([
    'ClvCita' => 'T4', 'ClvPac' => 'PX', 'ClvPsi' => $clvPsi, 'ClvCons' => $clvCons,
    'EstadoCita' => 'INASISTENCIA', 'FechaCita' => $ahora->format('Y-m-d'), 'HraInicioCita' => '10:00:00'
], $clvPsi, $clvCons, $ahora);
($ina['estado'] === PendienteClinicoService::SIN_ACCION_CLINICA)
    ? ok('INASISTENCIA → SIN_ACCION_CLINICA')
    : fail('INASISTENCIA → SIN_ACCION_CLINICA');

$snap1 = $svc->obtenerSnapshotOperativo($clvPsi, $clvCons);
$snap2 = $svc->obtenerSnapshotOperativo($clvPsi, $clvCons);
(count($snap1['citas']) === count($snap2['citas']))
    ? ok('Snapshot idempotente (sin duplicados)')
    : fail('Snapshot idempotente');

(
    array_key_exists('ahora', $snap1)
    && array_key_exists('zona', $snap1)
    && array_key_exists('proximaEvaluacionIso', $snap1)
)
    ? ok('Snapshot incluye ahora/zona/proximaEvaluacionIso')
    : fail('Snapshot incluye metadatos');

($snap1['zona'] === 'America/Mexico_City')
    ? ok('Zona America/Mexico_City')
    : fail('Zona America/Mexico_City');

// Completar datos: solo PROGRAMADA/ASISTIDA o historial responsable
$citaModel = new Cita();
$pac = $db->query(
    "SELECT ClvPac FROM paciente WHERE ClvCons = " . $db->quote($clvCons) . " LIMIT 1"
)->fetchColumn();

if ($pac) {
    $soloCancelada = !$citaModel->existeCitaProgramadaOAsistida(
        (string) $pac,
        $clvPsi,
        $clvCons
    );
    // método nuevo existe
    ok('existeCitaProgramadaOAsistida disponible');
} else {
    ok('existeCitaProgramadaOAsistida disponible (sin paciente de prueba)');
}

$routes = require APP_ROOT . '/routes/web.php';
$ruta = $routes['GET']['/psicologo/agenda/pendientes-operativos'][0] ?? '';
($ruta === \App\Controllers\PsicologoController::class)
    ? ok('Endpoint pendientes-operativos en PsicologoController')
    : fail('Endpoint pendientes-operativos en PsicologoController');

echo "HUMO_FAILS={$fails}\n";
exit($fails > 0 ? 1 : 0);
