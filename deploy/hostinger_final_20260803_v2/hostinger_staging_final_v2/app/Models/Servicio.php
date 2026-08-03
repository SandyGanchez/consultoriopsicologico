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

    public function listarPorConsultorio(
        string $clvCons
    ): array {
        $sql = "SELECT
                    s.ClvServ,
                    s.NombreServicio,
                    s.Descripcion,
                    s.DuracionMinutos,
                    s.CostoServicio,
                    s.EstatusServicio,
                    s.ClvCons,
                    (
                        SELECT COUNT(DISTINCT ps.ClvPsi)
                        FROM psicologo_servicio ps
                        INNER JOIN psicologo psi
                            ON psi.ClvPsi = ps.ClvPsi
                        WHERE ps.ClvServ = s.ClvServ
                          AND psi.ClvCons = s.ClvCons
                          AND ps.EstatusAsignacion = 'ACTIVA'
                    ) AS TotalPsicologos

                FROM servicios s

                WHERE s.ClvCons = :clvCons

                ORDER BY
                    s.NombreServicio ASC";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => $clvCons
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorClaveYConsultorio(
        string $clvServ,
        string $clvCons
    ): ?array {
        $sql = "SELECT
                    ClvServ,
                    NombreServicio,
                    Descripcion,
                    DuracionMinutos,
                    CostoServicio,
                    EstatusServicio,
                    ClvCons

                FROM servicios

                WHERE ClvServ = :clvServ
                  AND ClvCons = :clvCons

                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvServ' => $clvServ,
            'clvCons' => $clvCons
        ]);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return $resultado ?: null;
    }

    public function existeNombreEnConsultorio(
        string $nombre,
        string $clvCons,
        ?string $excluirClave = null
    ): bool {
        $sql = "SELECT COUNT(*)
                FROM servicios
                WHERE ClvCons = :clvCons
                  AND LOWER(TRIM(NombreServicio)) =
                      LOWER(TRIM(:nombre))";

        $parametros = [
            'clvCons' => $clvCons,
            'nombre' => $nombre
        ];

        if ($excluirClave !== null && $excluirClave !== '') {
            $sql .= " AND ClvServ <> :excluirClave";
            $parametros['excluirClave'] = $excluirClave;
        }

        $stmt = $this->db->prepare($sql);

        $stmt->execute($parametros);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function crearParaConsultorio(
        string $clvCons,
        array $datos
    ): bool {
        $sql = "INSERT INTO servicios (
                    ClvServ,
                    NombreServicio,
                    Descripcion,
                    ClvCons,
                    DuracionMinutos,
                    CostoServicio,
                    EstatusServicio
                ) VALUES (
                    :clvServ,
                    :nombreServicio,
                    :descripcion,
                    :clvCons,
                    :duracionMinutos,
                    :costoServicio,
                    :estatusServicio
                )";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'clvServ' => $datos['ClvServ'],
            'nombreServicio' => $datos['NombreServicio'],
            'descripcion' => $datos['Descripcion'],
            'clvCons' => $clvCons,
            'duracionMinutos' => $datos['DuracionMinutos'],
            'costoServicio' => $datos['CostoServicio'],
            'estatusServicio' => $datos['EstatusServicio']
        ]);
    }

    public function actualizarParaConsultorio(
        string $clvServ,
        string $clvCons,
        array $datos
    ): bool {
        $sql = "UPDATE servicios
                SET
                    NombreServicio = :nombreServicio,
                    Descripcion = :descripcion,
                    DuracionMinutos = :duracionMinutos,
                    CostoServicio = :costoServicio
                WHERE ClvServ = :clvServ
                  AND ClvCons = :clvCons";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'nombreServicio' => $datos['NombreServicio'],
            'descripcion' => $datos['Descripcion'],
            'duracionMinutos' => $datos['DuracionMinutos'],
            'costoServicio' => $datos['CostoServicio'],
            'clvServ' => $clvServ,
            'clvCons' => $clvCons
        ]);
    }

    public function cambiarEstatusParaConsultorio(
        string $clvServ,
        string $clvCons,
        string $estatus
    ): bool {
        $sql = "UPDATE servicios
                SET EstatusServicio = :estatus
                WHERE ClvServ = :clvServ
                  AND ClvCons = :clvCons";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'estatus' => $estatus,
            'clvServ' => $clvServ,
            'clvCons' => $clvCons
        ]);
    }

    public function contarPsicologosAsignados(
        string $clvServ,
        string $clvCons
    ): int {
        $sql = "SELECT COUNT(DISTINCT ps.ClvPsi)
                FROM psicologo_servicio ps
                INNER JOIN psicologo psi
                    ON psi.ClvPsi = ps.ClvPsi
                INNER JOIN servicios s
                    ON s.ClvServ = ps.ClvServ
                WHERE ps.ClvServ = :clvServ
                  AND s.ClvCons = :clvCons
                  AND psi.ClvCons = :clvCons
                  AND ps.EstatusAsignacion = 'ACTIVA'";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvServ' => $clvServ,
            'clvCons' => $clvCons
        ]);

        return (int) $stmt->fetchColumn();
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

    public function listarActivosPorPsicologo(
        string $clvPsi
    ): array {
        $sql = "SELECT
                    s.ClvServ,
                    s.NombreServicio,
                    s.Descripcion,
                    ps.DuracionMinutos,
                    ps.PrecioServicio,
                    ps.ClvPsiServ,
                    ps.EstatusAsignacion,
                    psi.ClvCons

                FROM psicologo_servicio ps

                INNER JOIN servicios s
                    ON s.ClvServ = ps.ClvServ

                INNER JOIN psicologo psi
                    ON psi.ClvPsi = ps.ClvPsi

                INNER JOIN consultorio c
                    ON c.ClvCons = psi.ClvCons

                INNER JOIN usuario usu
                    ON usu.ClvUsu = psi.ClvUsu

                WHERE ps.ClvPsi = :clvPsi
                  AND ps.EstatusAsignacion = 'ACTIVA'
                  AND s.EstatusServicio = 'ACTIVO'
                  AND psi.EstatusPsi = 'ACTIVO'
                  AND c.EstatusCons = 'ACTIVO'
                  AND c.PublicadoCons = 1
                  AND usu.EstadoUsu = 1
                  AND s.ClvCons = psi.ClvCons
                  AND ps.PrecioServicio > 0
                  AND ps.DuracionMinutos BETWEEN 1 AND 480

                ORDER BY
                    s.NombreServicio ASC";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerAsignacionActivaParaAgendamiento(
        string $clvPsi,
        string $clvServ
    ): ?array {
        $sql = "SELECT
                    s.ClvServ,
                    s.NombreServicio,
                    s.ClvCons AS ClvConsServicio,
                    ps.ClvPsiServ,
                    ps.DuracionMinutos,
                    ps.PrecioServicio,
                    ps.EstatusAsignacion,
                    psi.ClvPsi,
                    psi.ClvCons,
                    psi.EstatusPsi

                FROM psicologo_servicio ps

                INNER JOIN servicios s
                    ON s.ClvServ = ps.ClvServ

                INNER JOIN psicologo psi
                    ON psi.ClvPsi = ps.ClvPsi

                INNER JOIN consultorio c
                    ON c.ClvCons = psi.ClvCons

                WHERE ps.ClvPsi = :clvPsi
                  AND ps.ClvServ = :clvServ
                  AND ps.EstatusAsignacion = 'ACTIVA'
                  AND s.EstatusServicio = 'ACTIVO'
                  AND psi.EstatusPsi = 'ACTIVO'
                  AND c.EstatusCons = 'ACTIVO'
                  AND c.PublicadoCons = 1
                  AND s.ClvCons = psi.ClvCons
                  AND ps.PrecioServicio > 0
                  AND ps.DuracionMinutos BETWEEN 1 AND 480

                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi,
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

                  AND ps.EstatusAsignacion = 'ACTIVA'

                  AND psi.EstatusPsi = 'ACTIVO'

                  AND usu.EstadoUsu = 1

                  AND ps.PrecioServicio > 0

                  AND ps.DuracionMinutos BETWEEN 1 AND 480

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