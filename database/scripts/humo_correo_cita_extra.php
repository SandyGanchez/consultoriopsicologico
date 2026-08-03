<?php

if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';
require APP_ROOT . '/database/scripts/_guard_bd_prueba.php';

use App\Config\Config;
use App\Config\Database;
use App\Services\CorreoCitaService;

Config::reset();
Config::load();

$dbPrueba = 'consultorio_psicologico_correos_prueba';
pm_rechazar_bd_no_prueba($dbPrueba, (string) Config::get('APP_ENV', ''));
Config::override([
    'DB_NAME' => $dbPrueba,
    'MAIL_CITA_DRY_RUN' => '1'
]);

$ref = new \ReflectionClass(Database::class);
$prop = $ref->getProperty('connection');
$prop->setAccessible(true);
$prop->setValue(null, null);

$db = Database::connect();
if ((string) $db->query('SELECT DATABASE()')->fetchColumn() !== $dbPrueba) {
    fwrite(STDERR, "ABORT BD\n");
    exit(1);
}

$svc = new CorreoCitaService($db);
$fails = 0;
$psi = $db->query(
    "SELECT psi.ClvPsi, psi.ClvCons, psi.ClvUsu, pac.ClvPac, pac.ClvUsu AS ClvUsuPac, s.ClvServ
     FROM psicologo psi
     INNER JOIN paciente pac ON pac.ClvCons = psi.ClvCons
     INNER JOIN servicios s ON s.ClvCons = psi.ClvCons AND s.EstatusServicio='ACTIVO'
     LIMIT 1"
)->fetch(\PDO::FETCH_ASSOC);

$zona = new \DateTimeZone('America/Mexico_City');
$ahora = new \DateTimeImmutable('now', $zona);

function mk(\PDO $db, array $psi, \DateTimeImmutable $inicio, string $suf): string
{
    $c = 'CT' . substr(md5($suf . microtime(true)), 0, 8);
    $f = $inicio->modify('+60 minutes');
    $st = $db->prepare(
        "INSERT INTO cita (
            ClvCita, FechaCita, HraInicioCita, HraFinCita,
            DuracionAplicadaMin, CostoAplicado, EstadoCita,
            ClvPac, ClvPsi, ClvCons, ClvServ
         ) VALUES (
            :c, :f, :hi, :hf, 60, 500, 'PROGRAMADA',
            :pac, :psi, :cons, :serv
         )"
    );
    $st->execute([
        'c' => $c,
        'f' => $inicio->format('Y-m-d'),
        'hi' => $inicio->format('H:i:s'),
        'hf' => $f->format('H:i:s'),
        'pac' => $psi['ClvPac'],
        'psi' => $psi['ClvPsi'],
        'cons' => $psi['ClvCons'],
        'serv' => $psi['ClvServ']
    ]);
    return $c;
}

function cnt(\PDO $db, string $c, string $t, string $e): int
{
    $st = $db->prepare(
        'SELECT COUNT(*) FROM correo_cita
         WHERE ClvCita=:c AND TipoCorreo=:t AND EstadoCorreo=:e'
    );
    $st->execute(['c' => $c, 't' => $t, 'e' => $e]);
    return (int) $st->fetchColumn();
}

function pass(string $m): void
{
    echo "PASS: {$m}\n";
}

function fail(string $m): void
{
    global $fails;
    $fails++;
    echo "FAIL: {$m}\n";
}

// ASISTIDA
$c1 = mk($db, $psi, $ahora->modify('+36 hours'), 'asi');
$db->beginTransaction();
$svc->prepararParaCitaNueva($c1);
$db->commit();
$db->prepare("UPDATE cita SET EstadoCita='ASISTIDA' WHERE ClvCita=:c")
    ->execute(['c' => $c1]);
$db->prepare(
    "UPDATE correo_cita SET FechaProgramada=NOW()-INTERVAL 1 MINUTE,
        EstadoCorreo='PENDIENTE', Intentos=0
     WHERE ClvCita=:c AND TipoCorreo='RECORDATORIO_24H'"
)->execute(['c' => $c1]);
$svc->procesarLote(20);
cnt($db, $c1, 'RECORDATORIO_24H', 'OMITIDO') === 2
    ? pass('ASISTIDA → recordatorio OMITIDO')
    : fail('ASISTIDA → recordatorio OMITIDO');

// INASISTENCIA
$c2 = mk($db, $psi, $ahora->modify('+40 hours'), 'ina');
$db->beginTransaction();
$svc->prepararParaCitaNueva($c2);
$db->commit();
$db->prepare("UPDATE cita SET EstadoCita='INASISTENCIA' WHERE ClvCita=:c")
    ->execute(['c' => $c2]);
$db->prepare(
    "UPDATE correo_cita SET FechaProgramada=NOW()-INTERVAL 1 MINUTE,
        EstadoCorreo='PENDIENTE', Intentos=0
     WHERE ClvCita=:c AND TipoCorreo='RECORDATORIO_24H'"
)->execute(['c' => $c2]);
$svc->procesarLote(20);
cnt($db, $c2, 'RECORDATORIO_24H', 'OMITIDO') === 2
    ? pass('INASISTENCIA → recordatorio OMITIDO')
    : fail('INASISTENCIA → recordatorio OMITIDO');

