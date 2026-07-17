<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\Cita;
use App\Models\Consultorio;
use App\Models\Psicologo;
class PsicologoController extends Controller
{
    private array $usuario;

    public function __construct()
    {
        if (!Session::has('usuario')) {
            Response::redirect('login');
        }

        $usuario = Session::get('usuario');

        if (
            !isset($usuario['RolUsu']) ||
            $usuario['RolUsu'] !== 'PSICOLOGO'
        ) {
            Response::redirect('login');
        }

        $this->usuario = $usuario;
    }

public function dashboard(): void
{
    $clvUsu = $this->usuario['ClvUsu'] ?? '';

    if ($clvUsu === '') {
        Session::destroy();
        Response::redirect('login');
    }

    $psicologoModel = new Psicologo();

    $psicologo = $psicologoModel->obtenerPorUsuario(
        $clvUsu
    );

    if (!$psicologo) {
        http_response_code(403);

        echo 'La cuenta no está vinculada con un especialista.';

        return;
    }

    if ($psicologo['EstatusPsi'] !== 'ACTIVO') {
        Session::destroy();
        Response::redirect('login');
    }

    $consultorioModel = new Consultorio();

    /*
     * Lo ideal es que obtenerInformacion permita
     * buscar por ClvCons.
     */
    $consultorio =
        $consultorioModel->obtenerPorClave(
            $psicologo['ClvCons']
        );

    if (!$consultorio) {
        http_response_code(500);

        echo 'No se encontró la información del consultorio.';

        return;
    }

    $clvPsi = $psicologo['ClvPsi'];

    $citaModel = new Cita();

    $this->view(
    'psicologo/dashboard',
    [
        'titulo' => 'Panel del especialista',

        'usuario' => $this->usuario,

        'psicologo' => $psicologo,

        'consultorio' => $consultorio,

        'citasHoy' =>
            $citaModel->contarCitasHoy(
                $clvPsi
            ),

        'totalPacientes' =>
            $citaModel->contarPacientesActivos(
                $clvPsi
            ),

        'citasSemana' =>
            $citaModel->contarCitasSemana(
                $clvPsi
            ),

        'proximasCitas' =>
            $citaModel->obtenerProximasCitas(
                $clvPsi
            )
    ],
    'psicologo'
);
}
public function actualizarPerfil(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::redirect('psicologo/perfil');
    }

    $clvUsu = $this->usuario['ClvUsu'] ?? '';

    if ($clvUsu === '') {
        Session::set(
            'error',
            'No se pudo identificar al especialista.'
        );

        Response::redirect('psicologo/perfil');
    }

    $datos = [
        'NombrePer' =>
            trim($_POST['NombrePer'] ?? ''),

        'ApPatPer' =>
            trim($_POST['ApPatPer'] ?? ''),

        'ApMatPer' =>
            trim($_POST['ApMatPer'] ?? ''),

        'FechaNacimiento' =>
            trim($_POST['FechaNacimiento'] ?? ''),

        'GeneroPer' =>
            trim($_POST['GeneroPer'] ?? ''),

        'TelefonoUsu' =>
            trim($_POST['TelefonoUsu'] ?? '')
    ];

    $errores = [];

    if ($datos['NombrePer'] === '') {
        $errores[] = 'El nombre es obligatorio.';
    }

    if ($datos['ApPatPer'] === '') {
        $errores[] = 'El apellido paterno es obligatorio.';
    }

    if ($datos['ApMatPer'] === '') {
        $errores[] = 'El apellido materno es obligatorio.';
    }

    if ($datos['FechaNacimiento'] === '') {
        $errores[] =
            'La fecha de nacimiento es obligatoria.';
    }

    $generosPermitidos = [
        'Masculino',
        'Femenino',
        'Otro'
    ];

    if (
        !in_array(
            $datos['GeneroPer'],
            $generosPermitidos,
            true
        )
    ) {
        $errores[] =
            'Selecciona un género válido.';
    }

