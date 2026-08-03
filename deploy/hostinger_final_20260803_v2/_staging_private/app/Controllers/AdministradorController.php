<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Helper;
use App\Models\ActivacionCuenta;
use App\Models\Consultorio;
use App\Models\Notificacion;
use App\Services\AccesoSesionService;
use App\Services\ActivacionCuentaService;
use App\Services\AdministradorService;
use App\Services\IncidenciaSoporteService;
use App\Services\InstalacionConsultorioService;
use App\Services\NotificacionService;
use App\Services\PublicacionConsultorioService;
use Throwable;
use RuntimeException;

class AdministradorController extends Controller
{
    private array $usuario;

    public function __construct()
    {
        (new AccesoSesionService())->exigirSesionActiva('ADMINISTRADOR');
        $this->usuario = Session::get('usuario');
    }

    /*
    =========================================
                DASHBOARD
    =========================================
    */

public function dashboard(): void
{
    $instalacion = new InstalacionConsultorioService();
    $estado = $instalacion->resolver();

    $consultorio = null;
    $notificaciones = [];
    $estadoPaginaPublica = null;
    $activacionInfo = null;
    $inconsistenciaCuenta = null;
    $appUrl = (string) (\App\Config\Config::get('APP_URL', ''));

    if ($estado['estado'] === InstalacionConsultorioService::ESTADO_MULTIPLE) {
        error_log(
            'ADMIN_INSTALACION: más de un consultorio detectado (sin LIMIT 1)'
        );
        Session::setFlash(
            'error',
            'Se detectó más de un consultorio en la instalación. Corrige la base de datos: solo debe existir uno.'
        );
    } elseif ($estado['estado'] === InstalacionConsultorioService::ESTADO_UNICO) {
        $consultorio = $estado['consultorio'];
        $publicacionService = new PublicacionConsultorioService();
        $estadoPaginaPublica = $publicacionService->derivarEstadoPagina(
            $consultorio,
            (int) ($consultorio['EstadoUsu'] ?? 0)
        );

        $notificaciones = (new Notificacion())->listarRecientesPorUsuario(
            (string) ($this->usuario['ClvUsu'] ?? ''),
            5
        );

        try {
            $cuenta = (new AdministradorService())
                ->resolverCuentaPrincipalUnica(
                    (string) ($consultorio['ClvCons'] ?? '')
                );
            $consultorio = array_merge($consultorio, $cuenta);
            $activacionInfo = $this->obtenerInfoActivacion(
                (string) ($cuenta['ClvUsu'] ?? '')
            );
        } catch (Throwable $e) {
            $inconsistenciaCuenta = $e->getMessage();
        }
    }

    $this->view(
        'administrador/dashboard',
        [
            'usuario' => $this->usuario,
            'estadoInstalacion' => $estado['estado'],
            'consultorio' => $consultorio,
            'estadoPaginaPublica' => $estadoPaginaPublica,
            'notificaciones' => $notificaciones,
            'activacionInfo' => $activacionInfo,
            'inconsistenciaCuenta' => $inconsistenciaCuenta,
            'appUrl' => $appUrl,
            'moduloIncidenciasDisponible' =>
                (new IncidenciaSoporteService())->moduloDisponible(),
            'incidenciasAbiertas' =>
                (new IncidenciaSoporteService())->contarAbiertasAdministrador(),
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error')
        ],
        'master_admin'
    );
}

    /*
    =========================================
              INCIDENCIAS DE ACCESO
    =========================================
    */

    public function listarIncidencias(): void
    {
        $servicio = new IncidenciaSoporteService();

        if (!$servicio->moduloDisponible()) {
            Session::setFlash(
                'error',
                'El módulo de incidencias aún no está disponible.'
            );
            Response::redirect('administrador');
            return;
        }

        try {
            $incidencias = $servicio->listarParaAdministrador();
        } catch (Throwable $e) {
            Session::setFlash('error', $e->getMessage());
            Response::redirect('administrador');
            return;
        }

        $this->view(
            'administrador/incidencias/index',
            [
                'usuario' => $this->usuario,
                'incidencias' => $incidencias,
                'servicio' => $servicio,
                'success' => Session::getFlash('success'),
                'error' => Session::getFlash('error'),
            ],
            'master_admin'
        );
    }

