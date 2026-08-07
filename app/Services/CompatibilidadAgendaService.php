<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\DisponibilidadPsicologo;
use App\Models\HorarioConsultorio;
use App\Models\Servicio;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Compatibilidad entre disponibilidad, duración de servicios y citas.
 * Fuente única de fórmulas de bloque efectivo, candidatos y solapes.
 */
class CompatibilidadAgendaService
{
    public const INTERVALO_INICIO_MINUTOS = 30;

    private const MAPA_DIA_SEMANA = [
        1 => 'LUNES',
        2 => 'MARTES',
        3 => 'MIERCOLES',
        4 => 'JUEVES',
        5 => 'VIERNES',
        6 => 'SABADO',
        7 => 'DOMINGO'
    ];

    private Cita $citaModel;
    private DisponibilidadPsicologo $disponibilidadModel;
    private HorarioConsultorio $horarioModel;
    private Servicio $servicioModel;

    public function __construct(
        ?Cita $citaModel = null,
        ?DisponibilidadPsicologo $disponibilidadModel = null,
        ?HorarioConsultorio $horarioModel = null,
        ?Servicio $servicioModel = null
    ) {
        $this->citaModel = $citaModel ?? new Cita();
        $this->disponibilidadModel =
            $disponibilidadModel ?? new DisponibilidadPsicologo();
        $this->horarioModel = $horarioModel ?? new HorarioConsultorio();
        $this->servicioModel = $servicioModel ?? new Servicio();
    }

    public function zona(): DateTimeZone
    {
        return new DateTimeZone('America/Mexico_City');
    }

