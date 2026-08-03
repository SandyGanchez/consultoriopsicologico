<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class DiagnosticoSeguimiento extends Model
{
    public function obtenerUltimoPorSeguimiento(string $clvSeg): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT *
             FROM diagnostico_seguimiento
             WHERE ClvSeg = :clvSeg
             ORDER BY FechaDiagSeg DESC
             LIMIT 1"
        );
        $stmt->execute(['clvSeg' => $clvSeg]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }
}
