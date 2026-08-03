<?php

/**
 * Audita activacion_cuenta (solo lectura en BD real) y valida
 * migraciones propuestas en consultorio_psicologico_admin_prueba.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('APP_ROOT', dirname(__DIR__, 2));

$pdo = new PDO(
    'mysql:host=localhost;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "=== A. AUDITORÍA REAL (solo lectura) ===\n";
$pdo->exec('USE consultorio_psicologico');
$create = $pdo->query('SHOW CREATE TABLE activacion_cuenta')->fetch(PDO::FETCH_NUM);
echo $create[1] . "\n\n";

$col = $pdo->query("SHOW COLUMNS FROM activacion_cuenta LIKE 'TipoActivacion'")
    ->fetch(PDO::FETCH_ASSOC);
echo 'COLUMNA=' . ($col['Field'] ?? '') . "\n";
echo 'ENUM_ACTUAL=' . ($col['Type'] ?? '') . "\n";
echo 'NULLABLE=' . ($col['Null'] ?? '') . ' DEFAULT=' . ($col['Default'] ?? 'NULL') . "\n";
echo 'COLLATION_COL=' . ($col['Collation'] ?? '') . "\n";

$tableStatus = $pdo->query(
    "SHOW TABLE STATUS LIKE 'activacion_cuenta'"
)->fetch(PDO::FETCH_ASSOC);
echo 'TABLE_COLLATION=' . ($tableStatus['Collation'] ?? '') . "\n";

$grupos = $pdo->query(
    'SELECT TipoActivacion, Estado, COUNT(*) c
     FROM activacion_cuenta
     GROUP BY TipoActivacion, Estado
     ORDER BY TipoActivacion, Estado'
)->fetchAll(PDO::FETCH_ASSOC);
echo 'FILAS_POR_TIPO_ESTADO=' . json_encode($grupos, JSON_UNESCAPED_UNICODE) . "\n";

$idx = $pdo->query('SHOW INDEX FROM activacion_cuenta')->fetchAll(PDO::FETCH_ASSOC);
foreach ($idx as $i) {
    echo 'IDX ' . $i['Key_name'] . ' -> ' . $i['Column_name'] . "\n";
}

$fks = $pdo->query(
    "SELECT k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME,
            r.UPDATE_RULE, r.DELETE_RULE
     FROM information_schema.KEY_COLUMN_USAGE k
     JOIN information_schema.REFERENTIAL_CONSTRAINTS r
       ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
      AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
     WHERE k.TABLE_SCHEMA = 'consultorio_psicologico'
       AND k.TABLE_NAME = 'activacion_cuenta'
       AND k.REFERENCED_TABLE_NAME IS NOT NULL"
)->fetchAll(PDO::FETCH_ASSOC);
echo 'FKS=' . json_encode($fks, JSON_UNESCAPED_UNICODE) . "\n";

$estados = $pdo->query(
    "SHOW COLUMNS FROM activacion_cuenta LIKE 'Estado'"
)->fetch(PDO::FETCH_ASSOC);
echo 'ESTADOS_ENUM=' . ($estados['Type'] ?? '') . "\n";

$dbPrueba = 'consultorio_psicologico_admin_prueba';
echo "\n=== B. VALIDACIÓN EN {$dbPrueba} ===\n";

$pdo->exec("DROP DATABASE IF EXISTS `{$dbPrueba}`");
$pdo->exec(
    "CREATE DATABASE `{$dbPrueba}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
);

// Clonar solo las tablas necesarias con mysqldump vía shell
$dump = APP_ROOT . '/storage/tmp_admin_prueba.sql';
@mkdir(APP_ROOT . '/storage', 0775, true);
$cmd = 'mysqldump -u root --routines=false --triggers=false consultorio_psicologico '
    . 'persona usuario consultorio consultorio_usuario activacion_cuenta '
    . '> ' . escapeshellarg($dump);
exec($cmd, $out, $code);
if ($code !== 0 || !is_file($dump)) {
    // Fallback: SET FOREIGN_KEY_CHECKS + CREATE desde SHOW CREATE
    $pdo->exec("USE `{$dbPrueba}`");
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach (['persona', 'usuario', 'consultorio', 'consultorio_usuario', 'activacion_cuenta'] as $t) {
        $c = $pdo->query("SHOW CREATE TABLE consultorio_psicologico.`{$t}`")
            ->fetch(PDO::FETCH_NUM)[1];
        $c = preg_replace('/AUTO_INCREMENT=\d+/', 'AUTO_INCREMENT=1', $c);
        $pdo->exec($c);
        $rows = $pdo->query("SELECT * FROM consultorio_psicologico.`{$t}`")
            ->fetchAll(PDO::FETCH_ASSOC);
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
} else {
    $pdo->exec("USE `{$dbPrueba}`");
    $sql = file_get_contents($dump);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt === '' || str_starts_with($stmt, '/*') || str_starts_with($stmt, '--')) {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (Throwable $e) {
            // ignore dump noise
        }
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    @unlink($dump);
}

$antes = (int) $pdo->query('SELECT COUNT(*) FROM activacion_cuenta')->fetchColumn();
$mig = file_get_contents(
    APP_ROOT . '/database/migrations/proposed/2026_08_03_tipo_recuperacion_consultorio.sql'
);
$pdo->exec(
    "ALTER TABLE activacion_cuenta
     MODIFY COLUMN TipoActivacion
       enum('ALTA_PSICOLOGO','ALTA_PACIENTE','ALTA_CONSULTORIO','RECUPERACION_CONSULTORIO')
       COLLATE utf8mb4_unicode_ci NOT NULL"
);
$despues = (int) $pdo->query('SELECT COUNT(*) FROM activacion_cuenta')->fetchColumn();
$type = (string) ($pdo->query(
    "SHOW COLUMNS FROM activacion_cuenta LIKE 'TipoActivacion'"
)->fetch(PDO::FETCH_ASSOC)['Type'] ?? '');

echo "FILAS_ANTES={$antes} FILAS_DESPUES={$despues}\n";
echo "ENUM_PRUEBA={$type}\n";
echo 'ENUM_TIENE_RECUPERACION=' . (stripos($type, 'RECUPERACION_CONSULTORIO') !== false ? '1' : '0') . "\n";
echo 'ENUM_CONSERVA_ALTA=' . (
    stripos($type, 'ALTA_CONSULTORIO') !== false
    && stripos($type, 'ALTA_PSICOLOGO') !== false
    && stripos($type, 'ALTA_PACIENTE') !== false ? '1' : '0'
) . "\n";

// Insertar recuperación de prueba
$clv = (string) $pdo->query(
    "SELECT ClvUsu FROM usuario WHERE RolUsu='CONSULTORIO' LIMIT 1"
)->fetchColumn();
if ($clv !== '') {
    $pdo->prepare(
        "INSERT INTO activacion_cuenta
         (ClvUsu, TokenHash, TipoActivacion, FechaExpiracion, Estado)
         VALUES (:u, :h, 'RECUPERACION_CONSULTORIO', DATE_ADD(NOW(), INTERVAL 1 DAY), 'PENDIENTE')"
    )->execute(['u' => $clv, 'h' => str_repeat('b', 64)]);
    echo "INSERT_RECUPERACION_OK=1\n";
}

// incidencia propuesta
$pdo->exec(
    file_get_contents(
        APP_ROOT . '/database/migrations/proposed/2026_08_03_incidencia_soporte_propuesta.sql'
    )
);
$has = (bool) $pdo->query("SHOW TABLES LIKE 'incidencia_soporte'")->fetchColumn();
$createInc = $has
    ? $pdo->query('SHOW CREATE TABLE incidencia_soporte')->fetch(PDO::FETCH_NUM)[1]
    : '';
echo 'INCIDENCIA_OK=' . ($has ? '1' : '0') . "\n";
if ($has) {
    echo $createInc . "\n";
}

// Confirmar real intacta
$pdo->exec('USE consultorio_psicologico');
$realType = (string) ($pdo->query(
    "SHOW COLUMNS FROM activacion_cuenta LIKE 'TipoActivacion'"
)->fetch(PDO::FETCH_ASSOC)['Type'] ?? '');
$realInc = (bool) $pdo->query("SHOW TABLES LIKE 'incidencia_soporte'")->fetchColumn();
echo "\n=== C. CONFIRMACIÓN BD REAL ===\n";
echo 'REAL_SIN_RECUPERACION=' . (stripos($realType, 'RECUPERACION_CONSULTORIO') === false ? '1' : '0') . "\n";
echo 'REAL_SIN_INCIDENCIA_SOPORTE=' . (!$realInc ? '1' : '0') . "\n";
echo "DONE\n";
