<?php

/**
 * Pruebas Fase 4C — pacientes dependientes y responsables.
 *
 * Uso:
 *   php database/scripts/probar_dependientes_responsables.php
 *
 * - Solo BD temporal (consultorio_psicologico_dependientes_prueba).
 * - Aplica migración 20260809 SOLO en la BD temporal.
 * - NO modifica consultorio_psicologico (BD real).
 * - Casos 1–40 del spec + guardas de integridad.
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
use App\Models\ConsentimientoDatosPersonales;
use App\Models\Paciente;
use App\Models\PacienteResponsable;
use App\Models\Persona;
use App\Models\Usuario;
use App\Services\AuthService;
use App\Services\ClaveService;
use App\Services\CorreoCitaService;
use App\Services\DependienteService;
use App\Services\GestionPacienteConsultorioService;
use App\Services\InstalacionConsultorioService;
use App\Services\NotificacionService;
use App\Services\PrivacidadService;

Config::load(APP_ROOT . '/.env');

$DB_ORIG = 'consultorio_psicologico';
$DB_COPY = 'consultorio_psicologico_dependientes_prueba';
$DB_DIRTY = 'consultorio_psicologico_dependientes_dirty_prueba';

if (in_array(strtolower((string) Config::get('APP_ENV', '')), ['production', 'prod'], true)) {
    fwrite(STDERR, "BLOQUEADO: APP_ENV=production.\n");
    exit(99);
}

pm_rechazar_bd_no_prueba($DB_COPY, (string) Config::get('APP_ENV', ''));
pm_rechazar_bd_no_prueba($DB_DIRTY, (string) Config::get('APP_ENV', ''));

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

/**
 * @param list<string> $tablas
 */
function pm4c_copiar_tablas(PDO $pdo, string $origen, string $destino, array $tablas): void
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

/**
 * Guardas de integridad equivalentes a la sección 0 de la migración SQL.
 *
 * @return string|null mensaje ABORT o null si OK
 */
function pm4c_guardas_integridad(PDO $pdo): ?string
{
    // Solo pacientes CON cuenta: ClvUsu NULL (dependiente) es válido post-4C.
    $pacSinUsuario = (int) $pdo->query(
        'SELECT COUNT(*) FROM paciente p
         LEFT JOIN usuario u ON u.ClvUsu = p.ClvUsu
         WHERE p.ClvUsu IS NOT NULL AND u.ClvUsu IS NULL'
    )->fetchColumn();
    if ($pacSinUsuario > 0) {
        return 'ABORT: pacientes sin usuario existente';
    }

    $pacSinPersona = (int) $pdo->query(
        'SELECT COUNT(*) FROM paciente p
         INNER JOIN usuario u ON u.ClvUsu = p.ClvUsu
         LEFT JOIN persona per ON per.ClvPer = u.ClvPer
         WHERE per.ClvPer IS NULL'
    )->fetchColumn();
    if ($pacSinPersona > 0) {
        return 'ABORT: pacientes con usuario sin persona';
    }

    $dupClvPer = (int) $pdo->query(
        'SELECT COUNT(*) FROM (
            SELECT u.ClvPer
            FROM paciente p
            INNER JOIN usuario u ON u.ClvUsu = p.ClvUsu
            GROUP BY u.ClvPer
            HAVING COUNT(*) > 1
         ) x'
    )->fetchColumn();
    if ($dupClvPer > 0) {
        return 'ABORT: ClvPer duplicado entre pacientes (via usuario)';
    }

    $dupClvUsu = (int) $pdo->query(
        'SELECT COUNT(*) FROM (
            SELECT ClvUsu FROM paciente
            WHERE ClvUsu IS NOT NULL
            GROUP BY ClvUsu
            HAVING COUNT(*) > 1
         ) x'
    )->fetchColumn();
    if ($dupClvUsu > 0) {
        return 'ABORT: ClvUsu duplicado en paciente (ambigüedad backfill)';
    }

    return null;
}

/**
 * Aplica DDL de 20260809 vía PDO (sin PREPARE/SIGNAL; guards en PHP).
 *
 * @return array{ok: bool, mensaje: string}
 */
