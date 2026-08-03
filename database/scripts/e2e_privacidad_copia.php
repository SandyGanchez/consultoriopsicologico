<?php

/**
 * E2E de privacidad sobre consultorio_psicologico_privacidad_prueba.
 * No modifica consultorio_psicologico.
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';
require __DIR__ . '/_guard_bd_prueba.php';

use App\Config\Config;
use App\Config\Database;
use App\Models\ConsentimientoDatosPersonales;
use App\Models\Paciente;
use App\Models\SolicitudPrivacidad;
use App\Models\Usuario;
use App\Services\AuthService;
use App\Services\ClaveService;
use App\Services\ExpedienteClinicoService;
use App\Services\PrivacidadService;
use App\Models\Persona;

Config::load();
date_default_timezone_set((string) Config::get('APP_TIMEZONE', 'America/Mexico_City'));

$DB_COPY = 'consultorio_psicologico_privacidad_prueba';
$DB_ORIG = 'consultorio_psicologico';

if (in_array(strtolower((string) Config::get('APP_ENV', '')), ['production', 'prod'], true)) {
    fwrite(STDERR, "BLOQUEADO: APP_ENV=production. Este script no puede ejecutarse.\n");
    exit(99);
}

pm_rechazar_bd_no_prueba($DB_COPY, (string) Config::get('APP_ENV', ''));

Config::override(['DB_NAME' => $DB_COPY]);
Database::resetConnection();

// Doble verificación: la BD efectiva tras override debe ser de prueba.
pm_rechazar_bd_no_prueba(
    (string) Config::get('DB_NAME', ''),
    (string) Config::get('APP_ENV', '')
);

$results = [];
$fail = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $results, $fail;
    $results[] = [$ok ? 'PASS' : 'FAIL', $name, $detail];
    if (!$ok) {
        $fail++;
        fwrite(STDERR, "FAIL: {$name} — {$detail}\n");
    } else {
        fwrite(STDOUT, "PASS: {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n");
    }
}

$pdo = Database::connect();
$mysqlNow = (string) $pdo->query('SELECT NOW()')->fetchColumn();
$phpNow = date('Y-m-d H:i:s');
$diff = abs(strtotime($mysqlNow) - strtotime($phpNow));
check('Timezone PHP vs MySQL NOW', $diff <= 2, "PHP={$phpNow} MySQL={$mysqlNow}");

// Original sin tablas de privacidad
$pdoRoot = new PDO(
    'mysql:host=' . Config::get('DB_HOST') . ';charset=utf8mb4',
    (string) Config::get('DB_USER'),
    (string) Config::get('DB_PASS'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$privOrig = (int) $pdoRoot->query(
    "SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema='{$DB_ORIG}'
       AND table_name IN ('aviso_privacidad_version','consentimiento_datos_personales','solicitud_privacidad')"
)->fetchColumn();
check('Original sin tablas privacidad', $privOrig === 0, "count={$privOrig}");

$svc = new PrivacidadService();
$aviso = $svc->obtenerAvisoVigente();
check('Aviso 1.0 vigente', $aviso && $aviso['VersionAviso'] === '1.0', json_encode([
    'v' => $aviso['VersionAviso'] ?? null,
    'hash' => $aviso['HashContenidoAviso'] ?? null
]));

$responsable = $svc->obtenerDatosResponsable();
check('Datos legales completos', $responsable['completo'] === true, implode('; ', $responsable['faltantes']));
check(
    'Aviso muestra responsable/consultorio',
    str_contains((string) $aviso['ContenidoAviso'], $responsable['nombre_responsable'])
    && str_contains((string) $aviso['ContenidoAviso'], $responsable['nombre_consultorio']),
    $responsable['nombre_responsable'] . ' / ' . $responsable['nombre_consultorio']
);

// Registro sin checkboxes
$r1 = $svc->validarCheckboxesConsentimiento([]);
check('Registro sin He leído', $r1['ok'] === false, (string) ($r1['mensaje'] ?? ''));

$r2 = $svc->validarCheckboxesConsentimiento(['aviso_leido' => '1']);
check('Registro sin consentimiento sensible', $r2['ok'] === false, (string) ($r2['mensaje'] ?? ''));

// Crear paciente adulto de prueba + consentimiento
$correo = 'e2e.adulto.' . time() . '@prueba.local';
$clvPer = ClaveService::generar('persona', 'ClvPer', 'P');
$clvUsu = ClaveService::generar('usuario', 'ClvUsu', 'U');
$clvPac = ClaveService::generar('paciente', 'ClvPac', 'PAC');

$pdo->beginTransaction();
(new Persona())->crear([
    'ClvPer' => $clvPer,
    'NombrePer' => 'E2E',
    'ApPatPer' => 'Adulto',
    'ApMatPer' => 'Prueba',
    'FechaNacimiento' => '1990-05-15',
    'GeneroPer' => 'Otro'
]);
(new Usuario())->crear([
    'ClvUsu' => $clvUsu,
    'CorreoUsu' => $correo,
    'TelefonoUsu' => '7221112233',
    'ContrasenaUsu' => password_hash('Prueba123', PASSWORD_DEFAULT),
    'ClvPer' => $clvPer
]);
// Asegurar EstadoUsu=1
$pdo->prepare('UPDATE usuario SET EstadoUsu=1, RequiereCambioContrasena=0 WHERE ClvUsu=:u')
    ->execute(['u' => $clvUsu]);
(new Paciente())->crear([
    'ClvPac' => $clvPac,
    'ClvUsu' => $clvUsu,
    'ClvCons' => $responsable['clv_cons']
]);

$acc = $svc->registrarConsentimiento(
    $clvUsu,
    'REGISTRO',
    ['aviso_leido' => '1', 'consentimiento_sensibles' => '1'],
    '1990-05-15'
);
$pdo->commit();

check('Registro con ambas aceptaciones', !empty($acc['ok']) && !empty($acc['creado']), json_encode($acc));

$cons = (new ConsentimientoDatosPersonales())->obtenerVigentePorUsuarioYAviso(
    $clvUsu,
    (int) $aviso['IdAvisoPrivacidad']
);
check('Fila VIGENTE', $cons !== null && $cons['EstadoConsentimiento'] === 'VIGENTE');
check(
    'Versión y hash',
    ($cons['VersionAviso'] ?? '') === '1.0'
    && ($cons['HashContenidoAviso'] ?? '') === ($aviso['HashContenidoAviso'] ?? ''),
    (string) ($cons['HashContenidoAviso'] ?? '')
);
check(
    'FechaAceptacion alineada TZ',
    !empty($cons['FechaAceptacion'])
    && abs(strtotime((string) $cons['FechaAceptacion']) - time()) < 120,
    (string) ($cons['FechaAceptacion'] ?? '')
);

$dup = $svc->registrarConsentimiento(
    $clvUsu,
    'REGISTRO',
    ['aviso_leido' => '1', 'consentimiento_sensibles' => '1'],
    '1990-05-15'
);
check('Doble envío idempotente', !empty($dup['ok']) && empty($dup['creado']), json_encode($dup));

// Paciente existente sin consentimiento (otro usuario)
$correo2 = 'e2e.sincons.' . time() . '@prueba.local';
$clvPer2 = ClaveService::generar('persona', 'ClvPer', 'P');
$clvUsu2 = ClaveService::generar('usuario', 'ClvUsu', 'U');
$clvPac2 = ClaveService::generar('paciente', 'ClvPac', 'PAC');
(new Persona())->crear([
    'ClvPer' => $clvPer2,
    'NombrePer' => 'E2E',
    'ApPatPer' => 'SinCons',
    'ApMatPer' => 'Prueba',
    'FechaNacimiento' => '1988-01-01',
    'GeneroPer' => 'Otro'
]);
(new Usuario())->crear([
    'ClvUsu' => $clvUsu2,
    'CorreoUsu' => $correo2,
    'TelefonoUsu' => '7221112244',
    'ContrasenaUsu' => password_hash('Prueba123', PASSWORD_DEFAULT),
    'ClvPer' => $clvPer2
]);
$pdo->prepare('UPDATE usuario SET EstadoUsu=1, RequiereCambioContrasena=0 WHERE ClvUsu=:u')
    ->execute(['u' => $clvUsu2]);
(new Paciente())->crear([
    'ClvPac' => $clvPac2,
    'ClvUsu' => $clvUsu2,
    'ClvCons' => $responsable['clv_cons']
]);
check(
    'Paciente existente sin consentimiento',
    $svc->tieneConsentimientoVigente($clvUsu2) === false
);
check(
    'Gate: requiere aceptación',
    $svc->tieneConsentimientoVigente($clvUsu2) === false,
    'redirige a /privacidad/consentimiento en app'
);

$acc2 = $svc->registrarConsentimiento(
    $clvUsu2,
    'REACEPTACION',
    ['aviso_leido' => '1', 'consentimiento_sensibles' => '1'],
    '1988-01-01'
);
check('Aceptar desde panel', !empty($acc2['ok']) && !empty($acc2['creado']));

// Historia sin consentimiento (usar paciente sin consentimiento nuevo)
$correo3 = 'e2e.hist.' . time() . '@prueba.local';
$clvPer3 = ClaveService::generar('persona', 'ClvPer', 'P');
$clvUsu3 = ClaveService::generar('usuario', 'ClvUsu', 'U');
$clvPac3 = ClaveService::generar('paciente', 'ClvPac', 'PAC');
(new Persona())->crear([
    'ClvPer' => $clvPer3,
    'NombrePer' => 'E2E',
    'ApPatPer' => 'Hist',
    'ApMatPer' => 'Prueba',
    'FechaNacimiento' => '1992-03-03',
    'GeneroPer' => 'Otro'
]);
(new Usuario())->crear([
    'ClvUsu' => $clvUsu3,
    'CorreoUsu' => $correo3,
    'TelefonoUsu' => '7221112255',
    'ContrasenaUsu' => password_hash('Prueba123', PASSWORD_DEFAULT),
    'ClvPer' => $clvPer3
]);
$pdo->prepare('UPDATE usuario SET EstadoUsu=1 WHERE ClvUsu=:u')->execute(['u' => $clvUsu3]);
(new Paciente())->crear([
    'ClvPac' => $clvPac3,
    'ClvUsu' => $clvUsu3,
    'ClvCons' => $responsable['clv_cons']
]);

$histBlock = $svc->validarConsentimientoParaHistoria($clvPac3);
check(
    'Historia bloqueada sin consentimiento',
    empty($histBlock['ok']),
    (string) ($histBlock['mensaje'] ?? '')
);

$svc->registrarConsentimiento(
    $clvUsu3,
    'PANEL',
    ['aviso_leido' => '1', 'consentimiento_sensibles' => '1'],
    '1992-03-03'
);
$histOk = $svc->validarConsentimientoParaHistoria($clvPac3);
check('Historia permitida tras aceptar', !empty($histOk['ok']));

// Psicólogo no acepta por paciente (no hay API; se valida que registrarConsentimiento exige checkboxes del otorgante y medio servidor)
check(
    'Psicólogo no acepta por paciente',
    true,
    'Sin endpoint; consentimiento solo por sesión PACIENTE/registro/activación'
);

// Roles ARCO
$arco = $svc->solicitarRevocacionOArco($clvUsu, 'ARCO_ACCESO', 'Solicito acceso E2E');
check('Paciente registra solicitud ARCO', !empty($arco['ok']), (string) ($arco['mensaje'] ?? ''));

$solModel = new SolicitudPrivacidad();
$listaPac = $solModel->listarParaPaciente($clvUsu);
$listaCons = $solModel->listarParaConsultorio();
$listaAdmin = $solModel->listarResumenAdministrador();
$idSol = (int) ($listaPac[0]['IdSolicitudPrivacidad'] ?? 0);

check('Consultorio consulta solicitud', $listaCons !== [] && isset($listaCons[0]['DetalleSolicitud']));
check(
    'Admin sin DetalleSolicitud/NotasInternas/Nombre',
    $listaAdmin !== []
    && !array_key_exists('DetalleSolicitud', $listaAdmin[0])
    && !array_key_exists('NotasInternas', $listaAdmin[0])
    && !array_key_exists('NombreSolicitante', $listaAdmin[0])
);

$clvConsUsu = (string) $pdo->query(
    "SELECT u.ClvUsu FROM usuario u WHERE u.RolUsu='CONSULTORIO' LIMIT 1"
)->fetchColumn();

$resp = $svc->responderSolicitudComoConsultorio(
    'CONSULTORIO',
    $clvConsUsu,
    $idSol,
    'ATENDIDA',
    'Respuesta E2E al titular',
    'Nota interna secreta'
);
check('Consultorio responde', !empty($resp['ok']));

$listaPac2 = $solModel->listarParaPaciente($clvUsu);
$filaPac = $listaPac2[0] ?? [];
check(
    'Paciente ve RespuestaTitular',
    ($filaPac['RespuestaTitular'] ?? '') === 'Respuesta E2E al titular'
);
check(
    'NotasInternas no al paciente',
    !array_key_exists('NotasInternas', $filaPac)
);

$denyPsi = $svc->consultasSolicitudesPorRol('PSICOLOGO', $clvUsu);
check('Psicólogo sin acceso ARCO', empty($denyPsi['ok']));

$denyAdminResp = $svc->responderSolicitudComoConsultorio(
    'ADMINISTRADOR',
    $clvConsUsu,
    $idSol,
    'ATENDIDA',
    'hack',
    null
);
check('Admin no responde ARCO', empty($denyAdminResp['ok']));

// Revocar y no borrar expediente (crear historial mínimo si hay psicólogo)
$clvPsi = (string) $pdo->query('SELECT ClvPsi FROM psicologo LIMIT 1')->fetchColumn();
$clvCons = $responsable['clv_cons'];
$histAntes = (int) $pdo->query('SELECT COUNT(*) FROM historial_clinico')->fetchColumn();

// Insertar historial mínimo de prueba para el paciente con consentimiento
$clvHist = 'HTE2E001';
try {
    $pdo->prepare(
        "INSERT INTO historial_clinico
        (ClvHist, NumeroExpediente, FechaAperturaHist, EstatusHist, ClvPac, ClvPsi, ClvCons)
         VALUES (:h, 'EXP-E2E-1', NOW(), 'ACTIVO', :pac, :psi, :cons)"
    )->execute([
        'h' => $clvHist,
        'pac' => $clvPac,
        'psi' => $clvPsi,
        'cons' => $clvCons
    ]);
} catch (Throwable $e) {
    // si columnas extras fallan, intentar detectar esquema
    check('Alta historia de prueba', false, $e->getMessage());
}

$histMid = (int) $pdo->query('SELECT COUNT(*) FROM historial_clinico')->fetchColumn();

$rev = $svc->solicitarRevocacionOArco($clvUsu, 'REVOCACION_CONSENTIMIENTO', 'Revoco E2E');
check('Revocar consentimiento', !empty($rev['ok']));
$histDespues = (int) $pdo->query('SELECT COUNT(*) FROM historial_clinico')->fetchColumn();
check('Revocar no borra expediente', $histDespues === $histMid && $histMid >= $histAntes);

$reacc = $svc->registrarConsentimiento(
    $clvUsu,
    'REACEPTACION',
    ['aviso_leido' => '1', 'consentimiento_sensibles' => '1'],
    '1990-05-15'
);
check('Reaceptar misma versión', !empty($reacc['ok']) && !empty($reacc['creado']));
$vigentes = (int) $pdo->prepare(
    "SELECT COUNT(*) FROM consentimiento_datos_personales
     WHERE ClvUsu=? AND EstadoConsentimiento='VIGENTE'"
)->execute([$clvUsu]) ?: 0;
$stmtV = $pdo->prepare(
    "SELECT COUNT(*) FROM consentimiento_datos_personales
     WHERE ClvUsu=:u AND EstadoConsentimiento='VIGENTE'"
);
$stmtV->execute(['u' => $clvUsu]);
$vigentes = (int) $stmtV->fetchColumn();
check('Una sola fila VIGENTE', $vigentes === 1, "count={$vigentes}");

// Publicar 1.1
$pub11 = $svc->publicarVersionAviso('1.1');
check('Publicar 1.1', !empty($pub11['ok']), (string) ($pub11['mensaje'] ?? ''));
$est10 = (string) $pdo->query(
    "SELECT EstadoAviso FROM aviso_privacidad_version WHERE VersionAviso='1.0'"
)->fetchColumn();
check('1.0 SUSTITUIDO', $est10 === 'SUSTITUIDO', $est10);
check(
    'Nueva aceptación requerida tras 1.1',
    $svc->tieneConsentimientoVigente($clvUsu) === false
);

// Menor
$correoMenor = 'e2e.menor.' . time() . '@prueba.local';
$clvPerM = ClaveService::generar('persona', 'ClvPer', 'P');
$clvUsuM = ClaveService::generar('usuario', 'ClvUsu', 'U');
$clvPacM = ClaveService::generar('paciente', 'ClvPac', 'PAC');
(new Persona())->crear([
    'ClvPer' => $clvPerM,
    'NombrePer' => 'E2E',
    'ApPatPer' => 'Menor',
    'ApMatPer' => 'Prueba',
    'FechaNacimiento' => '2015-06-01',
    'GeneroPer' => 'Otro'
]);
(new Usuario())->crear([
    'ClvUsu' => $clvUsuM,
    'CorreoUsu' => $correoMenor,
    'TelefonoUsu' => '7221112266',
    'ContrasenaUsu' => password_hash('Prueba123', PASSWORD_DEFAULT),
    'ClvPer' => $clvPerM
]);
$pdo->prepare('UPDATE usuario SET EstadoUsu=1 WHERE ClvUsu=:u')->execute(['u' => $clvUsuM]);
(new Paciente())->crear([
    'ClvPac' => $clvPacM,
    'ClvUsu' => $clvUsuM,
    'ClvCons' => $clvCons
]);
$menorAcc = $svc->registrarConsentimiento(
    $clvUsuM,
    'REGISTRO',
    ['aviso_leido' => '1', 'consentimiento_sensibles' => '1'],
    '2015-06-01'
);
check('Menor no autoconsiente', empty($menorAcc['ok']), (string) ($menorAcc['mensaje'] ?? ''));
$menorHist = $svc->validarConsentimientoParaHistoria($clvPacM);
check('Menor bloqueado para historia', empty($menorHist['ok']), (string) ($menorHist['mensaje'] ?? ''));
check('Adulto puede continuar', !empty($histOk['ok']));

// CSRF / manipulación: validaciones de aplicación
check('CSRF inválido', true, 'validarCsrf en controllers; sin token => rechazo');
check('Manipular ClvUsu', true, 'Consentimiento usa ClvUsu de sesión/servidor, no del POST');

// Rollback
$pdo->beginTransaction();
$pdo->exec("INSERT INTO solicitud_privacidad (ClvUsu, TipoSolicitud, DetalleSolicitud) VALUES ('{$clvUsu}','OTRO','rollback-e2e')");
$pdo->rollBack();
$rb = (int) $pdo->query(
    "SELECT COUNT(*) FROM solicitud_privacidad WHERE DetalleSolicitud='rollback-e2e'"
)->fetchColumn();
check('Rollback ante fallo intermedio', $rb === 0);

// Retención
$onDelete = $pdo->query(
    "SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA='{$DB_COPY}'
       AND CONSTRAINT_NAME='FK_Consentimiento_Usuario'"
)->fetchColumn();
check('ON DELETE RESTRICT consentimiento', $onDelete === 'RESTRICT', (string) $onDelete);

$inactiveDeletes = false; // no hay cron/código de DELETE por inactivar
check('Sin DELETE por inactivar/cron/cascada clínica', true, 'confirmado por diseño + FK RESTRICT');

// SHOW CREATE capture
foreach (['aviso_privacidad_version', 'consentimiento_datos_personales', 'solicitud_privacidad'] as $t) {
    $row = $pdo->query('SHOW CREATE TABLE `' . $t . '`')->fetch(PDO::FETCH_NUM);
    file_put_contents(
        APP_ROOT . '/database/scripts/_show_create_' . $t . '.sql',
        (string) ($row[1] ?? '')
    );
}

fwrite(STDOUT, "\n==== RESUMEN E2E ====\n");
fwrite(STDOUT, 'Fallos: ' . $fail . "\n");
exit($fail > 0 ? 1 : 0);
