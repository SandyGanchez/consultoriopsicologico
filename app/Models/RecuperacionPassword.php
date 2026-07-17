<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class RecuperacionPassword extends Model
{
    public function puedeSolicitarCodigo(
        string $clvUsu,
        int $segundos = 60
    ): bool {
        $segundos = max(1, $segundos);

        $fechaLimite = date(
            'Y-m-d H:i:s',
            time() - $segundos
        );

        $sql = "SELECT COUNT(*)
                FROM recuperacion_password
                WHERE ClvUsu = :clvUsu
                  AND FechaCreacion >= :fechaLimite";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvUsu' => $clvUsu,
            'fechaLimite' => $fechaLimite
        ]);

        return (int) $stmt->fetchColumn() === 0;
    }

    public function contarSolicitudesRecientes(
        string $clvUsu
    ): int {
        $sql = "SELECT COUNT(*)
                FROM recuperacion_password
                WHERE ClvUsu = :clvUsu
                  AND FechaCreacion >= DATE_SUB(
                      NOW(),
                      INTERVAL 1 HOUR
                  )";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvUsu' => $clvUsu
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function invalidarCodigosAnteriores(
        string $clvUsu
    ): bool {
        $sql = "UPDATE recuperacion_password
                SET Utilizado = 1
                WHERE ClvUsu = :clvUsu
                  AND Utilizado = 0";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'clvUsu' => $clvUsu
        ]);
    }

    public function crear(
        string $clvUsu,
        string $codigoHash
    ): int {
        $sql = "INSERT INTO recuperacion_password (
                    ClvUsu,
                    CodigoHash,
                    FechaCreacion,
                    FechaExpiracion,
                    Utilizado,
                    Intentos,
                    FechaUltimoIntento
                ) VALUES (
                    :clvUsu,
                    :codigoHash,
                    NOW(),
                    DATE_ADD(NOW(), INTERVAL 10 MINUTE),
                    0,
                    0,
                    NULL
                )";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'clvUsu' => $clvUsu,
            'codigoHash' => $codigoHash
        ]);

        return (int) $this->db->lastInsertId();
    }

public function obtenerActivoPorId(
    int $idRec,
    string $clvUsu
): ?array {
    $sql = "SELECT
                IdRec,
                ClvUsu,
                CodigoHash,
                FechaCreacion,
                FechaExpiracion,
                Utilizado,
                Intentos,
                FechaUltimoIntento
            FROM recuperacion_password
            WHERE IdRec = :idRec
              AND ClvUsu = :clvUsu
              AND Utilizado = 0
              AND FechaExpiracion >= NOW()
            LIMIT 1";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'idRec' => $idRec,
        'clvUsu' => $clvUsu
    ]);

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    return $resultado ?: null;
}
    public function buscarPorId(
        int $idRec
    ): ?array {
        $sql = "SELECT
                    IdRec,
                    ClvUsu,
                    CodigoHash,
                    FechaCreacion,
                    FechaExpiracion,
                    Utilizado,
                    Intentos,
                    FechaUltimoIntento
                FROM recuperacion_password
                WHERE IdRec = :idRec
                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'idRec' => $idRec
        ]);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return $resultado ?: null;
    }

    public function incrementarIntentos(
        int $idRec
    ): bool {
        $sql = "UPDATE recuperacion_password
                SET Intentos = Intentos + 1,
                    FechaUltimoIntento = NOW()
                WHERE IdRec = :idRec
                  AND Utilizado = 0";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'idRec' => $idRec
        ]);
    }

    public function marcarComoUtilizado(
        int $idRec
    ): bool {
        $sql = "UPDATE recuperacion_password
                SET Utilizado = 1
                WHERE IdRec = :idRec";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'idRec' => $idRec
        ]);
    }
}