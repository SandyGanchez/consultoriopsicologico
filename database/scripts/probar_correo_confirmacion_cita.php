<?php

/**
 * Pruebas mínimas — correo confirmación de cita (casos 1–4).
 *
 * Uso:
 *   php database/scripts/probar_correo_confirmacion_cita.php
 *   php database/scripts/probar_correo_confirmacion_cita.php --smtp-real
 *
 * - Solo BD temporal consultorio_psicologico_correo_conf_prueba
 * - NO modifica consultorio_psicologico
 * - Por defecto MAIL_CITA_DRY_RUN=1 (sin SMTP)
 * - --smtp-real: un envío real PACIENTE si SMTP/.env lo permiten
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Solo CLI.\n");
    exit(1);
}

define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';
require __DIR__ . '/_guard_bd_prueba.php';

use App\Config\Config;
use App\Config\Database;
use App\Services\CorreoCitaService;
use App\Services\IcsCitaService;
use App\Services\MailService;

Config::load(APP_ROOT . '/.env');

$DB_ORIG = 'consultorio_psicologico';
$DB_COPY = 'consultorio_psicologico_correo_conf_prueba';
$smtpReal = in_array('--smtp-real', $argv, true);

if (in_array(strtolower((string) Config::get('APP_ENV', '')), ['production', 'prod'], true)) {
    fwrite(STDERR, "BLOQUEADO: APP_ENV=production.\n");
    exit(99);
}

pm_rechazar_bd_no_prueba($DB_COPY, (string) Config::get('APP_ENV', ''));

$host = (string) Config::get('DB_HOST', 'localhost');
$user = (string) Config::get('DB_USER', 'root');
$pass = (string) Config::get('DB_PASS', '');

$pdo = new PDO(
    "mysql:host={$host};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$passCount = 0;
$failCount = 0;
$check = static function (string $name, bool $ok, string $detail = '') use (&$passCount, &$failCount): void {
    if ($ok) {
        $passCount++;
        echo "PASS: {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    } else {
        $failCount++;
        echo "FAIL: {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
};

$tablas = [
    'persona', 'usuario', 'direccion', 'consultorio', 'consultorio_usuario',
    'psicologo', 'servicios', 'psicologo_servicio', 'paciente',
    'paciente_responsable', 'cita', 'correo_cita',
];

echo "=== Correo confirmación cita (temp) ===\n";

$pdo->exec("DROP DATABASE IF EXISTS `{$DB_COPY}`");
$pdo->exec(
    "CREATE DATABASE `{$DB_COPY}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
);
$pdo->exec("USE `{$DB_COPY}`");
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($tablas as $t) {
    $exists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=" . $pdo->quote($DB_ORIG)
         . " AND TABLE_NAME=" . $pdo->quote($t)
    )->fetchColumn();
    if ($exists < 1) {
        continue;
    }
    $create = $pdo->query("SHOW CREATE TABLE `{$DB_ORIG}`.`{$t}`")
        ->fetch(PDO::FETCH_NUM)[1];
    $create = preg_replace('/AUTO_INCREMENT=\d+/', 'AUTO_INCREMENT=1', $create) ?? $create;
    $pdo->exec($create);
    $rows = $pdo->query("SELECT * FROM `{$DB_ORIG}`.`{$t}`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $cols = array_keys($r);
        $ph = array_map(static fn ($x) => ':' . $x, $cols);
        $st = $pdo->prepare(
            "INSERT INTO `{$t}` (`" . implode('`,`', $cols) . '`) VALUES ('
            . implode(',', $ph) . ')'
        );
        foreach ($r as $k => $v) {
            $st->bindValue(':' . $k, $v);
        }
        $st->execute();
    }
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

Config::override([
    'DB_NAME' => $DB_COPY,
    'MAIL_CITA_DRY_RUN' => '1',
]);
$ref = new ReflectionClass(Database::class);
$prop = $ref->getProperty('connection');
$prop->setAccessible(true);
$prop->setValue(null, null);
$db = Database::connect();
$actual = (string) $db->query('SELECT DATABASE()')->fetchColumn();
if ($actual !== $DB_COPY) {
    fwrite(STDERR, "ABORT: DATABASE()={$actual}\n");
    exit(1);
}

$base = $db->query(
    "SELECT
        psi.ClvPsi, psi.ClvCons, psi.ClvUsu AS ClvUsuPsi,
        pac.ClvPac, pac.ClvUsu AS ClvUsuPac, pac.ClvPer AS ClvPerPac,
        s.ClvServ,
        usuPac.CorreoUsu AS CorreoPac
     FROM psicologo psi
     INNER JOIN paciente pac ON pac.ClvCons = psi.ClvCons AND pac.ClvUsu IS NOT NULL
     INNER JOIN usuario usuPac ON usuPac.ClvUsu = pac.ClvUsu
     INNER JOIN servicios s ON s.ClvCons = psi.ClvCons AND s.EstatusServicio = 'ACTIVO'
     LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

if (!$base) {
    $check('datos base para prueba', false, 'sin psicólogo/paciente/servicio');
    echo "PASS={$passCount} FAIL={$failCount}\n";
    exit(1);
}

$zona = new DateTimeZone('America/Mexico_City');
$mkCita = static function (
    PDO $db,
    array $base,
    string $clvPac,
    string $clvUsuCreador,
    string $origen,
    ?int $idRel
) use ($zona): string {
    $inicio = (new DateTimeImmutable('now', $zona))->modify('+3 days')->setTime(10, 0, 0);
    $fin = $inicio->modify('+60 minutes');
    $clv = 'CC' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    $st = $db->prepare(
        "INSERT INTO cita (
            ClvCita, FechaCita, HraInicioCita, HraFinCita,
            DuracionAplicadaMin, CostoAplicado, EstadoCita,
            ClvPac, ClvPsi, ClvCons, ClvServ,
            ClvUsuCreador, OrigenCita, IdRelacionResponsable
         ) VALUES (
            :c, :f, :hi, :hf, 60, 500.00, 'PROGRAMADA',
            :pac, :psi, :cons, :serv,
            :creador, :origen, :rel
         )"
    );
    $st->execute([
        'c' => $clv,
        'f' => $inicio->format('Y-m-d'),
        'hi' => $inicio->format('H:i:s'),
        'hf' => $fin->format('H:i:s'),
        'pac' => $clvPac,
        'psi' => $base['ClvPsi'],
        'cons' => $base['ClvCons'],
        'serv' => $base['ClvServ'],
        'creador' => $clvUsuCreador,
        'origen' => $origen,
        'rel' => $idRel,
    ]);

    return $clv;
};

$correoSvc = new CorreoCitaService($db);
$mailSvc = new MailService();

// —— Caso 1: cita propia (Origen PACIENTE → ClvUsuCreador, rol PACIENTE)
$clvPropia = $mkCita(
    $db,
    $base,
    (string) $base['ClvPac'],
    (string) $base['ClvUsuPac'],
    'PACIENTE',
    null
);
$correoSvc->prepararParaCitaNueva($clvPropia);
$rowPropia = $db->query(
    "SELECT RolDestinatario, ClvUsuDestino, TipoCorreo, EstadoCorreo
     FROM correo_cita WHERE ClvCita=" . $db->quote($clvPropia)
     . " AND TipoCorreo='CONFIRMACION' ORDER BY RolDestinatario"
)->fetchAll(PDO::FETCH_ASSOC);
$destPac = null;
$destPsi = null;
foreach ($rowPropia as $r) {
    if ($r['RolDestinatario'] === 'PACIENTE') {
        $destPac = $r;
    }
    if ($r['RolDestinatario'] === 'PSICOLOGO') {
        $destPsi = $r;
    }
}
$check(
    '1. propia: CONFIRMACION PACIENTE → ClvUsuCreador',
    is_array($destPac)
    && ($destPac['ClvUsuDestino'] ?? '') === $base['ClvUsuPac']
);
$check('1b. propia: CONFIRMACION PSICOLOGO presente', is_array($destPsi));
$ctxPropia = $correoSvc->obtenerContextoCita($clvPropia);
$htmlPropia = $mailSvc->previsualizarConfirmacionCitaHtml($ctxPropia ?? [], 'PACIENTE');
$check(
    'A/E. HTML propia: encabezado + fecha/hora + botones',
    is_array($ctxPropia)
    && str_contains($htmlPropia, 'Cita confirmada')
    && str_contains($htmlPropia, 'Cita para')
    && str_contains($htmlPropia, 'Agregar al calendario')
    && str_contains($htmlPropia, 'Cómo llegar')
    && str_contains((string) ($ctxPropia['UrlGoogleCalendar'] ?? ''), 'calendar.google.com')
    && trim((string) ($ctxPropia['UrlComoLlegar'] ?? '')) !== ''
    && trim((string) ($ctxPropia['NombrePaciente'] ?? '')) !== ''
);

$captura = APP_ROOT . '/storage/tmp/correo_confirmacion_propia.html';
@mkdir(dirname($captura), 0775, true);
file_put_contents($captura, $htmlPropia);
$check('captura HTML propia', is_file($captura), $captura);

$icsPropia = (new IcsCitaService($db))->generarParaCita($clvPropia);
$check(
    'F. ICS propia UID estable + filename',
    is_array($icsPropia)
    && str_contains($icsPropia['contenido'], 'UID:' . $clvPropia . '@psicomatch')
    && $icsPropia['filename'] === 'cita-' . $clvPropia . '.ics'
);

// —— Caso 2/3: dependiente sin usuario propio
$suf = substr(bin2hex(random_bytes(3)), 0, 5);
$clvPerDep = 'PE' . $suf;
$clvPacDep = 'PA' . $suf;
$db->exec(
    "INSERT INTO persona (ClvPer, NombrePer, ApPatPer, ApMatPer, FechaNacimiento, GeneroPer)
     VALUES (" . $db->quote($clvPerDep) . ", 'Hijo', 'Prueba', 'Correo', '2015-05-01', 'Otro')"
);
$db->prepare(
    "INSERT INTO paciente (ClvPac, ClvUsu, ClvCons, ClvPer, EstadoActivoPac)
     VALUES (:p, NULL, :c, :per, 1)"
)->execute([
    'p' => $clvPacDep,
    'c' => $base['ClvCons'],
    'per' => $clvPerDep,
]);
$db->prepare(
    "INSERT INTO paciente_responsable (
        ClvPac, ClvUsuResponsable, Parentesco, EsTutorLegal, PuedeAgendar, EstadoRelacion, FechaRegistro
     ) VALUES (:p, :u, 'Padre/Madre', 1, 1, 'ACTIVA', NOW())"
)->execute([
    'p' => $clvPacDep,
    'u' => $base['ClvUsuPac'],
]);
$idRel = (int) $db->lastInsertId();

$clvDep = $mkCita(
    $db,
    $base,
    $clvPacDep,
    (string) $base['ClvUsuPac'],
    'RESPONSABLE',
    $idRel
);
$correoSvc->prepararParaCitaNueva($clvDep);
$rowsDep = $db->query(
    "SELECT RolDestinatario, ClvUsuDestino FROM correo_cita
     WHERE ClvCita=" . $db->quote($clvDep) . " AND TipoCorreo='CONFIRMACION'"
)->fetchAll(PDO::FETCH_ASSOC);
$rolResp = false;
$rolPacErr = false;
$rolPsiDep = false;
foreach ($rowsDep as $r) {
    if ($r['RolDestinatario'] === 'RESPONSABLE'
        && $r['ClvUsuDestino'] === $base['ClvUsuPac']
    ) {
        $rolResp = true;
    }
    if ($r['RolDestinatario'] === 'PACIENTE') {
        $rolPacErr = true;
    }
    if ($r['RolDestinatario'] === 'PSICOLOGO') {
        $rolPsiDep = true;
    }
}
$check('2/3. dependiente sin usuario: CONFIRMACION → RESPONSABLE (creador)', $rolResp);
$check('2/3b. NUNCA CONFIRMACION PACIENTE del dependiente', !$rolPacErr);
$check('D. PSICOLOGO se mantiene en dependiente', $rolPsiDep);

$ctxDep = $correoSvc->obtenerContextoCita($clvDep);
$htmlDep = $mailSvc->previsualizarConfirmacionCitaHtml($ctxDep ?? [], 'RESPONSABLE');
$check(
    'B/H. HTML dependiente: bloque Paciente/Reservación/Parentesco + Maps',
    is_array($ctxDep)
    && ($ctxDep['ClvUsuPaciente'] === null || trim((string) $ctxDep['ClvUsuPaciente']) === '')
    && trim((string) ($ctxDep['NombrePaciente'] ?? '')) !== ''
    && str_contains($htmlDep, 'Reservación realizada por')
    && str_contains($htmlDep, 'Parentesco')
    && str_contains($htmlDep, 'Padre/Madre')
    && !str_contains($htmlDep, $clvPacDep)
    && str_contains((string) ($ctxDep['UrlComoLlegar'] ?? ''), 'google.com/maps')
);
$capturaDep = APP_ROOT . '/storage/tmp/correo_confirmacion_dependiente.html';
file_put_contents($capturaDep, $htmlDep);
$check('captura HTML dependiente', is_file($capturaDep), $capturaDep);

// —— Caso 4: fallo SMTP controlado (cita no se revierte; estado FALLIDO)
$clvFail = $mkCita(
    $db,
    $base,
    (string) $base['ClvPac'],
    (string) $base['ClvUsuPac'],
    'PACIENTE',
    null
);
Config::override(['MAIL_CITA_DRY_RUN' => '0']);
$mailFalla = new class extends MailService {
    public function enviarConfirmacionCitaPaciente(array $contexto): void
    {
        throw new RuntimeException('SMTP simulado fallido');
    }

    public function enviarConfirmacionCitaPsicologo(array $contexto): void
    {
        // ok psicólogo en dry conceptual
    }
};
$svcFail = new CorreoCitaService($db, null, $mailFalla);
$svcFail->prepararParaCitaNueva($clvFail);
$envio = $svcFail->procesarConfirmacionesInmediatas($clvFail);
$estadoPac = (string) $db->query(
    "SELECT EstadoCorreo FROM correo_cita
     WHERE ClvCita=" . $db->quote($clvFail)
     . " AND TipoCorreo='CONFIRMACION' AND RolDestinatario='PACIENTE' LIMIT 1"
)->fetchColumn();
$citaSigue = (string) $db->query(
    'SELECT EstadoCita FROM cita WHERE ClvCita=' . $db->quote($clvFail)
)->fetchColumn();
$check(
    'I. fallo SMTP → correo FALLIDO, cita PROGRAMADA intacta',
    $envio['paciente'] === false
    && $estadoPac === 'FALLIDO'
    && $citaSigue === 'PROGRAMADA'
    && !empty($envio['mensajeCorreo'])
);

// Google Calendar / ICS en contexto dependiente
$check(
    'G. UrlGoogleCalendar con dates',
    is_array($ctxDep)
    && str_contains((string) ($ctxDep['UrlGoogleCalendar'] ?? ''), 'dates=')
);

Config::override(['MAIL_CITA_DRY_RUN' => '1']);

// —— Envío real opcional (una sola vez)
if ($smtpReal) {
    echo "\n--- Intento SMTP real (1 correo PACIENTE) ---\n";
    Config::override([
        'MAIL_CITA_DRY_RUN' => '0',
        'MAIL_FORCE_IPV4' => '1',
    ]);
    $clvSmtp = $mkCita(
        $db,
        $base,
        (string) $base['ClvPac'],
        (string) $base['ClvUsuPac'],
        'PACIENTE',
        null
    );
    // Redirigir destino a MAIL_FROM_ADDRESS para no spamear pacientes reales
    $correoPrueba = trim((string) Config::get('MAIL_FROM_ADDRESS', ''));
    $ctxSmtp = $correoSvc->obtenerContextoCita($clvSmtp);
    if (!is_array($ctxSmtp) || $correoPrueba === '') {
        $check('SMTP real: contexto/correo prueba', false);
    } else {
        $ctxSmtp['CorreoPaciente'] = $correoPrueba;
        $ctxSmtp['CorreoCreador'] = $correoPrueba;
        try {
            (new MailService())->enviarConfirmacionCitaPaciente($ctxSmtp);
            $check('SMTP real: envío PACIENTE a MAIL_FROM_ADDRESS', true, $correoPrueba);
        } catch (Throwable $e) {
            $check(
                'SMTP real: envío PACIENTE',
                false,
                'falló (sin stack al usuario): ENVIO_CORREO_FALLIDO / revisar red-SMTP'
            );
            echo "DETAIL: " . substr($e->getMessage(), 0, 180) . "\n";
        }
    }
} else {
    echo "\n(SMTP real omitido; use --smtp-real para un envío)\n";
}

echo "\n=== Resumen ===\n";
echo "PASS={$passCount} FAIL={$failCount}\n";
echo "HTML: storage/tmp/correo_confirmacion_propia.html\n";
echo "HTML: storage/tmp/correo_confirmacion_dependiente.html\n";
exit($failCount > 0 ? 1 : 0);