    public function verIncidencia(string $id): void
    {
        $servicio = new IncidenciaSoporteService();

        if (!$servicio->moduloDisponible()) {
            Session::setFlash(
                'error',
                'El módulo de incidencias aún no está disponible.'
            );
            Response::redirect('administrador');
            return;
        }

        $idInc = (int) $id;

        try {
            $incidencia = $servicio->obtenerDetalleAdministrador($idInc);
            $consultorio = $this->exigirConsultorioUnicoDeInstalacion();
            $cuentaPrincipal = null;
            $activacionInfo = null;

            try {
                $cuentaPrincipal = (new AdministradorService())
                    ->resolverCuentaPrincipalUnica(
                        (string) ($consultorio['ClvCons'] ?? '')
                    );
                $activacionInfo = $this->obtenerInfoActivacion(
                    (string) ($cuentaPrincipal['ClvUsu'] ?? '')
                );
            } catch (Throwable $e) {
                $cuentaPrincipal = null;
            }
        } catch (Throwable $e) {
            Session::setFlash('error', $e->getMessage());
            Response::redirect('administrador/incidencias');
            return;
        }

        // Acciones de cuenta principal solo si el solicitante es CONSULTORIO principal.
        $rolSolicitante = strtoupper(trim(
            (string) ($incidencia['RolSolicitante'] ?? '')
        ));
        $relacionadaPrincipal = is_array($cuentaPrincipal)
            && $rolSolicitante === 'CONSULTORIO'
            && trim((string) ($incidencia['ClvUsuSolicitante'] ?? '')) !== ''
            && trim((string) ($incidencia['ClvUsuSolicitante'] ?? ''))
                === trim((string) ($cuentaPrincipal['ClvUsu'] ?? ''));

        $this->view(
            'administrador/incidencias/ver',
            [
                'usuario' => $this->usuario,
                'incidencia' => $incidencia,
                'servicio' => $servicio,
                'relacionadaPrincipal' => $relacionadaPrincipal,
                'cuentaPrincipal' => $cuentaPrincipal,
                'activacionInfo' => $activacionInfo,
                'success' => Session::getFlash('success'),
                'error' => Session::getFlash('error'),
            ],
            'master_admin'
        );
    }

    public function actualizarIncidencia(string $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('administrador/incidencias');
            return;
        }

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            Session::setFlash(
                'error',
                'La solicitud no es válida. Intenta nuevamente.'
            );
            Response::redirect('administrador/incidencias/' . (int) $id);
            return;
        }

        // Ignorar cualquier ClvCons / ClvUsuAtencion / rol del POST.
        unset(
            $_POST['ClvCons'],
            $_POST['ClvUsuAtencion'],
            $_POST['ClvUsuSolicitante'],
            $_POST['RolDestino'],
            $_POST['RolUsu']
        );

        $servicio = new IncidenciaSoporteService();

        try {
            $resultado = $servicio->cambiarEstadoAdministrador(
                (int) $id,
                (string) ($_POST['estado'] ?? ''),
                (string) ($_POST['observacion'] ?? ''),
                (string) ($this->usuario['ClvUsu'] ?? '')
            );
            Session::setFlash('success', $resultado['mensaje']);
        } catch (Throwable $e) {
            Session::setFlash('error', $e->getMessage());
        }

        Response::redirect('administrador/incidencias/' . (int) $id);
    }

    /*
    =========================================
          LISTAR CONSULTORIOS
    =========================================
    */

