<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Notificacion extends Model
{
    /*
    =====================================
            CREAR NOTIFICACIÓN
    =====================================
    */

    public function crear(array $datos): bool
    {
        $sql = "
            INSERT INTO notificacion (
                ClvNotif,
                TituloNotif,
                MensajeNotif,
                TipoNotif,
                RutaNotif,
                FechaNotif,
                LeidaNotif,
                ClvUsu
            )
            VALUES (
                :clave,
                :titulo,
                :mensaje,
                :tipo,
                :ruta,
                NOW(),
                0,
                :usuario
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':clave'    => $datos['ClvNotif'],
            ':titulo'   => $datos['TituloNotif'],
            ':mensaje'  => $datos['MensajeNotif'],
            ':tipo'     => $datos['TipoNotif'],
            ':ruta' => $datos['RutaNotif'] ?? null,
            ':usuario'  => $datos['ClvUsu']
        ]);
    }

    /*
    =====================================
      OBTENER TODAS LAS NOTIFICACIONES
    =====================================
    */

    public function obtenerPorUsuario(
        string $clvUsuario
    ): array {

        $sql = "
            SELECT *
            FROM notificacion
            WHERE ClvUsu = :usuario
            ORDER BY
                LeidaNotif ASC,
                FechaNotif DESC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':usuario' => $clvUsuario
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    =====================================
        ÚLTIMAS NOTIFICACIONES
    =====================================
    */

    public function obtenerRecientes(
        string $clvUsuario,
        int $limite = 5
    ): array {

        $sql = "
            SELECT *
            FROM notificacion
            WHERE ClvUsu = :usuario
            ORDER BY
                FechaNotif DESC
            LIMIT :limite
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(
            ':usuario',
            $clvUsuario
        );

        $stmt->bindValue(
            ':limite',
            $limite,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    /*
    =====================================
       CONTAR NO LEÍDAS
    =====================================
    */

    public function contarNoLeidas(
        string $clvUsuario
    ): int {

        $sql = "
            SELECT COUNT(*) total
            FROM notificacion
            WHERE
                ClvUsu = :usuario
            AND
                LeidaNotif = 0
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':usuario' => $clvUsuario
        ]);

        return (int)
            $stmt->fetchColumn();
    }

    /*
=====================================
    OBTENER UNA NOTIFICACIÓN
=====================================
*/

public function obtenerPorClave(
    string $clave,
    string $clvUsuario
): ?array {
    $sql = "
        SELECT
            ClvNotif,
            TituloNotif,
            MensajeNotif,
            TipoNotif,
            RutaNotif,
            FechaNotif,
            LeidaNotif,
            FechaLecturaNotif,
            ClvUsu
        FROM notificacion
        WHERE ClvNotif = :clave
          AND ClvUsu = :usuario
        LIMIT 1
    ";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        ':clave' => $clave,
        ':usuario' => $clvUsuario
    ]);

    $resultado = $stmt->fetch();

    return $resultado ?: null;
}

/*
=====================================
    MARCAR COMO LEÍDA
=====================================
*/


public function marcarComoLeida(
    string $clave,
    string $clvUsuario
): bool {
    $sql = "
        UPDATE notificacion
        SET
            LeidaNotif = 1,
            FechaLecturaNotif =
                COALESCE(
                    FechaLecturaNotif,
                    NOW()
                )
        WHERE ClvNotif = :clave
          AND ClvUsu = :usuario
    ";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        ':clave' => $clave,
        ':usuario' => $clvUsuario
    ]);

    return $stmt->rowCount() > 0;
}
/*
=====================================
    ELIMINAR NOTIFICACIÓN
=====================================
*/

public function eliminar(
    string $clave,
    string $clvUsuario
): bool {
    $sql = "
        DELETE FROM notificacion
        WHERE ClvNotif = :clave
          AND ClvUsu = :usuario
    ";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        ':clave' => $clave,
        ':usuario' => $clvUsuario
    ]);

    return $stmt->rowCount() > 0;
}
}