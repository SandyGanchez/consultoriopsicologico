<?php

namespace App\Models;

use App\Core\Model;

class Paciente extends Model
{
    public function crear(array $datos): void
    {
        $stmt = $this->db->prepare(

            "INSERT INTO paciente

            (

                ClvPac,
                FotoPerfilPac,
                EstadoActivoPac,
                ClvUsu

            )

            VALUES

            (?,?,?,?)"

        );

        $stmt->execute([

            $datos['ClvPac'],

            'perfil-default.png',

            1,

            $datos['ClvUsu']

        ]);
    }

public function obtenerPorUsuario(string $clvUsu): ?array
{
    $sql = "SELECT *

            FROM paciente

            WHERE ClvUsu = :clvUsu

            LIMIT 1";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'clvUsu' => $clvUsu
    ]);

    $paciente = $stmt->fetch(\PDO::FETCH_ASSOC);

    return $paciente ?: null;
}
}