public function listarConsultorios(): void
{
    $instalacion = new InstalacionConsultorioService();
    $estado = $instalacion->resolver();

    if ($estado['estado'] === InstalacionConsultorioService::ESTADO_NINGUNO) {
        Response::redirect('administrador/consultorio/configurar');
        return;
    }

    if ($estado['estado'] === InstalacionConsultorioService::ESTADO_MULTIPLE) {
        Session::setFlash(
            'error',
            'Se detectó más de un consultorio. Esta instalación admite exactamente uno.'
        );
        Response::redirect('administrador');
        return;
    }

    Response::redirect('administrador/consultorio');
}

/**
 * Legacy multiconsultorio: no opera sobre {id}; redirige a la cuenta única.
 */
public function redirigirCuentaUnica(string $id = ''): void
{
    unset($id);
    Response::redirect('administrador/consultorio');
}

/**
 * Legacy POST multiconsultorio: no ejecuta la acción ni usa ClvCons de la URL.
 */
public function redirigirCuentaUnicaPost(string $id = ''): void
{
    unset($id);
    Session::setFlash(
        'error',
        'Esa operación ya no acepta un identificador externo. Usa la cuenta del consultorio de esta instalación.'
    );
    Response::redirect('administrador/consultorio');
}

/**
 * @deprecated Legacy stub.
 */
public function vistaPreviaConsultorio(string $id): void
{
    $this->redirigirCuentaUnica($id);
}
    /*
=========================================
      FORMULARIO DE REGISTRO
=========================================
*/

public function crearConsultorio(): void
{
    $instalacion = new InstalacionConsultorioService();
    $estado = $instalacion->resolver();

    if ($estado['estado'] === InstalacionConsultorioService::ESTADO_UNICO) {
        Session::setFlash(
            'error',
            'Ya existe el consultorio de esta instalación.'
        );
        Response::redirect('administrador/consultorio');
        return;
    }

    if ($estado['estado'] === InstalacionConsultorioService::ESTADO_MULTIPLE) {
        Session::setFlash(
            'error',
            'Se detectó más de un consultorio. No es posible registrar otro.'
        );
        Response::redirect('administrador');
        return;
    }

    $this->view(
        'administrador/consultorios/form',
        [
            'usuario' => $this->usuario,
            'consultorio' => [],
            'datos' => Session::getFlash('datos') ?? [],
            'errores' => Session::getFlash('errores') ?? []
        ],
        'master_admin'
    );
}
/*
=========================================
      GUARDAR CONSULTORIO
=========================================
*/

