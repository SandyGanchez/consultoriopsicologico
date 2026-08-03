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
                FechaNotif,
                LeidaNotif,
                ClvUsu
            )
            VALUES (
                :clave,
                :titulo,
                :mensaje,
                :tipo,
                NOW(),
                0,
                :usuario
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':clave' => $datos['ClvNotif'],
            ':titulo' => $datos['TituloNotif'],
            ':mensaje' => $datos['MensajeNotif'],
            ':tipo' => $datos['TipoNotif'],
            ':usuario' => $datos['ClvUsu']
        ]);
    }

    /*
    =====================================
      LISTAR POR USUARIO (paginado)
    =====================================
    */

    public function listarPorUsuario(
        string $clvUsu,
        int $limite = 20,
        int $offset = 0
    ): array {
        $limite = max(1, min(100, $limite));
        $offset = max(0, $offset);

        $sql = "
            SELECT
                ClvNotif,
                TituloNotif,
                MensajeNotif,
                TipoNotif,
                FechaNotif,
                LeidaNotif,
                FechaLecturaNotif,
                ClvUsu
            FROM notificacion
            WHERE ClvUsu = :usuario
            ORDER BY FechaNotif DESC
            LIMIT :limite OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':usuario', $clvUsu);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Compatibilidad con llamadas previas sin paginación.
     */
    public function obtenerPorUsuario(string $clvUsuario): array
    {
        return $this->listarPorUsuario($clvUsuario, 100, 0);
    }

    /*
    =====================================
        ÚLTIMAS NOTIFICACIONES
    =====================================
    */

    public function listarRecientesPorUsuario(
        string $clvUsu,
        int $limite = 5
    ): array {
        $limite = max(1, min(20, $limite));

        $sql = "
            SELECT
                ClvNotif,
                TituloNotif,
                MensajeNotif,
                TipoNotif,
                FechaNotif,
                LeidaNotif,
                FechaLecturaNotif,
                ClvUsu
            FROM notificacion
            WHERE ClvUsu = :usuario
            ORDER BY FechaNotif DESC
            LIMIT :limite
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':usuario', $clvUsu);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerRecientes(
        string $clvUsuario,
        int $limite = 5
    ): array {
        return $this->listarRecientesPorUsuario(
            $clvUsuario,
            $limite
        );
    }

    /*
    =====================================
       CONTAR NO LEÍDAS
    =====================================
    */

    public function contarNoLeidas(string $clvUsu): int
    {
        $sql = "
            SELECT COUNT(*) AS total
            FROM notificacion
            WHERE ClvUsu = :usuario
              AND LeidaNotif = 0
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':usuario' => $clvUsu
        ]);

        return (int) $stmt->fetchColumn();
    }

    /*
    =====================================
    OBTENER UNA NOTIFICACIÓN (dueño)
    =====================================
    */

    public function obtenerPorClaveYUsuario(
        string $clvNot,
        string $clvUsu
    ): ?array {
        $sql = "
            SELECT
                ClvNotif,
                TituloNotif,
                MensajeNotif,
                TipoNotif,
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
            ':clave' => $clvNot,
            ':usuario' => $clvUsu
        ]);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return $resultado ?: null;
    }

    public function obtenerPorClave(
        string $clave,
        string $clvUsuario
    ): ?array {
        return $this->obtenerPorClaveYUsuario(
            $clave,
            $clvUsuario
        );
    }

    /*
    =====================================
        MARCAR COMO LEÍDA
    =====================================
    */

    public function marcarLeida(
        string $clvNot,
        string $clvUsu
    ): bool {
        $sql = "
            UPDATE notificacion
            SET
                LeidaNotif = 1,
                FechaLecturaNotif = COALESCE(
                    FechaLecturaNotif,
                    NOW()
                )
            WHERE ClvNotif = :clave
              AND ClvUsu = :usuario
              AND LeidaNotif = 0
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':clave' => $clvNot,
            ':usuario' => $clvUsu
        ]);

        return $stmt->rowCount() > 0;
    }

    public function marcarComoLeida(
        string $clave,
        string $clvUsuario
    ): bool {
        return $this->marcarLeida($clave, $clvUsuario);
    }

    /*
    =====================================
       MARCAR TODAS COMO LEÍDAS
    =====================================
    */

    public function marcarTodasLeidas(string $clvUsu): int
    {
        $sql = "
            UPDATE notificacion
            SET
                LeidaNotif = 1,
                FechaLecturaNotif = COALESCE(
                    FechaLecturaNotif,
                    NOW()
                )
            WHERE ClvUsu = :usuario
              AND LeidaNotif = 0
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':usuario' => $clvUsu
        ]);

        return (int) $stmt->rowCount();
    }

    public function marcarTodasComoLeidas(string $clvUsuario): bool
    {
        $this->marcarTodasLeidas($clvUsuario);

        return true;
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
