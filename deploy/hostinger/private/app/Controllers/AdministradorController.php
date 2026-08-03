<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Helper;
use App\Models\ActivacionCuenta;
use App\Models\Consultorio;
use App\Models\Notificacion;
use App\Services\ActivacionCuentaService;
use App\Services\AdministradorService;
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
        if (!Session::has('usuario')) {
            Response::redirect('login');
            return;
        }

        $usuario = Session::get('usuario');

        if (
            !isset($usuario['RolUsu']) ||
            $usuario['RolUsu'] !== 'ADMINISTRADOR'
        ) {
            Response::redirect('login');
            return;
        }

        if ((int) ($usuario['EstadoUsu'] ?? 0) !== 1) {
            Session::destroy();
            Response::redirect('login');
            return;
        }

        $this->usuario = $usuario;
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
    $ultimaIncidenciaAcceso = null;
    $estadoPaginaPublica = null;

    if ($estado['estado'] === InstalacionConsultorioService::ESTADO_MULTIPLE) {
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

        $clvUsuCons = trim((string) ($consultorio['ClvUsu'] ?? ''));
        if ($clvUsuCons !== '') {
            $ultimaIncidenciaAcceso = $this->obtenerUltimaIncidenciaAcceso($clvUsuCons);
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
            'ultimaIncidenciaAcceso' => $ultimaIncidenciaAcceso,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error')
        ],
        'master_admin'
    );
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
        Response::redirect('administrador/consultorios/crear');
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

    $clvCons = (string) ($estado['consultorio']['ClvCons'] ?? '');
    Response::redirect(
        'administrador/consultorios/ver/' . rawurlencode($clvCons)
    );
}

/**
 * Legacy sin uso en UI (alcance admin = cuentas).
 * La vista previa operativa permanece en el panel CONSULTORIO.
 */
public function vistaPreviaConsultorio(string $id): void
{
    unset($id);

    Session::setFlash(
        'error',
        'La vista previa administrativa ya no está disponible. El consultorio administra su página desde su propio panel.'
    );

    Response::redirect('administrador/consultorios');
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
        $clvCons = (string) ($estado['consultorio']['ClvCons'] ?? '');
        Session::setFlash(
            'error',
            'Ya existe el consultorio de esta instalación.'
        );
        Response::redirect(
            'administrador/consultorios/ver/' . rawurlencode($clvCons)
        );
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
            'administrador/consultorios'
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
            'administrador/consultorios/crear'
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
            'administrador/consultorios/crear'
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
            Response::redirect('administrador/consultorios');
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
            Response::redirect('administrador/consultorios');
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
            'administrador/consultorios/crear'
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

public function reenviarActivacionConsultorio(string $id): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::redirect('administrador/consultorios');
        return;
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        Session::setFlash(
            'error',
            'La solicitud no es válida. Intenta nuevamente.'
        );
        Response::redirect('administrador/consultorios');
        return;
    }

    $consultorioModel = new Consultorio();
    $consultorio = $consultorioModel->obtenerPorClave($id);

    if (!$consultorio || empty($consultorio['ClvUsu'])) {
        Session::setFlash(
            'error',
            'No se encontró el consultorio o su responsable.'
        );
        Response::redirect('administrador/consultorios');
        return;
    }

    $nombreResponsable = trim(implode(' ', array_filter([
        $consultorio['NombrePer'] ?? '',
        $consultorio['ApPatPer'] ?? '',
        $consultorio['ApMatPer'] ?? ''
    ])));

    $invitacion = (new ActivacionCuentaService())
        ->crearInvitacionConsultorioExistente(
            (string) $consultorio['ClvUsu'],
            (string) ($this->usuario['ClvUsu'] ?? ''),
            $nombreResponsable !== '' ? $nombreResponsable : 'Responsable',
            (string) ($consultorio['NombreCons'] ?? '')
        );

    if (!empty($invitacion['correoEnviado'])) {
        Session::setFlash(
            'success',
            'Se reenvió el enlace de activación correctamente.'
        );
    } else {
        Session::setFlash(
            'error',
            'El consultorio permanece pendiente, pero no se pudo enviar el enlace de activación.'
        );
    }

    Response::redirect('administrador/consultorios');
}
 /*
=========================================
      VER CONSULTORIO
=========================================
*/

