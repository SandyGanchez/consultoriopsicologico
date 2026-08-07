<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Ventana temporal para registrar ASISTIDA / INASISTENCIA (Fase 4A).
 * Autoridad: servidor America/Mexico_City. No usa hora del cliente.
 */
class ResultadoCitaVentanaService
{
    public const CODIGO_OK = 'OK';
    public const CODIGO_FUTURA = 'CITA_FUTURA';
    public const CODIGO_ANTES_INICIO = 'CITA_ANTES_INICIO';
    public const CODIGO_DURANTE_SESION = 'CITA_DURANTE_SESION';
    public const CODIGO_DIA_CERRADO = 'CITA_DIA_CERRADO';
    public const CODIGO_HORARIO_INVALIDO = 'HORARIO_INVALIDO';
    public const CODIGO_ESTADO_INVALIDO = 'ESTADO_INVALIDO';
    public const CODIGO_ACCION_INVALIDA = 'ACCION_INVALIDA';

    public function zona(): DateTimeZone
    {
        return new DateTimeZone('America/Mexico_City');
    }

    public function ahora(?DateTimeImmutable $ahora = null): DateTimeImmutable
    {
        return $ahora ?? new DateTimeImmutable('now', $this->zona());
    }

    /**
     * @param array<string, mixed> $cita
     */
    public function resolverInicio(array $cita): ?DateTimeImmutable
    {
        $fecha = trim((string) ($cita['FechaCita'] ?? ''));
        $hora = trim((string) ($cita['HraInicioCita'] ?? ''));

        if ($fecha === '' || $hora === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
            $hora .= ':00';
        }

        $inicio = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $fecha . ' ' . $hora,
            $this->zona()
        );

