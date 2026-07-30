<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Services\NotificacionService;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class NotificacionController extends Controller
{
    private array $usuario;
    private NotificacionService $notificacionService;

    public function __construct()
    {
        /*
        =====================================
              VALIDAR SESIÓN ACTIVA
        =====================================
        */

        if (!Session::has('usuario')) {
            Response::redirect('login');
        }

        $usuario = Session::get('usuario');

        if (
            !is_array($usuario)
            || empty($usuario['ClvUsu'])
        ) {
            Session::destroy();
            Response::redirect('login');
        }

        $this->usuario = $usuario;
        $this->notificacionService =
            new NotificacionService();
    }

    /*
    =====================================
        LISTAR NOTIFICACIONES DEL USUARIO
    =====================================
    */

    public function index(): void
{
    $clvUsuario = $this->usuario['ClvUsu'];

    $notificaciones =
        $this->notificacionService
            ->listarPorUsuario($clvUsuario);

    $totalNoLeidas =
        $this->notificacionService
            ->contarNoLeidas($clvUsuario);

    $this->view(
        'notificaciones/index',
        [
            'notificaciones' =>
                $notificaciones,

            'totalNoLeidas' =>
                $totalNoLeidas,

            'totalNotificacionesNoLeidas' =>
                $totalNoLeidas,

            'notificacionesRecientes' =>
                array_slice(
                    $notificaciones,
                    0,
                    5
                )
        ],
        $this->obtenerLayout()
    );
}
    

    /*
    =====================================
      ABRIR Y MARCAR COMO LEÍDA
    =====================================
    */

     public function abrir(string $clave): void
    {
        $clave = trim($clave);

        if ($clave === '') {
            Session::setFlash(
                'error',
                'La notificación no es válida.'
            );

            Response::redirect('notificaciones');
        }

        $clvUsuario =
            (string) $this->usuario['ClvUsu'];

        try {
            $notificacion =
                $this->notificacionService
                    ->obtenerPorClave(
                        $clave,
                        $clvUsuario
                    );

            if (!$notificacion) {
                Session::setFlash(
                    'error',
                    'La notificación no existe o no te pertenece.'
                );

                Response::redirect(
                    'notificaciones'
                );
            }

            if (
                (int) (
                    $notificacion['LeidaNotif']
                    ?? 0
                ) === 0
            ) {
                $this->notificacionService
                    ->marcarComoLeida(
                        $clave,
                        $clvUsuario
                    );
            }

            $ruta = trim(
                (string) (
                    $notificacion['RutaNotif']
                    ?? ''
                )
            );

            if ($ruta === '') {
                Response::redirect(
                    'notificaciones'
                );
            }

            /*
             * Solo permite rutas internas.
             * Evita redirecciones a sitios externos.
             */
            if (
                str_contains($ruta, '://')
                || str_starts_with($ruta, '//')
            ) {
                Response::redirect(
                    'notificaciones'
                );
            }

            $ruta = ltrim($ruta, '/');

            /*
             * Si la ruta vuelve al mismo listado,
             * únicamente marcará la notificación como leída.
             */
            Response::redirect($ruta);

        } catch (Throwable $e) {
            Session::setFlash(
                'error',
                'No fue posible abrir la notificación.'
            );

            Response::redirect('notificaciones');
        }
    }

   

    /*
    =====================================
        MARCAR UNA COMO LEÍDA
    =====================================
    */

     public function marcarLeida(
        string $clave
    ): void {
        $clave = trim($clave);

        if ($clave === '') {
            Session::setFlash(
                'error',
                'La notificación no es válida.'
            );

            Response::redirect('notificaciones');
        }

        try {
            $this->notificacionService
                ->marcarComoLeida(
                    $clave,
                    (string) $this->usuario[
                        'ClvUsu'
                    ]
                );

            Session::setFlash(
                'success',
                'Notificación marcada como leída.'
            );

        } catch (Throwable $e) {
            Session::setFlash(
                'error',
                'No fue posible marcar la notificación.'
            );
        }

        Response::redirect('notificaciones');
    }

    /*
    =====================================
       MARCAR TODAS COMO LEÍDAS
    =====================================
    */

    public function marcarTodasLeidas(): void
    {
        try {
            $this->notificacionService
                ->marcarTodasComoLeidas(
                    (string) $this->usuario[
                        'ClvUsu'
                    ]
                );

            Session::setFlash(
                'success',
                'Todas las notificaciones fueron marcadas como leídas.'
            );

        } catch (Throwable $e) {
            Session::setFlash(
                'error',
                'No fue posible actualizar las notificaciones.'
            );
        }

        Response::redirect('notificaciones');
    }


    /*
    =====================================
         ELIMINAR NOTIFICACIÓN
    =====================================
    */

     public function eliminar(
        string $clave
    ): void {
        $clave = trim($clave);

        if ($clave === '') {
            Session::setFlash(
                'error',
                'La notificación no es válida.'
            );

            Response::redirect('notificaciones');
        }

        try {
            $eliminada =
                $this->notificacionService
                    ->eliminar(
                        $clave,
                        (string) $this->usuario[
                            'ClvUsu'
                        ]
                    );

            if (!$eliminada) {
                Session::setFlash(
                    'error',
                    'La notificación no existe o no te pertenece.'
                );

                Response::redirect(
                    'notificaciones'
                );
            }

            Session::setFlash(
                'success',
                'La notificación fue eliminada correctamente.'
            );

        } catch (Throwable $e) {
            Session::setFlash(
                'error',
                'No fue posible eliminar la notificación.'
            );
        }

        Response::redirect('notificaciones');
    }
 private function obtenerLayout(): string
    {
        $rol = strtoupper(
            trim(
                (string) (
                    $this->usuario['RolUsu']
                    ?? ''
                )
            )
        );

        return match ($rol) {
            'ADMINISTRADOR' =>
                'master_admin',

            'CONSULTORIO' =>
                'master_consultorio',

            'PSICOLOGO' =>
                'master_psicologo',

            'PACIENTE' =>
                'master_paciente',

            default =>
                'master'
        };
    }


    /*
    =====================================
       VALIDAR RUTA INTERNA
    =====================================
    */

    private function esRutaInternaValida(
        string $ruta
    ): bool {
        if ($ruta === '') {
            return false;
        }

        /*
         * No permitir protocolos externos:
         * https://, http://, javascript:, etc.
         */
        if (
            preg_match(
                '/^[a-z][a-z0-9+\-.]*:/i',
                $ruta
            )
        ) {
            return false;
        }

        /*
         * No permitir URLs que empiecen con //.
         */
        if (str_starts_with($ruta, '//')) {
            return false;
        }

        /*
         * Limpiar la diagonal inicial porque
         * Response::redirect() trabaja con rutas
         * internas como "administrador/dashboard".
         */
        return true;
    }
}