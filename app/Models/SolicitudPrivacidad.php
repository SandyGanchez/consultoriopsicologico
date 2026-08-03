<?php

namespace App\Models;

use App\Config\Database;
use PDO;
use PDOException;

class SolicitudPrivacidad
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function tablaDisponible(): bool
    {
        try {
            $this->db->query('SELECT 1 FROM solicitud_privacidad LIMIT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * @return array{ok: bool, id?: int, mensaje?: string}
     */
    public function registrar(array $datos): array
    {
        $clvUsu = trim((string) ($datos['ClvUsu'] ?? ''));
        $tipo = trim((string) ($datos['TipoSolicitud'] ?? ''));
        $detalle = trim((string) ($datos['DetalleSolicitud'] ?? ''));

        if ($clvUsu === '' || $tipo === '') {
            return [
                'ok' => false,
                'mensaje' => 'La solicitud de privacidad está incompleta.'
            ];
        }

        $sql = "
            INSERT INTO solicitud_privacidad (
                ClvUsu,
                ClvPac,
                TipoSolicitud,
                DetalleSolicitud,
                NombreSolicitante,
                CorreoSolicitante,
                TelefonoSolicitante,
                IdAvisoPrivacidad,
                EstadoSolicitud
            ) VALUES (
                :clvUsu,
                :clvPac,
                :tipo,
                :detalle,
                :nombre,
                :correo,
                :telefono,
                :idAviso,
                'RECIBIDA'
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvUsu' => $clvUsu,
            'clvPac' => $this->nullableKey($datos['ClvPac'] ?? null),
            'tipo' => $tipo,
            'detalle' => $detalle !== '' ? $detalle : null,
            'nombre' => $this->nullableString($datos['NombreSolicitante'] ?? null),
            'correo' => $this->nullableString($datos['CorreoSolicitante'] ?? null),
            'telefono' => $this->nullableString($datos['TelefonoSolicitante'] ?? null),
            'idAviso' => !empty($datos['IdAvisoPrivacidad'])
                ? (int) $datos['IdAvisoPrivacidad']
                : null
        ]);

        return [
            'ok' => true,
            'id' => (int) $this->db->lastInsertId()
        ];
    }

    /**
     * Vista paciente: estado + respuesta titular (sin NotasInternas).
     *
     * @return list<array<string, mixed>>
     */
    public function listarParaPaciente(string $clvUsu): array
    {
        $sql = "
            SELECT
                IdSolicitudPrivacidad,
                TipoSolicitud,
                EstadoSolicitud,
                FechaSolicitud,
                FechaAtencion,
                RespuestaTitular,
                FechaRespuesta,
                IdAvisoPrivacidad
            FROM solicitud_privacidad
            WHERE ClvUsu = :clvUsu
            ORDER BY FechaSolicitud DESC, IdSolicitudPrivacidad DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvUsu' => trim($clvUsu)]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Vista consultorio: detalle operativo completo.
     *
     * @return list<array<string, mixed>>
     */
    public function listarParaConsultorio(?string $estado = null): array
    {
        $sql = "
            SELECT
                s.*,
                a.VersionAviso
            FROM solicitud_privacidad s
            LEFT JOIN aviso_privacidad_version a
                ON a.IdAvisoPrivacidad = s.IdAvisoPrivacidad
        ";

        $params = [];

        if ($estado !== null && $estado !== '') {
            $sql .= ' WHERE s.EstadoSolicitud = :estado';
            $params['estado'] = $estado;
        }

        $sql .= ' ORDER BY s.FechaSolicitud DESC, s.IdSolicitudPrivacidad DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerParaConsultorio(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM solicitud_privacidad WHERE IdSolicitudPrivacidad = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    /**
     * Resumen administrativo sin detalle sensible.
     *
     * @return list<array<string, mixed>>
     */
    public function listarResumenAdministrador(): array
    {
        $sql = "
            SELECT
                IdSolicitudPrivacidad,
                TipoSolicitud,
                EstadoSolicitud,
                FechaSolicitud,
                FechaAtencion,
                FechaRespuesta
            FROM solicitud_privacidad
            ORDER BY FechaSolicitud DESC, IdSolicitudPrivacidad DESC
        ";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array{ok: bool, mensaje?: string}
     */
    public function responderComoConsultorio(
        int $id,
        string $clvUsuAtencion,
        string $estado,
        string $respuestaTitular,
        ?string $notasInternas = null
    ): array {
        $estados = ['RECIBIDA', 'EN_REVISION', 'ATENDIDA', 'RECHAZADA'];

        if (!in_array($estado, $estados, true)) {
            return [
                'ok' => false,
                'mensaje' => 'Estado de solicitud no válido.'
            ];
        }

        $actual = $this->obtenerParaConsultorio($id);

        if ($actual === null) {
            return [
                'ok' => false,
                'mensaje' => 'Solicitud no encontrada.'
            ];
        }

        $sql = "
            UPDATE solicitud_privacidad
            SET EstadoSolicitud = :estado,
                RespuestaTitular = :respuesta,
                FechaRespuesta = NOW(),
                FechaAtencion = NOW(),
                ClvUsuAtencion = :atencion,
                NotasInternas = :notas
            WHERE IdSolicitudPrivacidad = :id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'estado' => $estado,
            'respuesta' => trim($respuestaTitular) !== ''
                ? trim($respuestaTitular)
                : null,
            'atencion' => trim($clvUsuAtencion),
            'notas' => $notasInternas !== null && trim($notasInternas) !== ''
                ? trim($notasInternas)
                : null,
            'id' => $id
        ]);

        return ['ok' => true];
    }

    private function nullableString(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto !== '' ? $texto : null;
    }

    private function nullableKey(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto !== '' ? $texto : null;
    }
}