        return $inicio instanceof DateTimeImmutable ? $inicio : null;
    }

    /**
     * Fin de cita: HraFinCita o inicio + DuracionAplicadaMin.
     *
     * @param array<string, mixed> $cita
     */
    public function resolverFin(array $cita): ?DateTimeImmutable
    {
        $inicio = $this->resolverInicio($cita);
        if ($inicio === null) {
            return null;
        }

        $fecha = trim((string) ($cita['FechaCita'] ?? ''));
        $horaFin = trim((string) ($cita['HraFinCita'] ?? ''));

        if ($horaFin !== '') {
            if (preg_match('/^\d{2}:\d{2}$/', $horaFin)) {
                $horaFin .= ':00';
            }

            $fin = DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $fecha . ' ' . $horaFin,
                $this->zona()
            );

            if ($fin instanceof DateTimeImmutable) {
                return $fin;
            }
        }

        $duracion = (int) ($cita['DuracionAplicadaMin'] ?? 0);
        if ($duracion <= 0 || $duracion > 24 * 60) {
            return null;
        }

        return $inicio->modify('+' . $duracion . ' minutes');
    }

    /**
     * @param array<string, mixed> $cita
     */
    public function finDelDia(array $cita): ?DateTimeImmutable
    {
        $fecha = trim((string) ($cita['FechaCita'] ?? ''));
        if ($fecha === '') {
            return null;
        }

        $finDia = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $fecha . ' 23:59:59',
            $this->zona()
        );

        return $finDia instanceof DateTimeImmutable ? $finDia : null;
    }

    /**
     * Indicadores para UI (no sustituyen la validación transaccional).
     *
     * @param array<string, mixed> $cita
     * @return array{
     *   puedeMarcarAsistida: bool,
     *   puedeMarcarInasistencia: bool,
     *   motivoBloqueoAsistencia: string,
     *   codigo: string,
     *   horaInicioLegible: string,
     *   horaFinLegible: string
     * }
     */
    public function evaluarIndicadores(
        array $cita,
        ?DateTimeImmutable $ahora = null
    ): array {
        $ahora = $this->ahora($ahora);
        $estado = strtoupper(trim((string) ($cita['EstadoCita'] ?? '')));

        $base = [
            'puedeMarcarAsistida' => false,
            'puedeMarcarInasistencia' => false,
            'motivoBloqueoAsistencia' => '',
            'codigo' => self::CODIGO_ESTADO_INVALIDO,
            'horaInicioLegible' => '',
            'horaFinLegible' => ''
        ];

        if ($estado !== 'PROGRAMADA') {
            $base['motivoBloqueoAsistencia'] =
                'Esta cita ya tiene un resultado registrado y no puede modificarse.';
            return $base;
        }

        $inicio = $this->resolverInicio($cita);
        $fin = $this->resolverFin($cita);
        $finDia = $this->finDelDia($cita);

        if ($inicio === null || $fin === null || $finDia === null) {
            $base['codigo'] = self::CODIGO_HORARIO_INVALIDO;
            $base['motivoBloqueoAsistencia'] =
                'No fue posible validar el horario de la cita.';
            return $base;
        }

        $base['horaInicioLegible'] = $inicio->format('H:i');
        $base['horaFinLegible'] = $fin->format('H:i');

        if ($ahora->format('Y-m-d') < $inicio->format('Y-m-d')) {
            $base['codigo'] = self::CODIGO_FUTURA;
            $base['motivoBloqueoAsistencia'] =
                'No puedes registrar la asistencia antes del día de la cita.';
            return $base;
        }

        if ($ahora > $finDia) {
            $base['codigo'] = self::CODIGO_DIA_CERRADO;
            $base['motivoBloqueoAsistencia'] =
                'El periodo para registrar el resultado de esta cita ya terminó.';
            return $base;
        }

        if ($ahora < $inicio) {
            $base['codigo'] = self::CODIGO_ANTES_INICIO;
            $base['motivoBloqueoAsistencia'] =
                'Podrás registrar la asistencia a partir de las '
                . $inicio->format('H:i')
                . '.';
            return $base;
        }

        if ($ahora < $fin) {
            $base['codigo'] = self::CODIGO_DURANTE_SESION;
            $base['puedeMarcarAsistida'] = true;
            $base['motivoBloqueoAsistencia'] =
                'La inasistencia solo puede registrarse después de finalizar el horario de la cita.';
            return $base;
        }

        // Desde HraFinCita hasta 23:59:59 del mismo día.
        $base['codigo'] = self::CODIGO_OK;
        $base['puedeMarcarAsistida'] = true;
        $base['puedeMarcarInasistencia'] = true;
        $base['motivoBloqueoAsistencia'] = '';

        return $base;
    }

    /**
     * Validación definitiva por acción solicitada.
     *
     * @param array<string, mixed> $cita
     * @return array{ok: bool, codigo: string, mensaje: string}
     */
    public function validarAccion(
        array $cita,
        string $accion,
        ?DateTimeImmutable $ahora = null
    ): array {
        $accion = strtoupper(trim($accion));

        if (!in_array($accion, ['ASISTIDA', 'INASISTENCIA'], true)) {
            return [
                'ok' => false,
                'codigo' => self::CODIGO_ACCION_INVALIDA,
                'mensaje' =>
                    'Esta cita ya tiene un resultado registrado y no puede modificarse.'
            ];
        }

        $ind = $this->evaluarIndicadores($cita, $ahora);

        if ($accion === 'ASISTIDA' && !empty($ind['puedeMarcarAsistida'])) {
            return [
                'ok' => true,
                'codigo' => self::CODIGO_OK,
                'mensaje' => ''
            ];
        }

        if (
            $accion === 'INASISTENCIA'
            && !empty($ind['puedeMarcarInasistencia'])
        ) {
            return [
                'ok' => true,
                'codigo' => self::CODIGO_OK,
                'mensaje' => ''
            ];
        }

        $mensaje = (string) ($ind['motivoBloqueoAsistencia'] ?? '');
        if ($mensaje === '') {
            $mensaje = 'No es posible registrar el resultado en este momento.';
        }

        // Mapear a códigos esperados por el controlador.
        $codigoHttp = match ((string) ($ind['codigo'] ?? '')) {
            self::CODIGO_FUTURA,
            self::CODIGO_ANTES_INICIO => 'CITA_NO_INICIADA',
            self::CODIGO_DURANTE_SESION => 'CITA_DURANTE_SESION',
            self::CODIGO_DIA_CERRADO => 'CITA_DIA_CERRADO',
            self::CODIGO_HORARIO_INVALIDO => 'FECHA_INVALIDA',
            default => 'TRANSICION_NO_PERMITIDA'
        };

        return [
            'ok' => false,
            'codigo' => $codigoHttp,
            'mensaje' => $mensaje
        ];
    }
}