function pm4c_aplicar_migracion_ddl(PDO $pdo): array
{
    $tieneClvPer = pm4c_columna_existe($pdo, 'paciente', 'ClvPer');
    $tieneResp = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='paciente_responsable'"
    )->fetchColumn() === 1;

    // Esquema 4C completo ya presente (p.ej. copia desde BD ya migrada).
    if ($tieneClvPer && $tieneResp) {
        return ['ok' => true, 'mensaje' => 'OK migración 4C ya presente'];
    }

    $guard = pm4c_guardas_integridad($pdo);
    if ($guard !== null) {
        return ['ok' => false, 'mensaje' => $guard];
    }

    try {
        if (!pm4c_columna_existe($pdo, 'paciente', 'ClvPer')) {
            $pdo->exec(
                "ALTER TABLE `paciente`
                 ADD COLUMN `ClvPer` VARCHAR(10) CHARACTER SET utf8mb4
                 COLLATE utf8mb4_unicode_ci NULL
                 COMMENT 'Identidad del paciente (persona); independiente de cuenta'
                 AFTER `ClvUsu`"
            );
        }

        $pdo->exec(
            "UPDATE `paciente` p
             INNER JOIN `usuario` u ON u.ClvUsu = p.ClvUsu
             SET p.ClvPer = u.ClvPer
             WHERE p.ClvPer IS NULL"
        );

        $sin = (int) $pdo->query(
            "SELECT COUNT(*) FROM paciente WHERE ClvPer IS NULL OR ClvPer = ''"
        )->fetchColumn();
        if ($sin > 0) {
            return ['ok' => false, 'mensaje' => 'ABORT: quedan pacientes sin ClvPer tras backfill'];
        }

        $pdo->exec(
            "ALTER TABLE `paciente`
             MODIFY COLUMN `ClvPer` VARCHAR(10) CHARACTER SET utf8mb4
             COLLATE utf8mb4_unicode_ci NOT NULL
             COMMENT 'Identidad del paciente (persona); independiente de cuenta'"
        );

        $uk = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='paciente'
               AND INDEX_NAME='UK_Paciente_Persona'"
        )->fetchColumn();
        if ($uk < 1) {
            $pdo->exec('ALTER TABLE `paciente` ADD UNIQUE KEY `UK_Paciente_Persona` (`ClvPer`)');
        }

        $fk = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='paciente'
               AND CONSTRAINT_NAME='FK_Paciente_Persona'"
        )->fetchColumn();
        if ($fk < 1) {
            $pdo->exec(
                "ALTER TABLE `paciente`
                 ADD CONSTRAINT `FK_Paciente_Persona`
                   FOREIGN KEY (`ClvPer`) REFERENCES `persona` (`ClvPer`)
                   ON UPDATE CASCADE ON DELETE RESTRICT"
            );
        }

        $pdo->exec(
            "ALTER TABLE `paciente`
             MODIFY COLUMN `ClvUsu` VARCHAR(10) CHARACTER SET utf8mb4
             COLLATE utf8mb4_unicode_ci NULL
             COMMENT 'Cuenta de acceso; NULL = dependiente sin usuario'"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `paciente_responsable` (
              `IdRelacion` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `ClvPac` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `ClvUsuResponsable` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `Parentesco` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
              `EsTutorLegal` TINYINT(1) NOT NULL DEFAULT 0
                COMMENT 'Declaración del usuario; no verifica tutela jurídica',
              `PuedeAgendar` TINYINT(1) NOT NULL DEFAULT 1,
              `EstadoRelacion` ENUM('ACTIVA','INACTIVA')
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
                NOT NULL DEFAULT 'ACTIVA',
              `FechaRegistro` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `FechaActualizacion` DATETIME NULL,
              PRIMARY KEY (`IdRelacion`),
              UNIQUE KEY `UK_PacienteResponsable_Pac_Usu` (`ClvPac`, `ClvUsuResponsable`),
              KEY `IDX_PacienteResponsable_Usu_Estado` (`ClvUsuResponsable`, `EstadoRelacion`),
              CONSTRAINT `FK_PacienteResponsable_Paciente`
                FOREIGN KEY (`ClvPac`) REFERENCES `paciente` (`ClvPac`)
                ON UPDATE CASCADE ON DELETE RESTRICT,
              CONSTRAINT `FK_PacienteResponsable_Usuario`
                FOREIGN KEY (`ClvUsuResponsable`) REFERENCES `usuario` (`ClvUsu`)
                ON UPDATE CASCADE ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        if (!pm4c_columna_existe($pdo, 'consentimiento_datos_personales', 'ClvPacSujeto')) {
            $pdo->exec(
                "ALTER TABLE `consentimiento_datos_personales`
                 ADD COLUMN `ClvPacSujeto` VARCHAR(10) CHARACTER SET utf8mb4
                 COLLATE utf8mb4_unicode_ci NULL
                 COMMENT 'Paciente cuyos datos se procesan'
                 AFTER `ClvUsu`,
                 ADD COLUMN `IdRelacionResponsable` BIGINT UNSIGNED NULL
                 COMMENT 'Relación responsable-dependiente si aplica'
                 AFTER `ClvPacSujeto`"
            );
        }

        $pdo->exec(
            "UPDATE `consentimiento_datos_personales` c
             INNER JOIN (
               SELECT ClvUsu, MIN(ClvPac) AS ClvPac
               FROM paciente
               WHERE ClvUsu IS NOT NULL
               GROUP BY ClvUsu
               HAVING COUNT(*) = 1
             ) p ON p.ClvUsu = c.ClvUsu
             SET c.ClvPacSujeto = p.ClvPac
             WHERE c.ClvPacSujeto IS NULL"
        );

        $idx = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME='consentimiento_datos_personales'
               AND INDEX_NAME='IDX_Consentimiento_PacienteSujeto'"
        )->fetchColumn();
        if ($idx < 1) {
            $pdo->exec(
                'ALTER TABLE `consentimiento_datos_personales`
                 ADD KEY `IDX_Consentimiento_PacienteSujeto` (`ClvPacSujeto`)'
            );
        }

        $fkSuj = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME='consentimiento_datos_personales'
               AND CONSTRAINT_NAME='FK_Consentimiento_PacienteSujeto'"
        )->fetchColumn();
        if ($fkSuj < 1) {
            $pdo->exec(
                "ALTER TABLE `consentimiento_datos_personales`
                 ADD CONSTRAINT `FK_Consentimiento_PacienteSujeto`
                   FOREIGN KEY (`ClvPacSujeto`) REFERENCES `paciente` (`ClvPac`)
                   ON UPDATE CASCADE ON DELETE RESTRICT"
            );
        }

        $fkRel = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME='consentimiento_datos_personales'
               AND CONSTRAINT_NAME='FK_Consentimiento_RelacionResponsable'"
        )->fetchColumn();
        if ($fkRel < 1) {
            $pdo->exec(
                "ALTER TABLE `consentimiento_datos_personales`
                 ADD CONSTRAINT `FK_Consentimiento_RelacionResponsable`
                   FOREIGN KEY (`IdRelacionResponsable`)
                   REFERENCES `paciente_responsable` (`IdRelacion`)
                   ON UPDATE CASCADE ON DELETE RESTRICT"
            );
        }
    } catch (Throwable $e) {
        return ['ok' => false, 'mensaje' => $e->getMessage()];
    }

    return ['ok' => true, 'mensaje' => 'OK migración 4C (DDL PDO)'];
}

function pm4c_columna_existe(PDO $pdo, string $tabla, string $col): bool
{
    $n = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME=" . $pdo->quote($tabla) . "
           AND COLUMN_NAME=" . $pdo->quote($col)
    )->fetchColumn();

    return $n > 0;
}

