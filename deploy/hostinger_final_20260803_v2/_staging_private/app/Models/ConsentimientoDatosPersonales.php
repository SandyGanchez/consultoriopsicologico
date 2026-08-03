<?php

namespace App\Models;

use App\Config\Database;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class ConsentimientoDatosPersonales
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function tablaDisponible(): bool
    {
        try {
            $this->db->query(
                'SELECT 1 FROM consentimiento_datos_personales LIMIT 1'
            );
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function obtenerVigentePorUsuarioYAviso(
        string $clvUsu,
        int $idAvisoPrivacidad
    ): ?array {
        $sql = "
            SELECT *
            FROM consentimiento_datos_personales
            WHERE ClvUsu = :clvUsu
              AND IdAvisoPrivacidad = :idAviso
              AND EstadoConsentimiento = 'VIGENTE'
              AND AvisoLeido = 1
              AND ConsentimientoDatosSensibles = 1
            ORDER BY FechaAceptacion DESC, IdConsentimiento DESC
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvUsu' => trim($clvUsu),
            'idAviso' => $idAvisoPrivacidad
        ]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    public function obtenerUltimoPorUsuario(string $clvUsu): ?array
    {
        $sql = "
            SELECT *
            FROM consentimiento_datos_personales
            WHERE ClvUsu = :clvUsu
            ORDER BY FechaAceptacion DESC, IdConsentimiento DESC
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvUsu' => trim($clvUsu)]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    /**
     * VersionAviso y HashContenidoAviso se leen SOLO desde aviso_privacidad_version.
     *
     * @param array{
     *   ClvUsu: string,
     *   IdAvisoPrivacidad: int,
     *   AvisoLeido: int|bool,
     *   ConsentimientoDatosSensibles: int|bool,
     *   MedioAceptacion: string
     * } $datos
     * @return array{ok: bool, creado: bool, mensaje?: string, id?: int}
     */
    public function registrarAceptacion(array $datos): array
    {
        $clvUsu = trim((string) ($datos['ClvUsu'] ?? ''));
        $idAviso = (int) ($datos['IdAvisoPrivacidad'] ?? 0);
        $medio = trim((string) ($datos['MedioAceptacion'] ?? ''));
        $medios = ['REGISTRO', 'ACTIVACION', 'REACEPTACION', 'PANEL'];

        if (
            $clvUsu === ''
            || $idAviso <= 0
            || !in_array($medio, $medios, true)
        ) {
            return [
                'ok' => false,
                'creado' => false,
                'mensaje' => 'Datos de consentimiento incompletos o inválidos.'
            ];
        }

        $propia = !$this->db->inTransaction();

        try {
            if ($propia) {
                $this->db->beginTransaction();
            }

            $this->bloquearUsuario($clvUsu);

            $aviso = $this->obtenerAvisoVigenteBloqueado($idAviso);

            if ($aviso === null) {
                throw new RuntimeException(
                    'El aviso de privacidad se encuentra en proceso de configuración.'
                );
            }

            $version = (string) $aviso['VersionAviso'];
            $hash = strtolower((string) $aviso['HashContenidoAviso']);

            $existente = $this->obtenerVigenteBloqueado($clvUsu, $idAviso);

            if ($existente !== null) {
                if ($propia) {
                    $this->db->commit();
                }

                return [
                    'ok' => true,
                    'creado' => false,
                    'id' => (int) $existente['IdConsentimiento']
                ];
            }

            $this->marcarOtrasVigentesComoSupersedidas($clvUsu);

            $sql = "
                INSERT INTO consentimiento_datos_personales (
                    ClvUsu,
                    IdAvisoPrivacidad,
                    VersionAviso,
                    HashContenidoAviso,
                    AvisoLeido,
                    ConsentimientoDatosSensibles,
                    FechaAceptacion,
                    MedioAceptacion,
                    EstadoConsentimiento,
                    FechaRevocacion,
                    FechaCambioEstado
                ) VALUES (
                    :clvUsu,
                    :idAviso,
                    :version,
                    :hash,
                    :aviso,
                    :sensibles,
                    NOW(),
                    :medio,
                    'VIGENTE',
                    NULL,
                    NULL
                )
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'clvUsu' => $clvUsu,
                'idAviso' => $idAviso,
                'version' => $version,
                'hash' => $hash,
                'aviso' => !empty($datos['AvisoLeido']) ? 1 : 0,
                'sensibles' => !empty($datos['ConsentimientoDatosSensibles'])
                    ? 1
                    : 0,
                'medio' => $medio
            ]);

            $id = (int) $this->db->lastInsertId();

            if ($propia) {
                $this->db->commit();
            }

            return [
                'ok' => true,
                'creado' => true,
                'id' => $id
            ];
        } catch (Throwable $e) {
            if ($propia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($e instanceof RuntimeException) {
                return [
                    'ok' => false,
                    'creado' => false,
                    'mensaje' => $e->getMessage()
                ];
            }

            throw $e;
        }
    }

    public function revocarVigentePorAviso(string $clvUsu, int $idAviso): bool
    {
        $propia = !$this->db->inTransaction();

        try {
            if ($propia) {
                $this->db->beginTransaction();
            }

            $this->bloquearUsuario($clvUsu);

            $sql = "
                UPDATE consentimiento_datos_personales
                SET EstadoConsentimiento = 'REVOCADO',
                    FechaRevocacion = NOW(),
                    FechaCambioEstado = NOW()
                WHERE ClvUsu = :clvUsu
                  AND IdAvisoPrivacidad = :idAviso
                  AND EstadoConsentimiento = 'VIGENTE'
            ";

            $stmt = $this->db->prepare($sql);
            $ok = $stmt->execute([
                'clvUsu' => trim($clvUsu),
                'idAviso' => $idAviso
            ]);

            if ($propia) {
                $this->db->commit();
            }

            return $ok;
        } catch (Throwable $e) {
            if ($propia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    private function bloquearUsuario(string $clvUsu): void
    {
        $stmt = $this->db->prepare(
            'SELECT ClvUsu FROM usuario WHERE ClvUsu = :clvUsu LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['clvUsu' => trim($clvUsu)]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('Usuario no encontrado para consentimiento.');
        }
    }

    private function obtenerAvisoVigenteBloqueado(int $idAviso): ?array
    {
        $sql = "
            SELECT IdAvisoPrivacidad, VersionAviso, HashContenidoAviso, EstadoAviso
            FROM aviso_privacidad_version
            WHERE IdAvisoPrivacidad = :id
              AND EstadoAviso = 'VIGENTE'
            LIMIT 1
            FOR UPDATE
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $idAviso]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    private function obtenerVigenteBloqueado(
        string $clvUsu,
        int $idAviso
    ): ?array {
        $sql = "
            SELECT *
            FROM consentimiento_datos_personales
            WHERE ClvUsu = :clvUsu
              AND IdAvisoPrivacidad = :idAviso
              AND EstadoConsentimiento = 'VIGENTE'
            ORDER BY FechaAceptacion DESC, IdConsentimiento DESC
            LIMIT 1
            FOR UPDATE
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'clvUsu' => trim($clvUsu),
            'idAviso' => $idAviso
        ]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    private function marcarOtrasVigentesComoSupersedidas(string $clvUsu): void
    {
        $sql = "
            UPDATE consentimiento_datos_personales
            SET EstadoConsentimiento = 'SUPERSEDIDO',
                FechaCambioEstado = NOW(),
                FechaRevocacion = NULL
            WHERE ClvUsu = :clvUsu
              AND EstadoConsentimiento = 'VIGENTE'
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['clvUsu' => trim($clvUsu)]);
    }
}
