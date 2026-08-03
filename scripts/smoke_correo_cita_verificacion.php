<?php

declare(strict_types=1);

/**
 * Humo local correo_cita + cita (MAIL_CITA_DRY_RUN=1).
 * Limpia únicamente los datos creados.
 */

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Models\Cita;
use App\Services\AgendaService;
use App\Services\CorreoCitaService;
use App\Services\NotificacionService;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

Config::load();
Config::override([
    'MAIL_CITA_DRY_RUN' => '1',
    'APP_ENV' => 'development',
]);

$db = Database::connect();
$zona = new DateTimeZone('America/Mexico_City');
$clvPsi = 'PSI001';
$clvServ = 'SER001';
$clvPac = 'PAC001';

$agenda = new AgendaService();
$citaModel = new Cita();
$correo = new CorreoCitaService();

if (!$correo->persistenciaDisponible()) {
    fwrite(STDERR, "FAIL: correo_cita no disponible\n");
    exit(1);
}

$fecha = null;
$hora = null;
for ($i = 3; $i <= 21; $i++) {
    $candidato = (new DateTimeImmutable('now', $zona))->modify("+{$i} days")->format('Y-m-d');
    $disp = $agenda->calcularEspaciosDisponibles($clvPsi, $clvServ, $candidato);
    if (!empty($disp['ok']) && !empty($disp['espacios'][0]['valor'])) {
        $fecha = $candidato;
        $hora = (string) $disp['espacios'][0]['valor'];
        break;
    }
}

if ($fecha === null || $hora === '') {
    fwrite(STDERR, "FAIL: sin slot disponible >24h\n");
    exit(1);
}

echo "SLOT {$fecha} {$hora}\n";

