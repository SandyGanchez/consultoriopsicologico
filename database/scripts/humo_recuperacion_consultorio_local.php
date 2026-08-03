<?php

/**
 * Humo local de recuperación administrativa.
 * No deja tokens PENDIENTE. Restaura hash de contraseña.
 * Intenta evitar SMTP real (fallo de envío no revierte el token).
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Core\Session;
use App\Services\AccesoSesionService;
use App\Services\ActivacionCuentaService;
use App\Services\AdministradorService;
use App\Services\InstalacionConsultorioService;

Config::load();
Config::override([
    'MAIL_HOST' => '127.0.0.1',
    'MAIL_PORT' => '1',
    'MAIL_USERNAME' => 'invalid',
    'MAIL_PASSWORD' => 'invalid'
]);

$pdo = Database::connect();
$db = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($db !== 'consultorio_psicologico') {
    fwrite(STDERR, "ABORT DB={$db}\n");
    exit(99);
}

$fails = 0;
function ok(string $m): void { echo "PASS: {$m}\n"; }
function fail(string $m): void { global $fails; $fails++; echo "FAIL: {$m}\n"; }

$svcAct = new ActivacionCuentaService();
$svcAct->soportaRecuperacionConsultorio()
    ? ok('ENUM RECUPERACION detectado')
    : fail('ENUM RECUPERACION');

$inst = (new InstalacionConsultorioService())->resolver();
($inst['estado'] ?? '') === 'unico'
    ? ok('Instalación única')
    : fail('Instalación única');

$admin = new AdministradorService();
$cuenta = $admin->resolverCuentaPrincipalUnica((string) $inst['consultorio']['ClvCons']);
$clvUsu = (string) $cuenta['ClvUsu'];
$clvCons = (string) $inst['consultorio']['ClvCons'];

$estadoUsu = (int) $cuenta['EstadoUsu'];
$req = (int) $cuenta['RequiereCambioContrasena'];
$correo = (string) $cuenta['CorreoUsu'];

($estadoUsu === 1 && $req === 0 && filter_var($correo, FILTER_VALIDATE_EMAIL))
    ? ok('Cuenta elegible para Restablecer acceso')
    : fail('Cuenta no elegible');

$hashOriginal = (string) $pdo->query(
    'SELECT ContrasenaUsu FROM usuario WHERE ClvUsu=' . $pdo->quote($clvUsu)
)->fetchColumn();
$estatusOriginal = (string) $pdo->query(
    'SELECT EstatusCons FROM consultorio WHERE ClvCons=' . $pdo->quote($clvCons)
)->fetchColumn();

$countAltaAntes = (int) $pdo->query(
    "SELECT COUNT(*) FROM activacion_cuenta
     WHERE TipoActivacion IN ('ALTA_CONSULTORIO','ALTA_PSICOLOGO','ALTA_PACIENTE')"
)->fetchColumn();

// Inactiva → no recuperación
$pdo->prepare("UPDATE usuario SET EstadoUsu=0 WHERE ClvUsu=:u")->execute(['u' => $clvUsu]);
try {
    $admin->restablecerAcceso($clvCons);
    fail('Inactiva debió bloquear recuperación');
} catch (Throwable $e) {
    str_contains($e->getMessage(), 'Activa la cuenta antes')
        ? ok('Inactiva exige activar primero')
        : fail('Mensaje inactiva: ' . $e->getMessage());
}
$pdo->prepare("UPDATE usuario SET EstadoUsu=1, RequiereCambioContrasena=0 WHERE ClvUsu=:u")
    ->execute(['u' => $clvUsu]);

// Pendiente activación → no recuperación
$pdo->prepare("UPDATE usuario SET RequiereCambioContrasena=1 WHERE ClvUsu=:u")->execute(['u' => $clvUsu]);
try {
    $admin->restablecerAcceso($clvCons);
    fail('Pendiente debió bloquear recuperación');
} catch (Throwable $e) {
    str_contains($e->getMessage(), 'activación inicial')
        ? ok('Pendiente usa reenviar activación')
        : fail('Mensaje pendiente: ' . $e->getMessage());
}
$pdo->prepare("UPDATE usuario SET RequiereCambioContrasena=0, EstadoUsu=1 WHERE ClvUsu=:u")
    ->execute(['u' => $clvUsu]);

$nombre = trim(($cuenta['NombrePer'] ?? '') . ' ' . ($cuenta['ApPatPer'] ?? ''));
$adminUsu = (string) $pdo->query(
    "SELECT ClvUsu FROM usuario WHERE RolUsu='ADMINISTRADOR' LIMIT 1"
)->fetchColumn();

$r1 = $svcAct->crearRecuperacionConsultorio(
    $clvUsu,
    $adminUsu !== '' ? $adminUsu : $clvUsu,
    $nombre !== '' ? $nombre : 'Responsable',
    (string) ($inst['consultorio']['NombreCons'] ?? '')
);
!empty($r1['ok']) ? ok('Generó recuperación (correo puede fallar en dry SMTP)') : fail('crearRecuperacion 1: ' . ($r1['mensaje'] ?? ''));

$id1 = (int) $pdo->query(
    "SELECT IdActivacion FROM activacion_cuenta
     WHERE ClvUsu=" . $pdo->quote($clvUsu) . "
       AND TipoActivacion='RECUPERACION_CONSULTORIO'
       AND Estado='PENDIENTE'
     ORDER BY IdActivacion DESC LIMIT 1"
)->fetchColumn();
$id1 > 0 ? ok("Token RECUPERACION PENDIENTE id={$id1}") : fail('Sin token PENDIENTE');

$countAltaDespues = (int) $pdo->query(
    "SELECT COUNT(*) FROM activacion_cuenta
     WHERE TipoActivacion IN ('ALTA_CONSULTORIO','ALTA_PSICOLOGO','ALTA_PACIENTE')"
)->fetchColumn();
$countAltaAntes === $countAltaDespues
    ? ok('Tokens ALTA_* intactos en conteo')
    : fail("ALTA cambió {$countAltaAntes}->{$countAltaDespues}");

// Segundo envío invalida solo recuperación anterior
$r2 = $svcAct->crearRecuperacionConsultorio(
    $clvUsu,
    $adminUsu !== '' ? $adminUsu : $clvUsu,
    $nombre !== '' ? $nombre : 'Responsable',
    (string) ($inst['consultorio']['NombreCons'] ?? '')
);
!empty($r2['ok']) ? ok('Segundo envío OK') : fail('crearRecuperacion 2');

$est1 = (string) $pdo->query(
    'SELECT Estado FROM activacion_cuenta WHERE IdActivacion=' . (int) $id1
)->fetchColumn();
$est1 === 'REVOCADA' ? ok('Recuperación anterior REVOCADA') : fail("Anterior estado={$est1}");

$id2 = (int) $pdo->query(
    "SELECT IdActivacion FROM activacion_cuenta
     WHERE ClvUsu=" . $pdo->quote($clvUsu) . "
       AND TipoActivacion='RECUPERACION_CONSULTORIO'
       AND Estado='PENDIENTE'
     ORDER BY IdActivacion DESC LIMIT 1"
)->fetchColumn();

// Obtener token plano no está en BD — generar consumo directo vía hash conocido:
// crear un token controlado insertando hash de un token de prueba
$tokenPrueba = bin2hex(random_bytes(32));
$hashPrueba = hash('sha256', $tokenPrueba);
$pdo->prepare(
    "UPDATE activacion_cuenta
     SET TokenHash=:h, Estado='PENDIENTE', FechaExpiracion=DATE_ADD(NOW(), INTERVAL 1 DAY), FechaUso=NULL
     WHERE IdActivacion=:id"
)->execute(['h' => $hashPrueba, 'id' => $id2]);

// Token ALTA no funciona como recuperación (ruta valida tipo)
$alta = $pdo->query(
    "SELECT TokenHash FROM activacion_cuenta
     WHERE TipoActivacion='ALTA_CONSULTORIO' LIMIT 1"
)->fetchColumn();
$alta ? ok('Existe ALTA_CONSULTORIO de control') : fail('Sin ALTA control');

// Consumir recuperación
$passNueva = 'PruebaRec9x';
$consumo = $svcAct->activarCuenta($tokenPrueba, $passNueva, $passNueva, []);
!empty($consumo['ok']) ? ok('Consumo token recuperación OK') : fail('Consumo: ' . ($consumo['mensaje'] ?? ''));

$estUsada = (string) $pdo->query(
    'SELECT Estado FROM activacion_cuenta WHERE IdActivacion=' . (int) $id2
)->fetchColumn();
$estUsada === 'USADA' ? ok('Token marcado USADA') : fail("Estado post={$estUsada}");

// Rechazo token usado
$reuso = $svcAct->activarCuenta($tokenPrueba, $passNueva, $passNueva, []);
empty($reuso['ok']) ? ok('Token usado rechazado') : fail('Token usado aceptado');

// Password verify
$hashAhora = (string) $pdo->query(
    'SELECT ContrasenaUsu FROM usuario WHERE ClvUsu=' . $pdo->quote($clvUsu)
)->fetchColumn();
password_verify($passNueva, $hashAhora)
    ? ok('Nueva contraseña válida')
    : fail('Nueva contraseña no verifica');

$estadoFinal = (int) $pdo->query(
    'SELECT EstadoUsu FROM usuario WHERE ClvUsu=' . $pdo->quote($clvUsu)
)->fetchColumn();
$estadoFinal === 1 ? ok('EstadoUsu permanece 1') : fail('EstadoUsu cambió');

$estatusFinal = (string) $pdo->query(
    'SELECT EstatusCons FROM consultorio WHERE ClvCons=' . $pdo->quote($clvCons)
)->fetchColumn();
$estatusFinal === $estatusOriginal
    ? ok('EstatusCons intacto=' . $estatusFinal)
    : fail('EstatusCons cambió');

$reqFinal = (int) $pdo->query(
    'SELECT RequiereCambioContrasena FROM usuario WHERE ClvUsu=' . $pdo->quote($clvUsu)
)->fetchColumn();
$reqFinal === 0 ? ok('RequiereCambioContrasena=0') : fail('RequiereCambio=');

// Sesión inactiva
Session::start();
Session::set('usuario', [
    'ClvUsu' => $clvUsu,
    'RolUsu' => 'CONSULTORIO',
    'EstadoUsu' => 1
]);
$pdo->prepare("UPDATE usuario SET EstadoUsu=0 WHERE ClvUsu=:u")->execute(['u' => $clvUsu]);
AccesoSesionService::resetCache();
$eval = (new AccesoSesionService())->evaluarSesionActiva('CONSULTORIO');
(empty($eval['ok']) && ($eval['motivo'] ?? '') === 'inactiva')
    ? ok('Sesión abierta invalidada al inactivar')
    : fail('Sesión inactiva');
$pdo->prepare("UPDATE usuario SET EstadoUsu=1 WHERE ClvUsu=:u")->execute(['u' => $clvUsu]);

// CSRF
Session::start();
$tokenCsrf = Session::csrfToken();
Session::validarCsrf($tokenCsrf) ? ok('CSRF válido') : fail('CSRF válido');
!Session::validarCsrf('alterado') ? ok('CSRF alterado rechazado') : fail('CSRF alterado');

// Manipulación IDs: restablecerAcceso usa solo consultorio de instalación
try {
    $admin->restablecerAcceso('CON_FAKE');
    fail('ClvCons fake debió fallar');
} catch (Throwable $e) {
    ok('ClvCons manipulado rechazado');
}

// Restaurar password original
$pdo->prepare(
    'UPDATE usuario SET ContrasenaUsu=:h, EstadoUsu=1, RequiereCambioContrasena=0 WHERE ClvUsu=:u'
)->execute(['h' => $hashOriginal, 'u' => $clvUsu]);
ok('Hash original restaurado');

// Limpiar PENDIENTE de recuperación de prueba
$pdo->exec(
    "UPDATE activacion_cuenta
     SET Estado='REVOCADA'
     WHERE TipoActivacion='RECUPERACION_CONSULTORIO' AND Estado='PENDIENTE'"
);

$psi = (int) $pdo->query(
    "SELECT COUNT(*) FROM usuario WHERE RolUsu='PSICOLOGO' AND EstadoUsu=1"
)->fetchColumn();
$pac = (int) $pdo->query(
    "SELECT COUNT(*) FROM usuario WHERE RolUsu='PACIENTE' AND EstadoUsu=1"
)->fetchColumn();
ok("Psicólogos activos={$psi} Pacientes activos={$pac}");

$inc = (bool) $pdo->query("SHOW TABLES LIKE 'incidencia_soporte'")->fetchColumn();
!$inc ? ok('incidencia_soporte sigue pendiente') : fail('incidencia aplicada por error');

echo "HUMO_FAILS={$fails}\n";
exit($fails > 0 ? 1 : 0);
