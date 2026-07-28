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

        $this->view(

            'paciente/dashboard',

            [

                'titulo' => 'Panel del paciente',

                'usuario' => $this->usuario,

                'proximaCita' => $proximaCita,

                'saludo' => $saludo,

                'fechaActual' => (

                    new \IntlDateFormatter(

                        'es_MX',

                        \IntlDateFormatter::FULL,

                        \IntlDateFormatter::NONE,

                        'America/Mexico_City',

                        null,

                        "EEEE d 'de' MMMM 'de' y"

                    )

                )->format(new \DateTime())

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

        $horarioModel = new Horario();

        /*
        =====================================
                PSICÓLOGOS
        =====================================
        */

        $psicologos =
            $psicologoModel
                ->obtenerActivosPorConsultorio(
                    $clvCons
                );

        /*
        =====================================
                SERVICIOS
        =====================================
        */

        $servicios =
            $servicioModel
                ->listarServiciosActivos(
                    $clvCons
                );

        /*
        =====================================
                HORARIOS
        =====================================
        */

        $horarios = [];

        foreach ($psicologos as $psicologo) {

            $horarios[
                $psicologo['ClvPsi']
            ] =

            $horarioModel
                ->obtenerPorPsicologo(
                    $psicologo['ClvPsi']
                );

        }

        $this->view(

            'paciente/agendar',

            [

                'titulo' => 'Agendar cita',

                'usuario' => $this->usuario,

                'paciente' => $paciente,

                'psicologos' => $psicologos,

                'servicios' => $servicios,

                'horarios' => $horarios

            ],

            'paciente'

        );

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

        $pacienteModel = new Paciente();

        $paciente = $pacienteModel->obtenerPorUsuario(
            $this->usuario['ClvUsu']
        );

        if (!$paciente) {

            Response::redirect('paciente');

        }

        $servicioModel = new Servicio();

        $servicio =
            $servicioModel->obtenerPorClave(
                $_POST['servicio']
            );

        if (!$servicio) {

            throw new \RuntimeException(
                'Servicio inválido.'
            );

        }

        $datos = [

            'fecha' => $_POST['fecha'],

            'inicio' => $_POST['hora'],

            'duracion' => $servicio['DuracionMinutos'],

            'costo' => $servicio['CostoServicio'],

            'paciente' => $paciente['ClvPac'],

            'psicologo' => $_POST['psicologo'],

            'consultorio' => $servicio['ClvCons'],

            'servicio' => $servicio['ClvServ']

        ];

        $datos['fin'] = date(

            'H:i:s',

            strtotime($datos['inicio']) +

            ($datos['duracion'] * 60)

        );

        try {

            $citaModel = new Cita();

            $citaModel->guardar($datos);

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
            HISTORIAL
    =====================================
    */

    public function historial(): void
    {
        $pacienteModel = new Paciente();

        $paciente = $pacienteModel->obtenerPorUsuario(
            $this->usuario['ClvUsu']
        );

        if (!$paciente) {

            Response::redirect('paciente');

        }

        $citaModel = new Cita();

        $historial = $citaModel->obtenerHistorial(
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

        $paciente = $pacienteModel->obtenerPorUsuario(
            $this->usuario['ClvUsu']
        );

        if (!$paciente) {

            Response::redirect('paciente');

        }

        $this->view(

            'paciente/perfil',

            [

                'titulo' => 'Mi perfil',

                'usuario' => $this->usuario,

                'paciente' => $paciente

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

}