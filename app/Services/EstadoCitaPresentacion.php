<?php

namespace App\Services;

/**
 * Presentación operativa uniforme de EstadoCita.
 * No incluye información clínica.
 */
class EstadoCitaPresentacion
{
    public const ESTADOS = [
        'PROGRAMADA',
        'ASISTIDA',
        'CANCELADA',
        'INASISTENCIA'
    ];

    /**
     * @return array<string, string>
     */
    public static function etiquetas(): array
    {
        return [
            'PROGRAMADA' => 'Programada',
            'ASISTIDA' => 'Asistida',
            'CANCELADA' => 'Cancelada',
            'INASISTENCIA' => 'Inasistencia'
        ];
    }

    public static function etiqueta(?string $estado): string
    {
        $estado = strtoupper(trim((string) $estado));
        $etiquetas = self::etiquetas();

        return $etiquetas[$estado] ?? ($estado !== '' ? $estado : '—');
    }

    public static function esValido(?string $estado): bool
    {
        return in_array(
            strtoupper(trim((string) $estado)),
            self::ESTADOS,
            true
        );
    }

    public static function claseCss(?string $estado, string $prefijo = 'is'): string
    {
        $estado = strtolower(trim((string) $estado));

        if ($estado === '') {
            return $prefijo . '-programada';
        }

        return $prefijo . '-' . $estado;
    }

    public static function iconoBootstrap(?string $estado): string
    {
        return match (strtoupper(trim((string) $estado))) {
            'ASISTIDA' => 'bi-check-circle',
            'CANCELADA' => 'bi-x-circle',
            'INASISTENCIA' => 'bi-person-x',
            'PROGRAMADA' => 'bi-calendar-check',
            default => 'bi-calendar'
        };
    }

    /**
     * Nota operativa derivada (paciente).
     */
    public static function notaPaciente(
        ?string $estado,
        bool $programadaYaInicio = false
    ): string {
        $estado = strtoupper(trim((string) $estado));

        return match ($estado) {
            'PROGRAMADA' => $programadaYaInicio
                ? 'Pendiente de confirmación por el especialista.'
                : 'Tu cita está programada.',
            'ASISTIDA' => 'La asistencia fue confirmada por el especialista.',
            'CANCELADA' => 'Esta cita fue cancelada.',
            'INASISTENCIA' => 'El especialista registró que no hubo asistencia.',
            default => ''
        };
    }

    /**
     * Nota operativa derivada (psicólogo).
     */
    public static function notaPsicologo(?string $estado): string
    {
        return match (strtoupper(trim((string) $estado))) {
            'PROGRAMADA' => 'La cita está programada.',
            'ASISTIDA' => 'Registraste la asistencia de esta cita.',
            'CANCELADA' => 'La cita fue cancelada.',
            'INASISTENCIA' => 'Registraste que el paciente no asistió.',
            default => ''
        };
    }

    public static function programadaYaInicio(
        ?string $fechaCita,
        ?string $horaInicio,
        ?\DateTimeImmutable $ahora = null
    ): bool {
        $fecha = trim((string) $fechaCita);
        $hora = trim((string) $horaInicio);

        if ($fecha === '' || $hora === '') {
            return false;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
            $hora .= ':00';
        }

        $zona = new \DateTimeZone('America/Mexico_City');
        $inicio = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $fecha . ' ' . substr($hora, 0, 8),
            $zona
        );

        if (!$inicio instanceof \DateTimeImmutable) {
            return false;
        }

        $ahora ??= new \DateTimeImmutable('now', $zona);

        return $inicio <= $ahora;
    }
}
