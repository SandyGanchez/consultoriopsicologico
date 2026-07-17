<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\ConsultorioUsuario;
use App\Models\Cita;
use App\Models\Psicologo;
use App\Helpers\Helper;
use App\Services\MailService;

class ConsultorioController extends Controller
{
    private array $usuario;
    private array $consultorio;

    public function __construct()
    {
        if (!Session::has('usuario')) {
            Response::redirect('login');
        }

        $usuario = Session::get('usuario');

        if (
            !isset($usuario['RolUsu']) ||
            $usuario['RolUsu'] !== 'CONSULTORIO'
        ) {
            Response::redirect('login');
        }

        $consultorioUsuarioModel = new ConsultorioUsuario();

        $consultorio = $consultorioUsuarioModel->buscarPorUsuario(
            $usuario['ClvUsu']
        );

        if (!$consultorio) {
            Session::set(
                'error',
                'La cuenta no está asociada a un consultorio activo.'
            );

            Session::destroy();
            Response::redirect('login');
        }

        $this->usuario = $usuario;
        $this->consultorio = $consultorio;
    }

    public function dashboard(): void
    {
        $this->view(
            'consultorio/dashboard',
            [
                'titulo' => 'Panel del consultorio',
                'usuario' => $this->usuario,
                'consultorio' => $this->consultorio,

                // Valores temporales hasta conectar citas
                'citasHoy' => 0,
                'totalPacientes' => 0,
                'citasSemana' => 0,
                'proximasCitas' => []
            ],
            'consultorio'
        );
    }
    public function obtenerPorConsultorio(
    string $clvCons,
    ?string $clvPsi = null,
    ?string $estado = null
): array {
    $sql = "SELECT
                c.ClvCita,
                c.FechaCita,
                c.HraInicioCita,
                c.HraFinCita,
                c.EstadoCita,
                c.NotasCita,
                c.MotivoCancelacion,
                c.FechaCancelacion,
                c.ClvPac,
                c.ClvPsi,
                c.ClvCons,
                c.ClvServ,

                CONCAT(
                    perPac.NombrePer,
                    ' ',
                    perPac.ApPatPer,
                    ' ',
                    COALESCE(perPac.ApMatPer, '')
                ) AS NombrePaciente,

                CONCAT(
                    perPsi.NombrePer,
                    ' ',
                    perPsi.ApPatPer,
                    ' ',
                    COALESCE(perPsi.ApMatPer, '')
                ) AS NombrePsicologo,

                s.NombreServicio

            FROM cita c

            INNER JOIN paciente pac
                ON c.ClvPac = pac.ClvPac

            INNER JOIN usuario usuPac
                ON pac.ClvUsu = usuPac.ClvUsu

            INNER JOIN persona perPac
                ON usuPac.ClvPer = perPac.ClvPer

            INNER JOIN psicologo psi
                ON c.ClvPsi = psi.ClvPsi

            INNER JOIN usuario usuPsi
                ON psi.ClvUsu = usuPsi.ClvUsu

            INNER JOIN persona perPsi
                ON usuPsi.ClvPer = perPsi.ClvPer

            INNER JOIN servicios s
                ON c.ClvServ = s.ClvServ

            WHERE c.ClvCons = :clvCons";

    $parametros = [
        'clvCons' => $clvCons
    ];

    if ($clvPsi !== null && $clvPsi !== '') {
        $sql .= " AND c.ClvPsi = :clvPsi";

        $parametros['clvPsi'] = $clvPsi;
    }

    if ($estado !== null && $estado !== '') {
        $sql .= " AND UPPER(c.EstadoCita) = :estado";

        $parametros['estado'] = strtoupper($estado);
    }

    $sql .= "
        ORDER BY
            c.FechaCita ASC,
            c.HraInicioCita ASC
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($parametros);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
   public function agenda(): void
{
    $clvCons = $this->consultorio['ClvCons'];

    $psicologoModel = new Psicologo();

    $psicologos =
        $psicologoModel->obtenerActivosPorConsultorio(
            $clvCons
        );

    $this->view(
        'consultorio/agenda',
        [
            'titulo' => 'Agenda general',
            'usuario' => $this->usuario,
            'consultorio' => $this->consultorio,
            'psicologos' => $psicologos
        ],
        'consultorio'
    );
}
public function eventosAgenda(): void
{
    $clvCons = $this->consultorio['ClvCons'];

    $clvPsi = isset($_GET['psicologo'])
        ? trim((string) $_GET['psicologo'])
        : '';

    $estado = isset($_GET['estado'])
        ? strtoupper(trim((string) $_GET['estado']))
        : '';

    $estadosPermitidos = [
        'PROGRAMADA',
        'ASISTIDA',
        'CANCELADA',
        'INASISTENCIA'
    ];

    if (
        $estado !== '' &&
        !in_array($estado, $estadosPermitidos, true)
    ) {
        $estado = '';
    }

    $citaModel = new Cita();

    $citas = $citaModel->obtenerPorConsultorio(
        $clvCons,
        $clvPsi !== '' ? $clvPsi : null,
        $estado !== '' ? $estado : null
    );

    $eventos = array_map(
        static function (array $cita): array {
            $estadoCita = strtoupper(
                trim($cita['EstadoCita'])
            );

            return [
                'id' => $cita['ClvCita'],
                'title' => $cita['NombrePaciente'],

                'start' =>
                    $cita['FechaCita'] .
                    'T' .
                    $cita['HraInicioCita'],

                'end' =>
                    !empty($cita['HraFinCita'])
                        ? $cita['FechaCita'] .
                          'T' .
                          $cita['HraFinCita']
                        : null,

                'classNames' => [
                    'cita-evento',
                    'cita-' . strtolower($estadoCita)
                ],

                'extendedProps' => [
                    'paciente' =>
                        $cita['NombrePaciente'],

                    'psicologo' =>
                        $cita['NombrePsicologo'],

                    'servicio' =>
                        $cita['NombreServicio'],

                    'estado' =>
                        $estadoCita,

                    'notas' =>
                        $cita['NotasCita'] ?? '',

                    'motivoCancelacion' =>
                        $cita['MotivoCancelacion'] ?? '',

                    'fechaCancelacion' =>
                        $cita['FechaCancelacion'] ?? '',

                    'canceladaPor' =>
                        $estadoCita === 'CANCELADA'
                            ? 'Paciente'
                            : ''
                ]
            ];
        },
        $citas
    );

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $eventos,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}
public function psicologos(): void
{
    $clvCons = $this->consultorio['ClvCons'];

    $psicologoModel = new Psicologo();

    $psicologos =
        $psicologoModel->listarPorConsultorio(
            $clvCons
        );

    $this->view(
        'consultorio/psicologos/index',
        [
            'titulo' => 'Especialistas',
            'usuario' => $this->usuario,
            'consultorio' => $this->consultorio,
            'psicologos' => $psicologos
        ],
        'consultorio'
    );
}

public function nuevoPsicologo(): void
{
    $this->view(
        'consultorio/psicologos/form',
        [
            'titulo' => 'Registrar especialista',
            'usuario' => $this->usuario,
            'consultorio' => $this->consultorio,
            'psicologo' => null,
            'errores' => [],
            'datos' => []
        ],
        'consultorio'
    );
}
public function guardarPsicologo(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header(
            'Location: ' .
            \App\Helpers\Helper::baseUrl(
                'consultorio/psicologos'
            )
        );

        exit;
    }

  $datos = [
    'nombre' =>
        trim($_POST['nombre'] ?? ''),

    'apellidoPaterno' =>
        trim($_POST['apellidoPaterno'] ?? ''),

    'apellidoMaterno' =>
        trim($_POST['apellidoMaterno'] ?? ''),

    'fechaNacimiento' =>
        trim($_POST['fechaNacimiento'] ?? ''),

    'genero' =>
        trim($_POST['genero'] ?? ''),

    'correo' =>
        strtolower(
            trim($_POST['correo'] ?? '')
        ),

    'telefono' =>
        trim($_POST['telefono'] ?? ''),

    'cedulaProfesional' =>
        trim($_POST['cedulaProfesional'] ?? ''),

    'especialidad' =>
        trim($_POST['especialidad'] ?? ''),

    'descripcionProfesional' =>
        trim(
            $_POST['descripcionProfesional'] ?? ''
        ),

    'mostrarEnPagina' =>
        isset($_POST['mostrarEnPagina'])
            ? 1
            : 0
];

$contrasenaTemporal =
    $this->generarContrasenaTemporal();

$datos['contrasenaTemporal'] =
    $contrasenaTemporal;

    $errores = $this->validarDatosPsicologo(
        $datos
    );

    if (!empty($errores)) {
        $this->view(
            'consultorio/psicologos/form',
            [
                'titulo' => 'Registrar especialista',
                'usuario' => $this->usuario,
                'consultorio' => $this->consultorio,
                'psicologo' => null,
                'errores' => $errores,
                'datos' => $datos
            ],
            'consultorio'
        );

        return;
    }
try {
    $psicologoModel = new Psicologo();

    $psicologoModel->guardar(
        $datos,
        $this->consultorio['ClvCons']
    );

    $nombreCompleto = trim(
        $datos['nombre'] . ' ' .
        $datos['apellidoPaterno'] . ' ' .
        $datos['apellidoMaterno']
    );

    $urlLogin = Helper::baseUrl('login');

    $mailService = new MailService();

    $mailService->enviarAccesoPsicologo(
        $datos['correo'],
        $nombreCompleto,
        $contrasenaTemporal,
        $urlLogin
    );

    $_SESSION['success'] =
        'El especialista se registró correctamente y ' .
        'se enviaron sus datos de acceso por correo.';

    header(
        'Location: ' .
        Helper::baseUrl(
            'consultorio/psicologos'
        )
    );

    exit;

} catch (\Throwable $e) {
    $errores['general'] =
        $e->getMessage();

    $this->view(
        'consultorio/psicologos/form',
        [
            'titulo' =>
                'Registrar especialista',

            'usuario' =>
                $this->usuario,

            'consultorio' =>
                $this->consultorio,

            'psicologo' =>
                null,

            'errores' =>
                $errores,

            'datos' =>
                $datos
        ],
        'consultorio'
    );
}
}




private function generarContrasenaTemporal(): string
{
    $mayusculas = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $minusculas = 'abcdefghijkmnopqrstuvwxyz';
    $numeros = '23456789';
    $simbolos = '#@$%';

    $contrasena =
        $mayusculas[
            random_int(
                0,
                strlen($mayusculas) - 1
            )
        ];

    $contrasena .=
        $minusculas[
            random_int(
                0,
                strlen($minusculas) - 1
            )
        ];

    $contrasena .=
        $numeros[
            random_int(
                0,
                strlen($numeros) - 1
            )
        ];

    $contrasena .=
        $simbolos[
            random_int(
                0,
                strlen($simbolos) - 1
            )
        ];

    $caracteres =
        $mayusculas .
        $minusculas .
        $numeros;

    while (strlen($contrasena) < 10) {
        $contrasena .=
            $caracteres[
                random_int(
                    0,
                    strlen($caracteres) - 1
                )
            ];
    }

    return str_shuffle($contrasena);
}
public function editarPsicologo(): void
{
    $id = trim($_GET['id'] ?? '');

    if ($id === '') {
        $_SESSION['error'] =
            'No se recibió la clave del especialista.';

        header(
            'Location: ' .
            \App\Helpers\Helper::baseUrl(
                'consultorio/psicologos'
            )
        );

        exit;
    }

    $psicologoModel = new Psicologo();

    $psicologo =
        $psicologoModel->obtenerPorClave(
            $id,
            $this->consultorio['ClvCons']
        );

    if (!$psicologo) {
        $_SESSION['error'] =
            'El especialista no fue encontrado.';

        header(
            'Location: ' .
            \App\Helpers\Helper::baseUrl(
                'consultorio/psicologos'
            )
        );

        exit;
    }

    $datos = [
        'clvPsi' => $psicologo['ClvPsi'],
        'nombre' => $psicologo['NombrePer'],
        'apellidoPaterno' =>
            $psicologo['ApPatPer'],
        'apellidoMaterno' =>
            $psicologo['ApMatPer'],
        'fechaNacimiento' =>
            $psicologo['FechaNacimiento'],
        'genero' =>
            $psicologo['GeneroPer'],
        'correo' =>
            $psicologo['CorreoUsu'],
        'telefono' =>
            $psicologo['TelefonoUsu'],
        'cedulaProfesional' =>
            $psicologo['CedulaProfesional'],
        'especialidad' =>
            $psicologo['EspecialidadPsi'],
        'descripcionProfesional' =>
            $psicologo['DescripcionProfesional'] ?? '',
        'mostrarEnPagina' =>
            (int) $psicologo['MostrarEnPagina']
    ];

    $this->view(
        'consultorio/psicologos/form',
        [
            'titulo' => 'Editar especialista',
            'usuario' => $this->usuario,
            'consultorio' => $this->consultorio,
            'psicologo' => $psicologo,
            'datos' => $datos,
            'errores' => [],
            'modoEdicion' => true
        ],
        'consultorio'
    );
}
public function actualizarPsicologo(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header(
            'Location: ' .
            \App\Helpers\Helper::baseUrl(
                'consultorio/psicologos'
            )
        );

        exit;
    }

