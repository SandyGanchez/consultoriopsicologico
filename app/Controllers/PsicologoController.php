<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\Cita;
use App\Models\Consultorio;
use App\Models\DisponibilidadPsicologo;
use App\Models\HorarioConsultorio;
use App\Models\Paciente;
use App\Models\Psicologo;
use App\Models\PsicologoServicio;
use App\Models\Usuario;
use App\Helpers\Helper;
use App\Services\ActivacionCuentaService;
use App\Services\AgendaService;
use App\Services\ClaveService;
use App\Services\CompletarInformacionPacienteService;
use App\Services\CuentaService;
use App\Services\ExpedienteClinicoService;
use App\Services\NotificacionService;
use App\Services\CorreoCitaService;
use App\Services\PendienteClinicoService;
use App\Services\ServicioOfertaService;
use App\Services\SugerenciaServicioService;
use App\Services\RedSocialService;
use App\Services\RedSocialUrlValidator;
use App\Models\RedSocialPsicologo;
use DateTimeImmutable;
use DateTimeZone;
use PDOException;
use RuntimeException;
class PsicologoController extends Controller
{
    private array $usuario;

    public function __construct()
    {
        (new \App\Services\AccesoSesionService())->exigirSesionActiva('PSICOLOGO');
        $this->usuario = Session::get('usuario');
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

        $perfil = (new Psicologo())->obtenerPerfilPorUsuario($clvUsu);
        $foto = is_array($perfil)
            ? trim((string) ($perfil['FotoPerfilPer'] ?? ''))
            : '';

        $usuarioSesion = Session::get('usuario');

        if (!is_array($usuarioSesion)) {
            return;
        }

        $usuarioSesion['FotoPerfilPer'] = $foto;
        Session::set('usuario', $usuarioSesion);
        $this->usuario = $usuarioSesion;
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
    $clvCons = (string) ($psicologo['ClvCons'] ?? '');

    $citaModel = new Cita();
    $pendientes = (new PendienteClinicoService())->listarPendientesOperativos(
        (string) $clvPsi,
        $clvCons
    );

    $this->view(
    'psicologo/dashboard',
    [
        'titulo' => 'Panel del especialista',

        'usuario' => $this->usuario,

        'psicologo' => $psicologo,

        'perfilIncompleto' =>
            (int) ($psicologo['MostrarEnPagina'] ?? 0) === 0,

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
            ),

        'citasRegistrarAsistencia' => $pendientes['registrarAsistencia'],
        'historiasPendientes' => $pendientes['historiasPendientes'],
        'seguimientosPendientes' => $pendientes['seguimientosPendientes']
    ],
    'psicologo'
);
}
public function actualizarPerfil(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::redirect('psicologo/perfil');
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        Session::set(
            'error',
            'La solicitud no es válida. Intenta nuevamente.'
        );

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
            trim($_POST['GeneroPer'] ?? '')
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

    $validacionFecha = (new \App\Services\EdadService())
        ->validarFechaNacimiento(
            (string) ($datos['FechaNacimiento'] ?? ''),
            'adulto'
        );

    if (empty($validacionFecha['ok'])) {
        $errores[] = (string) (
            $validacionFecha['mensaje']
            ?? \App\Services\EdadService::MENSAJE_OBLIGATORIA
        );
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

    if (!empty($errores)) {
        Session::set(
            'error',
            implode(' ', $errores)
        );

        Response::redirect('psicologo/perfil');
    }

    try {
        $psicologoModel = new Psicologo();
        $perfilActual = $psicologoModel->obtenerPerfilPorUsuario($clvUsu);

        $datos['TelefonoUsu'] = trim(
            (string) ($perfilActual['TelefonoUsu'] ?? '')
        );

        $psicologoModel->actualizarPerfil(
            $clvUsu,
            $datos,
            $_FILES['FotoPerfilPer'] ?? null
        );

        $perfilActualizado = $psicologoModel->obtenerPerfilPorUsuario(
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
    $contexto = $this->obtenerContextoPsicologo();
    $clvPsi = $contexto['psicologo']['ClvPsi'];
    $clvCons = $contexto['psicologo']['ClvCons'];

    // Idempotente: incorpora servicios institucionales faltantes.
    (new ServicioOfertaService())->incorporarServiciosActivosAPsicologo(
        $clvPsi,
        $clvCons
    );

    $asignacionModel = new PsicologoServicio();
    $misServicios = $asignacionModel->listarPorPsicologo($clvPsi);
    $sugerenciaService = new SugerenciaServicioService();

    $this->view(
        'psicologo/servicios/index',
        [
            'titulo' => 'Mis servicios',
            'usuario' => $this->usuario,
            'consultorio' => $contexto['consultorio'],
            'psicologo' => $contexto['psicologo'],
            'misServicios' => $misServicios,
            'catalogoDisponible' => [],
            'sugerencias' => $sugerenciaService->listarParaPsicologo($clvPsi),
            'sugerenciasHabilitadas' => $sugerenciaService->persistenciaDisponible(),
            'cargarServiciosPsicologoCss' => true
        ],
        'psicologo'
    );
}

public function sugerirServicio(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::redirect('psicologo/servicios');
    }

    $this->validarCsrfServiciosPsicologo();

    $contexto = $this->obtenerContextoPsicologo();
    $resultado = (new SugerenciaServicioService())->crearSugerencia(
        (string) $contexto['psicologo']['ClvPsi'],
        (string) $contexto['psicologo']['ClvCons'],
        $_POST
    );

    $_SESSION[$resultado['ok'] ? 'success' : 'error'] =
        (string) ($resultado['mensaje'] ?? 'No fue posible enviar la sugerencia.');

    Response::redirect('psicologo/servicios');
}

/**
 * Legacy: la selección manual quedó retirada.
 * Los servicios institucionales se incorporan automáticamente.
 */
public function seleccionarServicio(): void
{
    $_SESSION['error'] =
        'La selección manual de servicios ya no está disponible. '
        . 'Configura precio y duración en Mis servicios.';

    Response::redirect('psicologo/servicios');
}

/**
 * Legacy: alta manual de relación psicologo_servicio retirada de rutas activas.
 */
public function guardarServicio(): void
{
    $_SESSION['error'] =
        'La asignación manual de servicios ya no está disponible.';

    Response::redirect('psicologo/servicios');
}

public function editarServicio(): void
{
    $contexto = $this->obtenerContextoPsicologo();
    $clvServ = trim($_GET['id'] ?? '');

    if ($clvServ === '') {
        $_SESSION['error'] =
            'No se recibió la clave del servicio.';

        Response::redirect('psicologo/servicios');
    }

    $clvPsi = $contexto['psicologo']['ClvPsi'];
    $asignacionModel = new PsicologoServicio();

    $asignacion = $asignacionModel->obtenerAsignacionPropia(
        $clvPsi,
        $clvServ
    );

    if (
        !$asignacion ||
        ($asignacion['ClvCons'] ?? '') !== $contexto['psicologo']['ClvCons']
    ) {
        $_SESSION['error'] =
            'La asignación solicitada no existe o no te pertenece.';

        Response::redirect('psicologo/servicios');
    }

    $this->view(
        'psicologo/servicios/form',
        [
            'titulo' => 'Editar servicio',
            'usuario' => $this->usuario,
            'consultorio' => $contexto['consultorio'],
            'psicologo' => $contexto['psicologo'],
            'servicio' => $asignacion,
            'datos' => $asignacion,
            'errores' => [],
            'modoEdicion' => true,
            'cargarServiciosPsicologoCss' => true
        ],
        'psicologo'
    );
}

public function actualizarServicio(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::redirect('psicologo/servicios');
    }

    $this->validarCsrfServiciosPsicologo();

    $contexto = $this->obtenerContextoPsicologo();
    $clvPsi = $contexto['psicologo']['ClvPsi'];
    $clvCons = $contexto['psicologo']['ClvCons'];
    $clvServ = trim($_POST['clvServ'] ?? '');

    if ($clvServ === '') {
        $_SESSION['error'] =
            'No se recibió la clave del servicio.';

        Response::redirect('psicologo/servicios');
    }

    $asignacionModel = new PsicologoServicio();

    $asignacion = $asignacionModel->obtenerAsignacionPropia(
        $clvPsi,
        $clvServ
    );

    if (
        !$asignacion ||
        ($asignacion['ClvCons'] ?? '') !== $clvCons
    ) {
        $_SESSION['error'] =
            'La asignación solicitada no existe o no te pertenece.';

        Response::redirect('psicologo/servicios');
    }

    $datosEntrada = $this->obtenerDatosAsignacionDesdePost();
    $errores = $this->validarDatosAsignacion($datosEntrada);

    if ($errores !== []) {
        $this->view(
            'psicologo/servicios/form',
            [
                'titulo' => 'Editar servicio',
                'usuario' => $this->usuario,
                'consultorio' => $contexto['consultorio'],
                'psicologo' => $contexto['psicologo'],
                'servicio' => $asignacion,
                'datos' => array_merge($asignacion, $datosEntrada),
                'errores' => $errores,
                'modoEdicion' => true,
                'cargarServiciosPsicologoCss' => true
            ],
            'psicologo'
        );

        return;
    }

    try {
        $actualizado = $asignacionModel->actualizarAsignacion(
            $clvPsi,
            $clvServ,
            (float) $datosEntrada['PrecioServicio'],
            (int) $datosEntrada['DuracionMinutos']
        );

        if (!$actualizado) {
            throw new RuntimeException(
                'No fue posible actualizar la asignación.'
            );
        }

        $_SESSION['success'] =
            'Precio y duración actualizados correctamente.';

        Response::redirect('psicologo/servicios');
    } catch (\Throwable $e) {
        $errores['general'] =
            'No fue posible actualizar la asignación. Inténtalo nuevamente.';

        $this->view(
            'psicologo/servicios/form',
            [
                'titulo' => 'Editar servicio',
                'usuario' => $this->usuario,
                'consultorio' => $contexto['consultorio'],
                'psicologo' => $contexto['psicologo'],
                'servicio' => $asignacion,
                'datos' => array_merge($asignacion, $datosEntrada),
                'errores' => $errores,
                'modoEdicion' => true,
                'cargarServiciosPsicologoCss' => true
            ],
            'psicologo'
        );
    }
}

public function cambiarEstatusServicio(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::redirect('psicologo/servicios');
    }

    $this->validarCsrfServiciosPsicologo();

    $contexto = $this->obtenerContextoPsicologo();
    $clvPsi = $contexto['psicologo']['ClvPsi'];
    $clvCons = $contexto['psicologo']['ClvCons'];
    $clvServ = trim($_POST['clvServ'] ?? '');
    $accion = strtolower(trim($_POST['accion'] ?? ''));

    if ($clvServ === '') {
        $_SESSION['error'] =
            'No se recibió la clave del servicio.';

        Response::redirect('psicologo/servicios');
    }

    if (!in_array($accion, ['activar', 'inactivar'], true)) {
        $_SESSION['error'] =
            'La acción solicitada no es válida.';

        Response::redirect('psicologo/servicios');
    }

    $asignacionModel = new PsicologoServicio();

    $asignacion = $asignacionModel->obtenerAsignacionPropia(
        $clvPsi,
        $clvServ
    );

    if (
        !$asignacion ||
        ($asignacion['ClvCons'] ?? '') !== $clvCons
    ) {
        $_SESSION['error'] =
            'La asignación solicitada no existe o no te pertenece.';

        Response::redirect('psicologo/servicios');
    }

    $nuevoEstatus =
        $accion === 'activar'
            ? 'ACTIVA'
            : 'INACTIVA';

    if (($asignacion['EstatusAsignacion'] ?? '') === $nuevoEstatus) {
        $_SESSION['error'] =
            'La asignación ya se encuentra en ese estatus.';

        Response::redirect('psicologo/servicios');
    }

    if (
        $nuevoEstatus === 'ACTIVA' &&
        ($asignacion['EstatusServicio'] ?? '') !== 'ACTIVO'
    ) {
        $_SESSION['error'] =
            'No puedes activar una oferta cuyo servicio institucional está inactivo.';

        Response::redirect('psicologo/servicios');
    }

    if ($nuevoEstatus === 'ACTIVA') {
        $precio = (float) ($asignacion['PrecioServicio'] ?? 0);
        $duracion = (int) ($asignacion['DuracionMinutos'] ?? 0);

        if (!ServicioOfertaService::precioEsValido($precio)) {
            $_SESSION['error'] =
                'Configura un precio válido mayor a cero antes de ofrecer el servicio.';

            Response::redirect(
                'psicologo/servicios/editar?id=' . rawurlencode($clvServ)
            );
        }

        if (!ServicioOfertaService::duracionEsValida($duracion)) {
            $_SESSION['error'] =
                'Configura una duración válida antes de ofrecer el servicio.';

            Response::redirect(
                'psicologo/servicios/editar?id=' . rawurlencode($clvServ)
            );
        }
    }

    try {
        $actualizado = $asignacionModel->cambiarEstatus(
            $clvPsi,
            $clvServ,
            $nuevoEstatus
        );

        if (!$actualizado) {
            throw new RuntimeException(
                'No fue posible cambiar el estatus de la asignación.'
            );
        }

        $_SESSION['success'] =
            $nuevoEstatus === 'ACTIVA'
                ? 'La asignación fue activada correctamente.'
                : 'La asignación fue desactivada correctamente.';

        Response::redirect('psicologo/servicios');
    } catch (\Throwable $e) {
        $_SESSION['error'] =
            'No fue posible cambiar el estatus de la asignación.';

        Response::redirect('psicologo/servicios');
    }
}

