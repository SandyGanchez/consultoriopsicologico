<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Validación y clasificación de fechas de nacimiento.
 * Zona horaria: America/Mexico_City.
 */
class EdadService
{
    public const CLASIFICACION_MAYOR = 'MAYOR_DE_EDAD';

    public const CLASIFICACION_MENOR = 'MENOR_DE_EDAD';

    public const ANIOS_MAXIMOS = 120;

    public const ANIOS_MAYORIA = 18;

    public const MENSAJE_OBLIGATORIA =
        'La fecha de nacimiento es obligatoria.';

    public const MENSAJE_FORMATO =
        'La fecha de nacimiento no tiene un formato válido.';

    public const MENSAJE_NO_REAL =
        'La fecha de nacimiento no corresponde a una fecha real.';

    public const MENSAJE_FUTURA =
        'La fecha de nacimiento no puede ser futura.';

    public const MENSAJE_LIMITE =
        'La edad registrada supera el límite permitido.';

    public const MENSAJE_MAYORIA =
        'Debes tener al menos 18 años.';

    public const MENSAJE_MENOR_DETECTADO =
        'El paciente fue identificado como menor de edad.';

    public const MENSAJE_REGISTRO_PUBLICO_MENOR =
        'El registro en línea está disponible para personas mayores de edad. '
        . 'Para registrar a un paciente menor, comunícate con el consultorio.';

    public const MENSAJE_ALTA_MENOR_PSICOLOGO =
        'El paciente fue registrado como menor de edad. El expediente clínico y el '
        . 'consentimiento permanecerán restringidos hasta registrar la autorización '
        . 'de su representante legal.';

    private DateTimeZone $zona;

    public function __construct(?DateTimeZone $zona = null)
    {
        $this->zona = $zona ?? new DateTimeZone('America/Mexico_City');
    }

    public function hoy(): DateTimeImmutable
    {
        return new DateTimeImmutable('today', $this->zona);
    }

    /**
     * @return array{
     *   ok: bool,
     *   mensaje?: string,
     *   fecha?: string,
     *   edad?: int,
     *   clasificacion?: string,
     *   es_mayor?: bool
     * }
     */
    public function validarFechaNacimiento(
        ?string $fecha,
        string $politica = 'general'
    ): array {
        $fecha = trim((string) $fecha);
        $politica = strtolower(trim($politica));

        if ($fecha === '') {
            return [
                'ok' => false,
                'mensaje' => self::MENSAJE_OBLIGATORIA
            ];
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return [
                'ok' => false,
                'mensaje' => self::MENSAJE_FORMATO
            ];
        }

        $nacimiento = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $fecha,
            $this->zona
        );

        $errores = DateTimeImmutable::getLastErrors();

        if (
            !$nacimiento instanceof DateTimeImmutable
            || (
                is_array($errores)
                && (
                    ($errores['warning_count'] ?? 0) > 0
                    || ($errores['error_count'] ?? 0) > 0
                )
            )
            || $nacimiento->format('Y-m-d') !== $fecha
        ) {
            return [
                'ok' => false,
                'mensaje' => self::MENSAJE_NO_REAL
            ];
        }

        $hoy = $this->hoy();

        if ($nacimiento > $hoy) {
            return [
                'ok' => false,
                'mensaje' => self::MENSAJE_FUTURA
            ];
        }

        $minima = $this->obtenerFechaMinimaPermitida();
        if ($fecha < $minima) {
            return [
                'ok' => false,
                'mensaje' => self::MENSAJE_LIMITE
            ];
        }

        $edad = $this->calcularEdadExactaDesdeFecha($nacimiento);
        $esMayor = $edad >= self::ANIOS_MAYORIA;
        $clasificacion = $esMayor
            ? self::CLASIFICACION_MAYOR
            : self::CLASIFICACION_MENOR;

        if (in_array($politica, ['adulto', 'adultos', 'mayor'], true) && !$esMayor) {
            return [
                'ok' => false,
                'mensaje' => self::MENSAJE_MAYORIA,
                'fecha' => $fecha,
                'edad' => $edad,
                'clasificacion' => $clasificacion,
                'es_mayor' => false
            ];
        }

        if (
            in_array($politica, ['registro_publico', 'registro-publico'], true)
            && !$esMayor
        ) {
            return [
                'ok' => false,
                'mensaje' => self::MENSAJE_REGISTRO_PUBLICO_MENOR,
                'fecha' => $fecha,
                'edad' => $edad,
                'clasificacion' => $clasificacion,
                'es_mayor' => false
            ];
        }

        return [
            'ok' => true,
            'fecha' => $fecha,
            'edad' => $edad,
            'clasificacion' => $clasificacion,
            'es_mayor' => $esMayor
        ];
    }

    public function calcularEdadExacta(string $fecha): int
    {
        $validacion = $this->validarFechaNacimiento($fecha, 'general');

        if (!$validacion['ok']) {
            throw new \InvalidArgumentException(
                (string) ($validacion['mensaje'] ?? self::MENSAJE_FORMATO)
            );
        }

        return (int) $validacion['edad'];
    }

    public function esMayorDeEdad(string $fecha): bool
    {
        $validacion = $this->validarFechaNacimiento($fecha, 'general');

        return !empty($validacion['ok']) && !empty($validacion['es_mayor']);
    }

    public function clasificarEdad(string $fecha): string
    {
        $validacion = $this->validarFechaNacimiento($fecha, 'general');

        if (!$validacion['ok']) {
            throw new \InvalidArgumentException(
                (string) ($validacion['mensaje'] ?? self::MENSAJE_FORMATO)
            );
        }

        return (string) $validacion['clasificacion'];
    }

    public function obtenerFechaMinimaPermitida(): string
    {
        return $this->hoy()
            ->modify('-' . self::ANIOS_MAXIMOS . ' years')
            ->format('Y-m-d');
    }

    public function obtenerFechaMaximaAdulto(): string
    {
        return $this->hoy()
            ->modify('-' . self::ANIOS_MAYORIA . ' years')
            ->format('Y-m-d');
    }

    public function obtenerFechaMaximaGeneral(): string
    {
        return $this->hoy()->format('Y-m-d');
    }

    /**
     * @return array{min: string, max: string}
     */
    public function limitesInput(string $politica = 'paciente'): array
    {
        $politica = strtolower(trim($politica));

        if (in_array($politica, ['adulto', 'adultos', 'mayor'], true)) {
            return [
                'min' => $this->obtenerFechaMinimaPermitida(),
                'max' => $this->obtenerFechaMaximaAdulto()
            ];
        }

        return [
            'min' => $this->obtenerFechaMinimaPermitida(),
            'max' => $this->obtenerFechaMaximaGeneral()
        ];
    }

    private function calcularEdadExactaDesdeFecha(
        DateTimeImmutable $nacimiento
    ): int {
        $hoy = $this->hoy();
        $diff = $nacimiento->diff($hoy);

        if ($diff->invert === 1) {
            return 0;
        }

        return max(0, (int) $diff->y);
    }
}
