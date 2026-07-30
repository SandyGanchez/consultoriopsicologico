<?php

namespace App\Services;

use App\Models\Notificacion;
use InvalidArgumentException;
use RuntimeException;

class NotificacionService
{
    private Notificacion $notificacionModel;

    public function __construct()
    {
        $this->notificacionModel =
            new Notificacion();
    }

    /*
    =====================================
          CREAR NOTIFICACIÓN
    =====================================
    */

    public function crear(array $datos): array
    {
        $clvUsuario = trim(
            $datos['ClvUsu'] ?? ''
        );

        $titulo = trim(
            $datos['TituloNotif'] ?? ''
        );

        $mensaje = trim(
            $datos['MensajeNotif'] ?? ''
        );

        $tipo = strtoupper(
            trim(
                $datos['TipoNotif']
                ?? 'SISTEMA'
            )
        );

        $ruta = isset($datos['RutaNotif'])
            ? trim((string) $datos['RutaNotif'])
            : null;

        $this->validarDatos(
            $clvUsuario,
            $titulo,
            $mensaje,
            $tipo,
            $ruta
        );

        $clave = ClaveService::generar(
            'notificacion',
            'ClvNotif',
            'NOT'
        );

        $creada =
            $this->notificacionModel->crear([
                'ClvNotif' => $clave,
                'TituloNotif' => $titulo,
                'MensajeNotif' => $mensaje,
                'TipoNotif' => $tipo,
                'RutaNotif' =>
                    $ruta !== ''
                        ? $ruta
                        : null,
                'ClvUsu' => $clvUsuario
            ]);

        if (!$creada) {
            throw new RuntimeException(
                'No fue posible crear la notificación.'
            );
        }

        return [
            'ClvNotif' => $clave,
            'TituloNotif' => $titulo,
            'MensajeNotif' => $mensaje,
            'TipoNotif' => $tipo,
            'RutaNotif' => $ruta,
            'ClvUsu' => $clvUsuario
        ];
    }

    /*
    =====================================
       LISTAR TODAS POR USUARIO
    =====================================
    */

    public function listarPorUsuario(
        string $clvUsuario
    ): array {
        $clvUsuario = trim($clvUsuario);

        if ($clvUsuario === '') {
            throw new InvalidArgumentException(
                'La clave del usuario es obligatoria.'
            );
        }

        return $this->notificacionModel
            ->obtenerPorUsuario(
                $clvUsuario
            );
    }

    /*
    =====================================
         OBTENER NOTIFICACIONES RECIENTES
    =====================================
    */

    public function obtenerRecientes(
        string $clvUsuario,
        int $limite = 5
    ): array {
        $clvUsuario = trim($clvUsuario);

        if ($clvUsuario === '') {
            throw new InvalidArgumentException(
                'La clave del usuario es obligatoria.'
            );
        }

        if ($limite < 1) {
            $limite = 1;
        }

        if ($limite > 20) {
            $limite = 20;
        }

        return $this->notificacionModel
            ->obtenerRecientes(
                $clvUsuario,
                $limite
            );
    }

    /*
    =====================================
          CONTAR NO LEÍDAS
    =====================================
    */

    public function contarNoLeidas(
        string $clvUsuario
    ): int {
        $clvUsuario = trim($clvUsuario);

        if ($clvUsuario === '') {
            return 0;
        }

        return $this->notificacionModel
            ->contarNoLeidas(
                $clvUsuario
            );
    }

    /*
    =====================================
       OBTENER UNA NOTIFICACIÓN
    =====================================
    */

  public function obtenerPorClave(
    string $clave,
    string $clvUsuario
): ?array {
    return $this->notificacionModel
        ->obtenerPorClave(
            $clave,
            $clvUsuario
        );
}

    /*
    =====================================
          MARCAR COMO LEÍDA
    =====================================
    */

    public function marcarComoLeida(
        string $clave,
        string $clvUsuario
    ): bool {
        $clave = trim($clave);
        $clvUsuario = trim($clvUsuario);

        if (
            $clave === ''
            || $clvUsuario === ''
        ) {
            throw new InvalidArgumentException(
                'La notificación y el usuario son obligatorios.'
            );
        }

        $notificacion =
            $this->notificacionModel
                ->obtenerPorClave(
                    $clave,
                    $clvUsuario
                );

        if (!$notificacion) {
            throw new RuntimeException(
                'La notificación no existe o no pertenece al usuario.'
            );
        }

        if (
            (int) (
                $notificacion['LeidaNotif']
                ?? 0
            ) === 1
        ) {
            return true;
        }

        return $this->notificacionModel
            ->marcarComoLeida(
                $clave,
                $clvUsuario
            );
    }

    /*
    =====================================
       MARCAR TODAS COMO LEÍDAS
    =====================================
    */

    public function marcarTodasComoLeidas(
        string $clvUsuario
    ): bool {
        $clvUsuario = trim($clvUsuario);

        if ($clvUsuario === '') {
            throw new InvalidArgumentException(
                'La clave del usuario es obligatoria.'
            );
        }

        return $this->notificacionModel
            ->marcarTodasComoLeidas(
                $clvUsuario
            );
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
        $clave = trim($clave);
        $clvUsuario = trim($clvUsuario);

        if (
            $clave === ''
            || $clvUsuario === ''
        ) {
            throw new InvalidArgumentException(
                'La notificación y el usuario son obligatorios.'
            );
        }

        $notificacion =
            $this->notificacionModel
                ->obtenerPorClave(
                    $clave,
                    $clvUsuario
                );

        if (!$notificacion) {
            throw new RuntimeException(
                'La notificación no existe o no pertenece al usuario.'
            );
        }

        return $this->notificacionModel
            ->eliminar(
                $clave,
                $clvUsuario
            );
    }

    /*
    =====================================
        VALIDAR DATOS DE CREACIÓN
    =====================================
    */

    private function validarDatos(
        string $clvUsuario,
        string $titulo,
        string $mensaje,
        string $tipo,
        ?string $ruta
    ): void {
        if ($clvUsuario === '') {
            throw new InvalidArgumentException(
                'El usuario destinatario es obligatorio.'
            );
        }

        if ($titulo === '') {
            throw new InvalidArgumentException(
                'El título de la notificación es obligatorio.'
            );
        }

        if (mb_strlen($titulo) > 100) {
            throw new InvalidArgumentException(
                'El título no puede exceder 100 caracteres.'
            );
        }

        if ($mensaje === '') {
            throw new InvalidArgumentException(
                'El mensaje de la notificación es obligatorio.'
            );
        }

        $tiposPermitidos = [
            'CITA',
            'CANCELACION',
            'RECORDATORIO',
            'CUENTA',
            'PSICOLOGO',
            'SISTEMA',
            'OTRA'
        ];

        if (
            !in_array(
                $tipo,
                $tiposPermitidos,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'El tipo de notificación no es válido.'
            );
        }

        if (
            $ruta !== null
            && mb_strlen($ruta) > 255
        ) {
            throw new InvalidArgumentException(
                'La ruta de la notificación no puede exceder 255 caracteres.'
            );
        }
    }
}