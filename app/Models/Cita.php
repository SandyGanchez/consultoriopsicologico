<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use RuntimeException;

class Cita extends Model
{

    /*
    =====================================
            PRÓXIMA CITA
    =====================================
    */

    public function obtenerProximaCitaPaciente(
        string $clvPac
    ): ?array {

        $sql = "SELECT

                    c.*,

                    CONCAT(
                        per.NombrePer,
                        ' ',
                        per.ApPatPer,
                        ' ',
                        per.ApMatPer
                    ) AS NombrePsicologo,

                    s.NombreServicio

                FROM cita c

                INNER JOIN psicologo p
                    ON c.ClvPsi = p.ClvPsi

                INNER JOIN usuario u
                    ON p.ClvUsu = u.ClvUsu

                INNER JOIN persona per
                    ON u.ClvPer = per.ClvPer

                INNER JOIN servicios s
                    ON c.ClvServ = s.ClvServ

                WHERE

                    c.ClvPac = :clvPac

                    AND c.EstadoCita = 'PROGRAMADA'

                    AND c.FechaCita >= CURDATE()

                ORDER BY

                    c.FechaCita,

                    c.HraInicioCita

                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPac'=>$clvPac
        ]);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return $resultado ?: null;

    }

    /*
=====================================
        MIS CITAS
=====================================
*/

public function obtenerMisCitas(
    string $clvPac
): array {

    $sql = "SELECT

                c.*,

                CONCAT(

                    per.NombrePer,

                    ' ',

                    per.ApPatPer,

                    ' ',

                    per.ApMatPer

                ) AS NombrePsicologo,

                s.NombreServicio

            FROM cita c

            INNER JOIN psicologo p
                ON c.ClvPsi = p.ClvPsi

            INNER JOIN usuario u
                ON p.ClvUsu = u.ClvUsu

            INNER JOIN persona per
                ON u.ClvPer = per.ClvPer

            INNER JOIN servicios s
                ON c.ClvServ = s.ClvServ

            WHERE

                c.ClvPac = :clvPac

            ORDER BY

                c.FechaCita DESC,

                c.HraInicioCita DESC";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([

        'clvPac' => $clvPac

    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);

}

    /*
    =====================================
            HISTORIAL
    =====================================
    */

    public function obtenerHistorial(
        string $clvPac
    ): array {

        $sql = "SELECT

                    c.*,

                    CONCAT(
                        per.NombrePer,
                        ' ',
                        per.ApPatPer,
                        ' ',
                        per.ApMatPer
                    ) AS NombrePsicologo,

                    s.NombreServicio

                FROM cita c

                INNER JOIN psicologo p
                    ON c.ClvPsi = p.ClvPsi

                INNER JOIN usuario u
                    ON p.ClvUsu = u.ClvUsu

                INNER JOIN persona per
                    ON u.ClvPer = per.ClvPer

                INNER JOIN servicios s
                    ON c.ClvServ = s.ClvServ

                WHERE

                    c.ClvPac=:clvPac

                    AND c.EstadoCita<>'PROGRAMADA'

                ORDER BY

                    c.FechaCita DESC,

                    c.HraInicioCita DESC";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPac'=>$clvPac
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }

    /*
    =====================================
            HORAS OCUPADAS
    =====================================
    */

    public function obtenerHorasOcupadas(

        string $clvPsi,

        string $fecha

    ): array {

        $sql = "SELECT

                    HraInicioCita

                FROM cita

                WHERE

                    ClvPsi=:clvPsi

                    AND FechaCita=:fecha

                    AND EstadoCita='PROGRAMADA'";

        $stmt = $this->db->prepare($sql); 

        $stmt->execute([

            'clvPsi'=>$clvPsi,

            'fecha'=>$fecha

        ]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);

    }

    /*
=====================================
      VALIDAR HORARIO OCUPADO
=====================================
*/

