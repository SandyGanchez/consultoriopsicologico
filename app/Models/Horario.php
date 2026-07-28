<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Horario extends Model
{
    public function obtenerPorPsicologo(
        string $clvPsi
    ): array {

        $sql = "SELECT
                    ClvHorario,
                    DiaSemana,
                    HoraInicio,
                    HoraFin,
                    EstatusHorario

                FROM horario

                WHERE ClvPsi = :clvPsi
                  AND EstatusHorario = 'ACTIVO'

                ORDER BY FIELD(
                    DiaSemana,
                    'LUNES',
                    'MARTES',
                    'MIERCOLES',
                    'JUEVES',
                    'VIERNES',
                    'SABADO'
                )";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerHorarioDia(
        string $clvPsi,
        string $diaSemana
    ): ?array {

        $sql = "SELECT

                    ClvHorario,
                    HoraInicio,
                    HoraFin

                FROM horario

                WHERE ClvPsi = :clvPsi
                  AND DiaSemana = :diaSemana
                  AND EstatusHorario = 'ACTIVO'

                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi,
            'diaSemana' => strtoupper($diaSemana)
        ]);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return $resultado ?: null;
    }

    public function psicologoTrabaja(
        string $clvPsi,
        string $diaSemana
    ): bool {

        $sql = "SELECT COUNT(*)

                FROM horario

                WHERE ClvPsi = :clvPsi
                  AND DiaSemana = :diaSemana
                  AND EstatusHorario = 'ACTIVO'";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi,
            'diaSemana' => strtoupper($diaSemana)
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }
}