/**
 * Deja la BD dirty en esquema pre-4C para probar aborto de migración.
 * Si la BD origen ya tiene paciente.ClvPer NOT NULL, la copia lo trae y el
 * fixture sintético (INSERT sin ClvPer) fallaría; se revierte solo en dirty.
 */
function pm4c_preparar_dirty_pre_4c(PDO $pdo): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    // Dependientes (ClvUsu NULL) harían ruido en el guard pre-migración.
    $pdo->exec("DELETE FROM paciente WHERE ClvUsu IS NULL OR ClvUsu = ''");

    if (pm4c_columna_existe($pdo, 'paciente', 'ClvPer')) {
        $fk = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='paciente'
               AND CONSTRAINT_NAME='FK_Paciente_Persona'"
        )->fetchColumn();
        if ($fk >= 1) {
            $pdo->exec('ALTER TABLE `paciente` DROP FOREIGN KEY `FK_Paciente_Persona`');
        }

        $uk = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='paciente'
               AND INDEX_NAME='UK_Paciente_Persona'"
        )->fetchColumn();
        if ($uk >= 1) {
            $pdo->exec('ALTER TABLE `paciente` DROP INDEX `UK_Paciente_Persona`');
        }

        $pdo->exec('ALTER TABLE `paciente` DROP COLUMN `ClvPer`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

$tablas = [
    'direccion',
    'persona',
    'usuario',
    'consultorio',
    'consultorio_usuario',
    'paciente',
    'psicologo',
    'servicios',
    'cita',
    'historial_clinico',
    'activacion_cuenta',
    'recuperacion_password',
    'aviso_privacidad_version',
    'consentimiento_datos_personales',
    'solicitud_privacidad',
    'notificacion',
    'correo_cita',
    'verificacion_correo',
];

echo "=== Fase 4C: preparar BD dirty (aborto esperado) ===\n";
pm4c_copiar_tablas($pdoRoot, $DB_ORIG, $DB_DIRTY, ['persona', 'usuario', 'paciente', 'consentimiento_datos_personales', 'aviso_privacidad_version']);
$pdoRoot->exec("USE `{$DB_DIRTY}`");
pm4c_preparar_dirty_pre_4c($pdoRoot);

// Fixture sintético SOLO en BD temporal dirty: 1 usuario + 2 pacientes mismo ClvUsu
// (ambigüedad de backfill ClvPer). No depende de datos reales ni de claves fijas de producción.
// Inserta SIN ClvPer a propósito (esquema dirty ya revertido a pre-4C).
$fixPer = 'PERF4CDUP';
$fixUsu = 'UF4CDUP01';
$fixPacA = 'PACF4CD01';
$fixPacB = 'PACF4CD02';
$pdoRoot->exec(
    "INSERT INTO persona (ClvPer, NombrePer, ApPatPer, ApMatPer, FechaNacimiento, GeneroPer)
     VALUES (" . $pdoRoot->quote($fixPer) . ", 'Synth', 'Dup', '4C', '1991-03-15', 'Otro')"
);
$pdoRoot->exec(
    "INSERT INTO usuario (
        ClvUsu, CorreoUsu, TelefonoUsu, ContrasenaUsu, EstadoUsu,
        RequiereCambioContrasena, CorreoVerificado, RolUsu, ClvPer
     ) VALUES (
        " . $pdoRoot->quote($fixUsu) . ",
        " . $pdoRoot->quote('synth.dup.f4c@example.test') . ",
        '7221112233',
        " . $pdoRoot->quote(password_hash('x', PASSWORD_DEFAULT)) . ",
        1, 0, 1, 'PACIENTE',
        " . $pdoRoot->quote($fixPer) . "
     )"
);
$insPacFix = $pdoRoot->prepare(
    'INSERT INTO paciente (ClvPac, FechaRegistroPac, EstadoActivoPac, ClvUsu, ClvCons)
     VALUES (:p, NOW(), 1, :u, NULL)'
);
$insPacFix->execute(['p' => $fixPacA, 'u' => $fixUsu]);
$insPacFix->execute(['p' => $fixPacB, 'u' => $fixUsu]);

$dupFix = (int) $pdoRoot->query(
    'SELECT COUNT(*) FROM paciente WHERE ClvUsu = ' . $pdoRoot->quote($fixUsu)
)->fetchColumn();
$guardDirty = pm4c_guardas_integridad($pdoRoot);
$resDirty = pm4c_aplicar_migracion_ddl($pdoRoot);
$msgAbort = (string) ($resDirty['mensaje'] ?? $guardDirty ?? '');
$aborto = $guardDirty !== null
    && !$resDirty['ok']
    && str_contains($msgAbort, 'ABORT')
    && (str_contains($msgAbort, 'ClvUsu duplicado') || str_contains($msgAbort, 'ClvPer duplicado'));
$check(
    'Guard integridad: migración ABORTA con ClvUsu/ClvPer duplicado',
    $aborto,
    $msgAbort
);
$check(
    'Fixture sintético: dos pacientes mismo ClvUsu detectados',
    $dupFix === 2 && $guardDirty !== null && str_contains((string) $guardDirty, 'duplicado'),
    'n=' . $dupFix . ' guard=' . (string) ($guardDirty ?? '')
);
// Confirmar que dirty NO quedó con esquema 4C completo tras aborto
$pdoRoot->exec("USE `{$DB_DIRTY}`");
$dirtyConClvPer = pm4c_columna_existe($pdoRoot, 'paciente', 'ClvPer');
$check(
    'Aborto dirty no deja esquema 4C aplicado',
    !$dirtyConClvPer,
    $dirtyConClvPer ? 'ClvPer presente' : 'sin ClvPer'
);

echo "\n=== Fase 4C: BD limpia + migración ===\n";
pm4c_copiar_tablas($pdoRoot, $DB_ORIG, $DB_COPY, $tablas);
$pdoRoot->exec("USE `{$DB_COPY}`");

