<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;

use App\Models\Paciente;
use App\Models\Cita;
use App\Models\Psicologo;
use App\Models\Servicio;
use App\Models\Consultorio;
use App\Models\Notificacion;
use App\Helpers\Helper;
use App\Services\AgendaService;
use App\Services\CorreoCitaService;
use App\Services\CuentaService;
use App\Services\NotificacionService;
use App\Services\PrivacidadService;
use PDOException;
use RuntimeException;

class PacienteController extends Controller
{
    private array $usuario;

    public function __construct()
    {
        (new \App\Services\AccesoSesionService())->exigirSesionActiva('PACIENTE');
        $usuario = Session::get('usuario');

        if ((int) ($usuario['RequiereCambioContrasena'] ?? 0) === 1) {
            Response::redirect('cambiar-contrasena');
        }

        if (
            (new PrivacidadService())->pacienteDebeResolverPrivacidad(
                (string) ($usuario['ClvUsu'] ?? '')
            )
        ) {
            Response::redirect('privacidad/consentimiento');
        }

        $this->usuario = $usuario;
        $this->asegurarFotoPerfilEnSesion();
    }

    /**
     * Hidrata FotoPerfilPer en sesión (fuente: persona) si aún no está.
     */
    private function asegurarFotoPerfilEnSesion(): void
    {
        if (array_key_exists('FotoPerfilPer', $this->usuario)) {
            return;
        }

        $clvUsu = trim((string) ($this->usuario['ClvUsu'] ?? ''));

        if ($clvUsu === '') {
            return;
        }

        $perfil = (new Paciente())->obtenerPerfilCompleto($clvUsu);
        $foto = is_array($perfil)
            ? trim((string) ($perfil['FotoPerfilPer'] ?? ''))
            : '';

        $this->actualizarFotoSesion($foto);
    }

    private function actualizarFotoSesion(string $fotoPerfilPer): void
    {
        $usuarioSesion = Session::get('usuario');

        if (!is_array($usuarioSesion)) {
            return;
        }

        $usuarioSesion['FotoPerfilPer'] = $fotoPerfilPer;
        Session::set('usuario', $usuarioSesion);
        $this->usuario = $usuarioSesion;
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
            (string) $this->usuario['ClvUsu']
        );

        if (!$paciente) {
            Response::redirect('login');
        }

        $clvPac = (string) ($paciente['ClvPac'] ?? '');
        $clvUsu = (string) $this->usuario['ClvUsu'];

        $citaModel = new Cita();
        $notificacionModel = new Notificacion();

        $proximasCitas = $citaModel->obtenerProximasPorPaciente(
            $clvPac,
            6
        );

        $proximaCita = $proximasCitas[0] ?? null;
        $siguientesCitas = array_slice($proximasCitas, 1, 5);

        $resumenCitas = $citaModel->obtenerResumenPorPaciente($clvPac);

        $actividadReciente =
            $citaModel->obtenerActividadRecientePorPaciente(
                $clvPac,
                5
            );

        $notificacionesRecientes =
            $notificacionModel->listarRecientesPorUsuario(
                $clvUsu,
                3
            );

        $notificacionesNoLeidas =
            $notificacionModel->contarNoLeidas($clvUsu);

        $partesNombre = array_filter(
            [
                trim((string) ($this->usuario['NombrePer'] ?? '')),
                trim((string) ($this->usuario['ApPatPer'] ?? '')),
                trim((string) ($this->usuario['ApMatPer'] ?? ''))
            ],
            static fn(string $parte): bool => $parte !== ''
        );

        $nombrePaciente = trim(implode(' ', $partesNombre));

        if ($nombrePaciente === '') {
            $nombrePaciente = 'Paciente';
        }

