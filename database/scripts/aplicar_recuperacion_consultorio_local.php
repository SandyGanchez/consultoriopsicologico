<?php

/**
 * Aplica RECUPERACION_CONSULTORIO SOLO en consultorio_psicologico local.
 * Crea respaldo previo. No toca incidencias ni Hostinger.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Solo CLI\n");
    exit(1);
}

define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';

use App\Config\Config;
use App\Services\ActivacionCuentaService;

Config::load();

$pdo = new PDO(
    'mysql:host=localhost;dbname=consultorio_psicologico;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$db = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
echo "A_DATABASE={$db}\n";

if ($db !== 'consultorio_psicologico') {
    fwrite(STDERR, "ABORT: base incorrecta\n");
    exit(2);
}

$col = $pdo->query(
    "SHOW COLUMNS FROM activacion_cuenta LIKE 'TipoActivacion'"
)->fetch(PDO::FETCH_ASSOC);
$typeAntes = (string) ($col['Type'] ?? '');
echo "C_ENUM_PREVIO={$typeAntes}\n";

if (stripos($typeAntes, 'RECUPERACION_CONSULTORIO') !== false) {
    echo "SKIP: RECUPERACION_CONSULTORIO ya existe\n";
    exit(0);
}

if (
    stripos($typeAntes, 'ALTA_PSICOLOGO') === false
    || stripos($typeAntes, 'ALTA_PACIENTE') === false
    || stripos($typeAntes, 'ALTA_CONSULTORIO') === false
) {
    fwrite(STDERR, "ABORT: ENUM previo inesperado\n");
    exit(3);
}

// Respaldo completo
$backupDir = APP_ROOT . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0775, true);
}

$stamp = date('Ymd_His');
$backupFile = $backupDir . DIRECTORY_SEPARATOR
    . "consultorio_psicologico_pre_recuperacion_admin_{$stamp}.sql";

$mysqldumpCandidates = [
    'C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysqldump.exe',
    'C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysqldump.exe',
    'C:\\laragon\\bin\\mysql\\mysql-8.0.30\\bin\\mysqldump.exe',
    'mysqldump',
];

$dumpBin = null;
foreach ($mysqldumpCandidates as $cand) {
    if ($cand === 'mysqldump') {
        $dumpBin = 'mysqldump';
        break;
    }
    if (is_file($cand)) {
        $dumpBin = $cand;
        break;
    }
}

// Buscar mysqldump en Laragon
if ($dumpBin === 'mysqldump' || $dumpBin === null) {
    $dirs = glob('C:\\laragon\\bin\\mysql\\mysql-*\\bin\\mysqldump.exe');
    if (!empty($dirs)) {
        $dumpBin = $dirs[0];
    }
}

if ($dumpBin === null || ($dumpBin !== 'mysqldump' && !is_file($dumpBin))) {
    // Fallback PHP dump de tablas clave + schema completo vía SHOW TABLES
    echo "BACKUP_MODE=php_fallback\n";
    $fh = fopen($backupFile, 'wb');
    if ($fh === false) {
        fwrite(STDERR, "ABORT: no se pudo crear respaldo\n");
        exit(4);
    }
    fwrite($fh, "-- Backup PHP fallback {$stamp}\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')
            ->fetch(PDO::FETCH_NUM);
        fwrite($fh, "DROP TABLE IF EXISTS `{$table}`;\n");
        fwrite($fh, $create[1] . ";\n\n");
        $rows = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`')
            ->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $cols = array_keys($row);
            $vals = [];
            foreach ($row as $v) {
                if ($v === null) {
                    $vals[] = 'NULL';
                } else {
                    $vals[] = $pdo->quote((string) $v);
                }
            }
            fwrite(
                $fh,
                'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES ('
                . implode(',', $vals) . ");\n"
            );
        }
        fwrite($fh, "\n");
    }
    fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fh);
} else {
    echo "BACKUP_MODE=mysqldump\n";
    $cmd = '"' . $dumpBin . '" -u root --databases consultorio_psicologico --result-file='
        . escapeshellarg($backupFile);
    exec($cmd, $out, $code);
    if ($code !== 0 || !is_file($backupFile)) {
        fwrite(STDERR, "ABORT: mysqldump falló code={$code}\n");
        exit(4);
    }
}

$size = filesize($backupFile);
echo "B_BACKUP={$backupFile}\n";
echo "B_SIZE={$size}\n";

if ($size < 1000) {
    fwrite(STDERR, "ABORT: respaldo vacío o demasiado pequeño\n");
    exit(5);
}

// Mover SQL a migrations (una sola copia)
$proposed = APP_ROOT . '/database/migrations/proposed/2026_08_03_tipo_recuperacion_consultorio.sql';
$final = APP_ROOT . '/database/migrations/2026_08_03_tipo_recuperacion_consultorio.sql';

$sql = <<<'SQL'
-- Migración aplicada localmente: RECUPERACION_CONSULTORIO
-- Base: consultorio_psicologico
-- No modificar Hostinger / ZIP / producción desde este archivo.

SET NAMES utf8mb4;

ALTER TABLE `activacion_cuenta`
  MODIFY COLUMN `TipoActivacion`
    enum(
      'ALTA_PSICOLOGO',
      'ALTA_PACIENTE',
      'ALTA_CONSULTORIO',
      'RECUPERACION_CONSULTORIO'
    ) COLLATE utf8mb4_unicode_ci NOT NULL;
SQL;

file_put_contents($final, $sql);
if (is_file($proposed)) {
    unlink($proposed);
}
echo "D_SQL={$final}\n";

// Aplicar
$pdo->exec(
    "ALTER TABLE activacion_cuenta
     MODIFY COLUMN TipoActivacion
       enum('ALTA_PSICOLOGO','ALTA_PACIENTE','ALTA_CONSULTORIO','RECUPERACION_CONSULTORIO')
       COLLATE utf8mb4_unicode_ci NOT NULL"
);

$col2 = $pdo->query(
    "SHOW COLUMNS FROM activacion_cuenta LIKE 'TipoActivacion'"
)->fetch(PDO::FETCH_ASSOC);
$typeDespues = (string) ($col2['Type'] ?? '');
echo "E_ENUM_POSTERIOR={$typeDespues}\n";

$ok = stripos($typeDespues, 'RECUPERACION_CONSULTORIO') !== false
    && stripos($typeDespues, 'ALTA_CONSULTORIO') !== false
    && stripos($typeDespues, 'ALTA_PSICOLOGO') !== false
    && stripos($typeDespues, 'ALTA_PACIENTE') !== false;

echo 'E_ENUM_OK=' . ($ok ? '1' : '0') . "\n";

$grupos = $pdo->query(
    'SELECT TipoActivacion, COUNT(*) c FROM activacion_cuenta GROUP BY TipoActivacion'
)->fetchAll(PDO::FETCH_ASSOC);
echo 'F_CONTEO=' . json_encode($grupos, JSON_UNESCAPED_UNICODE) . "\n";

$inc = (bool) $pdo->query("SHOW TABLES LIKE 'incidencia_soporte'")->fetchColumn();
echo 'T_INCIDENCIA_AUSENTE=' . (!$inc ? '1' : '0') . "\n";

$soporta = (new ActivacionCuentaService())->soportaRecuperacionConsultorio();
echo 'G_SOPORTA_RECUPERACION=' . ($soporta ? '1' : '0') . "\n";

echo "DONE\n";
exit($ok && !$inc && $soporta ? 0 : 6);