    $clvPsi = trim(
        $_POST['clvPsi'] ?? ''
    );

    $datos = [
        'nombre' =>
            trim($_POST['nombre'] ?? ''),

        'apellidoPaterno' =>
            trim($_POST['apellidoPaterno'] ?? ''),

        'apellidoMaterno' =>
            trim($_POST['apellidoMaterno'] ?? ''),

        'fechaNacimiento' =>
            trim($_POST['fechaNacimiento'] ?? ''),

        'genero' =>
            trim($_POST['genero'] ?? ''),

        'correo' =>
            strtolower(
                trim($_POST['correo'] ?? '')
            ),

        'telefono' =>
            trim($_POST['telefono'] ?? ''),

        'cedulaProfesional' =>
            trim(
                $_POST['cedulaProfesional'] ?? ''
            ),

        'especialidad' =>
            trim($_POST['especialidad'] ?? ''),

        'descripcionProfesional' =>
            trim(
                $_POST['descripcionProfesional'] ?? ''
            ),

        'mostrarEnPagina' =>
            isset($_POST['mostrarEnPagina'])
                ? 1
                : 0
    ];

    $errores =
        $this->validarDatosPsicologo(
            $datos
        );

    if ($clvPsi === '') {
        $errores['general'] =
            'La clave del especialista es obligatoria.';
    }

