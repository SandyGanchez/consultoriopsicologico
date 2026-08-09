<?php

/**
 * Pruebas esenciales — agendar con dependientes (casos 1–12).
 *
 * Uso:
 *   php database/scripts/probar_agendar_dependientes.php
 *
 * - Solo BD temporal consultorio_psicologico_agendar_dep_prueba
 * - Aplica 4C (si falta) + 20260809_cita_responsable SOLO en temp
 * - NO modifica consultorio_psicologico (BD real)
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
use App\Models\Cita;
use App\Models\Paciente;
use App\Models\Persona;
use App\Models\Usuario;
use App\Services\ClaveService;
use App\Services\CorreoCitaService;
use App\Services\DependienteService;
use App\Services\IcsCitaService;
use App\Services\PrivacidadService;

Config::load(APP_ROOT . '/.env');

$DB_ORIG = 'consultorio_psicologico';
$DB_COPY = 'consultorio_psicologico_agendar_dep_prueba';

if (in_array(strtolower((string) Config::get('APP_ENV', '')), ['production', 'prod'], true)) {
    fwrite(STDERR, "BLOQUEADO: APP_ENV=production.\n");
    exit(99);
}

pm_rechazar_bd_no_prueba($DB_COPY, (string) Config::get('APP_ENV', ''));

$host = (string) Config::get('DB_HOST', 'localhost');
$user = (string) Config::get('DB_USER', 'root');
$pass = (string) Config::get('DB_PASS', '');

$pdoRoot = new PDO(
    "mysql:host={$host};charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ]
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

function pm_ag_columna(PDO $pdo, string $tabla, string $col): bool
{
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :t AND COLUMN_NAME = :c"
    );
    $st->execute(['t' => $tabla, 'c' => $col]);
    return (int) $st->fetchColumn() > 0;
}

/**
 * @param list<string> $tablas
 */
