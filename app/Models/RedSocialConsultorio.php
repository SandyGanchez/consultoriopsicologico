<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class RedSocialConsultorio extends Model
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listarPorConsultorio(string $clvCons): array
    {
        $sql = "SELECT *
                FROM redsocial
                WHERE ClvCons = :clvCons
                ORDER BY OrdenRed ASC, FechaRegistro ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvCons' => trim($clvCons)]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPublicasPorConsultorio(string $clvCons): array
    {
        $sql = "SELECT ClvRed, TipoRed, URLRed, EtiquetaRed, OrdenRed
                FROM redsocial
                WHERE ClvCons = :clvCons
                  AND EstadoRed = 'ACTIVA'
                ORDER BY OrdenRed ASC, FechaRegistro ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvCons' => trim($clvCons)]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPropia(string $clvRed, string $clvCons): ?array
    {
        $sql = "SELECT *
                FROM redsocial
                WHERE ClvRed = :clvRed
                  AND ClvCons = :clvCons
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvRed' => trim($clvRed),
            'clvCons' => trim($clvCons)
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function crear(array $datos): bool
    {
        $sql = "INSERT INTO redsocial (
                    ClvRed, TipoRed, URLRed, EtiquetaRed,
                    EstadoRed, OrdenRed, ClvCons
                ) VALUES (
                    :clvRed, :tipo, :url, :etiqueta,
                    :estado, :orden, :clvCons
                )";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'clvRed' => $datos['ClvRed'],
            'tipo' => $datos['TipoRed'],
            'url' => $datos['URLRed'],
            'etiqueta' => $datos['EtiquetaRed'],
            'estado' => $datos['EstadoRed'],
            'orden' => $datos['OrdenRed'],
            'clvCons' => $datos['ClvCons']
        ]);
    }

    public function actualizar(string $clvRed, string $clvCons, array $datos): bool
    {
        $sql = "UPDATE redsocial
                SET TipoRed = :tipo,
                    URLRed = :url,
                    EtiquetaRed = :etiqueta,
                    EstadoRed = :estado,
                    OrdenRed = :orden,
                    FechaActualizacion = NOW()
                WHERE ClvRed = :clvRed
                  AND ClvCons = :clvCons";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'tipo' => $datos['TipoRed'],
            'url' => $datos['URLRed'],
            'etiqueta' => $datos['EtiquetaRed'],
            'estado' => $datos['EstadoRed'],
            'orden' => $datos['OrdenRed'],
            'clvRed' => $clvRed,
            'clvCons' => $clvCons
        ]) && $stmt->rowCount() > 0;
    }

    public function cambiarEstado(
        string $clvRed,
        string $clvCons,
        string $estado
    ): bool {
        $sql = "UPDATE redsocial
                SET EstadoRed = :estado,
                    FechaActualizacion = NOW()
                WHERE ClvRed = :clvRed
                  AND ClvCons = :clvCons";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'estado' => $estado,
            'clvRed' => $clvRed,
            'clvCons' => $clvCons
        ]) && $stmt->rowCount() > 0;
    }
}
