<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class PacienteResponsable extends Model
{
    /**
     * @param array{
     *   ClvPac: string,
     *   ClvUsuResponsable: string,
     *   Parentesco: string,
     *   EsTutorLegal?: int|bool,
     *   PuedeAgendar?: int|bool
     * } $datos
     */
    public function crearRelacion(array $datos): int
    {
        $sql = "INSERT INTO paciente_responsable (
                    ClvPac,
                    ClvUsuResponsable,
                    Parentesco,
                    EsTutorLegal,
                    PuedeAgendar,
                    EstadoRelacion,
                    FechaRegistro
                ) VALUES (
                    :pac,
                    :usu,
                    :parentesco,
                    :tutor,
                    :agendar,
                    'ACTIVA',
                    NOW()
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'pac' => trim((string) $datos['ClvPac']),
            'usu' => trim((string) $datos['ClvUsuResponsable']),
            'parentesco' => trim((string) $datos['Parentesco']),
            'tutor' => !empty($datos['EsTutorLegal']) ? 1 : 0,
            'agendar' => array_key_exists('PuedeAgendar', $datos)
                ? (!empty($datos['PuedeAgendar']) ? 1 : 0)
                : 1,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function obtenerPorResponsable(
        string $clvUsuResponsable,
        bool $soloActivas = false
    ): array {
        $sql = "SELECT
                    r.*,
                    p.ClvPac,
                    p.EstadoActivoPac,
                    p.ClvUsu AS ClvUsuPaciente,
                    per.NombrePer,
                    per.ApPatPer,
                    per.ApMatPer,
                    per.FechaNacimiento,
                    per.GeneroPer,
                    per.FotoPerfilPer
                FROM paciente_responsable r
                INNER JOIN paciente p ON p.ClvPac = r.ClvPac
                INNER JOIN persona per ON per.ClvPer = p.ClvPer
                WHERE r.ClvUsuResponsable = :usu";

        if ($soloActivas) {
            $sql .= " AND r.EstadoRelacion = 'ACTIVA'";
        }

        $sql .= ' ORDER BY r.FechaRegistro DESC, r.IdRelacion DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['usu' => trim($clvUsuResponsable)]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerRelacion(int $idRelacion): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM paciente_responsable
             WHERE IdRelacion = :id LIMIT 1'
        );
        $stmt->execute(['id' => $idRelacion]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    public function perteneceAResponsable(
        int $idRelacion,
        string $clvUsuResponsable
    ): bool {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM paciente_responsable
             WHERE IdRelacion = :id
               AND ClvUsuResponsable = :usu'
        );
        $stmt->execute([
            'id' => $idRelacion,
            'usu' => trim($clvUsuResponsable),
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function pertenecePacienteAResponsable(
        string $clvPac,
        string $clvUsuResponsable
    ): bool {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM paciente_responsable
             WHERE ClvPac = :pac
               AND ClvUsuResponsable = :usu
               AND EstadoRelacion = 'ACTIVA'"
        );
        $stmt->execute([
            'pac' => trim($clvPac),
            'usu' => trim($clvUsuResponsable),
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Relación ACTIVA con permiso de agendar (anti-IDOR en reserva).
     *
     * @return array<string, mixed>|null
     */
    public function obtenerParaAgendar(
        string $clvPac,
        string $clvUsuResponsable
    ): ?array {
        $stmt = $this->db->prepare(
            "SELECT r.*
             FROM paciente_responsable r
             WHERE r.ClvPac = :pac
               AND r.ClvUsuResponsable = :usu
               AND r.EstadoRelacion = 'ACTIVA'
               AND r.PuedeAgendar = 1
             LIMIT 1"
        );
        $stmt->execute([
            'pac' => trim($clvPac),
            'usu' => trim($clvUsuResponsable),
        ]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    /**
     * @param array{
     *   Parentesco?: string,
     *   EsTutorLegal?: int|bool,
     *   PuedeAgendar?: int|bool
     * } $datos
     */
    public function actualizar(
        int $idRelacion,
        string $clvUsuResponsable,
        array $datos
    ): bool {
        if (!$this->perteneceAResponsable($idRelacion, $clvUsuResponsable)) {
            return false;
        }

        $sets = [];
        $params = [
            'id' => $idRelacion,
            'usu' => trim($clvUsuResponsable),
        ];

        if (array_key_exists('Parentesco', $datos)) {
            $sets[] = 'Parentesco = :parentesco';
            $params['parentesco'] = trim((string) $datos['Parentesco']);
        }
        if (array_key_exists('EsTutorLegal', $datos)) {
            $sets[] = 'EsTutorLegal = :tutor';
            $params['tutor'] = !empty($datos['EsTutorLegal']) ? 1 : 0;
        }
        if (array_key_exists('PuedeAgendar', $datos)) {
            $sets[] = 'PuedeAgendar = :agendar';
            $params['agendar'] = !empty($datos['PuedeAgendar']) ? 1 : 0;
        }

        if ($sets === []) {
            return true;
        }

        $sets[] = 'FechaActualizacion = NOW()';
        $sql = 'UPDATE paciente_responsable SET '
            . implode(', ', $sets)
            . ' WHERE IdRelacion = :id AND ClvUsuResponsable = :usu';

        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function cambiarEstado(
        int $idRelacion,
        string $clvUsuResponsable,
        string $estado
    ): bool {
        $estado = strtoupper(trim($estado));
        if (!in_array($estado, ['ACTIVA', 'INACTIVA'], true)) {
            return false;
        }

        if (!$this->perteneceAResponsable($idRelacion, $clvUsuResponsable)) {
            return false;
        }

        $stmt = $this->db->prepare(
            "UPDATE paciente_responsable
             SET EstadoRelacion = :e,
                 FechaActualizacion = NOW()
             WHERE IdRelacion = :id
               AND ClvUsuResponsable = :usu"
        );

        return $stmt->execute([
            'e' => $estado,
            'id' => $idRelacion,
            'usu' => trim($clvUsuResponsable),
        ]);
    }
}
