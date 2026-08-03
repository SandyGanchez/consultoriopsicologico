<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Caracteristica extends Model
{
    public function obtenerPorConsultorio(
        string $clvCons,
        bool $soloActivas = false
    ): array {
        $sql = "SELECT
                    ClvCar,
                    Titulo,
                    Descripcion,
                    Icono,
                    OrdenCar,
                    EstadoCar,
                    ClvCons
                FROM caracteristica
                WHERE ClvCons = :clvCons";

        if ($soloActivas) {
            $sql .= " AND EstadoCar = 1";
        }

        $sql .= " ORDER BY OrdenCar, Titulo";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvCons' => trim($clvCons)
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function perteneceAlConsultorio(
        string $clvCar,
        string $clvCons
    ): bool {
        $sql = "SELECT COUNT(*)
                FROM caracteristica
                WHERE ClvCar = :clave
                  AND ClvCons = :clvCons";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clave' => trim($clvCar),
            'clvCons' => trim($clvCons)
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function actualizarPorConsultorio(
        string $clvCar,
        string $clvCons,
        array $datos
    ): bool {
        $campos = [];
        $parametros = [
            'clave' => trim($clvCar),
            'clvCons' => trim($clvCons)
        ];

        if (array_key_exists('Titulo', $datos)) {
            $campos[] = 'Titulo = :titulo';
            $parametros['titulo'] = $datos['Titulo'];
        }

        if (array_key_exists('Descripcion', $datos)) {
            $campos[] = 'Descripcion = :descripcion';
            $parametros['descripcion'] = $datos['Descripcion'];
        }

        if (array_key_exists('EstadoCar', $datos)) {
            $campos[] = 'EstadoCar = :estado';
            $parametros['estado'] = (int) $datos['EstadoCar'];
        }

        if ($campos === []) {
            return false;
        }

        $sql = "UPDATE caracteristica
                SET " . implode(', ', $campos) . "
                WHERE ClvCar = :clave
                  AND ClvCons = :clvCons";

        $stmt = $this->db->prepare($sql);

        $stmt->execute($parametros);

        return $stmt->rowCount() > 0;
    }
}
