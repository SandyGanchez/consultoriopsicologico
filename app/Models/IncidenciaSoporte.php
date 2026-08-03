<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class IncidenciaSoporte extends Model
{
    public function tablaExiste(): bool
    {
        try {
            return (bool) $this->db
                ->query("SHOW TABLES LIKE 'incidencia_soporte'")
                ->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function insertar(array $datos): int
    {
        $sql = "INSERT INTO incidencia_soporte (
                    ClvCons,
                    ClvUsuSolicitante,
                    CorreoReportado,
                    TipoIncidencia,
                    RolDestino,
                    NivelAtencion,
                    IdIncidenciaOrigen,
                    Descripcion,
                    EstadoIncidencia,
                    FechaSolicitud
                ) VALUES (
                    :clvCons,
                    :solicitante,
                    :correo,
                    :tipo,
                    :rolDestino,
                    :nivelAtencion,
                    :idOrigen,
                    :descripcion,
                    'PENDIENTE',
                    NOW()
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvCons' => $datos['ClvCons'],
            'solicitante' => $datos['ClvUsuSolicitante'] ?? null,
            'correo' => $datos['CorreoReportado'],
            'tipo' => $datos['TipoIncidencia'],
            'rolDestino' => $datos['RolDestino'] ?? 'CONSULTORIO',
            'nivelAtencion' => $datos['NivelAtencion'] ?? 'PRIMER_NIVEL',
            'idOrigen' => $datos['IdIncidenciaOrigen'] ?? null,
            'descripcion' => $datos['Descripcion'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function insertarEscalada(
        string $clvCons,
        ?string $clvUsuSolicitante,
        string $correoReportado,
        string $tipoIncidencia,
        string $descripcion,
        int $idOrigen
    ): int {
        return $this->insertar([
            'ClvCons' => $clvCons,
            'ClvUsuSolicitante' => $clvUsuSolicitante,
            'CorreoReportado' => $correoReportado,
            'TipoIncidencia' => $tipoIncidencia,
            'RolDestino' => 'ADMINISTRADOR',
            'NivelAtencion' => 'ESCALADA',
            'IdIncidenciaOrigen' => $idOrigen,
            'Descripcion' => $descripcion,
        ]);
    }

    public function existeDuplicadoReciente(
        string $correo,
        string $tipo,
        string $descripcion,
        int $segundos = 60
    ): bool {
        $sql = "SELECT IdIncidencia
                FROM incidencia_soporte
                WHERE CorreoReportado = :correo
                  AND TipoIncidencia = :tipo
                  AND Descripcion = :descripcion
                  AND EstadoIncidencia = 'PENDIENTE'
                  AND FechaSolicitud >= DATE_SUB(NOW(), INTERVAL :segundos SECOND)
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('correo', $correo);
        $stmt->bindValue('tipo', $tipo);
        $stmt->bindValue('descripcion', $descripcion);
        $stmt->bindValue('segundos', $segundos, PDO::PARAM_INT);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPorDestino(string $clvCons, string $rolDestino): array
    {
        $sql = "SELECT
                    i.IdIncidencia,
                    i.ClvCons,
                    i.ClvUsuSolicitante,
                    i.CorreoReportado,
                    i.TipoIncidencia,
                    i.RolDestino,
                    i.NivelAtencion,
                    i.IdIncidenciaOrigen,
                    i.Descripcion,
                    i.EstadoIncidencia,
                    i.FechaSolicitud,
                    i.FechaActualizacion,
                    i.FechaResolucion,
                    i.ClvUsuAtencion,
                    i.ObservacionConsultorio,
                    i.ObservacionAdministrador,
                    u.RolUsu AS RolSolicitante
                FROM incidencia_soporte i
                LEFT JOIN usuario u ON u.ClvUsu = i.ClvUsuSolicitante
                WHERE i.ClvCons = :clvCons
                  AND i.RolDestino = :rolDestino
                ORDER BY
                    FIELD(i.EstadoIncidencia, 'PENDIENTE', 'EN_PROCESO', 'RESUELTA'),
                    i.FechaSolicitud DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvCons' => trim($clvCons),
            'rolDestino' => strtoupper(trim($rolDestino)),
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @deprecated Preferir listarPorDestino.
     * @return list<array<string, mixed>>
     */
    public function listarPorConsultorio(string $clvCons): array
    {
        return $this->listarPorDestino($clvCons, 'CONSULTORIO');
    }

    public function contarAbiertasPorDestino(
        string $clvCons,
        string $rolDestino
    ): int {
        $sql = "SELECT COUNT(*)
                FROM incidencia_soporte
                WHERE ClvCons = :clvCons
                  AND RolDestino = :rolDestino
                  AND EstadoIncidencia IN ('PENDIENTE', 'EN_PROCESO')";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvCons' => trim($clvCons),
            'rolDestino' => strtoupper(trim($rolDestino)),
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @deprecated Preferir contarAbiertasPorDestino.
     */
    public function contarAbiertas(string $clvCons): int
    {
        return $this->contarAbiertasPorDestino($clvCons, 'CONSULTORIO');
    }

    public function obtenerPorIdYConsultorioDestino(
        int $id,
        string $clvCons,
        string $rolDestino
    ): ?array {
        $sql = "SELECT
                    i.*,
                    u.RolUsu AS RolSolicitante,
                    u.EstadoUsu AS EstadoUsuSolicitante,
                    u.RequiereCambioContrasena AS RequiereCambioSolicitante
                FROM incidencia_soporte i
                LEFT JOIN usuario u ON u.ClvUsu = i.ClvUsuSolicitante
                WHERE i.IdIncidencia = :id
                  AND i.ClvCons = :clvCons
                  AND i.RolDestino = :rolDestino
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'clvCons' => trim($clvCons),
            'rolDestino' => strtoupper(trim($rolDestino)),
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function obtenerPorIdYConsultorio(
        int $id,
        string $clvCons
    ): ?array {
        $sql = "SELECT
                    i.*,
                    u.RolUsu AS RolSolicitante,
                    u.EstadoUsu AS EstadoUsuSolicitante,
                    u.RequiereCambioContrasena AS RequiereCambioSolicitante
                FROM incidencia_soporte i
                LEFT JOIN usuario u ON u.ClvUsu = i.ClvUsuSolicitante
                WHERE i.IdIncidencia = :id
                  AND i.ClvCons = :clvCons
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'clvCons' => trim($clvCons),
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function bloquearParaActualizar(
        int $id,
        string $clvCons
    ): ?array {
        $sql = "SELECT *
                FROM incidencia_soporte
                WHERE IdIncidencia = :id
                  AND ClvCons = :clvCons
                LIMIT 1
                FOR UPDATE";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'clvCons' => trim($clvCons),
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Actualiza estado. Pasa ObservacionConsultorio u ObservacionAdministrador
     * (solo el campo presente se escribe).
     */
    public function actualizarEstado(array $datos): bool
    {
        $campos = [
            'EstadoIncidencia = :estado',
            'ClvUsuAtencion = :atencion',
            'FechaActualizacion = NOW()',
            'FechaResolucion = :resolucion',
        ];
        $params = [
            'estado' => $datos['EstadoIncidencia'],
            'atencion' => $datos['ClvUsuAtencion'],
            'resolucion' => $datos['FechaResolucion'],
            'id' => $datos['IdIncidencia'],
            'clvCons' => $datos['ClvCons'],
        ];

        if (array_key_exists('ObservacionConsultorio', $datos)) {
            $campos[] = 'ObservacionConsultorio = :obsCons';
            $params['obsCons'] = $datos['ObservacionConsultorio'];
        }

        if (array_key_exists('ObservacionAdministrador', $datos)) {
            $campos[] = 'ObservacionAdministrador = :obsAdmin';
            $params['obsAdmin'] = $datos['ObservacionAdministrador'];
        }

        $sql = 'UPDATE incidencia_soporte SET '
            . implode(', ', $campos)
            . ' WHERE IdIncidencia = :id AND ClvCons = :clvCons';

        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function tieneEscaladaActiva(int $idOrigen): bool
    {
        $sql = "SELECT IdIncidencia
                FROM incidencia_soporte
                WHERE IdIncidenciaOrigen = :idOrigen
                  AND NivelAtencion = 'ESCALADA'
                  AND EstadoIncidencia <> 'RESUELTA'
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['idOrigen' => $idOrigen]);

        return (bool) $stmt->fetchColumn();
    }

    public function obtenerEscaladaHija(int $idOrigen): ?array
    {
        $sql = "SELECT
                    IdIncidencia,
                    EstadoIncidencia,
                    NivelAtencion,
                    RolDestino,
                    FechaSolicitud,
                    FechaResolucion,
                    Descripcion
                FROM incidencia_soporte
                WHERE IdIncidenciaOrigen = :idOrigen
                  AND NivelAtencion = 'ESCALADA'
                ORDER BY FechaSolicitud DESC, IdIncidencia DESC
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['idOrigen' => $idOrigen]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
