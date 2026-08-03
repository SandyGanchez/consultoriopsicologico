<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class RedSocialPsicologo extends Model
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listarPorPsicologo(string $clvPsi): array
    {
        $sql = "SELECT *
                FROM red_social_psicologo
                WHERE ClvPsi = :clvPsi
                ORDER BY OrdenRed ASC, FechaRegistro ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvPsi' => trim($clvPsi)]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Redes públicas: activas y solo si el psicólogo puede mostrarse.
     *
     * @return list<array<string, mixed>>
     */
    public function listarPublicasPorPsicologo(string $clvPsi): array
    {
        $sql = "SELECT
                    r.IdRedSocialPsi,
                    r.TipoRed,
                    r.URLRed,
                    r.EtiquetaRed,
                    r.OrdenRed
                FROM red_social_psicologo r
                INNER JOIN psicologo psi
                    ON psi.ClvPsi = r.ClvPsi
                INNER JOIN usuario usu
                    ON usu.ClvUsu = psi.ClvUsu
                WHERE r.ClvPsi = :clvPsi
                  AND r.EstadoRed = 'ACTIVA'
                  AND psi.EstatusPsi = 'ACTIVO'
                  AND psi.MostrarEnPagina = 1
                  AND usu.EstadoUsu = 1
                ORDER BY r.OrdenRed ASC, r.FechaRegistro ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvPsi' => trim($clvPsi)]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPropia(int $id, string $clvPsi): ?array
    {
        $sql = "SELECT r.*
                FROM red_social_psicologo r
                INNER JOIN psicologo psi
                    ON psi.ClvPsi = r.ClvPsi
                WHERE r.IdRedSocialPsi = :id
                  AND r.ClvPsi = :clvPsi
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'clvPsi' => trim($clvPsi)
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function crear(array $datos): int
    {
        $sql = "INSERT INTO red_social_psicologo (
                    ClvPsi, TipoRed, URLRed, EtiquetaRed,
                    EstadoRed, OrdenRed
                ) VALUES (
                    :clvPsi, :tipo, :url, :etiqueta,
                    :estado, :orden
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvPsi' => $datos['ClvPsi'],
            'tipo' => $datos['TipoRed'],
            'url' => $datos['URLRed'],
            'etiqueta' => $datos['EtiquetaRed'],
            'estado' => $datos['EstadoRed'],
            'orden' => $datos['OrdenRed']
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $id, string $clvPsi, array $datos): bool
    {
        $sql = "UPDATE red_social_psicologo
                SET TipoRed = :tipo,
                    URLRed = :url,
                    EtiquetaRed = :etiqueta,
                    EstadoRed = :estado,
                    OrdenRed = :orden,
                    FechaActualizacion = NOW()
                WHERE IdRedSocialPsi = :id
                  AND ClvPsi = :clvPsi";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'tipo' => $datos['TipoRed'],
            'url' => $datos['URLRed'],
            'etiqueta' => $datos['EtiquetaRed'],
            'estado' => $datos['EstadoRed'],
            'orden' => $datos['OrdenRed'],
            'id' => $id,
            'clvPsi' => $clvPsi
        ]) && $stmt->rowCount() > 0;
    }

    public function cambiarEstado(int $id, string $clvPsi, string $estado): bool
    {
        $sql = "UPDATE red_social_psicologo
                SET EstadoRed = :estado,
                    FechaActualizacion = NOW()
                WHERE IdRedSocialPsi = :id
                  AND ClvPsi = :clvPsi";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'estado' => $estado,
            'id' => $id,
            'clvPsi' => $clvPsi
        ]) && $stmt->rowCount() > 0;
    }
}