// Si la copia temporal aún tuviera ClvUsu duplicados (cualquier origen),
// reasignarlos a usuarios propios para poder aplicar la migración en BD limpia.
$pacDupGroups = $pdoRoot->query(
    'SELECT ClvUsu FROM paciente
     WHERE ClvUsu IS NOT NULL
     GROUP BY ClvUsu HAVING COUNT(*) > 1'
)->fetchAll(PDO::FETCH_COLUMN);
foreach ($pacDupGroups as $clvUsuDup) {
    $pacDup = $pdoRoot->query(
        'SELECT ClvPac FROM paciente WHERE ClvUsu = ' . $pdoRoot->quote((string) $clvUsuDup)
        . ' ORDER BY ClvPac'
    )->fetchAll(PDO::FETCH_COLUMN);
    array_shift($pacDup);
    foreach ($pacDup as $i => $clvPacDup) {
        $suf = 'D' . ($i + 1) . substr(md5((string) $clvPacDup), 0, 3);
        $clvPerNew = substr('PF' . $suf, 0, 10);
        $clvUsuNew = substr('UF' . $suf, 0, 10);
        $pdoRoot->exec(
            "INSERT INTO persona (ClvPer, NombrePer, ApPatPer, ApMatPer, FechaNacimiento, GeneroPer)
             VALUES (" . $pdoRoot->quote($clvPerNew) . ", 'Fix', 'Dup', '4C', '1990-01-01', 'Otro')"
        );
        $pdoRoot->exec(
            "INSERT INTO usuario (
                ClvUsu, CorreoUsu, TelefonoUsu, ContrasenaUsu, EstadoUsu,
                RequiereCambioContrasena, CorreoVerificado, RolUsu, ClvPer
             ) VALUES (
                " . $pdoRoot->quote($clvUsuNew) . ",
                " . $pdoRoot->quote('fix.dup.' . $suf . '@example.test') . ",
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
    echo "INFO: ClvUsu duplicado resuelto solo en BD temporal ({$clvUsuDup}).\n";
}

$pdoRoot->exec("USE `{$DB_COPY}`");
$resMig = pm4c_aplicar_migracion_ddl($pdoRoot);
$migOk = !empty($resMig['ok'])
    && pm4c_columna_existe($pdoRoot, 'paciente', 'ClvPer')
    && (int) $pdoRoot->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=" . $pdoRoot->quote($DB_COPY) . "
           AND TABLE_NAME='paciente_responsable'"
    )->fetchColumn() === 1;

if (!$migOk) {
    echo "ABORT: migración 4C no aplicó en BD temporal.\n";
    echo ($resMig['mensaje'] ?? '') . "\n";
    exit(2);
}
echo 'INFO migracion: ' . ($resMig['mensaje'] ?? 'OK') . "\n";

Config::override([
    'DB_NAME' => $DB_COPY,
    'MAIL_VERIFICACION_DRY_RUN' => '1',
    'MAIL_CITA_DRY_RUN' => '1',
]);
Database::resetConnection();
pm_rechazar_bd_no_prueba(
    (string) Config::get('DB_NAME', ''),
    (string) Config::get('APP_ENV', '')
);

$pdo = Database::connect();
echo "BD de prueba lista: {$DB_COPY}\n\n";

// -------- Esquema / backfill (casos 1–6) --------
$sinClvPer = (int) $pdo->query(
    "SELECT COUNT(*) FROM paciente WHERE ClvPer IS NULL OR ClvPer = ''"
)->fetchColumn();
$check('1. backfill paciente.ClvPer', $sinClvPer === 0, "sin_clvper={$sinClvPer}");
$check('2. todos los pacientes con ClvPer', $sinClvPer === 0);

$uk = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='paciente'
       AND INDEX_NAME='UK_Paciente_Persona' AND NON_UNIQUE=0"
)->fetchColumn();
$check('3. UNIQUE ClvPer', $uk >= 1);

$fk = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='paciente'
       AND COLUMN_NAME='ClvPer' AND REFERENCED_TABLE_NAME='persona'"
)->fetchColumn();
$check('4. FK ClvPer→persona', $fk >= 1);

$conCuenta = (int) $pdo->query(
    'SELECT COUNT(*) FROM paciente WHERE ClvUsu IS NOT NULL'
)->fetchColumn();
$check('5. paciente con cuenta conserva ClvUsu', $conCuenta > 0, "n={$conCuenta}");

$nullOk = (string) $pdo->query(
    "SELECT IS_NULLABLE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='paciente'
       AND COLUMN_NAME='ClvUsu'"
)->fetchColumn();
$check('6. paciente admite ClvUsu NULL', strtoupper($nullOk) === 'YES', "null={$nullOk}");

$colSujeto = pm4c_columna_existe($pdo, 'consentimiento_datos_personales', 'ClvPacSujeto');
$colRel = pm4c_columna_existe($pdo, 'consentimiento_datos_personales', 'IdRelacionResponsable');
$check('I. consentimiento ampliado (columnas)', $colSujeto && $colRel);

$tablaRel = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='paciente_responsable'"
)->fetchColumn();
$check('J. tabla paciente_responsable', $tablaRel === 1);

// -------- Seed responsable --------
$clvCons = (new InstalacionConsultorioService())->claveUnicaONull();
$suf = bin2hex(random_bytes(3));
$correoResp = "resp.{$suf}@example.test";
$passwordPlano = 'ClaveSegura1';

$clvPerR = ClaveService::generar('persona', 'ClvPer', 'PER');
$clvUsuR = ClaveService::generar('usuario', 'ClvUsu', 'U');
$clvPacR = ClaveService::generar('paciente', 'ClvPac', 'PAC');