public function existeCitaEnHorario(

    string $clvPsi,

    string $fecha,

    string $hora

): bool {

    $sql = "SELECT COUNT(*)

            FROM cita

            WHERE

                ClvPsi = :clvPsi

                AND FechaCita = :fecha

                AND HraInicioCita = :hora

                AND EstadoCita = 'PROGRAMADA'";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([

        'clvPsi' => $clvPsi,

        'fecha' => $fecha,

        'hora' => $hora

    ]);

    return (int)$stmt->fetchColumn() > 0;

}

    /*
    =====================================
            PACIENTE TIENE CITA
    =====================================
    */

public function pacienteTieneCita(

    string $clvPac,

    string $fecha,

    string $hora

): bool {

    $sql="SELECT COUNT(*)

          FROM cita

          WHERE

            ClvPac=:paciente

            AND FechaCita=:fecha

            AND HraInicioCita=:hora

            AND EstadoCita='PROGRAMADA'";

    $stmt=$this->db->prepare($sql);

    $stmt->execute([

        'paciente'=>$clvPac,

        'fecha'=>$fecha,

        'hora'=>$hora

    ]);

    return (int)$stmt->fetchColumn()>0;

}

    /*
    =====================================
            GUARDAR CITA
    =====================================
    */

public function guardar(array $datos): void
{

    if (
        $this->pacienteTieneCita(
            $datos['paciente'],
            $datos['fecha'],
            $datos['inicio']
        )
    ) {

        throw new RuntimeException(
            'Ya tienes una cita registrada en ese horario.'
        );

    }

    if (
        $this->existeCitaEnHorario(
            $datos['psicologo'],
            $datos['fecha'],
            $datos['inicio']
        )
    ) {

        throw new RuntimeException(
            'Ese horario ya fue ocupado.'
        );

    }

    $sql = "INSERT INTO cita(

                ClvCita,
                FechaCita,
                HraInicioCita,
                HraFinCita,
                DuracionAplicadaMin,
                CostoAplicado,
                EstadoCita,
                ClvPac,
                ClvPsi,
                ClvCons,
                ClvServ

            )

            VALUES(

                :clv,
                :fecha,
                :inicio,
                :fin,
                :duracion,
                :costo,
                'PROGRAMADA',
                :paciente,
                :psicologo,
                :consultorio,
                :servicio

            )";

    try {

        $stmt = $this->db->prepare($sql);

        $stmt->execute([

            'clv'          => $this->generarClave(),
            'fecha'        => $datos['fecha'],
            'inicio'       => $datos['inicio'],
            'fin'          => $datos['fin'],
            'duracion'     => $datos['duracion'],
            'costo'        => $datos['costo'],
            'paciente'     => $datos['paciente'],
            'psicologo'    => $datos['psicologo'],
            'consultorio'  => $datos['consultorio'],
            'servicio'     => $datos['servicio']

        ]);

    } catch (\Throwable $e) {

        throw new RuntimeException(
            'No fue posible registrar la cita.'
        );

    }

}

    /*
    =====================================
            CANCELAR
    =====================================
    */

    public function cancelar(
    string $clvCita,
    string $motivo
): void {

    $sql = "UPDATE cita

            SET

                EstadoCita='CANCELADA',

                MotivoCancelacion=:motivo,

                FechaCancelacion=NOW()

            WHERE

                ClvCita=:clv";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([

        'motivo' => $motivo,

        'clv' => $clvCita

    ]);

    if ($stmt->rowCount() === 0) {

        throw new RuntimeException(
            'No fue posible cancelar la cita.'
        );

    }

}

    /*
=====================================
        OBTENER UNA CITA
=====================================
*/

public function obtenerPorClave(

    string $clvCita

): ?array {

    $sql = "SELECT *

            FROM cita

            WHERE ClvCita = :clv

            LIMIT 1";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([

        'clv' => $clvCita

    ]);

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    return $resultado ?: null;

}

    /*
    =====================================
            GENERAR CLAVE
    =====================================
    */

    private function generarClave(): string {

        $sql="SELECT

                MAX(

                    CAST(

                        SUBSTRING(ClvCita,4)

                        AS UNSIGNED

                    )

                )

                FROM cita";

        $ultimo=(int)$this->db
            ->query($sql)
            ->fetchColumn();

        return 'CIT'.str_pad(

            (string)($ultimo+1),

            3,

            '0',

            STR_PAD_LEFT

        );

    }

}