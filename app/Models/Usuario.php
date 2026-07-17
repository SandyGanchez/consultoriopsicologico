<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Usuario extends Model
{
    public function existeCorreo(string $correo): bool
    {
        $stmt = $this->db->prepare(

            "SELECT COUNT(*)
             FROM usuario
             WHERE CorreoUsu=?"

        );

        $stmt->execute([$correo]);

        return $stmt->fetchColumn() > 0;
    }
public function buscarPorCorreo(string $correo): ?array
{
    $sql = "SELECT
                u.*,
                p.NombrePer,
                p.ApPatPer,
                p.ApMatPer
            FROM usuario u
            INNER JOIN persona p
                ON u.ClvPer = p.ClvPer
            WHERE LOWER(u.CorreoUsu) = LOWER(:correo)
            LIMIT 1";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'correo' => trim($correo)
    ]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    return $usuario ?: null;
}
public function desactivarCambioTemporal(
    string $clvUsu
): bool {

    $sql = "UPDATE usuario
            SET RequiereCambioContrasena = 0
            WHERE ClvUsu = :clvUsu";

    $stmt = $this->db->prepare($sql);

    return $stmt->execute([
        'clvUsu' => $clvUsu
    ]);
}
public function actualizarPassword(
    string $clvUsu,
    string $passwordHash
): bool {
    $sql = "UPDATE usuario
            SET ContrasenaUsu = :password
            WHERE ClvUsu = :clvUsu";

    $stmt = $this->db->prepare($sql);

    return $stmt->execute([
        'password' => $passwordHash,
        'clvUsu' => $clvUsu
    ]);
}
    public function crear(array $datos): void
    {
        $stmt = $this->db->prepare(

            "INSERT INTO usuario

            (

                ClvUsu,
                CorreoUsu,
                TelefonoUsu,
                ContrasenaUsu,
                EstadoUsu,
                ClvPer,
                RolUsu

            )

            VALUES

            (?,?,?,?,?,?,?)"

        );

        $stmt->execute([

            $datos['ClvUsu'],
            $datos['CorreoUsu'],
            $datos['TelefonoUsu'],
            $datos['ContrasenaUsu'],
            1,
            $datos['ClvPer'],
            'PACIENTE'

        ]);
    }
}