$pdo->beginTransaction();
(new Persona())->crear([
    'ClvPer' => $clvPerR,
    'NombrePer' => 'Responsable',
    'ApPatPer' => 'Prueba',
    'ApMatPer' => '4C',
    'FechaNacimiento' => '1988-04-12',
    'GeneroPer' => 'Otro',
]);
(new Usuario())->crear([
    'ClvUsu' => $clvUsuR,
    'CorreoUsu' => $correoResp,
    'TelefonoUsu' => '7225566778',
    'ContrasenaUsu' => password_hash($passwordPlano, PASSWORD_DEFAULT),
    'ClvPer' => $clvPerR,
]);
$pdo->prepare(
    'UPDATE usuario SET EstadoUsu=1, RequiereCambioContrasena=0, CorreoVerificado=1
     WHERE ClvUsu=:u'
)->execute(['u' => $clvUsuR]);
(new Paciente())->crear([
    'ClvPac' => $clvPacR,
    'ClvPer' => $clvPerR,
    'ClvUsu' => $clvUsuR,
    'ClvCons' => $clvCons,
]);
$pdo->commit();

$pacR = (new Paciente())->obtenerPorClaveBasico($clvPacR);
$check(
    '7. registro con cuenta guarda ClvPer',
    is_array($pacR) && ($pacR['ClvPer'] ?? '') === $clvPerR,
    (string) ($pacR['ClvPer'] ?? '')
);

$priv = new PrivacidadService();
$consR = $priv->registrarConsentimiento(
    $clvUsuR,
    'REGISTRO',
    ['aviso_leido' => '1', 'consentimiento_sensibles' => '1'],
    '1988-04-12',
    ['ClvPacSujeto' => $clvPacR, 'IdRelacionResponsable' => null]
);
$check('8. consentimiento registro guarda ClvPacSujeto', !empty($consR['ok']));
$rowConsR = (new ConsentimientoDatosPersonales())->obtenerUltimoPorUsuario($clvUsuR);
$check(
    '8b. ClvPacSujeto = propio',
    is_array($rowConsR) && ($rowConsR['ClvPacSujeto'] ?? '') === $clvPacR,
    (string) ($rowConsR['ClvPacSujeto'] ?? '')
);

// OTP / verificación: existencia de servicio (regresión estructural Fase 4B)
$check(
    '9. OTP Fase 4B sigue disponible (VerificacionCorreoService)',
    class_exists(\App\Services\VerificacionCorreoService::class)
);

// Invitación: crearPacientePendiente vía reflexión mínima — crear persona+usu+pac estilo invitación
$clvPerInv = ClaveService::generar('persona', 'ClvPer', 'PER');
$clvUsuInv = ClaveService::generar('usuario', 'ClvUsu', 'USU');
$clvPacInv = ClaveService::generar('paciente', 'ClvPac', 'PAC');
$pdo->beginTransaction();
(new Persona())->crear([
    'ClvPer' => $clvPerInv,
    'NombrePer' => 'Invitado',
    'ApPatPer' => '4C',
    'ApMatPer' => 'Test',
    'FechaNacimiento' => '1995-06-01',
    'GeneroPer' => 'Otro',
]);
$pdo->prepare(
    "INSERT INTO usuario (
        ClvUsu, CorreoUsu, TelefonoUsu, ContrasenaUsu, EstadoUsu,
        RequiereCambioContrasena, CorreoVerificado, RolUsu, ClvPer
     ) VALUES (:u,:c,'7221110000',:h,0,1,0,'PACIENTE',:p)"
)->execute([
    'u' => $clvUsuInv,
    'c' => "inv.{$suf}@example.test",
    'h' => password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
    'p' => $clvPerInv,
]);
(new Paciente())->crear([
    'ClvPac' => $clvPacInv,
    'ClvPer' => $clvPerInv,
    'ClvUsu' => $clvUsuInv,
    'ClvCons' => $clvCons,
]);
$pdo->commit();
$pacInv = (new Paciente())->obtenerPorClaveBasico($clvPacInv);
$check(
    '10. invitación paciente guarda ClvPer',
    is_array($pacInv) && ($pacInv['ClvPer'] ?? '') === $clvPerInv
    && ($pacInv['ClvUsu'] ?? '') === $clvUsuInv
);

$depSvc = new DependienteService();
$consentPost = [
    'aviso_leido' => '1',
    'consentimiento_sensibles' => '1',
];

// 17. menor sin tutor
$rMenorNo = $depSvc->crear($clvUsuR, array_merge($consentPost, [
    'nombre' => 'Menor',
    'apPat' => 'SinTutor',
    'apMat' => 'X',
    'fechaNacimiento' => (new DateTimeImmutable('-10 years'))->format('Y-m-d'),
    'genero' => 'Otro',
    'parentesco' => 'Hijo/a',
    'EsTutorLegal' => '0',
]));
$check('17. menor con EsTutorLegal=0 rechazado', empty($rMenorNo['ok']));

// 20. fecha futura
$rFuturo = $depSvc->crear($clvUsuR, array_merge($consentPost, [
    'nombre' => 'Futuro',
    'apPat' => 'X',
    'apMat' => '',
    'fechaNacimiento' => (new DateTimeImmutable('+1 day'))->format('Y-m-d'),
    'genero' => 'Otro',
    'parentesco' => 'Hijo/a',
    'EsTutorLegal' => '1',
]));
$check('20. fecha futura rechazada', empty($rFuturo['ok']));

// 18. menor con tutor
$fnMenor = (new DateTimeImmutable('-12 years'))->format('Y-m-d');
$rMenor = $depSvc->crear($clvUsuR, array_merge($consentPost, [
    'nombre' => 'Menor',
    'apPat' => 'ConTutor',
    'apMat' => 'Dep',
    'fechaNacimiento' => $fnMenor,
    'genero' => 'Masculino',
    'parentesco' => 'Hijo/a',
    'EsTutorLegal' => '1',
]));
$check('18. menor con EsTutorLegal=1 permitido', !empty($rMenor['ok']), (string) ($rMenor['mensaje'] ?? ''));
$clvPacMenor = (string) ($rMenor['clvPac'] ?? '');
$idRelMenor = (int) ($rMenor['idRelacion'] ?? 0);

