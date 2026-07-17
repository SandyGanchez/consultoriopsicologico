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
}