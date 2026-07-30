<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Helper;
use App\Models\Consultorio;
use App\Services\AdministradorService;
use App\Services\MailService;
use Throwable;

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

        $this->usuario = $usuario;
    }

    /*
    =========================================
                DASHBOARD
    =========================================
    */

public function dashboard(): void
{
    $consultorioModel = new Consultorio();

    $this->view(
        'administrador/dashboard',
        [
            'usuario' => $this->usuario,

            'totalConsultorios' =>
                $consultorioModel->contarTodos(),

            'consultoriosActivos' =>
                $consultorioModel->contarPorEstado(
                    'ACTIVO'
                ),

            'consultoriosInactivos' =>
                $consultorioModel->contarPorEstado(
                    'INACTIVO'
                ),

            'consultoriosRecientes' =>
                $consultorioModel->obtenerRecientes(5),

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
          LISTAR CONSULTORIOS
    =========================================
    */

public function listarConsultorios(): void
{
    $consultorioModel = new Consultorio();

    $consultorios = $consultorioModel->obtenerTodos();

    $this->view(
        'administrador/consultorios/index',
        [
            'usuario' => $this->usuario,
            'consultorios' => $consultorios,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error')
        ],
        'master_admin'
    );
}
    /*
=========================================
      FORMULARIO DE REGISTRO
=========================================
*/

public function crearConsultorio(): void
{
    $this->view(
        'administrador/consultorios/form',
        [
            'usuario' => $this->usuario,

            'consultorio' => [],

            'datos' =>
                Session::getFlash('datos') ?? [],

            'errores' =>
                Session::getFlash('errores') ?? []
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
        $service = new AdministradorService();

        $resultado =
            $service->registrarConsultorio(
                $datos
            );
    } catch (Throwable $e) {
        Session::setFlash(
            'errores',
            [
                'general' =>
                    'No fue posible registrar el consultorio: '
                    . $e->getMessage()
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

    /*
    =====================================
       2. ENVIAR EL ACCESO INICIAL
    =====================================

    El correo se envía después de confirmar la
    transacción. Si SMTP falla, el consultorio
    permanece registrado y no se duplica al
    intentar registrarlo otra vez.
    */

    $correoEnviado = false;
    $mensajeCorreo = '';

    try {
        $mailService = new MailService();

        $mailService->enviarAccesoConsultorio(
            $resultado['correo'],
            $resultado['nombreResponsable'],
            $resultado['nombreConsultorio'],
            $resultado['contrasenaTemporal'],
            Helper::baseUrl('login')
        );

        $correoEnviado = true;

        $mensajeCorreo =
            'El acceso inicial fue enviado al correo del responsable.';
    } catch (Throwable $e) {
        error_log(
            'No se pudo enviar el acceso inicial del consultorio '
            . ($resultado['ClvCons'] ?? '')
            . ': '
            . $e->getMessage()
        );

        $mensajeCorreo =
            'El consultorio fue registrado, pero no fue posible '
            . 'enviar el correo de acceso. Conserva la contraseña '
            . 'temporal mostrada para entregarla al responsable.';
    }

    /*
    =====================================
          3. MOSTRAR EL RESULTADO
    =====================================
    */

    $this->view(
        'administrador/consultorios/acceso',
        [
            'usuario' => $this->usuario,

            'resultado' => [
                'correo' =>
                    $resultado['correo'] ?? '',

                'contrasenaTemporal' =>
                    $resultado[
                        'contrasenaTemporal'
                    ] ?? '',

                'correoEnviado' =>
                    $correoEnviado,

                'mensaje' =>
                    'Consultorio registrado correctamente. '
                    . $mensajeCorreo
            ]
        ],
        'master_admin'
    );
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

    $this->view(
        'administrador/consultorios/ver',
        [
            'usuario' => $this->usuario,
            'consultorio' => $consultorio,
            'success' =>
                Session::getFlash('success'),
            'error' =>
                Session::getFlash('error'),
            'contrasenaTemporal' =>
                Session::getFlash(
                    'contrasenaTemporal'
                )
        ],
        'master_admin'
    );
}

  /*
=========================================
      FORMULARIO DE EDICIÓN
=========================================
*/

public function editarConsultorio(
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

    $this->view(
        'administrador/consultorios/form',
        [
            'usuario' => $this->usuario,
            'consultorio' => $consultorio,
            'datos' =>
                Session::getFlash('datos') ?? [],
            'errores' =>
                Session::getFlash('errores') ?? []
        ],
        'master_admin'
    );
}
   /*
=========================================
      ACTUALIZAR CONSULTORIO
=========================================
*/

public function actualizarConsultorio(
    string $id
): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::redirect(
            'administrador/consultorios'
        );

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

    $datos = $this->obtenerDatosFormulario();

    $errores = $this->validarDatosConsultorio(
        $datos,
        $id,
        $consultorio['ClvUsu'] ?? null
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
            'administrador/consultorios/editar/'
            . rawurlencode($id)
        );

        return;
    }

    try {
        $service = new AdministradorService();

        $service->actualizarConsultorio(
            $id,
            $datos
        );

        Session::setFlash(
            'success',
            'Consultorio actualizado correctamente.'
        );

        Response::redirect(
            'administrador/consultorios'
        );

        return;
    } catch (Throwable $e) {
        Session::setFlash(
            'errores',
            [
                'general' =>
                    'No fue posible actualizar el consultorio: '
                    . $e->getMessage()
            ]
        );

        Session::setFlash(
            'datos',
            $datos
        );

        Response::redirect(
            'administrador/consultorios/editar/'
            . rawurlencode($id)
        );

        return;
    }
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

        $this->view(
            'administrador/consultorios/acceso',
            [
                'usuario' => $this->usuario,

                'resultado' => [
                    'correo' =>
                        $resultado['correo'],

                    'contrasenaTemporal' =>
                        $resultado[
                            'contrasenaTemporal'
                        ],

                    'mensaje' =>
                        'Acceso restablecido correctamente.'
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
    return [
        /*
        =====================================
              DATOS DEL CONSULTORIO
        =====================================
        */

        'nombreConsultorio' => trim(
            $_POST['NombreCons'] ?? ''
        ),

        'slogan' => trim(
            $_POST['Slogan'] ?? ''
        ),

        'descripcion' => trim(
            $_POST['Descripcion'] ?? ''
        ),

        'telefonoConsultorio' => trim(
            $_POST['TelefonoCons'] ?? ''
        ),

        'correoConsultorio' => strtolower(
            trim(
                $_POST['CorreoElectronico'] ?? ''
            )
        ),

        'limiteCancelacion' => trim(
            $_POST['LimiteCancHoras'] ?? ''
        ),

        /*
        =====================================
                    DIRECCIÓN
        =====================================
        */

        'pais' => trim(
            $_POST['PaisDir'] ?? ''
        ),

        'estado' => trim(
            $_POST['EstadoDir'] ?? ''
        ),

        'municipio' => trim(
            $_POST['MunicipioDir'] ?? ''
        ),

        'colonia' => trim(
            $_POST['ColoniaDir'] ?? ''
        ),

        'calle' => trim(
            $_POST['CalleDir'] ?? ''
        ),

        'codigoPostal' => trim(
            $_POST['CodPostDir'] ?? ''
        ),

        'numeroExterior' => trim(
            $_POST['NumExtDir'] ?? ''
        ),

        'numeroInterior' => trim(
            $_POST['NumIntDir'] ?? ''
        ),

        /*
        =====================================
            RESPONSABLE DEL CONSULTORIO
        =====================================
        */

        'nombreResponsable' => trim(
            $_POST['NombrePer'] ?? ''
        ),

        'apellidoPaternoResponsable' => trim(
            $_POST['ApPatPer'] ?? ''
        ),

        'apellidoMaternoResponsable' => trim(
            $_POST['ApMatPer'] ?? ''
        ),

        'fechaNacimientoResponsable' => trim(
            $_POST['FechaNacimiento'] ?? ''
        ),

      'generoResponsable' => trim(
    $_POST['GeneroPer'] ?? ''
),

        /*
        =====================================
                 CUENTA DE ACCESO
        =====================================
        */

        'telefonoResponsable' => trim(
            $_POST['TelefonoUsu'] ?? ''
        ),

        'correoResponsable' => strtolower(
            trim(
                $_POST['CorreoUsu'] ?? ''
            )
        ),

        /*
        =====================================
                    ARCHIVOS
        =====================================
        */

        'logotipo' => null
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
    } elseif (
        mb_strlen($datos['nombreConsultorio']) > 100
    ) {
        $errores['NombreCons'] =
            'El nombre del consultorio no puede exceder 100 caracteres.';
    }
    elseif (
        $consultorioModel->existeNombreConsultorio(
            $datos['nombreConsultorio'],
            $ignorarConsultorio
        )
    ) {
        $errores['NombreCons'] =
            'Ya existe un consultorio registrado con ese nombre.';
    }

    if ($datos['slogan'] !== '' && mb_strlen($datos['slogan']) > 150) {
        $errores['Slogan'] =
            'El eslogan no puede exceder 150 caracteres.';
    }

    if ($datos['descripcion'] !== '' && mb_strlen($datos['descripcion']) > 1000) {
        $errores['Descripcion'] =
            'La descripción no puede exceder 1000 caracteres.';
    }

    if ($datos['correoConsultorio'] === '') {
        $errores['CorreoElectronico'] =
            'El correo del consultorio es obligatorio.';
    } elseif (
        !filter_var(
            $datos['correoConsultorio'],
            FILTER_VALIDATE_EMAIL
        )
    ) {
        $errores['CorreoElectronico'] =
            'El correo del consultorio no es válido.';
    } elseif (
        $consultorioModel->existeCorreoConsultorio(
            $datos['correoConsultorio'],
            $ignorarConsultorio
        )
    ) {
        $errores['CorreoElectronico'] =
            'El correo ya está registrado en otro consultorio.';
    }

    if ($datos['telefonoConsultorio'] === '') {
        $errores['TelefonoCons'] =
            'El teléfono del consultorio es obligatorio.';
    } elseif (
        !preg_match(
            '/^\\d{10}$/',
            $datos['telefonoConsultorio']
        )
    ) {
        $errores['TelefonoCons'] =
            'El teléfono del consultorio debe tener 10 dígitos.';
    }

    if (
        $datos['limiteCancelacion'] !== '' &&
        (
            !ctype_digit($datos['limiteCancelacion']) ||
            (int) $datos['limiteCancelacion'] < 0 ||
            (int) $datos['limiteCancelacion'] > 168
        )
    ) {
        $errores['LimiteCancHoras'] =
            'El límite de cancelación debe estar entre 0 y 168 horas.';
    }

    /*
    =====================================
                DIRECCIÓN
    =====================================
    */

    if ($datos['pais'] === '') {
        $errores['PaisDir'] =
            'El país es obligatorio.';
    }

    if ($datos['estado'] === '') {
        $errores['EstadoDir'] =
            'El estado es obligatorio.';
    }

    if ($datos['municipio'] === '') {
        $errores['MunicipioDir'] =
            'El municipio es obligatorio.';
    }

    if ($datos['colonia'] === '') {
        $errores['ColoniaDir'] =
            'La colonia es obligatoria.';
    }

    if ($datos['codigoPostal'] === '') {
        $errores['CodPostDir'] =
            'El código postal es obligatorio.';
    } elseif (
        !preg_match(
            '/^\\d{5}$/',
            $datos['codigoPostal']
        )
    ) {
        $errores['CodPostDir'] =
            'El código postal debe tener 5 dígitos.';
    }

    /*
    =====================================
        RESPONSABLE DEL CONSULTORIO
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

    if ($datos['fechaNacimientoResponsable'] === '') {
        $errores['FechaNacimiento'] =
            'La fecha de nacimiento es obligatoria.';
    } elseif (
        !$this->fechaValida(
            $datos['fechaNacimientoResponsable']
        )
    ) {
        $errores['FechaNacimiento'] =
            'La fecha de nacimiento no es válida.';
    } elseif (
        $datos['fechaNacimientoResponsable'] > date('Y-m-d')
    ) {
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
        $errores['GeneroPer'] =
            'Seleccione un género válido.';
    }

    /*
    =====================================
             CUENTA DEL RESPONSABLE
    =====================================
    */

    if ($datos['correoResponsable'] === '') {
        $errores['CorreoUsu'] =
            'El correo del responsable es obligatorio.';
    } elseif (
        !filter_var(
            $datos['correoResponsable'],
            FILTER_VALIDATE_EMAIL
        )
    ) {
        $errores['CorreoUsu'] =
            'El correo del responsable no es válido.';
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
            'El teléfono del responsable es obligatorio.';
    } elseif (
        !preg_match(
            '/^\\d{10}$/',
            $datos['telefonoResponsable']
        )
    ) {
        $errores['TelefonoUsu'] =
            'El teléfono del responsable debe tener 10 dígitos.';
    }

    return $errores;
}
}