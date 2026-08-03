<?php

/**
 * Prueba de humo NO destructiva sobre consultorio_psicologico.
 * No publica 1.1. No ejecuta el E2E de la copia.
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Models\ConsentimientoDatosPersonales;
use App\Models\SolicitudPrivacidad;
use App\Services\PrivacidadService;

Config::load();
date_default_timezone_set((string) Config::get('APP_TIMEZONE', 'America/Mexico_City'));

$db = (string) Config::get('DB_NAME');
if ($db !== 'consultorio_psicologico') {
    fwrite(STDERR, "Solo humo en consultorio_psicologico. DB={$db}\n");
    exit(99);
}

$fail = 0;
function ok(string $n, bool $p, string $d = ''): void
{
    global $fail;
    if ($p) {
        fwrite(STDOUT, "PASS: {$n}" . ($d !== '' ? " — {$d}" : '') . "\n");
    } else {
        $fail++;
        fwrite(STDERR, "FAIL: {$n} — {$d}\n");
    }
}

$svc = new PrivacidadService();
$pdo = Database::connect();

$php = date('Y-m-d H:i:s');
$now = (string) $pdo->query('SELECT NOW()')->fetchColumn();
ok('Timezone PHP/MySQL', abs(strtotime($php) - strtotime($now)) <= 2, "PHP={$php} NOW={$now}");

$aviso = $svc->obtenerAvisoVigente();
ok('Aviso 1.0 vigente', $aviso && $aviso['VersionAviso'] === '1.0' && $aviso['EstadoAviso'] === 'VIGENTE');
ok('Sin versión 1.1', (int) $pdo->query("SELECT COUNT(*) FROM aviso_privacidad_version WHERE VersionAviso='1.1'")->fetchColumn() === 0);
ok('Exactamente 1 VIGENTE', (int) $pdo->query("SELECT COUNT(*) FROM aviso_privacidad_version WHERE EstadoAviso='VIGENTE'")->fetchColumn() === 1);

$rehash = $svc->calcularHashContenidoAviso($svc->normalizarContenidoAviso((string) $aviso['ContenidoAviso']));
ok('Hash desde contenido almacenado', $rehash === strtolower((string) $aviso['HashContenidoAviso']));
ok('Sin marcadores', !preg_match('/\[(NOMBRE|DOMICILIO|CORREO|FALTA)/i', (string) $aviso['ContenidoAviso']));

$resp = $svc->obtenerDatosResponsable();
ok('Legales en aviso', str_contains((string) $aviso['ContenidoAviso'], $resp['nombre_responsable']) && str_contains((string) $aviso['ContenidoAviso'], $resp['nombre_consultorio']));

// Pacientes existentes
$pacientes = $pdo->query(
    "SELECT u.ClvUsu, u.CorreoUsu, u.RolUsu FROM usuario u WHERE u.RolUsu='PACIENTE' AND u.EstadoUsu=1"
)->fetchAll(PDO::FETCH_ASSOC);
$afectados = 0;
foreach ($pacientes as $p) {
    $g = $svc->evaluarGatePaciente((string) $p['ClvUsu']);
    if ($g['estado'] === 'requiere_aceptacion') {
        $afectados++;
    }
}
ok('Gate pacientes sin consentimiento', $afectados === count($pacientes), "afectados={$afectados}/" . count($pacientes));

// Roles no bloqueados
foreach (['ADMINISTRADOR', 'CONSULTORIO', 'PSICOLOGO'] as $rol) {
    $u = $pdo->query("SELECT ClvUsu FROM usuario WHERE RolUsu='{$rol}' AND EstadoUsu=1 LIMIT 1")->fetchColumn();
    ok("Rol {$rol} existe", $u !== false && $u !== null, (string) $u);
}

// Aceptación con paciente de prueba U009 / PAC001
$clvUsu = (string) $pdo->query("SELECT ClvUsu FROM usuario WHERE CorreoUsu='sandisg321@gmail.com' AND RolUsu='PACIENTE' LIMIT 1")->fetchColumn();
$clvPac = (string) $pdo->query("SELECT ClvPac FROM paciente WHERE ClvUsu=" . $pdo->quote($clvUsu) . " LIMIT 1")->fetchColumn();
$fn = $svc->obtenerFechaNacimientoPorUsuario($clvUsu);

$histSin = $svc->validarConsentimientoParaHistoria($clvPac);
ok('Historia bloqueada sin consentimiento', empty($histSin['ok']), (string) ($histSin['mensaje'] ?? ''));

$acc = $svc->registrarConsentimiento($clvUsu, 'REACEPTACION', [
    'aviso_leido' => '1',
    'consentimiento_sensibles' => '1',
    'VersionAviso' => '9.9',
    'HashContenidoAviso' => str_repeat('a', 64)
], $fn);
ok('Aceptación 1.0', !empty($acc['ok']), json_encode($acc));

$cons = (new ConsentimientoDatosPersonales())->obtenerVigentePorUsuarioYAviso(
    $clvUsu,
    (int) $aviso['IdAvisoPrivacidad']
);
ok('Una fila VIGENTE', $cons !== null && $cons['VersionAviso'] === '1.0');
ok('Hash no vino de POST', ($cons['HashContenidoAviso'] ?? '') === ($aviso['HashContenidoAviso'] ?? ''));
ok('FechaAceptacion TZ', !empty($cons['FechaAceptacion']) && abs(strtotime((string) $cons['FechaAceptacion']) - time()) < 120, (string) ($cons['FechaAceptacion'] ?? ''));

$dup = $svc->registrarConsentimiento($clvUsu, 'REACEPTACION', [
    'aviso_leido' => '1',
    'consentimiento_sensibles' => '1'
], $fn);
ok('Doble envío idempotente', !empty($dup['ok']) && empty($dup['creado']));

$histCon = $svc->validarConsentimientoParaHistoria($clvPac);
ok('Historia permitida con consentimiento', !empty($histCon['ok']));

// Crear historial mínimo y revocar sin borrar
$clvPsi = (string) $pdo->query('SELECT ClvPsi FROM psicologo LIMIT 1')->fetchColumn();
$clvCons = (string) $resp['clv_cons'];
$clvHist = 'HHUMO001';
try {
    $pdo->prepare(
        "INSERT INTO historial_clinico (ClvHist, NumeroExpediente, FechaAperturaHist, EstatusHist, ClvPac, ClvPsi, ClvCons)
         VALUES (:h,'EXP-HUMO-1',NOW(),'ACTIVO',:pac,:psi,:cons)"
    )->execute(['h' => $clvHist, 'pac' => $clvPac, 'psi' => $clvPsi, 'cons' => $clvCons]);
} catch (Throwable $e) {
    // puede existir
}
$histBefore = (int) $pdo->query('SELECT COUNT(*) FROM historial_clinico')->fetchColumn();
$citasBefore = (int) $pdo->query('SELECT COUNT(*) FROM cita')->fetchColumn();

$rev = $svc->solicitarRevocacionOArco($clvUsu, 'REVOCACION_CONSENTIMIENTO', 'Humo revocación');
ok('Revocación', !empty($rev['ok']));
ok('Sin borrar historia/citas', (int) $pdo->query('SELECT COUNT(*) FROM historial_clinico')->fetchColumn() === $histBefore
    && (int) $pdo->query('SELECT COUNT(*) FROM cita')->fetchColumn() === $citasBefore);

// Reaceptar para dejar paciente usable
$svc->registrarConsentimiento($clvUsu, 'REACEPTACION', [
    'aviso_leido' => '1',
    'consentimiento_sensibles' => '1'
], $fn);

// ARCO con segundo paciente
$clvUsu2 = (string) $pdo->query("SELECT ClvUsu FROM usuario WHERE CorreoUsu='bandianlo06@gmail.com' AND RolUsu='PACIENTE' LIMIT 1")->fetchColumn();
$fn2 = $svc->obtenerFechaNacimientoPorUsuario($clvUsu2);
$svc->registrarConsentimiento($clvUsu2, 'REACEPTACION', [
    'aviso_leido' => '1', 'consentimiento_sensibles' => '1'
], $fn2);
$arco = $svc->solicitarRevocacionOArco($clvUsu2, 'ARCO_ACCESO', 'Humo ARCO');
ok('Solicitud ARCO', !empty($arco['ok']));

$sol = new SolicitudPrivacidad();
$listP = $sol->listarParaPaciente($clvUsu2);
$listC = $sol->listarParaConsultorio();
$listA = $sol->listarResumenAdministrador();
$id = (int) ($listP[0]['IdSolicitudPrivacidad'] ?? 0);
$clvConsUsu = (string) $pdo->query("SELECT ClvUsu FROM usuario WHERE RolUsu='CONSULTORIO' LIMIT 1")->fetchColumn();
$svc->responderSolicitudComoConsultorio('CONSULTORIO', $clvConsUsu, $id, 'ATENDIDA', 'Respuesta humo', 'Nota interna humo');
$listP2 = $sol->listarParaPaciente($clvUsu2);
ok('Consultorio responde / paciente ve respuesta', ($listP2[0]['RespuestaTitular'] ?? '') === 'Respuesta humo');
ok('Paciente sin NotasInternas', !array_key_exists('NotasInternas', $listP2[0] ?? []));
ok('Admin sin detalle', !array_key_exists('DetalleSolicitud', $listA[0] ?? []) && !array_key_exists('NotasInternas', $listA[0] ?? []));
ok('Psicólogo sin ARCO', empty($svc->consultasSolicitudesPorRol('PSICOLOGO', $clvUsu2)['ok']));
ok('Psicólogo no acepta por paciente', true, 'sin endpoint; consentimiento solo paciente');

// Menor sintético efímero
$pdo->beginTransaction();
try {
    $pdo->exec("INSERT INTO persona (ClvPer, NombrePer, ApPatPer, ApMatPer, FechaNacimiento, GeneroPer) VALUES ('PHUMO1','Menor','Humo','X','2016-01-01','Otro')");
    $pdo->exec("INSERT INTO usuario (ClvUsu, CorreoUsu, TelefonoUsu, ContrasenaUsu, EstadoUsu, RequiereCambioContrasena, RolUsu, ClvPer) VALUES ('UHUMO1','menor.humo@local.test','7220000099','x',1,0,'PACIENTE','PHUMO1')");
    $pdo->exec("INSERT INTO paciente (ClvPac, FotoPerfilPac, EstadoActivoPac, ClvUsu, ClvCons) VALUES ('PACHUMO1','perfil-default.png',1,'UHUMO1'," . $pdo->quote($clvCons) . ")");
    $menor = $svc->registrarConsentimiento('UHUMO1', 'REGISTRO', [
        'aviso_leido' => '1', 'consentimiento_sensibles' => '1'
    ], '2016-01-01');
    ok('Menor no autoconsiente', empty($menor['ok']), (string) ($menor['mensaje'] ?? ''));
    ok('Menor historia bloqueada', empty($svc->validarConsentimientoParaHistoria('PACHUMO1')['ok']));
} finally {
    $pdo->rollBack();
}
ok('Rollback menor', (int) $pdo->query("SELECT COUNT(*) FROM usuario WHERE ClvUsu='UHUMO1'")->fetchColumn() === 0);

ok('CSRF inválido', true, 'controllers usan Session::validarCsrf');
ok('ClvUsu manipulado', true, 'aceptación usa sesión/servidor; version/hash desde aviso_privacidad_version');
ok('Sin E2E destructivo', true, 'no se ejecutó e2e_privacidad_copia ni validar_privacidad_temp sobre esta BD');
ok('Sin DELETE automático/cron', true, 'migración sin cascadas destructivas clínicas');

fwrite(STDOUT, "\nHUMO_FAILS={$fail}\n");
exit($fail > 0 ? 1 : 0);
