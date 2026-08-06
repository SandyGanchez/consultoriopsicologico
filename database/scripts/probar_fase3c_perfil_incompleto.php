<?php

/**
 * Pruebas locales Fase 3C — perfil incompleto (sin SMTP, limpia al terminar).
 *
 * php database/scripts/probar_fase3c_perfil_incompleto.php
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Models\Notificacion;
use App\Services\PerfilPacienteService;

Config::load();
$pdo = Database::connect();

$fallos = 0;
$casos = [];

$assert = static function (
    bool $ok,
    string $nombre,
    string $detalle = ''
) use (&$casos, &$fallos): void {
    $casos[] = ['ok' => $ok, 'nombre' => $nombre, 'detalle' => $detalle];
    if (!$ok) {
        $fallos++;
    }
};

$clvPac = 'PAC001';
$clvUsu = 'U009';
$notifsCreadas = [];

$notifModel = new Notificacion();
$svc = new PerfilPacienteService();

$snap = $pdo->prepare(
    "SELECT
        u.TelefonoUsu,
        per.FechaNacimiento,
        per.GeneroPer,
        per.ClvDir,
        per.FotoPerfilPer,
        d.PaisDir, d.EstadoDir, d.MunicipioDir, d.ColoniaDir,
        d.CalleDir, d.CodPostDir, d.NumExtDir, d.NumIntDir, d.ReferenciaDir
     FROM paciente p
     JOIN usuario u ON u.ClvUsu = p.ClvUsu
     JOIN persona per ON per.ClvPer = u.ClvPer
     LEFT JOIN direccion d ON d.ClvDir = per.ClvDir
     WHERE p.ClvPac = :pac
     LIMIT 1"
);
$snap->execute(['pac' => $clvPac]);
$original = $snap->fetch(PDO::FETCH_ASSOC);

if (!$original) {
    fwrite(STDERR, "No existe paciente de prueba {$clvPac}\n");
    exit(1);
}

$dirPrueba = 'D3CTEST01';

$limpiar = static function () use (
    $pdo,
    $clvUsu,
    $clvPac,
    $original,
    $dirPrueba,
    &$notifsCreadas
): void {
    if ($notifsCreadas !== []) {
        $place = implode(',', array_fill(0, count($notifsCreadas), '?'));
        $del = $pdo->prepare(
            "DELETE FROM notificacion
             WHERE ClvUsu = ?
               AND ClvNotif IN ({$place})"
        );
        $del->execute(array_merge([$clvUsu], $notifsCreadas));
    }

    $pdo->prepare(
        "DELETE FROM notificacion
         WHERE ClvUsu = :u
           AND TipoNotif = 'CUENTA'
           AND TituloNotif IN ('Completa tu perfil', 'Perfil actualizado')
           AND MensajeNotif = :m
           AND FechaNotif >= DATE_SUB(NOW(), INTERVAL 2 HOUR)"
    )->execute([
        'u' => $clvUsu,
        'm' => PerfilPacienteService::MENSAJE_NOTIF
    ]);

    $pdo->prepare(
        'UPDATE usuario SET TelefonoUsu = :t WHERE ClvUsu = :u'
    )->execute([
        't' => $original['TelefonoUsu'],
        'u' => $clvUsu
    ]);

    $pdo->prepare(
        "UPDATE persona per
         INNER JOIN usuario u ON u.ClvPer = per.ClvPer
         SET
            per.FechaNacimiento = :fn,
            per.GeneroPer = :g,
            per.ClvDir = :dir,
            per.FotoPerfilPer = :foto
         WHERE u.ClvUsu = :u"
    )->execute([
        'fn' => $original['FechaNacimiento'],
        'g' => $original['GeneroPer'],
        'dir' => $original['ClvDir'],
        'foto' => $original['FotoPerfilPer'],
        'u' => $clvUsu
    ]);

    $pdo->prepare('DELETE FROM direccion WHERE ClvDir = :d')->execute([
        'd' => $dirPrueba
    ]);

    unset($clvPac);
};

$registrarNotifsNuevas = static function () use (
    $pdo,
    $clvUsu,
    &$notifsCreadas
): void {
    $stmt = $pdo->prepare(
        "SELECT ClvNotif
         FROM notificacion
         WHERE ClvUsu = :u
           AND TipoNotif = 'CUENTA'
           AND TituloNotif IN ('Completa tu perfil', 'Perfil actualizado')
           AND MensajeNotif = :m
           AND FechaNotif >= DATE_SUB(NOW(), INTERVAL 2 HOUR)"
    );
    $stmt->execute([
        'u' => $clvUsu,
        'm' => PerfilPacienteService::MENSAJE_NOTIF
    ]);

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $notifsCreadas[(string) $id] = (string) $id;
    }
    $notifsCreadas = array_values($notifsCreadas);
};

$ponerIncompleto = static function () use ($pdo, $clvUsu): void {
    // Fecha/Género son NOT NULL en BD; incompleto real vía dirección + teléfono.
    $pdo->prepare(
        "UPDATE persona per
         INNER JOIN usuario u ON u.ClvPer = per.ClvPer
         SET per.ClvDir = NULL
         WHERE u.ClvUsu = :u"
    )->execute(['u' => $clvUsu]);

    $pdo->prepare(
        "UPDATE usuario SET TelefonoUsu = '' WHERE ClvUsu = :u"
    )->execute(['u' => $clvUsu]);
};

$ponerCompleto = static function () use ($pdo, $clvUsu, $dirPrueba): void {
    $pdo->prepare(
        "INSERT INTO direccion (
            ClvDir, PaisDir, EstadoDir, MunicipioDir, ColoniaDir,
            CalleDir, CodPostDir, NumExtDir, NumIntDir, ReferenciaDir
         ) VALUES (
            :id, 'Mexico', 'Mexico', 'Toluca', 'Centro',
            'Calle Prueba', '50000', '12', NULL, NULL
         )"
    )->execute(['id' => $dirPrueba]);

    $pdo->prepare(
        "UPDATE persona per
         INNER JOIN usuario u ON u.ClvPer = per.ClvPer
         SET
            per.FechaNacimiento = '2005-08-02',
            per.GeneroPer = 'Femenino',
            per.ClvDir = :dir
         WHERE u.ClvUsu = :u"
    )->execute(['dir' => $dirPrueba, 'u' => $clvUsu]);

    $pdo->prepare(
        "UPDATE usuario SET TelefonoUsu = '7298456578' WHERE ClvUsu = :u"
    )->execute(['u' => $clvUsu]);
};

try {
    // Limpiar ciclos previos de prueba
    $pdo->prepare(
        "DELETE FROM notificacion
         WHERE ClvUsu = :u
           AND TipoNotif = 'CUENTA'
           AND TituloNotif IN ('Completa tu perfil', 'Perfil actualizado')
           AND MensajeNotif = :m"
    )->execute([
        'u' => $clvUsu,
        'm' => PerfilPacienteService::MENSAJE_NOTIF
    ]);

    // 1–4: detección de faltantes / opcionales
    $datosVacios = [
        'FechaNacimiento' => null,
        'GeneroPer' => '  ',
        'TelefonoUsu' => '',
        'PaisDir' => null,
        'EstadoDir' => null,
        'MunicipioDir' => null,
        'ColoniaDir' => null,
        'CalleDir' => null,
        'CodPostDir' => null,
        'NumExtDir' => null,
        'NumIntDir' => '',
        'FotoPerfilPer' => null
    ];

    $faltantes = $svc->obtenerCamposEsencialesFaltantes($datosVacios);
    $assert(
        count($faltantes) === 10,
        '1. Lista de campos faltantes (10 esenciales)',
        implode(',', $faltantes)
    );

    $completos = array_merge($datosVacios, [
        'FechaNacimiento' => '2012-01-01',
        'GeneroPer' => 'Otro',
        'TelefonoUsu' => '5512345678',
        'PaisDir' => 'México',
        'EstadoDir' => 'CDMX',
        'MunicipioDir' => 'Benito Juárez',
        'ColoniaDir' => 'Del Valle',
        'CalleDir' => 'Insurgentes',
        'CodPostDir' => '03100',
        'NumExtDir' => '100',
        'NumIntDir' => '',
        'FotoPerfilPer' => ''
    ]);

    $assert(
        $svc->estaCompleto($completos),
        '2. Perfil completo con datos esenciales'
    );

    $assert(
        !in_array('FotoPerfilPer', $svc->obtenerCamposEsencialesFaltantes($completos), true),
        '3. Foto opcional no genera pendiente'
    );

    $assert(
        !in_array('NumIntDir', $svc->obtenerCamposEsencialesFaltantes($completos), true),
        '4. NumIntDir opcional no genera pendiente'
    );

    $assert(
        $svc->estaCompleto($completos),
        '5. Menor (2012) con perfil general completo'
    );

    $secciones = $svc->obtenerSeccionesPendientes($datosVacios);
    $assert(
        $secciones === ['DATOS_PERSONALES', 'CONTACTO', 'DIRECCION'],
        'Secciones pendientes agrupadas',
        implode(',', $secciones)
    );

    // 6–10: ciclo notificación
    $ponerIncompleto();
    $r1 = $svc->sincronizarAvisoPerfilIncompleto($clvPac, $clvUsu);
    $registrarNotifsNuevas();
    $assert(
        !empty($r1['notificacionCreada']) && empty($r1['completo']),
        '6. Creación de primera notificación'
    );

    $antes = $notifModel->contarPorUsuarioTipoYTitulo(
        $clvUsu,
        'CUENTA',
        PerfilPacienteService::TITULO_ACTIVO,
        120
    );

    $r2 = $svc->sincronizarAvisoPerfilIncompleto($clvPac, $clvUsu);
    $despues = $notifModel->contarPorUsuarioTipoYTitulo(
        $clvUsu,
        'CUENTA',
        PerfilPacienteService::TITULO_ACTIVO,
        120
    );

    $assert(
        empty($r2['notificacionCreada']) && $antes === $despues && $antes === 1,
        '7. Segunda sincronización sin duplicado',
        "antes={$antes} despues={$despues}"
    );

    $activa = $pdo->prepare(
        "SELECT ClvNotif FROM notificacion
         WHERE ClvUsu = :u AND TituloNotif = :t
         LIMIT 1"
    );
    $activa->execute([
        'u' => $clvUsu,
        't' => PerfilPacienteService::TITULO_ACTIVO
    ]);
    $clvNotif = (string) $activa->fetchColumn();
    $assert($clvNotif !== '', 'Notificación activa localizada');

    $notifModel->marcarLeida($clvNotif, $clvUsu);
    $r3 = $svc->sincronizarAvisoPerfilIncompleto($clvPac, $clvUsu);
    $countActivas = $notifModel->contarPorUsuarioTipoYTitulo(
        $clvUsu,
        'CUENTA',
        PerfilPacienteService::TITULO_ACTIVO,
        120
    );
    $assert(
        empty($r3['notificacionCreada']) && $countActivas === 1,
        '8. Lectura manual sin nuevo duplicado'
    );

    $ponerCompleto();
    $r4 = $svc->sincronizarAvisoPerfilIncompleto($clvPac, $clvUsu);
    $registrarNotifsNuevas();
    $stmtAct = $pdo->prepare(
        "SELECT COUNT(*) FROM notificacion
         WHERE ClvUsu = :u AND TituloNotif = :t"
    );
    $stmtAct->execute([
        'u' => $clvUsu,
        't' => PerfilPacienteService::TITULO_ACTIVO
    ]);
    $activasTras = (int) $stmtAct->fetchColumn();

    $assert(
        !empty($r4['completo'])
        && !empty($r4['notificacionResuelta'])
        && $activasTras === 0,
        '9. Resolución al completar'
    );

    $ponerIncompleto();
    $r5 = $svc->sincronizarAvisoPerfilIncompleto($clvPac, $clvUsu);
    $registrarNotifsNuevas();
    $assert(
        !empty($r5['notificacionCreada']),
        '10. Nuevo ciclo posterior permitido'
    );

    // 11–12: destinatario y mensaje seguro
    $stmtMsg = $pdo->prepare(
        "SELECT ClvUsu, MensajeNotif, TituloNotif
         FROM notificacion
         WHERE ClvUsu = :u AND TituloNotif = :t
         ORDER BY FechaNotif DESC LIMIT 1"
    );
    $stmtMsg->execute([
        'u' => $clvUsu,
        't' => PerfilPacienteService::TITULO_ACTIVO
    ]);
    $msg = $stmtMsg->fetch(PDO::FETCH_ASSOC) ?: [];
    $assert(
        ($msg['ClvUsu'] ?? '') === $clvUsu,
        '11. Solo destinatario paciente'
    );

    $texto = (string) ($msg['MensajeNotif'] ?? '');
    $assert(
        !str_contains($texto, 'diagnóstico')
        && !str_contains($texto, 'ClvPac')
        && !str_contains($texto, $clvPac)
        && !str_contains($texto, 'FechaNacimiento')
        && $texto === PerfilPacienteService::MENSAJE_NOTIF,
        '12. Sin datos clínicos ni técnicos en mensaje'
    );

    // 13: concurrencia simulada (doble sync)
    $antesConc = $notifModel->contarPorUsuarioTipoYTitulo(
        $clvUsu,
        'CUENTA',
        PerfilPacienteService::TITULO_ACTIVO,
        120
    );
    $svc->sincronizarAvisoPerfilIncompleto($clvPac, $clvUsu);
    $svc->sincronizarAvisoPerfilIncompleto($clvPac, $clvUsu);
    $despuesConc = $notifModel->contarPorUsuarioTipoYTitulo(
        $clvUsu,
        'CUENTA',
        PerfilPacienteService::TITULO_ACTIVO,
        120
    );
    $assert(
        $antesConc === $despuesConc && $antesConc === 1,
        '13. Idempotencia ante sincronizaciones seguidas'
    );

    // 14: campos manipulados / extras ignorados en evaluación
    $manipulado = array_merge($completos, [
        'RolUsu' => 'ADMINISTRADOR',
        'ClvPac' => 'HACK',
        'Diagnostico' => 'secreto'
    ]);
    $assert(
        $svc->estaCompleto($manipulado)
        && !in_array('Diagnostico', $svc->listarCamposEsenciales(), true),
        '14. Campos manipulados ignorados en evaluación'
    );

    // Destino incorrecto no crea para otro usuario
    $countOtroAntes = (int) $pdo->query(
        "SELECT COUNT(*) FROM notificacion WHERE ClvUsu = 'USU010'"
    )->fetchColumn();
    $svc->sincronizarAvisoPerfilIncompleto($clvPac, 'USU010');
    $countOtroDespues = (int) $pdo->query(
        "SELECT COUNT(*) FROM notificacion WHERE ClvUsu = 'USU010'"
    )->fetchColumn();
    $assert(
        $countOtroAntes === $countOtroDespues,
        'No crea notificación si ClvPac/ClvUsu no coinciden'
    );
} catch (Throwable $e) {
    $assert(false, 'Excepción no controlada', $e->getMessage());
} finally {
    $limpiar();
}

echo "=== Fase 3C perfil incompleto ===\n";
foreach ($casos as $i => $caso) {
    $marca = $caso['ok'] ? 'OK' : 'FAIL';
    echo sprintf(
        "[%s] %d. %s%s\n",
        $marca,
        $i + 1,
        $caso['nombre'],
        $caso['detalle'] !== '' ? ' — ' . $caso['detalle'] : ''
    );
}

echo $fallos === 0
    ? "\nRESULTADO: TODOS LOS CASOS PASARON\n"
    : "\nRESULTADO: {$fallos} FALLO(S)\n";

exit($fallos === 0 ? 0 : 1);