    public function normalizarHora(string $hora): string
    {
        $hora = trim($hora);

        if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
            return $hora . ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}/', $hora)) {
            return substr($hora, 0, 8);
        }

        return $hora;
    }

    public function obtenerDiaSemana(DateTimeImmutable $fecha): string
    {
        return self::MAPA_DIA_SEMANA[(int) $fecha->format('N')] ?? '';
    }

    /**
     * @return array{inicio: string, fin: string, minutos: int}|null
     */
    public function calcularBloqueEfectivo(
        string $inicioDisponibilidad,
        string $finDisponibilidad,
        string $inicioConsultorio,
        string $finConsultorio
    ): ?array {
        $inicio = $this->maxHora(
            $this->normalizarHora($inicioDisponibilidad),
            $this->normalizarHora($inicioConsultorio)
        );
        $fin = $this->minHora(
            $this->normalizarHora($finDisponibilidad),
            $this->normalizarHora($finConsultorio)
        );

        if ($inicio >= $fin) {
            return null;
        }

        return [
            'inicio' => $inicio,
            'fin' => $fin,
            'minutos' => $this->calcularMinutosBloque($inicio, $fin)
        ];
    }

    /**
     * Normaliza bloques efectivos en memoria:
     * 1) cruza cada bloque con el horario del consultorio;
     * 2) ordena por inicio;
     * 3) fusiona solapes heredados y adyacentes (fin == inicio siguiente);
     * 4) conserva separación si hay cualquier pausa real.
     *
     * No modifica filas de disponibilidad_psicologo.
     *
     * @param list<array<string, mixed>> $bloques
     * @return list<array{inicio: string, fin: string, minutos: int}>
     */
    public function normalizarBloquesEfectivos(
        array $bloques,
        string $inicioConsultorio,
        string $finConsultorio
    ): array {
        $efectivos = [];

        foreach ($bloques as $bloque) {
            $inicio = (string) (
                $bloque['inicio']
                ?? $bloque['HoraInicio']
                ?? ''
            );
            $fin = (string) (
                $bloque['fin']
                ?? $bloque['HoraFin']
                ?? ''
            );

            if ($inicio === '' || $fin === '') {
                continue;
            }

            $efectivo = $this->calcularBloqueEfectivo(
                $inicio,
                $fin,
                $inicioConsultorio,
                $finConsultorio
            );

            if ($efectivo !== null) {
                $efectivos[] = $efectivo;
            }
        }

        usort(
            $efectivos,
            static function (array $a, array $b): int {
                return strcmp($a['inicio'], $b['inicio']);
            }
        );

        $fusionados = [];

        foreach ($efectivos as $efectivo) {
            if ($fusionados === []) {
                $fusionados[] = $efectivo;
                continue;
            }

            $ultimoIdx = count($fusionados) - 1;
            $ultimoFin = $fusionados[$ultimoIdx]['fin'];

            // Solape o adyacencia exacta (sin pausa).
            if ($efectivo['inicio'] <= $ultimoFin) {
                if ($efectivo['fin'] > $ultimoFin) {
                    $fusionados[$ultimoIdx]['fin'] = $efectivo['fin'];
                }

                $fusionados[$ultimoIdx]['minutos'] =
                    $this->calcularMinutosBloque(
                        $fusionados[$ultimoIdx]['inicio'],
                        $fusionados[$ultimoIdx]['fin']
                    );
                continue;
            }

            $fusionados[] = $efectivo;
        }

        return $fusionados;
    }

    /**
     * Cuenta opciones de inicio en un bloque vacío (sin citas).
     * No representa citas simultáneas garantizadas.
     */
    public function contarOpcionesInicioVacias(
        string $inicioEfectivo,
        string $finEfectivo,
        int $duracionMinutos,
        int $intervaloMinutos = self::INTERVALO_INICIO_MINUTOS
    ): int {
        $fechaRef = '2099-01-05';
        $ahora = new DateTimeImmutable(
            $fechaRef . ' 00:00:00',
            $this->zona()
        );

        return count(
            $this->generarHorariosCandidatos(
                $fechaRef,
                $inicioEfectivo,
                $finEfectivo,
                $duracionMinutos,
                [],
                $ahora,
                $intervaloMinutos
            )
        );
    }

    public function calcularMinutosBloque(
        string $inicio,
        string $fin
    ): int {
        $ini = DateTimeImmutable::createFromFormat(
            'H:i:s',
            $this->normalizarHora($inicio)
        );
        $finObj = DateTimeImmutable::createFromFormat(
            'H:i:s',
            $this->normalizarHora($fin)
        );

        if (!$ini || !$finObj || $finObj <= $ini) {
            return 0;
        }

        return (int) (($finObj->getTimestamp() - $ini->getTimestamp()) / 60);
    }

    /**
     * @return array{
     *   compatible: bool,
     *   capacidadTeorica: int,
     *   minutosRestantes: int,
     *   estado: string,
     *   etiquetaEstado: string,
     *   nivelAlerta: string
     * }
     */
    public function evaluarServicioEnBloque(
        int $minutosBloque,
        int $duracionServicio
    ): array {
        $minutosBloque = max(0, $minutosBloque);
        $duracionServicio = max(0, $duracionServicio);

        if ($duracionServicio <= 0 || $minutosBloque <= 0) {
            return [
                'compatible' => false,
                'capacidadTeorica' => 0,
                'minutosRestantes' => $minutosBloque,
                'estado' => 'incompatible',
                'etiquetaEstado' => 'no cabe en este bloque',
                'nivelAlerta' => 'rojo'
            ];
        }

        if ($duracionServicio > $minutosBloque) {
            return [
                'compatible' => false,
                'capacidadTeorica' => 0,
                'minutosRestantes' => $minutosBloque,
                'estado' => 'incompatible',
                'etiquetaEstado' => 'no cabe en este bloque',
                'nivelAlerta' => 'rojo'
            ];
        }

        $capacidad = intdiv($minutosBloque, $duracionServicio);
        $restantes = $minutosBloque % $duracionServicio;

        if ($restantes === 0) {
            return [
                'compatible' => true,
                'capacidadTeorica' => $capacidad,
                'minutosRestantes' => 0,
                'estado' => 'compatible',
                'etiquetaEstado' => 'compatible',
                'nivelAlerta' => 'verde'
            ];
        }

        return [
            'compatible' => true,
            'capacidadTeorica' => $capacidad,
            'minutosRestantes' => $restantes,
            'estado' => 'compatible_con_resto',
            'etiquetaEstado' =>
                'compatible con espacio restante',
            'nivelAlerta' => 'ambar'
        ];
    }

    /**
     * Genera inicios candidatos dentro de UN intervalo efectivo continuo
     * (ya normalizado / fusionado). No atraviesa pausas reales.
     *
     * @param list<array<string, mixed>> $citasProgramadas
     * @return list<array{valor: string, texto: string, fin: string}>
     */
    public function generarHorariosCandidatos(
        string $fecha,
        string $inicioEfectivo,
        string $finEfectivo,
        int $duracionMinutos,
        array $citasProgramadas = [],
        ?DateTimeImmutable $ahora = null,
        int $intervaloMinutos = self::INTERVALO_INICIO_MINUTOS
    ): array {
        $duracionMinutos = (int) $duracionMinutos;
        $intervaloMinutos = max(1, (int) $intervaloMinutos);

        if ($duracionMinutos <= 0) {
            return [];
        }

        $inicio = $this->normalizarHora($inicioEfectivo);
        $fin = $this->normalizarHora($finEfectivo);

        if ($inicio >= $fin) {
            return [];
        }

        $cursor = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $fecha . ' ' . $inicio
        );
        $limite = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $fecha . ' ' . $fin
        );

        if (!$cursor || !$limite) {
            return [];
        }

        $ahora = $ahora ?? new DateTimeImmutable('now', $this->zona());
        $hoyStr = $ahora->format('Y-m-d');
        $intervalo = new DateInterval('PT' . $intervaloMinutos . 'M');
        $citasNorm = $this->normalizarCitasOcupadas($citasProgramadas);
        $candidatos = [];

        while (true) {
            $finCandidato = $cursor->add(
                new DateInterval('PT' . $duracionMinutos . 'M')
            );

            if ($finCandidato > $limite) {
                break;
            }

            $inicioStr = $cursor->format('H:i:s');
            $finStr = $finCandidato->format('H:i:s');

            if (
                $fecha === $hoyStr
                && $cursor <= $ahora
            ) {
                $cursor = $cursor->add($intervalo);
                continue;
            }

            if ($this->solapaConLista($inicioStr, $finStr, $citasNorm)) {
                $cursor = $cursor->add($intervalo);
                continue;
            }

            $candidatos[$inicioStr] = [
                'valor' => $inicioStr,
                'texto' => substr($inicioStr, 0, 5),
                'fin' => $finStr
            ];

            $cursor = $cursor->add($intervalo);
        }

        return array_values($candidatos);
    }

    /**
     * Resta citas ocupadas de un conjunto de candidatos ya generados.
     *
     * @param list<array{valor: string, texto?: string, fin?: string}> $candidatos
     * @param list<array<string, mixed>> $citas
     * @return list<array{valor: string, texto: string, fin: string}>
     */
    public function restarCitasOcupadas(
        array $candidatos,
        array $citas,
        int $duracionMinutos
    ): array {
        $citasNorm = $this->normalizarCitasOcupadas($citas);
        $resultado = [];

        foreach ($candidatos as $candidato) {
            $inicio = $this->normalizarHora(
                (string) ($candidato['valor'] ?? '')
            );
            $fin = $this->normalizarHora(
                (string) ($candidato['fin'] ?? '')
            );

            if ($fin === '' || $fin === '00:00:00') {
                $base = DateTimeImmutable::createFromFormat(
                    'H:i:s',
                    $inicio
                );
                if (!$base || $duracionMinutos <= 0) {
                    continue;
                }
                $fin = $base
                    ->add(new DateInterval('PT' . $duracionMinutos . 'M'))
                    ->format('H:i:s');
            }

            if ($this->solapaConLista($inicio, $fin, $citasNorm)) {
                continue;
            }

            $resultado[] = [
                'valor' => $inicio,
                'texto' => substr($inicio, 0, 5),
                'fin' => $fin
            ];
        }

        return $resultado;
    }

    public function detectarSolapamientoHorarios(
        string $inicioA,
        string $finA,
        string $inicioB,
        string $finB
    ): bool {
        $aIni = $this->normalizarHora($inicioA);
        $aFin = $this->normalizarHora($finA);
        $bIni = $this->normalizarHora($inicioB);
        $bFin = $this->normalizarHora($finB);

        return $aIni < $bFin && $aFin > $bIni;
    }

    public function detectarDisponibilidadSuperpuesta(
        string $clvPsi,
        string $diaSemana,
        string $horaInicio,
        string $horaFin,
        ?string $excluirClave = null
    ): bool {
        return $this->disponibilidadModel->existeSolapamiento(
            $clvPsi,
            $diaSemana,
            $this->normalizarHora($horaInicio),
            $this->normalizarHora($horaFin),
            $excluirClave
        );
    }

    /**
     * ¿La cita [inicio, fin] cabe completa en algún bloque efectivo?
     *
     * @param list<array{inicio: string, fin: string}> $bloquesEfectivos
     */
    public function citaCabeEnBloquesEfectivos(
        string $inicioCita,
        string $finCita,
        array $bloquesEfectivos
    ): bool {
        $inicio = $this->normalizarHora($inicioCita);
        $fin = $this->normalizarHora($finCita);

        if ($inicio >= $fin) {
            return false;
        }

        foreach ($bloquesEfectivos as $bloque) {
            $bIni = $this->normalizarHora((string) ($bloque['inicio'] ?? ''));
            $bFin = $this->normalizarHora((string) ($bloque['fin'] ?? ''));

            if ($bIni === '' || $bFin === '' || $bIni >= $bFin) {
                continue;
            }

            if ($inicio >= $bIni && $fin <= $bFin) {
                return true;
            }
        }

        return false;
    }

    /**
     * Citas futuras PROGRAMADA afectadas por editar/reducir/desactivar un bloque.
     *
     * @param array<string, mixed> $bloqueActual
     * @param array<string, mixed>|null $bloquePropuesto null = desactivar
     * @return array{
     *   ok: bool,
     *   afectadas: list<array<string, mixed>>,
     *   total: int,
     *   mensaje: string
     * }
     */
    public function detectarCitasAfectadasPorCambio(
        string $clvPsi,
        string $clvCons,
        array $bloqueActual,
        ?array $bloquePropuesto
    ): array {
        $clvPsi = trim($clvPsi);
        $hoy = new DateTimeImmutable('today', $this->zona());
        $citas = $this->citaModel->listarProgramadasFuturasPorPsicologo(
            $clvPsi,
            $hoy->format('Y-m-d')
        );

        $bloquesActivos = $this->disponibilidadModel
            ->obtenerPorPsicologo($clvPsi);
        $horarios = $this->indexarHorariosConsultorio($clvCons);

        $claveBloque = (string) ($bloqueActual['ClvDisponibilidad'] ?? '');
        $propuestos = [];

        foreach ($bloquesActivos as $bloque) {
            if (
                ($bloque['EstatusDisponibilidad'] ?? '') !== 'ACTIVA'
            ) {
                continue;
            }

            $clave = (string) ($bloque['ClvDisponibilidad'] ?? '');

            if ($clave === $claveBloque) {
                if ($bloquePropuesto === null) {
                    continue;
                }

                $propuestos[] = [
                    'DiaSemana' => strtoupper(
                        (string) ($bloquePropuesto['DiaSemana']
                            ?? $bloque['DiaSemana'])
                    ),
                    'HoraInicio' => $this->normalizarHora(
                        (string) ($bloquePropuesto['HoraInicio']
                            ?? $bloque['HoraInicio'])
                    ),
                    'HoraFin' => $this->normalizarHora(
                        (string) ($bloquePropuesto['HoraFin']
                            ?? $bloque['HoraFin'])
                    ),
                    'EstatusDisponibilidad' => 'ACTIVA',
                    'ClvDisponibilidad' => $clave
                ];
                continue;
            }

            $propuestos[] = [
                'DiaSemana' => strtoupper((string) $bloque['DiaSemana']),
                'HoraInicio' => $this->normalizarHora(
                    (string) $bloque['HoraInicio']
                ),
                'HoraFin' => $this->normalizarHora(
                    (string) $bloque['HoraFin']
                ),
                'EstatusDisponibilidad' => 'ACTIVA',
                'ClvDisponibilidad' => $clave
            ];
        }

        $afectadas = [];
        $diaActual = strtoupper((string) ($bloqueActual['DiaSemana'] ?? ''));
        $diaPropuesto = $bloquePropuesto !== null
            ? strtoupper(
                (string) (
                    $bloquePropuesto['DiaSemana']
                    ?? $bloqueActual['DiaSemana']
                    ?? ''
                )
            )
            : $diaActual;

        $diasRelevantes = array_values(array_unique(array_filter([
            $diaActual,
            $diaPropuesto
        ])));

        $activosAntes = [];
        foreach ($bloquesActivos as $bloque) {
            if (($bloque['EstatusDisponibilidad'] ?? '') !== 'ACTIVA') {
                continue;
            }

            $activosAntes[] = [
                'DiaSemana' => strtoupper((string) $bloque['DiaSemana']),
                'HoraInicio' => $this->normalizarHora(
                    (string) $bloque['HoraInicio']
                ),
                'HoraFin' => $this->normalizarHora(
                    (string) $bloque['HoraFin']
                ),
                'EstatusDisponibilidad' => 'ACTIVA',
                'ClvDisponibilidad' => (string) (
                    $bloque['ClvDisponibilidad'] ?? ''
                )
            ];
        }

        foreach ($citas as $cita) {
            $fecha = (string) ($cita['FechaCita'] ?? '');
            $fechaObj = DateTimeImmutable::createFromFormat(
                'Y-m-d',
                $fecha,
                $this->zona()
            );

            if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
                continue;
            }

            $diaCita = $this->obtenerDiaSemana($fechaObj);

            if (!in_array($diaCita, $diasRelevantes, true)) {
                continue;
            }

            $inicioCita = $this->normalizarHora(
                (string) ($cita['HraInicioCita'] ?? '')
            );
            $finCita = $this->resolverFinCita($cita);

            $efectivosAntes = $this->bloquesEfectivosParaDia(
                $activosAntes,
                $diaCita,
                $horarios
            );
            $efectivosDespues = $this->bloquesEfectivosParaDia(
                $propuestos,
                $diaCita,
                $horarios
            );

            $cabeAntes = $this->citaCabeEnBloquesEfectivos(
                $inicioCita,
                $finCita,
                $efectivosAntes
            );
            $cabeDespues = $this->citaCabeEnBloquesEfectivos(
                $inicioCita,
                $finCita,
                $efectivosDespues
            );

            // Solo citas que hoy caben y dejarían de caber tras el cambio.
            if (!$cabeAntes || $cabeDespues) {
                continue;
            }

            $afectadas[] = [
                'ClvCita' => (string) ($cita['ClvCita'] ?? ''),
                'FechaCita' => $fecha,
                'HraInicioCita' => $inicioCita,
                'HraFinCita' => $finCita,
                'NombreServicio' => (string) (
                    $cita['NombreServicio'] ?? ''
                ),
                'DuracionAplicadaMin' => (int) (
                    $cita['DuracionAplicadaMin'] ?? 0
                )
            ];
        }

        $total = count($afectadas);

        if ($total === 0) {
            return [
                'ok' => true,
                'afectadas' => [],
                'total' => 0,
                'mensaje' => ''
            ];
        }

        $detalle = [];
        foreach (array_slice($afectadas, 0, 5) as $item) {
            $detalle[] = sprintf(
                '%s %s–%s (%s)',
                $this->formatearFechaCorta($item['FechaCita']),
                substr($item['HraInicioCita'], 0, 5),
                substr($item['HraFinCita'], 0, 5),
                $item['NombreServicio'] !== ''
                    ? $item['NombreServicio']
                    : 'Servicio'
            );
        }

        $mensaje =
            'No puedes modificar este horario porque existen citas '
            . 'futuras programadas dentro del periodo que quedaría '
            . 'fuera de disponibilidad.';

        if ($total === 1) {
            $mensaje .= ' Cita afectada: ' . $detalle[0] . '.';
        } else {
            $mensaje .= ' Hay ' . $total . ' citas afectadas';
            if ($detalle !== []) {
                $mensaje .= ': ' . implode('; ', $detalle);
                if ($total > 5) {
                    $mensaje .= '…';
                }
            }
            $mensaje .= '.';
        }

        return [
            'ok' => false,
            'afectadas' => $afectadas,
            'total' => $total,
            'mensaje' => $mensaje
        ];
    }

    /**
     * Resumen de compatibilidad servicios × bloques efectivos.
     *
     * @param list<array<string, mixed>> $bloques
     * @param list<array<string, mixed>> $horariosConsultorio
     * @param list<array<string, mixed>>|null $servicios
     * @return array{
     *   bloques: list<array<string, mixed>>,
     *   serviciosSinBloque: list<array<string, mixed>>,
     *   alertaGlobal: array{nivel: string, mensaje: string}|null
     * }
     */
    public function resumirCompatibilidadServicios(
        string $clvPsi,
        string $clvCons,
        array $bloques,
        array $horariosConsultorio,
        ?array $servicios = null
    ): array {
        $servicios = $servicios
            ?? $this->servicioModel->listarActivosPorPsicologo($clvPsi);
        $horariosIdx = [];

        foreach ($horariosConsultorio as $horario) {
            $dia = strtoupper((string) ($horario['DiaSemana'] ?? ''));
            $horariosIdx[$dia] = $horario;
        }

        $bloquesResumen = [];
        $serviciosConAlgunaCompatibilidad = [];

        foreach ($servicios as $servicio) {
            $clave = (string) ($servicio['ClvServ'] ?? '');
            if ($clave !== '') {
                $serviciosConAlgunaCompatibilidad[$clave] = false;
            }
        }

        // Compatibilidad global: intervalos fusionados por día (sin N+1).
        $activosPorDia = [];
        foreach ($bloques as $bloque) {
            if (
                strtoupper((string) ($bloque['EstatusDisponibilidad'] ?? ''))
                !== 'ACTIVA'
            ) {
                continue;
            }
            $dia = strtoupper((string) ($bloque['DiaSemana'] ?? ''));
            if ($dia === '') {
                continue;
            }
            $activosPorDia[$dia][] = $bloque;
        }

        foreach ($activosPorDia as $dia => $bloquesDia) {
            $horario = $horariosIdx[$dia] ?? null;
            if (
                !$horario
                || ($horario['EstatusHorario'] ?? '') !== 'ACTIVO'
            ) {
                continue;
            }

            $fusionados = $this->normalizarBloquesEfectivos(
                $bloquesDia,
                (string) $horario['HoraInicio'],
                (string) $horario['HoraFin']
            );

            foreach ($servicios as $servicio) {
                $claveServ = (string) ($servicio['ClvServ'] ?? '');
                if ($claveServ === '' || !empty($serviciosConAlgunaCompatibilidad[$claveServ])) {
                    continue;
                }

                $duracion = (int) ($servicio['DuracionMinutos'] ?? 0);
                foreach ($fusionados as $intervalo) {
                    $eval = $this->evaluarServicioEnBloque(
                        (int) $intervalo['minutos'],
                        $duracion
                    );
                    if (!empty($eval['compatible'])) {
                        $serviciosConAlgunaCompatibilidad[$claveServ] = true;
                        break;
                    }
                }
            }
        }

        foreach ($bloques as $bloque) {
            $dia = strtoupper((string) ($bloque['DiaSemana'] ?? ''));
            $horario = $horariosIdx[$dia] ?? null;
            $estatusBloque = strtoupper(
                (string) ($bloque['EstatusDisponibilidad'] ?? '')
            );

            $nivelBloque = 'verde';
            $mensajeBloque = '';
            $efectivo = null;

            if ($estatusBloque !== 'ACTIVA') {
                $nivelBloque = 'ambar';
                $mensajeBloque = 'Bloque inactivo.';
            } elseif (
                !$horario
                || ($horario['EstatusHorario'] ?? '') !== 'ACTIVO'
            ) {
                $nivelBloque = 'rojo';
                $mensajeBloque =
                    'El consultorio no atiende este día.';
            } else {
                $efectivo = $this->calcularBloqueEfectivo(
                    (string) $bloque['HoraInicio'],
                    (string) $bloque['HoraFin'],
                    (string) $horario['HoraInicio'],
                    (string) $horario['HoraFin']
                );

                if ($efectivo === null) {
                    $nivelBloque = 'rojo';
                    $mensajeBloque =
                        'Sin bloque efectivo respecto al horario del consultorio.';
                }
            }

            $filasServicio = [];
            $algunCompatible = false;
            $algunIncompatible = false;
            $algunResto = false;

            if ($efectivo !== null && $estatusBloque === 'ACTIVA') {
                foreach ($servicios as $servicio) {
                    $duracion = (int) ($servicio['DuracionMinutos'] ?? 0);
                    $eval = $this->evaluarServicioEnBloque(
                        $efectivo['minutos'],
                        $duracion
                    );

                    $opciones = $this->contarOpcionesInicioVacias(
                        $efectivo['inicio'],
                        $efectivo['fin'],
                        $duracion
                    );

                    if ($eval['compatible']) {
                        $algunCompatible = true;
                    } else {
                        $algunIncompatible = true;
                    }

                    if ($eval['estado'] === 'compatible_con_resto') {
                        $algunResto = true;
                    }

                    $filasServicio[] = [
                        'ClvServ' => (string) ($servicio['ClvServ'] ?? ''),
                        'NombreServicio' => (string) (
                            $servicio['NombreServicio'] ?? 'Servicio'
                        ),
                        'DuracionMinutos' => $duracion,
                        'minutosBloque' => $efectivo['minutos'],
                        'capacidadTeorica' => $eval['capacidadTeorica'],
                        'opcionesInicioDisponibles' => $opciones,
                        'minutosRestantes' => $eval['minutosRestantes'],
                        'estado' => $eval['estado'],
                        'etiquetaEstado' => $eval['etiquetaEstado'],
                        'nivelAlerta' => $eval['nivelAlerta']
                    ];
                }

                if ($filasServicio === []) {
                    $nivelBloque = 'rojo';
                    $mensajeBloque =
                        'No hay servicios activos configurados.';
                } elseif (!$algunCompatible) {
                    $nivelBloque = 'rojo';
                    $mensajeBloque =
                        'Ningún servicio activo cabe en este bloque.';
                } elseif ($algunIncompatible || $algunResto) {
                    $nivelBloque = 'ambar';
                    $mensajeBloque = $algunIncompatible
                        ? 'Algunos servicios no caben en este bloque.'
                        : 'Hay minutos restantes que ningún servicio aprovecha por completo.';
                }
            }

            $bloquesResumen[] = [
                'ClvDisponibilidad' => (string) (
                    $bloque['ClvDisponibilidad'] ?? ''
                ),
                'DiaSemana' => $dia,
                'HoraInicio' => $this->normalizarHora(
                    (string) ($bloque['HoraInicio'] ?? '')
                ),
                'HoraFin' => $this->normalizarHora(
                    (string) ($bloque['HoraFin'] ?? '')
                ),
                'EstatusDisponibilidad' => $estatusBloque,
                'efectivo' => $efectivo,
                'nivelAlerta' => $nivelBloque,
                'mensajeAlerta' => $mensajeBloque,
                'servicios' => $filasServicio
            ];
        }

        $serviciosSinBloque = [];
        foreach ($servicios as $servicio) {
            $clave = (string) ($servicio['ClvServ'] ?? '');
            if (
                $clave !== ''
                && empty($serviciosConAlgunaCompatibilidad[$clave])
            ) {
                $serviciosSinBloque[] = [
                    'ClvServ' => $clave,
                    'NombreServicio' => (string) (
                        $servicio['NombreServicio'] ?? 'Servicio'
                    ),
                    'DuracionMinutos' => (int) (
                        $servicio['DuracionMinutos'] ?? 0
                    )
                ];
            }
        }

        $alertaGlobal = null;
        if ($serviciosSinBloque !== []) {
            $nombres = array_map(
                static fn (array $s): string =>
                    (string) $s['NombreServicio']
                    . ' ('
                    . (int) $s['DuracionMinutos']
                    . ' min)',
                $serviciosSinBloque
            );
            $alertaGlobal = [
                'nivel' => 'rojo',
                'mensaje' =>
                    'Con la duración actual, estos servicios no caben '
                    . 'en ninguno de tus horarios activos: '
                    . implode(', ', $nombres)
                    . '. No se ofrecerán espacios hasta que ajustes '
                    . 'tu disponibilidad o la duración.'
            ];
        }

        return [
            'bloques' => $bloquesResumen,
            'serviciosSinBloque' => $serviciosSinBloque,
            'alertaGlobal' => $alertaGlobal
        ];
    }

    /**
     * Advertencia al cambiar duración de un servicio.
     *
     * @return array{sinBloqueCompatible: bool, mensaje: string}
     */
    public function advertenciaCambioDuracion(
        string $clvPsi,
        string $clvCons,
        int $nuevaDuracion,
        string $nombreServicio = 'este servicio'
    ): array {
        if ($nuevaDuracion <= 0) {
            return [
                'sinBloqueCompatible' => true,
                'mensaje' =>
                    'La duración indicada no es válida.'
            ];
        }

        $bloques = $this->disponibilidadModel->obtenerPorPsicologo($clvPsi);
        $horariosIdx = $this->indexarHorariosConsultorio($clvCons);

        $activosPorDia = [];
        foreach ($bloques as $bloque) {
            if (($bloque['EstatusDisponibilidad'] ?? '') !== 'ACTIVA') {
                continue;
            }
            $dia = strtoupper((string) ($bloque['DiaSemana'] ?? ''));
            if ($dia !== '') {
                $activosPorDia[$dia][] = $bloque;
            }
        }

        $cabeEnAlguno = false;

        foreach ($activosPorDia as $dia => $bloquesDia) {
            $horario = $horariosIdx[$dia] ?? null;
            if (
                !$horario
                || ($horario['EstatusHorario'] ?? '') !== 'ACTIVO'
            ) {
                continue;
            }

            $fusionados = $this->normalizarBloquesEfectivos(
                $bloquesDia,
                (string) $horario['HoraInicio'],
                (string) $horario['HoraFin']
            );

            foreach ($fusionados as $intervalo) {
                $eval = $this->evaluarServicioEnBloque(
                    (int) $intervalo['minutos'],
                    $nuevaDuracion
                );

                if (!empty($eval['compatible'])) {
                    $cabeEnAlguno = true;
                    break 2;
                }
            }
        }

        if ($cabeEnAlguno) {
            return [
                'sinBloqueCompatible' => false,
                'mensaje' => ''
            ];
        }

        return [
            'sinBloqueCompatible' => true,
            'mensaje' =>
                'Con la duración de '
                . $nuevaDuracion
                . ' minutos, '
                . $nombreServicio
                . ' no cabe en ninguno de tus horarios actuales. '
                . 'No se ofrecerán espacios hasta que ajustes tu '
                . 'disponibilidad.'
        ];
    }

    public function resolverFinCita(array $cita): string
    {
        $fin = trim((string) ($cita['HraFinCita'] ?? ''));

        if ($fin !== '' && $fin !== '00:00:00') {
            return $this->normalizarHora($fin);
        }

        $inicio = $this->normalizarHora(
            (string) ($cita['HraInicioCita'] ?? '')
        );
        $duracion = (int) ($cita['DuracionAplicadaMin'] ?? 0);

        if ($duracion <= 0) {
            $duracion = 60;
        }

        $base = DateTimeImmutable::createFromFormat('H:i:s', $inicio);

        if (!$base) {
            return $inicio;
        }

        return $base
            ->add(new DateInterval('PT' . $duracion . 'M'))
            ->format('H:i:s');
    }

    /**
     * @param list<array<string, mixed>> $citas
     * @return list<array{inicio: string, fin: string}>
     */
    public function normalizarCitasOcupadas(array $citas): array
    {
        $norm = [];

        foreach ($citas as $cita) {
            $inicio = $this->normalizarHora(
                (string) ($cita['HraInicioCita'] ?? $cita['inicio'] ?? '')
            );
            $fin = isset($cita['fin'])
                ? $this->normalizarHora((string) $cita['fin'])
                : $this->resolverFinCita($cita);

            if ($inicio === '' || $fin === '' || $inicio >= $fin) {
                continue;
            }

            $norm[] = [
                'inicio' => $inicio,
                'fin' => $fin
            ];
        }

        return $norm;
    }

    /**
     * @param list<array{inicio: string, fin: string}> $citas
     */
    public function solapaConLista(
        string $inicioCandidato,
        string $finCandidato,
        array $citas
    ): bool {
        foreach ($citas as $cita) {
            if (
                $this->detectarSolapamientoHorarios(
                    $inicioCandidato,
                    $finCandidato,
                    (string) $cita['inicio'],
                    (string) $cita['fin']
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $bloquesActivos
     * @param array<string, array<string, mixed>|null> $horariosIdx
     * @return list<array{inicio: string, fin: string, minutos: int}>
     */
    public function bloquesEfectivosParaDia(
        array $bloquesActivos,
        string $diaSemana,
        array $horariosIdx
    ): array {
        $diaSemana = strtoupper(trim($diaSemana));
        $horario = $horariosIdx[$diaSemana] ?? null;

        if (
            !$horario
            || ($horario['EstatusHorario'] ?? '') !== 'ACTIVO'
        ) {
            return [];
        }

        $delDia = [];

        foreach ($bloquesActivos as $bloque) {
            if (
                strtoupper((string) ($bloque['DiaSemana'] ?? ''))
                !== $diaSemana
            ) {
                continue;
            }

            if (
                isset($bloque['EstatusDisponibilidad'])
                && strtoupper((string) $bloque['EstatusDisponibilidad'])
                    !== 'ACTIVA'
            ) {
                continue;
            }

            $delDia[] = $bloque;
        }

        return $this->normalizarBloquesEfectivos(
            $delDia,
            (string) $horario['HoraInicio'],
            (string) $horario['HoraFin']
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function indexarHorariosConsultorio(string $clvCons): array
    {
        $idx = [];

        foreach ($this->horarioModel->obtenerPorConsultorio($clvCons) as $h) {
            $dia = strtoupper((string) ($h['DiaSemana'] ?? ''));
            if ($dia !== '') {
                $idx[$dia] = $h;
            }
        }

        return $idx;
    }

    private function maxHora(string $a, string $b): string
    {
        return strcmp($a, $b) >= 0 ? $a : $b;
    }

    private function minHora(string $a, string $b): string
    {
        return strcmp($a, $b) <= 0 ? $a : $b;
    }

    private function formatearFechaCorta(string $fecha): string
    {
        $obj = DateTimeImmutable::createFromFormat(
            'Y-m-d',
            $fecha,
            $this->zona()
        );

        return $obj ? $obj->format('d/m/Y') : $fecha;
    }
}