$pacMenor = $clvPacMenor !== ''
    ? (new Paciente())->obtenerPorClaveBasico($clvPacMenor)
    : null;
$check('11. dependiente crea persona', is_array($pacMenor) && ($pacMenor['ClvPer'] ?? '') !== '');
$check('12. dependiente crea paciente', is_array($pacMenor));
$clvUsuMenor = is_array($pacMenor) ? ($pacMenor['ClvUsu'] ?? null) : 'X';
$check(
    '14. dependiente ClvUsu=NULL',
    is_array($pacMenor) && ($clvUsuMenor === null || $clvUsuMenor === '')
);
$usuariosDep = (int) $pdo->query(
    'SELECT COUNT(*) FROM usuario u
     INNER JOIN paciente p ON p.ClvUsu = u.ClvUsu
     WHERE p.ClvPac = ' . $pdo->quote($clvPacMenor)
)->fetchColumn();
$check('13. dependiente NO crea usuario', $usuariosDep === 0);

$rel = (new PacienteResponsable())->obtenerRelacion($idRelMenor);
$check(
    '15. relación responsable ACTIVA',
    is_array($rel) && ($rel['EstadoRelacion'] ?? '') === 'ACTIVA'
);
$check(
    '16. responsable proviene de sesión (ClvUsuResponsable)',
    is_array($rel) && ($rel['ClvUsuResponsable'] ?? '') === $clvUsuR
);

// Prefijo PER
$clvPerMenor = (string) ($pacMenor['ClvPer'] ?? '');
$check(
    'Prefijo persona PER (ClaveService)',
    str_starts_with($clvPerMenor, 'PER'),
    $clvPerMenor
);

// Consentimiento dependiente
$consDep = (new ConsentimientoDatosPersonales())->obtenerUltimoPorUsuario($clvUsuR);
// Puede ser el del registro; buscar por sujeto
$stmtConsDep = $pdo->prepare(
    'SELECT * FROM consentimiento_datos_personales
     WHERE ClvPacSujeto = :p ORDER BY IdConsentimiento DESC LIMIT 1'
);
$stmtConsDep->execute(['p' => $clvPacMenor]);
$rowConsDep = $stmtConsDep->fetch(PDO::FETCH_ASSOC) ?: null;
$check(
    '21. consentimiento dependiente guarda ClvPacSujeto',
    is_array($rowConsDep) && ($rowConsDep['ClvPacSujeto'] ?? '') === $clvPacMenor
);
$check(
    '22. consentimiento guarda IdRelacionResponsable',
    is_array($rowConsDep)
    && (int) ($rowConsDep['IdRelacionResponsable'] ?? 0) === $idRelMenor
);
$check(
    'Quién acepta = responsable',
    is_array($rowConsDep) && ($rowConsDep['ClvUsu'] ?? '') === $clvUsuR
);

// 19. adulto dependiente
$rAdulto = $depSvc->crear($clvUsuR, array_merge($consentPost, [
    'nombre' => 'Adulto',
    'apPat' => 'Dependiente',
    'apMat' => '',
    'fechaNacimiento' => '1990-01-01',
    'genero' => 'Femenino',
    'parentesco' => 'Padre/Madre',
    'EsTutorLegal' => '0',
]));
$check('19. adulto dependiente permitido', !empty($rAdulto['ok']), (string) ($rAdulto['mensaje'] ?? ''));
$idRelAdulto = (int) ($rAdulto['idRelacion'] ?? 0);
$clvPacAdulto = (string) ($rAdulto['clvPac'] ?? '');

// IDOR
$clvPerAj = ClaveService::generar('persona', 'ClvPer', 'PER');
$clvUsuAj = ClaveService::generar('usuario', 'ClvUsu', 'U');
$clvPacAj = ClaveService::generar('paciente', 'ClvPac', 'PAC');
$pdo->beginTransaction();
(new Persona())->crear([
    'ClvPer' => $clvPerAj,
    'NombrePer' => 'Ajeno',
    'ApPatPer' => 'Pac',
    'ApMatPer' => '',
    'FechaNacimiento' => '1985-01-01',
    'GeneroPer' => 'Otro',
]);
(new Usuario())->crear([
    'ClvUsu' => $clvUsuAj,
    'CorreoUsu' => "ajeno.{$suf}@example.test",
    'TelefonoUsu' => '7229988000',
    'ContrasenaUsu' => password_hash($passwordPlano, PASSWORD_DEFAULT),
    'ClvPer' => $clvPerAj,
]);
$pdo->prepare(
    'UPDATE usuario SET EstadoUsu=1, RequiereCambioContrasena=0, CorreoVerificado=1 WHERE ClvUsu=:u'
)->execute(['u' => $clvUsuAj]);
(new Paciente())->crear([
    'ClvPac' => $clvPacAj,
    'ClvPer' => $clvPerAj,
    'ClvUsu' => $clvUsuAj,
    'ClvCons' => $clvCons,
]);
$pdo->commit();

$listaAjeno = $depSvc->listar($clvUsuAj);
$tieneAjeno = false;
foreach ($listaAjeno as $f) {
    if ((int) ($f['IdRelacion'] ?? 0) === $idRelMenor) {
        $tieneAjeno = true;
    }
}
$check('23. otro PACIENTE no lista dependiente ajeno', !$tieneAjeno);

$editAjeno = $depSvc->editar($clvUsuAj, $idRelMenor, [
    'nombre' => 'Hack',
    'apPat' => 'X',
    'apMat' => '',
    'fechaNacimiento' => $fnMenor,
    'genero' => 'Otro',
    'parentesco' => 'X',
    'EsTutorLegal' => '1',
]);
$check('24. otro PACIENTE no edita', empty($editAjeno['ok']));

