<?php

/**
 * Humo local de sugerencia_servicio.
 * Limpia filas de prueba al final. No deja sugerencias de ejemplo.
 */

define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Controllers\ConsultorioController;
use App\Controllers\PsicologoController;
use App\Models\SugerenciaServicio;
use App\Services\ServicioOfertaService;
use App\Services\SugerenciaServicioService;

Config::load();
$dbName = (string) Config::get('DB_NAME', '');
if ($dbName !== 'consultorio_psicologico') {
    fwrite(STDERR, "ABORT: DB_NAME={$dbName}\n");
    exit(1);
}

$db = Database::connect();
$current = (string) $db->query('SELECT DATABASE()')->fetchColumn();
if ($current !== 'consultorio_psicologico') {
    fwrite(STDERR, "ABORT: DATABASE()={$current}\n");
    exit(1);
}

$fails = 0;
$idsCreados = [];
$serviciosCreados = [];

function ok(string $m): void
{
    echo "PASS: {$m}\n";
}

function fail(string $m): void
{
    global $fails;
    $fails++;
    echo "FAIL: {$m}\n";
}

$model = new SugerenciaServicio();
$svc = new SugerenciaServicioService();

$model->tablaDisponible()
    ? ok('tablaDisponible() = true')
    : fail('tablaDisponible() = true');

$svc->persistenciaDisponible()
    ? ok('persistenciaDisponible() = true (aviso debe desaparecer)')
    : fail('persistenciaDisponible() = true');