$citaModel->beginTransaccion();
try {
    if (!$citaModel->bloquearPsicologoParaReserva($clvPsi)) {
        throw new RuntimeException('No se pudo bloquear psicólogo');
    }
    $validacion = $agenda->validarEspacioReserva($clvPsi, $clvServ, $fecha, $hora);
    if (empty($validacion['ok'])) {
        throw new RuntimeException($validacion['mensaje'] ?? 'validacion');
    }
    $datos = $validacion['datos'];
    $datos['ClvCita'] = $citaModel->generarClaveCita();
    $datos['ClvPac'] = $clvPac;
    $citaModel->crearCita($datos);
    $correo->prepararParaCitaNueva((string) $datos['ClvCita']);
    $citaModel->commitTransaccion();
    $clvCita = (string) $datos['ClvCita'];
} catch (Throwable $e) {
    if ($citaModel->inTransaccion()) {
        $citaModel->rollbackTransaccion();
    }
    fwrite(STDERR, 'FAIL alta: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "CITA {$clvCita}\n";

$row = $db->prepare('SELECT EstadoCita, CostoAplicado, DuracionAplicadaMin FROM cita WHERE ClvCita=:c');
$row->execute(['c' => $clvCita]);
$cita = $row->fetch(PDO::FETCH_ASSOC);
echo 'Estado=' . ($cita['EstadoCita'] ?? '') .
    ' Costo=' . ($cita['CostoAplicado'] ?? '') .
    ' Duracion=' . ($cita['DuracionAplicadaMin'] ?? '') . PHP_EOL;

$stmt = $db->prepare(
    "SELECT TipoCorreo, RolDestinatario, EstadoCorreo, FechaProgramada
     FROM correo_cita WHERE ClvCita=:c ORDER BY TipoCorreo, RolDestinatario"
);
$stmt->execute(['c' => $clvCita]);
$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "CORREOS=" . count($filas) . PHP_EOL;
foreach ($filas as $f) {
    echo sprintf(
        "  %s %s %s %s\n",
        $f['TipoCorreo'],
        $f['RolDestinatario'],
        $f['EstadoCorreo'],
        $f['FechaProgramada']
    );
}

$conf = array_filter($filas, static fn ($f) => $f['TipoCorreo'] === 'CONFIRMACION');
$rec = array_filter($filas, static fn ($f) => $f['TipoCorreo'] === 'RECORDATORIO_24H');
if (count($conf) !== 2 || count($rec) !== 2) {
    fwrite(STDERR, "FAIL conteo correos conf=" . count($conf) . " rec=" . count($rec) . "\n");
    exit(1);
}
foreach ($rec as $f) {
    if ($f['EstadoCorreo'] !== 'PENDIENTE') {
        fwrite(STDERR, "FAIL recordatorio no PENDIENTE\n");
        exit(1);
    }
}

$envio = $correo->procesarConfirmacionesInmediatas($clvCita);
echo 'CONF_DRYRUN paciente=' . (int) $envio['paciente'] . ' psicologo=' . (int) $envio['psicologo'] . PHP_EOL;

$stmt->execute(['c' => $clvCita]);
$filas2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
$confEnviados = 0;
foreach ($filas2 as $f) {
    if ($f['TipoCorreo'] === 'CONFIRMACION' && $f['EstadoCorreo'] === 'ENVIADO') {
        $confEnviados++;
    }
}
echo "CONF_ENVIADOS={$confEnviados}\n";

try {
    (new NotificacionService())->notificarCitaCreadaPorPaciente($clvCita);
    echo "NOTIF_OK\n";
} catch (Throwable $e) {
    echo "NOTIF_WARN " . $e->getMessage() . PHP_EOL;
}

// CLI lote (con dry-run vía override ya cargado en este proceso)
$lote1 = $correo->procesarLote(40);
echo 'LOTE1 ' . json_encode($lote1) . PHP_EOL;
$lote2 = $correo->procesarLote(40);
echo 'LOTE2 ' . json_encode($lote2) . PHP_EOL;

$countAntes = (int) $db->query(
    "SELECT COUNT(*) FROM correo_cita WHERE ClvCita=" . $db->quote($clvCita)
)->fetchColumn();

// Cancelar cita y forzar procesamiento de recordatorios
$db->prepare(
    "UPDATE cita SET EstadoCita='CANCELADA', MotivoCancelacion='HUMO_VERIFICACION', FechaCancelacion=NOW()
     WHERE ClvCita=:c"
)->execute(['c' => $clvCita]);
$db->prepare(
    "UPDATE correo_cita SET FechaProgramada=DATE_SUB(NOW(), INTERVAL 1 MINUTE)
     WHERE ClvCita=:c AND TipoCorreo='RECORDATORIO_24H'"
)->execute(['c' => $clvCita]);

$loteCancel = $correo->procesarLote(40);
echo 'LOTE_CANCEL ' . json_encode($loteCancel) . PHP_EOL;

$stmt->execute(['c' => $clvCita]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
    if ($f['TipoCorreo'] === 'RECORDATORIO_24H') {
        echo "REC_POST {$f['RolDestinatario']} {$f['EstadoCorreo']} {$f['MotivoOmision']}\n";
        if ($f['EstadoCorreo'] !== 'OMITIDO') {
            fwrite(STDERR, "FAIL recordatorio no omitido tras cancelar\n");
            exit(1);
        }
    }
}

$countDespues = (int) $db->query(
    "SELECT COUNT(*) FROM correo_cita WHERE ClvCita=" . $db->quote($clvCita)
)->fetchColumn();
if ($countDespues !== $countAntes) {
    fwrite(STDERR, "FAIL duplicados correo count {$countAntes}->{$countDespues}\n");
    exit(1);
}

// Limpieza exclusiva de esta prueba
$db->prepare('DELETE FROM correo_cita WHERE ClvCita=:c')->execute(['c' => $clvCita]);
$db->prepare(
    "DELETE FROM notificacion WHERE MensajeNotif LIKE :m OR TituloNotif LIKE :t"
)->execute([
    'm' => '%' . $clvCita . '%',
    't' => '%cita%',
]);
// Borrar notificaciones recientes de prueba por clave cita si existe columna
try {
    $db->prepare('DELETE FROM notificacion WHERE ClvCita=:c')->execute(['c' => $clvCita]);
} catch (Throwable $e) {
    // columna puede no existir
}
$db->prepare('DELETE FROM cita WHERE ClvCita=:c AND MotivoCancelacion=:m')
    ->execute(['c' => $clvCita, 'm' => 'HUMO_VERIFICACION']);

$queda = (int) $db->query(
    "SELECT COUNT(*) FROM cita WHERE ClvCita=" . $db->quote($clvCita)
)->fetchColumn();
$quedaCorreo = (int) $db->query(
    "SELECT COUNT(*) FROM correo_cita WHERE ClvCita=" . $db->quote($clvCita)
)->fetchColumn();

echo "CLEAN cita={$queda} correo={$quedaCorreo}\n";
echo "SMOKE_CORREO_CITA_OK\n";
