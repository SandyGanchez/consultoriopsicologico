<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;

use App\Models\Paciente;
use App\Models\Cita;
use App\Models\Psicologo;
use App\Models\Servicio;
use App\Models\Horario;

class PacienteController extends Controller
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
            $usuario['RolUsu'] !== 'PACIENTE'
        ) {
            Response::redirect('login');
        }

        $this->usuario = $usuario;
    }


    /*
    =====================================
            DASHBOARD
    =====================================
    */

    public function dashboard(): void
{
    $pacienteModel = new Paciente();

    $paciente = $pacienteModel->obtenerPorUsuario(
        $this->usuario['ClvUsu']
    );

    $proximaCita = null;

    if ($paciente) {

        $citaModel = new Cita();

        $proximaCita =
            $citaModel->obtenerProximaCitaPaciente(
                $paciente['ClvPac']
            );
    }

    $hora = (int) date('H');

    if ($hora < 12) {

        $saludo = 'Buenos días ☀️';

    } elseif ($hora < 19) {

        $saludo = 'Buenas tardes 🌤️';

    } else {

        $saludo = 'Buenas noches 🌙';
    }

    $dias = [
        'Sunday'    => 'Domingo',
        'Monday'    => 'Lunes',
        'Tuesday'   => 'Martes',
        'Wednesday' => 'Miércoles',
        'Thursday'  => 'Jueves',
        'Friday'    => 'Viernes',
        'Saturday'  => 'Sábado'
    ];

    $meses = [
        'January'   => 'enero',
        'February'  => 'febrero',
        'March'     => 'marzo',
        'April'     => 'abril',
        'May'       => 'mayo',
        'June'      => 'junio',
        'July'      => 'julio',
        'August'    => 'agosto',
        'September' => 'septiembre',
        'October'   => 'octubre',
        'November'  => 'noviembre',
        'December'  => 'diciembre'
    ];

    $fechaActual =
        $dias[date('l')] .
        ' ' .
        date('d') .
        ' de ' .
        $meses[date('F')] .
        ' de ' .
        date('Y');

    $this->view(
        'paciente/dashboard',
        [
            'titulo' => 'Panel del paciente',

            'usuario' => $this->usuario,

            'proximaCita' => $proximaCita,

            'saludo' => $saludo,

            'fechaActual' => $fechaActual
        ],
        'paciente'
    );
}

    /*
    =====================================
            MIS CITAS
    =====================================
    */

    public function misCitas(): void
    {
        $pacienteModel = new Paciente();

        $paciente = $pacienteModel->obtenerPorUsuario(
            $this->usuario['ClvUsu']
        );

        if (!$paciente) {
            Response::redirect('paciente');
        }

        $citaModel = new Cita();

        $citas = $citaModel->obtenerMisCitas(
            $paciente['ClvPac']
        );

        $this->view(
            'paciente/misCitas',
            [
                'titulo' => 'Mis citas',

                'usuario' => $this->usuario,

                'citas' => $citas
            ],
            'paciente'
        );
    }


    /*
    =====================================
            AGENDAR CITA
    =====================================
    */

    public function agendar(): void
    {
        $pacienteModel = new Paciente();

        $paciente = $pacienteModel->obtenerPorUsuario(
            $this->usuario['ClvUsu']
        );

        if (!$paciente) {
            Response::redirect('paciente');
        }

        /*
        =====================================
                CONSULTORIO
        =====================================
        */

        $clvCons = 'CON001';

        /*
        =====================================
                MODELOS
        =====================================
        */

        $psicologoModel = new Psicologo();

        $servicioModel = new Servicio();

        /*
        =====================================
                PSICÓLOGOS
        =====================================
        */

        $psicologos =
            $psicologoModel->obtenerActivosPorConsultorio(
                $clvCons
            );

        /*
        =====================================
                SERVICIOS
        =====================================
        */

        $servicios =
            $servicioModel->listarServiciosActivos(
                $clvCons
            );

        $this->view(
            'paciente/agendar',
            [
                'titulo' => 'Agendar cita',

                'usuario' => $this->usuario,

                'paciente' => $paciente,

                'psicologos' => $psicologos,

                'servicios' => $servicios
            ],
            'paciente'
        );
    }


    /*
    =====================================
        HORARIOS DISPONIBLES
    =====================================
    
    Esta función es utilizada por AJAX
    desde agendar.php.
    */

    public function horariosDisponibles(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $psicologo = trim($_GET['psicologo'] ?? '');

        $fecha = trim($_GET['fecha'] ?? '');

        $servicio = trim($_GET['servicio'] ?? '');

        if (
            $psicologo === '' ||
            $fecha === '' ||
            $servicio === ''
        ) {
            echo json_encode([
                'success' => false,
                'horarios' => [],
                'mensaje' => 'Faltan datos para consultar los horarios.'
            ]);

            return;
        }

        /*
        =====================================
                VALIDAR FECHA
        =====================================
        */

        $fechaObj = \DateTime::createFromFormat(
            'Y-m-d',
            $fecha
        );

        if (
            !$fechaObj ||
            $fechaObj->format('Y-m-d') !== $fecha
        ) {
            echo json_encode([
                'success' => false,
                'horarios' => [],
                'mensaje' => 'La fecha seleccionada no es válida.'
            ]);

            return;
        }

        /*
        =====================================
                OBTENER DÍA
        =====================================
        */

        $diasSemana = [
            1 => 'LUNES',
            2 => 'MARTES',
            3 => 'MIERCOLES',
            4 => 'JUEVES',
            5 => 'VIERNES',
            6 => 'SABADO',
            7 => 'DOMINGO'
        ];

        $numeroDia = (int) $fechaObj->format('N');

        $diaSemana = $diasSemana[$numeroDia];

        /*
        =====================================
                MODELOS
        =====================================
        */

        $horarioModel = new Horario();

        $citaModel = new Cita();

        $servicioModel = new Servicio();

        /*
        =====================================
                SERVICIO
        =====================================
        */

        $servicioData =
            $servicioModel->obtenerPorClave(
                $servicio
            );

        if (!$servicioData) {

            echo json_encode([
                'success' => false,
                'horarios' => [],
                'mensaje' => 'El servicio seleccionado no es válido.'
            ]);

            return;
        }

        $duracion = (int) $servicioData['DuracionMinutos'];

        if ($duracion <= 0) {
            $duracion = 60;
        }

        /*
        =====================================
                HORARIO DEL PSICÓLOGO
        =====================================
        */

        $horariosDia =
            $horarioModel->obtenerHorariosDelDia(
                $psicologo,
                $diaSemana
            );

        if (empty($horariosDia)) {

            echo json_encode([
                'success' => true,
                'horarios' => [],
                'mensaje' =>
                    'El psicólogo no trabaja ese día.'
            ]);

            return;
        }

        /*
        =====================================
                HORAS OCUPADAS
        =====================================
        */

        $horasOcupadas =
            $citaModel->obtenerHorasOcupadas(
                $psicologo,
                $fecha
            );

        $horasOcupadas = array_map(
            function ($hora) {
                return substr($hora, 0, 5);
            },
            $horasOcupadas
        );

        /*
        =====================================
                GENERAR HORARIOS
        =====================================
        */

        $horasDisponibles = [];

        foreach ($horariosDia as $horario) {

            $inicioHorario = new \DateTime(
                $fecha . ' ' . $horario['HoraInicio']
            );

            $finHorario = new \DateTime(
                $fecha . ' ' . $horario['HoraFin']
            );

            $intervalo = new \DateInterval(
                'PT30M'
            );

            $horaActual = clone $inicioHorario;

            while (true) {

                $horaFinCita = clone $horaActual;

                $horaFinCita->modify(
                    '+' . $duracion . ' minutes'
                );

                /*
                La cita debe terminar dentro
                del horario laboral.
                */

                if ($horaFinCita > $finHorario) {
                    break;
                }

                $hora = $horaActual->format('H:i');

                /*
                No mostrar horarios ocupados.
                */

                if (!in_array(
                    $hora,
                    $horasOcupadas,
                    true
                )) {

                    $horasDisponibles[] = [
                        'valor' => $horaActual->format('H:i:s'),
                        'texto' => $hora
                    ];
                }

                $horaActual->add($intervalo);
            }
        }

        /*
        =====================================
                ELIMINAR DUPLICADOS
        =====================================
        */

        $unicos = [];

        foreach ($horasDisponibles as $hora) {

            $unicos[$hora['valor']] = $hora;
        }

        $horasDisponibles = array_values($unicos);

        /*
        =====================================
                ORDENAR
        =====================================
        */

        usort(
            $horasDisponibles,
            function ($a, $b) {
                return strcmp(
                    $a['valor'],
                    $b['valor']
                );
            }
        );

        echo json_encode([
            'success' => true,
            'horarios' => $horasDisponibles,
            'mensaje' => empty($horasDisponibles)
                ? 'No hay horarios disponibles para esa fecha.'
                : ''
        ]);
    }


    /*
    =====================================
            GUARDAR CITA
    =====================================
    */

    public function guardarCita(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

            Response::redirect(
                'paciente/agendar'
            );
        }

        /*
        =====================================
                DATOS POST
        =====================================
        */

        $fecha = trim(
            $_POST['fecha'] ?? ''
        );

        $hora = trim(
            $_POST['hora'] ?? ''
        );

        $psicologo = trim(
            $_POST['psicologo'] ?? ''
        );

        $servicioClave = trim(
            $_POST['servicio'] ?? ''
        );

        if (
            $fecha === '' ||
            $hora === '' ||
            $psicologo === '' ||
            $servicioClave === ''
        ) {

            $_SESSION['error'] =
                'Completa todos los campos para agendar la cita.';

            Response::redirect(
                'paciente/agendar'
            );
        }

        /*
        =====================================
                PACIENTE
        =====================================
        */

        $pacienteModel = new Paciente();

        $paciente =
            $pacienteModel->obtenerPorUsuario(
                $this->usuario['ClvUsu']
            );

        if (!$paciente) {

            Response::redirect('paciente');
        }

        /*
        =====================================
                VALIDAR FECHA
        =====================================
        */

        $fechaObj = \DateTime::createFromFormat(
            'Y-m-d',
            $fecha
        );

        if (
            !$fechaObj ||
            $fechaObj->format('Y-m-d') !== $fecha
        ) {

            $_SESSION['error'] =
                'La fecha seleccionada no es válida.';

            Response::redirect(
                'paciente/agendar'
            );
        }

        $hoy = new \DateTime(
            date('Y-m-d')
        );

        if ($fechaObj < $hoy) {

            $_SESSION['error'] =
                'No puedes agendar una cita en una fecha pasada.';

            Response::redirect(
                'paciente/agendar'
            );
        }

        /*
        =====================================
                SERVICIO
        =====================================
        */

        $servicioModel = new Servicio();

        $servicio =
            $servicioModel->obtenerPorClave(
                $servicioClave
            );

        if (!$servicio) {

            $_SESSION['error'] =
                'El servicio seleccionado no es válido.';

            Response::redirect(
                'paciente/agendar'
            );
        }

        /*
        =====================================
                OBTENER HORARIO
        =====================================
        */

        $diasSemana = [
            1 => 'LUNES',
            2 => 'MARTES',
            3 => 'MIERCOLES',
            4 => 'JUEVES',
            5 => 'VIERNES',
            6 => 'SABADO',
            7 => 'DOMINGO'
        ];

        $diaSemana =
            $diasSemana[
                (int) $fechaObj->format('N')
            ];

        $horarioModel = new Horario();

        $horariosDia =
            $horarioModel->obtenerHorariosDelDia(
                $psicologo,
                $diaSemana
            );

        if (empty($horariosDia)) {

            $_SESSION['error'] =
                'El psicólogo no trabaja ese día.';

            Response::redirect(
                'paciente/agendar'
            );
        }

        /*
        =====================================
                DURACIÓN
        =====================================
        */

        $duracion =
            (int) $servicio['DuracionMinutos'];

        if ($duracion <= 0) {
            $duracion = 60;
        }

        /*
        =====================================
                VALIDAR HORA
        =====================================
        */

        $horaObj = \DateTime::createFromFormat(
            'H:i:s',
            $hora
        );

        if (!$horaObj) {

            $horaObj = \DateTime::createFromFormat(
                'H:i',
                $hora
            );
        }

        if (!$horaObj) {

            $_SESSION['error'] =
                'El horario seleccionado no es válido.';

            Response::redirect(
                'paciente/agendar'
            );
        }

        $horaNormalizada =
            $horaObj->format('H:i:s');

        $inicioCita = new \DateTime(
            $fecha . ' ' . $horaNormalizada
        );

        $horaValida = false;

        foreach ($horariosDia as $horario) {

            $inicioHorario = new \DateTime(
                $fecha . ' ' . $horario['HoraInicio']
            );

            $finHorario = new \DateTime(
                $fecha . ' ' . $horario['HoraFin']
            );

            $finCita = clone $inicioCita;

            $finCita->modify(
                '+' . $duracion . ' minutes'
            );

            if (
                $inicioCita >= $inicioHorario &&
                $finCita <= $finHorario
            ) {

                $horaValida = true;

                break;
            }
        }

        if (!$horaValida) {

            $_SESSION['error'] =
                'El horario seleccionado no corresponde al horario laboral del psicólogo.';

            Response::redirect(
                'paciente/agendar'
            );
        }

        /*
        =====================================
                OBTENER PACIENTE
        =====================================
        */

        $datos = [

            'fecha' =>
                $fecha,

            'inicio' =>
                $horaNormalizada,

            'duracion' =>
                $duracion,

            'costo' =>
                $servicio['CostoServicio'],

            'paciente' =>
                $paciente['ClvPac'],

            'psicologo' =>
                $psicologo,

            'consultorio' =>
                $servicio['ClvCons'],

            'servicio' =>
                $servicio['ClvServ']
        ];

        /*
        =====================================
                CALCULAR FIN
        =====================================
        */

        $datos['fin'] = date(
            'H:i:s',
            strtotime(
                $datos['inicio']
            ) +
            ($datos['duracion'] * 60)
        );

        /*
        =====================================
                GUARDAR
        =====================================
        */

        try {

            $citaModel = new Cita();

            $citaModel->guardar(
                $datos
            );

            $_SESSION['success'] =
                'La cita fue registrada correctamente.';

            Response::redirect(
                'paciente/mis-citas'
            );

        } catch (\RuntimeException $e) {

            $_SESSION['error'] =
                $e->getMessage();

            Response::redirect(
                'paciente/agendar'
            );
        }
    }

    /*
=====================================
        DETALLE DE CITA
=====================================
*/