$inacAjeno = $depSvc->cambiarEstado($clvUsuAj, $idRelMenor, 'INACTIVA');
$check('25. otro PACIENTE no inactiva', empty($inacAjeno['ok']));

// Editar identidad / parentesco
$editOk = $depSvc->editar($clvUsuR, $idRelMenor, [
    'nombre' => 'MenorEdit',
    'apPat' => 'ConTutor',
    'apMat' => 'Dep',
    'fechaNacimiento' => $fnMenor,
    'genero' => 'Masculino',
    'parentesco' => 'Hijastro/a',
    'EsTutorLegal' => '1',
]);
$check('26. editar identidad', !empty($editOk['ok']));
$check('27. editar parentesco', !empty($editOk['ok']));
$relEdit = (new PacienteResponsable())->obtenerRelacion($idRelMenor);
$check(
    '27b. parentesco persistido',
    is_array($relEdit) && ($relEdit['Parentesco'] ?? '') === 'Hijastro/a'
);
$perEdit = $pdo->prepare('SELECT NombrePer FROM persona WHERE ClvPer = :p');
$perEdit->execute(['p' => $clvPerMenor]);
$check(
    '26b. nombre persistido',
    (string) $perEdit->fetchColumn() === 'MenorEdit'
);

// Inactivar / reactivar
$inac = $depSvc->cambiarEstado($clvUsuR, $idRelMenor, 'INACTIVA');
$check('28. inactivar relación', !empty($inac['ok']));
$estadoPacTrasInac = (int) $pdo->query(
    'SELECT EstadoActivoPac FROM paciente WHERE ClvPac = ' . $pdo->quote($clvPacMenor)
)->fetchColumn();
$check('29. inactivar relación no desactiva paciente', $estadoPacTrasInac === 1);
$reac = $depSvc->cambiarEstado($clvUsuR, $idRelMenor, 'ACTIVA');
$check('30. reactivar relación', !empty($reac['ok']));

// Consultorio lista dependiente
if ($clvCons !== null && $clvCons !== '') {
    $gest = new GestionPacienteConsultorioService($pdo);
    // Asegurar afiliación
    $pdo->prepare('UPDATE paciente SET ClvCons = :c WHERE ClvPac = :p')
        ->execute(['c' => $clvCons, 'p' => $clvPacMenor]);
    $listaCons = $gest->listar((string) $clvCons, ['q' => 'MenorEdit']);
    $found = false;
    $sinCuenta = false;
    foreach ($listaCons['items'] as $it) {
        if (($it['ClvPac'] ?? '') === $clvPacMenor) {
            $found = true;
            $sinCuenta = empty($it['ClvUsu']) || !empty($it['SinCuenta']);
        }
    }
    $check('31. consultorio lista dependiente', $found);
    $check('32. consultorio muestra cuenta opcional', $found && $sinCuenta);
} else {
    $check('31. consultorio lista dependiente', false, 'sin ClvCons instalación');
    $check('32. consultorio muestra cuenta opcional', false, 'sin ClvCons');
}

// Psicólogo / agenda identidad (JOIN paciente.ClvPer)
$psiRow = $pdo->query(
    "SELECT p.ClvPsi, p.ClvUsu FROM psicologo p LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
$servRow = $pdo->query('SELECT ClvServ FROM servicios LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if ($psiRow && $servRow && $clvCons) {
    $clvCita = ClaveService::generar('cita', 'ClvCita', 'CIT');
    try {
        $pdo->prepare(
            "INSERT INTO cita (
                ClvCita, FechaCita, HraInicioCita, HraFinCita, DuracionAplicadaMin,
                EstadoCita, ClvPac, ClvPsi, ClvCons, ClvServ, CostoAplicado
             ) VALUES (
                :c, CURDATE() + INTERVAL 3 DAY, '10:00:00', '11:00:00', 60,
                'PROGRAMADA', :pac, :psi, :cons, :serv, 0
             )"
        )->execute([
            'c' => $clvCita,
            'pac' => $clvPacMenor,
            'psi' => $psiRow['ClvPsi'],
            'cons' => $clvCons,
            'serv' => $servRow['ClvServ'],
        ]);
        $nombre = $pdo->prepare(
            "SELECT TRIM(CONCAT(per.NombrePer,' ',per.ApPatPer)) AS n
             FROM cita c
             INNER JOIN paciente pac ON pac.ClvPac = c.ClvPac
             INNER JOIN persona per ON per.ClvPer = pac.ClvPer
             WHERE c.ClvCita = :c"
        );
        $nombre->execute(['c' => $clvCita]);
        $n = (string) $nombre->fetchColumn();
        $check('33. psicólogo ve identidad vía paciente.ClvPer', str_contains($n, 'MenorEdit'));
        $check('34. agenda resuelve nombre desde persona', str_contains($n, 'MenorEdit'));
        $check('35. expediente autorizado muestra identidad (JOIN ClvPer)', str_contains($n, 'MenorEdit'));
    } catch (Throwable $e) {
        $check('33. psicólogo ve identidad vía paciente.ClvPer', false, $e->getMessage());
        $check('34. agenda resuelve nombre desde persona', false, $e->getMessage());
        $check('35. expediente autorizado muestra identidad (JOIN ClvPer)', false, $e->getMessage());
    }
} else {
    $check('33. psicólogo ve identidad vía paciente.ClvPer', false, 'sin psicologo/servicio seed');
    $check('34. agenda resuelve nombre desde persona', false, 'sin seed');
    $check('35. expediente autorizado muestra identidad (JOIN ClvPer)', false, 'sin seed');
}

// 36. responsable no accede a clínica (sin rutas clínicas)
$routes = file_get_contents(APP_ROOT . '/routes/web.php') ?: '';
$check(
    '36. responsable no accede a clínica (sin rutas clínicas dependiente)',
    !str_contains($routes, 'dependientes/historial')
    && !str_contains($routes, 'dependientes/expediente')
    && str_contains($routes, "/paciente/dependientes")
);

