<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\Consultorio;
use App\Models\ConsultorioUsuario;
use App\Models\Psicologo;
use App\Services\NotificacionService;
use Throwable;

class NotificacionController extends Controller
{
    private array $usuario;
    private NotificacionService $notificacionService;

    public function __construct()
    {
        if (!Session::has('usuario')) {
            $this->responderNoAutorizado();
        }

        $usuario = Session::get('usuario');

        if (
            !is_array($usuario)
            || empty($usuario['ClvUsu'])
        ) {
            Session::destroy();
            $this->responderNoAutorizado();
        }

        $rol = strtoupper(trim(
            (string) ($usuario['RolUsu'] ?? '')
        ));

        if (
            !in_array(
                $rol,
                [
                    'PACIENTE',
                    'PSICOLOGO',
                    'CONSULTORIO',
                    'ADMINISTRADOR'
                ],
                true
            )
        ) {
            $this->responderNoAutorizado();
        }

        $this->usuario = $usuario;
        $this->notificacionService = new NotificacionService();
    }

    /*
    =====================================
        LISTAR NOTIFICACIONES
    =====================================
    */

    public function index(): void
    {
        $clvUsuario = (string) $this->usuario['ClvUsu'];

        $notificaciones = $this->notificacionService
            ->listarPorUsuario($clvUsuario, 50, 0);

        $totalNoLeidas = $this->notificacionService
            ->contarNoLeidas($clvUsuario);

        $datosVista = [
            'titulo' => 'Mis notificaciones',
            'notificaciones' => $notificaciones,
            'totalNoLeidas' => $totalNoLeidas,
            'totalNotificacionesNoLeidas' => $totalNoLeidas,
            'notificacionesRecientes' => array_slice(
                $notificaciones,
                0,
                5
            ),
            'csrfToken' => Session::csrfToken(),
            'usuario' => $this->usuario
        ];

        $rol = strtoupper(trim(
            (string) ($this->usuario['RolUsu'] ?? '')
        ));

        if ($rol === 'PSICOLOGO') {
            $contexto = $this->obtenerContextoLayoutPsicologo();
            $datosVista['psicologo'] = $contexto['psicologo'];
            $datosVista['consultorio'] = $contexto['consultorio'];
        }

        if ($rol === 'CONSULTORIO') {
            $datosVista['consultorio'] =
                $this->obtenerContextoLayoutConsultorio();
            $datosVista['usuario'] = $this->usuario;
        }

        $this->view(
            'notificaciones/index',
            $datosVista,
            $this->obtenerLayout()
        );
    }

    /*
    =====================================
         RECIENTES (JSON)
    =====================================
    */

    public function recientes(): void
    {
        $clvUsuario = (string) $this->usuario['ClvUsu'];

        $items = $this->notificacionService
            ->obtenerRecientes($clvUsuario, 5);

        $this->responderJson([
            'ok' => true,
            'notificaciones' => array_map(
                [$this, 'serializarNotificacion'],
                $items
            ),
            'noLeidas' => $this->notificacionService
                ->contarNoLeidas($clvUsuario)
        ]);
    }

    /*
    =====================================
        CONTAR NO LEÍDAS (JSON)
    =====================================
    */

    public function contarNoLeidas(): void
    {
        $total = $this->notificacionService->contarNoLeidas(
            (string) $this->usuario['ClvUsu']
        );

        $this->responderJson([
            'ok' => true,
            'noLeidas' => $total
        ]);
    }

    /*
    =====================================
      ABRIR Y MARCAR COMO LEÍDA
    =====================================
    */

