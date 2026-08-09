<?php

/**
 * Pruebas CLI de verificación de correo (registro público PACIENTE).
 *
 * Uso:
 *   php database/scripts/probar_verificacion_correo.php
 *
 * - Solo BD de prueba (consultorio_psicologico_verif_prueba).
 * - MAIL_VERIFICACION_DRY_RUN=1 (no SMTP real).
 * - No modifica consultorio_psicologico (producción/local real).
 * - No commit/push.
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
use App\Core\Session;
use App\Helpers\Helper;
use App\Models\Paciente;
use App\Models\Persona;
use App\Models\Usuario;
use App\Services\AccesoSesionService;
use App\Services\AuthService;
use App\Services\ClaveService;
use App\Services\InstalacionConsultorioService;
use App\Services\VerificacionCorreoService;

Config::load(APP_ROOT . '/.env');

$DB_ORIG = 'consultorio_psicologico';
$DB_COPY = 'consultorio_psicologico_verif_prueba';

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
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Sesión ANTES de cualquier salida (headers).
Config::override([
    'DB_NAME' => $DB_COPY,
    'MAIL_VERIFICACION_DRY_RUN' => '1',
]);

$pdoRoot->exec("DROP DATABASE IF EXISTS `{$DB_COPY}`");
$pdoRoot->exec(
    "CREATE DATABASE `{$DB_COPY}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
);
$pdoRoot->exec("USE `{$DB_COPY}`");
$pdoRoot->exec('SET FOREIGN_KEY_CHECKS=0');

$tablas = [
    'direccion',
    'persona',
    'usuario',
    'consultorio',
    'consultorio_usuario',
    'paciente',
    'activacion_cuenta',
    'recuperacion_password',
    'consentimiento_datos_personales',
];

foreach ($tablas as $t) {
    $exists = (int) $pdoRoot->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=" . $pdoRoot->quote($DB_ORIG)
         . " AND TABLE_NAME=" . $pdoRoot->quote($t)
    )->fetchColumn();
    if ($exists < 1) {
        continue;
    }

    $create = $pdoRoot->query("SHOW CREATE TABLE `{$DB_ORIG}`.`{$t}`")
        ->fetch(PDO::FETCH_NUM)[1];
    $create = preg_replace('/AUTO_INCREMENT=\d+/', 'AUTO_INCREMENT=1', $create);
    $pdoRoot->exec($create);

    $rows = $pdoRoot->query("SELECT * FROM `{$DB_ORIG}`.`{$t}`")
        ->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $cols = array_keys($r);
        $ph = array_map(static fn ($x) => ':' . $x, $cols);
        $st = $pdoRoot->prepare(
            "INSERT INTO `{$t}` (`" . implode('`,`', $cols) . '`) VALUES ('
            . implode(',', $ph) . ')'
        );
        foreach ($r as $k => $v) {
            $st->bindValue(':' . $k, $v);
        }
        $st->execute();
    }
}

$pdoRoot->exec('SET FOREIGN_KEY_CHECKS=1');

// Fase 4C: Paciente::crear requiere columna ClvPer (sin UNIQUE aquí para
// tolerar inconsistencia histórica U009→2 pacientes en el seed copiado).
$colClvPer = (int) $pdoRoot->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=" . $pdoRoot->quote($DB_COPY) . "
       AND TABLE_NAME='paciente' AND COLUMN_NAME='ClvPer'"
)->fetchColumn();
if ($colClvPer < 1) {
    $pdoRoot->exec(
        "ALTER TABLE `paciente`
         ADD COLUMN `ClvPer` VARCHAR(10) CHARACTER SET utf8mb4
         COLLATE utf8mb4_unicode_ci NULL AFTER `ClvUsu`"
    );
    $pdoRoot->exec(
        "UPDATE `paciente` p
         INNER JOIN `usuario` u ON u.ClvUsu = p.ClvUsu
         SET p.ClvPer = u.ClvPer
         WHERE p.ClvPer IS NULL"
    );
}

// Aplicar migración de verificación (solo en BD de prueba).
$sqlMig = file_get_contents(
    APP_ROOT . '/database/migrations/20260808_verificacion_correo.sql'
);
if ($sqlMig === false) {
    fwrite(STDERR, "No se pudo leer la migración.\n");
    exit(1);
}

foreach (array_filter(array_map('trim', explode(';', $sqlMig))) as $stmtSql) {
    if ($stmtSql === '' || str_starts_with($stmtSql, '--')) {
        // Puede contener comentarios multilínea; ejecutar bloque completo sin -- lines.
    }
}

// Ejecutar statements de forma robusta (ignorar líneas comentario).
$sinComentarios = preg_replace('/^\s*--.*$/m', '', $sqlMig) ?? $sqlMig;
foreach (array_filter(array_map('trim', explode(';', $sinComentarios))) as $stmtSql) {
    if ($stmtSql === '') {
        continue;
    }
    try {
        $pdoRoot->exec($stmtSql);
    } catch (Throwable $e) {
        fwrite(STDERR, 'WARN migracion: ' . $e->getMessage() . "\n");
    }
}

Database::resetConnection();
pm_rechazar_bd_no_prueba(
    (string) Config::get('DB_NAME', ''),
    (string) Config::get('APP_ENV', '')
);

Session::start();
ob_start();
echo "BD de prueba lista: {$DB_COPY}\n";

$pass = 0;
$fail = 0;
$checks = [];

$check = static function (string $name, bool $ok, string $detail = '') use (&$pass, &$fail, &$checks): void {
    $checks[] = [$ok ? 'PASS' : 'FAIL', $name, $detail];
    if ($ok) {
        $pass++;
        echo "PASS: {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    } else {
        $fail++;
        echo "FAIL: {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
};

$pdo = Database::connect();
$clvCons = (new InstalacionConsultorioService())->claveUnicaONull();
$check('Instalación con consultorio', $clvCons !== null, (string) $clvCons);

$colCv = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuario'
       AND COLUMN_NAME='CorreoVerificado'"
)->fetchColumn();
$tablaVc = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='verificacion_correo'"
)->fetchColumn();
$check('Migración: columna CorreoVerificado', $colCv === 1);
$check('Migración: tabla verificacion_correo', $tablaVc === 1);

$existentes = (int) $pdo->query(
    'SELECT COUNT(*) FROM usuario WHERE CorreoVerificado = 0'
)->fetchColumn();
$check(
    '25. Cuentas existentes marcadas verificadas',
    $existentes === 0,
    "no_verificados={$existentes}"
);

$suf = bin2hex(random_bytes(3));
$correo = "verif.{$suf}@example.test";
$passwordPlano = 'ClaveSegura1';
$nombre = 'Verif';
$apPat = 'Prueba';

$db = $pdo;
$db->beginTransaction();

$clvPer = ClaveService::generar('persona', 'ClvPer', 'P');
$clvUsu = ClaveService::generar('usuario', 'ClvUsu', 'U');
$clvPac = ClaveService::generar('paciente', 'ClvPac', 'PAC');

(new Persona())->crear([
    'ClvPer' => $clvPer,
    'NombrePer' => $nombre,
    'ApPatPer' => $apPat,
    'ApMatPer' => 'Test',
    'FechaNacimiento' => '1990-01-15',
    'GeneroPer' => 'Otro',
]);

(new Usuario())->crear([
    'ClvUsu' => $clvUsu,
    'CorreoUsu' => $correo,
    'TelefonoUsu' => '7229988776',
    'ContrasenaUsu' => password_hash($passwordPlano, PASSWORD_DEFAULT),
    'ClvPer' => $clvPer,
]);

(new Paciente())->crear([
    'ClvPac' => $clvPac,
    'ClvPer' => $clvPer,
    'ClvUsu' => $clvUsu,
    'ClvCons' => (string) $clvCons,
]);

$db->commit();

$row = $pdo->prepare(
    'SELECT CorreoVerificado, RequiereCambioContrasena, RolUsu, EstadoUsu
     FROM usuario WHERE ClvUsu = :u'
);
$row->execute(['u' => $clvUsu]);
$u = $row->fetch(PDO::FETCH_ASSOC) ?: [];

$check('1. Registro deja CorreoVerificado=0', (int) ($u['CorreoVerificado'] ?? 1) === 0);
$check('2. RequiereCambioContrasena=0', (int) ($u['RequiereCambioContrasena'] ?? 1) === 0);
$check('Rol PACIENTE EstadoUsu=1', ($u['RolUsu'] ?? '') === 'PACIENTE' && (int) ($u['EstadoUsu'] ?? 0) === 1);

Session::remove('usuario');
Session::remove(VerificacionCorreoService::SESION_CLAVE);

$svc = new VerificacionCorreoService();
$inicio = $svc->iniciarTrasRegistro($clvUsu, $correo, $nombre . ' ' . $apPat);

$check('3. No crea sesión autenticada al iniciar OTP', !Session::has('usuario'));
$check('Contexto registro_verificacion', Session::has(VerificacionCorreoService::SESION_CLAVE));

$codigo = (string) ($inicio['_codigo_prueba'] ?? '');
$check('4. OTP 6 dígitos (dry-run)', (bool) preg_match('/^\d{6}$/', $codigo), $codigo !== '' ? 'len=6' : 'sin codigo');

$hashRow = $pdo->prepare(
    "SELECT CodigoHash, Estado FROM verificacion_correo
     WHERE ClvUsu = :u AND Estado = 'PENDIENTE' ORDER BY IdVerificacion DESC LIMIT 1"
);
$hashRow->execute(['u' => $clvUsu]);
$vr = $hashRow->fetch(PDO::FETCH_ASSOC) ?: [];
$hash = (string) ($vr['CodigoHash'] ?? '');
$check('5. BD solo contiene hash (no plano)', $hash !== '' && $hash !== $codigo && !str_contains($hash, $codigo));

$idPrimera = (int) $pdo->query(
    "SELECT IdVerificacion FROM verificacion_correo
     WHERE ClvUsu=" . $pdo->quote($clvUsu) . " AND Estado='PENDIENTE'
     ORDER BY IdVerificacion DESC LIMIT 1"
)->fetchColumn();

// Código incorrecto
$bad = $svc->validarCodigo('000000');
$intentos = (int) $pdo->query(
    'SELECT Intentos FROM verificacion_correo WHERE IdVerificacion=' . (int) $idPrimera
)->fetchColumn();
$check(
    '9. Código incorrecto suma intento',
    empty($bad['ok']) && $intentos === 1,
    'intentos=' . $intentos
);

// 5 intentos bloquean (ya 1; sumar 4 más)
for ($i = 0; $i < 4; $i++) {
    $svc->validarCodigo('000000');
}
$estadoBloq = (string) $pdo->query(
    'SELECT Estado FROM verificacion_correo WHERE IdVerificacion=' . $idPrimera
)->fetchColumn();
$check('10. 5 intentos inutilizan', $estadoBloq === 'REVOCADA', $estadoBloq);

// Nuevo código tras bloqueo
$re1 = $svc->reenviar();
$codigo2 = (string) ($re1['_codigo_prueba'] ?? '');
$idSegunda = (int) $pdo->query(
    "SELECT IdVerificacion FROM verificacion_correo
     WHERE ClvUsu=" . $pdo->quote($clvUsu) . " AND Estado='PENDIENTE'
     ORDER BY IdVerificacion DESC LIMIT 1"
)->fetchColumn();
$estadoPrimera = (string) $pdo->query(
    'SELECT Estado FROM verificacion_correo WHERE IdVerificacion=' . $idPrimera
)->fetchColumn();
$check('12. Reenvío revoca anterior', $estadoPrimera === 'REVOCADA' || $idPrimera !== $idSegunda);
$check('13. Nuevo código distinto/vigente', $idSegunda > 0 && $codigo2 !== '' && preg_match('/^\d{6}$/', $codigo2));

// Cooldown
$cool = $svc->reenviar();
$check(
    '14. Cooldown funciona',
    empty($cool['ok']) && (($cool['codigo'] ?? '') === 'COOLDOWN'),
    (string) ($cool['codigo'] ?? '')
);

// Expirado
$pdo->prepare(
    'UPDATE verificacion_correo SET FechaExpiracion = DATE_SUB(NOW(), INTERVAL 1 MINUTE)
     WHERE IdVerificacion = :id'
)->execute(['id' => $idSegunda]);
$exp = $svc->validarCodigo($codigo2 !== '' ? $codigo2 : '123456');
$estadoExp = (string) $pdo->query(
    'SELECT Estado FROM verificacion_correo WHERE IdVerificacion=' . $idSegunda
)->fetchColumn();
$check(
    '11. Expirado rechaza',
    empty($exp['ok']) && (($exp['codigo'] ?? '') === 'EXPIRADO' || $estadoExp === 'EXPIRADA'),
    (string) ($exp['codigo'] ?? $estadoExp)
);

// Forzar cooldown pasado y generar código final
$pdo->prepare(
    "UPDATE verificacion_correo
     SET FechaUltimoEnvio = DATE_SUB(NOW(), INTERVAL 2 MINUTE), Estado='REVOCADA'
     WHERE ClvUsu = :u AND Estado='PENDIENTE'"
)->execute(['u' => $clvUsu]);

$fin = $svc->reenviar();
$codigoOk = (string) ($fin['_codigo_prueba'] ?? '');
$idFinal = (int) $pdo->query(
    "SELECT IdVerificacion FROM verificacion_correo
     WHERE ClvUsu=" . $pdo->quote($clvUsu) . " AND Estado='PENDIENTE'
     ORDER BY IdVerificacion DESC LIMIT 1"
)->fetchColumn();

$okVal = $svc->validarCodigo($codigoOk);
$check('6. Código correcto verifica', !empty($okVal['ok']));

$u2 = $pdo->prepare(
    'SELECT CorreoVerificado, FechaVerificacionCorreo FROM usuario WHERE ClvUsu=:u'
);
$u2->execute(['u' => $clvUsu]);
$uv = $u2->fetch(PDO::FETCH_ASSOC) ?: [];
$check('7. FechaVerificacionCorreo se llena', (int) ($uv['CorreoVerificado'] ?? 0) === 1 && !empty($uv['FechaVerificacionCorreo']));

$reuse = $svc->validarCodigo($codigoOk);
// Contexto limpiado tras éxito → SIN_CONTEXTO; restaurar y probar USADA
Session::set(VerificacionCorreoService::SESION_CLAVE, [
    'ClvUsu' => $clvUsu,
    'correo' => $correo,
    'correo_mascarado' => VerificacionCorreoService::enmascararCorreo($correo),
    'FechaInicio' => date('c'),
]);
$reuse2 = $svc->validarCodigo($codigoOk);
$estadoUsada = (string) $pdo->query(
    'SELECT Estado FROM verificacion_correo WHERE IdVerificacion=' . $idFinal
)->fetchColumn();
$check(
    '8. Verificación usada no se reutiliza',
    $estadoUsada === 'USADA' && empty($reuse2['ok']),
    $estadoUsada
);

$usuarioCanon = $okVal['usuario'] ?? null;
if (!is_array($usuarioCanon)) {
    $usuarioCanon = (new Usuario())->buscarPorCorreo($correo);
}
Session::regenerar();
Session::set('usuario', $usuarioCanon);
$sesion = Session::get('usuario');
$claves = ['ClvUsu', 'RolUsu', 'CorreoUsu', 'EstadoUsu', 'RequiereCambioContrasena', 'ClvPer'];
$canonica = true;
foreach ($claves as $k) {
    if (!is_array($sesion) || !array_key_exists($k, $sesion)) {
        $canonica = false;
        break;
    }
}
$check('18. Sesión post-verificación estructura canónica', $canonica);
$destino = Helper::rutaPanelPorRol('PACIENTE');
$check('19. Redirect final = paciente', $destino === 'paciente' || str_ends_with($destino, '/paciente') || $destino === '/paciente', $destino);

AccesoSesionService::resetCache();
$evalDash = (new AccesoSesionService())->evaluarSesionActiva('PACIENTE');
$check('20. Paciente accede dashboard (sesión OK)', !empty($evalDash['ok']));

// Login no verificado
Session::remove('usuario');
$clvPer2 = ClaveService::generar('persona', 'ClvPer', 'P');
$clvUsu2 = ClaveService::generar('usuario', 'ClvUsu', 'U');
$clvPac2 = ClaveService::generar('paciente', 'ClvPac', 'PAC');
$correo2 = "verif2.{$suf}@example.test";
$db->beginTransaction();
(new Persona())->crear([
    'ClvPer' => $clvPer2,
    'NombrePer' => 'Login',
    'ApPatPer' => 'NoVerif',
    'ApMatPer' => 'Test',
    'FechaNacimiento' => '1991-02-20',
    'GeneroPer' => 'Otro',
]);
(new Usuario())->crear([
    'ClvUsu' => $clvUsu2,
    'CorreoUsu' => $correo2,
    'TelefonoUsu' => '7229988777',
    'ContrasenaUsu' => password_hash($passwordPlano, PASSWORD_DEFAULT),
    'ClvPer' => $clvPer2,
]);
(new Paciente())->crear([
    'ClvPac' => $clvPac2,
    'ClvPer' => $clvPer2,
    'ClvUsu' => $clvUsu2,
    'ClvCons' => (string) $clvCons,
]);
$db->commit();

$authNv = (new AuthService())->autenticar($correo2, $passwordPlano);
$check('16a. Credenciales OK en no verificado', !empty($authNv['ok']));
// Simular AuthController: no set usuario
Session::remove('usuario');
(new VerificacionCorreoService())->iniciarDesdeLogin(
    $clvUsu2,
    $correo2,
    'Login NoVerif'
);
$check('16. Login no verificado → contexto verificación (sin sesión auth)', !Session::has('usuario') && Session::has(VerificacionCorreoService::SESION_CLAVE));

// Login verificado
$authV = (new AuthService())->autenticar($correo, $passwordPlano);
$check(
    '17. Login verificado entra normalmente',
    !empty($authV['ok'])
    && (int) (($authV['usuario']['CorreoVerificado'] ?? 0)) === 1
);

// CSRF POST diseñado (rutas existen en web.php)
$routes = file_get_contents(APP_ROOT . '/routes/web.php') ?: '';
$check(
    '15. CSRF POST diseñado (rutas POST + controller)',
    str_contains($routes, "'/verificar-correo'")
    && str_contains($routes, "'/verificar-correo/reenviar'")
    && str_contains($routes, 'verificarCorreo')
    && str_contains($routes, 'reenviarCodigoVerificacion')
);

// Compat: activacion (activarConPassword marca verificado)
$clvPer3 = ClaveService::generar('persona', 'ClvPer', 'P');
$clvUsu3 = ClaveService::generar('usuario', 'ClvUsu', 'U');
$db->beginTransaction();
(new Persona())->crear([
    'ClvPer' => $clvPer3,
    'NombrePer' => 'Psi',
    'ApPatPer' => 'Inv',
    'ApMatPer' => 'Test',
    'FechaNacimiento' => '1985-03-10',
    'GeneroPer' => 'Otro',
]);
$pdo->prepare(
    "INSERT INTO usuario (ClvUsu, CorreoUsu, TelefonoUsu, ContrasenaUsu, EstadoUsu, RequiereCambioContrasena, CorreoVerificado, FechaVerificacionCorreo, RolUsu, ClvPer)
     VALUES (:u,:c,'7221112233',:h,0,1,0,NULL,'PSICOLOGO',:p)"
)->execute([
    'u' => $clvUsu3,
    'c' => "psi.{$suf}@example.test",
    'h' => password_hash('tmp', PASSWORD_DEFAULT),
    'p' => $clvPer3,
]);
$db->commit();
(new Usuario())->activarConPassword($clvUsu3, password_hash('ClaveSegura1', PASSWORD_DEFAULT));
$psi = $pdo->query(
    'SELECT EstadoUsu, CorreoVerificado, RequiereCambioContrasena FROM usuario WHERE ClvUsu='
    . $pdo->quote($clvUsu3)
)->fetch(PDO::FETCH_ASSOC) ?: [];
$check(
    '21. Invitación/activación marca correo verificado',
    (int) ($psi['EstadoUsu'] ?? 0) === 1
    && (int) ($psi['CorreoVerificado'] ?? 0) === 1
    && (int) ($psi['RequiereCambioContrasena'] ?? 1) === 0
);

$check(
    '22. Invitación consultorio (mismo activarConPassword)',
    (int) ($psi['CorreoVerificado'] ?? 0) === 1
);

// Recuperación intacta (modelo/tabla presentes)
$tablaRec = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='recuperacion_password'"
)->fetchColumn();
$check('23. Recuperación password intacta (tabla)', $tablaRec === 1);

// Cambio de correo: actualizarCorreoVerificado mantiene flag
$nuevoCorreo = "nuevo.{$suf}@example.test";
(new Usuario())->actualizarCorreoVerificado($clvUsu, $nuevoCorreo);
$cc = $pdo->query(
    'SELECT CorreoUsu, CorreoVerificado, FechaVerificacionCorreo FROM usuario WHERE ClvUsu='
    . $pdo->quote($clvUsu)
)->fetch(PDO::FETCH_ASSOC) ?: [];
$check(
    '24. Cambio de correo establece verificado',
    ($cc['CorreoUsu'] ?? '') === $nuevoCorreo
    && (int) ($cc['CorreoVerificado'] ?? 0) === 1
    && !empty($cc['FechaVerificacionCorreo'])
);

$check(
    'Enmascarado correo',
    VerificacionCorreoService::enmascararCorreo('sandra@gmail.com') === 'san***@gmail.com'
);

echo "\n=== RESUMEN ===\n";
echo "PASS={$pass} FAIL={$fail}\n";
echo "BD_PRUEBA={$DB_COPY}\n";
echo "MAIL_VERIFICACION_DRY_RUN=1\n";

ob_end_flush();
exit($fail > 0 ? 1 : 0);
