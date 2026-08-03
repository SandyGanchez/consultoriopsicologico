<?php

namespace App\Models;

use App\Core\Model;
use PDO;
use PDOException;

class CorreoCita extends Model
{
    public function tablaDisponible(): bool
    {
        try {
            $this->db->query('SELECT 1 FROM correo_cita LIMIT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * @param array{
     *   ClvCita: string,
     *   ClvUsuDestino: string,
     *   TipoCorreo: string,
     *   RolDestinatario: string,
     *   FechaProgramada: string,
     *   EstadoCorreo?: string,
     *   MotivoOmision?: ?string
     * } $datos
     */
    public function insertarIdempotente(array $datos): bool
    {
        $sql = "INSERT INTO correo_cita (
                    ClvCita,
                    ClvUsuDestino,
                    TipoCorreo,
                    RolDestinatario,
                    FechaProgramada,
                    EstadoCorreo,
                    MotivoOmision
                ) VALUES (
                    :clvCita,
                    :clvUsu,
                    :tipo,
                    :rol,
                    :fechaProg,
                    :estado,
                    :motivo
                )
                ON DUPLICATE KEY UPDATE
                    IdCorreoCita = IdCorreoCita";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'clvCita' => $datos['ClvCita'],
            'clvUsu' => $datos['ClvUsuDestino'],
            'tipo' => $datos['TipoCorreo'],
            'rol' => $datos['RolDestinatario'],
            'fechaProg' => $datos['FechaProgramada'],
            'estado' => $datos['EstadoCorreo'] ?? 'PENDIENTE',
            'motivo' => $datos['MotivoOmision'] ?? null
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPendientesProcesables(
        int $limite,
        int $maxIntentos
    ): array {
        $limite = max(1, min(100, $limite));
        $maxIntentos = max(1, min(10, $maxIntentos));

        $sql = "SELECT *
                FROM correo_cita
                WHERE (
                        EstadoCorreo = 'PENDIENTE'
                        OR (
                            EstadoCorreo = 'FALLIDO'
                            AND Intentos < :maxIntentos
                        )
                      )
                  AND FechaProgramada <= NOW()
                ORDER BY FechaProgramada ASC, IdCorreoCita ASC
                LIMIT {$limite}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['maxIntentos' => $maxIntentos]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerPorIdParaUpdate(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM correo_cita
             WHERE IdCorreoCita = :id
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function marcarProcesando(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE correo_cita
             SET EstadoCorreo = 'PROCESANDO',
                 Intentos = Intentos + 1,
                 FechaUltimoIntento = NOW(),
                 ErrorResumen = NULL
             WHERE IdCorreoCita = :id
               AND EstadoCorreo IN ('PENDIENTE', 'FALLIDO')"
        );

        return $stmt->execute(['id' => $id]) && $stmt->rowCount() > 0;
    }

    public function marcarEnviado(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE correo_cita
             SET EstadoCorreo = 'ENVIADO',
                 FechaEnvio = NOW(),
                 FechaUltimoIntento = NOW(),
                 ErrorResumen = NULL,
                 MotivoOmision = NULL
             WHERE IdCorreoCita = :id
               AND EstadoCorreo = 'PROCESANDO'"
        );

        return $stmt->execute(['id' => $id]) && $stmt->rowCount() > 0;
    }

    public function marcarFallido(int $id, string $errorResumen): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE correo_cita
             SET EstadoCorreo = 'FALLIDO',
                 FechaUltimoIntento = NOW(),
                 ErrorResumen = :error
             WHERE IdCorreoCita = :id
               AND EstadoCorreo = 'PROCESANDO'"
        );

        return $stmt->execute([
            'id' => $id,
            'error' => mb_substr(trim($errorResumen), 0, 255)
        ]) && $stmt->rowCount() > 0;
    }

    public function marcarOmitido(int $id, string $motivo): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE correo_cita
             SET EstadoCorreo = 'OMITIDO',
                 MotivoOmision = :motivo,
                 FechaUltimoIntento = NOW()
             WHERE IdCorreoCita = :id
               AND EstadoCorreo IN ('PENDIENTE', 'FALLIDO', 'PROCESANDO')"
        );

        return $stmt->execute([
            'id' => $id,
            'motivo' => mb_substr(trim($motivo), 0, 80)
        ]) && $stmt->rowCount() > 0;
    }

    /**
     * Recupera filas PROCESANDO abandonadas.
     */
    public function recuperarProcesandoAbandonados(int $minutos): int
    {
        $minutos = max(5, min(120, $minutos));
        $stmt = $this->db->prepare(
            "UPDATE correo_cita
             SET EstadoCorreo = 'FALLIDO',
                 ErrorResumen = 'PROCESO_INTERRUMPIDO',
                 FechaUltimoIntento = NOW()
             WHERE EstadoCorreo = 'PROCESANDO'
               AND FechaUltimoIntento IS NOT NULL
               AND FechaUltimoIntento < (NOW() - INTERVAL {$minutos} MINUTE)"
        );
        $stmt->execute();

        return (int) $stmt->rowCount();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarConfirmacionesPendientesPorCita(string $clvCita): array
    {
        $stmt = $this->db->prepare(
            "SELECT *
             FROM correo_cita
             WHERE ClvCita = :clvCita
               AND TipoCorreo = 'CONFIRMACION'
               AND EstadoCorreo IN ('PENDIENTE', 'FALLIDO')
             ORDER BY RolDestinatario ASC"
        );
        $stmt->execute(['clvCita' => $clvCita]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
