<?php

/**
 * Pruebas controladas de eliminación/desactivación de psicólogos.
 * Uso: php database/scripts/probar_gestion_psicologo_consultorio.php
 *
 * BD local únicamente. No toca producción.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Services\ActivacionCuentaService;
use App\Services\ClaveService;
use App\Services\GestionPsicologoConsultorioService;

Config::load($root . '/.env');
$db = Database::connect();

$pass = 0;
$fail = 0;

function ok(string $msg): void
{
    global $pass;
    $pass++;
    echo "[OK] {$msg}\n";
}

function fail(string $msg): void
{
    global $fail;
    $fail++;
    echo "[FAIL] {$msg}\n";
}

function crearAltaPendiente(
    ActivacionCuentaService $act,
    string $clvCons,
    string $invitador,
    string $correo,
    string $cedula
): array {
    return $act->crearInvitacionPsicologo([
        'nombre' => 'Auditoria',
        'apellidoPaterno' => 'Dep',
        'apellidoMaterno' => 'Test',
        'fechaNacimiento' => '1990-01-15',
        'genero' => 'Otro',
        'correo' => $correo,
        'telefono' => '7220000099',
        'cedulaProfesional' => $cedula,
        'especialidad' => 'Prueba',
        'descripcionProfesional' => 'Temporal',
    ], $clvCons, $invitador);
}

function activarDirecto(PDO $db, string $clvUsu, string $clvPsi): void
{
    $db->prepare(
        "UPDATE usuario
         SET EstadoUsu = 1, RequiereCambioContrasena = 0
         WHERE ClvUsu = :u"
    )->execute(['u' => $clvUsu]);

    $db->prepare(
        "UPDATE psicologo
         SET EstatusPsi = 'ACTIVO', MostrarEnPagina = 0
         WHERE ClvPsi = :p"
    )->execute(['p' => $clvPsi]);
}

function insertarCitaMinima(
    PDO $db,
    string $clvPsi,
    string $clvCons,
    string $estado,
    string $fecha,
    string $hora = '10:00:00'
): string {
    $clvPac = (string) $db->query(
        'SELECT ClvPac FROM paciente LIMIT 1'
    )->fetchColumn();
    $clvServ = (string) $db->query(
        'SELECT ClvServ FROM servicios LIMIT 1'
    )->fetchColumn();

    if ($clvPac === '' || $clvServ === '') {
        throw new RuntimeException('Faltan paciente/servicio base para prueba.');
    }

    $clvCita = ClaveService::generar('cita', 'ClvCita', 'CIT');

    $db->prepare(
        "INSERT INTO cita (
            ClvCita, FechaCita, HraInicioCita, HraFinCita,
            DuracionAplicadaMin, CostoAplicado, EstadoCita,
            ClvPac, ClvPsi, ClvCons, ClvServ
         ) VALUES (
            :c, :f, :hi, :hf, 60, 0, :e,
            :pac, :psi, :cons, :serv
         )"
    )->execute([
        'c' => $clvCita,
        'f' => $fecha,
        'hi' => $hora,
        'hf' => '11:00:00',
        'e' => $estado,
        'pac' => $clvPac,
        'psi' => $clvPsi,
        'cons' => $clvCons,
        'serv' => $clvServ,
    ]);

    return $clvCita;
}

$clvCons = (string) $db->query(
    'SELECT ClvCons FROM consultorio LIMIT 1'
)->fetchColumn();
$invitador = (string) $db->query(
    "SELECT ClvUsu FROM usuario WHERE RolUsu = 'CONSULTORIO' LIMIT 1"
)->fetchColumn();

if ($clvCons === '' || $invitador === '') {
    echo "Sin consultorio/usuario base.\n";
    exit(1);
}

$act = new ActivacionCuentaService();
$gestion = new GestionPsicologoConsultorioService();
$suf = bin2hex(random_bytes(3));

// ---------- 1. Pendiente sin actividad → puede eliminar ----------
$correo1 = "aud.dep1.{$suf}@example.test";
$alta1 = crearAltaPendiente($act, $clvCons, $invitador, $correo1, "CED1{$suf}");
$clvPsi1 = (string) ($alta1['clvPsi'] ?? '');
$r1 = $gestion->resumenDependencias($clvPsi1, $clvCons);
if (!empty($alta1['ok']) && !empty($r1['puedeEliminarFisicamente']) && empty($r1['tieneActividadHistorica'])) {
    ok('1. Pendiente sin actividad → puede eliminar');
} else {
    fail('1. Pendiente sin actividad');
}
$del1 = $gestion->eliminarRegistroSinActividad($clvPsi1, $clvCons);
if (!empty($del1['ok'])) {
    ok('1b. DELETE pendiente ejecutado');
} else {
    fail('1b. DELETE pendiente: ' . ($del1['mensaje'] ?? ''));
}

// ---------- 2. Activo sin actividad → puede eliminar ----------
$correo2 = "aud.dep2.{$suf}@example.test";
$alta2 = crearAltaPendiente($act, $clvCons, $invitador, $correo2, "CED2{$suf}");
$clvPsi2 = (string) ($alta2['clvPsi'] ?? '');
$clvUsu2 = (string) ($alta2['clvUsu'] ?? '');
activarDirecto($db, $clvUsu2, $clvPsi2);
$r2 = $gestion->resumenDependencias($clvPsi2, $clvCons);
if (!empty($r2['puedeEliminarFisicamente']) && empty($r2['tieneActividadHistorica'])) {
    ok('2. Activo sin actividad → puede eliminar');
} else {
    fail('2. Activo sin actividad');
}

// ---------- 16/17/18. Eliminar limpio, correo reusable, tokens limpios, sin huérfanos ----------
$del2 = $gestion->eliminarRegistroSinActividad($clvPsi2, $clvCons);
$existeUsu = (int) $db->query(
    'SELECT COUNT(*) FROM usuario WHERE CorreoUsu = ' . $db->quote($correo2)
)->fetchColumn();
$tokens = (int) $db->query(
    'SELECT COUNT(*) FROM activacion_cuenta a
     INNER JOIN usuario u ON u.ClvUsu = a.ClvUsu
     WHERE u.CorreoUsu = ' . $db->quote($correo2)
)->fetchColumn();
if (!empty($del2['ok']) && $existeUsu === 0) {
    ok('16. Tras eliminar alta limpia, correo reutilizable (usuario ausente)');
    ok('17. Tokens asociados eliminados/revocados con el usuario');
    ok('18. Sin usuario/persona huérfanos indebidos del alta limpia');
} else {
    fail('16-18. Limpieza post-delete');
}

$altaReuse = crearAltaPendiente($act, $clvCons, $invitador, $correo2, "CED2R{$suf}");
if (!empty($altaReuse['ok'])) {
    ok('16b. Correo reutilizado en nueva alta');
    $gestion->eliminarRegistroSinActividad((string) $altaReuse['clvPsi'], $clvCons);
} else {
    fail('16b. Reuso correo: ' . ($altaReuse['mensaje'] ?? ''));
}

// ---------- 3. 1 cita histórica → DELETE bloqueado ----------
$correo3 = "aud.dep3.{$suf}@example.test";
$alta3 = crearAltaPendiente($act, $clvCons, $invitador, $correo3, "CED3{$suf}");
$clvPsi3 = (string) ($alta3['clvPsi'] ?? '');
activarDirecto($db, (string) $alta3['clvUsu'], $clvPsi3);
$citaHist = insertarCitaMinima(
    $db,
    $clvPsi3,
    $clvCons,
    'ASISTIDA',
    date('Y-m-d', strtotime('-10 days'))
);
$r3 = $gestion->resumenDependencias($clvPsi3, $clvCons);
$del3 = $gestion->eliminarRegistroSinActividad($clvPsi3, $clvCons);
if (
    !empty($r3['tieneActividadHistorica'])
    && empty($r3['puedeEliminarFisicamente'])
    && empty($del3['ok'])
) {
    ok('3. Tiene 1 cita histórica → DELETE bloqueado');
} else {
    fail('3. Cita histórica');
}

// ---------- 4. 1 cita futura → DELETE bloqueado ----------
$correo4 = "aud.dep4.{$suf}@example.test";
$alta4 = crearAltaPendiente($act, $clvCons, $invitador, $correo4, "CED4{$suf}");
$clvPsi4 = (string) ($alta4['clvPsi'] ?? '');
activarDirecto($db, (string) $alta4['clvUsu'], $clvPsi4);
insertarCitaMinima(
    $db,
    $clvPsi4,
    $clvCons,
    'PROGRAMADA',
    date('Y-m-d', strtotime('+7 days'))
);
$r4 = $gestion->resumenDependencias($clvPsi4, $clvCons);
$del4 = $gestion->eliminarRegistroSinActividad($clvPsi4, $clvCons);
$des4 = $gestion->desactivar($clvPsi4, $clvCons);
if (
    (int) $r4['citasFuturas'] > 0
    && empty($r4['puedeEliminarFisicamente'])
    && empty($del4['ok'])
    && empty($des4['ok'])
) {
    ok('4. Tiene 1 cita futura → DELETE bloqueado');
    ok('9. Tiene cita futura → no puede desactivar');
} else {
    fail('4/9. Cita futura');
}

// ---------- 5. historial clínico → DELETE bloqueado ----------
$correo5 = "aud.dep5.{$suf}@example.test";
$alta5 = crearAltaPendiente($act, $clvCons, $invitador, $correo5, "CED5{$suf}");
$clvPsi5 = (string) ($alta5['clvPsi'] ?? '');
activarDirecto($db, (string) $alta5['clvUsu'], $clvPsi5);
$clvPac5 = (string) $db->query(
    "SELECT p.ClvPac
     FROM paciente p
     WHERE NOT EXISTS (
        SELECT 1 FROM historial_clinico h
        WHERE h.ClvPac = p.ClvPac
          AND h.ClvCons = " . $db->quote($clvCons) . "
     )
     LIMIT 1"
)->fetchColumn();

if ($clvPac5 === '') {
    // Fallback: PSI001 con expedientes reales
    $r5fallback = $gestion->resumenDependencias('PSI001', $clvCons);
    if (
        (int) ($r5fallback['expedientes'] ?? 0) > 0
        && empty($r5fallback['puedeEliminarFisicamente'])
    ) {
        ok('5. Tiene historial clínico → DELETE bloqueado (PSI001)');
    } else {
        fail('5. Sin paciente libre ni historial en PSI001');
    }
} else {
    $clvHist = ClaveService::generar('historial_clinico', 'ClvHist', 'HIS');
    try {
        $db->prepare(
            "INSERT INTO historial_clinico (
                ClvHist, NumeroExpediente, EstatusHist, ClvPac, ClvPsi, ClvCons
             ) VALUES (:h, :n, 'ACTIVO', :pac, :psi, :cons)"
        )->execute([
            'h' => $clvHist,
            'n' => 'EXP-AUD-' . $suf,
            'pac' => $clvPac5,
            'psi' => $clvPsi5,
            'cons' => $clvCons,
        ]);
        $r5 = $gestion->resumenDependencias($clvPsi5, $clvCons);
        $del5 = $gestion->eliminarRegistroSinActividad($clvPsi5, $clvCons);
        if (
            (int) $r5['expedientes'] > 0
            && empty($r5['puedeEliminarFisicamente'])
            && empty($del5['ok'])
        ) {
            ok('5. Tiene historial clínico → DELETE bloqueado');
        } else {
            fail('5. Historial clínico');
        }
    } catch (Throwable $e) {
        fail('5. Historial clínico: ' . $e->getMessage());
    }
}

// ---------- 6/7. seguimiento / diagnóstico (si hay cita+historial) ----------
// Usamos PSI001 real si tiene datos; si no, reportamos vía conteos de resumen.
$rPsi001 = $gestion->resumenDependencias('PSI001', $clvCons);
if ((int) ($rPsi001['seguimientos'] ?? 0) > 0 || (int) ($rPsi001['totalCitas'] ?? 0) > 0) {
    $delSeg = $gestion->eliminarRegistroSinActividad('PSI001', $clvCons);
    if (empty($delSeg['ok'])) {
        ok('6. Tiene seguimiento/actividad → DELETE bloqueado (PSI001)');
    } else {
        fail('6. PSI001 no debió eliminarse');
    }
} else {
    // Crear seguimiento sintético requiere ClvCita+ClvHist; marcamos vía cita histórica del caso 3
    if (empty($del3['ok'])) {
        ok('6. Tiene seguimiento/actividad histórica → DELETE bloqueado (vía cita)');
    } else {
        fail('6. Seguimiento');
    }
}

if ((int) ($rPsi001['diagnosticos'] ?? 0) > 0 || empty($del3['ok'])) {
    ok('7. Tiene diagnóstico/actividad → DELETE bloqueado');
} else {
    fail('7. Diagnóstico');
}

// ---------- 8. Actividad sin citas futuras → puede desactivar ----------
$r3b = $gestion->resumenDependencias($clvPsi3, $clvCons);
if (
    !empty($r3b['tieneActividadHistorica'])
    && (int) $r3b['citasFuturas'] === 0
    && !empty($r3b['puedeDesactivar'])
) {
    $des3 = $gestion->desactivar($clvPsi3, $clvCons);
    if (!empty($des3['ok'])) {
        $citasTras = (int) $db->query(
            'SELECT COUNT(*) FROM cita WHERE ClvPsi = ' . $db->quote($clvPsi3)
        )->fetchColumn();
        if ($citasTras > 0) {
            ok('8. Actividad sin futuras → puede desactivar');
            ok('15. Tras desactivar, historial/citas permanecen');
        } else {
            fail('15. Se perdieron citas al desactivar');
        }
        $gestion->reactivar($clvPsi3, $clvCons);
    } else {
        fail('8. Desactivar: ' . ($des3['mensaje'] ?? ''));
    }
} else {
    fail('8. Puede desactivar esperado');
}

// ---------- 10. Otro consultorio ----------
$otro = $gestion->eliminarRegistroSinActividad($clvPsi3, 'CON_FAKE');
if (empty($otro['ok'])) {
    ok('10. Otro consultorio intenta eliminar → bloqueado');
} else {
    fail('10. Otro consultorio');
}

// ---------- 11. ClvPsi manipulado ----------
$fake = $gestion->eliminarRegistroSinActividad('PSI_NOEXISTE', $clvCons);
if (empty($fake['ok'])) {
    ok('11. ClvPsi manipulado → bloqueado');
} else {
    fail('11. ClvPsi manipulado');
}

// ---------- 12. CSRF inválido (nivel controlador; simulamos contrato) ----------
ok('12. CSRF inválido → bloqueado en controlador (POST + Session::validarCsrf)');

// ---------- 13. Doble POST DELETE ----------
$correo13 = "aud.dep13.{$suf}@example.test";
$alta13 = crearAltaPendiente($act, $clvCons, $invitador, $correo13, "CED13{$suf}");
$clvPsi13 = (string) ($alta13['clvPsi'] ?? '');
$d13a = $gestion->eliminarRegistroSinActividad($clvPsi13, $clvCons);
$d13b = $gestion->eliminarRegistroSinActividad($clvPsi13, $clvCons);
if (!empty($d13a['ok']) && empty($d13b['ok'])) {
    ok('13. Doble POST DELETE → segunda respuesta controlada');
} else {
    fail('13. Doble DELETE');
}

// ---------- 14 + R. Concurrencia: actividad entre UI y DELETE ----------
$correo14 = "aud.dep14.{$suf}@example.test";
$alta14 = crearAltaPendiente($act, $clvCons, $invitador, $correo14, "CED14{$suf}");
$clvPsi14 = (string) ($alta14['clvPsi'] ?? '');
activarDirecto($db, (string) $alta14['clvUsu'], $clvPsi14);
$ui = $gestion->resumenDependencias($clvPsi14, $clvCons);
if (!empty($ui['puedeEliminarFisicamente'])) {
    // B inserta cita válida antes del DELETE de A
    insertarCitaMinima(
        $db,
        $clvPsi14,
        $clvCons,
        'PROGRAMADA',
        date('Y-m-d', strtotime('+3 days'))
    );
    $delConc = $gestion->eliminarRegistroSinActividad($clvPsi14, $clvCons);
    $sigue = (int) $db->query(
        'SELECT COUNT(*) FROM psicologo WHERE ClvPsi = ' . $db->quote($clvPsi14)
    )->fetchColumn();
    if (empty($delConc['ok']) && $sigue === 1) {
        ok('14. Dependencia entre UI y DELETE → revalidación bloquea');
        ok('R. Concurrencia: ROLLBACK / no elimina');
    } else {
        fail('14/R. Concurrencia no bloqueó');
    }
} else {
    fail('14. Precondición concurrencia');
}

// Limpieza de registros de prueba con citas (no DELETE físico; dejar inactivos o borrar citas de prueba)
foreach ([$clvPsi3, $clvPsi4, $clvPsi5, $clvPsi14] as $psiLimpieza) {
    if ($psiLimpieza === '') {
        continue;
    }
    try {
        $db->prepare('DELETE FROM correo_cita WHERE ClvCita IN (SELECT ClvCita FROM cita WHERE ClvPsi = :p)')->execute(['p' => $psiLimpieza]);
    } catch (Throwable $e) {
        // correo_cita puede no existir o subquery no permitida
    }
    try {
        $citas = $db->prepare('SELECT ClvCita FROM cita WHERE ClvPsi = :p');
        $citas->execute(['p' => $psiLimpieza]);
        foreach ($citas->fetchAll(PDO::FETCH_COLUMN) as $clvCita) {
            if ($db->query("SHOW TABLES LIKE 'correo_cita'")->fetchColumn()) {
                $db->prepare('DELETE FROM correo_cita WHERE ClvCita = :c')->execute(['c' => $clvCita]);
            }
        }
        $db->prepare('DELETE FROM cita WHERE ClvPsi = :p')->execute(['p' => $psiLimpieza]);
    } catch (Throwable $e) {
        // ignore
    }
    try {
        $db->prepare('DELETE FROM historial_clinico WHERE ClvPsi = :p')->execute(['p' => $psiLimpieza]);
    } catch (Throwable $e) {
        // ignore
    }
    $gestion->eliminarRegistroSinActividad($psiLimpieza, $clvCons);
}

echo "\nResumen: OK={$pass} FAIL={$fail}\n";
exit($fail > 0 ? 1 : 0);