private function validarCsrfServiciosPsicologo(): void
{
    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['error'] =
            'La solicitud no es válida. Intenta nuevamente.';

        Response::redirect('psicologo/servicios');
    }
}

private function obtenerDatosAsignacionDesdePost(): array
{
    return [
        'PrecioServicio' =>
            trim($_POST['precioServicio'] ?? ''),
        'DuracionMinutos' =>
            trim($_POST['duracionMinutos'] ?? '')
    ];
}

private function validarDatosAsignacion(array $datos): array
{
    $errores = [];

    $precio = $datos['PrecioServicio'] ?? '';

    if ($precio === '' || !is_numeric($precio)) {
        $errores['precioServicio'] =
            'El precio es obligatorio y debe ser numérico.';
    } elseif (!ServicioOfertaService::precioEsValido((float) $precio)) {
        $errores['precioServicio'] =
            'El precio debe ser mayor a cero y no exceder el límite permitido.';
    }

    $duracion = $datos['DuracionMinutos'] ?? '';

    if (
        $duracion === '' ||
        !ctype_digit($duracion) ||
        (int) $duracion <= 0
    ) {
        $errores['duracionMinutos'] =
            'La duración debe ser un entero positivo en minutos.';
    } elseif ((int) $duracion > 480) {
        $errores['duracionMinutos'] =
            'La duración no puede superar 480 minutos.';
    }

    return $errores;
}
public function pacientes(): void
{
    $contexto = $this->obtenerContextoPsicologo();

    $clvPsi = $contexto['psicologo']['ClvPsi'];

    $busqueda = trim((string) ($_GET['q'] ?? ''));
    $filtro = strtolower(trim((string) ($_GET['filtro'] ?? 'todos')));

    $filtrosPermitidos = [
        'todos',
        'proxima',
        'atendidos',
        'historicos'
    ];

    if (!in_array($filtro, $filtrosPermitidos, true)) {
        $filtro = 'todos';
    }

    $pacienteModel = new Paciente();

    $pacientes = $pacienteModel->listarPorPsicologo(
        $clvPsi,
        $busqueda !== '' ? $busqueda : null,
        $filtro === 'todos' ? null : $filtro
    );

    $total = count($pacientes);

    $this->view(
        'psicologo/pacientes/index',
        [
            'titulo' => 'Mis pacientes',
            'usuario' => $this->usuario,
            'psicologo' => $contexto['psicologo'],
            'consultorio' => $contexto['consultorio'],
            'pacientes' => $pacientes,
            'totalPacientes' => $total,
            'busqueda' => $busqueda,
            'filtro' => $filtro,
            'cargarPacientesPsicologo' => true
        ],
        'psicologo'
    );
}

public function registrarPacienteNuevo(): void
{
    $contexto = $this->obtenerContextoPsicologo();
    $clvPsi = $contexto['psicologo']['ClvPsi'];

    $asignacionModel = new PsicologoServicio();

    $servicios = array_values(
        array_filter(
            $asignacionModel->listarPorPsicologo($clvPsi),
            static function (array $servicio): bool {
                return ($servicio['EstatusAsignacion'] ?? '') === 'ACTIVA'
                    && ($servicio['EstatusServicio'] ?? '') === 'ACTIVO';
            }
        )
    );

    $this->view(
        'psicologo/pacientes/registrar',
        [
            'titulo' => 'Registrar paciente y agendar',
            'usuario' => $this->usuario,
            'psicologo' => $contexto['psicologo'],
            'consultorio' => $contexto['consultorio'],
            'servicios' => $servicios,
            'errores' => [],
            'datos' => [],
            'cargarPacientesPsicologo' => true
        ],
        'psicologo'
    );
}

public function guardarPacienteNuevo(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::redirect('psicologo/pacientes');
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['error'] =
            'La solicitud no es válida. Intenta nuevamente.';

        Response::redirect('psicologo/pacientes/registrar');
    }

    $contexto = $this->obtenerContextoPsicologo();
    $clvPsi = (string) $contexto['psicologo']['ClvPsi'];
    $clvCons = (string) $contexto['consultorio']['ClvCons'];

    $datosPaciente = [
        'nombre' => trim((string) ($_POST['nombre'] ?? '')),
        'apellidoPaterno' =>
            trim((string) ($_POST['apellidoPaterno'] ?? '')),
        'apellidoMaterno' =>
            trim((string) ($_POST['apellidoMaterno'] ?? '')),
        'fechaNacimiento' =>
            trim((string) ($_POST['fechaNacimiento'] ?? '')),
        'genero' => trim((string) ($_POST['genero'] ?? '')),
        'correo' => strtolower(trim((string) ($_POST['correo'] ?? ''))),
        'telefono' => preg_replace(
            '/\D+/',
            '',
            (string) ($_POST['telefono'] ?? '')
        )
    ];

    $datosCita = [
        'servicio' => trim((string) ($_POST['servicio'] ?? '')),
        'fecha' => trim((string) ($_POST['fecha'] ?? '')),
        'hora' => trim((string) ($_POST['hora'] ?? ''))
    ];

    $errores = $this->validarDatosPacienteNuevo(
        $datosPaciente,
        $datosCita
    );

    $validacionFechaHora = $this->validarFechaHoraAgenda(
        $datosCita['fecha'],
        $datosCita['hora']
    );

    if (!$validacionFechaHora['ok']) {
        $errores['fecha'] = $validacionFechaHora['mensaje'];
    }

    if ($errores !== []) {
        $asignacionModel = new PsicologoServicio();
        $servicios = array_values(
            array_filter(
                $asignacionModel->listarPorPsicologo($clvPsi),
                static function (array $servicio): bool {
                    return ($servicio['EstatusAsignacion'] ?? '') === 'ACTIVA'
                        && ($servicio['EstatusServicio'] ?? '') === 'ACTIVO';
                }
            )
        );

        $this->view(
            'psicologo/pacientes/registrar',
            [
                'titulo' => 'Registrar paciente y agendar',
                'usuario' => $this->usuario,
                'psicologo' => $contexto['psicologo'],
                'consultorio' => $contexto['consultorio'],
                'servicios' => $servicios,
                'errores' => $errores,
                'datos' => array_merge($datosPaciente, $datosCita),
                'cargarPacientesPsicologo' => true
            ],
            'psicologo'
        );

        return;
    }

    $resultado = (new ActivacionCuentaService())
        ->crearInvitacionPaciente(
            $datosPaciente,
            $datosCita,
            $clvPsi,
            $clvCons,
            (string) $this->usuario['ClvUsu']
        );

    if (!$resultado['ok']) {
        if (($resultado['codigo'] ?? '') === 'ACTIVO_RELACIONADO') {
            $_SESSION['error'] = $resultado['mensaje'];
            Response::redirect('psicologo/agenda');
        }

        if (($resultado['codigo'] ?? '') === 'PENDIENTE_MISMO') {
            $_SESSION['warning'] = $resultado['mensaje'];
            Response::redirect('psicologo/pacientes');
        }

        $_SESSION['error'] = $resultado['mensaje'];
        Response::redirect('psicologo/pacientes/registrar');
    }

    if (!empty($resultado['correoEnviado'])) {
        $_SESSION['success'] = $resultado['mensaje'];
    } else {
        $_SESSION['warning'] = $resultado['mensaje'];
    }

    $clasificacionEdad = (new \App\Services\EdadService())
        ->validarFechaNacimiento(
            (string) ($datosPaciente['fechaNacimiento'] ?? ''),
            'paciente'
        );

    if (
        !empty($clasificacionEdad['ok'])
        && ($clasificacionEdad['clasificacion'] ?? '')
            === \App\Services\EdadService::CLASIFICACION_MENOR
    ) {
        $_SESSION['warning'] = \App\Services\EdadService::MENSAJE_ALTA_MENOR_PSICOLOGO
            . (
                !empty($resultado['correoEnviado'])
                    ? ''
                    : ' ' . (string) ($resultado['mensaje'] ?? '')
            );
        unset($_SESSION['success']);
    }

    Response::redirect('psicologo/pacientes');
}

public function reenviarActivacionPaciente(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::redirect('psicologo/pacientes');
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['error'] =
            'La solicitud no es válida. Intenta nuevamente.';

        Response::redirect('psicologo/pacientes');
    }

    $clvUsu = trim((string) ($_POST['clvUsu'] ?? ''));

    if ($clvUsu === '') {
        $_SESSION['error'] = 'No se identificó al paciente.';
        Response::redirect('psicologo/pacientes');
    }

    $resultado = (new ActivacionCuentaService())
        ->reenviarActivacion(
            $clvUsu,
            (string) $this->usuario['ClvUsu'],
            ActivacionCuentaService::TIPO_PACIENTE
        );

    if (!empty($resultado['ok'])) {
        $_SESSION['success'] = $resultado['mensaje'];
    } else {
        $_SESSION['error'] = $resultado['mensaje'];
    }

    Response::redirect('psicologo/pacientes');
}

/**
 * @return array<string, string>
 */
private function validarDatosPacienteNuevo(
    array $datosPaciente,
    array $datosCita
): array {
    $errores = [];

    if ($datosPaciente['nombre'] === '') {
        $errores['nombre'] = 'El nombre es obligatorio.';
    }

    if ($datosPaciente['apellidoPaterno'] === '') {
        $errores['apellidoPaterno'] =
            'El apellido paterno es obligatorio.';
    }

    $validacionFecha = (new \App\Services\EdadService())
        ->validarFechaNacimiento(
            (string) ($datosPaciente['fechaNacimiento'] ?? ''),
            'paciente'
        );

    if (empty($validacionFecha['ok'])) {
        $errores['fechaNacimiento'] = (string) (
            $validacionFecha['mensaje']
            ?? \App\Services\EdadService::MENSAJE_OBLIGATORIA
        );
    }

    $generos = ['Masculino', 'Femenino', 'Otro'];

    if (
        !in_array(
            $datosPaciente['genero'],
            $generos,
            true
        )
    ) {
        $errores['genero'] =
            'Selecciona un género válido.';
    }

    if (
        !filter_var(
            $datosPaciente['correo'],
            FILTER_VALIDATE_EMAIL
        )
    ) {
        $errores['correo'] =
            'Ingresa un correo electrónico válido.';
    }

    if (
        !preg_match(
            '/^[0-9]{10}$/',
            (string) $datosPaciente['telefono']
        )
    ) {
        $errores['telefono'] =
            'El teléfono debe contener 10 dígitos.';
    }

    if ($datosCita['servicio'] === '') {
        $errores['servicio'] = 'Selecciona un servicio.';
    }

    if ($datosCita['fecha'] === '') {
        $errores['fecha'] = 'Selecciona una fecha.';
    }

    if ($datosCita['hora'] === '') {
        $errores['hora'] = 'Selecciona un horario disponible.';
    }

    return $errores;
}

