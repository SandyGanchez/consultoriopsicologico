<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class ActivacionCuenta extends Model
{
    public function crear(array $datos): int
    {
        $sql = "INSERT INTO activacion_cuenta (
                    ClvUsu,
                    TokenHash,
                    TipoActivacion,
                    ClvUsuInvitador,
                    FechaExpiracion,
                    Estado,
                    FechaUltimoEnvio,
                    NumReenvios
                ) VALUES (
                    :clvUsu,
                    :tokenHash,
                    :tipo,
                    :invitador,
                    :expira,
                    'PENDIENTE',
                    :ultimoEnvio,
                    :reenvios
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvUsu' => $datos['ClvUsu'],
            'tokenHash' => $datos['TokenHash'],
            'tipo' => $datos['TipoActivacion'],
            'invitador' => $datos['ClvUsuInvitador'] ?? null,
            'expira' => $datos['FechaExpiracion'],
            'ultimoEnvio' => $datos['FechaUltimoEnvio'] ?? null,
            'reenvios' => (int) ($datos['NumReenvios'] ?? 0)
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function obtenerPorTokenHash(
        string $tokenHash,
        bool $bloquear = false
    ): ?array {
        $sql = "SELECT
                    a.*,
                    u.CorreoUsu,
                    u.RolUsu,
                    u.EstadoUsu,
                    u.RequiereCambioContrasena,
                    p.NombrePer,
                    p.ApPatPer,
                    p.ApMatPer,
                    p.FechaNacimiento
                FROM activacion_cuenta a
                INNER JOIN usuario u ON u.ClvUsu = a.ClvUsu
                INNER JOIN persona p ON p.ClvPer = u.ClvPer
                WHERE a.TokenHash = :hash
                LIMIT 1";

        if ($bloquear) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['hash' => $tokenHash]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    public function obtenerPendienteVigentePorUsuario(
        string $clvUsu,
        string $tipo
    ): ?array {
        $sql = "SELECT *
                FROM activacion_cuenta
                WHERE ClvUsu = :clvUsu
                  AND TipoActivacion = :tipo
                  AND Estado = 'PENDIENTE'
                  AND FechaExpiracion > NOW()
                ORDER BY IdActivacion DESC
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvUsu' => $clvUsu,
            'tipo' => $tipo
        ]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    public function obtenerUltimaPorUsuario(
        string $clvUsu,
        string $tipo
    ): ?array {
        $sql = "SELECT *
                FROM activacion_cuenta
                WHERE ClvUsu = :clvUsu
                  AND TipoActivacion = :tipo
                ORDER BY IdActivacion DESC
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvUsu' => $clvUsu,
            'tipo' => $tipo
        ]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    public function revocarPendientes(
        string $clvUsu,
        string $tipo,
        ?int $exceptoId = null
    ): void {
        $sql = "UPDATE activacion_cuenta
                SET Estado = 'REVOCADA'
                WHERE ClvUsu = :clvUsu
                  AND TipoActivacion = :tipo
                  AND Estado = 'PENDIENTE'";

        $params = [
            'clvUsu' => $clvUsu,
            'tipo' => $tipo
        ];

        if ($exceptoId !== null) {
            $sql .= ' AND IdActivacion <> :excepto';
            $params['excepto'] = $exceptoId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    public function marcarUsada(int $idActivacion): void
    {
        $sql = "UPDATE activacion_cuenta
                SET
                    Estado = 'USADA',
                    FechaUso = NOW()
                WHERE IdActivacion = :id
                  AND Estado = 'PENDIENTE'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $idActivacion]);
    }

    public function marcarExpirada(int $idActivacion): void
    {
        $sql = "UPDATE activacion_cuenta
                SET Estado = 'EXPIRADA'
                WHERE IdActivacion = :id
                  AND Estado = 'PENDIENTE'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $idActivacion]);
    }

    public function registrarReenvio(
        int $idActivacion,
        int $numReenvios
    ): void {
        $sql = "UPDATE activacion_cuenta
                SET
                    FechaUltimoEnvio = NOW(),
                    NumReenvios = :reenvios
                WHERE IdActivacion = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'reenvios' => $numReenvios,
            'id' => $idActivacion
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function mapearUltimasPorUsuarios(
        array $clavesUsu,
        string $tipo
    ): array {
        if ($clavesUsu === []) {
            return [];
        }

        $placeholders = implode(
            ',',
            array_fill(0, count($clavesUsu), '?')
        );

        $sql = "SELECT a.*
                FROM activacion_cuenta a
                INNER JOIN (
                    SELECT ClvUsu, MAX(IdActivacion) AS MaxId
                    FROM activacion_cuenta
                    WHERE TipoActivacion = ?
                      AND ClvUsu IN ({$placeholders})
                    GROUP BY ClvUsu
                ) u ON u.MaxId = a.IdActivacion";

        $params = array_merge([$tipo], array_values($clavesUsu));
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $mapa = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $mapa[(string) $fila['ClvUsu']] = $fila;
        }

        return $mapa;
    }
}
