<?php

namespace App\Services;

use App\Config\Database;
use App\Models\SugerenciaServicio;
use PDO;
use RuntimeException;
use Throwable;

class SugerenciaServicioService
{
    private PDO $db;
    private SugerenciaServicio $model;

    public function __construct(
        ?PDO $db = null,
        ?SugerenciaServicio $model = null
    ) {
        $this->db = $db ?? Database::connect();
        $this->model = $model ?? new SugerenciaServicio();
    }

    public function persistenciaDisponible(): bool
    {
        return $this->model->tablaDisponible();
    }

    /**
     * @return array{ok: bool, mensaje?: string, id?: int, idempotente?: bool}
     */
    public function crearSugerencia(
        string $clvPsi,
        string $clvCons,
        array $post
    ): array {
        if (!$this->persistenciaDisponible()) {
            return [
                'ok' => false,
                'mensaje' =>
                    'Las sugerencias de servicio aún no están habilitadas en esta instalación.'
            ];
        }

        $clvPsi = trim($clvPsi);
        $clvCons = trim($clvCons);

        $nombre = trim((string) ($post['nombreSugerido'] ?? ''));
        $descripcion = trim((string) ($post['descripcionSugerida'] ?? ''));
        $justificacion = trim((string) ($post['justificacion'] ?? ''));

        if ($nombre === '' || mb_strlen($nombre) > 60) {
            return [
                'ok' => false,
                'mensaje' => 'Indica un nombre sugerido válido (máximo 60 caracteres).'
            ];
        }

        if ($descripcion === '' || mb_strlen($descripcion) > 255) {
            return [
                'ok' => false,
                'mensaje' => 'Indica una descripción sugerida válida (máximo 255 caracteres).'
            ];
        }

        if ($justificacion === '' || mb_strlen($justificacion) > 500) {
            return [
                'ok' => false,
                'mensaje' => 'Indica una justificación válida (máximo 500 caracteres).'
            ];
        }

        if ($nombre !== strip_tags($nombre)
            || $descripcion !== strip_tags($descripcion)
            || $justificacion !== strip_tags($justificacion)
        ) {
            return [
                'ok' => false,
                'mensaje' => 'Los campos no pueden contener HTML.'
            ];
        }

        $propia = !$this->db->inTransaction();

        try {
            if ($propia) {
                $this->db->beginTransaction();
            }

            $lock = $this->db->prepare(
                "SELECT psi.ClvPsi, psi.ClvCons, psi.EstatusPsi, usu.EstadoUsu
                 FROM psicologo psi
                 INNER JOIN usuario usu ON usu.ClvUsu = psi.ClvUsu
                 WHERE psi.ClvPsi = :clvPsi
                 LIMIT 1
                 FOR UPDATE"
            );
            $lock->execute(['clvPsi' => $clvPsi]);
            $psi = $lock->fetch(PDO::FETCH_ASSOC);

            if (!$psi) {
                throw new RuntimeException('No se encontró al especialista.');
            }

            if ((string) ($psi['ClvCons'] ?? '') !== $clvCons) {
                throw new RuntimeException(
                    'El consultorio del especialista no coincide con la sesión.'
                );
            }

            if (($psi['EstatusPsi'] ?? '') !== 'ACTIVO' || (int) ($psi['EstadoUsu'] ?? 0) !== 1) {
                throw new RuntimeException(
                    'Tu cuenta o perfil no está activo para sugerir servicios.'
                );
            }

            $existenteId = $this->model->buscarPendientePorNombre(
                $clvPsi,
                $nombre
            );

            if ($existenteId !== null) {
                if ($propia) {
                    $this->db->commit();
                }

                return [
                    'ok' => true,
                    'id' => $existenteId,
                    'idempotente' => true,
                    'mensaje' =>
                        'Ya existe una sugerencia pendiente con ese nombre. No se duplicó el envío.'
                ];
            }

            $id = $this->model->crear([
                'ClvPsi' => $clvPsi,
                'ClvCons' => $clvCons,
                'NombreSugerido' => $nombre,
                'DescripcionSugerida' => $descripcion,
                'Justificacion' => $justificacion
            ]);

            if ($propia) {
                $this->db->commit();
            }

            return [
                'ok' => true,
                'id' => $id,
                'idempotente' => false,
                'mensaje' => 'Tu sugerencia se envió al consultorio.'
            ];
        } catch (Throwable $e) {
            if ($propia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'mensaje' => $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'No fue posible registrar la sugerencia.'
            ];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarParaPsicologo(string $clvPsi): array
    {
        if (!$this->persistenciaDisponible()) {
            return [];
        }

        return $this->model->listarPorPsicologo($clvPsi);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarParaConsultorio(string $clvCons): array
    {
        if (!$this->persistenciaDisponible()) {
            return [];
        }

        return $this->model->listarPorConsultorio($clvCons);
    }

    public function obtenerParaConsultorio(int $id, string $clvCons): ?array
    {
        if (!$this->persistenciaDisponible()) {
            return null;
        }

        return $this->model->obtenerPorIdYConsultorio($id, $clvCons);
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public function rechazar(
        int $id,
        string $clvCons,
        string $clvUsuRevision,
        string $observacion
    ): array {
        if (!$this->persistenciaDisponible()) {
            return [
                'ok' => false,
                'mensaje' => 'Las sugerencias no están habilitadas.'
            ];
        }

        $observacion = trim($observacion);

        if ($observacion === '') {
            return [
                'ok' => false,
                'mensaje' => 'La observación es obligatoria al rechazar.'
            ];
        }

        if (mb_strlen($observacion) > 500) {
            return [
                'ok' => false,
                'mensaje' => 'La observación no puede superar 500 caracteres.'
            ];
        }

        if ($observacion !== strip_tags($observacion)) {
            return [
                'ok' => false,
                'mensaje' => 'La observación no puede contener HTML.'
            ];
        }

        $propia = !$this->db->inTransaction();

        try {
            if ($propia) {
                $this->db->beginTransaction();
            }

            $ok = $this->model->rechazar(
                $id,
                trim($clvCons),
                trim($clvUsuRevision),
                $observacion
            );

            if (!$ok) {
                throw new RuntimeException(
                    'No fue posible rechazar la sugerencia.'
                );
            }

            if ($propia) {
                $this->db->commit();
            }

            return [
                'ok' => true,
                'mensaje' => 'La sugerencia fue rechazada.'
            ];
        } catch (Throwable $e) {
            if ($propia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'ok' => false,
                'mensaje' => $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'No fue posible rechazar la sugerencia.'
            ];
        }
    }

    /**
     * Marca APROBADA tras crear el servicio institucional (misma transacción).
     */
    public function marcarAprobadaConServicio(
        int $id,
        string $clvCons,
        string $clvUsuRevision,
        string $clvServCreado
    ): void {
        if ($id <= 0) {
            return;
        }

        if (!$this->persistenciaDisponible()) {
            throw new RuntimeException(
                'Las sugerencias no están habilitadas.'
            );
        }

        $sugerencia = $this->model->obtenerPorIdYConsultorio($id, $clvCons);
        if (
            $sugerencia === null
            || ($sugerencia['EstadoSugerencia'] ?? '') !== 'PENDIENTE'
        ) {
            throw new RuntimeException(
                'La sugerencia no está disponible para aprobar.'
            );
        }

        $ok = $this->model->marcarAprobada(
            $id,
            $clvCons,
            $clvUsuRevision,
            $clvServCreado
        );

        if (!$ok) {
            throw new RuntimeException(
                'No fue posible vincular la sugerencia al servicio creado.'
            );
        }
    }
}
