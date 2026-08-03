<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\DisponibilidadPsicologo;
use App\Models\HorarioConsultorio;
use App\Models\Psicologo;
use App\Models\Servicio;
use DateInterval;
use DateTimeImmutable;

/**
 * Coordinador de espacios disponibles para agendamiento (Etapa 4).
 *
 * Intervalo de inicio entre candidatos: 30 minutos (decisión temporal;
 * ver docs/PENDIENTES_TECNICOS.md).
 */
class AgendaService
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

    private Psicologo $psicologoModel;

    private Servicio $servicioModel;

    private HorarioConsultorio $horarioConsultorioModel;

    private DisponibilidadPsicologo $disponibilidadModel;

    private Cita $citaModel;

    public function __construct()
    {
        $this->psicologoModel = new Psicologo();
        $this->servicioModel = new Servicio();
        $this->horarioConsultorioModel = new HorarioConsultorio();
        $this->disponibilidadModel = new DisponibilidadPsicologo();
        $this->citaModel = new Cita();
    }

    public function calcularEspaciosDisponibles(
        string $clvPsi,
        string $clvServ,
        string $fecha
    ): array {
        $clvPsi = trim($clvPsi);
        $clvServ = trim($clvServ);
        $fecha = trim($fecha);

        if ($clvPsi === '' || $clvServ === '' || $fecha === '') {
            return $this->respuestaError(
                'Faltan datos para consultar los espacios disponibles.'
            );
        }

        $fechaObj = DateTimeImmutable::createFromFormat(
            'Y-m-d',
            $fecha
        );

        if (
            !$fechaObj ||
            $fechaObj->format('Y-m-d') !== $fecha
        ) {
            return $this->respuestaError(
                'La fecha seleccionada no es válida.'
            );
        }

        $hoy = new DateTimeImmutable('today');

        if ($fechaObj < $hoy) {
            return $this->respuestaError(
                'No puedes consultar espacios en una fecha pasada.'
            );
        }

        $psicologo = $this->psicologoModel->obtenerParaAgendamiento(
            $clvPsi
        );

        if (!$psicologo) {
            return $this->respuestaError(
                'El psicólogo seleccionado no está disponible.'
            );
        }

        $asignacion =
            $this->servicioModel->obtenerAsignacionActivaParaAgendamiento(
                $clvPsi,
                $clvServ
            );

        if (!$asignacion) {
            return $this->respuestaError(
                'El servicio no está disponible para el psicólogo seleccionado.'
            );
        }

        $duracion = (int) $asignacion['DuracionMinutos'];

        if ($duracion <= 0) {
            return $this->respuestaError(
                'La duración del servicio no es válida.'
            );
        }

        $precio = (float) $asignacion['PrecioServicio'];

        $clvCons = $psicologo['ClvCons'];
        $diaSemana = $this->obtenerDiaSemana($fechaObj);

        $horarioConsultorio =
            $this->horarioConsultorioModel
                ->obtenerHorarioActivoPorConsultorioYDia(
                    $clvCons,
                    $diaSemana
                );

        if (!$horarioConsultorio) {
            return $this->respuestaExito(
                [],
                $duracion,
                $precio,
                'El consultorio no atiende ese día.'
            );
        }

        $bloques =
            $this->disponibilidadModel->obtenerActivasPorPsicologoYDia(
                $clvPsi,
                $diaSemana
            );

        if ($bloques === []) {
            return $this->respuestaExito(
                [],
                $duracion,
                $precio,
                'El psicólogo no tiene disponibilidad activa ese día.'
            );
        }

        $aperturaConsultorio = $this->normalizarHora(
            (string) $horarioConsultorio['HoraInicio']
        );
        $cierreConsultorio = $this->normalizarHora(
            (string) $horarioConsultorio['HoraFin']
        );

        $citasProgramadas =
            $this->citaModel->obtenerProgramadasPorPsicologoYFecha(
                $clvPsi,
                $fecha
            );

        $candidatos = [];
        $intervalo = new DateInterval(
            'PT' . self::INTERVALO_INICIO_MINUTOS . 'M'
        );

        foreach ($bloques as $bloque) {
            $inicioValido = $this->maxHora(
                $this->normalizarHora(
                    (string) $bloque['HoraInicio']
                ),
                $aperturaConsultorio
            );
            $finValido = $this->minHora(
                $this->normalizarHora(
                    (string) $bloque['HoraFin']
                ),
                $cierreConsultorio
            );

            if ($inicioValido >= $finValido) {
                continue;
            }

            $cursor = DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $fecha . ' ' . $inicioValido
            );

            $limite = DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $fecha . ' ' . $finValido
            );

            if (!$cursor || !$limite) {
                continue;
            }

            while (true) {
                $finCandidato = $cursor->add(
                    new DateInterval('PT' . $duracion . 'M')
                );

                if ($finCandidato > $limite) {
                    break;
                }

                $inicioCandidato = $cursor->format('H:i:s');
                $finCandidatoStr = $finCandidato->format('H:i:s');

                if (
                    $this->esHoraPasada(
                        $fechaObj,
                        $hoy,
                        $cursor
                    )
                ) {
                    $cursor = $cursor->add($intervalo);
                    continue;
                }

                if (
                    $this->solapaConCita(
                        $inicioCandidato,
                        $finCandidatoStr,
                        $citasProgramadas
                    )
                ) {
                    $cursor = $cursor->add($intervalo);
                    continue;
                }

                $candidatos[$inicioCandidato] = [
                    'valor' => $inicioCandidato,
                    'texto' => substr($inicioCandidato, 0, 5)
                ];

                $cursor = $cursor->add($intervalo);
            }
        }

        $espacios = array_values($candidatos);

        usort(
            $espacios,
            function (array $a, array $b): int {
                return strcmp($a['valor'], $b['valor']);
            }
        );

        if ($espacios === []) {
            return $this->respuestaExito(
                [],
                $duracion,
                $precio,
                'No hay espacios disponibles para la fecha seleccionada.'
            );
        }

        return $this->respuestaExito(
            $espacios,
            $duracion,
            $precio,
            ''
        );
    }

    public function obtenerDiasDisponiblesDelMes(
        string $clvPsi,
        string $clvServ,
        string $mes
    ): array {
        $clvPsi = trim($clvPsi);
        $clvServ = trim($clvServ);
        $mes = trim($mes);

        if ($clvPsi === '' || $clvServ === '' || $mes === '') {
            return [
                'ok' => false,
                'mes' => $mes,
                'diasDisponibles' => [],
                'mensaje' =>
                    'Faltan datos para consultar los días disponibles.'
            ];
        }

        if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
            return [
                'ok' => false,
                'mes' => $mes,
                'diasDisponibles' => [],
                'mensaje' => 'El mes indicado no es válido.'
            ];
        }

        $inicioMes = DateTimeImmutable::createFromFormat(
            'Y-m-d',
            $mes . '-01'
        );

        if (
            !$inicioMes ||
            $inicioMes->format('Y-m') !== $mes
        ) {
            return [
                'ok' => false,
                'mes' => $mes,
                'diasDisponibles' => [],
                'mensaje' => 'El mes indicado no es válido.'
            ];
        }

        $hoy = new DateTimeImmutable('today');
        $finMes = $inicioMes->modify('last day of this month');
        $cursor = $inicioMes;
        $diasDisponibles = [];
        $errorValidacion = null;

        while ($cursor <= $finMes) {
            $fecha = $cursor->format('Y-m-d');

            if ($cursor < $hoy) {
                $cursor = $cursor->add(new DateInterval('P1D'));
                continue;
            }

            $resultado = $this->calcularEspaciosDisponibles(
                $clvPsi,
                $clvServ,
                $fecha
            );

            if (!$resultado['ok']) {
                $errorValidacion = $resultado['mensaje'];
                break;
            }

            $totalEspacios = count($resultado['espacios']);

            if ($totalEspacios > 0) {
                $diasDisponibles[] = [
                    'fecha' => $fecha,
                    'totalEspacios' => $totalEspacios
                ];
            }

            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        if ($errorValidacion !== null) {
            return [
                'ok' => false,
                'mes' => $mes,
                'diasDisponibles' => [],
                'mensaje' => $errorValidacion
            ];
        }

        return [
            'ok' => true,
            'mes' => $mes,
            'diasDisponibles' => $diasDisponibles,
            'mensaje' => $diasDisponibles === []
                ? 'No hay días con espacios disponibles en este mes.'
                : ''
        ];
    }

    public function validarEspacioReserva(
        string $clvPsi,
        string $clvServ,
        string $fecha,
        string $horaInicio
    ): array {
        $horaNormalizada = $this->normalizarHora(
            trim($horaInicio)
        );

        if (
            !$this->horaValida(
                substr($horaNormalizada, 0, 5)
            )
        ) {
            return [
                'ok' => false,
                'mensaje' =>
                    'El horario seleccionado no es válido.'
            ];
        }

        $disponibilidad = $this->calcularEspaciosDisponibles(
            $clvPsi,
            $clvServ,
            $fecha
        );

        if (!$disponibilidad['ok']) {
            return [
                'ok' => false,
                'mensaje' => $disponibilidad['mensaje']
            ];
        }

        $espacioValido = false;

        foreach ($disponibilidad['espacios'] as $espacio) {
            if ($espacio['valor'] === $horaNormalizada) {
                $espacioValido = true;
                break;
            }
        }

        if (!$espacioValido) {
            return [
                'ok' => false,
                'mensaje' =>
                    'El horario seleccionado no está disponible.'
            ];
        }

        $psicologo = $this->psicologoModel->obtenerParaAgendamiento(
            $clvPsi
        );

        if (!$psicologo) {
            return [
                'ok' => false,
                'mensaje' =>
                    'El psicólogo seleccionado no está disponible.'
            ];
        }

        $duracion = (int) $disponibilidad['duracion'];

        $inicioObj = DateTimeImmutable::createFromFormat(
            'H:i:s',
            $horaNormalizada
        );

        if (!$inicioObj) {
            return [
                'ok' => false,
                'mensaje' =>
                    'El horario seleccionado no es válido.'
            ];
        }

        $horaFin = $inicioObj
            ->add(new DateInterval('PT' . $duracion . 'M'))
            ->format('H:i:s');

        return [
            'ok' => true,
            'mensaje' => '',
            'datos' => [
                'ClvPsi' => $clvPsi,
                'ClvServ' => $clvServ,
                'ClvCons' => $psicologo['ClvCons'],
                'FechaCita' => trim($fecha),
                'HraInicioCita' => $horaNormalizada,
                'HraFinCita' => $horaFin,
                'DuracionAplicadaMin' => $duracion,
                'CostoAplicado' =>
                    (float) $disponibilidad['precio']
            ]
        ];
    }

    private function horaValida(string $hora): bool
    {
        return (bool) preg_match(
            '/^([01]\d|2[0-3]):[0-5]\d$/',
            $hora
        );
    }

    public function obtenerDiaSemana(
        DateTimeImmutable $fecha
    ): string {
        $numeroDia = (int) $fecha->format('N');

        return self::MAPA_DIA_SEMANA[$numeroDia];
    }

    private function solapaConCita(
        string $inicioCandidato,
        string $finCandidato,
        array $citas
    ): bool {
        foreach ($citas as $cita) {
            $inicioCita = $this->normalizarHora(
                (string) $cita['HraInicioCita']
            );
            $finCita = $this->normalizarHoraFinCita($cita);

            if (
                $inicioCandidato < $finCita &&
                $finCandidato > $inicioCita
            ) {
                return true;
            }
        }

        return false;
    }

    private function normalizarHoraFinCita(array $cita): string
    {
        $fin = trim((string) ($cita['HraFinCita'] ?? ''));

        if ($fin !== '' && $fin !== '00:00:00') {
            return $this->normalizarHora($fin);
        }

        $inicio = $this->normalizarHora(
            (string) $cita['HraInicioCita']
        );
        $duracion = (int) ($cita['DuracionAplicadaMin'] ?? 0);

        if ($duracion <= 0) {
            $duracion = 60;
        }

        $base = DateTimeImmutable::createFromFormat(
            'H:i:s',
            $inicio
        );

        if (!$base) {
            return $inicio;
        }

        return $base
            ->add(new DateInterval('PT' . $duracion . 'M'))
            ->format('H:i:s');
    }

    private function esHoraPasada(
        DateTimeImmutable $fechaConsulta,
        DateTimeImmutable $hoy,
        DateTimeImmutable $inicioCandidato
    ): bool {
        if ($fechaConsulta->format('Y-m-d') !== $hoy->format('Y-m-d')) {
            return false;
        }

        $ahora = new DateTimeImmutable('now');

        return $inicioCandidato <= $ahora;
    }

    private function maxHora(string $a, string $b): string
    {
        return strcmp($a, $b) >= 0 ? $a : $b;
    }

    private function minHora(string $a, string $b): string
    {
        return strcmp($a, $b) <= 0 ? $a : $b;
    }

    private function normalizarHora(string $hora): string
    {
        $hora = trim($hora);

        if (strlen($hora) === 5) {
            return $hora . ':00';
        }

        return substr($hora, 0, 8);
    }

    private function respuestaExito(
        array $espacios,
        int $duracion,
        float $precio,
        string $mensaje
    ): array {
        return [
            'ok' => true,
            'espacios' => $espacios,
            'duracion' => $duracion,
            'precio' => $precio,
            'mensaje' => $mensaje
        ];
    }

    private function respuestaError(string $mensaje): array
    {
        return [
            'ok' => false,
            'mensaje' => $mensaje,
            'espacios' => [],
            'duracion' => null,
            'precio' => null
        ];
    }
}
