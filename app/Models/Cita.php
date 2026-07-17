<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Cita extends Model
{
    public function obtenerPorConsultorio(
        string $clvCons,
        ?string $clvPsi = null,
        ?string $estado = null
    ): array {
        $sql = "SELECT
                    c.ClvCita,
                    c.FechaCita,
                    c.HraInicioCita,
                    c.HraFinCita,
                    c.EstadoCita,
                    c.NotasCita,
                    c.MotivoCancelacion,
                    c.FechaCancelacion,
                    c.ClvPac,
                    c.ClvPsi,
                    c.ClvServ,

                    CONCAT(
                        perPac.NombrePer,
                        ' ',
                        perPac.ApPatPer,
                        ' ',
                        COALESCE(perPac.ApMatPer, '')
                    ) AS NombrePaciente,

                    CONCAT(
                        perPsi.NombrePer,
                        ' ',
                        perPsi.ApPatPer,
                        ' ',
                        COALESCE(perPsi.ApMatPer, '')
                    ) AS NombrePsicologo,

                    s.NombreServicio

                FROM cita c

                INNER JOIN paciente pac
                    ON c.ClvPac = pac.ClvPac

                INNER JOIN usuario usuPac
                    ON pac.ClvUsu = usuPac.ClvUsu

                INNER JOIN persona perPac
                    ON usuPac.ClvPer = perPac.ClvPer

                INNER JOIN psicologo psi
                    ON c.ClvPsi = psi.ClvPsi

                INNER JOIN usuario usuPsi
                    ON psi.ClvUsu = usuPsi.ClvUsu

                INNER JOIN persona perPsi
                    ON usuPsi.ClvPer = perPsi.ClvPer

                INNER JOIN servicios s
                    ON c.ClvServ = s.ClvServ

                WHERE c.ClvCons = :clvCons";

        $parametros = [
            'clvCons' => $clvCons
        ];

        if ($clvPsi !== null && $clvPsi !== '') {
            $sql .= " AND c.ClvPsi = :clvPsi";
            $parametros['clvPsi'] = $clvPsi;
        }

        if ($estado !== null && $estado !== '') {
            $sql .= " AND c.EstadoCita = :estado";
            $parametros['estado'] = $estado;
        }

        $sql .= " ORDER BY c.FechaCita, c.HraInicioCita";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function contarPacientesActivos(
    string $clvPsi
): int {
    $sql = "SELECT COUNT(DISTINCT ClvPac)
            FROM cita
            WHERE ClvPsi = :clvPsi
              AND EstadoCita <> 'CANCELADA'";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'clvPsi' => $clvPsi
    ]);

    return (int) $stmt->fetchColumn();
}
   public function contarCitasHoy(
    string $clvPsi
): int {
    $sql = "SELECT COUNT(*)
            FROM cita
            WHERE ClvPsi = :clvPsi
              AND FechaCita = CURDATE()
              AND EstadoCita = 'PROGRAMADA'";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'clvPsi' => $clvPsi
    ]);

    return (int) $stmt->fetchColumn();
}
public function contarCitasSemana(
    string $clvPsi
): int {
    $sql = "SELECT COUNT(*)
            FROM cita
            WHERE ClvPsi = :clvPsi
              AND YEARWEEK(
                    FechaCita,
                    1
                  ) = YEARWEEK(
                    CURDATE(),
                    1
                  )
              AND EstadoCita = 'PROGRAMADA'";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'clvPsi' => $clvPsi
    ]);

    return (int) $stmt->fetchColumn();
}
public function obtenerProximasCitas(
    string $clvPsi,
    int $limite = 5
): array {
    $sql = "SELECT
                c.ClvCita,
                c.FechaCita,
                c.HraInicioCita,
                c.HraFinCita,
                c.EstadoCita,
                c.NotasCita,

                CONCAT(
                    per.NombrePer,
                    ' ',
                    per.ApPatPer,
                    ' ',
                    per.ApMatPer
                ) AS NombrePaciente,

                ser.NombreServicio

            FROM cita c

            INNER JOIN paciente pac
                ON c.ClvPac = pac.ClvPac

            INNER JOIN usuario usu
                ON pac.ClvUsu = usu.ClvUsu

            INNER JOIN persona per
                ON usu.ClvPer = per.ClvPer

            INNER JOIN servicios ser
                ON c.ClvServ = ser.ClvServ

            WHERE c.ClvPsi = :clvPsi
              AND c.EstadoCita = 'PROGRAMADA'
              AND (
                    c.FechaCita > CURDATE()
                    OR (
                        c.FechaCita = CURDATE()
                        AND c.HraInicioCita >= CURTIME()
                    )
              )

            ORDER BY
                c.FechaCita ASC,
                c.HraInicioCita ASC

            LIMIT :limite";

    $stmt = $this->db->prepare($sql);

    $stmt->bindValue(
        ':clvPsi',
        $clvPsi,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':limite',
        $limite,
        PDO::PARAM_INT
    );

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
}