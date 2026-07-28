<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Servicio extends Model
{
    public function listarServiciosActivos(
        string $clvCons
    ): array {

        $sql = "SELECT
                    ClvServ,
                    NombreServicio,
                    Descripcion,
                    DuracionMinutos,
                    CostoServicio

                FROM servicios

                WHERE ClvCons = :clvCons
                  AND EstatusServicio = 'ACTIVO'

                ORDER BY
                    NombreServicio ASC";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => $clvCons
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }

    public function obtenerPorClave(
        string $clvServ
    ): ?array {

        $sql = "SELECT
                    ClvServ,
                    NombreServicio,
                    Descripcion,
                    DuracionMinutos,
                    CostoServicio,
                    ClvCons

                FROM servicios

                WHERE ClvServ = :clvServ

                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvServ' => $clvServ
        ]);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return $resultado ?: null;

    }

    public function obtenerPsicologosServicio(
        string $clvServ
    ): array {

        $sql = "SELECT

                    psi.ClvPsi,

                    CONCAT(
                        per.NombrePer,
                        ' ',
                        per.ApPatPer,
                        ' ',
                        per.ApMatPer
                    ) AS NombrePsicologo,

                    psi.EspecialidadPsi,

                    ps.PrecioServicio,
                    ps.DuracionMinutos

                FROM psicologo_servicio ps

                INNER JOIN psicologo psi
                    ON psi.ClvPsi = ps.ClvPsi

                INNER JOIN usuario usu
                    ON usu.ClvUsu = psi.ClvUsu

                INNER JOIN persona per
                    ON per.ClvPer = usu.ClvPer

                WHERE ps.ClvServ = :clvServ

                  AND ps.EstatusAsignacion = 'ACTIVO'

                  AND psi.EstatusPsi = 'ACTIVO'

                  AND usu.EstadoUsu = 1

                ORDER BY
                    per.NombrePer,
                    per.ApPatPer";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvServ' => $clvServ
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }
}