        $this->view(
            'paciente/dashboard',
            [
                'titulo' => 'Inicio',
                'usuario' => $this->usuario,
                'nombrePaciente' => $nombrePaciente,
                'fechaActual' => $this->formatearFechaDashboard(),
                'proximaCita' => $proximaCita,
                'siguientesCitas' => $siguientesCitas,
                'resumenCitas' => $resumenCitas,
                'actividadReciente' => $actividadReciente,
                'notificacionesRecientes' => $notificacionesRecientes,
                'notificacionesNoLeidas' => $notificacionesNoLeidas,
                'cargarDashboardCss' => true
            ],
            'paciente'
        );
    }

    private function formatearFechaDashboard(): string
    {
        $zona = new \DateTimeZone('America/Mexico_City');
        $ahora = new \DateTimeImmutable('now', $zona);

        $dias = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo'
        ];

        $meses = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre'
        ];

        $diaSemana = $dias[(int) $ahora->format('N')] ?? '';
        $dia = (int) $ahora->format('j');
        $mes = $meses[(int) $ahora->format('n')] ?? '';
        $anio = $ahora->format('Y');

        return "{$diaSemana}, {$dia} de {$mes} de {$anio}";
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

        $clvPac = (string) ($paciente['ClvPac'] ?? '');
        $citaModel = new Cita();
        $notificacionModel = new Notificacion();

        $citas = $citaModel->obtenerMisCitas($clvPac);

        $cancelables = 0;

        foreach ($citas as $indice => $cita) {
            $cancelacion =
                $citaModel->evaluarCancelacionPaciente($cita);

            $citas[$indice]['cancelacion'] = $cancelacion;

            if (!empty($cancelacion['puedeCancelar'])) {
                $cancelables++;
            }
        }

        $proximaCita = null;
        $zona = new \DateTimeZone('America/Mexico_City');
        $ahora = new \DateTimeImmutable('now', $zona);

        foreach ($citas as $cita) {
            $fecha = trim((string) ($cita['FechaCita'] ?? ''));
            $hora = trim((string) ($cita['HraInicioCita'] ?? ''));

            if ($fecha === '' || $hora === '') {
                continue;
            }

            try {
                $inicio = new \DateTimeImmutable(
                    $fecha . ' ' . substr($hora, 0, 8),
                    $zona
                );
            } catch (\Throwable $e) {
                continue;
            }

            if ($inicio >= $ahora) {
                $proximaCita = $cita;
                break;
            }
        }

        $this->view(
            'paciente/misCitas',
            [
                'titulo' => 'Mis citas',
                'usuario' => $this->usuario,
                'citas' => $citas,
                'proximaCita' => $proximaCita,
                'resumenCitas' => [
                    'programadas' => count($citas),
                    'cancelables' => $cancelables,
                    'noLeidas' => $notificacionModel->contarNoLeidas(
                        (string) $this->usuario['ClvUsu']
                    )
                ],
                'csrf' => Session::csrfToken(),
                'cargarCitasCss' => true
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

        $psicologoModel = new Psicologo();

        $psicologos =
            $psicologoModel->obtenerActivosParaAgendamiento();

        $psicologoPreseleccionado = strtoupper(
            trim((string) ($_GET['psicologo'] ?? ''))
        );
        $servicioPreseleccionado = strtoupper(
            trim((string) ($_GET['servicio'] ?? ''))
        );

        if (
            $psicologoPreseleccionado !== ''
            && !preg_match('/^[A-Z0-9]{1,10}$/', $psicologoPreseleccionado)
        ) {
            $psicologoPreseleccionado = '';
        }

        if (
            $servicioPreseleccionado !== ''
            && !preg_match('/^[A-Z0-9]{1,10}$/', $servicioPreseleccionado)
        ) {
            $servicioPreseleccionado = '';
        }

        if ($psicologoPreseleccionado !== '') {
            $valido = false;

            foreach ($psicologos as $psi) {
                if (
                    strtoupper((string) ($psi['ClvPsi'] ?? ''))
                    === $psicologoPreseleccionado
                ) {
                    $valido = true;
                    break;
                }
            }

            if (!$valido) {
                $psicologoPreseleccionado = '';
                $servicioPreseleccionado = '';
            }
        } else {
            $servicioPreseleccionado = '';
        }

        $this->view(
            'paciente/agendar',
            [
                'titulo' => 'Agendar cita',

                'usuario' => $this->usuario,

                'paciente' => $paciente,

                'psicologos' => $psicologos,

                'psicologoPreseleccionado' => $psicologoPreseleccionado,

                'servicioPreseleccionado' => $servicioPreseleccionado,

                'cargarAgendarCss' => true,

                'cargarAgendarJs' => true
            ],
            'paciente'
        );
    }


    public function serviciosPorPsicologo(): void
    {
        $clvPsi = trim($_GET['psicologo'] ?? '');

        if ($clvPsi === '') {
            $this->responderJson(
                [
                    'ok' => false,
                    'mensaje' =>
                        'Selecciona un psicólogo para ver sus servicios.',
                    'servicios' => []
                ],
                400
            );
        }

        $psicologoModel = new Psicologo();

        $psicologo = $psicologoModel->obtenerParaAgendamiento(
            $clvPsi
        );

        if (!$psicologo) {
            $this->responderJson(
                [
                    'ok' => false,
                    'mensaje' =>
                        'El psicólogo seleccionado no está disponible.',
                    'servicios' => []
                ],
                404
            );
        }

        $servicioModel = new Servicio();

        $servicios = $servicioModel->listarActivosPorPsicologo(
            $clvPsi
        );

        $consultorioModel = new Consultorio();

        $resumenConsultorio =
            $consultorioModel->obtenerResumenPublicoAgendamiento(
                $psicologo['ClvCons']
            );

        $direccionPublica = $resumenConsultorio !== null
            ? Helper::direccionPublicaLegible($resumenConsultorio)
            : '';

        $this->responderJson([
            'ok' => true,
            'servicios' => array_map(
                function (array $servicio): array {
                    return [
                        'ClvServ' => $servicio['ClvServ'],
                        'NombreServicio' =>
                            $servicio['NombreServicio'],
                        'Descripcion' =>
                            trim((string) ($servicio['Descripcion'] ?? '')),
                        'DuracionMinutos' =>
                            (int) $servicio['DuracionMinutos'],
                        'PrecioServicio' =>
                            (float) $servicio['PrecioServicio']
                    ];
                },
                $servicios
            ),
            'psicologo' => [
                'ClvPsi' => $psicologo['ClvPsi'],
                'NombrePsicologo' =>
                    $psicologo['NombrePsicologo'],
                'EspecialidadPsi' =>
                    trim((string) ($psicologo['EspecialidadPsi'] ?? '')),
                'NombreCons' =>
                    $psicologo['NombreCons'],
                'FotoPerfilPer' =>
                    trim((string) ($psicologo['FotoPerfilPer'] ?? '')),
                'NombrePer' =>
                    trim((string) ($psicologo['NombrePer'] ?? '')),
                'ApPatPer' =>
                    trim((string) ($psicologo['ApPatPer'] ?? ''))
            ],
            'consultorio' => [
                'NombreCons' =>
                    $resumenConsultorio['NombreCons']
                    ?? $psicologo['NombreCons'],
                'Direccion' => $direccionPublica,
                'ReferenciaDir' =>
                    trim((string) (
                        $resumenConsultorio['ReferenciaDir'] ?? ''
                    )),
                'LimiteCancHoras' => (int) (
                    $resumenConsultorio['LimiteCancHoras'] ?? 0
                )
            ],
            'mensaje' => $servicios === []
                ? 'Este especialista no tiene servicios disponibles actualmente.'
                : ''
        ]);
    }


    /*
    =====================================
        ESPACIOS DISPONIBLES (AJAX)
    =====================================
    */

    public function horariosDisponibles(): void
    {
        $clvPsi = trim($_GET['psicologo'] ?? '');
        $fecha = trim($_GET['fecha'] ?? '');
        $clvServ = trim($_GET['servicio'] ?? '');

        $agendaService = new AgendaService();

        $resultado = $agendaService->calcularEspaciosDisponibles(
            $clvPsi,
            $clvServ,
            $fecha
        );

        if (!$resultado['ok']) {
            $this->responderJson($resultado, 400);
        }

        $this->responderJson($resultado);
    }

    public function diasDisponibles(): void
    {
        $clvPsi = trim($_GET['psicologo'] ?? '');
        $clvServ = trim($_GET['servicio'] ?? '');
        $mes = trim($_GET['mes'] ?? '');

        $agendaService = new AgendaService();

        $resultado = $agendaService->obtenerDiasDisponiblesDelMes(
            $clvPsi,
            $clvServ,
            $mes
        );

        if (!$resultado['ok']) {
            $this->responderJson($resultado, 400);
        }

        $this->responderJson($resultado);
    }

    private function responderJson(
        array $datos,
        int $codigoHttp = 200
    ): void {
        http_response_code($codigoHttp);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }


    /*
    =====================================
            GUARDAR CITA
    =====================================
    */

    public function guardarCita(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('paciente/agendar');
        }

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            $_SESSION['error'] =
                'La solicitud no es válida. Intenta nuevamente.';

            Response::redirect('paciente/agendar');
        }

        $clvPsi = trim($_POST['psicologo'] ?? '');
        $clvServ = trim($_POST['servicio'] ?? '');
        $fechaCita = trim($_POST['fecha'] ?? '');
        $hraInicioCita = trim($_POST['hora'] ?? '');

        if (
            $clvPsi === '' ||
            $clvServ === '' ||
            $fechaCita === '' ||
            $hraInicioCita === ''
        ) {
            $_SESSION['error'] =
                'Completa todos los campos para agendar la cita.';

            Response::redirect('paciente/agendar');
        }

        $pacienteModel = new Paciente();

        $paciente = $pacienteModel->obtenerPorUsuario(
            $this->usuario['ClvUsu']
        );

        if (!$paciente) {
            Response::redirect('paciente');
        }

        $agendaService = new AgendaService();
        $citaModel = new Cita();

        try {
            $citaModel->beginTransaccion();

            if (
                !$citaModel->bloquearPsicologoParaReserva($clvPsi)
            ) {
                throw new RuntimeException(
                    'El psicólogo seleccionado no está disponible.'
                );
            }

            $validacion = $agendaService->validarEspacioReserva(
                $clvPsi,
                $clvServ,
                $fechaCita,
                $hraInicioCita
            );

            if (!$validacion['ok']) {
                throw new RuntimeException(
                    $validacion['mensaje']
                );
            }

            $datosReserva = $validacion['datos'];

            if (
                $citaModel->existeSolapamientoProgramado(
                    $datosReserva['ClvPsi'],
                    $datosReserva['FechaCita'],
                    $datosReserva['HraInicioCita'],
                    $datosReserva['HraFinCita']
                )
            ) {
                throw new RuntimeException(
                    'El horario seleccionado ya no está disponible. '
                    . 'Selecciona otro horario.'
                );
            }

            $datosReserva['ClvCita'] =
                $citaModel->generarClaveCita();
            $datosReserva['ClvPac'] =
                $paciente['ClvPac'];

            $citaModel->crearCita($datosReserva);

            $correoCitaService = new CorreoCitaService();
            if ($correoCitaService->persistenciaDisponible()) {
                $correoCitaService->prepararParaCitaNueva(
                    (string) $datosReserva['ClvCita']
                );
            }

            $citaModel->commitTransaccion();

            $clvCitaCreada = (string) $datosReserva['ClvCita'];

            $mensajeExito = 'Tu cita fue registrada correctamente.';

            if ($correoCitaService->persistenciaDisponible()) {
                try {
                    $envio = $correoCitaService
                        ->procesarConfirmacionesInmediatas($clvCitaCreada);
                    if (
                        empty($envio['paciente'])
                        && !empty($envio['mensajeCorreo'])
                    ) {
                        $mensajeExito .= ' ' . $envio['mensajeCorreo'];
                    }
                } catch (\Throwable $e) {
                    $mensajeExito .=
                        ' No fue posible enviar la confirmación por correo en este momento.';
                }
            }

            try {
                (new NotificacionService())
                    ->notificarCitaCreadaPorPaciente($clvCitaCreada);
            } catch (\Throwable $e) {
                // La cita ya quedó confirmada; el aviso es auxiliar.
            }

            $_SESSION['success'] = $mensajeExito;

            Response::redirect('paciente/mis-citas');
        } catch (PDOException $e) {
            $citaModel->rollbackTransaccion();

            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                $_SESSION['error'] =
                    'El horario seleccionado ya no está disponible. '
                    . 'Selecciona otro horario.';
            } else {
                $_SESSION['error'] =
                    'No fue posible registrar la cita.';
            }

            Response::redirect('paciente/agendar');
        } catch (RuntimeException $e) {
            $citaModel->rollbackTransaccion();

            $_SESSION['error'] = $e->getMessage();

            Response::redirect('paciente/agendar');
        } catch (\Throwable $e) {
            $citaModel->rollbackTransaccion();

            $_SESSION['error'] =
                'No fue posible registrar la cita.';

            Response::redirect('paciente/agendar');
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

    $cancelacion = $citaModel->evaluarCancelacionPaciente($cita);

    $this->view(
        'paciente/detalleCita',
        [
            'titulo' => 'Detalle de la cita',
            'usuario' => $this->usuario,
            'cita' => $cita,
            'cancelacion' => $cancelacion,
            'csrf' => Session::csrfToken(),
            'cargarCitasCss' => true
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

        $estadoRaw = strtoupper(trim((string) ($_GET['estado'] ?? 'TODAS')));
        $estadosPermitidos = [
            'TODAS',
            'ASISTIDA',
            'CANCELADA',
            'INASISTENCIA'
        ];

        if (!in_array($estadoRaw, $estadosPermitidos, true)) {
            $estadoRaw = 'TODAS';
        }

        $estadoFiltro = $estadoRaw === 'TODAS' ? null : $estadoRaw;

        $pagina = (int) ($_GET['pagina'] ?? 1);
        $pagina = max(1, $pagina);
        $porPagina = 10;

        $citaModel = new Cita();
        $clvPac = (string) ($paciente['ClvPac'] ?? '');

        $total = $citaModel->contarHistorial(
            $clvPac,
            $estadoFiltro
        );

        $totalPaginas = max(1, (int) ceil($total / $porPagina));

        if ($pagina > $totalPaginas) {
            $pagina = $totalPaginas;
        }

        $historial = $citaModel->obtenerHistorial(
            $clvPac,
            $estadoFiltro,
            $pagina,
            $porPagina
        );

        $this->view(
            'paciente/historial',
            [
                'titulo' => 'Historial de citas',
                'usuario' => $this->usuario,
                'historial' => $historial,
                'filtroEstado' => $estadoRaw,
                'paginaActual' => $pagina,
                'totalPaginas' => $totalPaginas,
                'totalHistorial' => $total,
                'cargarCitasCss' => true
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
            (string) $this->usuario['ClvUsu']
        );

        if (!$perfil) {
            Response::redirect('paciente');
        }

        $this->view(
            'paciente/perfil',
            [
                'titulo' => 'Mi perfil',
                'usuario' => $this->usuario,
                'perfil' => $perfil,
                'cargarPerfilCss' => true
            ],
            'paciente'
        );
    }

    public function editarPerfil(): void
    {
        $pacienteModel = new Paciente();

        $perfil = $pacienteModel->obtenerPerfilCompleto(
            (string) $this->usuario['ClvUsu']
        );

        if (!$perfil) {
            Response::redirect('paciente');
        }

        $errores = Session::getFlash('perfil_errores');
        $old = Session::getFlash('perfil_old');

        $this->view(
            'paciente/editarPerfil',
            [
                'titulo' => 'Editar perfil',
                'usuario' => $this->usuario,
                'perfil' => $perfil,
                'errores' => is_array($errores) ? $errores : [],
                'old' => is_array($old) ? $old : [],
                'csrf' => Session::csrfToken(),
                'cargarPerfilCss' => true
            ],
            'paciente'
        );
    }

    public function actualizarPerfil(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            Response::redirect('paciente/perfil');
        }

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            Session::set('error', 'La solicitud no es válida. Inténtalo nuevamente.');
            Response::redirect('paciente/perfil/editar');
        }

        $clvUsu = (string) ($this->usuario['ClvUsu'] ?? '');

        $datos = [
            'NombrePer' => trim((string) ($_POST['NombrePer'] ?? '')),
            'ApPatPer' => trim((string) ($_POST['ApPatPer'] ?? '')),
            'ApMatPer' => trim((string) ($_POST['ApMatPer'] ?? '')),
            'FechaNacimiento' => trim((string) ($_POST['FechaNacimiento'] ?? '')),
            'GeneroPer' => trim((string) ($_POST['GeneroPer'] ?? '')),
            'PaisDir' => trim((string) ($_POST['PaisDir'] ?? '')),
            'EstadoDir' => trim((string) ($_POST['EstadoDir'] ?? '')),
            'MunicipioDir' => trim((string) ($_POST['MunicipioDir'] ?? '')),
            'ColoniaDir' => trim((string) ($_POST['ColoniaDir'] ?? '')),
            'CalleDir' => trim((string) ($_POST['CalleDir'] ?? '')),
            'CodPostDir' => trim((string) ($_POST['CodPostDir'] ?? '')),
            'NumExtDir' => trim((string) ($_POST['NumExtDir'] ?? '')),
            'NumIntDir' => trim((string) ($_POST['NumIntDir'] ?? '')),
            'ReferenciaDir' => trim((string) ($_POST['ReferenciaDir'] ?? ''))
        ];

        $errores = [];

        if ($datos['NombrePer'] === '' || mb_strlen($datos['NombrePer']) > 50) {
            $errores['NombrePer'] = 'El nombre es obligatorio (máx. 50).';
        }

        if ($datos['ApPatPer'] === '' || mb_strlen($datos['ApPatPer']) > 50) {
            $errores['ApPatPer'] = 'El apellido paterno es obligatorio (máx. 50).';
        }

        if (mb_strlen($datos['ApMatPer']) > 50) {
            $errores['ApMatPer'] = 'El apellido materno no debe superar 50 caracteres.';
        }

        $validacionFecha = (new \App\Services\EdadService())
            ->validarFechaNacimiento(
                (string) ($datos['FechaNacimiento'] ?? ''),
                'paciente'
            );

        if (empty($validacionFecha['ok'])) {
            $errores['FechaNacimiento'] = (string) (
                $validacionFecha['mensaje']
                ?? \App\Services\EdadService::MENSAJE_OBLIGATORIA
            );
        }

        // Ignorar manipulación de clasificación enviada por POST.
        unset(
            $_POST['esMenor'],
            $_POST['edad'],
            $_POST['esMayor'],
            $_POST['clasificacion']
        );

        $generos = ['Masculino', 'Femenino', 'Otro'];

        if (!in_array($datos['GeneroPer'], $generos, true)) {
            $errores['GeneroPer'] = 'Selecciona un género válido.';
        }

        $hayDireccion = $datos['PaisDir'] !== ''
            || $datos['EstadoDir'] !== ''
            || $datos['MunicipioDir'] !== ''
            || $datos['ColoniaDir'] !== ''
            || $datos['CalleDir'] !== ''
            || $datos['CodPostDir'] !== ''
            || $datos['NumExtDir'] !== ''
            || $datos['NumIntDir'] !== ''
            || $datos['ReferenciaDir'] !== '';

        $datos['actualizar_direccion'] = false;

        if ($hayDireccion) {
            if ($datos['PaisDir'] === '' || mb_strlen($datos['PaisDir']) > 50) {
                $errores['PaisDir'] = 'El país es obligatorio.';
            }

            if ($datos['EstadoDir'] === '' || mb_strlen($datos['EstadoDir']) > 50) {
                $errores['EstadoDir'] = 'El estado es obligatorio.';
            }

            if (
                $datos['MunicipioDir'] === ''
                || mb_strlen($datos['MunicipioDir']) > 50
            ) {
                $errores['MunicipioDir'] = 'El municipio es obligatorio.';
            }

            if ($datos['ColoniaDir'] === '' || mb_strlen($datos['ColoniaDir']) > 50) {
                $errores['ColoniaDir'] = 'La colonia es obligatoria.';
            }

            if (!preg_match('/^[0-9]{5}$/', $datos['CodPostDir'])) {
                $errores['CodPostDir'] = 'El código postal debe tener 5 dígitos.';
            }

            if (mb_strlen($datos['CalleDir']) > 70) {
                $errores['CalleDir'] = 'La calle no debe superar 70 caracteres.';
            }

            if (mb_strlen($datos['NumExtDir']) > 10) {
                $errores['NumExtDir'] = 'El número exterior no es válido.';
            }

            if (mb_strlen($datos['NumIntDir']) > 10) {
                $errores['NumIntDir'] = 'El número interior no es válido.';
            }

            if (mb_strlen($datos['ReferenciaDir']) > 255) {
                $errores['ReferenciaDir'] = 'La referencia es demasiado larga.';
            }

            if (empty($errores['PaisDir'])
                && empty($errores['EstadoDir'])
                && empty($errores['MunicipioDir'])
                && empty($errores['ColoniaDir'])
                && empty($errores['CodPostDir'])
            ) {
                $datos['actualizar_direccion'] = true;
            }
        }

        if (!empty($errores)) {
            Session::setFlash('perfil_errores', $errores);
            Session::setFlash('perfil_old', $datos);
            Response::redirect('paciente/perfil/editar');
        }

        try {
            (new Paciente())->actualizarPerfilPersonal(
                $clvUsu,
                $datos,
                $_FILES['FotoPerfilPer'] ?? null
            );

            $perfilActualizado = (new Paciente())->obtenerPerfilCompleto(
                $clvUsu
            );

            $usuarioSesion = Session::get('usuario');

            if (is_array($usuarioSesion)) {
                $usuarioSesion['NombrePer'] = $datos['NombrePer'];
                $usuarioSesion['ApPatPer'] = $datos['ApPatPer'];
                $usuarioSesion['ApMatPer'] = $datos['ApMatPer'];
                $usuarioSesion['FotoPerfilPer'] = is_array($perfilActualizado)
                    ? trim((string) (
                        $perfilActualizado['FotoPerfilPer'] ?? ''
                    ))
                    : (string) ($usuarioSesion['FotoPerfilPer'] ?? '');
                Session::set('usuario', $usuarioSesion);
                $this->usuario = $usuarioSesion;
            }

            Session::set(
                'success',
                'Tu perfil fue actualizado correctamente.'
            );

            if (
                !empty($validacionFecha['ok'])
                && ($validacionFecha['clasificacion'] ?? '')
                    === \App\Services\EdadService::CLASIFICACION_MENOR
            ) {
                Session::set(
                    'warning',
                    \App\Services\EdadService::MENSAJE_ALTA_MENOR_PSICOLOGO
                );
            }
        } catch (\Throwable $e) {
            $mensaje = $e->getMessage();

            if (
                str_contains($mensaje, 'fotografía')
                || str_contains($mensaje, 'Fotografía')
                || str_contains($mensaje, 'JPG')
                || str_contains($mensaje, 'MB')
            ) {
                Session::set('error', $mensaje);
            } else {
                Session::set(
                    'error',
                    'No fue posible actualizar tu perfil. Intenta nuevamente.'
                );
            }

            Response::redirect('paciente/perfil/editar');
        }

        Response::redirect('paciente/perfil');
    }

    /*
    =====================================
            CONFIGURACIÓN
    =====================================
    */

    public function configuracion(): void
    {
        $clvUsu = (string) ($this->usuario['ClvUsu'] ?? '');
        $cuentaService = new CuentaService();

        $toastTipo = (string) (Session::getFlash('config_toast_tipo') ?? '');
        $toastMensaje = (string) (Session::getFlash(
            'config_toast_mensaje'
        ) ?? '');

        $this->usuario = is_array(Session::get('usuario'))
            ? Session::get('usuario')
            : $this->usuario;

        $toastSesionTipo = '';
        $toastSesionMensaje = '';

        if (Session::has('success')) {
            $toastSesionTipo = 'success';
            $toastSesionMensaje = (string) Session::get('success');
            Session::remove('success');
        } elseif (Session::has('error')) {
            $toastSesionTipo = 'error';
            $toastSesionMensaje = (string) Session::get('error');
            Session::remove('error');
        }

        if ($toastTipo === '' && $toastSesionTipo !== '') {
            $toastTipo = $toastSesionTipo;
            $toastMensaje = $toastSesionMensaje;
        }

        $this->view(
            'paciente/configuracion',
            [
                'titulo' => 'Configuración',
                'usuario' => $this->usuario,
                'solicitudCorreo' => $cuentaService->obtenerSolicitudPendienteVista(
                    $clvUsu
                ),
                'privacidad' => (new PrivacidadService())->resumenPrivacidadPaciente(
                    $clvUsu
                ),
                'toastTipo' => $toastTipo,
                'toastMensaje' => $toastMensaje,
                'csrf' => Session::csrfToken(),
                'cargarConfiguracionCss' => true
            ],
            'paciente'
        );
    }

    public function cambiarContrasenaConfiguracion(): void
    {
        $this->exigirPostConfiguracion();

        $resultado = (new CuentaService())->cambiarContrasena(
            (string) ($this->usuario['ClvUsu'] ?? ''),
            (string) ($_POST['contrasena_actual'] ?? ''),
            (string) ($_POST['nueva_contrasena'] ?? ''),
            (string) ($_POST['confirmar_contrasena'] ?? '')
        );

        $this->flashConfiguracion(
            $resultado['ok'] ? 'success' : 'error',
            $resultado['mensaje']
        );

        Response::redirect('paciente/configuracion');
    }

    public function actualizarTelefonoConfiguracion(): void
    {
        $this->exigirPostConfiguracion();

        $resultado = (new CuentaService())->actualizarTelefono(
            (string) ($this->usuario['ClvUsu'] ?? ''),
            (string) ($_POST['telefono'] ?? ''),
            (string) ($_POST['contrasena_actual'] ?? '')
        );

        if ($resultado['ok']) {
            $this->usuario = is_array(Session::get('usuario'))
                ? Session::get('usuario')
                : $this->usuario;
        }

        $this->flashConfiguracion(
            $resultado['ok'] ? 'success' : 'error',
            $resultado['mensaje']
        );

        Response::redirect('paciente/configuracion');
    }

    public function solicitarCambioCorreo(): void
    {
        $this->exigirPostConfiguracion();

        $nombre = trim(
            ((string) ($this->usuario['NombrePer'] ?? '')) . ' ' .
            ((string) ($this->usuario['ApPatPer'] ?? ''))
        );

        $resultado = (new CuentaService())->solicitarCambioCorreo(
            (string) ($this->usuario['ClvUsu'] ?? ''),
            (string) ($_POST['correo_nuevo'] ?? ''),
            (string) ($_POST['contrasena_actual'] ?? ''),
            $nombre !== '' ? $nombre : 'Paciente'
        );

        $this->flashConfiguracion(
            $resultado['ok'] ? 'success' : 'error',
            $resultado['mensaje']
        );

        Response::redirect('paciente/configuracion');
    }

    public function verificarCambioCorreo(): void
    {
        $this->exigirPostConfiguracion();

        $resultado = (new CuentaService())->verificarCambioCorreo(
            (string) ($this->usuario['ClvUsu'] ?? ''),
            (string) ($_POST['codigo'] ?? '')
        );

        if ($resultado['ok']) {
            $this->usuario = is_array(Session::get('usuario'))
                ? Session::get('usuario')
                : $this->usuario;
        }

        $this->flashConfiguracion(
            $resultado['ok'] ? 'success' : 'error',
            $resultado['mensaje']
        );

        Response::redirect('paciente/configuracion');
    }

    public function reenviarCodigoCorreo(): void
    {
        $this->exigirPostConfiguracion();

        $nombre = trim(
            ((string) ($this->usuario['NombrePer'] ?? '')) . ' ' .
            ((string) ($this->usuario['ApPatPer'] ?? ''))
        );

        $resultado = (new CuentaService())->reenviarCodigoCorreo(
            (string) ($this->usuario['ClvUsu'] ?? ''),
            $nombre !== '' ? $nombre : 'Paciente'
        );

        $this->flashConfiguracion(
            $resultado['ok'] ? 'success' : 'error',
            $resultado['mensaje']
        );

        Response::redirect('paciente/configuracion');
    }

    public function cancelarCambioCorreo(): void
    {
        $this->exigirPostConfiguracion();

        $resultado = (new CuentaService())->cancelarCambioCorreo(
            (string) ($this->usuario['ClvUsu'] ?? '')
        );

        $this->flashConfiguracion(
            $resultado['ok'] ? 'success' : 'error',
            $resultado['mensaje']
        );

        Response::redirect('paciente/configuracion');
    }

    private function exigirPostConfiguracion(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            Response::redirect('paciente/configuracion');
        }

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            $this->flashConfiguracion(
                'error',
                'La solicitud no es válida. Inténtalo nuevamente.'
            );

            Response::redirect('paciente/configuracion');
        }
    }

    private function flashConfiguracion(
        string $tipo,
        string $mensaje
    ): void {
        Session::setFlash('config_toast_tipo', $tipo);
        Session::setFlash('config_toast_mensaje', $mensaje);
    }

    /*
    =====================================
            NOTIFICACIONES
    =====================================
    */

    public function notificaciones(): void
    {
        Response::redirect('notificaciones');
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

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['error'] =
            'La solicitud no es válida. Inténtalo nuevamente.';

        Response::redirect('paciente/mis-citas');
    }

    $clvCita = trim($_POST['cita'] ?? '');

    if ($clvCita === '') {
        $_SESSION['error'] =
            'No se recibió la cita a cancelar.';

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

    $resultado = $citaModel->cancelarPorPaciente(
        $clvCita,
        $paciente['ClvPac']
    );

    if ($resultado['ok']) {
        /*
         * Cancelación ya hizo commit en Cita::cancelarPorPaciente.
         * Si falla la notificación, la cita permanece cancelada.
         */
        try {
            (new NotificacionService())
                ->notificarCancelacionPaciente($clvCita);
        } catch (\Throwable $e) {
            // Acción principal ya confirmada; no revertible aquí.
        }

        $_SESSION['success'] = $resultado['mensaje'];
    } else {
        $_SESSION['error'] = $resultado['mensaje'];
    }

    Response::redirect('paciente/mis-citas');
}
}