public function verPaciente(string $clvPac): void
{
    $contexto = $this->obtenerContextoPsicologo();

    $clvPsi = $contexto['psicologo']['ClvPsi'];
    $clvPac = trim($clvPac);

    if ($clvPac === '') {
        $_SESSION['error'] =
            'No tienes autorización para consultar este paciente.';

        Response::redirect('psicologo/pacientes');
    }

    $pacienteModel = new Paciente();

    $paciente = $pacienteModel->obtenerParaPsicologo(
        $clvPac,
        $clvPsi
    );

    if (!$paciente) {
        $_SESSION['error'] =
            'No tienes autorización para consultar este paciente.';

        Response::redirect('psicologo/pacientes');
    }

    $estadoRaw = strtoupper(trim((string) ($_GET['estado'] ?? 'TODAS')));
    $estadosPermitidos = [
        'TODAS',
        'PROGRAMADA',
        'ASISTIDA',
        'CANCELADA',
        'INASISTENCIA'
    ];

    if (!in_array($estadoRaw, $estadosPermitidos, true)) {
        $estadoRaw = 'TODAS';
    }

    $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
    $porPagina = 10;
    $conteosEstado = $pacienteModel->contarCitasConPsicologoPorEstado(
        $clvPac,
        (string) $clvPsi
    );
    $totalCitas = (int) ($conteosEstado[$estadoRaw] ?? $conteosEstado['TODAS']);
    $totalPaginas = max(1, (int) ceil($totalCitas / $porPagina));

    if ($pagina > $totalPaginas) {
        $pagina = $totalPaginas;
    }

    $citas = $pacienteModel->listarCitasConPsicologo(
        $clvPac,
        (string) $clvPsi,
        $estadoRaw === 'TODAS' ? null : $estadoRaw,
        $pagina,
        $porPagina
    );

    foreach ($citas as $i => $cita) {
        $citas[$i]['notaOperativa'] =
            \App\Services\EstadoCitaPresentacion::notaPsicologo(
                (string) ($cita['EstadoCita'] ?? '')
            );
    }

    $clvCons = (string) ($contexto['psicologo']['ClvCons'] ?? '');
    $pendientePaciente = (new PendienteClinicoService())->evaluarPaciente(
        $clvPac,
        (string) $clvPsi,
        $clvCons
    );

    $this->view(
        'psicologo/pacientes/ver',
        [
            'titulo' => 'Detalle del paciente',
            'usuario' => $this->usuario,
            'psicologo' => $contexto['psicologo'],
            'consultorio' => $contexto['consultorio'],
            'paciente' => $paciente,
            'citas' => $citas,
            'filtroEstadoCitas' => $estadoRaw,
            'conteosEstadoCitas' => $conteosEstado,
            'paginaCitas' => $pagina,
            'totalPaginasCitas' => $totalPaginas,
            'totalCitasFiltradas' => $totalCitas,
            'historiaPendiente' => !empty($pendientePaciente['historiaPendiente']),
            'seguimientoPendiente' => !empty($pendientePaciente['seguimientoPendiente']),
            'citasRegistrarAsistencia' =>
                $pendientePaciente['citasRegistrarAsistencia'] ?? [],
            'citasSeguimientoPendiente' =>
                $pendientePaciente['citasSeguimientoPendiente'] ?? [],
            'clvHistPendiente' => (string) ($pendientePaciente['clvHist'] ?? ''),
            'infoPersonalIncompleta' => !empty($pendientePaciente['infoPersonalIncompleta']),
            'faltantesPersonales' => $pendientePaciente['faltantes'] ?? [
                'persona' => [],
                'direccion' => []
            ],
            'datosPersonales' => $pendientePaciente['datosPersonales'] ?? $paciente,
            'mensajeExito' => Session::getFlash('success'),
            'mensajeError' => Session::getFlash('error'),
            'cargarPacientesPsicologo' => true
        ],
        'psicologo'
    );
}

public function completarInformacionPaciente(string $clvPac): void
{
    $contexto = $this->obtenerContextoPsicologo();
    $clvPsi = (string) $contexto['psicologo']['ClvPsi'];
    $clvCons = (string) $contexto['psicologo']['ClvCons'];
    $clvPac = trim($clvPac);

    $retorno = strtolower(trim((string) ($_GET['retorno'] ?? 'detalle')));
    $servicio = new CompletarInformacionPacienteService();
    $rutaRetorno = $servicio->resolverRutaRetorno($retorno, $clvPac);

    $preparacion = $servicio->prepararFormulario(
        $clvPac,
        $clvPsi,
        $clvCons
    );

    if (empty($preparacion['ok'])) {
        $mensaje = (string) (
            $preparacion['mensaje']
            ?? 'No tienes autorización para consultar este paciente.'
        );

        if (($preparacion['tieneFaltantes'] ?? null) === false) {
            Session::set('success', $mensaje);
        } else {
            Session::set('error', $mensaje);
        }

        Response::redirect($rutaRetorno);
    }

    $this->view(
        'psicologo/pacientes/completar-informacion',
        [
            'titulo' => 'Completar información del paciente',
            'usuario' => $this->usuario,
            'psicologo' => $contexto['psicologo'],
            'consultorio' => $contexto['consultorio'],
            'paciente' => $preparacion['paciente'],
            'faltantes' => $preparacion['faltantes'],
            'valores' => $preparacion['valores'] ?? [],
            'retorno' => in_array($retorno, CompletarInformacionPacienteService::RETORNOS, true)
                ? $retorno
                : 'detalle',
            'rutaCancelar' => $rutaRetorno,
            'errores' => Session::getFlash('completar_info_errores') ?? [],
            'valoresOld' => Session::getFlash('completar_info_old') ?? [],
            'mensajeError' => Session::getFlash('error'),
            'cargarPacientesPsicologo' => true
        ],
        'psicologo'
    );
}

public function guardarCompletarInformacionPaciente(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::redirect('psicologo/pacientes');
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        Session::set(
            'error',
            'La solicitud no es válida. Inténtalo nuevamente.'
        );
        Response::redirect('psicologo/pacientes');
    }

    $contexto = $this->obtenerContextoPsicologo();
    $clvPsi = (string) $contexto['psicologo']['ClvPsi'];
    $clvCons = (string) $contexto['psicologo']['ClvCons'];

    $servicio = new CompletarInformacionPacienteService();
    $resultado = $servicio->guardar($clvPsi, $clvCons, $_POST);

    $clvPac = trim((string) (
        $resultado['clvPac']
        ?? ($_POST['ClvPac'] ?? '')
    ));
    $retorno = strtolower(trim((string) ($_POST['retorno'] ?? 'detalle')));
    $rutaRetorno = $clvPac !== ''
        ? $servicio->resolverRutaRetorno($retorno, $clvPac)
        : 'psicologo/pacientes';

    if (empty($resultado['ok'])) {
        Session::setFlash(
            'completar_info_errores',
            $resultado['errores'] ?? []
        );

        $old = [];
        foreach (array_merge(
            CompletarInformacionPacienteService::CAMPOS_PERSONA,
            CompletarInformacionPacienteService::CAMPOS_DIRECCION
        ) as $campo) {
            if (array_key_exists($campo, $_POST)) {
                $old[$campo] = trim((string) $_POST[$campo]);
            }
        }
        Session::setFlash('completar_info_old', $old);
        Session::set(
            'error',
            (string) (
                $resultado['mensaje']
                ?? 'Revisa los datos ingresados.'
            )
        );

        if ($clvPac === '') {
            Response::redirect('psicologo/pacientes');
        }

        Response::redirect(
            'psicologo/pacientes/ver/'
            . rawurlencode($clvPac)
            . '/completar-informacion?retorno='
            . rawurlencode(
                in_array($retorno, CompletarInformacionPacienteService::RETORNOS, true)
                    ? $retorno
                    : 'detalle'
            )
        );
    }

    Session::set(
        'success',
        (string) (
            $resultado['mensaje']
            ?? 'Información del paciente actualizada correctamente.'
        )
    );

    $fechaNacimientoPost = trim((string) ($_POST['FechaNacimiento'] ?? ''));
    if ($fechaNacimientoPost !== '') {
        $clasificacion = (new \App\Services\EdadService())
            ->validarFechaNacimiento($fechaNacimientoPost, 'paciente');

        if (
            !empty($clasificacion['ok'])
            && ($clasificacion['clasificacion'] ?? '')
                === \App\Services\EdadService::CLASIFICACION_MENOR
        ) {
            Session::set(
                'warning',
                \App\Services\EdadService::MENSAJE_ALTA_MENOR_PSICOLOGO
            );
        }
    }

    Response::redirect($rutaRetorno);
}

public function calendario(): void
{
    Response::redirect('psicologo/agenda');
}

public function agenda(): void
{
    $contexto = $this->obtenerContextoPsicologo();

    $clvPsi = $contexto['psicologo']['ClvPsi'];

    $pacienteModel = new Paciente();

    $pacientes = $pacienteModel->listarParaCrearCita(
        $clvPsi
    );

    $asignacionModel = new PsicologoServicio();

    $servicios = array_values(
        array_filter(
            $asignacionModel->listarPorPsicologo($clvPsi),
            static function (array $servicio): bool {
                return ($servicio['EstatusAsignacion'] ?? '') === 'ACTIVA'
                    && ($servicio['EstatusServicio'] ?? '') === 'ACTIVO';
            }
        )
    );

    $pacientePreseleccionado = trim(
        (string) ($_GET['paciente'] ?? '')
    );

    $errorPacienteAgenda = '';

    if ($pacientePreseleccionado !== '') {
        if (
            !$pacienteModel->perteneceAPsicologo(
                $pacientePreseleccionado,
                $clvPsi
            )
        ) {
            $errorPacienteAgenda =
                'No tienes autorización para crear una cita para este paciente.';
            $pacientePreseleccionado = '';
        }
    }

    $pendienteAgenda = (new PendienteClinicoService())->listarPendientesOperativos(
        (string) $clvPsi,
        (string) ($contexto['psicologo']['ClvCons'] ?? '')
    );

    $citasPendientesAsistencia = is_array(
        $pendienteAgenda['registrarAsistencia'] ?? null
    )
        ? $pendienteAgenda['registrarAsistencia']
        : [];

    $this->view(
        'psicologo/agenda',
        [
            'titulo' => 'Mi agenda',
            'usuario' => $this->usuario,
            'psicologo' => $contexto['psicologo'],
            'consultorio' => $contexto['consultorio'],
            'pacientes' => $pacientes,
            'servicios' => $servicios,
            'pacientePreseleccionado' => $pacientePreseleccionado,
            'errorPacienteAgenda' => $errorPacienteAgenda,
            'citasPendientesAsistencia' => $citasPendientesAsistencia,
            'cargarAgendaPsicologo' => true
        ],
        'psicologo'
    );
}

/**
 * Snapshot operativo en vivo (sin contenido clínico).
 * ClvPsi solo desde sesión.
 */
public function pendientesOperativosAgenda(): void
{
    $contexto = $this->obtenerContextoPsicologo();
    $clvPsi = (string) $contexto['psicologo']['ClvPsi'];
    $clvCons = (string) $contexto['psicologo']['ClvCons'];
    $clvCita = trim((string) ($_GET['clvCita'] ?? ''));

    // No aceptar ClvPsi ajeno por query/body.
    if (isset($_GET['ClvPsi']) || isset($_POST['ClvPsi'])) {
        $this->responderJsonAgenda([
            'ok' => false,
            'codigo' => 'PSI_NO_PERMITIDO',
            'mensaje' => 'No autorizado.'
        ], 403);
    }

    $snapshot = (new PendienteClinicoService())->obtenerSnapshotOperativo(
        $clvPsi,
        $clvCons,
        $clvCita !== '' ? $clvCita : null
    );

    $this->responderJsonAgenda($snapshot);
}

