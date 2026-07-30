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

/*
=====================================
    OBTENER PERFIL COMPLETO
=====================================
*/

public function obtenerPerfilCompleto(
    string $clvUsu
): ?array {

    $sql = "SELECT

                p.ClvPac,
                p.FotoPerfilPac,
                p.EstadoActivoPac,

                u.ClvUsu,
                u.CorreoUsu,
                u.TelefonoUsu,

                per.ClvPer,
                per.NombrePer,
                per.ApPatPer,
                per.ApMatPer,
                per.FechaNacimiento,
                per.GeneroPer,
                per.FotoPerfilPer

            FROM paciente p

            INNER JOIN usuario u
                ON p.ClvUsu = u.ClvUsu

            INNER JOIN persona per
                ON u.ClvPer = per.ClvPer

            WHERE

                p.ClvUsu = :clvUsu

            LIMIT 1";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([

        'clvUsu' => $clvUsu

    ]);

    $perfil = $stmt->fetch(\PDO::FETCH_ASSOC);

    return $perfil ?: null;

}

/*
=====================================
        ACTUALIZAR PERFIL
=====================================
*/

public function actualizarPerfil(array $datos): bool
{
    try {

        $this->db->beginTransaction();

        /*
        ===============================
            ACTUALIZAR PERSONA
        ===============================
        */

        $sqlPersona = "

            UPDATE persona

            SET

                NombrePer = ?,
                ApPatPer = ?,
                ApMatPer = ?,
                FechaNacimiento = ?,
                GeneroPer = ?

            WHERE ClvPer = ?

        ";

        $stmt = $this->db->prepare($sqlPersona);

        $stmt->execute([

            $datos['NombrePer'],
            $datos['ApPatPer'],
            $datos['ApMatPer'],
            $datos['FechaNacimiento'],
            $datos['GeneroPer'],
            $datos['ClvPer']

        ]);

        /*
        ===============================
            ACTUALIZAR USUARIO
        ===============================
        */

        $sqlUsuario = "

            UPDATE usuario

            SET

                CorreoUsu = ?,
                TelefonoUsu = ?

            WHERE ClvUsu = ?

        ";

        $stmt = $this->db->prepare($sqlUsuario);

        $stmt->execute([

            $datos['CorreoUsu'],
            $datos['TelefonoUsu'],
            $datos['ClvUsu']

        ]);

        $this->db->commit();

        return true;

    } catch (\Throwable $e) {

        $this->db->rollBack();

        return false;
    }
}

/*
=====================================
    ACTUALIZAR FOTOGRAFÍA
=====================================
*/

public function actualizarFotografia(
    string $clvPac,
    string $foto
): bool
{
    $sql = "

        UPDATE paciente

        SET

            FotoPerfilPac = ?

        WHERE

            ClvPac = ?

    ";

    $stmt = $this->db->prepare($sql);

    return $stmt->execute([

        $foto,

        $clvPac

    ]);
}
}