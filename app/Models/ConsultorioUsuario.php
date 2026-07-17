<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class ConsultorioUsuario extends Model
{
    public function buscarPorUsuario(string $clvUsu): ?array
    {
        $sql = "SELECT
                    cu.ClvConsUsu,
                    cu.ClvCons,
                    cu.ClvUsu,
                    cu.EsResponsable,
                    cu.EstatusConsUsu,
                    cu.FechaAsignacion,
                    c.NombreCons,
                    c.LogotipoCons,
                    c.Slogan,
                    c.Descripcion,
                    c.TelefonoCons,
                    c.CorreoElectronico,
                    c.LimiteCancHoras,
                    c.EstatusCons
                FROM consultorio_usuario cu
                INNER JOIN consultorio c
                    ON cu.ClvCons = c.ClvCons
                WHERE cu.ClvUsu = :clvUsu
                  AND cu.EstatusConsUsu = 'ACTIVO'
                  AND c.EstatusCons = 'ACTIVO'
                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvUsu' => $clvUsu
        ]);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return $resultado ?: null;
    }
}