public function eventosAgenda(): void
{
    $contexto = $this->obtenerContextoPsicologo();

    $clvPsi = (string) $contexto['psicologo']['ClvPsi'];
    $clvCons = (string) $contexto['psicologo']['ClvCons'];

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

    $citas = $citaModel->obtenerPorPsicologo(
        $clvPsi,
        $estado !== '' ? $estado : null
    );

    $pendienteService = new PendienteClinicoService();
    $ahora = $pendienteService->ahoraMexico();

    $eventos = array_map(
        static function (array $cita) use (
            $clvPsi,
            $clvCons,
            $pendienteService,
            $ahora
        ): array {
            $estadoCita = strtoupper(
                trim((string) $cita['EstadoCita'])
            );

            $nombrePaciente = trim(
                (string) ($cita['NombrePaciente'] ?? '')
            );

            $nombreServicio = trim(
                (string) ($cita['NombreServicio'] ?? '')
            );

            $titulo = $nombrePaciente;

            if ($nombreServicio !== '') {
                $titulo .= ' · ' . $nombreServicio;
            }

            $eval = $pendienteService->evaluarCita(
                [
                    'ClvCita' => (string) ($cita['ClvCita'] ?? ''),
                    'ClvPac' => (string) ($cita['ClvPac'] ?? ''),
                    'ClvPsi' => $clvPsi,
                    'ClvCons' => $clvCons,
                    'EstadoCita' => $estadoCita,
                    'FechaCita' => (string) ($cita['FechaCita'] ?? ''),
                    'HraInicioCita' => (string) ($cita['HraInicioCita'] ?? '')
                ],
                $clvPsi,
                $clvCons,
                $ahora
            );

            $puedeRegistrarResultado = !empty($eval['puedeRegistrarAsistencia']);
            $urlClinica = $pendienteService->urlPublica($eval['rutaAccion']);
            $urlVerPaciente = $pendienteService->urlPublica(
                (string) ($eval['rutasSecundarias']['verPaciente'] ?? '')
            );
            $urlCompletarDatos = $pendienteService->urlPublica(
                (string) ($eval['rutasSecundarias']['completarDatos'] ?? '')
            );

            $mapaAccion = match ($eval['estado']) {
                PendienteClinicoService::REGISTRAR_ASISTENCIA => 'registrar_asistencia',
                PendienteClinicoService::HISTORIA_INICIAL_PENDIENTE => 'crear_historia',
                PendienteClinicoService::SEGUIMIENTO_PENDIENTE => 'registrar_seguimiento',
                PendienteClinicoService::DOCUMENTACION_COMPLETA => 'ver_seguimiento',
                default => ''
            };

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
                    'clvCita' => (string) ($cita['ClvCita'] ?? ''),
                    'clvPac' => (string) ($cita['ClvPac'] ?? ''),
                    'paciente' => $nombrePaciente,
                    'servicio' => $nombreServicio,
                    'estado' => $estadoCita,
                    'duracionMinutos' =>
                        (int) ($cita['DuracionAplicadaMin'] ?? 0),
                    'tarifaAplicada' =>
                        Helper::formatearMonedaMxn(
                            $cita['CostoAplicado'] ?? 0
                        ),
                    'puedeRegistrarResultado' => $puedeRegistrarResultado,
                    'estadoClinico' => $eval['estado'],
                    'mensajeClinico' => $eval['mensaje'],
                    'accionClinica' => $mapaAccion,
                    'etiquetaClinica' => $eval['etiquetaAccion'],
                    'urlClinica' => $urlClinica,
                    'urlVerPaciente' => $urlVerPaciente,
                    'urlCompletarDatos' => $urlCompletarDatos
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

public function registrarAsistenciaCita(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        $this->responderJsonAgenda([
            'ok' => false,
            'codigo' => 'METODO_INVALIDO',
            'mensaje' => 'La solicitud no es válida.'
        ], 405);
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        $this->responderJsonAgenda([
            'ok' => false,
            'codigo' => 'CSRF_INVALIDO',
            'mensaje' =>
                'La solicitud no es válida. Intenta nuevamente.'
        ], 403);
    }

    $contexto = $this->obtenerContextoPsicologo();
    $clvPsi = (string) $contexto['psicologo']['ClvPsi'];
    $clvCons = (string) $contexto['psicologo']['ClvCons'];

    $clvCita = trim((string) ($_POST['ClvCita'] ?? ''));
    $accion = strtoupper(trim((string) ($_POST['accion'] ?? '')));

    if ($clvCita === '') {
        $this->responderJsonAgenda([
            'ok' => false,
            'codigo' => 'CITA_INVALIDA',
            'mensaje' =>
                'No tienes autorización para modificar esta cita.'
        ], 400);
    }

    if (!in_array($accion, ['ASISTIDA', 'INASISTENCIA'], true)) {
        $this->responderJsonAgenda([
            'ok' => false,
            'codigo' => 'ACCION_INVALIDA',
            'mensaje' =>
                'Esta cita ya no puede cambiar de estado.'
        ], 400);
    }

    $citaModel = new Cita();

    $resultado = $citaModel->registrarResultadoPorPsicologo(
        $clvCita,
        $clvPsi,
        $clvCons,
        $accion
    );

    if (!$resultado['ok']) {
        $codigoHttp = match ($resultado['codigo'] ?? '') {
            'CSRF_INVALIDO', 'SIN_AUTORIZACION' => 403,
            'CITA_NO_INICIADA' => 409,
            'TRANSICION_NO_PERMITIDA', 'ACCION_INVALIDA' => 409,
            default => 400
        };

        $this->responderJsonAgenda([
            'ok' => false,
            'codigo' => (string) ($resultado['codigo'] ?? 'ERROR'),
            'mensaje' => (string) (
                $resultado['mensaje']
                ?? 'No fue posible actualizar la cita.'
            ),
            'estado' => $resultado['estado'] ?? null
        ], $codigoHttp);
    }

    /*
     * UPDATE + notificaciones ya confirmados en una sola transacción
     * dentro de Cita::registrarResultadoPorPsicologo.
     */
    $pendienteService = new PendienteClinicoService();
    $accionClinica = $pendienteService->resolverAccionClinicaAgenda(
        (string) ($resultado['clvPac'] ?? ''),
        $clvPsi,
        $clvCons,
        $clvCita,
        (string) ($resultado['estado'] ?? ''),
        (string) ($resultado['FechaCita'] ?? ''),
        (string) ($resultado['HraInicioCita'] ?? '')
    );

    $this->responderJsonAgenda([
        'ok' => true,
        'estado' => $resultado['estado'],
        'mensaje' => $resultado['mensaje'],
        'clvCita' => $clvCita,
        'estadoClinico' => $accionClinica['estado'] ?? '',
        'mensajeClinico' => $accionClinica['mensaje'] ?? '',
        'accionClinica' => $accionClinica['accion'],
        'etiquetaClinica' => $accionClinica['etiqueta'],
        'urlClinica' => $accionClinica['ruta'] !== ''
            ? Helper::baseUrl($accionClinica['ruta'])
            : '',
        'urlVerPaciente' => $pendienteService->urlPublica(
            (string) ($accionClinica['rutasSecundarias']['verPaciente'] ?? '')
        ),
        'urlCompletarDatos' => $pendienteService->urlPublica(
            (string) ($accionClinica['rutasSecundarias']['completarDatos'] ?? '')
        )
    ]);
}

public function horariosDisponiblesAgenda(): void
{
    $contexto = $this->obtenerContextoPsicologo();

    $clvPsi = $contexto['psicologo']['ClvPsi'];
    $clvServ = trim($_GET['servicio'] ?? '');
    $fecha = trim($_GET['fecha'] ?? '');

    $validacionFecha = $this->validarFechaAgenda($fecha);

    if (!$validacionFecha['ok']) {
        $this->responderJsonAgenda($validacionFecha, 400);
    }

    $agendaService = new AgendaService();

    $resultado = $agendaService->calcularEspaciosDisponibles(
        $clvPsi,
        $clvServ,
        $fecha
    );

    if (!$resultado['ok']) {
        $this->responderJsonAgenda([
            'ok' => false,
            'codigo' => 'DISPONIBILIDAD_ERROR',
            'mensaje' => $resultado['mensaje'] ?? 'No fue posible consultar los horarios.'
        ], 400);
    }

    $this->responderJsonAgenda($resultado);
}

public function guardarCitaAgenda(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        if ($this->esPeticionAjaxAgenda()) {
            $this->responderJsonAgenda([
                'ok' => false,
                'codigo' => 'METODO_INVALIDO',
                'mensaje' => 'La solicitud no es válida.'
            ], 405);
        }

        Response::redirect('psicologo/agenda');
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        if ($this->esPeticionAjaxAgenda()) {
            $this->responderJsonAgenda([
                'ok' => false,
                'codigo' => 'CSRF_INVALIDO',
                'mensaje' => 'La solicitud no es válida. Intenta nuevamente.'
            ], 403);
        }

        $_SESSION['error'] =
            'La solicitud no es válida. Intenta nuevamente.';

        Response::redirect('psicologo/agenda');
    }

    $contexto = $this->obtenerContextoPsicologo();

    $clvPsi = $contexto['psicologo']['ClvPsi'];

    $clvPac = trim($_POST['paciente'] ?? '');
    $clvServ = trim($_POST['servicio'] ?? '');
    $fechaCita = trim($_POST['fecha'] ?? '');
    $hraInicioCita = trim($_POST['hora'] ?? '');

    if (
        $clvPac === '' ||
        $clvServ === '' ||
        $fechaCita === '' ||
        $hraInicioCita === ''
    ) {
        $respuesta = [
            'ok' => false,
            'codigo' => 'DATOS_INCOMPLETOS',
            'mensaje' =>
                'Completa todos los campos para registrar la cita.'
        ];

        if ($this->esPeticionAjaxAgenda()) {
            $this->responderJsonAgenda($respuesta, 400);
        }

        $_SESSION['error'] = $respuesta['mensaje'];

        Response::redirect('psicologo/agenda');
    }

    $validacionFechaHora = $this->validarFechaHoraAgenda(
        $fechaCita,
        $hraInicioCita
    );

    if (!$validacionFechaHora['ok']) {
        if ($this->esPeticionAjaxAgenda()) {
            $this->responderJsonAgenda(
                $validacionFechaHora,
                400
            );
        }

        $_SESSION['error'] =
            $validacionFechaHora['mensaje'];

        Response::redirect('psicologo/agenda');
    }

    $citaModel = new Cita();

    if (
        !$citaModel->pacientePerteneceAPsicologo(
            $clvPac,
            $clvPsi
        )
    ) {
        $respuesta = [
            'ok' => false,
            'codigo' => 'PACIENTE_NO_PERMITIDO',
            'mensaje' =>
                'No puedes agendar citas para ese paciente.'
        ];

        if ($this->esPeticionAjaxAgenda()) {
            $this->responderJsonAgenda($respuesta, 403);
        }

        $_SESSION['error'] = $respuesta['mensaje'];

        Response::redirect('psicologo/agenda');
    }

    $agendaService = new AgendaService();

    try {
        $citaModel->beginTransaccion();

        if (
            !$citaModel->bloquearPsicologoParaReserva($clvPsi)
        ) {
            throw new RuntimeException(
                'No fue posible validar tu disponibilidad.'
            );
        }

        $revalidacionFechaHora = $this->validarFechaHoraAgenda(
            $fechaCita,
            $hraInicioCita
        );

        if (!$revalidacionFechaHora['ok']) {
            $citaModel->rollbackTransaccion();

            if ($this->esPeticionAjaxAgenda()) {
                $this->responderJsonAgenda(
                    $revalidacionFechaHora,
                    400
                );
            }

            $_SESSION['error'] =
                $revalidacionFechaHora['mensaje'];

            Response::redirect('psicologo/agenda');
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

        if ($datosReserva['ClvPsi'] !== $clvPsi) {
            throw new RuntimeException(
                'No fue posible validar la cita.'
            );
        }

        if (
            $citaModel->existeSolapamientoProgramado(
                $datosReserva['ClvPsi'],
                $datosReserva['FechaCita'],
                $datosReserva['HraInicioCita'],
                $datosReserva['HraFinCita']
            )
        ) {
            throw new RuntimeException(
                'El horario seleccionado ya no está disponible.'
            );
        }

        $datosReserva['ClvCita'] =
            $citaModel->generarClaveCita();
        $datosReserva['ClvPac'] = $clvPac;

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
                ->notificarCitaCreadaPorPsicologo($clvCitaCreada);
        } catch (\Throwable $e) {
            // La cita ya quedó confirmada; el aviso es auxiliar.
        }

        if ($this->esPeticionAjaxAgenda()) {
            $this->responderJsonAgenda([
                'ok' => true,
                'mensaje' => $mensajeExito
            ]);
        }

        $_SESSION['success'] = $mensajeExito;
    } catch (PDOException $e) {
        $citaModel->rollbackTransaccion();

        $mensaje = (int) ($e->errorInfo[1] ?? 0) === 1062
            ? 'El horario seleccionado ya no está disponible.'
            : 'No fue posible registrar la cita.';

        if ($this->esPeticionAjaxAgenda()) {
            $this->responderJsonAgenda([
                'ok' => false,
                'codigo' => 'ERROR_GUARDADO',
                'mensaje' => $mensaje
            ], 500);
        }

        $_SESSION['error'] = $mensaje;
    } catch (RuntimeException $e) {
        $citaModel->rollbackTransaccion();

        if ($this->esPeticionAjaxAgenda()) {
            $this->responderJsonAgenda([
                'ok' => false,
                'codigo' => 'VALIDACION',
                'mensaje' => $e->getMessage()
            ], 400);
        }

        $_SESSION['error'] = $e->getMessage();
    } catch (\Throwable $e) {
        $citaModel->rollbackTransaccion();

        if ($this->esPeticionAjaxAgenda()) {
            $this->responderJsonAgenda([
                'ok' => false,
                'codigo' => 'ERROR_INTERNO',
                'mensaje' =>
                    'No fue posible registrar la cita.'
            ], 500);
        }

        $_SESSION['error'] =
            'No fue posible registrar la cita.';
    }

    Response::redirect('psicologo/agenda');
}

private function esPeticionAjaxAgenda(): bool
{
    return strtolower(
        trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''))
    ) === 'xmlhttprequest';
}