    public function abrir(string $clave): void
    {
        $clave = trim($clave);
        $clvUsuario = (string) $this->usuario['ClvUsu'];

        if ($clave === '') {
            Session::setFlash(
                'error',
                'La notificación no es válida.'
            );
            Response::redirect('notificaciones');
        }

        try {
            $notificacion = $this->notificacionService
                ->obtenerPorClave($clave, $clvUsuario);

            if (!$notificacion) {
                Session::setFlash(
                    'error',
                    'La notificación no existe o no te pertenece.'
                );
                Response::redirect('notificaciones');
            }

            if ((int) ($notificacion['LeidaNotif'] ?? 0) === 0) {
                $this->notificacionService->marcarComoLeida(
                    $clave,
                    $clvUsuario
                );
            }

            Response::redirect(
                $this->resolverRutaRelacionada($notificacion)
            );
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

    public function marcarLeida(string $clave): void
    {
        if (!$this->esPost()) {
            $this->responderMetodoInvalido();
        }

        if (!$this->validarCsrfPeticion()) {
            $this->responderCsrfInvalido();
        }

        $clave = trim($clave);
        $clvUsuario = (string) $this->usuario['ClvUsu'];

        if ($clave === '') {
            if ($this->esAjax()) {
                $this->responderJson([
                    'ok' => false,
                    'mensaje' => 'La notificación no es válida.'
                ], 400);
            }

            Session::setFlash(
                'error',
                'La notificación no es válida.'
            );
            Response::redirect('notificaciones');
        }

        try {
            $this->notificacionService->marcarComoLeida(
                $clave,
                $clvUsuario
            );

            if ($this->esAjax()) {
                $this->responderJson([
                    'ok' => true,
                    'mensaje' => 'Notificación marcada como leída.',
                    'noLeidas' => $this->notificacionService
                        ->contarNoLeidas($clvUsuario)
                ]);
            }

            Session::setFlash(
                'success',
                'Notificación marcada como leída.'
            );
        } catch (Throwable $e) {
            if ($this->esAjax()) {
                $this->responderJson([
                    'ok' => false,
                    'mensaje' =>
                        'No fue posible marcar la notificación.'
                ], 400);
            }

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
        if (!$this->esPost()) {
            $this->responderMetodoInvalido();
        }

        if (!$this->validarCsrfPeticion()) {
            $this->responderCsrfInvalido();
        }

        $clvUsuario = (string) $this->usuario['ClvUsu'];

        try {
            $this->notificacionService->marcarTodasComoLeidas(
                $clvUsuario
            );

            if ($this->esAjax()) {
                $this->responderJson([
                    'ok' => true,
                    'mensaje' =>
                        'Todas las notificaciones fueron marcadas como leídas.',
                    'noLeidas' => 0
                ]);
            }

            Session::setFlash(
                'success',
                'Todas las notificaciones fueron marcadas como leídas.'
            );
        } catch (Throwable $e) {
            if ($this->esAjax()) {
                $this->responderJson([
                    'ok' => false,
                    'mensaje' =>
                        'No fue posible actualizar las notificaciones.'
                ], 400);
            }

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

    public function eliminar(string $clave): void
    {
        if (!$this->esPost()) {
            $this->responderMetodoInvalido();
        }

        if (!$this->validarCsrfPeticion()) {
            $this->responderCsrfInvalido();
        }

        $clave = trim($clave);
        $clvUsuario = (string) $this->usuario['ClvUsu'];

        if ($clave === '') {
            Session::setFlash(
                'error',
                'La notificación no es válida.'
            );
            Response::redirect('notificaciones');
        }

        try {
            $eliminada = $this->notificacionService->eliminar(
                $clave,
                $clvUsuario
            );

            if (!$eliminada) {
                Session::setFlash(
                    'error',
                    'La notificación no existe o no te pertenece.'
                );
                Response::redirect('notificaciones');
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

    /*
    =====================================
              HELPERS
    =====================================
    */

    private function obtenerLayout(): string
    {
        $rol = strtoupper(trim(
            (string) ($this->usuario['RolUsu'] ?? '')
        ));

        return match ($rol) {
            'ADMINISTRADOR' => 'master_admin',
            'CONSULTORIO' => 'consultorio',
            'PSICOLOGO' => 'psicologo',
            'PACIENTE' => 'paciente',
            default => 'master'
        };
    }

    private function obtenerContextoLayoutPsicologo(): array
    {
        $psicologo = (new Psicologo())->obtenerPorUsuario(
            (string) $this->usuario['ClvUsu']
        );

        if (!$psicologo) {
            Session::setFlash(
                'error',
                'No fue posible cargar tu panel.'
            );
            Response::redirect('psicologo');
        }

        $consultorio = (new Consultorio())->obtenerPorClave(
            (string) ($psicologo['ClvCons'] ?? '')
        );

        return [
            'psicologo' => $psicologo,
            'consultorio' => $consultorio ?: []
        ];
    }

    private function obtenerContextoLayoutConsultorio(): array
    {
        $consultorioUsuario = new ConsultorioUsuario();

        $consultorio = $consultorioUsuario->buscarPorUsuario(
            (string) $this->usuario['ClvUsu']
        );

        if (!$consultorio) {
            Session::setFlash(
                'error',
                'No fue posible cargar el panel del consultorio.'
            );
            Response::redirect('consultorio');
        }

        return $consultorio;
    }

    /**
     * Sin columna RutaNotif: enlace seguro al módulo relacionado.
     */
    private function resolverRutaRelacionada(array $notificacion): string
    {
        $rol = strtoupper(trim(
            (string) ($this->usuario['RolUsu'] ?? '')
        ));
        $tipo = strtoupper(trim(
            (string) ($notificacion['TipoNotif'] ?? '')
        ));

        if (
            in_array($tipo, ['CITA', 'CANCELACION'], true)
            && $rol === 'PACIENTE'
        ) {
            return 'paciente/mis-citas';
        }

        if (
            in_array($tipo, ['CITA', 'CANCELACION'], true)
            && $rol === 'PSICOLOGO'
        ) {
            return 'psicologo/agenda';
        }

        if (
            in_array($tipo, ['CITA', 'CANCELACION'], true)
            && $rol === 'CONSULTORIO'
        ) {
            return 'consultorio/agenda';
        }

        if (
            in_array($tipo, ['SISTEMA', 'CUENTA', 'PSICOLOGO'], true)
            && $rol === 'ADMINISTRADOR'
        ) {
            return 'administrador/consultorios';
        }

        return 'notificaciones';
    }

    private function serializarNotificacion(array $n): array
    {
        return [
            'ClvNotif' => (string) ($n['ClvNotif'] ?? ''),
            'TituloNotif' => (string) ($n['TituloNotif'] ?? ''),
            'MensajeNotif' => (string) ($n['MensajeNotif'] ?? ''),
            'TipoNotif' => (string) ($n['TipoNotif'] ?? ''),
            'FechaNotif' => (string) ($n['FechaNotif'] ?? ''),
            'LeidaNotif' => (int) ($n['LeidaNotif'] ?? 0)
        ];
    }

    private function esPost(): bool
    {
        return strtoupper(
            (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')
        ) === 'POST';
    }

    private function esAjax(): bool
    {
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $requested = strtolower(
            (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')
        );

        return $requested === 'xmlhttprequest'
            || str_contains($accept, 'application/json');
    }

    private function validarCsrfPeticion(): bool
    {
        $token = $_POST['csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? null;

        return Session::validarCsrf(
            is_string($token) ? $token : null
        );
    }

    private function responderJson(
        array $payload,
        int $codigo = 200
    ): void {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    private function responderNoAutorizado(): void
    {
        if ($this->esAjax()) {
            $this->responderJson([
                'ok' => false,
                'codigo' => 'SESION_EXPIRADA',
                'mensaje' => 'Tu sesión ha expirado.'
            ], 401);
        }

        Response::redirect('login');
    }

    private function responderMetodoInvalido(): void
    {
        if ($this->esAjax()) {
            $this->responderJson([
                'ok' => false,
                'mensaje' => 'Método no permitido.'
            ], 405);
        }

        Response::redirect('notificaciones');
    }

    private function responderCsrfInvalido(): void
    {
        if ($this->esAjax()) {
            $this->responderJson([
                'ok' => false,
                'mensaje' =>
                    'La solicitud no es válida. Inténtalo nuevamente.'
            ], 403);
        }

        Session::setFlash(
            'error',
            'La solicitud no es válida. Inténtalo nuevamente.'
        );
        Response::redirect('notificaciones');
    }
}