public function guardarConsultorio(): void
{
    if (
        ($_SERVER['REQUEST_METHOD'] ?? 'GET')
        !== 'POST'
    ) {
        Response::redirect(
            'administrador/consultorio/configurar'
        );

        return;
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        Session::setFlash(
            'errores',
            [
                'general' =>
                    'La solicitud no es válida. Intenta nuevamente.'
            ]
        );

        Response::redirect(
            'administrador/consultorio/configurar'
        );

        return;
    }

    $datos = $this->obtenerDatosFormulario();

    $errores = $this->validarDatosConsultorio(
        $datos
    );

    if (!empty($errores)) {
        Session::setFlash(
            'errores',
            $errores
        );

        Session::setFlash(
            'datos',
            $datos
        );

        Response::redirect(
            'administrador/consultorio/configurar'
        );

        return;
    }

    /*
    =====================================
       1. REGISTRAR EN LA BASE DE DATOS
    =====================================
    */

    try {
        $instalacion = new InstalacionConsultorioService();
        $estadoPrevio = $instalacion->resolver();

        if ($estadoPrevio['estado'] !== InstalacionConsultorioService::ESTADO_NINGUNO) {
            Session::setFlash(
                'errores',
                [
                    'general' =>
                        'Ya existe un consultorio en esta instalación. No es posible registrar otro.'
                ]
            );
            Response::redirect('administrador');
            return;
        }

        $service = new AdministradorService();

        $resultado =
            $service->registrarConsultorio(
                $datos
            );
    } catch (Throwable $e) {
        $mensaje = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'No fue posible registrar el consultorio. Verifica los datos e intenta nuevamente.';

        if (
            stripos($mensaje, 'Ya existe un consultorio') !== false
        ) {
            Session::setFlash('error', $mensaje);
            Response::redirect('administrador');
            return;
        }

        Session::setFlash(
            'errores',
            [
                'general' => $mensaje
            ]
        );

        Session::setFlash(
            'datos',
            $datos
        );

        Response::redirect(
            'administrador/consultorio/configurar'
        );

        return;
    }

    try {
        (new NotificacionService())
            ->notificarAdministradoresNuevoConsultorio(
                (string) (
                    $resultado['nombreConsultorio']
                    ?? ($datos['nombreConsultorio'] ?? '')
                )
            );
    } catch (Throwable $e) {
        // Alta ya confirmada; la notificación es auxiliar.
    }

    /*
    =====================================
       2. ENVIAR ENLACE DE ACTIVACIÓN
       (después del commit; no hace rollback)
    =====================================
    */

    $invitacion = (new ActivacionCuentaService())
        ->crearInvitacionConsultorioExistente(
            (string) ($resultado['ClvUsu'] ?? ''),
            (string) ($this->usuario['ClvUsu'] ?? ''),
            (string) ($resultado['nombreResponsable'] ?? ''),
            (string) ($resultado['nombreConsultorio'] ?? '')
        );

    $correoEnviado = !empty($invitacion['correoEnviado']);

    $mensaje = $correoEnviado
        ? 'Consultorio registrado correctamente. Se envió el enlace de activación al correo del responsable.'
        : 'El consultorio fue registrado, pero no se pudo enviar el enlace de activación.';

    $this->view(
        'administrador/consultorios/acceso',
        [
            'usuario' => $this->usuario,

            'resultado' => [
                'correo' =>
                    $resultado['correo'] ?? '',
                'correoEnviado' =>
                    $correoEnviado,
                'operacion' => 'registro',
                'clvCons' =>
                    $resultado['ClvCons'] ?? '',
                'mensaje' => $mensaje
            ]
        ],
        'master_admin'
    );
}

public function reenviarActivacionConsultorio(string $id = ''): void
{
    unset($id);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::redirect('administrador/consultorio');
        return;
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        Session::setFlash(
            'error',
            'La solicitud no es válida. Intenta nuevamente.'
        );
        Response::redirect('administrador/consultorio');
        return;
    }

    try {
        $consultorio = $this->exigirConsultorioUnicoDeInstalacion();
        $cuenta = (new AdministradorService())
            ->resolverCuentaPrincipalUnica(
                (string) $consultorio['ClvCons']
            );

        $nombreResponsable = trim(implode(' ', array_filter([
            $cuenta['NombrePer'] ?? '',
            $cuenta['ApPatPer'] ?? '',
            $cuenta['ApMatPer'] ?? ''
        ])));

        $invitacion = (new ActivacionCuentaService())
            ->crearInvitacionConsultorioExistente(
                (string) $cuenta['ClvUsu'],
                (string) ($this->usuario['ClvUsu'] ?? ''),
                $nombreResponsable !== '' ? $nombreResponsable : 'Responsable',
                (string) ($consultorio['NombreCons'] ?? '')
            );

        Session::setFlash(
            !empty($invitacion['ok']) && !empty($invitacion['correoEnviado'])
                ? 'success'
                : 'error',
            (string) ($invitacion['mensaje'] ?? 'No se pudo reenviar la activación.')
        );

        try {
            (new NotificacionService())->notificarAdministradoresSistema(
                'Reenvío de activación',
                'Se solicitó reenviar la activación de la cuenta del consultorio.'
            );
        } catch (Throwable $e) {
            // auxiliar
        }
    } catch (Throwable $e) {
        Session::setFlash('error', $e->getMessage());
    }

    Response::redirect('administrador/consultorio');
}
 /*
=========================================
      VER CONSULTORIO
=========================================
*/

