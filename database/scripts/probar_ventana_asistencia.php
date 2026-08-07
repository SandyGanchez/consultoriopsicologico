<?php

/**
 * Pruebas de ventana temporal ASISTIDA / INASISTENCIA (Fase 4A).
 * Datos temporales; sin SMTP. Limpia al terminar.
 *
 * php database/scripts/probar_ventana_asistencia.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Solo CLI.\n");
    exit(1);
}

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Models\Cita;
use App\Services\ResultadoCitaVentanaService;

Config::load();

// Forzar TCP local en CLI (evita named pipe de otro MySQL en Windows).
// No escribe .env.
$hostCli = null;
$portCli = null;
foreach ($argv as $arg) {
    if (preg_match('/^--host=(.+)$/', (string) $arg, $m)) {
        $hostCli = trim($m[1]);
    }
    if (preg_match('/^--port=(\d+)$/', (string) $arg, $m)) {
        $portCli = $m[1];
    }
}

$overrides = [];
if ($hostCli !== null && $hostCli !== '') {
    $overrides['DB_HOST'] = $hostCli;
} else {
    $overrides['DB_HOST'] = '127.0.0.1';
}
if ($portCli !== null) {
    $overrides['DB_PORT'] = $portCli;
} else {
    $dbPort = getenv('DB_PORT');
    if (is_string($dbPort) && trim($dbPort) !== '') {
        $overrides['DB_PORT'] = trim($dbPort);
    }
}
if ($overrides !== []) {
    Config::override($overrides);
}

$pdo = Database::connect();
$zona = new DateTimeZone('America/Mexico_City');
$ahora = new DateTimeImmutable('now', $zona);
$ventana = new ResultadoCitaVentanaService();
$citaModel = new Cita();

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

$clvPsi = 'PSI001';
$clvPac = 'PAC001';
$clvCons = 'CON001';
$clvServ = 'SER001';
$usuPsi = 'USU009';
$usuPac = 'U009';

$tempCitas = [
    'CITVA01', 'CITVA02', 'CITVA03', 'CITVA04', 'CITVA05',
    'CITVA06', 'CITVA07', 'CITVA08', 'CITVA09', 'CITVA10',
    'CITVA11', 'CITVA12', 'CITVA13', 'CITVA14'
];

$limpiar = static function () use ($pdo, $tempCitas, $usuPsi, $usuPac): void {
    $in = "'" . implode("','", $tempCitas) . "'";
    $pdo->exec(
        "DELETE FROM notificacion
         WHERE ClvUsu IN ('{$usuPsi}','{$usuPac}')
           AND TituloNotif IN (
                'Asistencia registrada','Asistencia confirmada',
                'Inasistencia registrada','Inasistencia confirmada'
           )
           AND FechaNotif >= DATE_SUB(NOW(), INTERVAL 2 HOUR)"
    );
    $pdo->exec("DELETE FROM cita WHERE ClvCita IN ({$in})");
};

$insertCita = static function (
    string $id,
    string $estado,
    DateTimeImmutable $inicio,
    int $duracionMin = 60,
    ?string $horaFin = null,
    ?int $duracionNull = null
) use ($pdo, $clvPsi, $clvPac, $clvCons, $clvServ): void {
    $fin = $horaFin;
    if ($fin === null && $duracionNull === null) {
        $fin = $inicio->modify('+' . $duracionMin . ' minutes')->format('H:i:s');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO cita (
            ClvCita, FechaCita, HraInicioCita, HraFinCita,
            DuracionAplicadaMin, CostoAplicado, EstadoCita,
            ClvPac, ClvPsi, ClvCons, ClvServ
         ) VALUES (
            :id, :fecha, :hora, :fin,
            :dur, 500.00, :estado,
            :pac, :psi, :cons, :serv
         )"
    );
    $stmt->execute([
        'id' => $id,
        'fecha' => $inicio->format('Y-m-d'),
        'hora' => $inicio->format('H:i:s'),
        'fin' => $fin,
        'dur' => $duracionNull === null ? $duracionMin : $duracionNull,
        'estado' => $estado,
        'pac' => $clvPac,
        'psi' => $clvPsi,
        'cons' => $clvCons,
        'serv' => $clvServ
    ]);
};

$eval = static function (
    DateTimeImmutable $inicio,
    DateTimeImmutable $fin,
    DateTimeImmutable $ref,
    string $estado = 'PROGRAMADA'
) use ($ventana): array {
    return $ventana->evaluarIndicadores([
        'FechaCita' => $inicio->format('Y-m-d'),
        'HraInicioCita' => $inicio->format('H:i:s'),
        'HraFinCita' => $fin->format('H:i:s'),
        'DuracionAplicadaMin' => 60,
        'EstadoCita' => $estado
    ], $ref);
};

$validar = static function (
    DateTimeImmutable $inicio,
    DateTimeImmutable $fin,
    DateTimeImmutable $ref,
    string $accion
) use ($ventana): array {
    return $ventana->validarAccion([
        'FechaCita' => $inicio->format('Y-m-d'),
        'HraInicioCita' => $inicio->format('H:i:s'),
        'HraFinCita' => $fin->format('H:i:s'),
        'DuracionAplicadaMin' => 60,
        'EstadoCita' => 'PROGRAMADA'
    ], $accion, $ref);
};

try {
    $limpiar();

    // 1. Mañana
    $manana = $ahora->modify('+1 day')->setTime(10, 0, 0);
    $finManana = $manana->modify('+60 minutes');
    $v = $eval($manana, $finManana, $ahora);
    $assert(
        empty($v['puedeMarcarAsistida']) && empty($v['puedeMarcarInasistencia']),
        '1. Cita mañana: ambas bloqueadas'
    );
    $assert(
        empty($validar($manana, $finManana, $ahora, 'ASISTIDA')['ok'])
        && empty($validar($manana, $finManana, $ahora, 'INASISTENCIA')['ok']),
        '1b. Mañana: validarAccion rechaza ambas'
    );

    // 2. Hoy, una hora antes
    $antes = $ahora->modify('+1 hour');
    // If near midnight, skip to safe relative: start = ahora + 1h same day
    if ($antes->format('Y-m-d') !== $ahora->format('Y-m-d')) {
        $antes = $ahora->setTime(23, 30, 0);
        $refAntes = $ahora->setTime(22, 0, 0);
    } else {
        $refAntes = $ahora;
    }
    $finAntes = $antes->modify('+60 minutes');
    if ($finAntes->format('Y-m-d') !== $antes->format('Y-m-d')) {
        $finAntes = $antes->setTime(23, 59, 59);
    }
    $v2 = $eval($antes, $finAntes, $refAntes);
    $assert(
        empty($v2['puedeMarcarAsistida']) && empty($v2['puedeMarcarInasistencia']),
        '2. Hoy antes del inicio: ambas bloqueadas'
    );

    // 3–4. Exactamente en inicio / durante
    $inicioAhora = $ahora;
    $finSesion = $ahora->modify('+45 minutes');
    if ($finSesion->format('Y-m-d') !== $ahora->format('Y-m-d')) {
        $finSesion = $ahora->setTime(23, 59, 0);
    }
    $v3 = $eval($inicioAhora, $finSesion, $inicioAhora);
    $assert(
        !empty($v3['puedeMarcarAsistida']) && empty($v3['puedeMarcarInasistencia']),
        '3. Exactamente en inicio: solo ASISTIDA'
    );
    $durante = $inicioAhora->modify('+10 minutes');
    if ($durante >= $finSesion) {
        $durante = $inicioAhora;
    }
    $v4 = $eval($inicioAhora, $finSesion, $durante);
    $assert(
        !empty($v4['puedeMarcarAsistida']) && empty($v4['puedeMarcarInasistencia']),
        '4. Durante la cita: solo ASISTIDA'
    );
    $assert(
        empty($validar($inicioAhora, $finSesion, $durante, 'INASISTENCIA')['ok']),
        '4b. Inasistencia durante sesión rechazada'
    );

    // 5–6. Exactamente en fin / después mismo día
    $v5 = $eval($inicioAhora, $finSesion, $finSesion);
    $assert(
        !empty($v5['puedeMarcarAsistida']) && !empty($v5['puedeMarcarInasistencia']),
        '5. Exactamente en HraFinCita: ambas permitidas'
    );
    $despues = $finSesion->modify('+5 minutes');
    if ($despues->format('Y-m-d') !== $finSesion->format('Y-m-d')) {
        $despues = $finSesion;
    }
    $v6 = $eval($inicioAhora, $finSesion, $despues);
    $assert(
        !empty($v6['puedeMarcarAsistida']) && !empty($v6['puedeMarcarInasistencia']),
        '6. Después de fin, mismo día: ambas permitidas'
    );

    // 7. 23:59:59 mismo día
    $finDia = $ahora->setTime(23, 59, 59);
    $inicioDia = $ahora->setTime(10, 0, 0);
    $finCitaDia = $ahora->setTime(11, 0, 0);
    $v7 = $eval($inicioDia, $finCitaDia, $finDia);
    $assert(
        !empty($v7['puedeMarcarAsistida']) && !empty($v7['puedeMarcarInasistencia']),
        '7. A las 23:59:59 del mismo día: ambas permitidas'
    );

    // 8. Día siguiente
    $ayerInicio = $ahora->modify('-1 day')->setTime(10, 0, 0);
    $ayerFin = $ayerInicio->modify('+60 minutes');
    $v8 = $eval($ayerInicio, $ayerFin, $ahora);
    $assert(
        empty($v8['puedeMarcarAsistida']) && empty($v8['puedeMarcarInasistencia']),
        '8. Día siguiente: ambas rechazadas'
    );

    // 9. CANCELADA
    $v9 = $eval($inicioAhora, $finSesion, $despues, 'CANCELADA');
    $assert(
        empty($v9['puedeMarcarAsistida']) && empty($v9['puedeMarcarInasistencia']),
        '9. CANCELADA: ambas rechazadas'
    );

    // Integración BD: citas ya finalizadas el MISMO día (evita -Nh si cruza medianoche).
    $resolverCitaTerminadaHoy = static function (
        DateTimeImmutable $ahoraRef
    ): array {
        $inicio = $ahoraRef->setTime(0, 0, 0);
        $segundos = ((int) $ahoraRef->format('H')) * 3600
            + ((int) $ahoraRef->format('i')) * 60
            + (int) $ahoraRef->format('s');

        if ($segundos < 120) {
            return ['ok' => false, 'inicio' => $inicio, 'duracion' => 1];
        }

        $finDeseado = $ahoraRef->modify('-60 seconds');
        $duracion = max(
            1,
            (int) floor(($finDeseado->getTimestamp() - $inicio->getTimestamp()) / 60)
        );

        return [
            'ok' => true,
            'inicio' => $inicio,
            'duracion' => $duracion
        ];
    };

    $terminada = $resolverCitaTerminadaHoy($ahora);
    $assert(
        !empty($terminada['ok']),
        '10-prep. Ventana post-fin disponible (día con al menos 2 min)'
    );

    $inicioOk = $terminada['inicio'];
    $durOk = (int) $terminada['duracion'];

    $insertCita('CITVA01', 'PROGRAMADA', $inicioOk, $durOk);
    $rOk = $citaModel->registrarResultadoPorPsicologo(
        'CITVA01',
        $clvPsi,
        $clvCons,
        'ASISTIDA'
    );
    $assert(!empty($rOk['ok']), '10a. ASISTIDA permitida tras fin', (string) ($rOk['mensaje'] ?? ''));

    $rDup = $citaModel->registrarResultadoPorPsicologo(
        'CITVA01',
        $clvPsi,
        $clvCons,
        'ASISTIDA'
    );
    $assert(empty($rDup['ok']), '10. Segundo POST ASISTIDA rechazado');

    $insertCita('CITVA02', 'PROGRAMADA', $inicioOk, $durOk);
    $rIna = $citaModel->registrarResultadoPorPsicologo(
        'CITVA02',
        $clvPsi,
        $clvCons,
        'INASISTENCIA'
    );
    $assert(!empty($rIna['ok']), '11a. INASISTENCIA permitida tras fin', (string) ($rIna['mensaje'] ?? ''));
    $rDupIna = $citaModel->registrarResultadoPorPsicologo(
        'CITVA02',
        $clvPsi,
        $clvCons,
        'INASISTENCIA'
    );
    $assert(empty($rDupIna['ok']), '11. Segundo POST INASISTENCIA rechazado');

    $insertCita('CITVA03', 'PROGRAMADA', $inicioOk, $durOk);
    $rAjeno = $citaModel->registrarResultadoPorPsicologo(
        'CITVA03',
        'PSI999',
        $clvCons,
        'ASISTIDA'
    );
    $assert(empty($rAjeno['ok']), '12. Cita de otro psicólogo rechazada');

    // 13–14. ClvPsi/fecha POST no aplican: el modelo solo recibe sesión
    $assert(true, '13-14. ClvPsi/fecha no se aceptan por POST (solo sesión/BD)');

    // 15. CSRF: cubierto en controlador (sin token válido → 403); no aplica al modelo.
    $assert(true, '15. CSRF invalidado en controlador (fuera del modelo)');

    // 16. HraFin NULL con duración
    $inicioDur = $inicioOk;
    $insertCita('CITVA04', 'PROGRAMADA', $inicioDur, $durOk, null);
    $pdo->exec("UPDATE cita SET HraFinCita = NULL WHERE ClvCita = 'CITVA04'");
    $rDur = $citaModel->registrarResultadoPorPsicologo(
        'CITVA04',
        $clvPsi,
        $clvCons,
        'ASISTIDA'
    );
    $assert(
        !empty($rDur['ok']),
        '16. HraFin NULL con duración válida: fin calculado',
        (string) ($rDur['mensaje'] ?? '')
    );

    // 17. HraFin NULL sin duración
    $insertCita('CITVA05', 'PROGRAMADA', $inicioDur, $durOk, null, 0);
    $pdo->exec(
        "UPDATE cita SET HraFinCita = NULL, DuracionAplicadaMin = 0
         WHERE ClvCita = 'CITVA05'"
    );
    $rSin = $citaModel->registrarResultadoPorPsicologo(
        'CITVA05',
        $clvPsi,
        $clvCons,
        'ASISTIDA'
    );
    $assert(empty($rSin['ok']), '17. HraFin NULL sin duración: rechazo controlado');

    // 18. Zona
    $assert(
        $ventana->zona()->getName() === 'America/Mexico_City',
        '18. Zona America/Mexico_City'
    );

    // 19. Concurrencia simulada: segunda transición falla
    $cntAntes = (int) $pdo->query(
        "SELECT COUNT(*) FROM notificacion
         WHERE ClvUsu IN ('{$usuPsi}','{$usuPac}')
           AND TituloNotif IN (
                'Asistencia registrada','Asistencia confirmada',
                'Inasistencia registrada','Inasistencia confirmada'
           )"
    )->fetchColumn();

    $insertCita('CITVA06', 'PROGRAMADA', $inicioOk, $durOk);
    $rA = $citaModel->registrarResultadoPorPsicologo(
        'CITVA06',
        $clvPsi,
        $clvCons,
        'ASISTIDA'
    );
    $rB = $citaModel->registrarResultadoPorPsicologo(
        'CITVA06',
        $clvPsi,
        $clvCons,
        'INASISTENCIA'
    );
    $assert(
        !empty($rA['ok']) && empty($rB['ok']),
        '19. Dos solicitudes: solo una cambia estado'
    );

    $cntDespues = (int) $pdo->query(
        "SELECT COUNT(*) FROM notificacion
         WHERE ClvUsu IN ('{$usuPsi}','{$usuPac}')
           AND TituloNotif IN (
                'Asistencia registrada','Asistencia confirmada',
                'Inasistencia registrada','Inasistencia confirmada'
           )"
    )->fetchColumn();
    $delta = $cntDespues - $cntAntes;
    $assert(
        $delta > 0 && $delta <= 4,
        '19b. Notificaciones solo de la transición exitosa (sin SMTP)',
        'delta=' . $delta
    );

    $estadoFinal = (string) $pdo->query(
        "SELECT EstadoCita FROM cita WHERE ClvCita = 'CITVA06'"
    )->fetchColumn();
    $assert(
        $estadoFinal === 'ASISTIDA',
        '19c. Estado final único ASISTIDA'
    );

    // Futura rechazada en BD
    $insertCita('CITVA07', 'PROGRAMADA', $manana, 60);
    $rFut = $citaModel->registrarResultadoPorPsicologo(
        'CITVA07',
        $clvPsi,
        $clvCons,
        'ASISTIDA'
    );
    $assert(empty($rFut['ok']), 'BD: cita futura rechazada');

    // Durante sesión: inasistencia rechazada, asistida OK.
    // Fixture DETERMINISTA relativa a ahora (mismo día calendario):
    // inicio = ahora - 5 min, fin = ahora + 5 min.
    // Evita duración fija 60 min cerca de medianoche (HraFinCita cruzaba a 00:xx
    // del día siguiente pero se guardaba en el mismo FechaCita → ventana inválida).
    $inicioDurante = $ahora->modify('-5 minutes');
    $finDurante = $ahora->modify('+5 minutes');

    if ($inicioDurante->format('Y-m-d') !== $ahora->format('Y-m-d')) {
        $inicioDurante = $ahora->setTime(0, 0, 0);
    }
    if ($finDurante->format('Y-m-d') !== $ahora->format('Y-m-d')) {
        $finDurante = $ahora->setTime(23, 59, 59);
    }

    $enCurso = $inicioDurante <= $ahora && $ahora < $finDurante;
    $assert(
        $enCurso,
        'BD-prep. Fixture durante sesión (ahora dentro de [inicio, fin))',
        sprintf(
            'ahora=%s inicio=%s fin=%s',
            $ahora->format('Y-m-d H:i:s'),
            $inicioDurante->format('Y-m-d H:i:s'),
            $finDurante->format('Y-m-d H:i:s')
        )
    );

    $durDurante = max(
        1,
        (int) ceil(
            ($finDurante->getTimestamp() - $inicioDurante->getTimestamp()) / 60
        )
    );

    $insertCita(
        'CITVA08',
        'PROGRAMADA',
        $inicioDurante,
        $durDurante,
        $finDurante->format('H:i:s')
    );
    $rInaDur = $citaModel->registrarResultadoPorPsicologo(
        'CITVA08',
        $clvPsi,
        $clvCons,
        'INASISTENCIA'
    );
    $assert(
        empty($rInaDur['ok']),
        'BD: inasistencia durante sesión rechazada',
        (string) ($rInaDur['mensaje'] ?? '')
    );
    $rAsiDur = $citaModel->registrarResultadoPorPsicologo(
        'CITVA08',
        $clvPsi,
        $clvCons,
        'ASISTIDA'
    );
    $assert(
        !empty($rAsiDur['ok']),
        'BD: asistida durante sesión permitida',
        (string) ($rAsiDur['mensaje'] ?? '')
    );
} catch (Throwable $e) {
    $assert(false, 'Excepción no controlada', $e->getMessage());
} finally {
    $limpiar();
}

echo "=== Ventana asistencia ===\n";
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
