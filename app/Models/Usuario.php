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
                p.ApMatPer,
                p.FotoPerfilPer
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

public function obtenerHashContrasena(
    string $clvUsu
): ?string {
    $sql = "SELECT ContrasenaUsu
            FROM usuario
            WHERE ClvUsu = :clvUsu
            LIMIT 1";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'clvUsu' => $clvUsu
    ]);

    $hash = $stmt->fetchColumn();

    return is_string($hash) && $hash !== ''
        ? $hash
        : null;
}

public function actualizarContrasenaYLiberarCambio(
    string $clvUsu,
    string $passwordHash
): bool {
    $sql = "UPDATE usuario
            SET
                ContrasenaUsu = :password,
                RequiereCambioContrasena = 0
            WHERE ClvUsu = :clvUsu";

    $stmt = $this->db->prepare($sql);

    return $stmt->execute([
        'password' => $passwordHash,
        'clvUsu' => $clvUsu
    ]);
}

    public function obtenerPorClave(string $clvUsu): ?array
    {
        $sql = "SELECT
                    ClvUsu,
                    CorreoUsu,
                    TelefonoUsu,
                    EstadoUsu,
                    RequiereCambioContrasena,
                    RolUsu,
                    ClvPer
                FROM usuario
                WHERE ClvUsu = :clvUsu
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvUsu' => trim($clvUsu)]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    public function existeCorreoExcepto(
        string $correo,
        string $clvUsuExcluir
    ): bool {
        $sql = "SELECT COUNT(*)
                FROM usuario
                WHERE LOWER(CorreoUsu) = LOWER(:correo)
                  AND ClvUsu <> :clvUsu";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'correo' => trim($correo),
            'clvUsu' => trim($clvUsuExcluir)
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function actualizarTelefono(
        string $clvUsu,
        string $telefono
    ): bool {
        $sql = "UPDATE usuario
                SET TelefonoUsu = :telefono
                WHERE ClvUsu = :clvUsu";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'telefono' => $telefono,
            'clvUsu' => trim($clvUsu)
        ]);
    }

    /**
     * Actualiza el correo tras verificación, con bloqueo y revalidación.
     *
     * @throws \RuntimeException|PDOException
     */
    public function actualizarCorreoVerificado(
        string $clvUsu,
        string $correoNuevo
    ): void {
        $clvUsu = trim($clvUsu);
        $correoNuevo = strtolower(trim($correoNuevo));

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "SELECT ClvUsu, CorreoUsu
                 FROM usuario
                 WHERE ClvUsu = :clvUsu
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute(['clvUsu' => $clvUsu]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                throw new \RuntimeException(
                    'No se encontró la cuenta.'
                );
            }

            if ($this->existeCorreoExcepto($correoNuevo, $clvUsu)) {
                throw new \RuntimeException('CORREO_DUPLICADO');
            }

            $update = $this->db->prepare(
                "UPDATE usuario
                 SET CorreoUsu = :correo
                 WHERE ClvUsu = :clvUsu"
            );

            $update->execute([
                'correo' => $correoNuevo,
                'clvUsu' => $clvUsu
            ]);

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
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
                RequiereCambioContrasena,
                ClvPer,
                RolUsu

            )

            VALUES

            (?,?,?,?,?,?,?,?)"

        );

        $stmt->execute([

            $datos['ClvUsu'],
            $datos['CorreoUsu'],
            $datos['TelefonoUsu'],
            $datos['ContrasenaUsu'],
            1,
            0,
            $datos['ClvPer'],
            'PACIENTE'

        ]);
    }

    public function crearPendienteActivacion(array $datos): void
    {
        $sql = "INSERT INTO usuario (
                    ClvUsu,
                    CorreoUsu,
                    TelefonoUsu,
                    ContrasenaUsu,
                    EstadoUsu,
                    RequiereCambioContrasena,
                    RolUsu,
                    ClvPer
                ) VALUES (
                    :clvUsu,
                    :correo,
                    :telefono,
                    :contrasena,
                    0,
                    1,
                    :rol,
                    :clvPer
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvUsu' => $datos['ClvUsu'],
            'correo' => $datos['CorreoUsu'],
            'telefono' => $datos['TelefonoUsu'],
            'contrasena' => $datos['ContrasenaUsu'],
            'rol' => $datos['RolUsu'],
            'clvPer' => $datos['ClvPer']
        ]);
    }

    public function bloquearPorClave(string $clvUsu): ?array
    {
        $sql = "SELECT
                    ClvUsu,
                    CorreoUsu,
                    TelefonoUsu,
                    EstadoUsu,
                    RequiereCambioContrasena,
                    RolUsu,
                    ClvPer
                FROM usuario
                WHERE ClvUsu = :clvUsu
                LIMIT 1
                FOR UPDATE";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvUsu' => trim($clvUsu)]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    public function activarConPassword(
        string $clvUsu,
        string $passwordHash
    ): bool {
        $sql = "UPDATE usuario
                SET
                    ContrasenaUsu = :password,
                    EstadoUsu = 1,
                    RequiereCambioContrasena = 0
                WHERE ClvUsu = :clvUsu";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'password' => $passwordHash,
            'clvUsu' => trim($clvUsu)
        ]);
    }
}