public function verConsultorio(string $id = ''): void
{
    try {
        $consultorio = $this->exigirConsultorioUnicoDeInstalacion();

        if (
            $id !== ''
            && !$this->coincideClaveInstalacion($id, (string) $consultorio['ClvCons'])
        ) {
            Session::setFlash(
                'error',
                'Solo puede administrarse el consultorio de esta instalación.'
            );
            Response::redirect('administrador/consultorio');
            return;
        }

        $cuenta = (new AdministradorService())
            ->resolverCuentaPrincipalUnica(
                (string) $consultorio['ClvCons']
            );
        $consultorio = array_merge($consultorio, $cuenta);

        $estadoPagina = (new PublicacionConsultorioService())
            ->derivarEstadoPagina(
                $consultorio,
                (int) ($consultorio['EstadoUsu'] ?? 0)
            );

        $activacionInfo = $this->obtenerInfoActivacion(
            (string) ($cuenta['ClvUsu'] ?? '')
        );

        $this->view(
            'administrador/consultorios/ver',
            [
                'usuario' => $this->usuario,
                'consultorio' => $consultorio,
                'estadoPaginaPublica' => $estadoPagina,
                'activacionInfo' => $activacionInfo,
                'soportaRecuperacion' => (new ActivacionCuentaService())
                    ->soportaRecuperacionConsultorio(),
                'success' => Session::getFlash('success'),
                'error' => Session::getFlash('error')
            ],
            'master_admin'
        );
    } catch (Throwable $e) {
        Session::setFlash('error', $e->getMessage());
        Response::redirect('administrador');
    }
}

  /*
=========================================
      FORMULARIO DE EDICIÓN
=========================================
*/

/**
 * Legacy sin uso en UI (alcance admin = cuentas).
 * El contenido comercial/operativo se edita en el módulo CONSULTORIO.
 */
public function editarConsultorio(
    string $id
): void {
    unset($id);

    Session::setFlash(
        'error',
        'La edición administrativa del consultorio ya no está disponible. El responsable actualiza su información desde su panel.'
    );

    Response::redirect('administrador/consultorios');
}

/**
 * Legacy sin uso en UI. Bloquea actualizaciones manuales por POST.
 */
public function actualizarConsultorio(
    string $id
): void {
    unset($id);

    Session::setFlash(
        'error',
        'No es posible actualizar el consultorio desde el panel administrativo.'
    );

    Response::redirect('administrador/consultorios');
}
   /*
=========================================
      ACTIVAR CONSULTORIO
=========================================
*/

public function activarConsultorio(string $id = ''): void
{
    $this->cambiarEstadoCuentaPrincipal(true, $id);
}

public function desactivarConsultorio(string $id = ''): void
{
    $this->cambiarEstadoCuentaPrincipal(false, $id);
}

/**
 * Activa/inactiva solo usuario.EstadoUsu de la cuenta principal.
 * No usa ClvUsu/ClvCons del POST.
 */
public function cambiarEstadoCuenta(): void
{
    $activar = strtoupper(trim((string) ($_POST['accion'] ?? ''))) === 'ACTIVAR';
    $this->cambiarEstadoCuentaPrincipal($activar, '');
}

