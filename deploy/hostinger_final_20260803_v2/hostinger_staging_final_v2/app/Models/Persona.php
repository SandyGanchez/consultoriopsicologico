<?php

namespace App\Models;

use App\Core\Model;

class Persona extends Model
{
    public function crear(array $datos): void
    {
        $sql = "INSERT INTO persona
        (
            ClvPer,
            NombrePer,
            ApPatPer,
            ApMatPer,
            FechaNacimiento,
            GeneroPer,
            ClvDir
        )

        VALUES

        (?,?,?,?,?,?,NULL)";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([

            $datos['ClvPer'],
            $datos['NombrePer'],
            $datos['ApPatPer'],
            $datos['ApMatPer'],
            $datos['FechaNacimiento'],
            $datos['GeneroPer']

        ]);
    }
}