    if (!empty($errores)) {
        $datos['clvPsi'] = $clvPsi;

        $this->view(
            'consultorio/psicologos/form',
            [
                'titulo' => 'Editar especialista',
                'usuario' => $this->usuario,
                'consultorio' => $this->consultorio,
                'psicologo' => null,
                'datos' => $datos,
                'errores' => $errores,
                'modoEdicion' => true
            ],
            'consultorio'
        );

        return;
    }

    try {
        $psicologoModel = new Psicologo();

        $psicologoModel->actualizar(
            $clvPsi,
            $this->consultorio['ClvCons'],
            $datos
        );

        $_SESSION['success'] =
            'El especialista se actualizó correctamente.';

        header(
            'Location: ' .
            \App\Helpers\Helper::baseUrl(
                'consultorio/psicologos'
            )
        );

        exit;
    } catch (\Throwable $e) {
        $datos['clvPsi'] = $clvPsi;
        $errores['general'] = $e->getMessage();

        $this->view(
            'consultorio/psicologos/form',
            [
                'titulo' => 'Editar especialista',
                'usuario' => $this->usuario,
                'consultorio' => $this->consultorio,
                'psicologo' => null,
                'datos' => $datos,
                'errores' => $errores,
                'modoEdicion' => true
            ],
            'consultorio'
        );
    }
}
public function cambiarEstatusPsicologo(): void
{
    $id = trim($_GET['id'] ?? '');

    if ($id === '') {
        $_SESSION['error'] =
            'No se recibió la clave del especialista.';

        header(
            'Location: ' .
            \App\Helpers\Helper::baseUrl(
                'consultorio/psicologos'
            )
        );

        exit;
    }

    try {
        $psicologoModel = new Psicologo();

        $nuevoEstatus =
            $psicologoModel->cambiarEstatus(
                $id,
                $this->consultorio['ClvCons']
            );

        $_SESSION['success'] =
            $nuevoEstatus === 'ACTIVO'
                ? 'El especialista fue activado correctamente.'
                : 'El especialista fue inactivado correctamente.';
    } catch (\Throwable $e) {
        $_SESSION['error'] =
            $e->getMessage();
    }

    header(
        'Location: ' .
        \App\Helpers\Helper::baseUrl(
            'consultorio/psicologos'
        )
    );

    exit;
}
private function validarDatosPsicologo(
    array $datos
): array {
    $errores = [];

    if ($datos['nombre'] === '') {
        $errores['nombre'] =
            'El nombre es obligatorio.';
    }

    if ($datos['apellidoPaterno'] === '') {
        $errores['apellidoPaterno'] =
            'El apellido paterno es obligatorio.';
    }

    if ($datos['apellidoMaterno'] === '') {
        $errores['apellidoMaterno'] =
            'El apellido materno es obligatorio.';
    }

    if ($datos['fechaNacimiento'] === '') {
        $errores['fechaNacimiento'] =
            'La fecha de nacimiento es obligatoria.';
    }

    $generosPermitidos = [
        'Masculino',
        'Femenino',
        'Otro'
    ];

    if (
        !in_array(
            $datos['genero'],
            $generosPermitidos,
            true
        )
    ) {
        $errores['genero'] =
            'Selecciona un género válido.';
    }

    if (
        !filter_var(
            $datos['correo'],
            FILTER_VALIDATE_EMAIL
        )
    ) {
        $errores['correo'] =
            'Ingresa un correo electrónico válido.';
    }

    if (
        !preg_match(
            '/^[0-9]{10}$/',
            $datos['telefono']
        )
    ) {
        $errores['telefono'] =
            'El teléfono debe contener 10 dígitos.';
    }

  

 
    if ($datos['cedulaProfesional'] === '') {
        $errores['cedulaProfesional'] =
            'La cédula profesional es obligatoria.';
    }

    if ($datos['especialidad'] === '') {
        $errores['especialidad'] =
            'La especialidad es obligatoria.';
    }

    return $errores;
}
}