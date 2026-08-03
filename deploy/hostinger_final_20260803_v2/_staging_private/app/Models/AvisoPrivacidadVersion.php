<?php

namespace App\Models;

use App\Config\Database;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class AvisoPrivacidadVersion
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function tablaDisponible(): bool
    {
        try {
            $this->db->query('SELECT 1 FROM aviso_privacidad_version LIMIT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function obtenerVigente(): ?array
    {
        $sql = "
            SELECT *
            FROM aviso_privacidad_version
            WHERE EstadoAviso = 'VIGENTE'
            ORDER BY FechaPublicacion DESC, IdAvisoPrivacidad DESC
            LIMIT 1
        ";

        $fila = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM aviso_privacidad_version WHERE IdAvisoPrivacidad = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    public function obtenerPorVersion(string $version): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM aviso_privacidad_version WHERE VersionAviso = :v LIMIT 1'
        );
        $stmt->execute(['v' => trim($version)]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    /**
     * Publica versión inmutable.
     * - Idempotente si misma versión + mismo hash/contenido.
     * - Rechaza misma versión con contenido diferente.
     * - No modifica ContenidoAviso/Hash de filas ya publicadas.
     *
     * @return array{ok: bool, id?: int, hash?: string, mensaje?: string, creado?: bool}
     */
    public function publicarVersion(
        string $version,
        string $contenidoNormalizado,
        string $hash,
        ?string $fechaPublicacion = null
    ): array {
        $version = trim($version);
        $hash = strtolower(trim($hash));

        if ($version === '' || $contenidoNormalizado === '') {
            return [
                'ok' => false,
                'mensaje' => 'Versión o contenido del aviso incompletos.'
            ];
        }

        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            return [
                'ok' => false,
                'mensaje' => 'Hash del aviso inválido.'
            ];
        }

        $propia = !$this->db->inTransaction();

        try {
            if ($propia) {
                $this->db->beginTransaction();
            }

            $stmtLock = $this->db->prepare(
                "SELECT *
                 FROM aviso_privacidad_version
                 WHERE VersionAviso = :v OR EstadoAviso = 'VIGENTE'
                 ORDER BY IdAvisoPrivacidad ASC
                 FOR UPDATE"
            );
            $stmtLock->execute(['v' => $version]);
            $bloqueadas = $stmtLock->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $existenteMismaVersion = null;
            foreach ($bloqueadas as $fila) {
                if ((string) ($fila['VersionAviso'] ?? '') === $version) {
                    $existenteMismaVersion = $fila;
                    break;
                }
            }

            if ($existenteMismaVersion !== null) {
                $hashExistente = strtolower(
                    (string) ($existenteMismaVersion['HashContenidoAviso'] ?? '')
                );
                $contenidoExistente = (string) (
                    $existenteMismaVersion['ContenidoAviso'] ?? ''
                );

                if (
                    $hashExistente === $hash
                    && $contenidoExistente === $contenidoNormalizado
                ) {
                    if ($propia) {
                        $this->db->commit();
                    }

                    return [
                        'ok' => true,
                        'creado' => false,
                        'id' => (int) $existenteMismaVersion['IdAvisoPrivacidad'],
                        'hash' => $hash,
                        'mensaje' =>
                            'La versión ya estaba publicada con el mismo contenido (idempotente).'
                    ];
                }

                if ($propia) {
                    $this->db->rollBack();
                }

                return [
                    'ok' => false,
                    'creado' => false,
                    'mensaje' =>
                        'La versión ' . $version
                        . ' ya existe con contenido distinto y no puede modificarse.'
                ];
            }

            $this->db->exec(
                "UPDATE aviso_privacidad_version
                 SET EstadoAviso = 'SUSTITUIDO'
                 WHERE EstadoAviso = 'VIGENTE'"
            );

            $stmt = $this->db->prepare(
                "INSERT INTO aviso_privacidad_version (
                    VersionAviso,
                    FechaPublicacion,
                    ContenidoAviso,
                    HashContenidoAviso,
                    EstadoAviso
                ) VALUES (
                    :version,
                    :fecha,
                    :contenido,
                    :hash,
                    'VIGENTE'
                )"
            );

            $stmt->execute([
                'version' => $version,
                'fecha' => $fechaPublicacion ?: date('Y-m-d H:i:s'),
                'contenido' => $contenidoNormalizado,
                'hash' => $hash
            ]);

            $id = (int) $this->db->lastInsertId();

            if ($propia) {
                $this->db->commit();
            }

            return [
                'ok' => true,
                'creado' => true,
                'id' => $id,
                'hash' => $hash
            ];
        } catch (Throwable $e) {
            if ($propia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($e instanceof RuntimeException) {
                return [
                    'ok' => false,
                    'mensaje' => $e->getMessage()
                ];
            }

            throw $e;
        }
    }
}