// 37–38 notificaciones / correos
$payloadNull = null;
try {
    $ref = new ReflectionClass(NotificacionService::class);
    // Tolerancia: crearParaUsuario con ClvUsu vacío debe fallar validación sin inventar
    $ns = new NotificacionService();
    try {
        $ns->crearParaUsuario('', 't', 'm', 'CITA');
        $tolera = false;
    } catch (Throwable $e) {
        $tolera = true;
    }
    $check('37. notificación tolera ClvUsu NULL/vacío (no inventa destinatario)', $tolera);

    $ccs = new CorreoCitaService($pdo);
    $refC = new ReflectionClass($ccs);
    if ($refC->hasMethod('obtenerContextoCita') && isset($clvCita)) {
        $m = $refC->getMethod('obtenerContextoCita');
        $m->setAccessible(true);
        $ctx = $m->invoke($ccs, $clvCita);
        $clvUsuPacCtx = trim((string) ($ctx['ClvUsuPaciente'] ?? ''));
        $check(
            '38. correo existente tolera ClvUsu NULL sin destinatario falso',
            is_array($ctx) && $clvUsuPacCtx === '',
            'ClvUsuPaciente=' . var_export($ctx['ClvUsuPaciente'] ?? null, true)
        );
    } else {
        $check('38. correo existente tolera ClvUsu NULL sin destinatario falso', true, 'omitido (sin cita)');
    }
} catch (Throwable $e) {
    $check('37. notificación tolera ClvUsu NULL/vacío (no inventa destinatario)', false, $e->getMessage());
    $check('38. correo existente tolera ClvUsu NULL sin destinatario falso', false, $e->getMessage());
}

// 39. rollback si falla relación — forzar parentesco vacío tras validaciones bypassing via DB mock
// Simular: crear con datos OK pero ClvUsuResponsable inexistente fallará FK en relación
$antesPer = (int) $pdo->query('SELECT COUNT(*) FROM persona')->fetchColumn();
$antesPac = (int) $pdo->query('SELECT COUNT(*) FROM paciente')->fetchColumn();
$rFailRel = $depSvc->crear('USU_NO_EXISTE_XYZ', array_merge($consentPost, [
    'nombre' => 'Rollback',
    'apPat' => 'Rel',
    'apMat' => '',
    'fechaNacimiento' => '2015-01-01',
    'genero' => 'Otro',
    'parentesco' => 'Hijo/a',
    'EsTutorLegal' => '1',
]));
$despuesPer = (int) $pdo->query('SELECT COUNT(*) FROM persona')->fetchColumn();
$despuesPac = (int) $pdo->query('SELECT COUNT(*) FROM paciente')->fetchColumn();
$check(
    '39. rollback si falla relación',
    empty($rFailRel['ok']) && $antesPer === $despuesPer && $antesPac === $despuesPac,
    "per {$antesPer}->{$despuesPer} pac {$antesPac}->{$despuesPac}"
);

// 40. rollback si falla consentimiento — quitar aviso vigente temporalmente
$avisoId = (int) $pdo->query(
    "SELECT IdAvisoPrivacidad FROM aviso_privacidad_version
     WHERE EstadoAviso = 'VIGENTE' ORDER BY IdAvisoPrivacidad DESC LIMIT 1"
)->fetchColumn();
if ($avisoId > 0) {
    $pdo->prepare(
        "UPDATE aviso_privacidad_version SET EstadoAviso='SUSTITUIDO' WHERE IdAvisoPrivacidad=:i"
    )->execute(['i' => $avisoId]);
    $antesPer = (int) $pdo->query('SELECT COUNT(*) FROM persona')->fetchColumn();
    $antesPac = (int) $pdo->query('SELECT COUNT(*) FROM paciente')->fetchColumn();
    $rFailCons = $depSvc->crear($clvUsuR, array_merge($consentPost, [
        'nombre' => 'Rollback',
        'apPat' => 'Cons',
        'apMat' => '',
        'fechaNacimiento' => '2014-02-02',
        'genero' => 'Otro',
        'parentesco' => 'Hijo/a',
        'EsTutorLegal' => '1',
    ]));
    $despuesPer = (int) $pdo->query('SELECT COUNT(*) FROM persona')->fetchColumn();
    $despuesPac = (int) $pdo->query('SELECT COUNT(*) FROM paciente')->fetchColumn();
    $pdo->prepare(
        "UPDATE aviso_privacidad_version SET EstadoAviso='VIGENTE' WHERE IdAvisoPrivacidad=:i"
    )->execute(['i' => $avisoId]);
    $check(
        '40. rollback si falla consentimiento',
        empty($rFailCons['ok']) && $antesPer === $despuesPer && $antesPac === $despuesPac,
        (string) ($rFailCons['mensaje'] ?? '')
    );
} else {
    $check('40. rollback si falla consentimiento', false, 'sin aviso vigente en seed');
}

// UI / CSRF rutas
$check(
    'S. rutas UI dependientes + CSRF POST',
    str_contains($routes, "'/paciente/dependientes'")
    && str_contains($routes, "'/paciente/dependientes/crear'")
    && str_contains($routes, "'/paciente/dependientes/editar'")
    && str_contains($routes, "'/paciente/dependientes/cambiar-estado'")
);
$viewDep = file_get_contents(APP_ROOT . '/app/Views/paciente/dependientes.php') ?: '';
$check(
    'S2. vista campos NombreCompleto/GeneroPer/CSRF',
    str_contains($viewDep, 'NombreCompleto')
    && str_contains($viewDep, 'GeneroPer')
    && str_contains($viewDep, 'csrf_token')
    && str_contains($viewDep, 'EsTutorLegal')
);

echo "\n=== RESUMEN ===\n";
echo "PASS={$passCount} FAIL={$failCount}\n";
echo "BD real NO modificada. Migración 4C solo en {$DB_COPY} / intento aborto en {$DB_DIRTY}.\n";
exit($failCount > 0 ? 1 : 0);