    if (
        !preg_match(
            '/^[0-9]{10}$/',
            $datos['TelefonoUsu']
        )
    ) {
        $errores[] =
            'El teléfono debe contener 10 dígitos.';
    }

    if (!empty($errores)) {
        Session::set(
            'error',
            implode(' ', $errores)
        );

        Response::redirect('psicologo/perfil');
    }

    try {
        $psicologoModel = new Psicologo();

        $psicologoModel->actualizarPerfil(
            $clvUsu,
            $datos,
            $_FILES['FotoPerfilPer'] ?? null
        );

        Session::set(
            'success',
            'El perfil se actualizó correctamente.'
        );
    } catch (\Throwable $e) {
        Session::set(
            'error',
            $e->getMessage()
        );
    }

    Response::redirect('psicologo/perfil');
}

public function servicios(): void
{
    $contexto =
        $this->obtenerContextoPsicologo();

    $modelo =
        new Psicologo();

    $servicios =
        $modelo->listarServicios(
            $contexto['psicologo']['ClvPsi']
        );

    $this->view(
        'psicologo/servicios/index',
        [
            'titulo' => 'Mis servicios',
            'usuario' => $this->usuario,
            'consultorio' =>
                $contexto['consultorio'],
            'psicologo' =>
                $contexto['psicologo'],
            'servicios' =>
                $servicios
        ],
        'psicologo'
    );
}
  public function pacientes(): void
{
    $this->view(
        'psicologo/paciente',
        [
            'titulo' => 'Mis pacientes',
            'usuario' => $this->usuario
        ],
        'psicologo'
    );
}
  public function calendario(): void
{
    $this->view(
        'psicologo/calendario',
        [
            'titulo' => 'Mi agenda',
            'usuario' => $this->usuario
        ],
        'psicologo'
    );
}
private function obtenerContextoPsicologo(): array
{
    $clvUsu = $this->usuario['ClvUsu'] ?? '';

    if ($clvUsu === '') {
        Session::destroy();
        Response::redirect('login');
    }

    $psicologoModel = new Psicologo();

    $psicologo = $psicologoModel->obtenerPorUsuario(
        $clvUsu
    );

    if (!$psicologo) {
        http_response_code(403);

        echo 'La cuenta no está vinculada con un especialista.';

        exit;
    }

    if ($psicologo['EstatusPsi'] !== 'ACTIVO') {
        Session::destroy();
        Response::redirect('login');
    }

    $consultorioModel = new Consultorio();

    $consultorio = $consultorioModel->obtenerPorClave(
        $psicologo['ClvCons']
    );

    if (!$consultorio) {
        http_response_code(500);

        echo 'No se encontró la información del consultorio.';

        exit;
    }

    return [
        'psicologo' => $psicologo,
        'consultorio' => $consultorio
    ];
}
public function perfil(): void
{
    $contexto = $this->obtenerContextoPsicologo();

    $psicologoModel = new Psicologo();

    $perfil = $psicologoModel->obtenerPerfilPorUsuario(
        $this->usuario['ClvUsu']
    );

    if (!$perfil) {
        http_response_code(404);
        echo 'No se encontró el perfil del especialista.';
        return;
    }

    $this->view(
        'psicologo/perfil',
        [
            'titulo' => 'Mi perfil profesional',
            'usuario' => $this->usuario,
            'psicologo' => $contexto['psicologo'],
            'consultorio' => $contexto['consultorio'],
            'perfil' => $perfil,
            'errores' => []
        ],
        'psicologo'
    );
}


   public function expediente(): void
{
    $this->view(
        'psicologo/expediente',
        [
            'titulo' => 'Expedientes',
            'usuario' => $this->usuario
        ],
        'psicologo'
    );
}

  public function configuracion(): void
{
    $this->view(
        'psicologo/configuracion',
        [
            'titulo' => 'Configuración',
            'usuario' => $this->usuario
        ],
        'psicologo'
    );
}
}