private function responderJsonAgenda(
    array $datos,
    int $codigoHttp = 200
): void {
    http_response_code($codigoHttp);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

private function validarFechaAgenda(string $fecha): array
{
    $fecha = trim($fecha);

    if ($fecha === '') {
        return [
            'ok' => false,
            'codigo' => 'FECHA_REQUERIDA',
            'mensaje' =>
                'Selecciona una fecha para consultar los horarios disponibles.'
        ];
    }

    $fechaObj = \DateTimeImmutable::createFromFormat(
        'Y-m-d',
        $fecha
    );

    if (
        !$fechaObj ||
        $fechaObj->format('Y-m-d') !== $fecha
    ) {
        return [
            'ok' => false,
            'codigo' => 'FECHA_INVALIDA',
            'mensaje' => 'La fecha seleccionada no es válida.'
        ];
    }

    $hoy = new \DateTimeImmutable('today');

    if ($fechaObj < $hoy) {
        return [
            'ok' => false,
            'codigo' => 'FECHA_PASADA',
            'mensaje' =>
                'No puedes programar una cita en una fecha anterior.'
        ];
    }

    return ['ok' => true];
}

private function validarFechaHoraAgenda(
    string $fecha,
    string $hora
): array {
    $validacionFecha = $this->validarFechaAgenda($fecha);

    if (!$validacionFecha['ok']) {
        return $validacionFecha;
    }

    $hora = trim($hora);

    if ($hora === '') {
        return [
            'ok' => false,
            'codigo' => 'HORA_REQUERIDA',
            'mensaje' => 'Selecciona un horario disponible.'
        ];
    }

    if (strlen($hora) === 5) {
        $hora .= ':00';
    }

    $hora = substr($hora, 0, 8);

    $fechaObj = \DateTimeImmutable::createFromFormat(
        'Y-m-d',
        trim($fecha)
    );

    $inicioCita = \DateTimeImmutable::createFromFormat(
        'Y-m-d H:i:s',
        $fechaObj->format('Y-m-d') . ' ' . $hora
    );

    if (!$inicioCita) {
        return [
            'ok' => false,
            'codigo' => 'HORA_INVALIDA',
            'mensaje' => 'El horario seleccionado no es válido.'
        ];
    }

    $hoy = new \DateTimeImmutable('today');

    if ($fechaObj->format('Y-m-d') === $hoy->format('Y-m-d')) {
        $ahora = new \DateTimeImmutable('now');

        if ($inicioCita <= $ahora) {
            return [
                'ok' => false,
                'codigo' => 'HORA_PASADA',
                'mensaje' =>
                    'Ese horario ya transcurrió. Selecciona una hora futura.'
            ];
        }
    }

    return ['ok' => true];
}

private function validarCsrfAgendaPsicologo(): void
{
    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        if ($this->esPeticionAjaxAgenda()) {
            $this->responderJsonAgenda([
                'ok' => false,
                'codigo' => 'CSRF_INVALIDO',
                'mensaje' =>
                    'La solicitud no es válida. Intenta nuevamente.'
            ], 403);
        }

        $_SESSION['error'] =
            'La solicitud no es válida. Intenta nuevamente.';

        Response::redirect('psicologo/agenda');
    }
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

    if (($consultorio['EstatusCons'] ?? '') !== 'ACTIVO') {
        Session::destroy();
        Response::redirect('login');
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
            'redesProfesionales' => (new RedSocialPsicologo())
                ->listarPorPsicologo((string) $contexto['psicologo']['ClvPsi']),
            'plataformasRed' => RedSocialUrlValidator::PLATAFORMAS,
            'errores' => []
        ],
        'psicologo'
    );
}

public function guardarRedProfesional(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::redirect('psicologo/perfil#redes-profesionales');
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        Session::set('error', 'La solicitud no es válida. Intenta nuevamente.');
        Response::redirect('psicologo/perfil#redes-profesionales');
    }

    $contexto = $this->obtenerContextoPsicologo();
    $clvPsi = (string) $contexto['psicologo']['ClvPsi'];
    $resultado = (new RedSocialService())->crearParaPsicologo($clvPsi, $_POST);
    Session::set($resultado['ok'] ? 'success' : 'error', $resultado['mensaje']);
    Response::redirect('psicologo/perfil#redes-profesionales');
}

public function actualizarRedProfesional(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::redirect('psicologo/perfil#redes-profesionales');
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        Session::set('error', 'La solicitud no es válida. Intenta nuevamente.');
        Response::redirect('psicologo/perfil#redes-profesionales');
    }

    $contexto = $this->obtenerContextoPsicologo();
    $clvPsi = (string) $contexto['psicologo']['ClvPsi'];
    $id = (int) ($_POST['idRedSocialPsi'] ?? 0);
    $resultado = (new RedSocialService())->actualizarParaPsicologo(
        $clvPsi,
        $id,
        $_POST
    );
    Session::set($resultado['ok'] ? 'success' : 'error', $resultado['mensaje']);
    Response::redirect('psicologo/perfil#redes-profesionales');
}

public function cambiarEstadoRedProfesional(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::redirect('psicologo/perfil#redes-profesionales');
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        Session::set('error', 'La solicitud no es válida. Intenta nuevamente.');
        Response::redirect('psicologo/perfil#redes-profesionales');
    }

    $contexto = $this->obtenerContextoPsicologo();
    $resultado = (new RedSocialService())->cambiarEstadoPsicologo(
        (string) $contexto['psicologo']['ClvPsi'],
        (int) ($_POST['idRedSocialPsi'] ?? 0),
        (string) ($_POST['accion'] ?? '')
    );
    Session::set($resultado['ok'] ? 'success' : 'error', $resultado['mensaje']);
    Response::redirect('psicologo/perfil#redes-profesionales');
}

public function expediente(): void
{
    Response::redirect('psicologo/expedientes');
}

public function expedientes(): void
{
    $contexto = $this->obtenerContextoPsicologo();
    $clvPsi = (string) $contexto['psicologo']['ClvPsi'];

    $q = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($q, 'UTF-8') > 80) {
        $q = mb_substr($q, 0, 80, 'UTF-8');
    }

    $actividad = strtoupper(trim((string) ($_GET['actividad'] ?? 'TODOS')));
    $cita = strtoupper(trim((string) ($_GET['cita'] ?? 'TODOS')));
    $pendiente = strtoupper(trim((string) ($_GET['pendiente'] ?? 'TODOS')));
    $orden = strtoupper(trim((string) ($_GET['orden'] ?? 'NOMBRE_ASC')));
    $pagina = max(1, (int) ($_GET['pagina'] ?? 1));

    $actividades = ['TODOS', 'ACTIVIDAD_RECIENTE', 'SIN_ACTIVIDAD_RECIENTE'];
    $citas = ['TODOS', 'CON_CITA_PROXIMA', 'SIN_CITA_PROXIMA'];
    $pendientes = ['TODOS', 'CON_PENDIENTE', 'SIN_PENDIENTE'];
    $ordenes = [
        'NOMBRE_ASC',
        'NOMBRE_DESC',
        'ACTIVIDAD_RECIENTE',
        'ACTIVIDAD_ANTIGUA'
    ];

    if (!in_array($actividad, $actividades, true)) {
        $actividad = 'TODOS';
    }
    if (!in_array($cita, $citas, true)) {
        $cita = 'TODOS';
    }
    if (!in_array($pendiente, $pendientes, true)) {
        $pendiente = 'TODOS';
    }
    if (!in_array($orden, $ordenes, true)) {
        $orden = 'NOMBRE_ASC';
    }

    $pacienteModel = new Paciente();
    $errorCarga = false;
    $catalogo = [
        'items' => [],
        'total' => 0,
        'pagina' => 1,
        'porPagina' => 12,
        'totalPaginas' => 1,
        'desde' => 0,
        'hasta' => 0
    ];
    $resumen = [
        'total' => 0,
        'conCitaProxima' => 0,
        'conPendiente' => 0,
        'actividadReciente' => 0
    ];

    try {
        $catalogo = $pacienteModel->listarCatalogoExpedientes($clvPsi, [
            'q' => $q,
            'actividad' => $actividad,
            'cita' => $cita,
            'pendiente' => $pendiente,
            'orden' => $orden,
            'pagina' => $pagina,
            'porPagina' => 12
        ]);
        $resumen = $pacienteModel->resumenCatalogoExpedientes($clvPsi);
    } catch (\Throwable $e) {
        error_log('PsicologoController::expedientes: ' . $e->getMessage());
        $errorCarga = true;
    }

    $filtrosActivos = $q !== ''
        || $actividad !== 'TODOS'
        || $cita !== 'TODOS'
        || $pendiente !== 'TODOS'
        || $orden !== 'NOMBRE_ASC';

    $this->view(
        'psicologo/expedientes/index',
        [
            'titulo' => 'Expedientes',
            'usuario' => $this->usuario,
            'psicologo' => $contexto['psicologo'],
            'consultorio' => $contexto['consultorio'],
            'expedientes' => $catalogo['items'],
            'totalExpedientes' => $catalogo['total'],
            'paginaActual' => $catalogo['pagina'],
            'porPagina' => $catalogo['porPagina'],
            'totalPaginas' => $catalogo['totalPaginas'],
            'desde' => $catalogo['desde'],
            'hasta' => $catalogo['hasta'],
            'resumen' => $resumen,
            'q' => $q,
            'filtroActividad' => $actividad,
            'filtroCita' => $cita,
            'filtroPendiente' => $pendiente,
            'orden' => $orden,
            'filtrosActivos' => $filtrosActivos,
            'errorCarga' => $errorCarga,
            'cargarExpedientesPsicologo' => true
        ],
        'psicologo'
    );
}