public function detalleCita(): void
{
    $clvCita = trim($_GET['cita'] ?? '');

    if ($clvCita === '') {

        Response::redirect('paciente/mis-citas');
    }

    $pacienteModel = new Paciente();

    $paciente = $pacienteModel->obtenerPorUsuario(
        $this->usuario['ClvUsu']
    );

    if (!$paciente) {

        Response::redirect('paciente');
    }

    $citaModel = new Cita();

    $cita = $citaModel->obtenerDetallePaciente(
        $clvCita,
        $paciente['ClvPac']
    );

    if (!$cita) {

        $_SESSION['error'] =
            'La cita no existe o no pertenece a tu cuenta.';

        Response::redirect('paciente/mis-citas');
    }

    $this->view(
        'paciente/detalleCita',
        [
            'titulo' => 'Detalle de la cita',

            'usuario' => $this->usuario,

            'cita' => $cita
        ],
        'paciente'
    );
}

    /*
    =====================================
            HISTORIAL
    =====================================
    */

    public function historial(): void
    {
        $pacienteModel = new Paciente();

        $paciente =
            $pacienteModel->obtenerPorUsuario(
                $this->usuario['ClvUsu']
            );

        if (!$paciente) {
            Response::redirect('paciente');
        }

        $citaModel = new Cita();

        $historial =
            $citaModel->obtenerHistorial(
                $paciente['ClvPac']
            );

        $this->view(
            'paciente/historial',
            [
                'titulo' => 'Historial clínico',

                'usuario' => $this->usuario,

                'historial' => $historial
            ],
            'paciente'
        );
    }


    /*
=====================================
        PERFIL
=====================================
*/

