<?php

namespace App\Models;

use App\Core\Model;
use App\Services\ClaveService;
use PDO;

class DisponibilidadPsicologo extends Model
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

    public function obtenerPorPsicologo(
        string $clvPsi
    ): array {
        $orden = implode(
            "','",
            self::ORDEN_DIAS
        );

        $sql = "SELECT
                    ClvDisponibilidad,
                    DiaSemana,
                    HoraInicio,
                    HoraFin,
                    EstatusDisponibilidad,
                    FechaRegistroDisponibilidad,
                    ClvPsi
                FROM disponibilidad_psicologo
                WHERE ClvPsi = :clvPsi
                ORDER BY
                    FIELD(DiaSemana, '{$orden}'),
                    HoraInicio";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorPsicologoYDia(
        string $clvPsi,
        string $dia
    ): array {
        $sql = "SELECT
                    ClvDisponibilidad,
                    DiaSemana,
                    HoraInicio,
                    HoraFin,
                    EstatusDisponibilidad,
                    FechaRegistroDisponibilidad,
                    ClvPsi
                FROM disponibilidad_psicologo
                WHERE ClvPsi = :clvPsi
                  AND DiaSemana = :dia
                ORDER BY HoraInicio";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi,
            'dia' => strtoupper(trim($dia))
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerActivasPorPsicologoYDia(
        string $clvPsi,
        string $dia
    ): array {
        $sql = "SELECT
                    ClvDisponibilidad,
                    DiaSemana,
                    HoraInicio,
                    HoraFin,
                    EstatusDisponibilidad,
                    FechaRegistroDisponibilidad,
                    ClvPsi
                FROM disponibilidad_psicologo
                WHERE ClvPsi = :clvPsi
                  AND DiaSemana = :dia
                  AND EstatusDisponibilidad = 'ACTIVA'
                ORDER BY HoraInicio";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi,
            'dia' => strtoupper(trim($dia))
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function perteneceAlPsicologo(
        string $clvDisponibilidad,
        string $clvPsi
    ): bool {
        $sql = "SELECT COUNT(*)
                FROM disponibilidad_psicologo
                WHERE ClvDisponibilidad = :clave
                  AND ClvPsi = :clvPsi";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clave' => $clvDisponibilidad,
            'clvPsi' => $clvPsi
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function existeSolapamiento(
        string $clvPsi,
        string $dia,
        string $horaInicio,
        string $horaFin,
        ?string $excluirClave = null
    ): bool {
        $sql = "SELECT COUNT(*)
                FROM disponibilidad_psicologo
                WHERE ClvPsi = :clvPsi
                  AND DiaSemana = :dia
                  AND EstatusDisponibilidad = 'ACTIVA'
                  AND :nuevoInicio < HoraFin
                  AND :nuevoFin > HoraInicio";

        $parametros = [
            'clvPsi' => $clvPsi,
            'dia' => strtoupper(trim($dia)),
            'nuevoInicio' => $horaInicio,
            'nuevoFin' => $horaFin
        ];

        if ($excluirClave !== null && $excluirClave !== '') {
            $sql .= " AND ClvDisponibilidad <> :excluirClave";
            $parametros['excluirClave'] = $excluirClave;
        }

        $stmt = $this->db->prepare($sql);

        $stmt->execute($parametros);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function crear(array $datos): bool
    {
        $clave = ClaveService::generar(
            'disponibilidad_psicologo',
            'ClvDisponibilidad',
            'DPO'
        );

        $sql = "INSERT INTO disponibilidad_psicologo (
                    ClvDisponibilidad,
                    DiaSemana,
                    HoraInicio,
                    HoraFin,
                    EstatusDisponibilidad,
                    ClvPsi
                ) VALUES (
                    :clave,
                    :dia,
                    :horaInicio,
                    :horaFin,
                    :estatus,
                    :clvPsi
                )";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'clave' => $clave,
            'dia' => strtoupper(trim($datos['DiaSemana'])),
            'horaInicio' => $datos['HoraInicio'],
            'horaFin' => $datos['HoraFin'],
            'estatus' => $datos['EstatusDisponibilidad'] ?? 'ACTIVA',
            'clvPsi' => $datos['ClvPsi']
        ]);
    }

    public function actualizar(
        string $clvDisponibilidad,
        string $clvPsi,
        array $datos
    ): bool {
        $campos = [];
        $parametros = [
            'clave' => $clvDisponibilidad,
            'clvPsi' => $clvPsi
        ];

        if (array_key_exists('DiaSemana', $datos)) {
            $campos[] = 'DiaSemana = :dia';
            $parametros['dia'] =
                strtoupper(trim($datos['DiaSemana']));
        }

        if (array_key_exists('HoraInicio', $datos)) {
            $campos[] = 'HoraInicio = :horaInicio';
            $parametros['horaInicio'] = $datos['HoraInicio'];
        }

        if (array_key_exists('HoraFin', $datos)) {
            $campos[] = 'HoraFin = :horaFin';
            $parametros['horaFin'] = $datos['HoraFin'];
        }

        if (array_key_exists('EstatusDisponibilidad', $datos)) {
            $campos[] =
                'EstatusDisponibilidad = :estatus';
            $parametros['estatus'] =
                $datos['EstatusDisponibilidad'];
        }

        if ($campos === []) {
            return false;
        }

        $sql = "UPDATE disponibilidad_psicologo
                SET " . implode(', ', $campos) . "
                WHERE ClvDisponibilidad = :clave
                  AND ClvPsi = :clvPsi";

        $stmt = $this->db->prepare($sql);

        $stmt->execute($parametros);

        return $stmt->rowCount() > 0;
    }

    public function contarActivasIncompatiblesConHorario(
        string $clvCons,
        string $diaSemana,
        ?string $horaInicio,
        ?string $horaFin,
        bool $diaInactivo = false
    ): int {
        return (int) ($this->detalleActivasIncompatiblesConHorario(
            $clvCons,
            $diaSemana,
            $horaInicio,
            $horaFin,
            $diaInactivo
        )['total'] ?? 0);
    }

    /**
     * @return array{total:int, maxHoraFin:?string, minHoraInicio:?string}
     */
    public function detalleActivasIncompatiblesConHorario(
        string $clvCons,
        string $diaSemana,
        ?string $horaInicio,
        ?string $horaFin,
        bool $diaInactivo = false
    ): array {
        $sql = "SELECT
                    COUNT(*) AS total,
                    MAX(dp.HoraFin) AS maxHoraFin,
                    MIN(dp.HoraInicio) AS minHoraInicio
                FROM disponibilidad_psicologo dp
                INNER JOIN psicologo p
                    ON p.ClvPsi = dp.ClvPsi
                WHERE p.ClvCons = :clvCons
                  AND dp.DiaSemana = :dia
                  AND dp.EstatusDisponibilidad = 'ACTIVA'";

        $parametros = [
            'clvCons' => trim($clvCons),
            'dia' => strtoupper(trim($diaSemana))
        ];

        if (!$diaInactivo) {
            $sql .= "
                  AND (
                        dp.HoraInicio < :horaInicio
                        OR dp.HoraFin > :horaFin
                  )";

            $parametros['horaInicio'] = $horaInicio;
            $parametros['horaFin'] = $horaFin;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($fila['total'] ?? 0),
            'maxHoraFin' => isset($fila['maxHoraFin'])
                ? substr((string) $fila['maxHoraFin'], 0, 5)
                : null,
            'minHoraInicio' => isset($fila['minHoraInicio'])
                ? substr((string) $fila['minHoraInicio'], 0, 5)
                : null
        ];
    }

    public function cambiarEstatus(
        string $clvDisponibilidad,
        string $clvPsi,
        string $estatus
    ): bool {
        $sql = "UPDATE disponibilidad_psicologo
                SET EstatusDisponibilidad = :estatus
                WHERE ClvDisponibilidad = :clave
                  AND ClvPsi = :clvPsi";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'estatus' => $estatus,
            'clave' => $clvDisponibilidad,
            'clvPsi' => $clvPsi
        ]);

        return $stmt->rowCount() > 0;
    }
}