public function expedientePaciente(string $clvPac): void
{
    $contexto = $this->obtenerContextoPsicologo();
    $clvPsi = (string) $contexto['psicologo']['ClvPsi'];
    $clvCons = (string) $contexto['psicologo']['ClvCons'];
    $clvPac = trim($clvPac);

    $servicio = new ExpedienteClinicoService();
    $resultado = $servicio->obtenerExpediente(
        $clvPac,
        $clvPsi,
        $clvCons
    );

    if (!$resultado['ok']) {
        Session::set(
            'error',
            (string) ($resultado['mensaje'] ??
                'No autorizado.')
        );
        Response::redirect('psicologo/pacientes');
    }

    $tab = strtolower(trim((string) ($_GET['tab'] ?? 'ficha')));
    $tabsPermitidas = ['ficha', 'historia', 'seguimiento'];

    if (!in_array($tab, $tabsPermitidas, true)) {
        $tab = 'ficha';
    }

    $pendientePaciente = (new PendienteClinicoService())->evaluarPaciente(
        $clvPac,
        $clvPsi,
        $clvCons
    );

    $this->view(
        'psicologo/expediente/index',
        [
            'titulo' => 'Expediente clínico',
            'usuario' => $this->usuario,
            'psicologo' => $contexto['psicologo'],
            'consultorio' => $contexto['consultorio'],
            'paciente' => $resultado['paciente'],
            'historial' => $resultado['historial'],
            'completo' => $resultado['completo'],
            'citaHabilitadora' => $resultado['citaHabilitadora'],
            'infoPersonalIncompleta' => !empty($pendientePaciente['infoPersonalIncompleta']),
            'citasRegistrarAsistencia' =>
                $pendientePaciente['citasRegistrarAsistencia'] ?? [],
            'seguimientoPendienteOperativo' =>
                !empty($pendientePaciente['seguimientoPendiente']),
            'historiaPendienteOperativa' =>
                !empty($pendientePaciente['historiaPendiente']),
            'puedeCrear' => $resultado['puedeCrear'],
            'puedeEditar' => $resultado['puedeEditar'],
            'puedeRegistrarSeguimiento' =>
                $resultado['puedeRegistrarSeguimiento'] ?? false,
            'seguimientos' => $resultado['seguimientos'] ?? [],
            'totalSeguimientos' => $resultado['totalSeguimientos'] ?? 0,
            'tieneConsentimientoPrivacidad' =>
                $resultado['tieneConsentimientoPrivacidad'] ?? true,
            'mensajeConsentimiento' =>
                $resultado['mensajeConsentimiento'] ?? null,
            'tabActiva' => $tab,
            'cargarExpedientePsicologo' => true,
            'mensajeExito' => Session::getFlash('success'),
            'mensajeError' => Session::getFlash('error')
        ],
        'psicologo'
    );
}

public function crearHistoriaClinica(string $clvPac): void
{
    $contexto = $this->obtenerContextoPsicologo();
    $clvPsi = (string) $contexto['psicologo']['ClvPsi'];
    $clvCons = (string) $contexto['psicologo']['ClvCons'];
    $clvPac = trim($clvPac);

    $servicio = new ExpedienteClinicoService();
    $resultado = $servicio->prepararCreacion(
        $clvPac,
        $clvPsi,
        $clvCons
    );

    if (!$resultado['ok']) {
        Session::set(
            'error',
            (string) ($resultado['mensaje'] ??
                'No se puede crear la historia clínica.')
        );
        Response::redirect(
            'psicologo/pacientes/ver/' .
            rawurlencode($clvPac) .
            '/expediente?tab=historia'
        );
    }

    $infoPersonal = (new CompletarInformacionPacienteService())->evaluarFaltantes(
        $clvPac,
        $clvPsi,
        $clvCons
    );

    $this->view(
        'psicologo/expediente/historia-form',
        [
            'titulo' => 'Nueva historia clínica',
            'usuario' => $this->usuario,
            'psicologo' => $contexto['psicologo'],
            'consultorio' => $contexto['consultorio'],
            'paciente' => $resultado['paciente'],
            'citaHabilitadora' => $resultado['citaHabilitadora'],
            'modo' => 'crear',
            'completo' => null,
            'historial' => null,
            'cargarExpedientePsicologo' => true,
            'mensajeError' => Session::getFlash('error'),
            'mensajeExito' => Session::getFlash('success'),
            'datosFormulario' => Session::getFlash('historia_form_datos'),
            'pasoInicial' => (int) (Session::getFlash('historia_form_paso') ?? 1),
            'infoPersonalIncompleta' => !empty($infoPersonal['tieneFaltantes']),
            'faltantesPersonales' => $infoPersonal['faltantes'] ?? [
                'persona' => [],
                'direccion' => []
            ]
        ],
        'psicologo'
    );
}

public function guardarHistoriaClinica(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::redirect('psicologo/pacientes');
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        Session::set(
            'error',
            'La solicitud no es válida. Intenta nuevamente.'
        );
        Response::redirect('psicologo/pacientes');
    }

    $contexto = $this->obtenerContextoPsicologo();
    $clvPsi = (string) $contexto['psicologo']['ClvPsi'];
    $clvCons = (string) $contexto['psicologo']['ClvCons'];
    $clvPac = trim((string) ($_POST['ClvPac'] ?? ''));

    $servicio = new ExpedienteClinicoService();
    $resultado = $servicio->guardarHistoriaInicial(
        $clvPsi,
        $clvCons,
        $_POST
    );

    if (!$resultado['ok']) {
        Session::set(
            'error',
            (string) ($resultado['mensaje'] ??
                'No se pudo guardar.')
        );
        Session::setFlash(
            'historia_form_datos',
            $this->sanitizarPostHistoriaClinica($_POST)
        );
        Session::setFlash(
            'historia_form_paso',
            (int) ($_POST['_paso_actual'] ?? 1)
        );
        Response::redirect(
            'psicologo/pacientes/ver/' .
            rawurlencode($clvPac) .
            '/historia/nueva'
        );
    }

    Session::set(
        'success',
        (string) ($resultado['mensaje'] ??
            'Historia clínica guardada.')
    );

    Response::redirect(
        'psicologo/pacientes/ver/' .
        rawurlencode((string) ($resultado['clvPac'] ?? $clvPac)) .
        '/expediente?tab=historia'
    );
}

public function editarHistoriaClinica(string $clvHist): void
{
    $contexto = $this->obtenerContextoPsicologo();
    $clvPsi = (string) $contexto['psicologo']['ClvPsi'];
    $clvCons = (string) $contexto['psicologo']['ClvCons'];
    $clvHist = trim($clvHist);

    $servicio = new ExpedienteClinicoService();
    $resultado = $servicio->prepararEdicion(
        $clvHist,
        $clvPsi,
        $clvCons
    );

    if (!$resultado['ok']) {
        Session::set(
            'error',
            (string) ($resultado['mensaje'] ??
                'No autorizado.')
        );
        Response::redirect('psicologo/pacientes');
    }

    $clvPacEdicion = trim((string) ($resultado['paciente']['ClvPac'] ?? ''));
    $infoPersonal = $clvPacEdicion !== ''
        ? (new CompletarInformacionPacienteService())->evaluarFaltantes(
            $clvPacEdicion,
            $clvPsi,
            $clvCons
        )
        : ['tieneFaltantes' => false, 'faltantes' => ['persona' => [], 'direccion' => []]];

    $this->view(
        'psicologo/expediente/historia-form',
        [
            'titulo' => 'Editar historia clínica',
            'usuario' => $this->usuario,
            'psicologo' => $contexto['psicologo'],
            'consultorio' => $contexto['consultorio'],
            'paciente' => $resultado['paciente'],
            'citaHabilitadora' => null,
            'modo' => 'editar',
            'completo' => $resultado['completo'],
            'historial' => $resultado['historial'],
            'cargarExpedientePsicologo' => true,
            'mensajeError' => Session::getFlash('error'),
            'mensajeExito' => Session::getFlash('success'),
            'datosFormulario' => Session::getFlash('historia_form_datos'),
            'pasoInicial' => (int) (Session::getFlash('historia_form_paso') ?? 1),
            'infoPersonalIncompleta' => !empty($infoPersonal['tieneFaltantes']),
            'faltantesPersonales' => $infoPersonal['faltantes'] ?? [
                'persona' => [],
                'direccion' => []
            ]
        ],
        'psicologo'
    );
}

public function actualizarHistoriaClinica(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::redirect('psicologo/pacientes');
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        Session::set(
            'error',
            'La solicitud no es válida. Intenta nuevamente.'
        );
        Response::redirect('psicologo/pacientes');
    }

    $contexto = $this->obtenerContextoPsicologo();
    $clvPsi = (string) $contexto['psicologo']['ClvPsi'];
    $clvCons = (string) $contexto['psicologo']['ClvCons'];
    $clvHist = trim((string) ($_POST['ClvHist'] ?? ''));

    $servicio = new ExpedienteClinicoService();
    $resultado = $servicio->actualizarHistoriaInicial(
        $clvPsi,
        $clvCons,
        $_POST
    );

    if (!$resultado['ok']) {
        Session::set(
            'error',
            (string) ($resultado['mensaje'] ??
                'No se pudo actualizar.')
        );
        Session::setFlash(
            'historia_form_datos',
            $this->sanitizarPostHistoriaClinica($_POST)
        );
        Session::setFlash(
            'historia_form_paso',
            (int) ($_POST['_paso_actual'] ?? 1)
        );
        Response::redirect(
            'psicologo/pacientes/historia/editar/' .
            rawurlencode($clvHist)
        );
    }

    Session::set(
        'success',
        (string) ($resultado['mensaje'] ??
            'Historia clínica actualizada.')
    );

    Response::redirect(
        'psicologo/pacientes/ver/' .
        rawurlencode((string) ($resultado['clvPac'] ?? '')) .
        '/expediente?tab=historia'
    );
}

public function nuevoSeguimiento(string $clvHist): void
{
    $contexto = $this->obtenerContextoPsicologo();
    $clvPsi = (string) $contexto['psicologo']['ClvPsi'];
    $clvCons = (string) $contexto['psicologo']['ClvCons'];
    $clvHist = trim($clvHist);

    $servicio = new ExpedienteClinicoService();
    $resultado = $servicio->prepararNuevoSeguimiento(
        $clvHist,
        $clvPsi,
        $clvCons
    );

    if (!$resultado['ok']) {
        Session::set(
            'error',
            (string) ($resultado['mensaje'] ??
                'No se puede registrar el seguimiento.')
        );

        $historial = (new \App\Models\HistorialClinico())->obtenerPorClave(
            $clvHist
        );
        $clvPac = (string) ($historial['ClvPac'] ?? '');

        if ($clvPac !== '') {
            Response::redirect(
                'psicologo/pacientes/ver/' .
                rawurlencode($clvPac) .
                '/expediente?tab=seguimiento'
            );
        }

        Response::redirect('psicologo/pacientes');
    }

    $this->view(
        'psicologo/expediente/seguimiento-form',
        [
            'titulo' => 'Nueva sesión de seguimiento',
            'usuario' => $this->usuario,
            'psicologo' => $contexto['psicologo'],
            'consultorio' => $contexto['consultorio'],
            'paciente' => $resultado['paciente'],
            'historial' => $resultado['historial'],
            'citasPendientes' => $resultado['citasPendientes'],
            'modo' => 'crear',
            'completo' => null,
            'cargarExpedientePsicologo' => true,
            'mensajeError' => Session::getFlash('error')
        ],
        'psicologo'
    );
}