$psi = $db->query(
    "SELECT psi.ClvPsi, psi.ClvCons, usu.ClvUsu
     FROM psicologo psi
     INNER JOIN usuario usu ON usu.ClvUsu = psi.ClvUsu
     WHERE psi.EstatusPsi = 'ACTIVO' AND usu.EstadoUsu = 1
     LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

$clvUsuCons = (string) $db->query(
    "SELECT ClvUsu FROM usuario WHERE RolUsu = 'CONSULTORIO' AND EstadoUsu = 1 LIMIT 1"
)->fetchColumn();

if (!$psi || $clvUsuCons === '') {
    fail('Faltan psicólogo/consultorio de prueba');
    echo "HUMO_FAILS={$fails}\n";
    exit(1);
}

$clvPsi = (string) $psi['ClvPsi'];
$clvCons = (string) $psi['ClvCons'];

$nombre = 'Humo Sug ' . date('His');

$vacio = $svc->crearSugerencia($clvPsi, $clvCons, [
    'nombreSugerido' => '',
    'descripcionSugerida' => 'x',
    'justificacion' => 'y'
]);
!$vacio['ok'] ? ok('Campos vacíos rechazados') : fail('Campos vacíos rechazados');

$r1 = $svc->crearSugerencia($clvPsi, $clvCons, [
    'nombreSugerido' => $nombre,
    'descripcionSugerida' => 'Descripción humo sugerencia',
    'justificacion' => 'Justificación humo para validar flujo'
]);
if ($r1['ok'] ?? false) {
    ok('Sugerencia válida registrada');
    $idsCreados[] = (int) $r1['id'];
} else {
    fail('Sugerencia válida registrada: ' . ($r1['mensaje'] ?? ''));
}

$r2 = $svc->crearSugerencia($clvPsi, $clvCons, [
    'nombreSugerido' => '  ' . strtoupper($nombre) . '  ',
    'descripcionSugerida' => 'Descripción humo sugerencia 2',
    'justificacion' => 'Justificación humo doble envío'
]);
if (($r2['ok'] ?? false) && !empty($r2['idempotente']) && (int) $r2['id'] === (int) $r1['id']) {
    ok('Doble envío idempotente (sin duplicar)');
} else {
    fail('Doble envío idempotente');
}

$hist = $svc->listarParaPsicologo($clvPsi);
$veSu = false;
foreach ($hist as $row) {
    if ((int) ($row['IdSugerenciaServicio'] ?? 0) === (int) $r1['id']) {
        $veSu = true;
    }
}
$veSu ? ok('Psicólogo ve su historial') : fail('Psicólogo ve su historial');

$ajeno = $svc->listarParaPsicologo('PSI999XXX');
$ajeno === []
    ? ok('Psicólogo no ve sugerencias ajenas')
    : fail('Psicólogo no ve sugerencias ajenas');

$listaCons = $svc->listarParaConsultorio($clvCons);
$consVe = false;
foreach ($listaCons as $row) {
    if ((int) ($row['IdSugerenciaServicio'] ?? 0) === (int) $r1['id']) {
        $consVe = true;
    }
}
$consVe ? ok('Consultorio ve sugerencias de su instalación') : fail('Consultorio ve sugerencias');

$otraInst = $svc->listarParaConsultorio('CON999XXX');
$otraInst === []
    ? ok('Consultorio no ve otras instalaciones')
    : fail('Consultorio no ve otras instalaciones');

$rejVacio = $svc->rechazar((int) $r1['id'], $clvCons, $clvUsuCons, '   ');
!$rejVacio['ok']
    ? ok('Rechazo exige observación')
    : fail('Rechazo exige observación');

$rejOk = $svc->rechazar(
    (int) $r1['id'],
    $clvCons,
    $clvUsuCons,
    'No encaja en el catálogo institucional actual.'
);
$rejOk['ok'] ? ok('Flujo de rechazo OK') : fail('Flujo de rechazo: ' . ($rejOk['mensaje'] ?? ''));

$det = $svc->obtenerParaConsultorio((int) $r1['id'], $clvCons);
(($det['EstadoSugerencia'] ?? '') === 'RECHAZADA')
    ? ok('Estado RECHAZADA tras rechazo')
    : fail('Estado RECHAZADA tras rechazo');

// Aprobación: no marca APROBADA solo por abrir formulario (estado sigue PENDIENTE hasta crear).
$nombre2 = 'Humo Aprueba ' . date('His');
$r3 = $svc->crearSugerencia($clvPsi, $clvCons, [
    'nombreSugerido' => $nombre2,
    'descripcionSugerida' => 'Para aprobar con alta de servicio',
    'justificacion' => 'Necesaria para cobertura de pacientes'
]);
if (!($r3['ok'] ?? false)) {
    fail('Segunda sugerencia para aprobación');
} else {
    $idsCreados[] = (int) $r3['id'];
    $previa = $svc->obtenerParaConsultorio((int) $r3['id'], $clvCons);
    (($previa['EstadoSugerencia'] ?? '') === 'PENDIENTE')
        ? ok('Aprobación no ocurre antes de crear el servicio')
        : fail('Aprobación no ocurre antes de crear el servicio');
}

// Rollback ante fallo intermedio
try {
    $db->beginTransaction();
    $clvServFake = 'SERHUM' . substr((string) time(), -3);
    $db->prepare(
        "INSERT INTO servicios (
            ClvServ, NombreServicio, Descripcion, ClvCons,
            DuracionMinutos, CostoServicio, EstatusServicio
         ) VALUES (
            :c, 'Temp rollback', 'x', :cons, 60, 0, 'ACTIVO'
         )"
    )->execute(['c' => $clvServFake, 'cons' => $clvCons]);
    throw new RuntimeException('fallo intermedio simulado');
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}

$siguePendiente = $svc->obtenerParaConsultorio((int) $r3['id'], $clvCons);
$servFake = $db->prepare('SELECT COUNT(*) FROM servicios WHERE ClvServ = :c');
$servFake->execute(['c' => $clvServFake ?? '']);
(($siguePendiente['EstadoSugerencia'] ?? '') === 'PENDIENTE' && (int) $servFake->fetchColumn() === 0)
    ? ok('Rollback ante fallo intermedio')
    : fail('Rollback ante fallo intermedio');

