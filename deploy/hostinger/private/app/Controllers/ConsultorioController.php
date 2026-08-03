<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\ConsultorioUsuario;
use App\Models\Cita;
use App\Models\HorarioConsultorio;
use App\Models\Psicologo;
use App\Models\Caracteristica;
use App\Models\Consultorio as ConsultorioModel;
use App\Models\DisponibilidadPsicologo;
use App\Models\Servicio;
use App\Helpers\Helper;
use App\Services\ActivacionCuentaService;
use App\Services\ConfiguracionConsultorioService;
use App\Services\ClaveService;
use App\Services\PublicacionConsultorioService;
use RuntimeException;

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

        if ((int) ($usuario['EstadoUsu'] ?? 0) !== 1) {
            Session::destroy();
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
        $clvCons = $this->consultorio['ClvCons'];

        $citaModel = new Cita();

        $psicologoModel = new Psicologo();

        $publicacionService = new PublicacionConsultorioService();

        $psicologosActivos =
            $psicologoModel->obtenerActivosPorConsultorio(
                $clvCons
            );

        $progreso = $publicacionService->calcularProgreso($clvCons);

        $estadoPagina = $publicacionService->derivarEstadoPagina(
            $this->consultorio,
            (int) ($this->usuario['EstadoUsu'] ?? 0)
        );

        $etiquetaTarjeta = match ($estadoPagina['codigo']) {
            'PUBLICADO' => 'Publicada',
            'OCULTO' => 'Oculta',
            default => 'Borrador'
        };

        $this->view(
            'consultorio/dashboard',
            [
                'titulo' => 'Panel del consultorio',
                'usuario' => $this->usuario,
                'consultorio' => $this->consultorio,

                'citasHoy' =>
                    $citaModel->contarCitasHoyPorConsultorio(
                        $clvCons
                    ),

                'totalPacientes' =>
                    $citaModel->contarPacientesPorConsultorio(
                        $clvCons
                    ),

                'citasSemana' =>
                    $citaModel->contarCitasSemanaPorConsultorio(
                        $clvCons
                    ),

                'citasProgramadas' =>
                    $citaModel->contarCitasProgramadasPorConsultorio(
                        $clvCons
                    ),

                'citasCanceladas' =>
                    $citaModel->contarCitasCanceladasPorConsultorio(
                        $clvCons
                    ),

                'totalPsicologosActivos' =>
                    count($psicologosActivos),

                'proximasCitas' =>
                    $citaModel->obtenerProximasCitasPorConsultorio(
                        $clvCons
                    ),

                'progresoPublicacion' => $progreso,
                'estadoPagina' => $estadoPagina,
                'etiquetaPaginaPublica' => $etiquetaTarjeta,
                'csrf' => Session::csrfToken(),
                'success' => Session::getFlash('success'),
                'error' => Session::getFlash('error'),
                'pendientesPublicacion' =>
                    Session::getFlash('pendientesPublicacion') ?? []
            ],
            'consultorio'
        );
    }

    public function vistaPrevia(): void
    {
        // ClvCons únicamente desde sesión → consultorio_usuario.
        $clvCons = (string) $this->consultorio['ClvCons'];
        $publicacionService = new PublicacionConsultorioService();

        $busqueda = trim((string) ($_GET['busqueda'] ?? ''));
        $busqueda = preg_replace('/\s+/u', ' ', $busqueda) ?? '';
        if (mb_strlen($busqueda) > 80) {
            $busqueda = mb_substr($busqueda, 0, 80);
        }

        $especialidad = trim((string) ($_GET['especialidad'] ?? ''));
        if (mb_strlen($especialidad) > 100) {
            $especialidad = mb_substr($especialidad, 0, 100);
        }

        try {
            $datos = $publicacionService->obtenerDatosVistaPrevia(
                $clvCons,
                $busqueda,
                $especialidad
            );
        } catch (RuntimeException $e) {
            Session::setFlash('error', $e->getMessage());
            Response::redirect('consultorio');
        }

        $datos['titulo'] = 'Vista previa privada';
        $datos['bannerVistaPrevia'] =
            'Vista previa privada. Esta página todavía no es visible al público.';
        $datos['modoVistaPrevia'] = true;

        (new HomeController())->renderPaginaConsultorio(
            $clvCons,
            true,
            'consultorio/vista-previa',
            $datos
        );
    }

    public function publicar(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('consultorio');
        }

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            Session::setFlash(
                'error',
                'La solicitud no es válida. Intenta nuevamente.'
            );
            Response::redirect('consultorio');
        }

        // ClvCons solo desde sesión → consultorio_usuario (constructor).
        $clvCons = (string) $this->consultorio['ClvCons'];
        $clvUsu = (string) $this->usuario['ClvUsu'];

        $resultado = (new PublicacionConsultorioService())->publicar(
            $clvCons,
            $clvUsu
        );

        if (!$resultado['ok']) {
            Session::setFlash(
                'error',
                $resultado['mensaje']
            );

            if (!empty($resultado['pendientes'])) {
                Session::setFlash(
                    'pendientesPublicacion',
                    $resultado['pendientes']
                );
            }

            Response::redirect('consultorio');
        }

        Session::setFlash('success', $resultado['mensaje']);
        Response::redirect('consultorio');
    }

    public function ocultar(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('consultorio');
        }

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            Session::setFlash(
                'error',
                'La solicitud no es válida. Intenta nuevamente.'
            );
            Response::redirect('consultorio');
        }

        $clvCons = (string) $this->consultorio['ClvCons'];
        $clvUsu = (string) $this->usuario['ClvUsu'];

        $resultado = (new PublicacionConsultorioService())->ocultar(
            $clvCons,
            $clvUsu
        );

        if (!$resultado['ok']) {
            Session::setFlash('error', $resultado['mensaje']);
            Response::redirect('consultorio');
        }

        Session::setFlash('success', $resultado['mensaje']);
        Response::redirect('consultorio');
    }

    public function horario(): void
    {
        $clvCons = $this->consultorio['ClvCons'];

        $horarioModel = new HorarioConsultorio();
        $horarioModel->crearDiasFaltantes($clvCons);

        $horariosRegistrados =
            $horarioModel->obtenerPorConsultorio($clvCons);

        $horariosPorDia = [];

        foreach ($horariosRegistrados as $horario) {
            $horariosPorDia[$horario['DiaSemana']] = $horario;
        }

        $diasSemana = [];

        foreach (HorarioConsultorio::diasPermitidos() as $dia) {
            $diasSemana[] = [
                'DiaSemana' => $dia,
                'Etiqueta' => HorarioConsultorio::etiquetaDia($dia),
                'Horario' => $horariosPorDia[$dia] ?? null
            ];
        }

        $this->view(
            'consultorio/horario/index',
            [
                'titulo' => 'Horario general',
                'usuario' => $this->usuario,
                'consultorio' => $this->consultorio,
                'diasSemana' => $diasSemana,
                'errores' => Session::getFlash('errores') ?? [],
                'success' => Session::getFlash('success'),
                'error' => Session::getFlash('error')
            ],
            'consultorio'
        );
    }

    public function actualizarHorario(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('consultorio/horario');
        }

        $this->validarCsrfHorarioConfiguracion();

        $clvCons = $this->consultorio['ClvCons'];
        $clvHorarioCons = trim($_POST['clvHorarioCons'] ?? '');

        if ($clvHorarioCons === '') {
            Session::setFlash(
                'error',
                'No se recibió el horario a actualizar.'
            );

            $this->redirectDespuesHorario();
        }

        $horarioModel = new HorarioConsultorio();

        if (
            !$horarioModel->perteneceAlConsultorio(
                $clvHorarioCons,
                $clvCons
            )
        ) {
            Session::setFlash(
                'error',
                'No tienes permiso para modificar ese horario.'
            );

            $this->redirectDespuesHorario();
        }

        $registro = null;

        foreach (
            $horarioModel->obtenerPorConsultorio($clvCons) as $horario
        ) {
            if ($horario['ClvHorarioCons'] === $clvHorarioCons) {
                $registro = $horario;
                break;
            }
        }

        if (!$registro) {
            Session::setFlash(
                'error',
                'El horario solicitado no existe.'
            );

            $this->redirectDespuesHorario();
        }

        $horaInicio = trim($_POST['horaInicio'] ?? '');
        $horaFin = trim($_POST['horaFin'] ?? '');

        $errores = $this->validarHorarioActivo(
            $horaInicio,
            $horaFin
        );

        if (!empty($errores)) {
            Session::setFlash('errores', [
                $clvHorarioCons => $errores
            ]);

            $this->redirectDespuesHorario();
        }

        $horaInicioNorm = $this->normalizarHora($horaInicio);
        $horaFinNorm = $this->normalizarHora($horaFin);

        if (
            ($registro['EstatusHorario'] ?? '') === 'ACTIVO' &&
            $this->hayDisponibilidadesIncompatibles(
                $clvCons,
                $registro['DiaSemana'],
                $horaInicioNorm,
                $horaFinNorm,
                false
            )
        ) {
            Session::setFlash(
                'error',
                'No se puede aplicar este horario porque existen '
                . 'disponibilidades activas de especialistas fuera del '
                . 'nuevo rango. Ajusta primero la disponibilidad de los '
                . 'especialistas.'
            );

            $this->redirectDespuesHorario();
        }

        $actualizado = $horarioModel->actualizar(
            $clvHorarioCons,
            $clvCons,
            [
                'HoraInicio' => $horaInicioNorm,
                'HoraFin' => $horaFinNorm
            ]
        );

        if (!$actualizado) {
            Session::setFlash(
                'error',
                'No fue posible actualizar el horario.'
            );

            $this->redirectDespuesHorario();
        }

        Session::setFlash(
            'success',
            'Horario de '
            . HorarioConsultorio::etiquetaDia($registro['DiaSemana'])
            . ' actualizado correctamente.'
        );

        $this->redirectDespuesHorario();
    }

    public function cambiarEstatusHorario(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('consultorio/horario');
        }

        $this->validarCsrfHorarioConfiguracion();

        $clvCons = $this->consultorio['ClvCons'];
        $clvHorarioCons = trim($_POST['clvHorarioCons'] ?? '');
        $accion = strtolower(trim($_POST['accion'] ?? ''));

        if ($clvHorarioCons === '') {
            Session::setFlash(
                'error',
                'No se recibió el horario a modificar.'
            );

            $this->redirectDespuesHorario();
        }

        if (!in_array($accion, ['activar', 'inactivar'], true)) {
            Session::setFlash(
                'error',
                'La acción solicitada no es válida.'
            );

            $this->redirectDespuesHorario();
        }

        $horarioModel = new HorarioConsultorio();

        if (
            !$horarioModel->perteneceAlConsultorio(
                $clvHorarioCons,
                $clvCons
            )
        ) {
            Session::setFlash(
                'error',
                'No tienes permiso para modificar ese horario.'
            );

            $this->redirectDespuesHorario();
        }

        $registro = null;

        foreach (
            $horarioModel->obtenerPorConsultorio($clvCons) as $horario
        ) {
            if ($horario['ClvHorarioCons'] === $clvHorarioCons) {
                $registro = $horario;
                break;
            }
        }

        if (!$registro) {
            Session::setFlash(
                'error',
                'El horario solicitado no existe.'
            );

            $this->redirectDespuesHorario();
        }

        $nuevoEstatus =
            $accion === 'activar'
                ? 'ACTIVO'
                : 'INACTIVO';

        if ($registro['EstatusHorario'] === $nuevoEstatus) {
            Session::setFlash(
                'error',
                'El día ya se encuentra en ese estatus.'
            );

            $this->redirectDespuesHorario();
        }

        if ($nuevoEstatus === 'ACTIVO') {
            $horaInicio = substr((string) $registro['HoraInicio'], 0, 5);
            $horaFin = substr((string) $registro['HoraFin'], 0, 5);

            $errores = $this->validarHorarioActivo(
                $horaInicio,
                $horaFin
            );

            if (!empty($errores)) {
                Session::setFlash(
                    'error',
                    'Define un horario válido antes de activar el día.'
                );

                $this->redirectDespuesHorario();
            }
        }

        if (
            $nuevoEstatus === 'INACTIVO' &&
            $this->hayDisponibilidadesIncompatibles(
                $clvCons,
                $registro['DiaSemana'],
                null,
                null,
                true
            )
        ) {
            Session::setFlash(
                'error',
                'No se puede aplicar este horario porque existen '
                . 'disponibilidades activas de especialistas fuera del '
                . 'nuevo rango. Ajusta primero la disponibilidad de los '
                . 'especialistas.'
            );

            $this->redirectDespuesHorario();
        }

        $actualizado = $horarioModel->actualizar(
            $clvHorarioCons,
            $clvCons,
            [
                'EstatusHorario' => $nuevoEstatus
            ]
        );

        if (!$actualizado) {
            Session::setFlash(
                'error',
                'No fue posible cambiar el estatus del día.'
            );

            $this->redirectDespuesHorario();
        }

        Session::setFlash(
            'success',
            'El día '
            . HorarioConsultorio::etiquetaDia($registro['DiaSemana'])
            . (
                $nuevoEstatus === 'ACTIVO'
                    ? ' fue activado correctamente.'
                    : ' fue inactivado correctamente.'
            )
        );

        $this->redirectDespuesHorario();
    }

    public function configuracion(): void
    {
        $clvCons = $this->consultorio['ClvCons'];

        $consultorioModel = new ConsultorioModel();

        $datosConsultorio =
            $consultorioModel->obtenerPorClave($clvCons);

        if (!$datosConsultorio) {
            Session::setFlash(
                'error',
                'No fue posible cargar la configuración del consultorio.'
            );

            Response::redirect('consultorio');
        }

        $caracteristicaModel = new Caracteristica();

        $this->view(
            'consultorio/configuracion/index',
            [
                'titulo' => 'Configuración del consultorio',
                'usuario' => $this->usuario,
                'consultorio' => $this->consultorio,
                'datosConsultorio' => $datosConsultorio,
                'caracteristicas' =>
                    $caracteristicaModel->obtenerPorConsultorio(
                        $clvCons
                    ),
                'diasSemana' =>
                    $this->construirDiasSemanaHorario($clvCons),
                'erroresHorario' =>
                    Session::getFlash('errores') ?? [],
                'erroresConfig' =>
                    Session::getFlash('erroresConfig') ?? [],
                'success' => Session::getFlash('success'),
                'error' => Session::getFlash('error'),
                'cargarConfigCss' => true,
                'cargarConfigJs' => true
            ],
            'consultorio'
        );
    }

    public function actualizarConfiguracion(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('consultorio/configuracion');
        }

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            Session::setFlash(
                'error',
                'La solicitud no es válida. Intenta nuevamente.'
            );

            Response::redirect('consultorio/configuracion');
        }

        $clvCons = $this->consultorio['ClvCons'];

        $consultorioModel = new ConsultorioModel();

        $registro =
            $consultorioModel->obtenerPorClave($clvCons);

        if (!$registro || empty($registro['ClvDir'])) {
            Session::setFlash(
                'error',
                'No fue posible identificar la dirección del consultorio.'
            );

            Response::redirect('consultorio/configuracion');
        }

        $errores = $this->validarDatosConfiguracion(
            $_POST,
            $clvCons
        );

        if (!empty($errores)) {
            Session::setFlash('erroresConfig', $errores);

            Response::redirect('consultorio/configuracion');
        }

        $datosConsultorio = [
            'NombreCons' => trim($_POST['NombreCons'] ?? ''),
            'Slogan' => trim($_POST['Slogan'] ?? ''),
            'Descripcion' => trim($_POST['Descripcion'] ?? ''),
            'TelefonoCons' => trim($_POST['TelefonoCons'] ?? ''),
            'CorreoElectronico' => trim($_POST['CorreoElectronico'] ?? ''),
            'LimiteCancHoras' => (int) ($_POST['LimiteCancHoras'] ?? 0)
        ];

        $latitud = trim($_POST['LatitudDir'] ?? '');
        $longitud = trim($_POST['LongitudDir'] ?? '');

        $datosDireccion = [
            'ClvDir' => $registro['ClvDir'],
            'PaisDir' => trim($_POST['PaisDir'] ?? 'México'),
            'EstadoDir' => trim($_POST['EstadoDir'] ?? ''),
            'MunicipioDir' => trim($_POST['MunicipioDir'] ?? ''),
            'ColoniaDir' => trim($_POST['ColoniaDir'] ?? ''),
            'CalleDir' => trim($_POST['CalleDir'] ?? ''),
            'CodPostDir' => trim($_POST['CodPostDir'] ?? ''),
            'NumExtDir' => trim($_POST['NumExtDir'] ?? ''),
            'NumIntDir' => trim($_POST['NumIntDir'] ?? ''),
            'ReferenciaDir' => trim($_POST['ReferenciaDir'] ?? ''),
            'LatitudDir' =>
                $latitud !== '' ? (float) $latitud : null,
            'LongitudDir' =>
                $longitud !== '' ? (float) $longitud : null
        ];

        $caracteristicasPost =
            $_POST['caracteristicas'] ?? [];

        $caracteristicas = [];

        if (is_array($caracteristicasPost)) {
            foreach ($caracteristicasPost as $clave => $datosCar) {
                if (!is_array($datosCar)) {
                    continue;
                }

                $caracteristicas[$clave] = [
                    'Titulo' => trim($datosCar['Titulo'] ?? ''),
                    'Descripcion' => trim($datosCar['Descripcion'] ?? ''),
                    'EstadoCar' =>
                        isset($datosCar['EstadoCar']) ? 1 : 0
                ];
            }
        }

        try {
            $servicio = new ConfiguracionConsultorioService();

            $servicio->guardarConfiguracion(
                $clvCons,
                $datosConsultorio,
                $datosDireccion,
                $caracteristicas
            );

            Session::setFlash(
                'success',
                'La configuración fue actualizada correctamente.'
            );
        } catch (\Throwable $e) {
            Session::setFlash(
                'error',
                'No fue posible guardar la configuración.'
            );
        }

        Response::redirect('consultorio/configuracion');
    }

    public function actualizarLogoConfiguracion(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('consultorio/configuracion');
        }

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            Session::setFlash(
                'error',
                'La solicitud no es válida. Intenta nuevamente.'
            );

            Response::redirect('consultorio/configuracion');
        }

        $clvCons = $this->consultorio['ClvCons'];

        if (
            !isset($_FILES['logotipo']) ||
            !is_array($_FILES['logotipo'])
        ) {
            Session::setFlash(
                'error',
                'Selecciona un archivo de logotipo.'
            );

            Response::redirect('consultorio/configuracion');
        }

        try {
            $servicio = new ConfiguracionConsultorioService();

            $servicio->guardarLogotipo(
                $clvCons,
                $_FILES['logotipo']
            );

            Session::setFlash(
                'success',
                'El logotipo fue actualizado correctamente.'
            );
        } catch (RuntimeException $e) {
            Session::setFlash(
                'error',
                $e->getMessage()
            );
        } catch (\Throwable $e) {
            Session::setFlash(
                'error',
                'No fue posible actualizar el logotipo.'
            );
        }

        Response::redirect('consultorio/configuracion');
    }

    public function actualizarPortadaConfiguracion(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('consultorio/configuracion');
        }

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            Session::setFlash(
                'error',
                'La solicitud no es válida. Intenta nuevamente.'
            );

            Response::redirect('consultorio/configuracion');
        }

        $clvCons = $this->consultorio['ClvCons'];

        if (
            !isset($_FILES['portada']) ||
            !is_array($_FILES['portada'])
        ) {
            Session::setFlash(
                'error',
                'Selecciona un archivo de portada.'
            );

            Response::redirect('consultorio/configuracion');
        }

        try {
            $servicio = new ConfiguracionConsultorioService();

            $servicio->guardarPortada(
                $clvCons,
                $_FILES['portada']
            );

            Session::setFlash(
                'success',
                'La imagen de portada fue actualizada correctamente.'
            );
        } catch (RuntimeException $e) {
            Session::setFlash(
                'error',
                $e->getMessage()
            );
        } catch (\Throwable $e) {
            Session::setFlash(
                'error',
                'No fue posible actualizar la portada.'
            );
        }

        Response::redirect('consultorio/configuracion');
    }

   public function agenda(): void
{
    $clvCons = $this->consultorio['ClvCons'];

    $psicologoModel = new Psicologo();

    $psicologos =
        $psicologoModel->obtenerActivosPorConsultorio(
            $clvCons
        );

    $citaModel = new Cita();

    $this->view(
        'consultorio/agenda',
        [
            'titulo' => 'Agenda general',
            'usuario' => $this->usuario,
            'consultorio' => $this->consultorio,
            'psicologos' => $psicologos,
            'metricas' => [
                'programadas' =>
                    $citaModel->contarCitasProgramadasPorConsultorio(
                        $clvCons
                    ),
                'asistidas' =>
                    $citaModel->contarCitasAsistidasPorConsultorio(
                        $clvCons
                    ),
                'canceladas' =>
                    $citaModel->contarCitasCanceladasPorConsultorio(
                        $clvCons
                    ),
                'inasistencias' =>
                    $citaModel->contarInasistenciasPorConsultorio(
                        $clvCons
                    )
            ],
            'conteoPorEspecialista' =>
                $citaModel->obtenerConteoCitasPorEspecialistaConsultorio(
                    $clvCons
                )
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

    if ($clvPsi !== '') {
        $psicologoModel = new Psicologo();

        $psicologoValido =
            $psicologoModel->obtenerPorClave(
                $clvPsi,
                $clvCons
            );

        if (!$psicologoValido) {
            $clvPsi = '';
        }
    }

    $citaModel = new Cita();

    $citas = $citaModel->obtenerAgendaOperativaPorConsultorio(
        $clvCons,
        $clvPsi !== '' ? $clvPsi : null,
        $estado !== '' ? $estado : null
    );

    $eventos = array_map(
        static function (array $cita): array {
            $estadoCita = strtoupper(
                trim($cita['EstadoCita'])
            );

            $nombreServicio = trim(
                (string) ($cita['NombreServicio'] ?? '')
            );

            $nombrePsicologo = trim(
                (string) ($cita['NombrePsicologo'] ?? '')
            );

            $tituloBase = $nombreServicio !== ''
                ? $nombreServicio
                : 'Cita ocupada';

            $titulo = $tituloBase . ' · Psic. ' . $nombrePsicologo;

            $duracion = (int) ($cita['DuracionAplicadaMin'] ?? 0);

            return [
                'id' => $cita['ClvCita'],
                'title' => $titulo,

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
                    'psicologo' => $nombrePsicologo,
                    'especialidad' =>
                        trim(
                            (string) ($cita['EspecialidadPsi'] ?? '')
                        ),
                    'servicio' => $nombreServicio,
                    'estado' => $estadoCita,
                    'duracionMinutos' => $duracion,
                    'consultorio' =>
                        trim(
                            (string) ($cita['NombreCons'] ?? '')
                        )
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

    $psicologos = (new ActivacionCuentaService())
        ->enriquecerEstadosPsicologos($psicologos);

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
        Response::redirect('consultorio/psicologos');
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['error'] =
            'La solicitud no es válida. Intenta nuevamente.';

        Response::redirect('consultorio/psicologos/nuevo');
    }

    $datos = [
        'nombre' => trim($_POST['nombre'] ?? ''),
        'apellidoPaterno' =>
            trim($_POST['apellidoPaterno'] ?? ''),
        'apellidoMaterno' =>
            trim($_POST['apellidoMaterno'] ?? ''),
        'fechaNacimiento' =>
            trim($_POST['fechaNacimiento'] ?? ''),
        'genero' => trim($_POST['genero'] ?? ''),
        'correo' => strtolower(trim($_POST['correo'] ?? '')),
        'telefono' => preg_replace(
            '/\D+/',
            '',
            (string) ($_POST['telefono'] ?? '')
        ),
        'cedulaProfesional' =>
            trim($_POST['cedulaProfesional'] ?? ''),
        'especialidad' =>
            trim($_POST['especialidad'] ?? ''),
        'descripcionProfesional' =>
            trim($_POST['descripcionProfesional'] ?? '')
    ];

    $errores = $this->validarDatosPsicologo($datos);

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
        $resultado = (new ActivacionCuentaService())
            ->crearInvitacionPsicologo(
                $datos,
                (string) $this->consultorio['ClvCons'],
                (string) $this->usuario['ClvUsu']
            );

        if (!$resultado['ok']) {
            if (
                ($resultado['codigo'] ?? '') === 'PENDIENTE_MISMO' &&
                !empty($resultado['clvUsu'])
            ) {
                $_SESSION['warning'] =
                    $resultado['mensaje'] .
                    ' Puedes reenviar el enlace desde el listado.';
                $_SESSION['reenvio_clv_usu'] =
                    $resultado['clvUsu'];

                Response::redirect('consultorio/psicologos');
            }

            $errores['general'] = $resultado['mensaje'];

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

        if (!empty($resultado['correoEnviado'])) {
            $_SESSION['success'] = $resultado['mensaje'];
        } else {
            $_SESSION['warning'] = $resultado['mensaje'];
        }

        Response::redirect('consultorio/psicologos');
    } catch (\Throwable $e) {
        $errores['general'] =
            'No fue posible registrar al especialista.';

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
    }
}

public function reenviarActivacionPsicologo(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::redirect('consultorio/psicologos');
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['error'] =
            'La solicitud no es válida. Intenta nuevamente.';

        Response::redirect('consultorio/psicologos');
    }

    $clvUsu = trim((string) ($_POST['clvUsu'] ?? ''));

    if ($clvUsu === '') {
        $_SESSION['error'] =
            'No se identificó al especialista.';

        Response::redirect('consultorio/psicologos');
    }

    $resultado = (new ActivacionCuentaService())
        ->reenviarActivacion(
            $clvUsu,
            (string) $this->usuario['ClvUsu'],
            ActivacionCuentaService::TIPO_PSICOLOGO
        );

    if (!empty($resultado['ok'])) {
        $_SESSION['success'] = $resultado['mensaje'];
    } else {
        $_SESSION['error'] = $resultado['mensaje'];
    }

    Response::redirect('consultorio/psicologos');
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
        Response::redirect('consultorio/psicologos');
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['error'] =
            'La solicitud no es válida. Intenta nuevamente.';

        Response::redirect('consultorio/psicologos');
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

    private function validarHorarioActivo(
        string $horaInicio,
        string $horaFin
    ): array {
        $errores = [];

        if ($horaInicio === '' || $horaFin === '') {
            $errores[] =
                'Debes indicar hora de inicio y hora de fin.';

            return $errores;
        }

        if (
            !$this->horaValida($horaInicio) ||
            !$this->horaValida($horaFin)
        ) {
            $errores[] =
                'El formato de hora no es válido.';

            return $errores;
        }

        $inicio = $this->normalizarHora($horaInicio);
        $fin = $this->normalizarHora($horaFin);

        if ($inicio >= $fin) {
            $errores[] =
                'La hora de inicio debe ser menor que la hora de fin.';
        }

        return $errores;
    }

    private function horaValida(string $hora): bool
    {
        return (bool) preg_match(
            '/^([01]\d|2[0-3]):[0-5]\d$/',
            $hora
        );
    }

    private function normalizarHora(string $hora): string
    {
        $partes = explode(':', trim($hora));

        return sprintf(
            '%02d:%02d:00',
            (int) $partes[0],
            (int) ($partes[1] ?? 0)
        );
    }

    private function construirDiasSemanaHorario(
        string $clvCons
    ): array {
        $horarioModel = new HorarioConsultorio();
        $horarioModel->crearDiasFaltantes($clvCons);

        $horariosRegistrados =
            $horarioModel->obtenerPorConsultorio($clvCons);

        $horariosPorDia = [];

        foreach ($horariosRegistrados as $horario) {
            $horariosPorDia[$horario['DiaSemana']] = $horario;
        }

        $diasSemana = [];

        foreach (HorarioConsultorio::diasPermitidos() as $dia) {
            $diasSemana[] = [
                'DiaSemana' => $dia,
                'Etiqueta' => HorarioConsultorio::etiquetaDia($dia),
                'Horario' => $horariosPorDia[$dia] ?? null
            ];
        }

        return $diasSemana;
    }

    private function redirectDespuesHorario(): void
    {
        $returnTo = trim($_POST['returnTo'] ?? '');

        if ($returnTo === 'configuracion') {
            Response::redirect('consultorio/configuracion');
        }

        Response::redirect('consultorio/horario');
    }

    private function validarCsrfHorarioConfiguracion(): void
    {
        $returnTo = trim($_POST['returnTo'] ?? '');

        if ($returnTo !== 'configuracion') {
            return;
        }

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            Session::setFlash(
                'error',
                'La solicitud no es válida. Intenta nuevamente.'
            );

            Response::redirect('consultorio/configuracion');
        }
    }

    private function hayDisponibilidadesIncompatibles(
        string $clvCons,
        string $diaSemana,
        ?string $horaInicio,
        ?string $horaFin,
        bool $diaInactivo
    ): bool {
        $modelo = new DisponibilidadPsicologo();

        return $modelo->contarActivasIncompatiblesConHorario(
            $clvCons,
            $diaSemana,
            $horaInicio,
            $horaFin,
            $diaInactivo
        ) > 0;
    }

    private function validarDatosConfiguracion(
        array $post,
        string $clvCons
    ): array {
        $errores = [];

        $nombre = trim($post['NombreCons'] ?? '');

        if ($nombre === '') {
            $errores['NombreCons'] =
                'El nombre del consultorio es obligatorio.';
        } elseif (mb_strlen($nombre) > 100) {
            $errores['NombreCons'] =
                'El nombre no debe superar 100 caracteres.';
        }

        $telefono = trim($post['TelefonoCons'] ?? '');

        if ($telefono === '') {
            $errores['TelefonoCons'] =
                'El teléfono es obligatorio.';
        } elseif (!preg_match('/^\d{10}$/', $telefono)) {
            $errores['TelefonoCons'] =
                'El teléfono debe tener 10 dígitos.';
        }

        $correo = trim($post['CorreoElectronico'] ?? '');

        if (
            $correo !== '' &&
            !filter_var($correo, FILTER_VALIDATE_EMAIL)
        ) {
            $errores['CorreoElectronico'] =
                'El correo electrónico no es válido.';
        }

        $slogan = trim($post['Slogan'] ?? '');

        if ($slogan !== '' && mb_strlen($slogan) > 150) {
            $errores['Slogan'] =
                'El eslogan no debe superar 150 caracteres.';
        }

        $descripcion = trim($post['Descripcion'] ?? '');

        if ($descripcion !== '' && mb_strlen($descripcion) > 5000) {
            $errores['Descripcion'] =
                'La descripción es demasiado extensa.';
        }

        $limite = trim($post['LimiteCancHoras'] ?? '');

        if (
            $limite === '' ||
            !ctype_digit($limite) ||
            (int) $limite < 0 ||
            (int) $limite > 168
        ) {
            $errores['LimiteCancHoras'] =
                'El límite debe ser un entero entre 0 y 168 horas.';
        }

        $consultorioModel = new ConsultorioModel();

        if (
            $correo !== '' &&
            $consultorioModel->existeCorreoConsultorio(
                $correo,
                $clvCons
            )
        ) {
            $errores['CorreoElectronico'] =
                'El correo ya está registrado en otro consultorio.';
        }

        $codPost = trim($post['CodPostDir'] ?? '');

        if ($codPost === '' || !preg_match('/^\d{5}$/', $codPost)) {
            $errores['CodPostDir'] =
                'El código postal debe tener 5 dígitos.';
        }

        if (trim($post['EstadoDir'] ?? '') === '') {
            $errores['EstadoDir'] =
                'El estado es obligatorio.';
        }

        if (trim($post['MunicipioDir'] ?? '') === '') {
            $errores['MunicipioDir'] =
                'El municipio es obligatorio.';
        }

        if (trim($post['ColoniaDir'] ?? '') === '') {
            $errores['ColoniaDir'] =
                'La colonia es obligatoria.';
        }

        $latitud = trim($post['LatitudDir'] ?? '');
        $longitud = trim($post['LongitudDir'] ?? '');

        if ($latitud !== '') {
            if (
                !is_numeric($latitud) ||
                (float) $latitud < -90 ||
                (float) $latitud > 90
            ) {
                $errores['LatitudDir'] =
                    'La latitud debe estar entre -90 y 90.';
            }
        }

        if ($longitud !== '') {
            if (
                !is_numeric($longitud) ||
                (float) $longitud < -180 ||
                (float) $longitud > 180
            ) {
                $errores['LongitudDir'] =
                    'La longitud debe estar entre -180 y 180.';
            }
        }

        if (
            ($latitud !== '' && $longitud === '') ||
            ($latitud === '' && $longitud !== '')
        ) {
            $errores['LatitudDir'] =
                'Indica latitud y longitud, o deja ambos vacíos.';
        }

        return $errores;
    }

    public function servicios(): void
    {
        $clvCons = $this->consultorio['ClvCons'];
        $servicioModel = new Servicio();

        $servicios = $servicioModel->listarPorConsultorio($clvCons);

        $this->view(
            'consultorio/servicios/index',
            [
                'titulo' => 'Servicios del consultorio',
                'usuario' => $this->usuario,
                'consultorio' => $this->consultorio,
                'servicios' => $servicios,
                'cargarServiciosCss' => true
            ],
            'consultorio'
        );
    }

    public function nuevoServicio(): void
    {
        $this->view(
            'consultorio/servicios/form',
            [
                'titulo' => 'Registrar servicio',
                'usuario' => $this->usuario,
                'consultorio' => $this->consultorio,
                'servicio' => null,
                'errores' => [],
                'datos' => [
                    'EstatusServicio' => 'ACTIVO'
                ],
                'modoEdicion' => false,
                'cargarServiciosCss' => true
            ],
            'consultorio'
        );
    }

    public function guardarServicio(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('consultorio/servicios');
        }

        $this->validarCsrfServicios();

        $clvCons = $this->consultorio['ClvCons'];
        $datos = $this->obtenerDatosServicioDesdePost(true);
        $errores = $this->validarDatosServicio($datos, $clvCons);

        if ($errores !== []) {
            $this->view(
                'consultorio/servicios/form',
                [
                    'titulo' => 'Registrar servicio',
                    'usuario' => $this->usuario,
                    'consultorio' => $this->consultorio,
                    'servicio' => null,
                    'errores' => $errores,
                    'datos' => $datos,
                    'modoEdicion' => false,
                    'cargarServiciosCss' => true
                ],
                'consultorio'
            );

            return;
        }

        try {
            $servicioModel = new Servicio();
            $clvServ = ClaveService::generar(
                'servicios',
                'ClvServ',
                'SER'
            );

            $guardado = $servicioModel->crearParaConsultorio(
                $clvCons,
                [
                    'ClvServ' => $clvServ,
                    'NombreServicio' => $datos['NombreServicio'],
                    'Descripcion' => $datos['Descripcion'],
                    'DuracionMinutos' => (int) $datos['DuracionMinutos'],
                    'CostoServicio' => number_format(
                        (float) $datos['CostoServicio'],
                        2,
                        '.',
                        ''
                    ),
                    'EstatusServicio' => $datos['EstatusServicio']
                ]
            );

            if (!$guardado) {
                throw new RuntimeException(
                    'No fue posible registrar el servicio.'
                );
            }

            $_SESSION['success'] =
                'El servicio se registró correctamente.';

            Response::redirect('consultorio/servicios');
        } catch (\Throwable $e) {
            $errores['general'] =
                'No fue posible registrar el servicio. Inténtalo nuevamente.';

            $this->view(
                'consultorio/servicios/form',
                [
                    'titulo' => 'Registrar servicio',
                    'usuario' => $this->usuario,
                    'consultorio' => $this->consultorio,
                    'servicio' => null,
                    'errores' => $errores,
                    'datos' => $datos,
                    'modoEdicion' => false,
                    'cargarServiciosCss' => true
                ],
                'consultorio'
            );
        }
    }

    public function verServicio(): void
    {
        $clvServ = trim($_GET['id'] ?? '');

        if ($clvServ === '') {
            $_SESSION['error'] =
                'No se recibió la clave del servicio.';

            Response::redirect('consultorio/servicios');
        }

        $clvCons = $this->consultorio['ClvCons'];
        $servicioModel = new Servicio();

        $servicio = $servicioModel->obtenerPorClaveYConsultorio(
            $clvServ,
            $clvCons
        );

        if (!$servicio) {
            $_SESSION['error'] =
                'El servicio solicitado no existe o no pertenece a tu consultorio.';

            Response::redirect('consultorio/servicios');
        }

        $totalPsicologos = $servicioModel->contarPsicologosAsignados(
            $clvServ,
            $clvCons
        );

        $this->view(
            'consultorio/servicios/ver',
            [
                'titulo' => 'Detalle del servicio',
                'usuario' => $this->usuario,
                'consultorio' => $this->consultorio,
                'servicio' => $servicio,
                'totalPsicologos' => $totalPsicologos,
                'cargarServiciosCss' => true
            ],
            'consultorio'
        );
    }

    public function editarServicio(): void
    {
        $clvServ = trim($_GET['id'] ?? '');

        if ($clvServ === '') {
            $_SESSION['error'] =
                'No se recibió la clave del servicio.';

            Response::redirect('consultorio/servicios');
        }

        $clvCons = $this->consultorio['ClvCons'];
        $servicioModel = new Servicio();

        $servicio = $servicioModel->obtenerPorClaveYConsultorio(
            $clvServ,
            $clvCons
        );

        if (!$servicio) {
            $_SESSION['error'] =
                'El servicio solicitado no existe o no pertenece a tu consultorio.';

            Response::redirect('consultorio/servicios');
        }

        $this->view(
            'consultorio/servicios/form',
            [
                'titulo' => 'Editar servicio',
                'usuario' => $this->usuario,
                'consultorio' => $this->consultorio,
                'servicio' => $servicio,
                'errores' => [],
                'datos' => $servicio,
                'modoEdicion' => true,
                'cargarServiciosCss' => true
            ],
            'consultorio'
        );
    }

    public function actualizarServicio(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('consultorio/servicios');
        }

        $this->validarCsrfServicios();

        $clvServ = trim($_POST['clvServ'] ?? '');

        if ($clvServ === '') {
            $_SESSION['error'] =
                'No se recibió la clave del servicio.';

            Response::redirect('consultorio/servicios');
        }

        $clvCons = $this->consultorio['ClvCons'];
        $servicioModel = new Servicio();

        $servicio = $servicioModel->obtenerPorClaveYConsultorio(
            $clvServ,
            $clvCons
        );

        if (!$servicio) {
            $_SESSION['error'] =
                'El servicio solicitado no existe o no pertenece a tu consultorio.';

            Response::redirect('consultorio/servicios');
        }

        $datos = $this->obtenerDatosServicioDesdePost(false);
        $errores = $this->validarDatosServicio(
            $datos,
            $clvCons,
            $clvServ
        );

        if ($errores !== []) {
            $this->view(
                'consultorio/servicios/form',
                [
                    'titulo' => 'Editar servicio',
                    'usuario' => $this->usuario,
                    'consultorio' => $this->consultorio,
                    'servicio' => $servicio,
                    'errores' => $errores,
                    'datos' => array_merge($servicio, $datos),
                    'modoEdicion' => true,
                    'cargarServiciosCss' => true
                ],
                'consultorio'
            );

            return;
        }

        try {
            $actualizado = $servicioModel->actualizarParaConsultorio(
                $clvServ,
                $clvCons,
                [
                    'NombreServicio' => $datos['NombreServicio'],
                    'Descripcion' => $datos['Descripcion'],
                    'DuracionMinutos' => (int) $datos['DuracionMinutos'],
                    'CostoServicio' => number_format(
                        (float) $datos['CostoServicio'],
                        2,
                        '.',
                        ''
                    )
                ]
            );

            if (!$actualizado) {
                throw new RuntimeException(
                    'No fue posible actualizar el servicio.'
                );
            }

            $_SESSION['success'] =
                'El servicio se actualizó correctamente.';

            Response::redirect('consultorio/servicios');
        } catch (\Throwable $e) {
            $errores['general'] =
                'No fue posible actualizar el servicio. Inténtalo nuevamente.';

            $this->view(
                'consultorio/servicios/form',
                [
                    'titulo' => 'Editar servicio',
                    'usuario' => $this->usuario,
                    'consultorio' => $this->consultorio,
                    'servicio' => $servicio,
                    'errores' => $errores,
                    'datos' => array_merge($servicio, $datos),
                    'modoEdicion' => true,
                    'cargarServiciosCss' => true
                ],
                'consultorio'
            );
        }
    }

    public function cambiarEstatusServicio(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('consultorio/servicios');
        }

        $this->validarCsrfServicios();

        $clvServ = trim($_POST['clvServ'] ?? '');
        $accion = strtolower(trim($_POST['accion'] ?? ''));

        if ($clvServ === '') {
            $_SESSION['error'] =
                'No se recibió la clave del servicio.';

            Response::redirect('consultorio/servicios');
        }

        if (!in_array($accion, ['activar', 'inactivar'], true)) {
            $_SESSION['error'] =
                'La acción solicitada no es válida.';

            Response::redirect('consultorio/servicios');
        }

        $clvCons = $this->consultorio['ClvCons'];
        $servicioModel = new Servicio();

        $servicio = $servicioModel->obtenerPorClaveYConsultorio(
            $clvServ,
            $clvCons
        );

        if (!$servicio) {
            $_SESSION['error'] =
                'El servicio solicitado no existe o no pertenece a tu consultorio.';

            Response::redirect('consultorio/servicios');
        }

        $nuevoEstatus =
            $accion === 'activar'
                ? 'ACTIVO'
                : 'INACTIVO';

        if (($servicio['EstatusServicio'] ?? '') === $nuevoEstatus) {
            $_SESSION['error'] =
                'El servicio ya se encuentra en ese estatus.';

            Response::redirect('consultorio/servicios');
        }

        try {
            $actualizado = $servicioModel->cambiarEstatusParaConsultorio(
                $clvServ,
                $clvCons,
                $nuevoEstatus
            );

            if (!$actualizado) {
                throw new RuntimeException(
                    'No fue posible cambiar el estatus del servicio.'
                );
            }

            $_SESSION['success'] =
                $nuevoEstatus === 'ACTIVO'
                    ? 'El servicio fue activado correctamente.'
                    : 'El servicio fue inactivado correctamente.';

            Response::redirect('consultorio/servicios');
        } catch (\Throwable $e) {
            $_SESSION['error'] =
                'No fue posible cambiar el estatus del servicio.';

            Response::redirect('consultorio/servicios');
        }
    }

    private function validarCsrfServicios(): void
    {
        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            $_SESSION['error'] =
                'La solicitud no es válida. Intenta nuevamente.';

            Response::redirect('consultorio/servicios');
        }
    }

    private function obtenerDatosServicioDesdePost(
        bool $incluirEstatus
    ): array {
        $datos = [
            'NombreServicio' =>
                trim($_POST['nombreServicio'] ?? ''),
            'Descripcion' =>
                trim($_POST['descripcion'] ?? ''),
            'DuracionMinutos' =>
                trim($_POST['duracionMinutos'] ?? ''),
            'CostoServicio' =>
                trim($_POST['costoServicio'] ?? '')
        ];

        if ($incluirEstatus) {
            $datos['EstatusServicio'] =
                strtoupper(trim($_POST['estatusServicio'] ?? 'ACTIVO'));
        }

        return $datos;
    }

    private function validarDatosServicio(
        array $datos,
        string $clvCons,
        ?string $excluirClave = null
    ): array {
        $errores = [];
        $servicioModel = new Servicio();

        $nombre = $datos['NombreServicio'] ?? '';

        if ($nombre === '') {
            $errores['nombreServicio'] =
                'El nombre del servicio es obligatorio.';
        } elseif (strlen($nombre) > 60) {
            $errores['nombreServicio'] =
                'El nombre no puede superar 60 caracteres.';
        } elseif ($nombre !== strip_tags($nombre)) {
            $errores['nombreServicio'] =
                'El nombre no puede contener etiquetas HTML.';
        } elseif (
            $servicioModel->existeNombreEnConsultorio(
                $nombre,
                $clvCons,
                $excluirClave
            )
        ) {
            $errores['nombreServicio'] =
                'Ya existe un servicio con ese nombre en el consultorio.';
        }

        $descripcion = $datos['Descripcion'] ?? '';

        if (strlen($descripcion) > 255) {
            $errores['descripcion'] =
                'La descripción no puede superar 255 caracteres.';
        }

        $duracion = $datos['DuracionMinutos'] ?? '';

        if (
            $duracion === '' ||
            !ctype_digit($duracion) ||
            (int) $duracion <= 0
        ) {
            $errores['duracionMinutos'] =
                'La duración sugerida debe ser un entero positivo.';
        } elseif ((int) $duracion > 480) {
            $errores['duracionMinutos'] =
                'La duración sugerida no puede superar 480 minutos.';
        }

        $costo = $datos['CostoServicio'] ?? '';

        if ($costo === '' || !is_numeric($costo)) {
            $errores['costoServicio'] =
                'El precio sugerido debe ser un número válido.';
        } elseif ((float) $costo < 0) {
            $errores['costoServicio'] =
                'El precio sugerido no puede ser negativo.';
        } elseif ((float) $costo > 99999999.99) {
            $errores['costoServicio'] =
                'El precio sugerido excede el límite permitido.';
        }

        if (isset($datos['EstatusServicio'])) {
            $estatus = $datos['EstatusServicio'];

            if (!in_array($estatus, ['ACTIVO', 'INACTIVO'], true)) {
                $errores['estatusServicio'] =
                    'El estatus seleccionado no es válido.';
            }
        }

        return $errores;
    }
}