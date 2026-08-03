<?php

/**
 * Humo de correo_cita SOLO en BD de prueba.
 * No envía SMTP reales (MAIL_CITA_DRY_RUN forzado).
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';
require APP_ROOT . '/database/scripts/_guard_bd_prueba.php';

use App\Config\Config;
use App\Config\Database;
use App\Core\Session;
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

// Evitar warnings de session_start tras salida previa.
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$db = Database::connect();
$actual = (string) $db->query('SELECT DATABASE()')->fetchColumn();
if ($actual !== $dbPrueba) {
    fwrite(STDERR, "ABORT: DATABASE()={$actual}\n");
    exit(1);
}

$fails = 0;
function ok(string $m): void { echo "PASS: {$m}\n"; }
function fail(string $m): void { global $fails; $fails++; echo "FAIL: {$m}\n"; }

$svc = new CorreoCitaService($db);
$svc->persistenciaDisponible() ? ok('Tabla correo_cita disponible') : fail('Tabla correo_cita');

$psi = $db->query(
    "SELECT psi.ClvPsi, psi.ClvCons, psi.ClvUsu, pac.ClvPac, pac.ClvUsu AS ClvUsuPac, s.ClvServ
     FROM psicologo psi
     INNER JOIN paciente pac ON pac.ClvCons = psi.ClvCons
     INNER JOIN servicios s ON s.ClvCons = psi.ClvCons AND s.EstatusServicio='ACTIVO'
     LIMIT 1"
)->fetch(\PDO::FETCH_ASSOC);

if (!$psi) {
    fail('Sin datos base para prueba');
    echo "HUMO_FAILS={$fails}\n";
    exit(1);
}

$zona = new \DateTimeZone('America/Mexico_City');
$ahora = new \DateTimeImmutable('now', $zona);

function crearCitaPrueba(
    \PDO $db,
    array $psi,
    \DateTimeImmutable $inicio,
    string $sufijo
): string {
    $clvCita = 'CT' . substr(md5($sufijo . microtime(true)), 0, 8);
    $fin = $inicio->modify('+60 minutes');
    $stmt = $db->prepare(
        "INSERT INTO cita (
            ClvCita, FechaCita, HraInicioCita, HraFinCita,
            DuracionAplicadaMin, CostoAplicado, EstadoCita,
            ClvPac, ClvPsi, ClvCons, ClvServ
         ) VALUES (
            :c, :f, :hi, :hf, 60, 500.00, 'PROGRAMADA',
            :pac, :psi, :cons, :serv
         )"
    );
    $stmt->execute([
        'c' => $clvCita,
        'f' => $inicio->format('Y-m-d'),
        'hi' => $inicio->format('H:i:s'),
        'hf' => $fin->format('H:i:s'),
        'pac' => $psi['ClvPac'],
        'psi' => $psi['ClvPsi'],
        'cons' => $psi['ClvCons'],
        'serv' => $psi['ClvServ']
    ]);

    return $clvCita;
}

function contar(\PDO $db, string $clvCita, string $tipo, string $estado): int
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM correo_cita
         WHERE ClvCita=:c AND TipoCorreo=:t AND EstadoCorreo=:e"
    );
    $stmt->execute(['c' => $clvCita, 't' => $tipo, 'e' => $estado]);
    return (int) $stmt->fetchColumn();
}

$inicioLejana = $ahora->modify('+48 hours');
$db->beginTransaction();
$clvLejana = crearCitaPrueba($db, $psi, $inicioLejana, 'lej');
$svc->prepararParaCitaNueva($clvLejana);
$db->commit();

contar($db, $clvLejana, 'CONFIRMACION', 'PENDIENTE') === 2
    ? ok('>24h: 2 confirmaciones PENDIENTE')
    : fail('>24h: 2 confirmaciones PENDIENTE');
contar($db, $clvLejana, 'RECORDATORIO_24H', 'PENDIENTE') === 2
    ? ok('>24h: 2 recordatorios PENDIENTE')
    : fail('>24h: 2 recordatorios PENDIENTE');

$db->beginTransaction();
$svc->prepararParaCitaNueva($clvLejana);
$db->commit();
$total = (int) $db->query(
    "SELECT COUNT(*) FROM correo_cita WHERE ClvCita=" . $db->quote($clvLejana)
)->fetchColumn();
$total === 4 ? ok('UNIQUE evita duplicados al repreparar') : fail('UNIQUE evita duplicados');

$inicioCercana = $ahora->modify('+2 hours');
$db->beginTransaction();
$clvCercana = crearCitaPrueba($db, $psi, $inicioCercana, 'cer');
$svc->prepararParaCitaNueva($clvCercana);
$db->commit();
contar($db, $clvCercana, 'CONFIRMACION', 'PENDIENTE') === 2
    ? ok('<24h: confirmaciones PENDIENTE')
    : fail('<24h: confirmaciones PENDIENTE');
contar($db, $clvCercana, 'RECORDATORIO_24H', 'OMITIDO') === 2
    ? ok('<24h: recordatorios OMITIDO')
    : fail('<24h: recordatorios OMITIDO');

$envio = $svc->procesarConfirmacionesInmediatas($clvLejana);
($envio['paciente'] && $envio['psicologo'])
    ? ok('Confirmaciones procesadas (dry-run)')
    : fail('Confirmaciones procesadas (dry-run)');
contar($db, $clvLejana, 'CONFIRMACION', 'ENVIADO') === 2
    ? ok('Confirmaciones ENVIADO')
    : fail('Confirmaciones ENVIADO');

$inicioAct = $ahora->modify('+72 hours');
$db->beginTransaction();
$clvAct = crearCitaPrueba($db, $psi, $inicioAct, 'act');
$svc->prepararParaCitaNueva($clvAct, [
    'omitirConfirmacionPaciente' => true,
    'motivoOmitirConfirmacionPaciente' => CorreoCitaService::MOTIVO_ACTIVACION
]);
$db->commit();
$motivo = (string) $db->query(
    "SELECT MotivoOmision FROM correo_cita
     WHERE ClvCita=" . $db->quote($clvAct) . "
       AND TipoCorreo='CONFIRMACION' AND RolDestinatario='PACIENTE'"
)->fetchColumn();
($motivo === CorreoCitaService::MOTIVO_ACTIVACION)
    ? ok('Paciente nuevo: CONFIRMACION OMITIDO por activación')
    : fail('Paciente nuevo: CONFIRMACION OMITIDO por activación');
contar($db, $clvAct, 'CONFIRMACION', 'PENDIENTE') === 1
    ? ok('Paciente nuevo: solo psicólogo PENDIENTE confirmación')
    : fail('Paciente nuevo: solo psicólogo PENDIENTE confirmación');

$db->prepare("UPDATE cita SET EstadoCita='CANCELADA' WHERE ClvCita=:c")
    ->execute(['c' => $clvLejana]);
$db->prepare(
    "UPDATE correo_cita SET FechaProgramada = NOW() - INTERVAL 1 MINUTE,
        EstadoCorreo='PENDIENTE', Intentos=0
     WHERE ClvCita=:c AND TipoCorreo='RECORDATORIO_24H'"
)->execute(['c' => $clvLejana]);
$svc->procesarLote(20);
contar($db, $clvLejana, 'RECORDATORIO_24H', 'OMITIDO') === 2
    ? ok('CANCELADA → recordatorio OMITIDO')
    : fail('CANCELADA → recordatorio OMITIDO');

Session::start();
$token = Session::csrfToken();
Session::validarCsrf($token) ? ok('CSRF válido aceptado') : fail('CSRF válido');
!Session::validarCsrf(null) ? ok('CSRF vacío rechazado') : fail('CSRF vacío');
!Session::validarCsrf('token-alterado') ? ok('CSRF alterado rechazado') : fail('CSRF alterado');

$cols = $db->query('SHOW COLUMNS FROM correo_cita')->fetchAll(\PDO::FETCH_COLUMN);
$prohibidas = ['Diagnostico', 'Historia', 'Antecedente', 'Notas', 'Body', 'Password', 'SMTP'];
$limpia = true;
foreach ($cols as $col) {
    foreach ($prohibidas as $p) {
        if (stripos((string) $col, $p) !== false) {
            $limpia = false;
        }
    }
}
$limpia ? ok('correo_cita sin columnas clínicas/secretos') : fail('correo_cita limpia');

$chk = (new \PDO(
    'mysql:host=localhost;dbname=consultorio_psicologico;charset=utf8mb4',
    'root',
    ''
))->query("SHOW TABLES LIKE 'correo_cita'")->fetchColumn();
!$chk ? ok('consultorio_psicologico aún sin correo_cita') : fail('consultorio_psicologico aún sin correo_cita');

echo "HUMO_FAILS={$fails}\n";
exit($fails > 0 ? 1 : 0);
