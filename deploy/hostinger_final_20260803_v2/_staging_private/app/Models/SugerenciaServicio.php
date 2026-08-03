<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class SugerenciaServicio extends Model
{
    public function tablaDisponible(): bool
    {
        try {
            $this->db->query('SELECT 1 FROM sugerencia_servicio LIMIT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function buscarPendientePorNombre(
        string $clvPsi,
        string $nombre
    ): ?int {
        $sql = "SELECT IdSugerenciaServicio
                FROM sugerencia_servicio
                WHERE ClvPsi = :clvPsi
                  AND EstadoSugerencia = 'PENDIENTE'
                  AND LOWER(TRIM(NombreSugerido)) = LOWER(TRIM(:nombre))
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvPsi' => $clvPsi,
            'nombre' => $nombre
        ]);

        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    public function crear(array $datos): int
    {
        $sql = "INSERT INTO sugerencia_servicio (
                    ClvPsi,
                    ClvCons,
                    NombreSugerido,
                    DescripcionSugerida,
                    Justificacion,
                    EstadoSugerencia
                ) VALUES (
                    :clvPsi,
                    :clvCons,
                    :nombre,
                    :descripcion,
                    :justificacion,
                    'PENDIENTE'
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvPsi' => $datos['ClvPsi'],
            'clvCons' => $datos['ClvCons'],
            'nombre' => $datos['NombreSugerido'],
            'descripcion' => $datos['DescripcionSugerida'],
            'justificacion' => $datos['Justificacion']
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPorPsicologo(string $clvPsi): array
    {
        $sql = "SELECT *
                FROM sugerencia_servicio
                WHERE ClvPsi = :clvPsi
                ORDER BY FechaSolicitud DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvPsi' => $clvPsi]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPorConsultorio(string $clvCons): array
    {
        $sql = "SELECT
                    sug.*,
                    CONCAT(
                        per.NombrePer, ' ',
                        per.ApPatPer, ' ',
                        COALESCE(per.ApMatPer, '')
                    ) AS NombrePsicologo
                FROM sugerencia_servicio sug
                INNER JOIN psicologo psi
                    ON psi.ClvPsi = sug.ClvPsi
                INNER JOIN usuario usu
                    ON usu.ClvUsu = psi.ClvUsu
                INNER JOIN persona per
                    ON per.ClvPer = usu.ClvPer
                WHERE sug.ClvCons = :clvCons
                ORDER BY
                    FIELD(sug.EstadoSugerencia, 'PENDIENTE', 'APROBADA', 'RECHAZADA'),
                    sug.FechaSolicitud DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvCons' => $clvCons]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorIdYConsultorio(int $id, string $clvCons): ?array
    {
        $sql = "SELECT
                    sug.*,
                    CONCAT(
                        per.NombrePer, ' ',
                        per.ApPatPer, ' ',
                        COALESCE(per.ApMatPer, '')
                    ) AS NombrePsicologo
                FROM sugerencia_servicio sug
                INNER JOIN psicologo psi
                    ON psi.ClvPsi = sug.ClvPsi
                INNER JOIN usuario usu
                    ON usu.ClvUsu = psi.ClvUsu
                INNER JOIN persona per
                    ON per.ClvPer = usu.ClvPer
                WHERE sug.IdSugerenciaServicio = :id
                  AND sug.ClvCons = :clvCons
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'clvCons' => $clvCons
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function obtenerPorIdYPsicologo(int $id, string $clvPsi): ?array
    {
        $sql = "SELECT *
                FROM sugerencia_servicio
                WHERE IdSugerenciaServicio = :id
                  AND ClvPsi = :clvPsi
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'clvPsi' => $clvPsi
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function rechazar(
        int $id,
        string $clvCons,
        string $clvUsuRevision,
        string $observacion
    ): bool {
        $sql = "UPDATE sugerencia_servicio
                SET EstadoSugerencia = 'RECHAZADA',
                    ObservacionConsultorio = :observacion,
                    FechaRevision = NOW(),
                    ClvUsuRevision = :clvUsu
                WHERE IdSugerenciaServicio = :id
                  AND ClvCons = :clvCons
                  AND EstadoSugerencia = 'PENDIENTE'";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'observacion' => $observacion !== '' ? $observacion : null,
            'clvUsu' => $clvUsuRevision,
            'id' => $id,
            'clvCons' => $clvCons
        ]) && $stmt->rowCount() > 0;
    }

    public function marcarAprobada(
        int $id,
        string $clvCons,
        string $clvUsuRevision,
        string $clvServCreado,
        ?string $observacion = null
    ): bool {
        $sql = "UPDATE sugerencia_servicio
                SET EstadoSugerencia = 'APROBADA',
                    ObservacionConsultorio = :observacion,
                    FechaRevision = NOW(),
                    ClvUsuRevision = :clvUsu,
                    ClvServCreado = :clvServ
                WHERE IdSugerenciaServicio = :id
                  AND ClvCons = :clvCons
                  AND EstadoSugerencia = 'PENDIENTE'";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'observacion' => $observacion !== null && $observacion !== ''
                ? $observacion
                : null,
            'clvUsu' => $clvUsuRevision,
            'clvServ' => $clvServCreado,
            'id' => $id,
            'clvCons' => $clvCons
        ]) && $stmt->rowCount() > 0;
    }
}