function pm_ag_copiar(PDO $pdo, string $origen, string $destino, array $tablas): void
{
    $pdo->exec("DROP DATABASE IF EXISTS `{$destino}`");
    $pdo->exec(
        "CREATE DATABASE `{$destino}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
    $pdo->exec("USE `{$destino}`");
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

    foreach ($tablas as $t) {
        $exists = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA=" . $pdo->quote($origen)
             . " AND TABLE_NAME=" . $pdo->quote($t)
        )->fetchColumn();
        if ($exists < 1) {
            continue;
        }
        $create = $pdo->query("SHOW CREATE TABLE `{$origen}`.`{$t}`")
            ->fetch(PDO::FETCH_NUM)[1];
        $create = preg_replace('/AUTO_INCREMENT=\d+/', 'AUTO_INCREMENT=1', $create) ?? $create;
        $pdo->exec($create);
        $rows = $pdo->query("SELECT * FROM `{$origen}`.`{$t}`")->fetchAll(PDO::FETCH_ASSOC);
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
}

function pm_ag_aplicar_cita_responsable(PDO $pdo): void
{
    if (!pm_ag_columna($pdo, 'cita', 'ClvUsuCreador')) {
        $pdo->exec(
            "ALTER TABLE `cita`
             ADD COLUMN `ClvUsuCreador` VARCHAR(10)
               CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL
               AFTER `ClvServ`,
             ADD COLUMN `OrigenCita` ENUM(
                 'PACIENTE','RESPONSABLE','PSICOLOGO','CONSULTORIO'
               ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
               NOT NULL DEFAULT 'PACIENTE'
               AFTER `ClvUsuCreador`,
             ADD COLUMN `IdRelacionResponsable` BIGINT UNSIGNED NULL
               AFTER `OrigenCita`"
        );
        $pdo->exec('ALTER TABLE `cita` ADD KEY `IDX_Cita_ClvUsuCreador` (`ClvUsuCreador`)');
        $pdo->exec('ALTER TABLE `cita` ADD KEY `IDX_Cita_OrigenCita` (`OrigenCita`)');
        $pdo->exec(
            'ALTER TABLE `cita` ADD KEY `IDX_Cita_IdRelacionResponsable` (`IdRelacionResponsable`)'
        );
        $pdo->exec(
            "ALTER TABLE `cita`
             ADD CONSTRAINT `FK_Cita_UsuCreador`
               FOREIGN KEY (`ClvUsuCreador`) REFERENCES `usuario` (`ClvUsu`)
               ON DELETE RESTRICT ON UPDATE CASCADE,
             ADD CONSTRAINT `FK_Cita_RelacionResponsable`
               FOREIGN KEY (`IdRelacionResponsable`) REFERENCES `paciente_responsable` (`IdRelacion`)
               ON DELETE RESTRICT ON UPDATE CASCADE"
        );
    }

    $tipo = (string) ($pdo->query(
        "SHOW COLUMNS FROM correo_cita LIKE 'RolDestinatario'"
    )->fetch(PDO::FETCH_ASSOC)['Type'] ?? '');
    if ($tipo !== '' && stripos($tipo, 'RESPONSABLE') === false) {
        $pdo->exec(
            "ALTER TABLE `correo_cita`
             MODIFY COLUMN `RolDestinatario` ENUM(
                 'PACIENTE','PSICOLOGO','RESPONSABLE'
             ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL"
        );
    }
}

$tablas = [
    'persona',
    'usuario',
    'direccion',
    'consultorio',
    'consultorio_usuario',
    'psicologo',
    'servicios',
    'psicologo_servicio',
    'paciente',
    'paciente_responsable',
    'horario',
    'cita',
    'aviso_privacidad_version',
    'consentimiento_datos_personales',
    'notificacion',
    'correo_cita',
];

echo "=== Agendar dependientes: BD temporal ===\n";

// 1) BD real LOCAL debe tener migracion aplicada (cierre local)
$pdoRoot->exec("USE `{$DB_ORIG}`");
$realTiene = pm_ag_columna($pdoRoot, 'cita', 'ClvUsuCreador');
$check(
    'K. BD real con ClvUsuCreador (migracion APLICADA LOCAL)',
    $realTiene,
    $realTiene ? 'ok aplicada' : 'PENDIENTE en real — aplicar 20260809_cita_responsable.sql'
);

pm_ag_copiar($pdoRoot, $DB_ORIG, $DB_COPY, $tablas);
$pdoRoot->exec("USE `{$DB_COPY}`");

// Resolver ClvUsu duplicados en copia (misma lógica mínima 4C)
$dups = $pdoRoot->query(
    "SELECT ClvUsu FROM paciente WHERE ClvUsu IS NOT NULL
     GROUP BY ClvUsu HAVING COUNT(*) > 1"
)->fetchAll(PDO::FETCH_COLUMN);
foreach ($dups as $clvUsuDup) {
    $pacs = $pdoRoot->prepare(
        'SELECT ClvPac FROM paciente WHERE ClvUsu = :u ORDER BY ClvPac'
    );
    $pacs->execute(['u' => $clvUsuDup]);
    $lista = $pacs->fetchAll(PDO::FETCH_COLUMN);
    array_shift($lista);
    foreach ($lista as $clvPacDup) {
        $suf = substr(bin2hex(random_bytes(3)), 0, 6);
        $clvPerNew = 'PERAG' . $suf;
        $clvUsuNew = 'UAG' . $suf;
        $pdoRoot->exec(
            "INSERT INTO persona (ClvPer, NombrePer, ApPatPer, ApMatPer, FechaNacimiento, GeneroPer)
             VALUES (" . $pdoRoot->quote($clvPerNew) . ", 'Fix', 'Ag', 'Dup', '1990-01-01', 'Otro')"
        );
        $pdoRoot->exec(
            "INSERT INTO usuario (
                ClvUsu, CorreoUsu, TelefonoUsu, ContrasenaUsu, EstadoUsu,
                RequiereCambioContrasena, CorreoVerificado, RolUsu, ClvPer
             ) VALUES (
                " . $pdoRoot->quote($clvUsuNew) . ",
                " . $pdoRoot->quote('fix.ag.' . $suf . '@example.test') . ",
                '7220000000',
                " . $pdoRoot->quote(password_hash('x', PASSWORD_DEFAULT)) . ",
                1, 0, 1, 'PACIENTE',
                " . $pdoRoot->quote($clvPerNew) . "
             )"
        );
        $pdoRoot->prepare(
            'UPDATE paciente SET ClvUsu = :u WHERE ClvPac = :p'
        )->execute(['u' => $clvUsuNew, 'p' => $clvPacDup]);
    }
}

// Asegurar esquema 4C en temp (si la copia vino de BD ya migrada, no-op parcial)
if (!pm_ag_columna($pdoRoot, 'paciente', 'ClvPer')) {
    fwrite(STDERR, "ABORT: copia sin paciente.ClvPer; aplica 4C en origen o usa script 4C primero.\n");
    exit(2);
}
if ((int) $pdoRoot->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='paciente_responsable'"
)->fetchColumn() < 1) {
    fwrite(STDERR, "ABORT: falta paciente_responsable en copia temporal.\n");
    exit(2);
}

pm_ag_aplicar_cita_responsable($pdoRoot);
$check(
    '7. migración cita_responsable en TEMP',
    pm_ag_columna($pdoRoot, 'cita', 'ClvUsuCreador')
    && pm_ag_columna($pdoRoot, 'cita', 'OrigenCita')
    && pm_ag_columna($pdoRoot, 'cita', 'IdRelacionResponsable')
);

$tipoRol = (string) ($pdoRoot->query(
    "SHOW COLUMNS FROM correo_cita LIKE 'RolDestinatario'"
)->fetch(PDO::FETCH_ASSOC)['Type'] ?? '');
$check('11. ENUM RolDestinatario incluye RESPONSABLE', stripos($tipoRol, 'RESPONSABLE') !== false);

Config::override([
    'DB_NAME' => $DB_COPY,
    'MAIL_CITA_DRY_RUN' => '1',
    'MAIL_VERIFICACION_DRY_RUN' => '1',
]);
Database::resetConnection();
pm_rechazar_bd_no_prueba(
    (string) Config::get('DB_NAME', ''),
    (string) Config::get('APP_ENV', '')
);

$pdo = Database::connect();
echo "BD de prueba: {$DB_COPY}\n\n";

$citaModel = new Cita();
$check('G. columnasResponsableDisponibles()', $citaModel->columnasResponsableDisponibles());

$psi = $pdo->query(
    "SELECT p.ClvPsi, p.ClvCons, s.ClvServ,
            COALESCE(ps.DuracionMinutos, s.DuracionMinutos) AS DuracionMinutos,
            COALESCE(ps.PrecioServicio, s.CostoServicio) AS CostoServicio
     FROM psicologo p
     INNER JOIN psicologo_servicio ps ON ps.ClvPsi = p.ClvPsi
     INNER JOIN servicios s ON s.ClvServ = ps.ClvServ
     LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

if (!$psi) {
    echo "ABORT: no hay psicólogo/servicio activos en la copia.\n";
    exit(2);
}

$clvCons = (string) ($psi['ClvCons'] ?? '');
if ($clvCons === '') {
    $clvCons = (string) ($pdo->query(
        'SELECT ClvCons FROM consultorio ORDER BY ClvCons LIMIT 1'
    )->fetchColumn() ?: '');
}

$suf = bin2hex(random_bytes(3));
$clvPerR = ClaveService::generar('persona', 'ClvPer', 'PER');
$clvUsuR = ClaveService::generar('usuario', 'ClvUsu', 'U');
$clvPacR = ClaveService::generar('paciente', 'ClvPac', 'PAC');

$pdo->beginTransaction();
(new Persona())->crear([
    'ClvPer' => $clvPerR,
    'NombrePer' => 'Resp',
    'ApPatPer' => 'Agendar',
    'ApMatPer' => 'Test',
    'FechaNacimiento' => '1985-06-15',
    'GeneroPer' => 'Otro',
]);
(new Usuario())->crear([
    'ClvUsu' => $clvUsuR,
    'CorreoUsu' => "resp.ag.{$suf}@example.test",
    'TelefonoUsu' => '7229988776',
    'ContrasenaUsu' => password_hash('ClaveSegura1', PASSWORD_DEFAULT),
    'ClvPer' => $clvPerR,
]);
$pdo->prepare(
    'UPDATE usuario SET EstadoUsu=1, RequiereCambioContrasena=0, CorreoVerificado=1 WHERE ClvUsu=:u'
)->execute(['u' => $clvUsuR]);
(new Paciente())->crear([
    'ClvPac' => $clvPacR,
    'ClvPer' => $clvPerR,
    'ClvUsu' => $clvUsuR,
    'ClvCons' => $clvCons,
]);
$pdo->commit();

(new PrivacidadService())->registrarConsentimiento(
    $clvUsuR,
    'REGISTRO',
    ['aviso_leido' => '1', 'consentimiento_sensibles' => '1'],
    '1985-06-15',
    ['ClvPacSujeto' => $clvPacR]
);

$fechaCita = (new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City')))
    ->modify('+14 days')
    ->format('Y-m-d');

$mkReserva = static function (
    string $clvPac,
    string $clvUsuCreador,
    string $origen,
    ?int $idRel
) use ($citaModel, $psi, $fechaCita): string {
    $clvCita = $citaModel->generarClaveCita();
    $citaModel->crearCita([
        'ClvCita' => $clvCita,
        'FechaCita' => $fechaCita,
        'HraInicioCita' => '10:00:00',
        'HraFinCita' => '10:50:00',
        'DuracionAplicadaMin' => (int) ($psi['DuracionMinutos'] ?? 50),
        'CostoAplicado' => (float) ($psi['CostoServicio'] ?? 0),
        'ClvPac' => $clvPac,
        'ClvPsi' => (string) $psi['ClvPsi'],
        'ClvCons' => (string) $psi['ClvCons'],
        'ClvServ' => (string) $psi['ClvServ'],
        'ClvUsuCreador' => $clvUsuCreador,
        'OrigenCita' => $origen,
        'IdRelacionResponsable' => $idRel,
    ]);
    return $clvCita;
};

// Caso Yo (6)
$clvYo = $mkReserva($clvPacR, $clvUsuR, 'PACIENTE', null);
$rowYo = $pdo->query(
    'SELECT ClvPac, ClvUsuCreador, OrigenCita, IdRelacionResponsable FROM cita WHERE ClvCita='
    . $pdo->quote($clvYo)
)->fetch(PDO::FETCH_ASSOC);
$check(
    '6. cita Yo: ClvPac propio + Origen PACIENTE + IdRelacion NULL',
    ($rowYo['ClvPac'] ?? '') === $clvPacR
    && ($rowYo['ClvUsuCreador'] ?? '') === $clvUsuR
    && ($rowYo['OrigenCita'] ?? '') === 'PACIENTE'
    && ($rowYo['IdRelacionResponsable'] ?? null) === null
);

// Dependiente existente (4, 8–10)
$depSvc = new DependienteService();
$alta = $depSvc->crear($clvUsuR, [
    'nombre' => 'Hijo',
    'apPat' => 'Agendar',
    'apMat' => 'Dep',
    'fechaNacimiento' => '2012-03-20',
    'genero' => 'Masculino',
    'parentesco' => 'Hijo',
    'EsTutorLegal' => '1',
    'aviso_leido' => '1',
    'consentimiento_sensibles' => '1',
]);
$check('F. DependienteService alta inline', !empty($alta['ok']), (string) ($alta['mensaje'] ?? ''));
$clvPacDep = (string) ($alta['clvPac'] ?? '');
$idRel = (int) ($alta['idRelacion'] ?? 0);

$listaAg = $depSvc->listarParaAgendar($clvUsuR);
$enLista = false;
foreach ($listaAg as $f) {
    if (($f['ClvPac'] ?? '') === $clvPacDep) {
        $enLista = true;
        break;
    }
}
$check('D. listarParaAgendar incluye ACTIVA+PuedeAgendar', $enLista);

$relOk = $depSvc->relacionParaAgendar($clvUsuR, $clvPacDep);
$check('5. perteneceAResponsable / PuedeAgendar', $relOk !== null);

// IDOR: otro usuario
$clvUsuAjeno = ClaveService::generar('usuario', 'ClvUsu', 'U');
$clvPerAjeno = ClaveService::generar('persona', 'ClvPer', 'PER');
(new Persona())->crear([
    'ClvPer' => $clvPerAjeno,
    'NombrePer' => 'Ajeno',
    'ApPatPer' => 'X',
    'ApMatPer' => '',
    'FechaNacimiento' => '1990-01-01',
    'GeneroPer' => 'Otro',
]);
(new Usuario())->crear([
    'ClvUsu' => $clvUsuAjeno,
    'CorreoUsu' => "ajeno.ag.{$suf}@example.test",
    'TelefonoUsu' => '7221112233',
    'ContrasenaUsu' => password_hash('ClaveSegura1', PASSWORD_DEFAULT),
    'ClvPer' => $clvPerAjeno,
]);
$idor = $depSvc->relacionParaAgendar($clvUsuAjeno, $clvPacDep);
$check('5b. IDOR: ajeno no agenda ese ClvPac', $idor === null);

// PuedeAgendar=0
$pdo->prepare(
    'UPDATE paciente_responsable SET PuedeAgendar=0 WHERE IdRelacion=:i'
)->execute(['i' => $idRel]);
$sinPermiso = $depSvc->relacionParaAgendar($clvUsuR, $clvPacDep);
$check('9. PuedeAgendar=0 bloquea', $sinPermiso === null);
$pdo->prepare(
    'UPDATE paciente_responsable SET PuedeAgendar=1 WHERE IdRelacion=:i'
)->execute(['i' => $idRel]);

$clvDepCita = $mkReserva($clvPacDep, $clvUsuR, 'RESPONSABLE', $idRel);
$rowDep = $pdo->query(
    'SELECT ClvPac, ClvUsuCreador, OrigenCita, IdRelacionResponsable FROM cita WHERE ClvCita='
    . $pdo->quote($clvDepCita)
)->fetch(PDO::FETCH_ASSOC);
$check(
    '8–10. cita dependiente: ClvPac=dep, Origen RESPONSABLE, IdRelacion',
    ($rowDep['ClvPac'] ?? '') === $clvPacDep
    && ($rowDep['ClvUsuCreador'] ?? '') === $clvUsuR
    && ($rowDep['OrigenCita'] ?? '') === 'RESPONSABLE'
    && (int) ($rowDep['IdRelacionResponsable'] ?? 0) === $idRel
);

// Correo RESPONSABLE (11–12)
$correoSvc = new CorreoCitaService();
if ($correoSvc->persistenciaDisponible()) {
    $correoSvc->prepararParaCitaNueva($clvDepCita);
    $rolResp = (int) $pdo->query(
        "SELECT COUNT(*) FROM correo_cita
         WHERE ClvCita=" . $pdo->quote($clvDepCita) . "
           AND TipoCorreo='CONFIRMACION'
           AND RolDestinatario='RESPONSABLE'
           AND ClvUsuDestino=" . $pdo->quote($clvUsuR)
    )->fetchColumn();
    $rolPsi = (int) $pdo->query(
        "SELECT COUNT(*) FROM correo_cita
         WHERE ClvCita=" . $pdo->quote($clvDepCita) . "
           AND TipoCorreo='CONFIRMACION'
           AND RolDestinatario='PSICOLOGO'"
    )->fetchColumn();
    $check('12. correo CONFIRMACION → RESPONSABLE (creador)', $rolResp === 1);
    $check('12b. correo CONFIRMACION → PSICOLOGO se mantiene', $rolPsi === 1);

    $ctx = $correoSvc->obtenerContextoCita($clvDepCita);
    $check(
        'H. contexto con NombrePaciente + NombreResponsable',
        is_array($ctx)
        && trim((string) ($ctx['NombrePaciente'] ?? '')) !== ''
        && trim((string) ($ctx['NombreResponsable'] ?? '')) !== ''
        && trim((string) ($ctx['CorreoResponsable'] ?? '')) !== ''
    );
} else {
    $check('12. correo_cita disponible', false, 'tabla ausente');
}

// ICS (17)
$ics = (new IcsCitaService())->generarParaCita($clvDepCita);
$check(
    'I. ICS generado con UID estable ClvCita',
    is_array($ics)
    && str_contains($ics['contenido'], 'BEGIN:VCALENDAR')
    && str_contains($ics['contenido'], 'UID:' . $clvDepCita . '@')
);

$detalle = $citaModel->obtenerDetalleParaCuentaPaciente($clvDepCita, $clvPacR, $clvUsuR);
$check('acceso detalle por responsable/creador', $detalle !== null);

$detalleIdor = $citaModel->obtenerDetalleParaCuentaPaciente(
    $clvDepCita,
    'PACNOEXIST',
    $clvUsuAjeno
);
$check('detalle IDOR denegado a ajeno', $detalleIdor === null);

echo "\n=== Resumen ===\n";
echo "PASS={$passCount} FAIL={$failCount}\n";
echo "BD real {$DB_ORIG}: migracion cita_responsable APLICADA LOCAL (temp aislada para pruebas).\n";
exit($failCount > 0 ? 1 : 0);
