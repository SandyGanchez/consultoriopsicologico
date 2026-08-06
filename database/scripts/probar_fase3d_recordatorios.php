<?php

/**
 * Pruebas locales Fase 3D — recordatorios (sin SMTP real).
 *
 * php database/scripts/probar_fase3d_recordatorios.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Solo CLI.\n");
    exit(1);
}

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Models\CorreoCita;
use App\Services\CorreoCitaService;
use App\Services\RecordatorioCitaService;

Config::load();
Config::override([
    'MAIL_CITA_DRY_RUN' => '1',
    'CITA_RECORDATORIO_HORAS' => '24'
]);

$pdo = Database::connect();
$zona = new DateTimeZone('America/Mexico_City');
$ahora = new DateTimeImmutable('now', $zona);

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

if (!(new CorreoCita())->tablaDisponible()) {
    fwrite(STDERR, "correo_cita no disponible.\n");
    exit(1);
}

$clvPsi = 'PSI001';
$clvPac = 'PAC001';
$clvCons = 'CON001';
$clvServ = 'SER001';
$usuPsi = 'USU009';
$usuPac = 'U009';
$usuCons = 'USU001';
$usuAdmin = 'U008';

$tempCitas = [
    'CIT3D01', 'CIT3D02', 'CIT3D03', 'CIT3D04',
    'CIT3D05', 'CIT3D06', 'CIT3D07', 'CIT3D08',
    'CIT3D09', 'CIT3D10'
];

$limpiar = static function () use ($pdo, $tempCitas, $usuPac, $usuPsi): void {
    $in = "'" . implode("','", $tempCitas) . "'";
    $pdo->exec("DELETE FROM correo_cita WHERE ClvCita IN ({$in})");
    $pdo->exec("DELETE FROM notificacion WHERE ClvUsu IN ('{$usuPac}','{$usuPsi}')
        AND TituloNotif IN ('Tu cita está próxima','Cita próxima')
        AND FechaNotif >= DATE_SUB(NOW(), INTERVAL 2 HOUR)");
    $pdo->exec("DELETE FROM cita WHERE ClvCita IN ({$in})");
};

$insertCita = static function (
    string $clvCita,
    string $estado,
    string $fecha,
    string $hora
) use ($pdo, $clvPsi, $clvPac, $clvCons, $clvServ): void {
    $stmt = $pdo->prepare(
        "INSERT INTO cita (
            ClvCita, FechaCita, HraInicioCita, HraFinCita,
            DuracionAplicadaMin, CostoAplicado, EstadoCita,
            ClvPac, ClvPsi, ClvCons, ClvServ
        ) VALUES (
            :id, :fecha, :hora, ADDTIME(:hora2, '01:00:00'),
            60, 500.00, :estado,
            :pac, :psi, :cons, :serv
        )"
    );
    $stmt->execute([
        'id' => $clvCita,
        'fecha' => $fecha,
        'hora' => $hora,
        'hora2' => $hora,
        'estado' => $estado,
        'pac' => $clvPac,
        'psi' => $clvPsi,
        'cons' => $clvCons,
        'serv' => $clvServ
    ]);
};

$contarNotif = static function (
    string $clvUsu,
    string $titulo
) use ($pdo): int {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM notificacion
         WHERE ClvUsu = :u AND TituloNotif = :t
           AND FechaNotif >= DATE_SUB(NOW(), INTERVAL 2 HOUR)"
    );
    $stmt->execute(['u' => $clvUsu, 't' => $titulo]);
    return (int) $stmt->fetchColumn();
};

$contarCorreo = static function (
    string $clvCita,
    string $rol
) use ($pdo): int {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM correo_cita
         WHERE ClvCita = :c
           AND TipoCorreo = 'RECORDATORIO_24H'
           AND RolDestinatario = :rol"
    );
    $stmt->execute(['c' => $clvCita, 'rol' => $rol]);
    return (int) $stmt->fetchColumn();
};

$estadoCorreo = static function (
    string $clvCita,
    string $rol
) use ($pdo): string {
    $stmt = $pdo->prepare(
        "SELECT EstadoCorreo FROM correo_cita
         WHERE ClvCita = :c
           AND TipoCorreo = 'RECORDATORIO_24H'
           AND RolDestinatario = :rol
         LIMIT 1"
    );
    $stmt->execute(['c' => $clvCita, 'rol' => $rol]);
    return strtoupper(trim((string) $stmt->fetchColumn()));
};

$mensajeLimpio = static function (string $clvUsu, string $titulo) use ($pdo): bool {
    $stmt = $pdo->prepare(
        "SELECT MensajeNotif FROM notificacion
         WHERE ClvUsu = :u AND TituloNotif = :t
           AND FechaNotif >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
         ORDER BY FechaNotif DESC LIMIT 3"
    );
    $stmt->execute(['u' => $clvUsu, 't' => $titulo]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $m = (string) ($fila['MensajeNotif'] ?? '');
        if (
            str_contains($m, '⟦ref:')
            || str_contains($m, 'ref:ClvCita')
            || str_contains($m, 'ClvPac')
            || str_contains($m, 'diagnóstico')
            || str_contains($m, 'FechaNacimiento')
        ) {
            return false;
        }
    }
    return true;
};

$rec = new RecordatorioCitaService();
$correoSvc = new CorreoCitaService();

try {
    $limpiar();

    // Límites temporales (servicio)
    $inicioMas48 = $ahora->modify('+48 hours');
    $inicioExact24 = $ahora->modify('+24 hours');
    $inicio23 = $ahora->modify('+23 hours +59 minutes');
    $inicio4 = $ahora->modify('+4 hours');
    $inicio1min = $ahora->modify('+1 minute');
    $inicioPasado = $ahora->modify('-10 minutes');

    $assert(
        !$rec->estaEnVentanaRecordatorio($inicioMas48, $ahora),
        '1. Cita a más de 24h: fuera de ventana'
    );
    $assert(
        $rec->estaEnVentanaRecordatorio($inicioExact24, $ahora),
        '2. Exactamente 24h: en ventana'
    );
    $assert(
        $rec->estaEnVentanaRecordatorio($inicio23, $ahora),
        '3. A 23:59: en ventana'
    );
    $assert(
        $rec->estaEnVentanaRecordatorio($inicio4, $ahora),
        '4. Creada a 4h: en ventana'
    );
    $assert(
        $rec->estaEnVentanaRecordatorio($inicio1min, $ahora),
        '5. A 1 minuto: en ventana'
    );
    $assert(
        !$rec->estaEnVentanaRecordatorio($inicioPasado, $ahora),
        '6. Ya inició: fuera de ventana'
    );

    // Cita lejana (>24h): filas PENDIENTE con FechaProgramada futura
    $fLejana = $ahora->modify('+48 hours');
    $insertCita(
        'CIT3D01',
        'PROGRAMADA',
        $fLejana->format('Y-m-d'),
        $fLejana->format('H:i:s')
    );
    $correoSvc->prepararParaCitaNueva('CIT3D01');
    $assert(
        $contarCorreo('CIT3D01', 'PACIENTE') === 1
        && $contarCorreo('CIT3D01', 'PSICOLOGO') === 1,
        'Ledger: un RECORDATORIO_24H por rol'
    );
    $stmtProg = $pdo->prepare(
        "SELECT FechaProgramada FROM correo_cita
         WHERE ClvCita = 'CIT3D01' AND TipoCorreo = 'RECORDATORIO_24H'
           AND RolDestinatario = 'PACIENTE' LIMIT 1"
    );
    $stmtProg->execute();
    $fp = (string) $stmtProg->fetchColumn();
    $assert(
        $fp > $ahora->format('Y-m-d H:i:s'),
        '1b. Recordatorio lejano aún no programado para ahora',
        $fp
    );

    $antesPac = $contarNotif($usuPac, RecordatorioCitaService::TITULO_PACIENTE);
    $antesPsi = $contarNotif($usuPsi, RecordatorioCitaService::TITULO_PSICOLOGO);
    $correoSvc->procesarLote(50);
    $assert(
        $contarNotif($usuPac, RecordatorioCitaService::TITULO_PACIENTE) === $antesPac
        && $contarNotif($usuPsi, RecordatorioCitaService::TITULO_PSICOLOGO) === $antesPsi,
        '1c. Procesador no notifica citas fuera de ventana'
    );

    // Cita a 4h: PENDIENTE inmediata (no OMITIDO)
    $f4 = $ahora->modify('+4 hours');
    $insertCita(
        'CIT3D02',
        'PROGRAMADA',
        $f4->format('Y-m-d'),
        $f4->format('H:i:s')
    );
    $correoSvc->prepararParaCitaNueva('CIT3D02');
    $assert(
        $estadoCorreo('CIT3D02', 'PACIENTE') === 'PENDIENTE'
        && $estadoCorreo('CIT3D02', 'PSICOLOGO') === 'PENDIENTE',
        '4b. Cita <24h queda PENDIENTE (no OMITIDO)'
    );

    $antesPac = $contarNotif($usuPac, RecordatorioCitaService::TITULO_PACIENTE);
    $antesPsi = $contarNotif($usuPsi, RecordatorioCitaService::TITULO_PSICOLOGO);
    $antesCons = $contarNotif($usuCons, RecordatorioCitaService::TITULO_PACIENTE)
        + $contarNotif($usuCons, RecordatorioCitaService::TITULO_PSICOLOGO);
    $antesAdmin = $contarNotif($usuAdmin, RecordatorioCitaService::TITULO_PACIENTE)
        + $contarNotif($usuAdmin, RecordatorioCitaService::TITULO_PSICOLOGO);

    $correoSvc->procesarLote(50);

    $assert(
        $contarNotif($usuPac, RecordatorioCitaService::TITULO_PACIENTE) === $antesPac + 1,
        '10. Primera ejecución: notificación paciente'
    );
    $assert(
        $contarNotif($usuPsi, RecordatorioCitaService::TITULO_PSICOLOGO) === $antesPsi + 1,
        '10b. Primera ejecución: notificación psicólogo'
    );
    $assert(
        $estadoCorreo('CIT3D02', 'PACIENTE') === 'ENVIADO'
        && $estadoCorreo('CIT3D02', 'PSICOLOGO') === 'ENVIADO',
        '20. Dry-run marca ENVIADO sin SMTP real'
    );

    // Segunda ejecución: sin duplicados
    $correoSvc->procesarLote(50);
    $assert(
        $contarNotif($usuPac, RecordatorioCitaService::TITULO_PACIENTE) === $antesPac + 1
        && $contarNotif($usuPsi, RecordatorioCitaService::TITULO_PSICOLOGO) === $antesPsi + 1
        && $contarCorreo('CIT3D02', 'PACIENTE') === 1
        && $contarCorreo('CIT3D02', 'PSICOLOGO') === 1,
        '11. Segunda ejecución sin duplicados'
    );

    $assert(
        ($contarNotif($usuCons, RecordatorioCitaService::TITULO_PACIENTE)
            + $contarNotif($usuCons, RecordatorioCitaService::TITULO_PSICOLOGO)) === $antesCons,
        '15. Consultorio sin recordatorio'
    );
    $assert(
        ($contarNotif($usuAdmin, RecordatorioCitaService::TITULO_PACIENTE)
            + $contarNotif($usuAdmin, RecordatorioCitaService::TITULO_PSICOLOGO)) === $antesAdmin,
        '15b. Administrador sin recordatorio'
    );

    $assert(
        $mensajeLimpio($usuPac, RecordatorioCitaService::TITULO_PACIENTE)
        && $mensajeLimpio($usuPsi, RecordatorioCitaService::TITULO_PSICOLOGO),
        '12. Mensajes sin refs/clínica'
    );

    // Concurrencia simulada: dos claims sobre misma fila ENVIADA no duplican
    $idPac = (int) $pdo->query(
        "SELECT IdCorreoCita FROM correo_cita
         WHERE ClvCita = 'CIT3D02' AND RolDestinatario = 'PACIENTE'
           AND TipoCorreo = 'RECORDATORIO_24H' LIMIT 1"
    )->fetchColumn();
    $correoSvc->procesarFilaPorId($idPac);
    $correoSvc->procesarFilaPorId($idPac);
    $assert(
        $contarNotif($usuPac, RecordatorioCitaService::TITULO_PACIENTE) === $antesPac + 1,
        '13. Concurrencia/reproceso sin duplicar campana'
    );

    // Cancelada antes de procesar
    $f5 = $ahora->modify('+3 hours');
    $insertCita(
        'CIT3D03',
        'CANCELADA',
        $f5->format('Y-m-d'),
        $f5->format('H:i:s')
    );
    // Insert ledger manualmente PENDIENTE (simula carrera)
    $pdo->prepare(
        "INSERT INTO correo_cita (
            ClvCita, ClvUsuDestino, TipoCorreo, RolDestinatario,
            FechaProgramada, EstadoCorreo
         ) VALUES (
            'CIT3D03', :u, 'RECORDATORIO_24H', 'PACIENTE',
            NOW(), 'PENDIENTE'
         )"
    )->execute(['u' => $usuPac]);
    $antesCancel = $contarNotif($usuPac, RecordatorioCitaService::TITULO_PACIENTE);
    $correoSvc->procesarLote(50);
    $assert(
        $contarNotif($usuPac, RecordatorioCitaService::TITULO_PACIENTE) === $antesCancel
        && $estadoCorreo('CIT3D03', 'PACIENTE') === 'OMITIDO',
        '7. CANCELADA no crea recordatorio'
    );

    // ASISTIDA / INASISTENCIA
    foreach ([['CIT3D04', 'ASISTIDA'], ['CIT3D05', 'INASISTENCIA']] as [$id, $est]) {
        $fx = $ahora->modify('+2 hours');
        $insertCita($id, $est, $fx->format('Y-m-d'), $fx->format('H:i:s'));
        $pdo->prepare(
            "INSERT INTO correo_cita (
                ClvCita, ClvUsuDestino, TipoCorreo, RolDestinatario,
                FechaProgramada, EstadoCorreo
             ) VALUES (
                :c, :u, 'RECORDATORIO_24H', 'PACIENTE', NOW(), 'PENDIENTE'
             )"
        )->execute(['c' => $id, 'u' => $usuPac]);
    }
    $antesEst = $contarNotif($usuPac, RecordatorioCitaService::TITULO_PACIENTE);
    $correoSvc->procesarLote(50);
    $assert(
        $contarNotif($usuPac, RecordatorioCitaService::TITULO_PACIENTE) === $antesEst
        && $estadoCorreo('CIT3D04', 'PACIENTE') === 'OMITIDO'
        && $estadoCorreo('CIT3D05', 'PACIENTE') === 'OMITIDO',
        '8-9. ASISTIDA/INASISTENCIA no recuerdan'
    );

    // Cita ya iniciada
    $fPas = $ahora->modify('-30 minutes');
    $insertCita(
        'CIT3D06',
        'PROGRAMADA',
        $fPas->format('Y-m-d'),
        $fPas->format('H:i:s')
    );
    $correoSvc->prepararParaCitaNueva('CIT3D06');
    $assert(
        $estadoCorreo('CIT3D06', 'PACIENTE') === 'OMITIDO',
        '6b. Cita iniciada: recordatorio OMITIDO al preparar'
    );

    // ClvUsu inválido / cita inexistente
    $okInexistente = $correoSvc->procesarFilaPorId(999999999);
    $assert($okInexistente === false, '17. Id inexistente no procesa');

    // Idempotencia preparar doble
    $correoSvc->prepararParaCitaNueva('CIT3D02');
    $assert(
        $contarCorreo('CIT3D02', 'PACIENTE') === 1
        && $contarCorreo('CIT3D02', 'PSICOLOGO') === 1,
        'Idempotencia prepararParaCitaNueva sin duplicar ledger'
    );

    // Config horas
    $assert($rec->horasRecordatorio() === 24, 'Config horas default/override 24');
    Config::override(['CITA_RECORDATORIO_HORAS' => '999']);
    $assert(
        (new RecordatorioCitaService())->horasRecordatorio() === 24,
        'Config horas fuera de rango → 24'
    );
    Config::override(['CITA_RECORDATORIO_HORAS' => '24']);

    // Fallo de notificación → ROLLBACK del claim
    $f8 = $ahora->modify('+3 hours');
    $insertCita(
        'CIT3D08',
        'PROGRAMADA',
        $f8->format('Y-m-d'),
        $f8->format('H:i:s')
    );
    $pdo->prepare(
        "INSERT INTO correo_cita (
            ClvCita, ClvUsuDestino, TipoCorreo, RolDestinatario,
            FechaProgramada, EstadoCorreo, Intentos
         ) VALUES (
            'CIT3D08', :u, 'RECORDATORIO_24H', 'PACIENTE',
            NOW(), 'PENDIENTE', 0
         )"
    )->execute(['u' => $usuPac]);
    $idFail = (int) $pdo->query(
        "SELECT IdCorreoCita FROM correo_cita
         WHERE ClvCita = 'CIT3D08' AND RolDestinatario = 'PACIENTE' LIMIT 1"
    )->fetchColumn();

    $recordatorioFalla = new class extends RecordatorioCitaService {
        public function crearNotificacionCampana(
            array $filaCorreo,
            array $contexto
        ): bool {
            return false;
        }
    };
    $svcFalla = new CorreoCitaService(null, null, null, $recordatorioFalla);
    $antesFailNotif = $contarNotif($usuPac, RecordatorioCitaService::TITULO_PACIENTE);
    $okFail = $svcFalla->procesarFilaPorId($idFail);
    $stmtFail = $pdo->prepare(
        "SELECT EstadoCorreo, Intentos FROM correo_cita
         WHERE IdCorreoCita = :id LIMIT 1"
    );
    $stmtFail->execute(['id' => $idFail]);
    $filaFail = $stmtFail->fetch(PDO::FETCH_ASSOC) ?: [];
    $assert(
        $okFail === false
        && ($filaFail['EstadoCorreo'] ?? '') === 'PENDIENTE'
        && (int) ($filaFail['Intentos'] ?? -1) === 0
        && $contarNotif($usuPac, RecordatorioCitaService::TITULO_PACIENTE) === $antesFailNotif,
        '15. Fallo de notificación: rollback del claim'
    );
    $pdo->exec(
        "UPDATE correo_cita
         SET EstadoCorreo = 'OMITIDO', MotivoOmision = 'PRUEBA_ROLLBACK'
         WHERE ClvCita = 'CIT3D08'"
    );

    // Reactivar OMITIDO antiguo menos24h
    $f7 = $ahora->modify('+5 hours');
    $insertCita(
        'CIT3D07',
        'PROGRAMADA',
        $f7->format('Y-m-d'),
        $f7->format('H:i:s')
    );
    $pdo->prepare(
        "INSERT INTO correo_cita (
            ClvCita, ClvUsuDestino, TipoCorreo, RolDestinatario,
            FechaProgramada, EstadoCorreo, MotivoOmision
         ) VALUES (
            'CIT3D07', :u, 'RECORDATORIO_24H', 'PACIENTE',
            :fp, 'OMITIDO', :mot
         )"
    )->execute([
        'u' => $usuPac,
        'fp' => $f7->modify('-24 hours')->format('Y-m-d H:i:s'),
        'mot' => CorreoCitaService::MOTIVO_MENOS_24H
    ]);
    $antesRe = $contarNotif($usuPac, RecordatorioCitaService::TITULO_PACIENTE);
    $correoSvc->procesarLote(50);
    $assert(
        $contarNotif($usuPac, RecordatorioCitaService::TITULO_PACIENTE) === $antesRe + 1
        && $estadoCorreo('CIT3D07', 'PACIENTE') === 'ENVIADO',
        '17. Reactivación OMITIDO menos24h + notificación'
    );

    // OMITIDO de cita CANCELADA no se reactiva
    $f9 = $ahora->modify('+6 hours');
    $insertCita(
        'CIT3D09',
        'CANCELADA',
        $f9->format('Y-m-d'),
        $f9->format('H:i:s')
    );
    $pdo->prepare(
        "INSERT INTO correo_cita (
            ClvCita, ClvUsuDestino, TipoCorreo, RolDestinatario,
            FechaProgramada, EstadoCorreo, MotivoOmision
         ) VALUES (
            'CIT3D09', :u, 'RECORDATORIO_24H', 'PACIENTE',
            NOW(), 'OMITIDO', :mot
         )"
    )->execute([
        'u' => $usuPac,
        'mot' => CorreoCitaService::MOTIVO_MENOS_24H
    ]);
    $correoSvc->procesarLote(50);
    $assert(
        $estadoCorreo('CIT3D09', 'PACIENTE') === 'OMITIDO',
        '18. OMITIDO de CANCELADA no se reactiva'
    );

    // CONFIRMACION OMITIDO no se reactiva como recordatorio
    $f10 = $ahora->modify('+7 hours');
    $insertCita(
        'CIT3D10',
        'PROGRAMADA',
        $f10->format('Y-m-d'),
        $f10->format('H:i:s')
    );
    $pdo->prepare(
        "INSERT INTO correo_cita (
            ClvCita, ClvUsuDestino, TipoCorreo, RolDestinatario,
            FechaProgramada, EstadoCorreo, MotivoOmision
         ) VALUES (
            'CIT3D10', :u, 'CONFIRMACION', 'PACIENTE',
            NOW(), 'OMITIDO', :mot
         )"
    )->execute([
        'u' => $usuPac,
        'mot' => CorreoCitaService::MOTIVO_MENOS_24H
    ]);
    $antesConf = $contarNotif($usuPac, RecordatorioCitaService::TITULO_PACIENTE);
    (new CorreoCita())->reactivarRecordatoriosOmitidosMenosHoras(
        CorreoCitaService::MOTIVO_MENOS_24H,
        24
    );
    $estadoConf = (string) $pdo->query(
        "SELECT EstadoCorreo FROM correo_cita
         WHERE ClvCita = 'CIT3D10' AND TipoCorreo = 'CONFIRMACION' LIMIT 1"
    )->fetchColumn();
    $assert(
        $estadoConf === 'OMITIDO'
        && $contarNotif($usuPac, RecordatorioCitaService::TITULO_PACIENTE) === $antesConf,
        '19. CONFIRMACION no se reactiva como recordatorio'
    );

    // Zona America/Mexico_City
    $assert(
        $rec->zona()->getName() === 'America/Mexico_City',
        'Zona America/Mexico_City'
    );
} catch (Throwable $e) {
    $assert(false, 'Excepción no controlada', $e->getMessage());
} finally {
    $limpiar();
}

echo "=== Fase 3D recordatorios ===\n";
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
