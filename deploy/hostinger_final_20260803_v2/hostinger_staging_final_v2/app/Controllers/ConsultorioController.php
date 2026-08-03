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
use App\Models\PsicologoServicio;
use App\Services\AccesoSesionService;
use App\Services\ActivacionCuentaService;
use App\Services\ConfiguracionConsultorioService;
use App\Services\ClaveService;
use App\Services\IncidenciaSoporteService;
use App\Services\PrivacidadService;
use App\Services\PublicacionConsultorioService;
use App\Services\ServicioOfertaService;
use App\Services\RedSocialService;
use App\Services\RedSocialUrlValidator;
use App\Models\RedSocialConsultorio;
use App\Config\Database;
use RuntimeException;
use Throwable;

class ConsultorioController extends Controller
{
    private array $usuario;
    private array $consultorio;

    public function __construct()
    {
        $usuarioDb = (new AccesoSesionService())->exigirSesionActiva('CONSULTORIO');

        $consultorioUsuarioModel = new ConsultorioUsuario();
        $consultorio = $consultorioUsuarioModel->buscarPorUsuario(
            (string) $usuarioDb['ClvUsu']
        );

        if (!$consultorio) {
            Session::set(
                'error',
                'La cuenta no está asociada a un consultorio activo.'
            );
            Session::destroy();
            Session::regenerar();
            Response::redirect('login');
        }

        $this->usuario = Session::get('usuario');
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

        $servicioModel = new Servicio();
        $servicios = $servicioModel->listarPorConsultorio($clvCons);
        $serviciosActivos = 0;
        foreach ($servicios as $serv) {
            if (strtoupper((string) ($serv['EstatusServicio'] ?? '')) === 'ACTIVO') {
                $serviciosActivos++;
            }
        }

        $horarioModel = new HorarioConsultorio();
        $horarios = $horarioModel->obtenerPorConsultorio($clvCons);
        $diasHorarioActivo = 0;
        foreach ($horarios as $horario) {
            if (strtoupper((string) ($horario['EstatusHorario'] ?? '')) === 'ACTIVO') {
                $diasHorarioActivo++;
            }
        }

        $incidenciasAbiertas = 0;
        $moduloIncidencias = false;
        try {
            $incService = new IncidenciaSoporteService();
            $moduloIncidencias = $incService->moduloDisponible();
            if ($moduloIncidencias) {
                $incidenciasAbiertas = $incService->contarAbiertasConsultorio(
                    (string) $clvCons
                );
            }
        } catch (Throwable $e) {
            $incidenciasAbiertas = 0;
        }

        $resumenActividad = $citaModel->resumenActividadEspecialistas(
            (string) $clvCons
        );

        $alertasOperativas = $this->construirAlertasDashboard(
            $progreso,
            $diasHorarioActivo,
            $incidenciasAbiertas,
            $moduloIncidencias,
            count($psicologosActivos)
        );

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

                'citasSemana' =>
                    $citaModel->contarCitasSemanaPorConsultorio(
                        $clvCons
                    ),

                'citasProgramadas' =>
                    $citaModel->contarCitasProgramadasPorConsultorio(
                        $clvCons
                    ),

                'citasAsistidas' =>
                    $citaModel->contarCitasAsistidasPorConsultorio(
                        $clvCons
                    ),

                'citasCanceladas' =>
                    $citaModel->contarCitasCanceladasPorConsultorio(
                        $clvCons
                    ),

                'inasistencias' =>
                    $citaModel->contarInasistenciasPorConsultorio(
                        $clvCons
                    ),

                'totalPsicologosActivos' =>
                    count($psicologosActivos),

                'serviciosActivos' => $serviciosActivos,
                'diasHorarioActivo' => $diasHorarioActivo,
                'incidenciasAbiertas' => $incidenciasAbiertas,
                'moduloIncidencias' => $moduloIncidencias,
                'resumenActividad' => $resumenActividad,
                'alertasOperativas' => $alertasOperativas,

                'proximasCitas' =>
                    $citaModel->obtenerProximasCitasPorConsultorio(
                        $clvCons,
                        6
                    ),

                'progresoPublicacion' => $progreso,
                'estadoPagina' => $estadoPagina,
                'etiquetaPaginaPublica' => $etiquetaTarjeta,
                'csrf' => Session::csrfToken(),
                'success' => Session::getFlash('success'),
                'error' => Session::getFlash('error'),
                'pendientesPublicacion' =>
                    Session::getFlash('pendientesPublicacion') ?? [],
                'cargarDashboardCss' => true
            ],
            'consultorio'
        );
    }

    /**
     * @param array<string, mixed> $progreso
     * @return list<array{titulo: string, texto: string, href: string, icono: string}>
     */
    private function construirAlertasDashboard(
        array $progreso,
        int $diasHorarioActivo,
        int $incidenciasAbiertas,
        bool $moduloIncidencias,
        int $psicologosActivos
    ): array {
        $alertas = [];

        if ($diasHorarioActivo < 1) {
            $alertas[] = [
                'titulo' => 'Sin horario activo',
                'texto' => 'Define al menos un día de atención institucional.',
                'href' => 'consultorio/horario',
                'icono' => 'bi-clock-history',
            ];
        }

        if ($psicologosActivos < 1) {
            $alertas[] = [
                'titulo' => 'Sin especialistas activos',
                'texto' => 'Registra o activa al menos un psicólogo.',
                'href' => 'consultorio/psicologos',
                'icono' => 'bi-person-x',
            ];
        }

        if (trim((string) ($this->consultorio['LogotipoCons'] ?? '')) === '') {
            $alertas[] = [
                'titulo' => 'Logotipo pendiente',
                'texto' => 'Agrega el logotipo para fortalecer la identidad pública.',
                'href' => 'consultorio/configuracion',
                'icono' => 'bi-image',
            ];
        }

        try {
            $stmtCoords = Database::connect()->prepare(
                "SELECT d.LatitudDir, d.LongitudDir
                 FROM consultorio c
                 LEFT JOIN direccion d ON d.ClvDir = c.ClvDir
                 WHERE c.ClvCons = :clv
                 LIMIT 1"
            );
            $stmtCoords->execute([
                'clv' => (string) ($this->consultorio['ClvCons'] ?? '')
            ]);
            $coords = $stmtCoords->fetch(\PDO::FETCH_ASSOC) ?: [];
            if (
                !Helper::coordenadasPublicasValidas(
                    $coords['LatitudDir'] ?? null,
                    $coords['LongitudDir'] ?? null
                )
            ) {
                $alertas[] = [
                    'titulo' => 'Ubicación incompleta',
                    'texto' => 'Confirma la dirección y coordenadas en configuración.',
                    'href' => 'consultorio/configuracion',
                    'icono' => 'bi-geo-alt',
                ];
            }
        } catch (Throwable $e) {
            // Sin alerta de coordenadas si no se puede verificar.
        }

        if ($moduloIncidencias && $incidenciasAbiertas > 0) {
            $alertas[] = [
                'titulo' => 'Incidencias por revisar',
                'texto' => $incidenciasAbiertas === 1
                    ? 'Hay 1 incidencia de acceso abierta.'
                    : 'Hay ' . $incidenciasAbiertas . ' incidencias de acceso abiertas.',
                'href' => 'consultorio/incidencias',
                'icono' => 'bi-flag',
            ];
        }

        $mapaRutas = [
            'nombre' => 'consultorio/configuracion',
            'descripcion' => 'consultorio/configuracion',
            'contacto' => 'consultorio/configuracion',
            'direccion' => 'consultorio/configuracion',
            'horario' => 'consultorio/horario',
            'servicio' => 'consultorio/servicios',
            'psicologo' => 'consultorio/psicologos',
            'cancelacion' => 'consultorio/configuracion',
        ];

        foreach (($progreso['pendientes'] ?? []) as $item) {
            if (count($alertas) >= 5) {
                break;
            }
            $clave = (string) ($item['clave'] ?? '');
            $etiqueta = trim((string) ($item['etiqueta'] ?? ''));
            if ($etiqueta === '') {
                continue;
            }
            $alertas[] = [
                'titulo' => 'Configuración pendiente',
                'texto' => $etiqueta,
                'href' => $mapaRutas[$clave] ?? 'consultorio/configuracion',
                'icono' => 'bi-exclamation-circle',
            ];
        }

        return array_slice($alertas, 0, 5);
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
                'horarioOld' => Session::getFlash('horario_old') ?? [],
                'success' => Session::getFlash('success'),
                'error' => Session::getFlash('error')
            ],
            'consultorio'
        );
    }

    public function guardarHorarioSemana(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('consultorio/horario');
        }

        $this->exigirCsrfHorario();

        $clvCons = (string) $this->consultorio['ClvCons'];
        $horarioModel = new HorarioConsultorio();
        $horarioModel->crearDiasFaltantes($clvCons);

        $registrados = $horarioModel->obtenerPorConsultorio($clvCons);
        $porDia = [];

        foreach ($registrados as $horario) {
            $porDia[(string) $horario['DiaSemana']] = $horario;
        }

        $postDias = is_array($_POST['dias'] ?? null) ? $_POST['dias'] : [];
        $errores = [];
        $payload = [];
        $old = [];

        foreach (HorarioConsultorio::diasPermitidos() as $dia) {
            $registro = $porDia[$dia] ?? null;
            $etiqueta = HorarioConsultorio::etiquetaDia($dia);

            if ($registro === null) {
                $errores[$dia] = $etiqueta
                    . ': este día aún no está configurado.';
                continue;
            }

            $datosDia = is_array($postDias[$dia] ?? null)
                ? $postDias[$dia]
                : [];

            $activo = (string) ($datosDia['activo'] ?? '0') === '1';
            $horaInicio = $this->normalizarHoraEntrada(
                (string) ($datosDia['horaInicio'] ?? '')
            );
            $horaFin = $this->normalizarHoraEntrada(
                (string) ($datosDia['horaFin'] ?? '')
            );

            $old[$dia] = [
                'activo' => $activo ? '1' : '0',
                'horaInicio' => $horaInicio,
                'horaFin' => $horaFin
            ];

            if ($activo) {
                if ($horaInicio === '') {
                    $errores[$dia] =
                        $etiqueta . ': selecciona una hora de apertura.';
                    continue;
                }

                if ($horaFin === '') {
                    $errores[$dia] =
                        $etiqueta . ': selecciona una hora de cierre.';
                    continue;
                }

                if (
                    !$this->horaValida($horaInicio)
                    || !$this->horaValida($horaFin)
                ) {
                    $errores[$dia] =
                        $etiqueta . ': el formato de hora no es válido.';
                    continue;
                }

                $inicioNorm = $this->normalizarHora($horaInicio);
                $finNorm = $this->normalizarHora($horaFin);

                if ($inicioNorm >= $finNorm) {
                    $errores[$dia] = $etiqueta
                        . ': la hora de cierre debe ser posterior a la '
                        . 'hora de apertura.';
                    continue;
                }

                $detalle = $this->detalleDisponibilidadesIncompatibles(
                    $clvCons,
                    $dia,
                    $inicioNorm,
                    $finNorm,
                    false
                );

                if (($detalle['total'] ?? 0) > 0) {
                    $cierrePedido = substr($finNorm, 0, 5);
                    $maxFin = $detalle['maxHoraFin'] ?? '';
                    $total = (int) $detalle['total'];
                    $errores[$dia] = $etiqueta
                        . ': no se puede cerrar a las '
                        . $cierrePedido
                        . (
                            $maxFin !== ''
                                ? ' porque existe disponibilidad activa hasta las '
                                    . $maxFin
                                : ''
                        )
                        . ' ('
                        . $total
                        . (
                            $total === 1
                                ? ' bloque incompatible'
                                : ' bloques incompatibles'
                        )
                        . '). Ajusta primero la disponibilidad de los especialistas.';
                    continue;
                }

                $payload[] = [
                    'ClvHorarioCons' => (string) $registro['ClvHorarioCons'],
                    'HoraInicio' => $inicioNorm,
                    'HoraFin' => $finNorm,
                    'EstatusHorario' => 'ACTIVO'
                ];
            } else {
                $detalle = $this->detalleDisponibilidadesIncompatibles(
                    $clvCons,
                    $dia,
                    null,
                    null,
                    true
                );

                if (($detalle['total'] ?? 0) > 0) {
                    $total = (int) $detalle['total'];
                    $errores[$dia] = 'No se puede inactivar el '
                        . mb_strtolower($etiqueta)
                        . ' porque existen '
                        . $total
                        . (
                            $total === 1
                                ? ' disponibilidad activa'
                                : ' disponibilidades activas'
                        )
                        . ' de psicólogos. Ajusta primero la disponibilidad '
                        . 'de los especialistas.';
                    continue;
                }

                // Conservar horas previas (columnas NOT NULL).
                $payload[] = [
                    'ClvHorarioCons' => (string) $registro['ClvHorarioCons'],
                    'HoraInicio' => $registro['HoraInicio'],
                    'HoraFin' => $registro['HoraFin'],
                    'EstatusHorario' => 'INACTIVO'
                ];
            }
        }

        if ($errores !== []) {
            Session::setFlash('errores', $errores);
            Session::setFlash('horario_old', $old);
            Session::setFlash(
                'error',
                'Revisa los días marcados. No se guardó ningún cambio.'
            );
            $this->redirectDespuesHorario();
        }

        try {
            $horarioModel->actualizarSemana($clvCons, $payload);
        } catch (\Throwable $e) {
            error_log(json_encode([
                'evento' => 'horario_semana_error',
                'ClvCons' => $clvCons,
                'clase' => $e::class
            ], JSON_UNESCAPED_UNICODE));

            Session::setFlash(
                'error',
                'No fue posible guardar el horario. Intenta nuevamente.'
            );
            $this->redirectDespuesHorario();
        }

        Session::setFlash(
            'success',
            'Horario de atención actualizado correctamente.'
        );

        $this->redirectDespuesHorario();
    }

    public function actualizarHorario(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('consultorio/horario');
        }

        $this->exigirCsrfHorario();

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

        $etiqueta = HorarioConsultorio::etiquetaDia(
            (string) $registro['DiaSemana']
        );
        $horaInicio = $this->normalizarHoraEntrada(
            trim((string) ($_POST['horaInicio'] ?? ''))
        );
        $horaFin = $this->normalizarHoraEntrada(
            trim((string) ($_POST['horaFin'] ?? ''))
        );

        $errores = $this->validarHorarioActivo(
            $horaInicio,
            $horaFin
        );

        if (!empty($errores)) {
            Session::setFlash('errores', [
                (string) $registro['DiaSemana'] => array_map(
                    static fn(string $msg): string => $etiqueta . ': ' . $msg,
                    $errores
                )
            ]);

            $this->redirectDespuesHorario();
        }

        $horaInicioNorm = $this->normalizarHora($horaInicio);
        $horaFinNorm = $this->normalizarHora($horaFin);

        if (($registro['EstatusHorario'] ?? '') === 'ACTIVO') {
            $detalle = $this->detalleDisponibilidadesIncompatibles(
                $clvCons,
                (string) $registro['DiaSemana'],
                $horaInicioNorm,
                $horaFinNorm,
                false
            );

            if (($detalle['total'] ?? 0) > 0) {
                $maxFin = $detalle['maxHoraFin'] ?? '';
                Session::setFlash(
                    'error',
                    'No se puede cerrar el '
                    . mb_strtolower($etiqueta)
                    . ' a las '
                    . substr($horaFinNorm, 0, 5)
                    . (
                        $maxFin !== ''
                            ? ' porque existe una disponibilidad activa hasta las '
                                . $maxFin . '.'
                            : '.'
                    )
                    . ' Ajusta primero la disponibilidad de los especialistas.'
                );

                $this->redirectDespuesHorario();
            }
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
            'Horario de atención actualizado correctamente.'
        );

        $this->redirectDespuesHorario();
    }

    public function cambiarEstatusHorario(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('consultorio/horario');
        }

        $this->exigirCsrfHorario();

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

        if ($nuevoEstatus === 'INACTIVO') {
            $detalle = $this->detalleDisponibilidadesIncompatibles(
                $clvCons,
                (string) $registro['DiaSemana'],
                null,
                null,
                true
            );

            if (($detalle['total'] ?? 0) > 0) {
                $etiqueta = HorarioConsultorio::etiquetaDia(
                    (string) $registro['DiaSemana']
                );
                $total = (int) $detalle['total'];

                Session::setFlash(
                    'error',
                    'No se puede inactivar el '
                    . mb_strtolower($etiqueta)
                    . ' porque existen '
                    . $total
                    . (
                        $total === 1
                            ? ' disponibilidad activa'
                            : ' disponibilidades activas'
                    )
                    . ' de psicólogos. Ajusta primero la disponibilidad '
                    . 'de los especialistas.'
                );

                $this->redirectDespuesHorario();
            }
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
            'Horario de atención actualizado correctamente.'
        );

        $this->redirectDespuesHorario();
    }

    public function solicitudesPrivacidad(): void
    {
        $servicio = new PrivacidadService();
        $consulta = $servicio->consultasSolicitudesPorRol(
            'CONSULTORIO',
            (string) ($this->usuario['ClvUsu'] ?? '')
        );

        $this->view(
            'consultorio/privacidad/solicitudes',
            [
                'titulo' => 'Solicitudes de privacidad',
                'usuario' => $this->usuario,
                'consultorio' => $this->consultorio,
                'solicitudes' => $consulta['data'] ?? [],
                'csrf' => Session::csrfToken()
            ],
            'consultorio'
        );
    }

    public function responderSolicitudPrivacidad(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('consultorio/privacidad/solicitudes');
        }

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            Session::setFlash(
                'error',
                'La solicitud expiró. Intenta nuevamente.'
            );
            Response::redirect('consultorio/privacidad/solicitudes');
        }

        $servicio = new PrivacidadService();
        $resultado = $servicio->responderSolicitudComoConsultorio(
            'CONSULTORIO',
            (string) ($this->usuario['ClvUsu'] ?? ''),
            (int) ($_POST['id_solicitud'] ?? 0),
            trim((string) ($_POST['estado_solicitud'] ?? '')),
            trim((string) ($_POST['respuesta_titular'] ?? '')),
            trim((string) ($_POST['notas_internas'] ?? ''))
        );

        Session::setFlash(
            $resultado['ok'] ? 'success' : 'error',
            (string) ($resultado['mensaje'] ?? (
                $resultado['ok']
                    ? 'Solicitud actualizada.'
                    : 'No se pudo actualizar la solicitud.'
            ))
        );

        Response::redirect('consultorio/privacidad/solicitudes');
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
                'horarioOld' =>
                    Session::getFlash('horario_old') ?? [],
                'erroresConfig' =>
                    Session::getFlash('erroresConfig') ?? [],
                'redesSociales' => (new RedSocialConsultorio())
                    ->listarPorConsultorio($clvCons),
                'plataformasRed' => RedSocialUrlValidator::PLATAFORMAS,
                'success' => Session::getFlash('success'),
                'error' => Session::getFlash('error'),
                'cargarConfigCss' => true,
                'cargarConfigJs' => true
            ],
            'consultorio'
        );
    }

    public function guardarRedSocial(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('consultorio/configuracion#redes-sociales');
        }

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'La solicitud no es válida. Intenta nuevamente.');
            Response::redirect('consultorio/configuracion#redes-sociales');
        }

        // ClvCons solo desde sesión/instalación.
        $clvCons = (string) ($this->consultorio['ClvCons'] ?? '');
        $resultado = (new RedSocialService())->crearParaConsultorio($clvCons, $_POST);
        Session::setFlash($resultado['ok'] ? 'success' : 'error', $resultado['mensaje']);
        Response::redirect('consultorio/configuracion#redes-sociales');
    }

    public function actualizarRedSocial(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('consultorio/configuracion#redes-sociales');
        }

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'La solicitud no es válida. Intenta nuevamente.');
            Response::redirect('consultorio/configuracion#redes-sociales');
        }

        $clvCons = (string) ($this->consultorio['ClvCons'] ?? '');
        $clvRed = trim((string) ($_POST['clvRed'] ?? ''));
        $resultado = (new RedSocialService())->actualizarParaConsultorio(
            $clvCons,
            $clvRed,
            $_POST
        );
        Session::setFlash($resultado['ok'] ? 'success' : 'error', $resultado['mensaje']);
        Response::redirect('consultorio/configuracion#redes-sociales');
    }

    public function cambiarEstadoRedSocial(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('consultorio/configuracion#redes-sociales');
        }

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'La solicitud no es válida. Intenta nuevamente.');
            Response::redirect('consultorio/configuracion#redes-sociales');
        }

        $clvCons = (string) ($this->consultorio['ClvCons'] ?? '');
        $resultado = (new RedSocialService())->cambiarEstadoConsultorio(
            $clvCons,
            trim((string) ($_POST['clvRed'] ?? '')),
            (string) ($_POST['accion'] ?? '')
        );
        Session::setFlash($resultado['ok'] ? 'success' : 'error', $resultado['mensaje']);
        Response::redirect('consultorio/configuracion#redes-sociales');
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

        $latitudNorm = Helper::normalizarCoordenada(
            $_POST['LatitudDir'] ?? null
        );
        $longitudNorm = Helper::normalizarCoordenada(
            $_POST['LongitudDir'] ?? null
        );

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
            'LatitudDir' => $latitudNorm,
            'LongitudDir' => $longitudNorm
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

            $tieneCoords = Helper::coordenadasPublicasValidas(
                $latitudNorm,
                $longitudNorm
            );

            if ($tieneCoords) {
                Session::setFlash(
                    'success',
                    'La configuración fue actualizada correctamente.'
                );
            } else {
                Session::setFlash(
                    'success',
                    'La dirección se guardó, pero falta seleccionar su ubicación en el mapa.'
                );
            }
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
                    'costoProgramado' =>
                        Helper::formatearMonedaMxn(
                            $cita['CostoAplicado'] ?? 0
                        ),
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
        $returnTo = strtolower(trim((string) ($_POST['returnTo'] ?? '')));
        $permitidos = ['configuracion', 'horario'];

        if (!in_array($returnTo, $permitidos, true)) {
            $returnTo = 'horario';
        }

        if ($returnTo === 'configuracion') {
            Response::redirect('consultorio/configuracion#horario-atencion');
        }

        Response::redirect('consultorio/horario');
    }

    private function exigirCsrfHorario(): void
    {
        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            Session::setFlash(
                'error',
                'Token de seguridad inválido. Recarga la página.'
            );

            $this->redirectDespuesHorario();
        }
    }

    /**
     * @return array{total:int, maxHoraFin:?string, minHoraInicio:?string}
     */
    private function detalleDisponibilidadesIncompatibles(
        string $clvCons,
        string $diaSemana,
        ?string $horaInicio,
        ?string $horaFin,
        bool $diaInactivo
    ): array {
        return (new DisponibilidadPsicologo())
            ->detalleActivasIncompatiblesConHorario(
                $clvCons,
                $diaSemana,
                $horaInicio,
                $horaFin,
                $diaInactivo
            );
    }

    private function hayDisponibilidadesIncompatibles(
        string $clvCons,
        string $diaSemana,
        ?string $horaInicio,
        ?string $horaFin,
        bool $diaInactivo
    ): bool {
        return ($this->detalleDisponibilidadesIncompatibles(
            $clvCons,
            $diaSemana,
            $horaInicio,
            $horaFin,
            $diaInactivo
        )['total'] ?? 0) > 0;
    }

    private function normalizarHoraEntrada(string $hora): string
    {
        $hora = trim($hora);

        if ($hora === '') {
            return '';
        }

        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $hora)) {
            return substr($hora, 0, 5);
        }

        return $hora;
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

        $latRaw = trim((string) ($post['LatitudDir'] ?? ''));
        $lngRaw = trim((string) ($post['LongitudDir'] ?? ''));

        $latitud = $latRaw === ''
            ? null
            : Helper::normalizarCoordenada($latRaw);
        $longitud = $lngRaw === ''
            ? null
            : Helper::normalizarCoordenada($lngRaw);

        if ($latRaw !== '' && $latitud === null) {
            $errores['LatitudDir'] =
                'La latitud no es válida.';
        } elseif (
            $latitud !== null
            && ($latitud < -90.0 || $latitud > 90.0)
        ) {
            $errores['LatitudDir'] =
                'La latitud debe estar entre -90 y 90.';
        }

        if ($lngRaw !== '' && $longitud === null) {
            $errores['LongitudDir'] =
                'La longitud no es válida.';
        } elseif (
            $longitud !== null
            && ($longitud < -180.0 || $longitud > 180.0)
        ) {
            $errores['LongitudDir'] =
                'La longitud debe estar entre -180 y 180.';
        }

        if (
            ($latitud !== null && $longitud === null)
            || ($latitud === null && $longitud !== null)
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
        $datos = [
            'EstatusServicio' => 'ACTIVO'
        ];
        $idSugerencia = (int) ($_GET['sugerencia'] ?? 0);

        if ($idSugerencia > 0) {
            $sugerencia = (new \App\Services\SugerenciaServicioService())
                ->obtenerParaConsultorio(
                    $idSugerencia,
                    (string) $this->consultorio['ClvCons']
                );

            if (
                $sugerencia
                && ($sugerencia['EstadoSugerencia'] ?? '') === 'PENDIENTE'
            ) {
                $datos['NombreServicio'] = (string) ($sugerencia['NombreSugerido'] ?? '');
                $datos['Descripcion'] = (string) ($sugerencia['DescripcionSugerida'] ?? '');
            } else {
                $idSugerencia = 0;
                $_SESSION['error'] =
                    'La sugerencia no está disponible para aprobar.';
            }
        }

        $this->view(
            'consultorio/servicios/form',
            [
                'titulo' => 'Registrar servicio',
                'usuario' => $this->usuario,
                'consultorio' => $this->consultorio,
                'servicio' => null,
                'errores' => [],
                'datos' => $datos,
                'idSugerencia' => $idSugerencia > 0 ? $idSugerencia : null,
                'modoEdicion' => false,
                'cargarServiciosCss' => true
            ],
            'consultorio'
        );
    }

    public function sugerenciasServicio(): void
    {
        $servicio = new \App\Services\SugerenciaServicioService();

        $this->view(
            'consultorio/servicios/sugerencias',
            [
                'titulo' => 'Sugerencias de servicios',
                'usuario' => $this->usuario,
                'consultorio' => $this->consultorio,
                'sugerencias' => $servicio->listarParaConsultorio(
                    (string) $this->consultorio['ClvCons']
                ),
                'sugerenciasHabilitadas' => $servicio->persistenciaDisponible(),
                'cargarServiciosCss' => true
            ],
            'consultorio'
        );
    }

    public function verSugerenciaServicio(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $servicio = new \App\Services\SugerenciaServicioService();
        $sugerencia = $id > 0
            ? $servicio->obtenerParaConsultorio(
                $id,
                (string) $this->consultorio['ClvCons']
            )
            : null;

        if (!$sugerencia) {
            $_SESSION['error'] = 'La sugerencia no existe o no pertenece a tu consultorio.';
            Response::redirect('consultorio/servicios/sugerencias');
        }

        $this->view(
            'consultorio/servicios/sugerencia-detalle',
            [
                'titulo' => 'Detalle de sugerencia',
                'usuario' => $this->usuario,
                'consultorio' => $this->consultorio,
                'sugerencia' => $sugerencia,
                'cargarServiciosCss' => true
            ],
            'consultorio'
        );
    }

    public function rechazarSugerenciaServicio(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('consultorio/servicios/sugerencias');
        }

        $this->validarCsrfServicios();

        $id = (int) ($_POST['idSugerencia'] ?? 0);
        $observacion = trim((string) ($_POST['observacion'] ?? ''));
        $resultado = (new \App\Services\SugerenciaServicioService())->rechazar(
            $id,
            (string) $this->consultorio['ClvCons'],
            (string) ($this->usuario['ClvUsu'] ?? ''),
            $observacion
        );

        $_SESSION[$resultado['ok'] ? 'success' : 'error'] = $resultado['mensaje'];
        Response::redirect(
            $id > 0
                ? 'consultorio/servicios/sugerencias/ver?id=' . $id
                : 'consultorio/servicios/sugerencias'
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

        $idSugerenciaForm = (int) ($_POST['idSugerencia'] ?? 0);

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
                    'idSugerencia' => $idSugerenciaForm > 0 ? $idSugerenciaForm : null,
                    'modoEdicion' => false,
                    'cargarServiciosCss' => true
                ],
                'consultorio'
            );

            return;
        }

        $db = Database::connect();

        try {
            $servicioModel = new Servicio();
            $clvServ = ClaveService::generar(
                'servicios',
                'ClvServ',
                'SER'
            );

            $db->beginTransaction();

            $guardado = $servicioModel->crearParaConsultorio(
                $clvCons,
                [
                    'ClvServ' => $clvServ,
                    'NombreServicio' => $datos['NombreServicio'],
                    'Descripcion' => $datos['Descripcion'],
                    'DuracionMinutos' => 60,
                    'CostoServicio' => '0.00',
                    'EstatusServicio' => $datos['EstatusServicio']
                ]
            );

            if (!$guardado) {
                throw new RuntimeException(
                    'No fue posible registrar el servicio.'
                );
            }

            (new ServicioOfertaService($db))->incorporarServicioAPsicologos(
                $clvServ,
                $clvCons
            );

            $idSugerencia = (int) ($_POST['idSugerencia'] ?? 0);
            if ($idSugerencia > 0) {
                (new \App\Services\SugerenciaServicioService($db))
                    ->marcarAprobadaConServicio(
                        $idSugerencia,
                        $clvCons,
                        (string) ($this->usuario['ClvUsu'] ?? ''),
                        $clvServ
                    );
            }

            $db->commit();

            $_SESSION['success'] =
                'El servicio se registró y se incorporó a todos los especialistas. '
                . 'Quedará disponible para citas cuando cada uno configure precio y duración.';

            Response::redirect('consultorio/servicios');
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

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
                    'idSugerencia' => $idSugerenciaForm > 0 ? $idSugerenciaForm : null,
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

        $ofertas = (new PsicologoServicio())->listarOfertasPorServicio(
            $clvServ,
            $clvCons
        );

        $totalDisponibles = 0;
        foreach ($ofertas as $oferta) {
            if (
                ($oferta['EstatusAsignacion'] ?? '') === 'ACTIVA'
                && (float) ($oferta['PrecioServicio'] ?? 0) > 0
                && (int) ($oferta['DuracionMinutos'] ?? 0) > 0
            ) {
                $totalDisponibles++;
            }
        }

        $this->view(
            'consultorio/servicios/ver',
            [
                'titulo' => 'Detalle del servicio',
                'usuario' => $this->usuario,
                'consultorio' => $this->consultorio,
                'servicio' => $servicio,
                'totalPsicologos' => $totalDisponibles,
                'ofertasEspecialistas' => $ofertas,
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
                    // Columnas NOT NULL del catálogo: se conservan; no son tarifas del especialista.
                    'DuracionMinutos' => (int) ($servicio['DuracionMinutos'] ?? 60),
                    'CostoServicio' => number_format(
                        (float) ($servicio['CostoServicio'] ?? 0),
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
                trim($_POST['descripcion'] ?? '')
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

        if ($descripcion === '') {
            $errores['descripcion'] =
                'La descripción es obligatoria.';
        } elseif (strlen($descripcion) > 255) {
            $errores['descripcion'] =
                'La descripción no puede superar 255 caracteres.';
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

    /*
    =========================================
          ACTIVIDAD DE ESPECIALISTAS
    =========================================
    */

    public function actividadEspecialistas(): void
    {
        $clvCons = (string) $this->consultorio['ClvCons'];

        $fechaDesde = trim((string) ($_GET['desde'] ?? ''));
        $fechaHasta = trim((string) ($_GET['hasta'] ?? ''));
        $clvPsi = trim((string) ($_GET['psicologo'] ?? ''));
        $clvServ = trim((string) ($_GET['servicio'] ?? ''));
        $estado = strtoupper(trim((string) ($_GET['estado'] ?? '')));

        $estadosPermitidos = [
            'PROGRAMADA',
            'ASISTIDA',
            'CANCELADA',
            'INASISTENCIA',
        ];

        if ($estado !== '' && !in_array($estado, $estadosPermitidos, true)) {
            $estado = '';
        }

        if ($fechaDesde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) {
            $fechaDesde = '';
        }

        if ($fechaHasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) {
            $fechaHasta = '';
        }

        $psicologoModel = new Psicologo();
        $psicologos = $psicologoModel->obtenerActivosPorConsultorio($clvCons);

        if ($clvPsi !== '') {
            $valido = $psicologoModel->obtenerPorClave($clvPsi, $clvCons);
            if (!$valido) {
                $clvPsi = '';
            }
        }

        $servicioModel = new Servicio();
        $servicios = $servicioModel->listarPorConsultorio($clvCons);

        if ($clvServ !== '') {
            $servOk = false;
            foreach ($servicios as $s) {
                if (($s['ClvServ'] ?? '') === $clvServ) {
                    $servOk = true;
                    break;
                }
            }
            if (!$servOk) {
                $clvServ = '';
            }
        }

        $citaModel = new Cita();
        $filas = $citaModel->listarActividadEspecialistas(
            $clvCons,
            $fechaDesde !== '' ? $fechaDesde : null,
            $fechaHasta !== '' ? $fechaHasta : null,
            $clvPsi !== '' ? $clvPsi : null,
            $clvServ !== '' ? $clvServ : null,
            $estado !== '' ? $estado : null
        );

        $resumen = $citaModel->resumenActividadEspecialistas(
            $clvCons,
            $fechaDesde !== '' ? $fechaDesde : null,
            $fechaHasta !== '' ? $fechaHasta : null,
            $clvPsi !== '' ? $clvPsi : null,
            $clvServ !== '' ? $clvServ : null,
            $estado !== '' ? $estado : null
        );

        $serviciosActivos = 0;
        foreach ($servicios as $s) {
            if (strtoupper((string) ($s['EstatusServicio'] ?? '')) === 'ACTIVO') {
                $serviciosActivos++;
            }
        }

        $disponibilidadModel = new DisponibilidadPsicologo();
        $bloquesDisponibilidad = 0;
        foreach ($psicologos as $psi) {
            $bloques = $disponibilidadModel->obtenerPorPsicologo(
                (string) ($psi['ClvPsi'] ?? '')
            );
            foreach ($bloques as $bloque) {
                if (
                    strtoupper((string) ($bloque['EstatusDisponibilidad'] ?? ''))
                    === 'ACTIVA'
                ) {
                    $bloquesDisponibilidad++;
                }
            }
        }

        $this->view(
            'consultorio/actividad-especialistas',
            [
                'titulo' => 'Actividad de especialistas',
                'usuario' => $this->usuario,
                'consultorio' => $this->consultorio,
                'psicologos' => $psicologos,
                'servicios' => $servicios,
                'filas' => $filas,
                'resumen' => $resumen,
                'serviciosActivos' => $serviciosActivos,
                'bloquesDisponibilidad' => $bloquesDisponibilidad,
                'filtros' => [
                    'desde' => $fechaDesde,
                    'hasta' => $fechaHasta,
                    'psicologo' => $clvPsi,
                    'servicio' => $clvServ,
                    'estado' => $estado,
                ],
            ],
            'consultorio'
        );
    }

    /*
    =========================================
          INCIDENCIAS DE ACCESO (CONSULTORIO)
    =========================================
    */

    public function listarIncidenciasAcceso(): void
    {
        $servicio = new IncidenciaSoporteService();
        $clvCons = (string) ($this->consultorio['ClvCons'] ?? '');

        if (!$servicio->moduloDisponible()) {
            Session::setFlash(
                'error',
                'El módulo de incidencias aún no está disponible.'
            );
            Response::redirect('consultorio');
            return;
        }

        try {
            $incidencias = $servicio->listarParaConsultorio($clvCons);
        } catch (Throwable $e) {
            Session::setFlash('error', $e->getMessage());
            Response::redirect('consultorio');
            return;
        }

        $this->view(
            'consultorio/incidencias/index',
            [
                'titulo' => 'Incidencias de acceso',
                'usuario' => $this->usuario,
                'consultorio' => $this->consultorio,
                'incidencias' => $incidencias,
                'servicio' => $servicio,
                'success' => Session::getFlash('success'),
                'error' => Session::getFlash('error'),
            ],
            'consultorio'
        );
    }

    public function verIncidenciaAcceso(string $id): void
    {
        $servicio = new IncidenciaSoporteService();
        $clvCons = (string) ($this->consultorio['ClvCons'] ?? '');
        $idInc = (int) $id;

        if (!$servicio->moduloDisponible()) {
            Session::setFlash(
                'error',
                'El módulo de incidencias aún no está disponible.'
            );
            Response::redirect('consultorio');
            return;
        }

        try {
            $incidencia = $servicio->obtenerDetalleConsultorio(
                $idInc,
                $clvCons
            );
        } catch (Throwable $e) {
            Session::setFlash('error', $e->getMessage());
            Response::redirect('consultorio/incidencias');
            return;
        }

        $this->view(
            'consultorio/incidencias/ver',
            [
                'titulo' => 'Incidencia de acceso',
                'usuario' => $this->usuario,
                'consultorio' => $this->consultorio,
                'incidencia' => $incidencia,
                'servicio' => $servicio,
                'csrf' => Session::csrfToken(),
                'success' => Session::getFlash('success'),
                'error' => Session::getFlash('error'),
            ],
            'consultorio'
        );
    }

    public function actualizarIncidenciaAcceso(string $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('consultorio/incidencias');
            return;
        }

        $idInc = (int) $id;

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            Session::setFlash(
                'error',
                'La solicitud no es válida. Intenta nuevamente.'
            );
            Response::redirect('consultorio/incidencias/' . $idInc);
            return;
        }

        unset(
            $_POST['ClvCons'],
            $_POST['ClvUsuAtencion'],
            $_POST['ClvUsuSolicitante'],
            $_POST['RolDestino'],
            $_POST['RolUsu']
        );

        $servicio = new IncidenciaSoporteService();

        try {
            $resultado = $servicio->cambiarEstadoConsultorio(
                $idInc,
                (string) ($_POST['estado'] ?? ''),
                (string) ($_POST['observacion'] ?? ''),
                (string) ($this->usuario['ClvUsu'] ?? ''),
                (string) ($this->consultorio['ClvCons'] ?? '')
            );
            Session::setFlash('success', $resultado['mensaje']);
        } catch (Throwable $e) {
            Session::setFlash('error', $e->getMessage());
        }

        Response::redirect('consultorio/incidencias/' . $idInc);
    }

    public function escalarIncidenciaAcceso(string $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Response::redirect('consultorio/incidencias');
            return;
        }

        $idInc = (int) $id;

        if (!Session::validarCsrf($_POST['csrf_token'] ?? null)) {
            Session::setFlash(
                'error',
                'La solicitud no es válida. Intenta nuevamente.'
            );
            Response::redirect('consultorio/incidencias/' . $idInc);
            return;
        }

        unset(
            $_POST['ClvCons'],
            $_POST['ClvUsuAtencion'],
            $_POST['RolDestino']
        );

        $servicio = new IncidenciaSoporteService();

        try {
            $resultado = $servicio->escalarAAdministrador(
                $idInc,
                (string) ($_POST['descripcion_tecnica'] ?? ''),
                (string) ($this->usuario['ClvUsu'] ?? ''),
                (string) ($this->consultorio['ClvCons'] ?? '')
            );
            Session::setFlash('success', $resultado['mensaje']);
        } catch (Throwable $e) {
            Session::setFlash('error', $e->getMessage());
        }

        Response::redirect('consultorio/incidencias/' . $idInc);
    }
}