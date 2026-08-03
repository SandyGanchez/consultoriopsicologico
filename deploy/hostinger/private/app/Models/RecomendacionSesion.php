<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class RecomendacionSesion extends Model
{
    public function listarPorSeguimiento(string $clvSeg): array
    {
        $stmt = $this->db->prepare(
            "SELECT *
             FROM recomendacion_sesion
             WHERE ClvSeg = :clvSeg
             ORDER BY ClvRecSeg ASC"
        );
        $stmt->execute(['clvSeg' => $clvSeg]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