private function cambiarEstadoCuentaPrincipal(
    bool $activar,
    string $idRuta
): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::redirect('administrador/consultorio');
        return;
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        Session::setFlash(
            'error',
            'La solicitud no es válida. Intenta nuevamente.'
        );
        Response::redirect('administrador/consultorio');
        return;
    }

    try {
        $consultorio = $this->exigirConsultorioUnicoDeInstalacion();

        if (
            $idRuta !== ''
            && !$this->coincideClaveInstalacion(
                $idRuta,
                (string) $consultorio['ClvCons']
            )
        ) {
            throw new RuntimeException(
                'Solo puede administrarse el consultorio de esta instalación.'
            );
        }

        $service = new AdministradorService();

        if ($activar) {
            $service->activarCuentaPrincipal(
                (string) $consultorio['ClvCons']
            );
            $mensaje = 'Cuenta principal activada. No se modificó el estatus institucional del consultorio.';
            $tituloNotif = 'Cuenta principal activada';
        } else {
            $service->inactivarCuentaPrincipal(
                (string) $consultorio['ClvCons']
            );
            $mensaje = 'Cuenta principal inactivada. No se modificó el estatus institucional del consultorio.';
            $tituloNotif = 'Cuenta principal inactivada';
        }

        try {
            (new NotificacionService())->notificarAdministradoresSistema(
                $tituloNotif,
                'Se actualizó el acceso de la cuenta principal del consultorio.'
            );
        } catch (Throwable $e) {
            // auxiliar
        }

        Session::setFlash('success', $mensaje);
    } catch (Throwable $e) {
        Session::setFlash('error', $e->getMessage());
    }

    Response::redirect('administrador/consultorio');
}

/*
=========================================
      RESTABLECER ACCESO
=========================================
*/

public function restablecerAcceso(string $id = ''): void
{
    unset($id);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::redirect('administrador/consultorio');
        return;
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        Session::setFlash(
            'error',
            'La solicitud no es válida. Intenta nuevamente.'
        );
        Response::redirect('administrador/consultorio');
        return;
    }

    try {
        $consultorio = $this->exigirConsultorioUnicoDeInstalacion();
        $service = new AdministradorService();
        $resultado = $service->restablecerAcceso(
            (string) $consultorio['ClvCons']
        );

        $invitacion = (new ActivacionCuentaService())
            ->crearRecuperacionConsultorio(
                (string) ($resultado['ClvUsu'] ?? ''),
                (string) ($this->usuario['ClvUsu'] ?? ''),
                (string) ($resultado['nombreResponsable'] ?? 'Responsable'),
                (string) ($consultorio['NombreCons'] ?? '')
            );

        if (empty($invitacion['ok'])) {
            Session::setFlash(
                'error',
                (string) ($invitacion['mensaje'] ?? 'No se pudo restablecer el acceso.')
            );
            Response::redirect('administrador/consultorio');
            return;
        }

        try {
            (new NotificacionService())
                ->notificarAdministradoresAccesoRestablecido(
                    (string) ($consultorio['NombreCons'] ?? '')
                );
        } catch (Throwable $e) {
            // auxiliar
        }

        $this->view(
            'administrador/consultorios/acceso',
            [
                'usuario' => $this->usuario,
                'resultado' => [
                    'correo' => $resultado['correo'],
                    'correoEnviado' => !empty($invitacion['correoEnviado']),
                    'operacion' => 'restablecer',
                    'mensaje' => (string) ($invitacion['mensaje'] ?? '')
                ]
            ],
            'master_admin'
        );
    } catch (Throwable $e) {
        Session::setFlash('error', $e->getMessage());
        Response::redirect('administrador/consultorio');
    }
}

   /*
=========================================
      OBTENER DATOS DEL FORMULARIO
=========================================
*/

