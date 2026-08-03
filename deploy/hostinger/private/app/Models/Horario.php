<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Horario extends Model
{
    /*
    =====================================
            HORARIOS DEL PSICÓLOGO
    =====================================
    */

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
                    'SABADO',
                    'DOMINGO'
                ),
                HoraInicio";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /*
    =====================================
            HORARIO DE UN DÍA
    =====================================
    */

    public function obtenerHorarioDia(
        string $clvPsi,
        string $diaSemana
    ): ?array {

        $sql = "SELECT

                    ClvHorario,
                    DiaSemana,
                    HoraInicio,
                    HoraFin

                FROM horario

                WHERE ClvPsi = :clvPsi

                  AND DiaSemana = :diaSemana

                  AND EstatusHorario = 'ACTIVO'

                ORDER BY HoraInicio

                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi,
            'diaSemana' => strtoupper(trim($diaSemana))
        ]);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return $resultado ?: null;
    }


    /*
    =====================================
        VERIFICAR SI TRABAJA ESE DÍA
    =====================================
    */

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
            'diaSemana' => strtoupper(trim($diaSemana))
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }


    /*
    =====================================
        OBTENER HORARIOS DEL DÍA
    =====================================
    
    Permite obtener directamente el rango
    de trabajo del psicólogo para un día.
    */

    public function obtenerHorariosDelDia(
        string $clvPsi,
        string $diaSemana
    ): array {

        $sql = "SELECT

                    ClvHorario,
                    DiaSemana,
                    HoraInicio,
                    HoraFin

                FROM horario

                WHERE ClvPsi = :clvPsi

                  AND DiaSemana = :diaSemana

                  AND EstatusHorario = 'ACTIVO'

                ORDER BY HoraInicio";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi,
            'diaSemana' => strtoupper(trim($diaSemana))
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}