public function perfil(): void
{
    $pacienteModel = new Paciente();

    $perfil = $pacienteModel->obtenerPerfilCompleto(
        $this->usuario['ClvUsu']
    );

    if (!$perfil) {

        Response::redirect('paciente');

    }

    $this->view(
        'paciente/perfil',
        [
            'titulo' => 'Mi perfil',

            'usuario' => $this->usuario,

            'perfil' => $perfil
        ],
        'paciente'
    );
}


    /*
    =====================================
            NOTIFICACIONES
    =====================================
    */

    public function notificaciones(): void
    {
        $this->view(
            'paciente/notificaciones',
            [
                'titulo' => 'Notificaciones',

                'usuario' => $this->usuario
            ],
            'paciente'
        );
    }

    /*
=====================================
        CANCELAR CITA
=====================================
*/

public function cancelarCita(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        Response::redirect('paciente/mis-citas');
    }

    $clvCita = trim($_POST['cita'] ?? '');

    $motivo = trim($_POST['motivo'] ?? '');

    if ($clvCita === '') {

        $_SESSION['error'] =
            'No se recibió la cita a cancelar.';

        Response::redirect('paciente/mis-citas');
    }

    $citaModel = new Cita();

    $cita = $citaModel->obtenerPorClave(
        $clvCita
    );

    if (!$cita) {

        $_SESSION['error'] =
            'La cita no existe.';

        Response::redirect('paciente/mis-citas');
    }

    $pacienteModel = new Paciente();

    $paciente =
        $pacienteModel->obtenerPorUsuario(
            $this->usuario['ClvUsu']
        );

    if (
        !$paciente ||
        $cita['ClvPac'] !== $paciente['ClvPac']
    ) {

        $_SESSION['error'] =
            'No tienes permiso para cancelar esa cita.';

        Response::redirect('paciente/mis-citas');
    }

    if (
        $cita['EstadoCita'] !== 'PROGRAMADA'
    ) {

        $_SESSION['error'] =
            'Solo se pueden cancelar citas programadas.';

        Response::redirect('paciente/mis-citas');
    }

    try {

        $citaModel->cancelar(
            $clvCita,
            $motivo
        );

        $_SESSION['success'] =
            'La cita fue cancelada correctamente.';

    } catch (\Throwable $e) {

        $_SESSION['error'] =
            'No fue posible cancelar la cita.';
    }

    Response::redirect('paciente/mis-citas');
}
}