// Rollback no deja correos
$c3 = mk($db, $psi, $ahora->modify('+50 hours'), 'rol');
try {
    $db->beginTransaction();
    $svc->prepararParaCitaNueva($c3);
    throw new \RuntimeException('force-rollback');
} catch (\Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}
$left = (int) $db->query(
    'SELECT COUNT(*) FROM correo_cita WHERE ClvCita=' . $db->quote($c3)
)->fetchColumn();
$left === 0 ? pass('Rollback no deja correos') : fail("Rollback correos={$left}");
$db->prepare('DELETE FROM cita WHERE ClvCita=:c')->execute(['c' => $c3]);

// Recordatorio enviado una vez
$c4 = mk($db, $psi, $ahora->modify('+30 hours'), 'rec');
$db->beginTransaction();
$svc->prepararParaCitaNueva($c4);
$db->commit();
$db->prepare(
    "UPDATE correo_cita SET FechaProgramada=NOW()-INTERVAL 1 MINUTE
     WHERE ClvCita=:c AND TipoCorreo='RECORDATORIO_24H'"
)->execute(['c' => $c4]);
$svc->procesarLote(20);
$svc->procesarLote(20);
cnt($db, $c4, 'RECORDATORIO_24H', 'ENVIADO') === 2
    ? pass('RECORDATORIO_24H ENVIADO una vez (2 roles)')
    : fail('RECORDATORIO_24H ENVIADO una vez');
$dup = (int) $db->query(
    'SELECT COUNT(*) FROM correo_cita WHERE ClvCita=' . $db->quote($c4)
    . " AND TipoCorreo='RECORDATORIO_24H'"
)->fetchColumn();
$dup === 2 ? pass('Sin filas duplicadas de recordatorio') : fail("dup={$dup}");

// Fallo de un destinatario no impide el otro (simula FALLIDO en paciente, procesa psicólogo)
$c5 = mk($db, $psi, $ahora->modify('+55 hours'), 'ind');
$db->beginTransaction();
$svc->prepararParaCitaNueva($c5);
$db->commit();
$db->prepare(
    "UPDATE correo_cita SET EstadoCorreo='FALLIDO', Intentos=3,
        ErrorResumen='simulado'
     WHERE ClvCita=:c AND TipoCorreo='CONFIRMACION' AND RolDestinatario='PACIENTE'"
)->execute(['c' => $c5]);
$envio = $svc->procesarConfirmacionesInmediatas($c5);
(!$envio['paciente'] && $envio['psicologo'])
    ? pass('Fallo paciente no impide psicólogo')
    : fail('Fallo paciente no impide psicólogo');

// Reintento controlado: FALLIDO con Intentos < max vuelve a procesarse
$c6 = mk($db, $psi, $ahora->modify('+60 hours'), 'ret');
$db->beginTransaction();
$svc->prepararParaCitaNueva($c6);
$db->commit();
$db->prepare(
    "UPDATE correo_cita SET EstadoCorreo='FALLIDO', Intentos=1,
        FechaProgramada=NOW()-INTERVAL 1 MINUTE
     WHERE ClvCita=:c AND TipoCorreo='CONFIRMACION' AND RolDestinatario='PSICOLOGO'"
)->execute(['c' => $c6]);
$svc->procesarLote(20);
$intentos = (int) $db->query(
    "SELECT Intentos FROM correo_cita
     WHERE ClvCita=" . $db->quote($c6) . "
       AND TipoCorreo='CONFIRMACION' AND RolDestinatario='PSICOLOGO'"
)->fetchColumn();
$estado = (string) $db->query(
    "SELECT EstadoCorreo FROM correo_cita
     WHERE ClvCita=" . $db->quote($c6) . "
       AND TipoCorreo='CONFIRMACION' AND RolDestinatario='PSICOLOGO'"
)->fetchColumn();
($estado === 'ENVIADO' && $intentos === 2)
    ? pass('Reintento controlado FALLIDO→ENVIADO')
    : fail("Reintento estado={$estado} intentos={$intentos}");

// ErrorResumen sin secretos en estructura de servicio (dry-run no escribe error)
$errCols = $db->query(
    "SELECT ErrorResumen FROM correo_cita WHERE ErrorResumen IS NOT NULL LIMIT 20"
)->fetchAll(\PDO::FETCH_COLUMN);
$seguro = true;
foreach ($errCols as $err) {
    $e = strtolower((string) $err);
    if (str_contains($e, 'password') || str_contains($e, 'smtp') || str_contains($e, 'passwd')) {
        $seguro = false;
    }
}
$seguro ? pass('ErrorResumen sin credenciales aparentes') : fail('ErrorResumen con secretos');

echo "EXTRA_FAILS={$fails}\n";
exit($fails > 0 ? 1 : 0);
