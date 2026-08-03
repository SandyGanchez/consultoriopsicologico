<?php

namespace App\Models;

use App\Core\Model;
use App\Services\ClaveService;
use PDO;

class HorarioConsultorio extends Model
{
    private const ORDEN_DIAS = [
        'LUNES',
        'MARTES',
        'MIERCOLES',
        'JUEVES',
        'VIERNES',
        'SABADO',
        'DOMINGO'
    ];

    public function obtenerPorConsultorio(
        string $clvCons
    ): array {
        $orden = implode(
            "','",
            self::ORDEN_DIAS
        );

        $sql = "SELECT
                    ClvHorarioCons,
                    DiaSemana,
                    HoraInicio,
                    HoraFin,
                    EstatusHorario,
                    ClvCons
                FROM horario_consultorio
                WHERE ClvCons = :clvCons
                ORDER BY FIELD(DiaSemana, '{$orden}')";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => $clvCons
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorConsultorioYDia(
        string $clvCons,
        string $dia
    ): ?array {
        $sql = "SELECT
                    ClvHorarioCons,
                    DiaSemana,
                    HoraInicio,
                    HoraFin,
                    EstatusHorario,
                    ClvCons
                FROM horario_consultorio
                WHERE ClvCons = :clvCons
                  AND DiaSemana = :dia
                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => $clvCons,
            'dia' => strtoupper(trim($dia))
        ]);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return $resultado ?: null;
    }

    public function perteneceAlConsultorio(
        string $clvHorarioCons,
        string $clvCons
    ): bool {
        $sql = "SELECT COUNT(*)
                FROM horario_consultorio
                WHERE ClvHorarioCons = :clave
                  AND ClvCons = :clvCons";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clave' => $clvHorarioCons,
            'clvCons' => $clvCons
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Crea únicamente los días faltantes para un consultorio.
     * No modifica ni duplica filas existentes.
     *
     * @param array{HoraInicio?:string,HoraFin?:string,EstatusHorario?:string} $valoresPorDefecto
     */
    public function crearDiasFaltantes(
        string $clvCons,
        array $valoresPorDefecto = []
    ): int {
        $clvCons = trim($clvCons);

        if ($clvCons === '') {
            return 0;
        }

        $horaInicio = $valoresPorDefecto['HoraInicio'] ?? '09:00:00';
        $horaFin = $valoresPorDefecto['HoraFin'] ?? '18:00:00';
        $estatus = $valoresPorDefecto['EstatusHorario'] ?? 'ACTIVO';

        $insertados = 0;

        foreach (self::diasPermitidos() as $dia) {
            if ($this->obtenerPorConsultorioYDia($clvCons, $dia) !== null) {
                continue;
            }

            $clave = ClaveService::generar(
                'horario_consultorio',
                'ClvHorarioCons',
                'HCO'
            );

            $estatusDia = in_array($dia, ['SABADO', 'DOMINGO'], true)
                ? 'INACTIVO'
                : $estatus;

            $sql = "INSERT INTO horario_consultorio (
                        ClvHorarioCons,
                        DiaSemana,
                        HoraInicio,
                        HoraFin,
                        EstatusHorario,
                        ClvCons
                    ) VALUES (
                        :clave,
                        :dia,
                        :horaInicio,
                        :horaFin,
                        :estatus,
                        :clvCons
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'clave' => $clave,
                'dia' => $dia,
                'horaInicio' => $horaInicio,
                'horaFin' => $horaFin,
                'estatus' => $estatusDia,
                'clvCons' => $clvCons,
            ]);

            $insertados++;
        }

        return $insertados;
    }

    public function actualizar(
        string $clvHorarioCons,
        string $clvCons,
        array $datos
    ): bool {
        $campos = [];
        $parametros = [
            'clave' => $clvHorarioCons,
            'clvCons' => $clvCons
        ];

        if (array_key_exists('HoraInicio', $datos)) {
            $campos[] = 'HoraInicio = :horaInicio';
            $parametros['horaInicio'] = $datos['HoraInicio'];
        }

        if (array_key_exists('HoraFin', $datos)) {
            $campos[] = 'HoraFin = :horaFin';
            $parametros['horaFin'] = $datos['HoraFin'];
        }

        if (array_key_exists('EstatusHorario', $datos)) {
            $campos[] = 'EstatusHorario = :estatus';
            $parametros['estatus'] = $datos['EstatusHorario'];
        }

        if ($campos === []) {
            return false;
        }

        $sql = "UPDATE horario_consultorio
                SET " . implode(', ', $campos) . "
                WHERE ClvHorarioCons = :clave
                  AND ClvCons = :clvCons";

        $stmt = $this->db->prepare($sql);

        // execute OK = éxito. rowCount()===0 solo indica que no hubo cambio
        // de valores (MySQL), no un fallo.
        return $stmt->execute($parametros);
    }

    /**
     * Actualiza los siete días en una sola transacción.
     *
     * @param list<array{
     *   ClvHorarioCons: string,
     *   HoraInicio: ?string,
     *   HoraFin: ?string,
     *   EstatusHorario: string
     * }> $dias
     */
    public function actualizarSemana(
        string $clvCons,
        array $dias
    ): void {
        $clvCons = trim($clvCons);

        if ($clvCons === '' || $dias === []) {
            throw new \InvalidArgumentException(
                'No hay horarios para actualizar.'
            );
        }

        $propia = !$this->db->inTransaction();

        try {
            if ($propia) {
                $this->db->beginTransaction();
            }

            foreach ($dias as $dia) {
                $clave = trim((string) ($dia['ClvHorarioCons'] ?? ''));

                if (
                    $clave === ''
                    || !$this->perteneceAlConsultorio($clave, $clvCons)
                ) {
                    throw new \RuntimeException(
                        'Horario inválido para el consultorio.'
                    );
                }

                $payload = [
                    'EstatusHorario' => (string) ($dia['EstatusHorario'] ?? 'INACTIVO')
                ];

                if (
                    array_key_exists('HoraInicio', $dia)
                    && $dia['HoraInicio'] !== null
                    && trim((string) $dia['HoraInicio']) !== ''
                ) {
                    $payload['HoraInicio'] = $dia['HoraInicio'];
                }

                if (
                    array_key_exists('HoraFin', $dia)
                    && $dia['HoraFin'] !== null
                    && trim((string) $dia['HoraFin']) !== ''
                ) {
                    $payload['HoraFin'] = $dia['HoraFin'];
                }

                $ok = $this->actualizar($clave, $clvCons, $payload);

                if (!$ok) {
                    throw new \RuntimeException(
                        'No fue posible actualizar un día del horario.'
                    );
                }
            }

            if ($propia) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($propia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public static function diasPermitidos(): array
    {
        return self::ORDEN_DIAS;
    }

    public static function etiquetaDia(string $dia): string
    {
        $etiquetas = [
            'LUNES' => 'Lunes',
            'MARTES' => 'Martes',
            'MIERCOLES' => 'Miércoles',
            'JUEVES' => 'Jueves',
            'VIERNES' => 'Viernes',
            'SABADO' => 'Sábado',
            'DOMINGO' => 'Domingo'
        ];

        return $etiquetas[strtoupper(trim($dia))] ?? $dia;
    }

    public function obtenerHorarioActivoPorConsultorioYDia(
        string $clvCons,
        string $dia
    ): ?array {
        $horario = $this->obtenerPorConsultorioYDia(
            $clvCons,
            $dia
        );

        if (
            !$horario ||
            ($horario['EstatusHorario'] ?? '') !== 'ACTIVO'
        ) {
            return null;
        }

        return $horario;
    }
}