public function guardarSeguimiento(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::redirect('psicologo/pacientes');
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        Session::set(
            'error',
            'La solicitud no es válida. Intenta nuevamente.'
        );
        Response::redirect('psicologo/pacientes');
    }

    $contexto = $this->obtenerContextoPsicologo();
    $clvPsi = (string) $contexto['psicologo']['ClvPsi'];
    $clvCons = (string) $contexto['psicologo']['ClvCons'];
    $clvHist = trim((string) ($_POST['ClvHist'] ?? ''));

    $servicio = new ExpedienteClinicoService();
    $resultado = $servicio->guardarSeguimiento(
        $clvPsi,
        $clvCons,
        $_POST
    );

    if (!$resultado['ok']) {
        Session::set(
            'error',
            (string) ($resultado['mensaje'] ??
                'No se pudo guardar el seguimiento.')
        );
        Response::redirect(
            'psicologo/expediente/' .
            rawurlencode($clvHist) .
            '/seguimientos/nuevo'
        );
    }

    Session::set(
        'success',
        (string) ($resultado['mensaje'] ??
            'El seguimiento de la sesión se guardó correctamente.')
    );

    Response::redirect(
        'psicologo/pacientes/ver/' .
        rawurlencode((string) ($resultado['clvPac'] ?? '')) .
        '/expediente?tab=seguimiento'
    );
}

public function verSeguimiento(string $clvSeg): void
{
    $contexto = $this->obtenerContextoPsicologo();
    $clvPsi = (string) $contexto['psicologo']['ClvPsi'];
    $clvCons = (string) $contexto['psicologo']['ClvCons'];

    $servicio = new ExpedienteClinicoService();
    $resultado = $servicio->obtenerSeguimientoAutorizado(
        trim($clvSeg),
        $clvPsi,
        $clvCons
    );

    if (!$resultado['ok']) {
        Session::set(
            'error',
            (string) ($resultado['mensaje'] ??
                'No tienes autorización para acceder a este seguimiento.')
        );
        Response::redirect('psicologo/pacientes');
    }

    $this->view(
        'psicologo/expediente/seguimiento-ver',
        [
            'titulo' => 'Detalle de seguimiento',
            'usuario' => $this->usuario,
            'psicologo' => $contexto['psicologo'],
            'consultorio' => $contexto['consultorio'],
            'paciente' => $resultado['paciente'],
            'historial' => $resultado['historial'],
            'completo' => $resultado['completo'],
            'cargarExpedientePsicologo' => true
        ],
        'psicologo'
    );
}

public function editarSeguimiento(string $clvSeg): void
{
    $contexto = $this->obtenerContextoPsicologo();
    $clvPsi = (string) $contexto['psicologo']['ClvPsi'];
    $clvCons = (string) $contexto['psicologo']['ClvCons'];

    $servicio = new ExpedienteClinicoService();
    $resultado = $servicio->obtenerSeguimientoAutorizado(
        trim($clvSeg),
        $clvPsi,
        $clvCons
    );

    if (!$resultado['ok']) {
        Session::set(
            'error',
            (string) ($resultado['mensaje'] ??
                'No tienes autorización para acceder a este seguimiento.')
        );
        Response::redirect('psicologo/pacientes');
    }

    $this->view(
        'psicologo/expediente/seguimiento-form',
        [
            'titulo' => 'Editar seguimiento',
            'usuario' => $this->usuario,
            'psicologo' => $contexto['psicologo'],
            'consultorio' => $contexto['consultorio'],
            'paciente' => $resultado['paciente'],
            'historial' => $resultado['historial'],
            'citasPendientes' => [],
            'modo' => 'editar',
            'completo' => $resultado['completo'],
            'cargarExpedientePsicologo' => true,
            'mensajeError' => Session::getFlash('error')
        ],
        'psicologo'
    );
}

public function actualizarSeguimiento(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::redirect('psicologo/pacientes');
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        Session::set(
            'error',
            'La solicitud no es válida. Intenta nuevamente.'
        );
        Response::redirect('psicologo/pacientes');
    }

    $contexto = $this->obtenerContextoPsicologo();
    $clvPsi = (string) $contexto['psicologo']['ClvPsi'];
    $clvCons = (string) $contexto['psicologo']['ClvCons'];
    $clvSeg = trim((string) ($_POST['ClvSeg'] ?? ''));

    $servicio = new ExpedienteClinicoService();
    $resultado = $servicio->actualizarSeguimiento(
        $clvPsi,
        $clvCons,
        $_POST
    );

    if (!$resultado['ok']) {
        Session::set(
            'error',
            (string) ($resultado['mensaje'] ??
                'No se pudo actualizar el seguimiento.')
        );
        Response::redirect(
            'psicologo/expediente/seguimientos/editar/' .
            rawurlencode($clvSeg)
        );
    }

    Session::set(
        'success',
        (string) ($resultado['mensaje'] ??
            'El seguimiento de la sesión se guardó correctamente.')
    );

    Response::redirect(
        'psicologo/pacientes/ver/' .
        rawurlencode((string) ($resultado['clvPac'] ?? '')) .
        '/expediente?tab=seguimiento'
    );
}

public function configuracion(): void
{
    $contexto = $this->obtenerContextoPsicologo();
    $cuentaService = new CuentaService();

    $this->usuario = is_array(Session::get('usuario'))
        ? Session::get('usuario')
        : $this->usuario;

    $toastTipo = (string) (Session::getFlash('config_toast_tipo') ?? '');
    $toastMensaje = (string) (Session::getFlash(
        'config_toast_mensaje'
    ) ?? '');

    $this->view(
        'psicologo/configuracion',
        [
            'titulo' => 'Configuración',
            'usuario' => $this->usuario,
            'psicologo' => $contexto['psicologo'],
            'consultorio' => $contexto['consultorio'],
            'solicitudCorreo' => $cuentaService->obtenerSolicitudPendienteVista(
                (string) ($this->usuario['ClvUsu'] ?? '')
            ),
            'toastTipo' => $toastTipo,
            'toastMensaje' => $toastMensaje,
            'cargarConfiguracionPsicologo' => true
        ],
        'psicologo'
    );
}

public function cambiarContrasenaConfiguracion(): void
{
    $this->exigirPostConfiguracionPsicologo();
    $this->obtenerContextoPsicologo();

    $resultado = (new CuentaService())->cambiarContrasena(
        trim((string) ($this->usuario['ClvUsu'] ?? '')),
        (string) ($_POST['contrasena_actual'] ?? ''),
        (string) ($_POST['nueva_contrasena'] ?? ''),
        (string) ($_POST['confirmar_contrasena'] ?? '')
    );

    $this->flashConfiguracion(
        $resultado['ok'] ? 'success' : 'error',
        $resultado['mensaje']
    );

    Response::redirect('psicologo/configuracion');
}

public function actualizarTelefonoConfiguracion(): void
{
    $this->exigirPostConfiguracionPsicologo();
    $this->obtenerContextoPsicologo();

    $resultado = (new CuentaService())->actualizarTelefono(
        trim((string) ($this->usuario['ClvUsu'] ?? '')),
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

    Response::redirect('psicologo/configuracion');
}

public function solicitarCambioCorreo(): void
{
    $this->exigirPostConfiguracionPsicologo();
    $this->obtenerContextoPsicologo();

    $nombre = trim(
        ((string) ($this->usuario['NombrePer'] ?? '')) . ' ' .
        ((string) ($this->usuario['ApPatPer'] ?? ''))
    );

    $resultado = (new CuentaService())->solicitarCambioCorreo(
        trim((string) ($this->usuario['ClvUsu'] ?? '')),
        (string) ($_POST['correo_nuevo'] ?? ''),
        (string) ($_POST['contrasena_actual'] ?? ''),
        $nombre !== '' ? $nombre : 'Especialista'
    );

    $this->flashConfiguracion(
        $resultado['ok'] ? 'success' : 'error',
        $resultado['mensaje']
    );

    Response::redirect('psicologo/configuracion');
}

public function verificarCambioCorreo(): void
{
    $this->exigirPostConfiguracionPsicologo();
    $this->obtenerContextoPsicologo();

    $resultado = (new CuentaService())->verificarCambioCorreo(
        trim((string) ($this->usuario['ClvUsu'] ?? '')),
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

    Response::redirect('psicologo/configuracion');
}

public function reenviarCodigoCorreo(): void
{
    $this->exigirPostConfiguracionPsicologo();
    $this->obtenerContextoPsicologo();

    $nombre = trim(
        ((string) ($this->usuario['NombrePer'] ?? '')) . ' ' .
        ((string) ($this->usuario['ApPatPer'] ?? ''))
    );

    $resultado = (new CuentaService())->reenviarCodigoCorreo(
        trim((string) ($this->usuario['ClvUsu'] ?? '')),
        $nombre !== '' ? $nombre : 'Especialista'
    );

    $this->flashConfiguracion(
        $resultado['ok'] ? 'success' : 'error',
        $resultado['mensaje']
    );

    Response::redirect('psicologo/configuracion');
}

public function cancelarCambioCorreo(): void
{
    $this->exigirPostConfiguracionPsicologo();
    $this->obtenerContextoPsicologo();

    $resultado = (new CuentaService())->cancelarCambioCorreo(
        trim((string) ($this->usuario['ClvUsu'] ?? ''))
    );

    $this->flashConfiguracion(
        $resultado['ok'] ? 'success' : 'error',
        $resultado['mensaje']
    );

    Response::redirect('psicologo/configuracion');
}

private function exigirPostConfiguracionPsicologo(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::redirect('psicologo/configuracion');
    }

    if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
        $this->flashConfiguracion(
            'error',
            'La solicitud no es válida. Intenta nuevamente.'
        );

        Response::redirect('psicologo/configuracion');
    }
}