private function obtenerDatosFormulario(): array
{
    $telefono = trim((string) ($_POST['TelefonoUsu'] ?? ''));
    $correoAcceso = strtolower(trim((string) ($_POST['CorreoUsu'] ?? '')));

    /*
     * Alta administrativa mínima:
     * teléfono/correo del consultorio se toman del responsable
     * (TelefonoCons NOT NULL; CorreoElectronico nullable pero útil).
     * LimiteCancHoras usa DEFAULT 24 sin campo de UI.
     */
    return [
        'nombreConsultorio' => trim((string) ($_POST['NombreCons'] ?? '')),
        'slogan' => '',
        'descripcion' => '',
        'telefonoConsultorio' => $telefono,
        'correoConsultorio' => $correoAcceso,
        'limiteCancelacion' => '',

        'pais' => trim((string) ($_POST['PaisDir'] ?? 'México')),
        'estado' => trim((string) ($_POST['EstadoDir'] ?? '')),
        'municipio' => trim((string) ($_POST['MunicipioDir'] ?? '')),
        'colonia' => trim((string) ($_POST['ColoniaDir'] ?? '')),
        'calle' => '',
        'codigoPostal' => trim((string) ($_POST['CodPostDir'] ?? '')),
        'numeroExterior' => '',
        'numeroInterior' => '',

        'nombreResponsable' => trim((string) ($_POST['NombrePer'] ?? '')),
        'apellidoPaternoResponsable' => trim((string) ($_POST['ApPatPer'] ?? '')),
        'apellidoMaternoResponsable' => trim((string) ($_POST['ApMatPer'] ?? '')),
        'fechaNacimientoResponsable' => trim((string) ($_POST['FechaNacimiento'] ?? '')),
        'generoResponsable' => trim((string) ($_POST['GeneroPer'] ?? '')),

        'telefonoResponsable' => $telefono,
        'correoResponsable' => $correoAcceso,

        'logotipo' => null,
    ];
}

    /*
=========================================
      VALIDAR FORMULARIO
=========================================
*/
private function fechaValida(string $fecha): bool
{
    $fecha = trim($fecha);

    if ($fecha === '') {
        return false;
    }

    $date = \DateTime::createFromFormat(
        'Y-m-d',
        $fecha
    );

    return $date !== false
        && $date->format('Y-m-d') === $fecha;
}
private function validarDatosConsultorio(
    array $datos,
    ?string $ignorarConsultorio = null,
    ?string $ignorarUsuario = null
): array {
    $errores = [];

    $consultorioModel = new Consultorio();

    /*
    =====================================
          DATOS DEL CONSULTORIO
    =====================================
    */

    if ($datos['nombreConsultorio'] === '') {
        $errores['NombreCons'] =
            'El nombre del consultorio es obligatorio.';
    } elseif (mb_strlen($datos['nombreConsultorio']) > 100) {
        $errores['NombreCons'] =
            'El nombre del consultorio no puede exceder 100 caracteres.';
    } elseif (
        $consultorioModel->existeNombreConsultorio(
            $datos['nombreConsultorio'],
            $ignorarConsultorio
        )
    ) {
        $errores['NombreCons'] =
            'Ya existe un consultorio registrado con ese nombre.';
    }

    /*
    =====================================
      UBICACIÓN MÍNIMA (direccion NOT NULL)
    =====================================
    */

    if ($datos['pais'] === '') {
        $errores['PaisDir'] = 'El país es obligatorio.';
    }

    if ($datos['estado'] === '') {
        $errores['EstadoDir'] = 'El estado es obligatorio.';
    }

    if ($datos['municipio'] === '') {
        $errores['MunicipioDir'] = 'El municipio es obligatorio.';
    }

    if ($datos['colonia'] === '') {
        $errores['ColoniaDir'] = 'La colonia es obligatoria.';
    }

    if ($datos['codigoPostal'] === '') {
        $errores['CodPostDir'] = 'El código postal es obligatorio.';
    } elseif (!preg_match('/^\\d{5}$/', $datos['codigoPostal'])) {
        $errores['CodPostDir'] =
            'El código postal debe tener 5 dígitos.';
    }

    /*
    =====================================
                RESPONSABLE / CUENTA
    =====================================
    */

    if ($datos['nombreResponsable'] === '') {
        $errores['NombrePer'] =
            'El nombre del responsable es obligatorio.';
    }

    if ($datos['apellidoPaternoResponsable'] === '') {
        $errores['ApPatPer'] =
            'El apellido paterno del responsable es obligatorio.';
    }

    if ($datos['apellidoMaternoResponsable'] === '') {
        $errores['ApMatPer'] =
            'El apellido materno del responsable es obligatorio.';
    }

    if ($datos['fechaNacimientoResponsable'] === '') {
        $errores['FechaNacimiento'] =
            'La fecha de nacimiento es obligatoria.';
    } elseif (!$this->fechaValida($datos['fechaNacimientoResponsable'])) {
        $errores['FechaNacimiento'] =
            'La fecha de nacimiento no es válida.';
    } elseif ($datos['fechaNacimientoResponsable'] > date('Y-m-d')) {
        $errores['FechaNacimiento'] =
            'La fecha de nacimiento no puede ser futura.';
    }

    if (
        !in_array(
            $datos['generoResponsable'],
            ['Masculino', 'Femenino', 'Otro'],
            true
        )
    ) {
        $errores['GeneroPer'] = 'Seleccione un género válido.';
    }

    if ($datos['correoResponsable'] === '') {
        $errores['CorreoUsu'] =
            'El correo de acceso es obligatorio.';
    } elseif (!filter_var($datos['correoResponsable'], FILTER_VALIDATE_EMAIL)) {
        $errores['CorreoUsu'] =
            'El correo de acceso no es válido.';
    } elseif (
        $consultorioModel->existeCorreoUsuario(
            $datos['correoResponsable'],
            $ignorarUsuario
        )
    ) {
        $errores['CorreoUsu'] =
            'El correo ya está registrado en otra cuenta.';
    }

    if ($datos['telefonoResponsable'] === '') {
        $errores['TelefonoUsu'] =
            'El teléfono es obligatorio.';
    } elseif (!preg_match('/^\\d{10}$/', $datos['telefonoResponsable'])) {
        $errores['TelefonoUsu'] =
            'El teléfono debe tener 10 dígitos.';
    }

    return $errores;
}

    /**
     * @return array{
     *   estado: string,
     *   fecha_ultimo_envio: ?string,
     *   requiere_activacion: bool
     * }|null
     */
    private function obtenerInfoActivacion(string $clvUsu): ?array
    {
        if ($clvUsu === '') {
            return null;
        }

        $activacion = (new ActivacionCuenta())->obtenerUltimaPorUsuario(
            $clvUsu,
            ActivacionCuentaService::TIPO_CONSULTORIO
        );

        if (!$activacion) {
            return [
                'estado' => 'SIN_REGISTRO',
                'fecha_ultimo_envio' => null,
                'requiere_activacion' => true
            ];
        }

        $fecha = (string) (
            $activacion['FechaUltimoEnvio']
            ?? $activacion['FechaCreacion']
            ?? ''
        );

        return [
            'estado' => (string) ($activacion['Estado'] ?? ''),
            'fecha_ultimo_envio' => $fecha !== '' ? $fecha : null,
            'requiere_activacion' =>
                (string) ($activacion['Estado'] ?? '') === 'PENDIENTE'
                || (string) ($activacion['Estado'] ?? '') === 'EXPIRADA'
                || (string) ($activacion['Estado'] ?? '') === 'REVOCADA'
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function exigirConsultorioUnicoDeInstalacion(): array
    {
        $estado = (new InstalacionConsultorioService())->resolver();

        if ($estado['estado'] === InstalacionConsultorioService::ESTADO_NINGUNO) {
            throw new RuntimeException(
                'Esta instalación todavía no tiene un consultorio configurado.'
            );
        }

        if ($estado['estado'] === InstalacionConsultorioService::ESTADO_MULTIPLE) {
            error_log(
                'ADMIN_INSTALACION: operación bloqueada por múltiples consultorios'
            );
            throw new RuntimeException(
                'Se detectó más de un consultorio. No es posible operar hasta corregirlo.'
            );
        }

        $consultorio = $estado['consultorio'] ?? null;

        if (!is_array($consultorio) || empty($consultorio['ClvCons'])) {
            throw new RuntimeException(
                'No fue posible resolver el consultorio de la instalación.'
            );
        }

        return $consultorio;
    }

    private function coincideClaveInstalacion(
        string $recibida,
        string $esperada
    ): bool {
        return strtoupper(trim($recibida)) === strtoupper(trim($esperada));
    }
}