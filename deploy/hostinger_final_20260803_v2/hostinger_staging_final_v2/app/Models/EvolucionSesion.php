<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class EvolucionSesion extends Model
{
    public function obtenerPorSeguimiento(string $clvSeg): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT *
             FROM evolucion_sesion
             WHERE ClvSeg = :clvSeg
             LIMIT 1"
        );
        $stmt->execute(['clvSeg' => $clvSeg]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }
}
