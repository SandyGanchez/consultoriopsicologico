<?php

/**
 * 1) Respaldo de consultorio_psicologico
 * 2) Validación en BD de prueba
 * 3) Aplicación en consultorio_psicologico local
 *
 * No toca Hostinger. No envía correos.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Solo CLI\n");
    exit(1);
}

define('APP_ROOT', dirname(__DIR__, 2));

$mysql = 'C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysql.exe';
$mysqldump = 'C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysqldump.exe';

if (!is_file($mysql) || !is_file($mysqldump)) {
    fwrite(STDERR, "ABORT: mysql/mysqldump no encontrados\n");
    exit(2);
}

$sqlFile = APP_ROOT . '/database/migrations/proposed/2026_08_03_incidencia_soporte_propuesta.sql';
if (!is_file($sqlFile)) {
    fwrite(STDERR, "ABORT: SQL propuesto no encontrado\n");
    exit(3);
}

$backupDir = APP_ROOT . '/database/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0775, true);
}

$stamp = date('Ymd_His');
$backupFile = $backupDir . "/consultorio_psicologico_pre_incidencia_soporte_{$stamp}.sql";

echo "=== RESPALDO ===\n";
$cmdDump = sprintf(
    '"%s" -uroot --single-transaction --routines --triggers consultorio_psicologico > "%s"',
    $mysqldump,
    $backupFile
);
passthru($cmdDump, $codeDump);
if ($codeDump !== 0 || !is_file($backupFile) || filesize($backupFile) < 1024) {
    fwrite(STDERR, "ABORT: respaldo vacío o fallido\n");
    exit(4);
}
echo 'BACKUP=' . $backupFile . ' SIZE=' . filesize($backupFile) . "\n";

$testDb = 'consultorio_psicologico_incidencias_prueba';
echo "=== PRUEBA EN {$testDb} ===\n";

$pdoRoot = new PDO(
    'mysql:host=localhost;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdoRoot->exec("DROP DATABASE IF EXISTS `{$testDb}`");
$pdoRoot->exec(
    "CREATE DATABASE `{$testDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
);

$cmdRestore = sprintf(
    '"%s" -uroot %s < "%s"',
    $mysql,
    escapeshellarg($testDb),
    $backupFile
);
passthru($cmdRestore, $codeRestore);
if ($codeRestore !== 0) {
    fwrite(STDERR, "ABORT: restore prueba falló\n");
    exit(5);
}

$pdoTest = new PDO(
    "mysql:host=localhost;dbname={$testDb};charset=utf8mb4",
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$sql = file_get_contents($sqlFile);
$pdoTest->exec($sql);

$create = $pdoTest->query('SHOW CREATE TABLE incidencia_soporte')
    ->fetch(PDO::FETCH_NUM)[1];
echo "CREATE_OK=" . (str_contains($create, 'AUTO_INCREMENT') ? '1' : '0') . "\n";
echo "FK_OK=" . (substr_count($create, 'FOREIGN KEY') >= 3 ? '1' : '0') . "\n";

$clvCons = (string) $pdoTest->query(
    'SELECT ClvCons FROM consultorio LIMIT 1'
)->fetchColumn();
if ($clvCons === '') {
    fwrite(STDERR, "ABORT: sin consultorio en prueba\n");
    exit(6);
}

$pdoTest->prepare(
    "INSERT INTO incidencia_soporte
        (ClvCons, ClvUsuSolicitante, CorreoReportado, TipoIncidencia, Descripcion)
     VALUES
        (:c, NULL, 'humo.incidencia@example.test', 'AUTENTICACION', 'Prueba humo incidencia')"
)->execute(['c' => $clvCons]);
$id = (int) $pdoTest->lastInsertId();
echo "INSERT_PENDIENTE_ID={$id}\n";

$admin = (string) $pdoTest->query(
    "SELECT ClvUsu FROM usuario WHERE RolUsu='ADMINISTRADOR' LIMIT 1"
)->fetchColumn();

$pdoTest->prepare(
    "UPDATE incidencia_soporte
     SET EstadoIncidencia='EN_PROCESO',
         ClvUsuAtencion=:a,
         FechaActualizacion=NOW()
     WHERE IdIncidencia=:id"
)->execute(['a' => $admin !== '' ? $admin : null, 'id' => $id]);

$pdoTest->prepare(
    "UPDATE incidencia_soporte
     SET EstadoIncidencia='RESUELTA',
         ObservacionAdministrador='Resuelta en prueba',
         ClvUsuAtencion=:a,
         FechaActualizacion=NOW(),
         FechaResolucion=NOW()
     WHERE IdIncidencia=:id"
)->execute(['a' => $admin !== '' ? $admin : null, 'id' => $id]);

$estado = (string) $pdoTest->query(
    "SELECT EstadoIncidencia FROM incidencia_soporte WHERE IdIncidencia={$id}"
)->fetchColumn();
echo "ESTADO_FINAL={$estado}\n";

$enumRejected = false;
try {
    $pdoTest->exec(
        "INSERT INTO incidencia_soporte
            (ClvCons, CorreoReportado, TipoIncidencia, Descripcion)
         VALUES ('{$clvCons}', 'x@y.z', 'INVALIDO', 'x')"
    );
} catch (Throwable $e) {
    $enumRejected = true;
}
echo 'ENUM_RECHAZADO=' . ($enumRejected ? '1' : '0') . "\n";

$citasAntes = (int) $pdoTest->query('SELECT COUNT(*) FROM cita')->fetchColumn();
echo "CITAS_INTACTAS={$citasAntes}\n";

if ($estado !== 'RESUELTA' || !$enumRejected) {
    fwrite(STDERR, "ABORT: validación de prueba falló\n");
    exit(7);
}

echo "=== APLICAR EN consultorio_psicologico ===\n";
$pdoReal = new PDO(
    'mysql:host=localhost;dbname=consultorio_psicologico;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$ya = (bool) $pdoReal->query("SHOW TABLES LIKE 'incidencia_soporte'")->fetchColumn();
if ($ya) {
    echo "SKIP: incidencia_soporte ya existe en real\n";
} else {
    $pdoReal->exec(file_get_contents($sqlFile));
    $ya = (bool) $pdoReal->query("SHOW TABLES LIKE 'incidencia_soporte'")->fetchColumn();
    echo 'APLICADA_REAL=' . ($ya ? '1' : '0') . "\n";
}

$dest = APP_ROOT . '/database/migrations/2026_08_03_incidencia_soporte.sql';
$finalSql = preg_replace(
    '/^-- PROPUESTA.*$/m',
    '-- Migración aplicada localmente: incidencia_soporte',
    file_get_contents($sqlFile),
    1
);
file_put_contents($dest, $finalSql);
echo "MOVED_TO={$dest}\n";

if (is_file($sqlFile)) {
    // Dejar stub en proposed indicando que se movió.
    file_put_contents(
        $sqlFile,
        "-- MOVIDA a database/migrations/2026_08_03_incidencia_soporte.sql\n"
        . "-- No aplicar desde aquí.\n"
    );
}

echo "OK\n";