public function verConsultorio(
    string $id
): void {
    $consultorioModel = new Consultorio();

    $consultorio =
        $consultorioModel->obtenerPorClave($id);

    if (!$consultorio) {
        Session::setFlash(
            'error',
            'El consultorio solicitado no existe.'
        );

        Response::redirect(
            'administrador/consultorios'
        );

        return;
    }

    $estadoPagina = (new PublicacionConsultorioService())
        ->derivarEstadoPagina(
            $consultorio,
            (int) ($consultorio['EstadoUsu'] ?? 0)
        );

    $this->view(
        'administrador/consultorios/ver',
        [
            'usuario' => $this->usuario,
            'consultorio' => $consultorio,
            'estadoPaginaPublica' => $estadoPagina,
            'success' =>
                Session::getFlash('success'),
            'error' =>
                Session::getFlash('error')
        ],
        'master_admin'
    );
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

public function activarConsultorio(
    string $id
): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::redirect(
            'administrador/consultorios'
        );

        return;
    }

    try {
        $consultorioModel = new Consultorio();

        $consultorio =
            $consultorioModel->obtenerPorClave($id);

        if (!$consultorio) {
            throw new \RuntimeException(
                'El consultorio solicitado no existe.'
            );
        }

        if (
            ($consultorio['EstatusCons'] ?? '')
            === 'ACTIVO'
        ) {
            throw new \RuntimeException(
                'El consultorio ya se encuentra activo.'
            );
        }

        $service = new AdministradorService();

        $service->activarConsultorio($id);

        try {
            (new NotificacionService())
                ->notificarAdministradoresCambioEstatusConsultorio(
                    (string) ($consultorio['NombreCons'] ?? ''),
                    'ACTIVO'
                );
        } catch (Throwable $e) {
            // Activación ya confirmada.
        }

        Session::setFlash(
            'success',
            'Consultorio activado correctamente.'
        );
    } catch (Throwable $e) {
        Session::setFlash(
            'error',
            $e->getMessage()
        );
    }

    Response::redirect(
        'administrador/consultorios'
    );
}
/*
=========================================
      DESACTIVAR CONSULTORIO
=========================================
*/

public function desactivarConsultorio(
    string $id
): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::redirect(
            'administrador/consultorios'
        );

        return;
    }

    try {
        $consultorioModel = new Consultorio();

        $consultorio =
            $consultorioModel->obtenerPorClave($id);

        if (!$consultorio) {
            throw new \RuntimeException(
                'El consultorio solicitado no existe.'
            );
        }

        if (
            ($consultorio['EstatusCons'] ?? '')
            === 'INACTIVO'
        ) {
            throw new \RuntimeException(
                'El consultorio ya se encuentra inactivo.'
            );
        }

        $service = new AdministradorService();

        $service->desactivarConsultorio($id);

        try {
            (new NotificacionService())
                ->notificarAdministradoresCambioEstatusConsultorio(
                    (string) ($consultorio['NombreCons'] ?? ''),
                    'INACTIVO'
                );
        } catch (Throwable $e) {
            // Desactivación ya confirmada.
        }

        Session::setFlash(
            'success',
            'Consultorio desactivado correctamente.'
        );
    } catch (Throwable $e) {
        Session::setFlash(
            'error',
            $e->getMessage()
        );
    }

    Response::redirect(
        'administrador/consultorios'
    );
}

/*
=========================================
      RESTABLECER ACCESO
=========================================
*/

public function restablecerAcceso(
    string $id
): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::redirect(
            'administrador/consultorios'
        );

        return;
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        Session::setFlash(
            'error',
            'La solicitud no es válida. Intenta nuevamente.'
        );
        Response::redirect('administrador/consultorios');
        return;
    }

    $consultorioModel = new Consultorio();

    $consultorio =
        $consultorioModel->obtenerPorClave($id);

    if (!$consultorio) {
        Session::setFlash(
            'error',
            'El consultorio solicitado no existe.'
        );

        Response::redirect(
            'administrador/consultorios'
        );

        return;
    }

    try {
        $service = new AdministradorService();

        $resultado =
            $service->restablecerAcceso($id);

        $invitacion = (new ActivacionCuentaService())
            ->crearInvitacionConsultorioExistente(
                (string) ($resultado['ClvUsu'] ?? ''),
                (string) ($this->usuario['ClvUsu'] ?? ''),
                (string) ($resultado['nombreResponsable'] ?? 'Responsable'),
                (string) ($consultorio['NombreCons'] ?? '')
            );

        try {
            (new NotificacionService())
                ->notificarAdministradoresAccesoRestablecido(
                    (string) ($consultorio['NombreCons'] ?? '')
                );
        } catch (Throwable $e) {
            // Restablecimiento ya confirmado.
        }

        $this->view(
            'administrador/consultorios/acceso',
            [
                'usuario' => $this->usuario,

                'resultado' => [
                    'correo' =>
                        $resultado['correo'],
                    'correoEnviado' =>
                        !empty($invitacion['correoEnviado']),
                    'operacion' => 'restablecer',
                    'mensaje' =>
                        'Acceso restablecido. '
                        . ($invitacion['mensaje'] ?? '')
                ]
            ],
            'master_admin'
        );
    } catch (Throwable $e) {
        Session::setFlash(
            'error',
            $e->getMessage()
        );

        Response::redirect(
            'administrador/consultorios/ver/'
            . rawurlencode($id)
        );

        return;
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
     * @return array{descripcion: string, fecha: ?string, estado: string}|null
     */
    private function obtenerUltimaIncidenciaAcceso(string $clvUsuCons): ?array
    {
        $activacion = (new ActivacionCuenta())->obtenerUltimaPorUsuario(
            $clvUsuCons,
            ActivacionCuentaService::TIPO_CONSULTORIO
        );

        if (!$activacion) {
            return null;
        }

        $fecha = (string) (
            $activacion['FechaUltimoEnvio']
            ?? $activacion['FechaCreacion']
            ?? ''
        );

        return [
            'descripcion' => 'Activación / enlace de acceso',
            'fecha' => $fecha !== '' ? $fecha : null,
            'estado' => (string) ($activacion['Estado'] ?? '')
        ];
    }
}