// Aprobación completa en transacción
try {
    $db->beginTransaction();
    $clvServ = 'SERH' . substr((string) time(), -4);
    $serviciosCreados[] = $clvServ;

    $db->prepare(
        "INSERT INTO servicios (
            ClvServ, NombreServicio, Descripcion, ClvCons,
            DuracionMinutos, CostoServicio, EstatusServicio
         ) VALUES (
            :c, :n, :d, :cons, 60, 0, 'ACTIVO'
         )"
    )->execute([
        'c' => $clvServ,
        'n' => $nombre2,
        'd' => 'Servicio creado desde sugerencia humo',
        'cons' => $clvCons
    ]);

    (new ServicioOfertaService($db))->incorporarServicioAPsicologos($clvServ, $clvCons);

    (new SugerenciaServicioService($db))->marcarAprobadaConServicio(
        (int) $r3['id'],
        $clvCons,
        $clvUsuCons,
        $clvServ
    );

    $db->commit();

    $aprobada = $svc->obtenerParaConsultorio((int) $r3['id'], $clvCons);
    $ofertas = (int) $db->query(
        "SELECT COUNT(*) FROM psicologo_servicio WHERE ClvServ = "
        . $db->quote($clvServ)
    )->fetchColumn();

    if (
        ($aprobada['EstadoSugerencia'] ?? '') === 'APROBADA'
        && ($aprobada['ClvServCreado'] ?? '') === $clvServ
        && $ofertas >= 1
    ) {
        ok('Aprobación vincula ClvServCreado e incorpora a psicólogos');
    } else {
        fail('Aprobación vincula ClvServCreado e incorpora a psicólogos');
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fail('Aprobación completa: ' . $e->getMessage());
}

// Permisos por rutas/controladores
$routes = require APP_ROOT . '/routes/web.php';
$sugerir = $routes['POST']['/psicologo/servicios/sugerir'][0] ?? '';
$listado = $routes['GET']['/consultorio/servicios/sugerencias'][0] ?? '';
($sugerir === PsicologoController::class)
    ? ok('Ruta sugerir solo en PsicologoController')
    : fail('Ruta sugerir solo en PsicologoController');
($listado === ConsultorioController::class)
    ? ok('Ruta listado solo en ConsultorioController')
    : fail('Ruta listado solo en ConsultorioController');

$tieneAdminOPaciente = false;
foreach (['GET', 'POST'] as $method) {
    foreach ($routes[$method] ?? [] as $path => $handler) {
        if (
            !is_string($path)
            || (
                !str_contains($path, 'sugerenc')
                && !str_contains($path, 'sugerir')
            )
        ) {
            continue;
        }
        $ctrl = (string) ($handler[0] ?? '');
        if (
            str_contains($ctrl, 'AdministradorController')
            || str_contains($ctrl, 'PacienteController')
        ) {
            $tieneAdminOPaciente = true;
        }
    }
}
!$tieneAdminOPaciente
    ? ok('Admin/Paciente sin rutas de sugerencias')
    : fail('Admin/Paciente sin rutas de sugerencias');

// Limpieza: no dejar datos de ejemplo
foreach ($idsCreados as $id) {
    $db->prepare('DELETE FROM sugerencia_servicio WHERE IdSugerenciaServicio = :id')
        ->execute(['id' => $id]);
}
foreach ($serviciosCreados as $clvServ) {
    $db->prepare('DELETE FROM psicologo_servicio WHERE ClvServ = :c')->execute(['c' => $clvServ]);
    $db->prepare('DELETE FROM servicios WHERE ClvServ = :c')->execute(['c' => $clvServ]);
}

$restantes = (int) $db->query(
    "SELECT COUNT(*) FROM sugerencia_servicio WHERE NombreSugerido LIKE 'Humo %'"
)->fetchColumn();
$restantes === 0
    ? ok('Limpieza: sin sugerencias de ejemplo residuales')
    : fail('Limpieza residual');

echo "HUMO_FAILS={$fails}\n";
exit($fails > 0 ? 1 : 0);
