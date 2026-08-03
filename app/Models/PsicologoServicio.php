<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class PsicologoServicio extends Model
{
    public function listarPorPsicologo(
        string $clvPsi
    ): array {
        $sql = "SELECT
                    ps.ClvPsiServ,
                    ps.ClvPsi,
                    ps.ClvServ,
                    ps.PrecioServicio,
                    ps.DuracionMinutos,
                    ps.DescripcionServicio,
                    ps.EstatusAsignacion,
                    ps.FechaAsignacion,

                    s.NombreServicio,
                    s.Descripcion,
                    s.CostoServicio,
                    s.DuracionMinutos AS DuracionSugerida,
                    s.EstatusServicio

                FROM psicologo_servicio ps

                INNER JOIN servicios s
                    ON s.ClvServ = ps.ClvServ

                INNER JOIN psicologo psi
                    ON psi.ClvPsi = ps.ClvPsi

                WHERE ps.ClvPsi = :clvPsi
                  AND s.ClvCons = psi.ClvCons

                ORDER BY
                    s.NombreServicio ASC";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarDisponiblesParaPsicologo(
        string $clvPsi,
        string $clvCons
    ): array {
        $sql = "SELECT
                    s.ClvServ,
                    s.NombreServicio,
                    s.Descripcion,
                    s.DuracionMinutos,
                    s.CostoServicio

                FROM servicios s

                WHERE s.ClvCons = :clvCons
                  AND s.EstatusServicio = 'ACTIVO'
                  AND NOT EXISTS (
                      SELECT 1
                      FROM psicologo_servicio ps
                      WHERE ps.ClvServ = s.ClvServ
                        AND ps.ClvPsi = :clvPsi
                  )

                ORDER BY
                    s.NombreServicio ASC";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi,
            'clvCons' => $clvCons
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerAsignacionPropia(
        string $clvPsi,
        string $clvServ
    ): ?array {
        $sql = "SELECT
                    ps.ClvPsiServ,
                    ps.ClvPsi,
                    ps.ClvServ,
                    ps.PrecioServicio,
                    ps.DuracionMinutos,
                    ps.DescripcionServicio,
                    ps.EstatusAsignacion,
                    ps.FechaAsignacion,

                    s.NombreServicio,
                    s.Descripcion,
                    s.CostoServicio,
                    s.DuracionMinutos AS DuracionSugerida,
                    s.EstatusServicio,
                    s.ClvCons

                FROM psicologo_servicio ps

                INNER JOIN servicios s
                    ON s.ClvServ = ps.ClvServ

                WHERE ps.ClvPsi = :clvPsi
                  AND ps.ClvServ = :clvServ

                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvPsi' => $clvPsi,
            'clvServ' => $clvServ
        ]);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return $resultado ?: null;
    }

    public function obtenerServicioActivoDelConsultorio(
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
                  AND EstatusServicio = 'ACTIVO'

                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvServ' => $clvServ,
            'clvCons' => $clvCons
        ]);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return $resultado ?: null;
    }

    public function servicioPerteneceAlConsultorio(
        string $clvServ,
        string $clvCons
    ): bool {
        $sql = "SELECT COUNT(*)
                FROM servicios
                WHERE ClvServ = :clvServ
                  AND ClvCons = :clvCons";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvServ' => $clvServ,
            'clvCons' => $clvCons
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function existeRelacion(string $clvPsi, string $clvServ): bool
    {
        $sql = "SELECT COUNT(*)
                FROM psicologo_servicio
                WHERE ClvPsi = :clvPsi
                  AND ClvServ = :clvServ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvPsi' => $clvPsi,
            'clvServ' => $clvServ
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Relación institucional pendiente de configuración del especialista.
     * Usa el ENUM existente EstatusAsignacion = INACTIVA.
     */
    public function crearRelacionPendiente(
        string $clvPsiServ,
        string $clvPsi,
        string $clvServ,
        float $precio,
        int $duracion
    ): bool {
        $sql = "INSERT INTO psicologo_servicio (
                    ClvPsiServ,
                    ClvPsi,
                    ClvServ,
                    PrecioServicio,
                    DuracionMinutos,
                    DescripcionServicio,
                    EstatusAsignacion
                ) VALUES (
                    :clvPsiServ,
                    :clvPsi,
                    :clvServ,
                    :precio,
                    :duracion,
                    '',
                    'INACTIVA'
                )";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'clvPsiServ' => $clvPsiServ,
            'clvPsi' => $clvPsi,
            'clvServ' => $clvServ,
            'precio' => number_format($precio, 2, '.', ''),
            'duracion' => $duracion
        ]);
    }

    /**
     * @deprecated Asignación manual del especialista. Preferir crearRelacionPendiente
     *             vía ServicioOfertaService y activar tras configurar precio/duración.
     */
    public function crearAsignacion(
        string $clvPsiServ,
        string $clvPsi,
        string $clvServ,
        float $precio,
        int $duracion
    ): bool {
        $sql = "INSERT INTO psicologo_servicio (
                    ClvPsiServ,
                    ClvPsi,
                    ClvServ,
                    PrecioServicio,
                    DuracionMinutos,
                    DescripcionServicio,
                    EstatusAsignacion
                ) VALUES (
                    :clvPsiServ,
                    :clvPsi,
                    :clvServ,
                    :precio,
                    :duracion,
                    '',
                    'ACTIVA'
                )";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'clvPsiServ' => $clvPsiServ,
            'clvPsi' => $clvPsi,
            'clvServ' => $clvServ,
            'precio' => number_format($precio, 2, '.', ''),
            'duracion' => $duracion
        ]);
    }

    /**
     * Consulta de ofertas por servicio (solo lectura para el consultorio).
     *
     * @return list<array<string, mixed>>
     */
    public function listarOfertasPorServicio(
        string $clvServ,
        string $clvCons
    ): array {
        $sql = "SELECT
                    ps.ClvPsiServ,
                    ps.ClvPsi,
                    ps.PrecioServicio,
                    ps.DuracionMinutos,
                    ps.EstatusAsignacion,
                    CONCAT(
                        per.NombrePer, ' ',
                        per.ApPatPer, ' ',
                        COALESCE(per.ApMatPer, '')
                    ) AS NombrePsicologo,
                    psi.EspecialidadPsi,
                    psi.EstatusPsi
                FROM psicologo_servicio ps
                INNER JOIN psicologo psi
                    ON psi.ClvPsi = ps.ClvPsi
                INNER JOIN usuario usu
                    ON usu.ClvUsu = psi.ClvUsu
                INNER JOIN persona per
                    ON per.ClvPer = usu.ClvPer
                WHERE ps.ClvServ = :clvServ
                  AND psi.ClvCons = :clvCons
                ORDER BY
                    per.NombrePer ASC,
                    per.ApPatPer ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvServ' => $clvServ,
            'clvCons' => $clvCons
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function actualizarAsignacion(
        string $clvPsi,
        string $clvServ,
        float $precio,
        int $duracion
    ): bool {
        $sql = "UPDATE psicologo_servicio
                SET
                    PrecioServicio = :precio,
                    DuracionMinutos = :duracion
                WHERE ClvPsi = :clvPsi
                  AND ClvServ = :clvServ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'precio' => number_format($precio, 2, '.', ''),
            'duracion' => $duracion,
            'clvPsi' => $clvPsi,
            'clvServ' => $clvServ
        ]);
    }

    public function reactivarAsignacion(
        string $clvPsi,
        string $clvServ,
        float $precio,
        int $duracion
    ): bool {
        $sql = "UPDATE psicologo_servicio
                SET
                    PrecioServicio = :precio,
                    DuracionMinutos = :duracion,
                    EstatusAsignacion = 'ACTIVA'
                WHERE ClvPsi = :clvPsi
                  AND ClvServ = :clvServ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'precio' => number_format($precio, 2, '.', ''),
            'duracion' => $duracion,
            'clvPsi' => $clvPsi,
            'clvServ' => $clvServ
        ]);
    }

    public function cambiarEstatus(
        string $clvPsi,
        string $clvServ,
        string $estatus
    ): bool {
        $sql = "UPDATE psicologo_servicio
                SET EstatusAsignacion = :estatus
                WHERE ClvPsi = :clvPsi
                  AND ClvServ = :clvServ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'estatus' => $estatus,
            'clvPsi' => $clvPsi,
            'clvServ' => $clvServ
        ]);
    }
}
