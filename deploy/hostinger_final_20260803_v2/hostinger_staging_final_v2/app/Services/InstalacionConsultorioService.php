<?php

namespace App\Services;

use App\Models\Consultorio;
use RuntimeException;

/**
 * Resolución del único consultorio de la instalación.
 * No selecciona de forma silenciosa entre varios registros.
 */
class InstalacionConsultorioService
{
    public const ESTADO_NINGUNO = 'ninguno';
    public const ESTADO_UNICO = 'unico';
    public const ESTADO_MULTIPLE = 'multiple';

    private Consultorio $consultorioModel;

    public function __construct(?Consultorio $consultorioModel = null)
    {
        $this->consultorioModel = $consultorioModel ?? new Consultorio();
    }

    /**
     * @return array{
     *   estado: 'ninguno'|'unico'|'multiple',
     *   consultorio: ?array,
     *   total: int
     * }
     */
    public function resolver(): array
    {
        return $this->consultorioModel->obtenerEstadoInstalacion();
    }

    /**
     * @return array|null Consultorio único, o null si la instalación está pendiente.
     *
     * @throws RuntimeException si hay más de un consultorio.
     */
    public function obtenerUnico(): ?array
    {
        return $this->consultorioModel->obtenerUnicoDeInstalacion();
    }

    public function existeConsultorio(): bool
    {
        return $this->consultorioModel->contarTodos() > 0;
    }

    /**
     * Clave del único consultorio, o null si no hay exactamente uno.
     */
    public function claveUnicaONull(): ?string
    {
        $estado = $this->resolver();

        if ($estado['estado'] !== self::ESTADO_UNICO) {
            return null;
        }

        $clave = strtoupper(trim((string) ($estado['consultorio']['ClvCons'] ?? '')));

        return $clave !== '' ? $clave : null;
    }

    public function coincideConUnico(string $clvCons): bool
    {
        $unica = $this->claveUnicaONull();

        if ($unica === null) {
            return false;
        }

        return $unica === strtoupper(trim($clvCons));
    }
}
