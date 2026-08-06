<?php

/**
 * Pruebas funcionales Fase 2 (local, sin SMTP).
 * Crea citas temporales, verifica y limpia.
 *
 * php database/scripts/probar_fase2_agenda.php
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Config\Config;
use App\Config\Database;
use App\Models\Cita;
use App\Models\Notificacion;
use App\Services\EstadoCitaPresentacion;
use App\Services\NotificacionService;

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

$clvPsi = 'PSI001';
$clvPac = 'PAC001';
$clvCons = 'CON001';
$clvServ = 'SER001';
$usuPsi = 'USU009';
$usuPac = 'U009';
$usuCons = 'USU001';
$usuAdmin = 'U008';

$tempCitas = ['CIT901', 'CIT902', 'CIT903', 'CIT904'];

$limpiar = static function () use ($pdo, $tempCitas, $usuPsi, $usuPac, $usuCons): void {
    $in = "'" . implode("','", $tempCitas) . "'";
    $pdo->exec("DELETE FROM notificacion WHERE TituloNotif IN (
        'Asistencia registrada','Asistencia confirmada',
        'Inasistencia registrada','Inasistencia confirmada'
    ) AND FechaNotif >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
      AND ClvUsu IN ('{$usuPsi}','{$usuPac}','{$usuCons}')");
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

$estadoCita = static function (string $clv) use ($pdo): string {
    $stmt = $pdo->prepare('SELECT EstadoCita FROM cita WHERE ClvCita = :id LIMIT 1');
    $stmt->execute(['id' => $clv]);
    return strtoupper(trim((string) $stmt->fetchColumn()));
};

$contarTitulo = static function (
    string $clvUsu,
    string $titulo
) use ($pdo): int {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM notificacion
         WHERE ClvUsu = :u AND TituloNotif = :t
           AND FechaNotif >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
    $stmt->execute(['u' => $clvUsu, 't' => $titulo]);
    return (int) $stmt->fetchColumn();
};

$mensajeTieneRef = static function (string $clvUsu, string $titulo) use ($pdo): bool {
    $stmt = $pdo->prepare(
        "SELECT MensajeNotif FROM notificacion
         WHERE ClvUsu = :u AND TituloNotif = :t
           AND FechaNotif >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
         ORDER BY FechaNotif DESC LIMIT 5"
    );
    $stmt->execute(['u' => $clvUsu, 't' => $titulo]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $m = (string) ($fila['MensajeNotif'] ?? '');
        if (str_contains($m, '⟦ref:') || str_contains($m, 'ref:CIT')) {
            return true;
        }
    }
    return false;
};

try {
    $limpiar();

    $pasado = (new DateTimeImmutable('yesterday', new DateTimeZone('America/Mexico_City')))
        ->format('Y-m-d');
    $futuro = (new DateTimeImmutable('+2 days', new DateTimeZone('America/Mexico_City')))
        ->format('Y-m-d');

    $insertCita('CIT901', 'PROGRAMADA', $pasado, '10:00:00');
    $insertCita('CIT902', 'PROGRAMADA', $pasado, '11:00:00');
    $insertCita('CIT903', 'PROGRAMADA', $futuro, '12:00:00');
    $insertCita('CIT904', 'CANCELADA', $pasado, '13:00:00');

    $citaModel = new Cita();
    $antesAdmin = $contarTitulo($usuAdmin, 'Asistencia registrada')
        + $contarTitulo($usuAdmin, 'Inasistencia registrada');

    // 1. ASISTIDA correcta
    $r1 = $citaModel->registrarResultadoPorPsicologo(
        'CIT901',
        $clvPsi,
        $clvCons,
        'ASISTIDA'
    );
    $assert(
        !empty($r1['ok']) && ($r1['estado'] ?? '') === 'ASISTIDA',
        '1. ASISTIDA correcta',
        (string) ($r1['mensaje'] ?? '')
    );
    $assert($estadoCita('CIT901') === 'ASISTIDA', '1b. Estado ASISTIDA persistido');

    // 9-11 notificaciones únicas
    $nPac = $contarTitulo($usuPac, 'Asistencia registrada');
    $nPsi = $contarTitulo($usuPsi, 'Asistencia confirmada');
    $nCons = $contarTitulo($usuCons, 'Asistencia registrada');
    $assert($nPac === 1, '9. Notificación paciente única', (string) $nPac);
    $assert($nPsi === 1, '10. Notificación psicólogo única', (string) $nPsi);
    $assert($nCons >= 1, '11. Notificación consultorio creada', (string) $nCons);

    // 13. Sin marcador técnico
    $assert(
        !$mensajeTieneRef($usuPac, 'Asistencia registrada')
        && !$mensajeTieneRef($usuPsi, 'Asistencia confirmada'),
        '13. Mensajes sin marcador técnico'
    );

    // 12. ADMIN sin aviso
    $despuesAdmin = $contarTitulo($usuAdmin, 'Asistencia registrada')
        + $contarTitulo($usuAdmin, 'Inasistencia registrada');
    $assert($despuesAdmin === $antesAdmin, '12. ADMINISTRADOR sin notificación');

    // 3. Segundo POST no duplica
    $r2 = $citaModel->registrarResultadoPorPsicologo(
        'CIT901',
        $clvPsi,
        $clvCons,
        'ASISTIDA'
    );
    $assert(
        empty($r2['ok'])
        && str_contains((string) ($r2['mensaje'] ?? ''), 'ya fue registrada'),
        '3. Segundo POST no duplica',
        (string) ($r2['mensaje'] ?? '')
    );
    $assert(
        $contarTitulo($usuPac, 'Asistencia registrada') === 1
        && $contarTitulo($usuPsi, 'Asistencia confirmada') === 1,
        '3b. Segundo POST no crea notificaciones'
    );

    // 2. INASISTENCIA correcta
    $r3 = $citaModel->registrarResultadoPorPsicologo(
        'CIT902',
        $clvPsi,
        $clvCons,
        'INASISTENCIA'
    );
    $assert(
        !empty($r3['ok']) && $estadoCita('CIT902') === 'INASISTENCIA',
        '2. INASISTENCIA correcta',
        (string) ($r3['mensaje'] ?? '')
    );
    $assert(
        $contarTitulo($usuPac, 'Inasistencia registrada') === 1
        && $contarTitulo($usuPsi, 'Inasistencia confirmada') === 1,
        '2b. Notificaciones INASISTENCIA'
    );

    // Segundo POST inasistencia
    $r3b = $citaModel->registrarResultadoPorPsicologo(
        'CIT902',
        $clvPsi,
        $clvCons,
        'INASISTENCIA'
    );
    $assert(
        empty($r3b['ok'])
        && $contarTitulo($usuPac, 'Inasistencia registrada') === 1,
        '3c. Segundo POST INASISTENCIA no duplica'
    );

    // 4. Fallo notificación → rollback
    $insertCita('CIT905', 'PROGRAMADA', $pasado, '14:00:00');
    $tempCitas[] = 'CIT905';
    $servicioFallo = new class extends NotificacionService {
        public function notificarResultadoCita(
            string $clvCita,
            string $resultado
        ): void {
            throw new RuntimeException('Fallo controlado de notificación.');
        }
    };
    $r4 = $citaModel->registrarResultadoPorPsicologo(
        'CIT905',
        $clvPsi,
        $clvCons,
        'ASISTIDA',
        $servicioFallo
    );
    $assert(
        empty($r4['ok']) && $estadoCita('CIT905') === 'PROGRAMADA',
        '4. Fallo notificación hace ROLLBACK',
        (string) ($r4['mensaje'] ?? '') . ' estado=' . $estadoCita('CIT905')
    );

    // 5. Cita futura rechazada
    $r5 = $citaModel->registrarResultadoPorPsicologo(
        'CIT903',
        $clvPsi,
        $clvCons,
        'ASISTIDA'
    );
    $assert(
        empty($r5['ok'])
        && ($r5['codigo'] ?? '') === 'CITA_NO_INICIADA'
        && $estadoCita('CIT903') === 'PROGRAMADA',
        '5. Cita futura rechazada',
        (string) ($r5['mensaje'] ?? '')
    );

    // 6. Cita ajena rechazada
    $r6 = $citaModel->registrarResultadoPorPsicologo(
        'CIT903',
        'PSI999',
        $clvCons,
        'ASISTIDA'
    );
    $assert(
        empty($r6['ok'])
        && in_array(($r6['codigo'] ?? ''), ['SIN_AUTORIZACION', 'CITA_NO_ENCONTRADA', 'DATOS_INVALIDOS'], true)
        && $estadoCita('CIT903') === 'PROGRAMADA',
        '6. Cita ajena rechazada',
        (string) (($r6['codigo'] ?? '') . ' ' . ($r6['mensaje'] ?? ''))
    );

    // 7. CANCELADA rechazada
    $r7 = $citaModel->registrarResultadoPorPsicologo(
        'CIT904',
        $clvPsi,
        $clvCons,
        'ASISTIDA'
    );
    $assert(
        empty($r7['ok']) && $estadoCita('CIT904') === 'CANCELADA',
        '7. CANCELADA rechazada',
        (string) ($r7['mensaje'] ?? '')
    );

    // 8. Estado arbitrario
    $r8 = $citaModel->registrarResultadoPorPsicologo(
        'CIT903',
        $clvPsi,
        $clvCons,
        'HACK'
    );
    $assert(
        empty($r8['ok']) && ($r8['codigo'] ?? '') === 'ACCION_INVALIDA',
        '8. Estado arbitrario rechazado'
    );

    // 14. Mensajes sin datos clínicos
    $stmt = $pdo->prepare(
        "SELECT MensajeNotif FROM notificacion
         WHERE ClvUsu IN (:a,:b) AND FechaNotif >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
    $stmt->execute(['a' => $usuPac, 'b' => $usuPsi]);
    $clinico = false;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $m = mb_strtolower((string) ($fila['MensajeNotif'] ?? ''));
        if (
            str_contains($m, 'diagn')
            || str_contains($m, 'historia clínica')
            || str_contains($m, 'antecedente')
        ) {
            $clinico = true;
        }
    }
    $assert(!$clinico, '14. Mensajes sin datos clínicos');

    // 15-16 filtros
    $assert(EstadoCitaPresentacion::esValido('ASISTIDA'), '15. Filtro válido ASISTIDA');
    $assert(!EstadoCitaPresentacion::esValido('INVALIDO'), '16. Filtro inválido controlado');
    $hist = $citaModel->obtenerHistorial($clvPac, 'ASISTIDA', 1, 10);
    $assert(is_array($hist), '15b. Historial filtro ASISTIDA responde');
    $histBad = $citaModel->obtenerHistorial($clvPac, 'INVALIDO', 1, 10);
    $assert(is_array($histBad), '16b. Estado inválido no rompe consulta');
    $histRango = $citaModel->contarHistorial($clvPac, null, 'texto', 'texto');
    $assert($histRango === 0, '16c. Rango inválido controlado');
} catch (Throwable $e) {
    $assert(false, 'EXCEPCIÓN NO CONTROLADA', $e->getMessage());
} finally {
    $limpiar();
    $pdo->exec("DELETE FROM cita WHERE ClvCita = 'CIT905'");
}

echo "Pruebas Fase 2 (cierre)\n\n";
foreach ($casos as $caso) {
    echo ($caso['ok'] ? '[OK] ' : '[FAIL] ')
        . $caso['nombre']
        . ($caso['detalle'] !== '' ? ' — ' . $caso['detalle'] : '')
        . "\n";
}
echo "\nTotal: " . count($casos) . " | Fallos: {$fallos}\n";
exit($fallos > 0 ? 1 : 0);
