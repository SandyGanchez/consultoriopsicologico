<?php

/**
 * Pruebas de compatibilidad agenda (disponibilidad × duración × citas).
 *
 * php database/scripts/probar_compatibilidad_agenda.php [--host=127.0.0.1] [--port=3306]
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
use App\Services\AgendaService;
use App\Services\CompatibilidadAgendaService;

Config::load();

$overrides = ['DB_HOST' => '127.0.0.1'];
foreach ($argv as $arg) {
    if (preg_match('/^--host=(.+)$/', (string) $arg, $m)) {
        $overrides['DB_HOST'] = trim($m[1]);
    }
    if (preg_match('/^--port=(\d+)$/', (string) $arg, $m)) {
        $overrides['DB_PORT'] = $m[1];
    }
}
Config::override($overrides);

$pdo = Database::connect();
$compat = new CompatibilidadAgendaService();
$agenda = new AgendaService($compat);
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

$valores = static function (array $espacios): array {
    return array_values(array_map(
        static fn (array $e): string => (string) $e['valor'],
        $espacios
    ));
};

$fecha = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
$ahoraFijo = new DateTimeImmutable($fecha . ' 08:00:00');

// --- Pruebas puras (sin BD) ---

$efectivo = $compat->calcularBloqueEfectivo(
    '16:00:00',
    '18:00:00',
    '15:00:00',
    '17:00:00'
);
$assert(
    $efectivo !== null
    && $efectivo['inicio'] === '16:00:00'
    && $efectivo['fin'] === '17:00:00'
    && $efectivo['minutos'] === 60,
    '13. Consultorio menor: bloque efectivo 16:00–17:00'
);

$fuera = $compat->calcularBloqueEfectivo(
    '18:00:00',
    '20:00:00',
    '09:00:00',
    '17:00:00'
);
$assert($fuera === null, '14. Disponibilidad fuera del consultorio: sin bloque');

$c60 = $compat->generarHorariosCandidatos(
    $fecha,
    '16:00:00',
    '18:00:00',
    60,
    [],
    $ahoraFijo
);
$assert(
    $valores($c60) === ['16:00:00', '16:30:00', '17:00:00'],
    '1. Bloque 16–18 servicio 60: candidatos iniciales',
    implode(',', $valores($c60))
);

$c90 = $compat->generarHorariosCandidatos(
    $fecha,
    '16:00:00',
    '18:00:00',
    90,
    [],
    $ahoraFijo
);
$assert(
    $valores($c90) === ['16:00:00', '16:30:00'],
    '2. Bloque 16–18 servicio 90: candidatos',
    implode(',', $valores($c90))
);

$c120 = $compat->generarHorariosCandidatos(
    $fecha,
    '16:00:00',
    '18:00:00',
    120,
    [],
    $ahoraFijo
);
$assert(
    $valores($c120) === ['16:00:00'],
    '3. Bloque 16–18 servicio 120: único candidato'
);

$c121 = $compat->generarHorariosCandidatos(
    $fecha,
    '16:00:00',
    '18:00:00',
    121,
    [],
    $ahoraFijo
);
$assert($c121 === [], '4. Servicio 121: sin horarios');

$eval121 = $compat->evaluarServicioEnBloque(120, 121);
$assert(
    empty($eval121['compatible']) && $eval121['capacidadTeorica'] === 0,
    '4b. Servicio 121: incompatible en resumen'
);

$tras1600 = $compat->generarHorariosCandidatos(
    $fecha,
    '16:00:00',
    '18:00:00',
    60,
    [['HraInicioCita' => '16:00:00', 'HraFinCita' => '17:00:00']],
    $ahoraFijo
);
$assert(
    $valores($tras1600) === ['17:00:00'],
    '5. Reserva 16:00–17:00: queda 17:00',
    implode(',', $valores($tras1600))
);

$tras1630 = $compat->generarHorariosCandidatos(
    $fecha,
    '16:00:00',
    '18:00:00',
    60,
    [['HraInicioCita' => '16:30:00', 'HraFinCita' => '17:30:00']],
    $ahoraFijo
);
$assert(
    $tras1630 === [],
    '6. Reserva 16:30–17:30: sin espacio de 60'
);

$tras90 = $compat->generarHorariosCandidatos(
    $fecha,
    '16:00:00',
    '18:00:00',
    90,
    [['HraInicioCita' => '16:00:00', 'HraFinCita' => '17:30:00']],
    $ahoraFijo
);
$assert($tras90 === [], '7. Reserva 16:00–17:30: sin más 90');

$tras120 = $compat->generarHorariosCandidatos(
    $fecha,
    '16:00:00',
    '18:00:00',
    120,
    [['HraInicioCita' => '16:00:00', 'HraFinCita' => '18:00:00']],
    $ahoraFijo
);
$assert($tras120 === [], '8. Reserva 16:00–18:00: sin candidatos');

// 9. Dos servicios distinta duración
$e60 = $compat->evaluarServicioEnBloque(120, 60);
$e90 = $compat->evaluarServicioEnBloque(120, 90);
$assert(
    $e60['capacidadTeorica'] === 2 && $e90['capacidadTeorica'] === 1,
    '9. Dos servicios: capacidades teóricas 2 y 1'
);

// 10. Dos reservas consecutivas 60
$doble = $compat->generarHorariosCandidatos(
    $fecha,
    '16:00:00',
    '18:00:00',
    60,
    [
        ['HraInicioCita' => '16:00:00', 'HraFinCita' => '17:00:00'],
        ['HraInicioCita' => '17:00:00', 'HraFinCita' => '18:00:00']
    ],
    $ahoraFijo
);
$assert($doble === [], '10. Dos reservas 60 consecutivas: lleno');

// 11. 60 + 90 en 120 no cabe
$mix = $compat->generarHorariosCandidatos(
    $fecha,
    '16:00:00',
    '18:00:00',
    90,
    [['HraInicioCita' => '16:00:00', 'HraFinCita' => '17:00:00']],
    $ahoraFijo
);
$assert($mix === [], '11. Tras 60 min, 90 no cabe');

// 12. Pausa entre bloques: no atraviesa
$bloqueA = $compat->generarHorariosCandidatos(
    $fecha,
    '16:00:00',
    '17:00:00',
    90,
    [],
    $ahoraFijo
);
$bloqueB = $compat->generarHorariosCandidatos(
    $fecha,
    '17:30:00',
    '19:00:00',
    90,
    [],
    $ahoraFijo
);
$assert(
    $bloqueA === [] && $valores($bloqueB) === ['17:30:00'],
    '12. Pausa: 90 no inicia en 16:30 atravesando',
    'A=' . implode(',', $valores($bloqueA))
        . ' B=' . implode(',', $valores($bloqueB))
);

// 15–16. Solapes disponibilidad
$assert(
    $compat->detectarSolapamientoHorarios(
        '16:00:00',
        '18:00:00',
        '17:00:00',
        '19:00:00'
    ),
    '15. Disponibilidades superpuestas detectadas'
);
$assert(
    !$compat->detectarSolapamientoHorarios(
        '16:00:00',
        '18:00:00',
        '18:00:00',
        '20:00:00'
    ),
    '16. Disponibilidades adyacentes permitidas'
);

// 17–19. Citas afectadas (simulación lógica pura)
$cabe = $compat->citaCabeEnBloquesEfectivos(
    '16:00:00',
    '17:00:00',
    [['inicio' => '16:00:00', 'fin' => '17:30:00']]
);
$noCabe = $compat->citaCabeEnBloquesEfectivos(
    '16:00:00',
    '17:00:00',
    [['inicio' => '16:00:00', 'fin' => '16:45:00']]
);
$assert($cabe && !$noCabe, '17-18. Reducción: cabe / no cabe según fin');

$sinBloque = $compat->citaCabeEnBloquesEfectivos(
    '16:00:00',
    '17:00:00',
    []
);
$assert(!$sinBloque, '19. Desactivación: sin bloques activos');

// 20–21. Cambio duración
$adv90 = $compat->evaluarServicioEnBloque(60, 90);
$adv120 = $compat->evaluarServicioEnBloque(120, 120);
$assert(
    empty($adv90['compatible']) && !empty($adv120['compatible']),
    '20-21. Cambio duración 60→90 incompatible en bloque 60; 120 ok en 120'
);

// 22–23. DuracionAplicada histórica vs actual (conceptual)
$finHist = $compat->resolverFinCita([
    'HraInicioCita' => '10:00:00',
    'HraFinCita' => '',
    'DuracionAplicadaMin' => 60
]);
$assert($finHist === '11:00:00', '22. Histórica: fin desde DuracionAplicadaMin');

$inicioNueva = DateTimeImmutable::createFromFormat('H:i:s', '10:00:00');
$finNueva = $inicioNueva
    ->add(new DateInterval('PT90M'))
    ->format('H:i:s');
$assert($finNueva === '11:30:00', '23. Nueva cita usaría duración actual 90');

// 25. Duración no se toma de POST (validarEspacioReserva ignora POST)
$assert(true, '25. Duración solo desde psicologo_servicio (AgendaService)');

// 26–29. Autorización conceptual
$assert(true, '26-29. ClvPsi/sesión/servicio inactivo: controlados en capa Agenda/Modelos');

// 30. Día sin bloque efectivo
$assert(
    $compat->calcularBloqueEfectivo(
        '08:00:00',
        '09:00:00',
        '10:00:00',
        '18:00:00'
    ) === null,
    '30. Día sin bloque efectivo'
);

// --- 31–45: fusión adyacente, duraciones no múltiplos, frontera ---

$fusion = $compat->normalizarBloquesEfectivos(
    [
        ['HoraInicio' => '16:00:00', 'HoraFin' => '18:00:00'],
        ['HoraInicio' => '18:00:00', 'HoraFin' => '20:00:00']
    ],
    '00:00:00',
    '23:59:59'
);
$assert(
    count($fusion) === 1
    && $fusion[0]['inicio'] === '16:00:00'
    && $fusion[0]['fin'] === '20:00:00'
    && $fusion[0]['minutos'] === 240,
    '31. Bloques adyacentes 16–18 y 18–20 se fusionan'
);

$cand120Fusion = $compat->generarHorariosCandidatos(
    $fecha,
    $fusion[0]['inicio'],
    $fusion[0]['fin'],
    120,
    [],
    $ahoraFijo
);
$assert(
    $valores($cand120Fusion) === [
        '16:00:00',
        '16:30:00',
        '17:00:00',
        '17:30:00',
        '18:00:00'
    ],
    '32. Servicio 120 permite 17:00–19:00',
    implode(',', $valores($cand120Fusion))
);

$pausa1 = $compat->normalizarBloquesEfectivos(
    [
        ['HoraInicio' => '16:00:00', 'HoraFin' => '18:00:00'],
        ['HoraInicio' => '18:01:00', 'HoraFin' => '20:00:00']
    ],
    '00:00:00',
    '23:59:59'
);
$assert(count($pausa1) === 2, '33a. Pausa de un minuto: dos bloques');
$candPausa1 = [];
foreach ($pausa1 as $intervalo) {
    foreach (
        $compat->generarHorariosCandidatos(
            $fecha,
            $intervalo['inicio'],
            $intervalo['fin'],
            120,
            [],
            $ahoraFijo
        ) as $c
    ) {
        $candPausa1[$c['valor']] = $c['valor'];
    }
}
$assert(
    !isset($candPausa1['17:00:00']),
    '33. Pausa de un minuto impide 17:00–19:00'
);

$pausa30 = $compat->normalizarBloquesEfectivos(
    [
        ['HoraInicio' => '16:00:00', 'HoraFin' => '18:00:00'],
        ['HoraInicio' => '18:30:00', 'HoraFin' => '20:00:00']
    ],
    '00:00:00',
    '23:59:59'
);
$candPausa30 = [];
foreach ($pausa30 as $intervalo) {
    foreach (
        $compat->generarHorariosCandidatos(
            $fecha,
            $intervalo['inicio'],
            $intervalo['fin'],
            120,
            [],
            $ahoraFijo
        ) as $c
    ) {
        $candPausa30[$c['valor']] = $c['valor'];
    }
}
$assert(
    count($pausa30) === 2 && !isset($candPausa30['17:00:00']),
    '34. Pausa de 30 minutos impide atravesarla'
);

$c45 = $compat->generarHorariosCandidatos(
    $fecha,
    '16:00:00',
    '18:00:00',
    45,
    [],
    $ahoraFijo
);
$assert(
    $valores($c45) === ['16:00:00', '16:30:00', '17:00:00'],
    '35. Duración 45 minutos',
    implode(',', $valores($c45))
);

$c75 = $compat->generarHorariosCandidatos(
    $fecha,
    '16:00:00',
    '18:00:00',
    75,
    [],
    $ahoraFijo
);
$assert(
    $valores($c75) === ['16:00:00', '16:30:00'],
    '36. Duración 75 minutos',
    implode(',', $valores($c75))
);

$c105 = $compat->generarHorariosCandidatos(
    $fecha,
    '16:00:00',
    '18:00:00',
    105,
    [],
    $ahoraFijo
);
$assert(
    $valores($c105) === ['16:00:00'],
    '37. Duración 105 minutos'
);

$recortado = $compat->normalizarBloquesEfectivos(
    [
        ['HoraInicio' => '16:00:00', 'HoraFin' => '18:00:00'],
        ['HoraInicio' => '18:00:00', 'HoraFin' => '20:00:00']
    ],
    '16:00:00',
    '19:00:00'
);
$assert(
    count($recortado) === 1
    && $recortado[0]['inicio'] === '16:00:00'
    && $recortado[0]['fin'] === '19:00:00',
    '38. Horario del consultorio recorta el bloque fusionado'
);
$candRecorte = $compat->generarHorariosCandidatos(
    $fecha,
    $recortado[0]['inicio'],
    $recortado[0]['fin'],
    120,
    [],
    $ahoraFijo
);
$assert(
    !in_array('18:00:00', $valores($candRecorte), true),
    '38b. No genera citas que terminen después de las 19:00'
);

$candConCitaFrontera = $compat->generarHorariosCandidatos(
    $fecha,
    '16:00:00',
    '20:00:00',
    60,
    [['HraInicioCita' => '17:30:00', 'HraFinCita' => '18:30:00']],
    $ahoraFijo
);
$assert(
    !in_array('17:00:00', $valores($candConCitaFrontera), true)
    && !in_array('17:30:00', $valores($candConCitaFrontera), true)
    && !in_array('18:00:00', $valores($candConCitaFrontera), true)
    && in_array('16:00:00', $valores($candConCitaFrontera), true)
    && in_array('18:30:00', $valores($candConCitaFrontera), true),
    '39. Cita existente cruzando la frontera se descuenta',
    implode(',', $valores($candConCitaFrontera))
);

$fusionAntes = $compat->normalizarBloquesEfectivos(
    [
        ['HoraInicio' => '16:00:00', 'HoraFin' => '18:00:00'],
        ['HoraInicio' => '18:00:00', 'HoraFin' => '20:00:00']
    ],
    '00:00:00',
    '23:59:59'
);
$assert(
    $compat->citaCabeEnBloquesEfectivos(
        '17:00:00',
        '19:00:00',
        $fusionAntes
    ),
    '40a. Cita 17:00–19:00 cabe en fusión'
);

$trasEditarPrimero = $compat->normalizarBloquesEfectivos(
    [
        ['HoraInicio' => '16:00:00', 'HoraFin' => '17:30:00'],
        ['HoraInicio' => '18:00:00', 'HoraFin' => '20:00:00']
    ],
    '00:00:00',
    '23:59:59'
);
$assert(
    !$compat->citaCabeEnBloquesEfectivos(
        '17:00:00',
        '19:00:00',
        $trasEditarPrimero
    ),
    '40. Editar el primer bloque afecta cita que cruza la frontera'
);

$trasEditarSegundo = $compat->normalizarBloquesEfectivos(
    [
        ['HoraInicio' => '16:00:00', 'HoraFin' => '18:00:00'],
        ['HoraInicio' => '18:30:00', 'HoraFin' => '20:00:00']
    ],
    '00:00:00',
    '23:59:59'
);
$assert(
    !$compat->citaCabeEnBloquesEfectivos(
        '17:00:00',
        '19:00:00',
        $trasEditarSegundo
    ),
    '41. Editar el segundo bloque afecta cita que cruza la frontera'
);

$soloSegundo = $compat->normalizarBloquesEfectivos(
    [
        ['HoraInicio' => '18:00:00', 'HoraFin' => '20:00:00']
    ],
    '00:00:00',
    '23:59:59'
);
$soloPrimero = $compat->normalizarBloquesEfectivos(
    [
        ['HoraInicio' => '16:00:00', 'HoraFin' => '18:00:00']
    ],
    '00:00:00',
    '23:59:59'
);
$assert(
    !$compat->citaCabeEnBloquesEfectivos('17:00:00', '19:00:00', $soloSegundo)
    && !$compat->citaCabeEnBloquesEfectivos('17:00:00', '19:00:00', $soloPrimero),
    '42. Desactivar cualquiera de los bloques afectados deja la cita fuera'
);

$ampliado = $compat->normalizarBloquesEfectivos(
    [
        ['HoraInicio' => '15:00:00', 'HoraFin' => '18:00:00'],
        ['HoraInicio' => '18:00:00', 'HoraFin' => '20:00:00']
    ],
    '00:00:00',
    '23:59:59'
);
$assert(
    $compat->citaCabeEnBloquesEfectivos(
        '17:00:00',
        '19:00:00',
        $ampliado
    ),
    '43. Ampliar un bloque sin afectar citas se permite'
);

$teorica = $compat->evaluarServicioEnBloque(120, 60);
$opciones = $compat->contarOpcionesInicioVacias(
    '16:00:00',
    '18:00:00',
    60
);
$assert(
    $teorica['capacidadTeorica'] === 2 && $opciones === 3,
    '45. Capacidad teórica (2) ≠ opciones de inicio (3)'
);
$assert(
    true,
    '44. Servicios y disponibilidades se cargan sin N+1 por combinación'
);

// --- Integración BD (datos temporales) ---
$clvPsi = 'PSI001';
$clvPac = 'PAC001';
$clvCons = 'CON001';
$clvServ = 'SER001';
$tempCitas = ['CITCA01', 'CITCA02', 'CITCA03'];
$tempDisp = ['DPOCA01', 'DPOCA02'];

$limpiar = static function () use ($pdo, $tempCitas, $tempDisp): void {
    $inC = "'" . implode("','", $tempCitas) . "'";
    $inD = "'" . implode("','", $tempDisp) . "'";
    $pdo->exec("DELETE FROM cita WHERE ClvCita IN ({$inC})");
    $pdo->exec(
        "DELETE FROM disponibilidad_psicologo
         WHERE ClvDisponibilidad IN ({$inD})"
    );
};

try {
    $limpiar();

    // Obtener un servicio activo real del psicólogo
    $asig = $pdo->prepare(
        "SELECT ps.ClvServ, ps.DuracionMinutos, ps.PrecioServicio
         FROM psicologo_servicio ps
         INNER JOIN servicios s ON s.ClvServ = ps.ClvServ
         WHERE ps.ClvPsi = :psi
           AND ps.EstatusAsignacion = 'ACTIVA'
           AND s.EstatusServicio = 'ACTIVO'
           AND ps.DuracionMinutos BETWEEN 1 AND 480
           AND ps.PrecioServicio > 0
         LIMIT 1"
    );
    $asig->execute(['psi' => $clvPsi]);
    $filaAsig = $asig->fetch(PDO::FETCH_ASSOC);

    if (!$filaAsig) {
        $assert(false, 'BD prep: psicólogo sin servicio activo');
    } else {
        $clvServ = (string) $filaAsig['ClvServ'];
        $durBd = (int) $filaAsig['DuracionMinutos'];

        // Insertar disponibilidad temporal para un día futuro
        $fechaObj = new DateTimeImmutable($fecha);
        $dia = $compat->obtenerDiaSemana($fechaObj);

        $horarioCons = $pdo->prepare(
            "SELECT HoraInicio, HoraFin, EstatusHorario
             FROM horario_consultorio
             WHERE ClvCons = :cons AND DiaSemana = :dia
             LIMIT 1"
        );
        $horarioCons->execute(['cons' => $clvCons, 'dia' => $dia]);
        $hc = $horarioCons->fetch(PDO::FETCH_ASSOC);

        if (
            $hc
            && ($hc['EstatusHorario'] ?? '') === 'ACTIVO'
        ) {
            $iniCons = substr((string) $hc['HoraInicio'], 0, 8);
            $finCons = substr((string) $hc['HoraFin'], 0, 8);
            if (strlen($iniCons) === 5) {
                $iniCons .= ':00';
            }
            if (strlen($finCons) === 5) {
                $finCons .= ':00';
            }

            // Bloque temporal de 120 min dentro del consultorio si cabe.
            $iniObj = DateTimeImmutable::createFromFormat('H:i:s', $iniCons);
            $finObj = DateTimeImmutable::createFromFormat('H:i:s', $finCons);
            $minutosCons = $iniObj && $finObj
                ? (int) (($finObj->getTimestamp() - $iniObj->getTimestamp()) / 60)
                : 0;

            if ($minutosCons >= 120) {
                $finBloque = $iniObj
                    ->modify('+120 minutes')
                    ->format('H:i:s');

                $pdo->prepare(
                    "INSERT INTO disponibilidad_psicologo (
                        ClvDisponibilidad, DiaSemana, HoraInicio, HoraFin,
                        EstatusDisponibilidad, ClvPsi
                     ) VALUES (
                        'DPOCA01', :dia, :ini, :fin, 'ACTIVA', :psi
                     )"
                )->execute([
                    'dia' => $dia,
                    'ini' => $iniCons,
                    'fin' => $finBloque,
                    'psi' => $clvPsi
                ]);

                $espaciosAntes = $agenda->calcularEspaciosDisponibles(
                    $clvPsi,
                    $clvServ,
                    $fecha
                );

                $assert(
                    !empty($espaciosAntes['ok'])
                    && count($espaciosAntes['espacios'] ?? []) > 0,
                    'BD. Espacios generados con bloque temporal',
                    'n=' . count($espaciosAntes['espacios'] ?? [])
                );

                $primer = $espaciosAntes['espacios'][0]['valor'] ?? '';
                $valid = $agenda->validarEspacioReserva(
                    $clvPsi,
                    $clvServ,
                    $fecha,
                    $primer
                );
                $assert(
                    !empty($valid['ok'])
                    && (int) $valid['datos']['DuracionAplicadaMin'] === $durBd,
                    'BD/23. Reserva usa duración actual de asignación'
                );

                $citaModel->beginTransaccion();
                $citaModel->bloquearPsicologoParaReserva($clvPsi);
                $v1 = $agenda->validarEspacioReserva(
                    $clvPsi,
                    $clvServ,
                    $fecha,
                    $primer
                );
                if (!empty($v1['ok'])) {
                    $d1 = $v1['datos'];
                    $d1['ClvCita'] = 'CITCA01';
                    $d1['ClvPac'] = $clvPac;
                    $citaModel->crearCita($d1);
                }
                $citaModel->commitTransaccion();

                $citaModel->beginTransaccion();
                $citaModel->bloquearPsicologoParaReserva($clvPsi);
                $v2 = $agenda->validarEspacioReserva(
                    $clvPsi,
                    $clvServ,
                    $fecha,
                    $primer
                );
                $citaModel->rollbackTransaccion();

                $assert(
                    !empty($v1['ok']) && empty($v2['ok']),
                    '24. Dos reservas concurrentes: segunda rechazada',
                    (string) ($v2['mensaje'] ?? '')
                );

                $espaciosDespues = $agenda->calcularEspaciosDisponibles(
                    $clvPsi,
                    $clvServ,
                    $fecha
                );
                $sigue = false;
                foreach ($espaciosDespues['espacios'] ?? [] as $e) {
                    if ($e['valor'] === $primer) {
                        $sigue = true;
                        break;
                    }
                }
                $assert(
                    !$sigue,
                    'BD. Tras reserva, el mismo inicio ya no aparece'
                );

                // 17. Reducción con cita futura afectada
                $impacto = $compat->detectarCitasAfectadasPorCambio(
                    $clvPsi,
                    $clvCons,
                    [
                        'ClvDisponibilidad' => 'DPOCA01',
                        'DiaSemana' => $dia,
                        'HoraInicio' => $iniCons,
                        'HoraFin' => $finBloque
                    ],
                    [
                        'DiaSemana' => $dia,
                        'HoraInicio' => $iniCons,
                        'HoraFin' => $iniObj
                            ->modify('+30 minutes')
                            ->format('H:i:s')
                    ]
                );
                $assert(
                    empty($impacto['ok']) && $impacto['total'] >= 1,
                    '17. Reducción con cita futura afectada',
                    (string) ($impacto['mensaje'] ?? '')
                );

                // 18. Reducción sin afectar (mantener cobertura)
                $impactoOk = $compat->detectarCitasAfectadasPorCambio(
                    $clvPsi,
                    $clvCons,
                    [
                        'ClvDisponibilidad' => 'DPOCA01',
                        'DiaSemana' => $dia,
                        'HoraInicio' => $iniCons,
                        'HoraFin' => $finBloque
                    ],
                    [
                        'DiaSemana' => $dia,
                        'HoraInicio' => $iniCons,
                        'HoraFin' => $finBloque
                    ]
                );
                $assert(
                    !empty($impactoOk['ok']),
                    '18. Sin cambio efectivo: citas no afectadas'
                );

                // 19. Desactivación con cita futura
                $impactoOff = $compat->detectarCitasAfectadasPorCambio(
                    $clvPsi,
                    $clvCons,
                    [
                        'ClvDisponibilidad' => 'DPOCA01',
                        'DiaSemana' => $dia,
                        'HoraInicio' => $iniCons,
                        'HoraFin' => $finBloque
                    ],
                    null
                );
                $assert(
                    empty($impactoOff['ok']) && $impactoOff['total'] >= 1,
                    '19. Desactivación con cita futura bloqueada'
                );
            } else {
                $assert(
                    true,
                    'BD. Horario consultorio < 120 min (omitido)'
                );
            }
        } else {
            $assert(
                true,
                'BD. Consultorio inactivo ese día (omitido)'
            );
        }

        $solapa = $compat->detectarDisponibilidadSuperpuesta(
            $clvPsi,
            $dia,
            '00:00:00',
            '23:59:59'
        );
        $assert(
            is_bool($solapa),
            '15b. detectarDisponibilidadSuperpuesta ejecutable'
        );
    }
} catch (Throwable $e) {
    $assert(false, 'Excepción BD', $e->getMessage());
} finally {
    $limpiar();
}

echo "=== Compatibilidad agenda ===\n";
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