private function flashConfiguracion(
    string $tipo,
    string $mensaje
): void {
    Session::setFlash('config_toast_tipo', $tipo);
    Session::setFlash('config_toast_mensaje', $mensaje);
}

    public function disponibilidad(): void
    {
        $contexto = $this->obtenerContextoPsicologo();

        $clvPsi = $contexto['psicologo']['ClvPsi'];
        $clvCons = $contexto['psicologo']['ClvCons'];

        $disponibilidadModel =
            new DisponibilidadPsicologo();

        $horarioModel = new HorarioConsultorio();

        $bloquesRegistrados =
            $disponibilidadModel->obtenerPorPsicologo(
                $clvPsi
            );

        $horariosConsultorio =
            $horarioModel->obtenerPorConsultorio(
                $clvCons
            );

        $bloquesPorDia = [];
        $horariosPorDia = [];

        foreach ($bloquesRegistrados as $bloque) {
            $bloquesPorDia[
                $bloque['DiaSemana']
            ][] = $bloque;
        }

        foreach ($horariosConsultorio as $horario) {
            $horariosPorDia[
                $horario['DiaSemana']
            ] = $horario;
        }

        $diasSemana = [];

        foreach (
            HorarioConsultorio::diasPermitidos() as $dia
        ) {
            $diasSemana[] = [
                'DiaSemana' => $dia,
                'Etiqueta' =>
                    HorarioConsultorio::etiquetaDia($dia),
                'HorarioConsultorio' =>
                    $horariosPorDia[$dia] ?? null,
                'Bloques' =>
                    $bloquesPorDia[$dia] ?? []
            ];
        }

        $this->view(
            'psicologo/disponibilidad/index',
            [
                'titulo' => 'Mi disponibilidad',
                'usuario' => $this->usuario,
                'psicologo' =>
                    $contexto['psicologo'],
                'consultorio' =>
                    $contexto['consultorio'],
                'diasSemana' => $diasSemana,
                'errores' =>
                    Session::getFlash('errores') ?? [],
                'success' =>
                    Session::getFlash('success'),
                'error' =>
                    Session::getFlash('error')
            ],
            'psicologo'
        );
    }

    public function guardarDisponibilidad(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('psicologo/disponibilidad');
        }

        $contexto = $this->obtenerContextoPsicologo();

        $clvPsi = $contexto['psicologo']['ClvPsi'];
        $clvCons = $contexto['psicologo']['ClvCons'];

        $diaSemana = strtoupper(
            trim($_POST['diaSemana'] ?? '')
        );
        $horaInicio = trim($_POST['horaInicio'] ?? '');
        $horaFin = trim($_POST['horaFin'] ?? '');
        $estatusSolicitado = strtoupper(
            trim($_POST['estatusDisponibilidad'] ?? 'ACTIVA')
        );

        if (
            !in_array(
                $estatusSolicitado,
                ['ACTIVA', 'INACTIVA'],
                true
            )
        ) {
            $estatusSolicitado = 'ACTIVA';
        }

        $errores = $this->validarDisponibilidad(
            $clvPsi,
            $clvCons,
            $contexto['psicologo'],
            $contexto['consultorio'],
            $diaSemana,
            $horaInicio,
            $horaFin,
            $estatusSolicitado === 'ACTIVA'
        );

        if (!empty($errores)) {
            Session::setFlash('errores', [
                'nuevo_' . $diaSemana => $errores
            ]);

            Response::redirect('psicologo/disponibilidad');
        }

        $disponibilidadModel =
            new DisponibilidadPsicologo();

        try {
            $creado = $disponibilidadModel->crear([
                'DiaSemana' => $diaSemana,
                'HoraInicio' =>
                    $this->normalizarHora($horaInicio),
                'HoraFin' =>
                    $this->normalizarHora($horaFin),
                'EstatusDisponibilidad' =>
                    $estatusSolicitado,
                'ClvPsi' => $clvPsi
            ]);
        } catch (\PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                Session::setFlash(
                    'error',
                    'Ya existe un bloque idéntico para ese día y horario.'
                );

                Response::redirect('psicologo/disponibilidad');
            }

            throw $e;
        }

        if (!$creado) {
            Session::setFlash(
                'error',
                'No fue posible crear el bloque de disponibilidad.'
            );

            Response::redirect('psicologo/disponibilidad');
        }

        Session::setFlash(
            'success',
            'Bloque de disponibilidad creado correctamente.'
        );

        Response::redirect('psicologo/disponibilidad');
    }

    public function actualizarDisponibilidad(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('psicologo/disponibilidad');
        }

        $contexto = $this->obtenerContextoPsicologo();

        $clvPsi = $contexto['psicologo']['ClvPsi'];
        $clvCons = $contexto['psicologo']['ClvCons'];
        $clvDisponibilidad = trim(
            $_POST['clvDisponibilidad'] ?? ''
        );

        if ($clvDisponibilidad === '') {
            Session::setFlash(
                'error',
                'No se recibió el bloque a actualizar.'
            );

            Response::redirect('psicologo/disponibilidad');
        }

        $disponibilidadModel =
            new DisponibilidadPsicologo();

        if (
            !$disponibilidadModel->perteneceAlPsicologo(
                $clvDisponibilidad,
                $clvPsi
            )
        ) {
            Session::setFlash(
                'error',
                'No tienes permiso para modificar ese bloque.'
            );

            Response::redirect('psicologo/disponibilidad');
        }

        $registro = $this->obtenerBloqueDisponibilidad(
            $disponibilidadModel,
            $clvPsi,
            $clvDisponibilidad
        );

        if (!$registro) {
            Session::setFlash(
                'error',
                'El bloque solicitado no existe.'
            );

            Response::redirect('psicologo/disponibilidad');
        }

        $diaSemana = strtoupper(
            trim($_POST['diaSemana'] ?? $registro['DiaSemana'])
        );
        $horaInicio = trim($_POST['horaInicio'] ?? '');
        $horaFin = trim($_POST['horaFin'] ?? '');
        $seraActiva =
            ($registro['EstatusDisponibilidad'] ?? '') === 'ACTIVA';

        $errores = $this->validarDisponibilidad(
            $clvPsi,
            $clvCons,
            $contexto['psicologo'],
            $contexto['consultorio'],
            $diaSemana,
            $horaInicio,
            $horaFin,
            $seraActiva,
            $clvDisponibilidad
        );

        if (!empty($errores)) {
            Session::setFlash('errores', [
                $clvDisponibilidad => $errores
            ]);

            Response::redirect('psicologo/disponibilidad');
        }

        try {
            $actualizado = $disponibilidadModel->actualizar(
                $clvDisponibilidad,
                $clvPsi,
                [
                    'DiaSemana' => $diaSemana,
                    'HoraInicio' =>
                        $this->normalizarHora($horaInicio),
                    'HoraFin' =>
                        $this->normalizarHora($horaFin)
                ]
            );
        } catch (\PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                Session::setFlash(
                    'error',
                    'Ya existe un bloque idéntico para ese día y horario.'
                );

                Response::redirect('psicologo/disponibilidad');
            }

            throw $e;
        }

        if (!$actualizado) {
            Session::setFlash(
                'error',
                'No fue posible actualizar el bloque.'
            );

            Response::redirect('psicologo/disponibilidad');
        }

        Session::setFlash(
            'success',
            'Bloque de disponibilidad actualizado correctamente.'
        );

        Response::redirect('psicologo/disponibilidad');
    }

    public function cambiarEstatusDisponibilidad(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('psicologo/disponibilidad');
        }

        $contexto = $this->obtenerContextoPsicologo();

        $clvPsi = $contexto['psicologo']['ClvPsi'];
        $clvCons = $contexto['psicologo']['ClvCons'];
        $clvDisponibilidad = trim(
            $_POST['clvDisponibilidad'] ?? ''
        );
        $accion = strtolower(
            trim($_POST['accion'] ?? '')
        );

        if ($clvDisponibilidad === '') {
            Session::setFlash(
                'error',
                'No se recibió el bloque a modificar.'
            );

            Response::redirect('psicologo/disponibilidad');
        }

        if (!in_array($accion, ['activar', 'inactivar'], true)) {
            Session::setFlash(
                'error',
                'La acción solicitada no es válida.'
            );

            Response::redirect('psicologo/disponibilidad');
        }

        $disponibilidadModel =
            new DisponibilidadPsicologo();

        if (
            !$disponibilidadModel->perteneceAlPsicologo(
                $clvDisponibilidad,
                $clvPsi
            )
        ) {
            Session::setFlash(
                'error',
                'No tienes permiso para modificar ese bloque.'
            );

            Response::redirect('psicologo/disponibilidad');
        }

        $registro = $this->obtenerBloqueDisponibilidad(
            $disponibilidadModel,
            $clvPsi,
            $clvDisponibilidad
        );

        if (!$registro) {
            Session::setFlash(
                'error',
                'El bloque solicitado no existe.'
            );

            Response::redirect('psicologo/disponibilidad');
        }

        $nuevoEstatus =
            $accion === 'activar'
                ? 'ACTIVA'
                : 'INACTIVA';

        if (
            ($registro['EstatusDisponibilidad'] ?? '')
            === $nuevoEstatus
        ) {
            Session::setFlash(
                'error',
                'El bloque ya se encuentra en ese estatus.'
            );

            Response::redirect('psicologo/disponibilidad');
        }

        if ($nuevoEstatus === 'ACTIVA') {
            $horaInicio = substr(
                (string) $registro['HoraInicio'],
                0,
                5
            );
            $horaFin = substr(
                (string) $registro['HoraFin'],
                0,
                5
            );

            $errores = $this->validarDisponibilidad(
                $clvPsi,
                $clvCons,
                $contexto['psicologo'],
                $contexto['consultorio'],
                $registro['DiaSemana'],
                $horaInicio,
                $horaFin,
                true,
                $clvDisponibilidad
            );

            if (!empty($errores)) {
                Session::setFlash(
                    'error',
                    'No es posible activar el bloque: '
                    . implode(' ', $errores)
                );

                Response::redirect('psicologo/disponibilidad');
            }
        }

        $actualizado =
            $disponibilidadModel->cambiarEstatus(
                $clvDisponibilidad,
                $clvPsi,
                $nuevoEstatus
            );

        if (!$actualizado) {
            Session::setFlash(
                'error',
                'No fue posible cambiar el estatus del bloque.'
            );

            Response::redirect('psicologo/disponibilidad');
        }

        Session::setFlash(
            'success',
            $nuevoEstatus === 'ACTIVA'
                ? 'Bloque activado correctamente.'
                : 'Bloque inactivado correctamente.'
        );

        Response::redirect('psicologo/disponibilidad');
    }

    private function obtenerBloqueDisponibilidad(
        DisponibilidadPsicologo $modelo,
        string $clvPsi,
        string $clvDisponibilidad
    ): ?array {
        foreach (
            $modelo->obtenerPorPsicologo($clvPsi) as $bloque
        ) {
            if (
                $bloque['ClvDisponibilidad']
                === $clvDisponibilidad
            ) {
                return $bloque;
            }
        }

        return null;
    }

    private function validarDisponibilidad(
        string $clvPsi,
        string $clvCons,
        array $psicologo,
        array $consultorio,
        string $diaSemana,
        string $horaInicio,
        string $horaFin,
        bool $validarSolapamiento,
        ?string $excluirClave = null
    ): array {
        $errores = [];

        if ($psicologo['EstatusPsi'] !== 'ACTIVO') {
            $errores[] =
                'Tu perfil de especialista no está activo.';

            return $errores;
        }

        if (($consultorio['EstatusCons'] ?? '') !== 'ACTIVO') {
            $errores[] =
                'El consultorio no está activo.';

            return $errores;
        }

        if (
            !in_array(
                $diaSemana,
                HorarioConsultorio::diasPermitidos(),
                true
            )
        ) {
            $errores[] = 'El día seleccionado no es válido.';

            return $errores;
        }

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

            return $errores;
        }

        $horarioModel = new HorarioConsultorio();

        $horarioConsultorio =
            $horarioModel->obtenerPorConsultorioYDia(
                $clvCons,
                $diaSemana
            );

        if (!$horarioConsultorio) {
            $errores[] =
                'El consultorio no tiene configurado el horario '
                . 'de '
                . HorarioConsultorio::etiquetaDia($diaSemana)
                . '.';

            return $errores;
        }

        if (
            ($horarioConsultorio['EstatusHorario'] ?? '')
            !== 'ACTIVO'
        ) {
            $errores[] =
                'El consultorio no atiende los '
                . HorarioConsultorio::etiquetaDia($diaSemana)
                . '.';

            return $errores;
        }

        $apertura = $this->normalizarHora(
            substr((string) $horarioConsultorio['HoraInicio'], 0, 5)
        );
        $cierre = $this->normalizarHora(
            substr((string) $horarioConsultorio['HoraFin'], 0, 5)
        );

        if ($inicio < $apertura) {
            $errores[] =
                'La hora de inicio no puede ser anterior '
                . 'a la apertura del consultorio '
                . '(' . substr($apertura, 0, 5) . ').';
        }

        if ($fin > $cierre) {
            $errores[] =
                'La hora de fin no puede ser posterior '
                . 'al cierre del consultorio '
                . '(' . substr($cierre, 0, 5) . ').';
        }

        if (!empty($errores)) {
            return $errores;
        }

        if ($validarSolapamiento) {
            $disponibilidadModel =
                new DisponibilidadPsicologo();

            if (
                $disponibilidadModel->existeSolapamiento(
                    $clvPsi,
                    $diaSemana,
                    $inicio,
                    $fin,
                    $excluirClave
                )
            ) {
                $errores[] =
                    'El bloque se solapa con otra '
                    . 'disponibilidad activa del mismo día.';
            }
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
        return strlen($hora) === 5
            ? $hora . ':00'
            : $hora;
    }

    /**
     * Conserva valores del formulario clínico ante error de validación.
     * Excluye tokens y claves de sesión; no altera lógica de autorización.
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function sanitizarPostHistoriaClinica(array $post): array
    {
        unset(
            $post['csrf_token'],
            $post['ClvPsi'],
            $post['ClvCons']
        );

        return $post;
    }
}