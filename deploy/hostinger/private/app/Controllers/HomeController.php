<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Helper;
use App\Models\Consultorio;
use App\Models\ConsultorioUsuario;
use App\Services\InstalacionConsultorioService;

class HomeController extends Controller
{
    /**
     * Portada pública del único consultorio de la instalación.
     */
    public function index(): void
    {
        $instalacion = new InstalacionConsultorioService();
        $estado = $instalacion->resolver();

        if ($estado['estado'] === InstalacionConsultorioService::ESTADO_MULTIPLE) {
            $this->responderInstalacionInvalida();
            return;
        }

        if ($estado['estado'] === InstalacionConsultorioService::ESTADO_NINGUNO) {
            $this->responderSitioEnConfiguracion();
            return;
        }

        $consultorio = $estado['consultorio'];
        $clvCons = strtoupper(trim((string) ($consultorio['ClvCons'] ?? '')));

        if ($clvCons === '') {
            $this->responderSitioEnConfiguracion();
            return;
        }

        $publicado = $this->consultorioPublicoDisponible($consultorio);

        if (!$publicado) {
            $this->responderSitioEnConfiguracion($consultorio);
            return;
        }

        $this->renderPaginaConsultorio($clvCons, false, '');
    }

    /**
     * Compatibilidad: /consultorios/{id} → / si coincide con el único; 404 si no.
     */
    public function mostrarConsultorio(string $consultorio): void
    {
        $clvCons = $this->normalizarClave($consultorio);

        if ($clvCons === '') {
            $this->responderNoDisponible(
                'Este consultorio todavía no se encuentra disponible públicamente.'
            );
            return;
        }

        $instalacion = new InstalacionConsultorioService();
        $estado = $instalacion->resolver();

        if ($estado['estado'] !== InstalacionConsultorioService::ESTADO_UNICO) {
            $this->responderNoDisponible(
                'Este consultorio todavía no se encuentra disponible públicamente.'
            );
            return;
        }

        $unica = strtoupper(trim((string) ($estado['consultorio']['ClvCons'] ?? '')));

        if ($unica === '' || $unica !== $clvCons) {
            $this->responderNoDisponible(
                'Este consultorio todavía no se encuentra disponible públicamente.'
            );
            return;
        }

        Response::redirect('', 301);
    }

    /**
     * Compatibilidad: redirige al perfil simplificado del único consultorio.
     */
    public function perfilEspecialista(
        string $consultorio,
        string $psicologo
    ): void {
        $clvCons = $this->normalizarClave($consultorio);
        $clvPsi = $this->normalizarClave($psicologo);

        if ($clvCons === '' || $clvPsi === '') {
            $this->responderNoDisponible(
                'Este especialista todavía no se encuentra disponible públicamente.'
            );
            return;
        }

        $instalacion = new InstalacionConsultorioService();

        if (!$instalacion->coincideConUnico($clvCons)) {
            $this->responderNoDisponible(
                'Este especialista todavía no se encuentra disponible públicamente.'
            );
            return;
        }

        Response::redirect(
            'especialistas/' . rawurlencode($clvPsi),
            301
        );
    }

    /**
     * Perfil público del especialista del único consultorio.
     * No acepta ClvCons desde la URL.
     */
    public function perfilEspecialistaCorto(string $psicologo): void
    {
        $clvPsi = $this->normalizarClave($psicologo);

        if ($clvPsi === '') {
            $this->responderNoDisponible(
                'Este especialista todavía no se encuentra disponible públicamente.'
            );
            return;
        }

        $instalacion = new InstalacionConsultorioService();
        $estado = $instalacion->resolver();

        if ($estado['estado'] === InstalacionConsultorioService::ESTADO_MULTIPLE) {
            $this->responderInstalacionInvalida();
            return;
        }

        if ($estado['estado'] !== InstalacionConsultorioService::ESTADO_UNICO) {
            $this->responderNoDisponible(
                'Este especialista todavía no se encuentra disponible públicamente.'
            );
            return;
        }

        $clvCons = strtoupper(trim((string) ($estado['consultorio']['ClvCons'] ?? '')));
        $consultorioModel = new Consultorio();

        $perfil = $consultorioModel->obtenerEspecialistaPublico(
            $clvPsi,
            $clvCons,
            true
        );

        $modoVistaPrevia = false;
        $rutaPaginaPublica = '';

        if (!$perfil) {
            $accesoPrevio = $this->resolverAccesoVistaPreviaPerfil(
                $clvPsi,
                $clvCons
            );

            if ($accesoPrevio !== null) {
                $perfil = $accesoPrevio['perfil'];
                $modoVistaPrevia = true;
                $rutaPaginaPublica = $accesoPrevio['rutaPaginaPublica'];
            }
        }

        if (!$perfil) {
            $this->responderNoDisponible(
                'Este especialista todavía no se encuentra disponible públicamente.'
            );
            return;
        }

        if (
            strtoupper((string) ($perfil['ClvCons'] ?? ''))
            !== $clvCons
        ) {
            $this->responderNoDisponible(
                'Este especialista todavía no se encuentra disponible públicamente.'
            );
            return;
        }

        $servicios = $consultorioModel->obtenerServiciosPublicosPsicologo(
            (string) $perfil['ClvPsi']
        );

        $consultorioNav = $modoVistaPrevia
            ? $consultorioModel->obtenerParaVistaPrevia($clvCons)
            : $consultorioModel->obtenerPublicadoPorClave($clvCons);

        if (!$consultorioNav) {
            $consultorioNav = [
                'ClvCons' => $clvCons,
                'NombreCons' => $estado['consultorio']['NombreCons'] ?? '',
                'LogotipoCons' => $estado['consultorio']['LogotipoCons'] ?? ''
            ];
        }

        $this->view(
            'home/perfil-especialista',
            [
                'titulo' => trim(
                    (string) ($perfil['NombreCompleto'] ?? 'Especialista')
                ),
                'consultorio' => $consultorioNav,
                'especialista' => $perfil,
                'servicios' => $servicios,
                'modoVistaPrevia' => $modoVistaPrevia,
                'rutaPaginaPublica' => $rutaPaginaPublica,
                'forzarInicioPublico' => false,
                'bannerVistaPrevia' => $modoVistaPrevia
                    ? 'Vista previa privada. Esta página todavía no es visible al público.'
                    : '',
                'cargarMapaHome' => false
            ]
        );
    }

    public function agendarCita(): void
    {
        $clvPsi = $this->normalizarClave($_GET['psicologo'] ?? '');
        $clvServ = $this->normalizarClave($_GET['servicio'] ?? '');
        // ClvCons de GET se ignora deliberadamente.
        unset($_GET['consultorio'], $_GET['ClvCons']);

        if ($clvPsi !== '') {
            $intencion = $this->construirIntencionAgendar(
                $clvPsi,
                $clvServ
            );

            if ($intencion === null) {
                Session::remove('intencion_agendar');
                Session::setFlash(
                    'error',
                    'El especialista seleccionado no está disponible para agendar.'
                );
                Response::redirect('');
            }

            Session::set('intencion_agendar', $intencion);
            $clvPsi = (string) $intencion['psicologo'];
            $clvServ = (string) ($intencion['servicio'] ?? '');
        }

        if (!Session::has('usuario')) {
            Response::redirect('login');
        }

        $usuario = Session::get('usuario');

        if (!is_array($usuario)) {
            Response::redirect('login');
        }

        $rol = strtoupper(
            trim((string) ($usuario['RolUsu'] ?? ''))
        );

        switch ($rol) {
            case 'PACIENTE':
                $intencion = $this->consumirIntencionAgendarValida();

                $destino = 'paciente/agendar';

                if ($intencion !== null) {
                    $query = [
                        'psicologo' => $intencion['psicologo']
                    ];

                    if ($intencion['servicio'] !== '') {
                        $query['servicio'] = $intencion['servicio'];
                    }

                    $destino .= '?' . http_build_query($query);
                } elseif ($clvPsi !== '') {
                    // Intención ya consumida o inválida: no preseleccionar basura.
                    Session::remove('intencion_agendar');
                }

                Response::redirect($destino);

            case 'PSICOLOGO':
                Session::remove('intencion_agendar');
                Response::redirect('psicologo');

            case 'CONSULTORIO':
                Session::remove('intencion_agendar');
                Response::redirect('consultorio');

            case 'ADMINISTRADOR':
                Session::remove('intencion_agendar');
                Response::redirect('administrador');

            default:
                Session::remove('intencion_agendar');
                Response::redirect('login');
        }
    }

    /**
     * Usado por AuthController tras login de paciente.
     *
     * @return array{psicologo: string, servicio: string, consultorio: string}|null
     */
    public static function consumirIntencionAgendarDesdeSesion(): ?array
    {
        $controller = new self();

        return $controller->consumirIntencionAgendarValida();
    }

    /**
     * Render compartido: página pública o datos de vista previa ya resueltos.
     *
     * @param array<string, mixed>|null $datosPrevios
     */
    public function renderPaginaConsultorio(
        string $clvCons,
        bool $modoVistaPrevia,
        string $rutaBase,
        ?array $datosPrevios = null
    ): void {
        $consultorioModel = new Consultorio();

        if ($datosPrevios !== null) {
            $datos = $datosPrevios;
            $consultorio = $datos['consultorio'] ?? null;
        } else {
            $consultorio = $modoVistaPrevia
                ? $consultorioModel->obtenerParaVistaPrevia($clvCons)
                : $consultorioModel->obtenerPublicadoPorClave($clvCons);

            if (!$consultorio) {
                $this->responderNoDisponible(
                    'Este consultorio todavía no se encuentra disponible públicamente.'
                );
                return;
            }

            $busqueda = $this->normalizarBusqueda($_GET['busqueda'] ?? '');
            $especialidad = $this->normalizarEspecialidad(
                $_GET['especialidad'] ?? ''
            );

            $especialidadesFiltro =
                $consultorioModel->listarEspecialidadesPublicas(
                    $clvCons,
                    !$modoVistaPrevia
                );

            if (
                $especialidad !== ''
                && !in_array($especialidad, $especialidadesFiltro, true)
            ) {
                $especialidad = '';
            }

            $filtrosActivos =
                $busqueda !== '' || $especialidad !== '';

            $especialistas =
                $consultorioModel->buscarEspecialistasPublicos(
                    $clvCons,
                    $busqueda,
                    $especialidad,
                    !$modoVistaPrevia
                );

            foreach ($especialistas as &$especialista) {
                $especialista['servicios'] =
                    $consultorioModel->obtenerServiciosPublicosPsicologo(
                        (string) $especialista['ClvPsi']
                    );
            }
            unset($especialista);

            $horarios = $consultorioModel->obtenerHorariosPorClave($clvCons);
            $servicios = $consultorioModel->obtenerServiciosPorClave($clvCons);

            $mapaDisponible = Helper::coordenadasPublicasValidas(
                $consultorio['LatitudDir'] ?? null,
                $consultorio['LongitudDir'] ?? null
            );

            $diasAtencion = 0;

            foreach ($horarios as $dia) {
                if (($dia['EstatusHorario'] ?? '') === 'ACTIVO') {
                    $diasAtencion++;
                }
            }

            $datos = [
                'consultorio' => $consultorio,
                'servicios' => $servicios,
                'horarios' => $horarios,
                'redes' => $consultorioModel->obtenerRedesPorClave($clvCons),
                'caracteristicas' =>
                    $consultorioModel->obtenerCaracteristicasPorClave($clvCons),
                'especialistas' => $especialistas,
                'especialidadesFiltro' => $especialidadesFiltro,
                'busquedaEspecialistas' => $busqueda,
                'filtroEspecialidad' => $especialidad,
                'filtrosActivos' => $filtrosActivos,
                'totalEspecialistas' => count($especialistas),
                'mapaDisponible' => $mapaDisponible,
                'diasAtencion' => $diasAtencion,
                'cargarMapaHome' => $mapaDisponible,
                'modoVistaPrevia' => $modoVistaPrevia
            ];
        }

        $datos['titulo'] = (string) (
            ($datos['consultorio']['NombreCons'] ?? null) ?: 'Consultorio'
        );
        $datos['rutaBusquedaEspecialistas'] = Helper::baseUrl($rutaBase);
        $datos['rutaPaginaPublica'] = $rutaBase;
        $datos['clvConsPublico'] = $clvCons;
        $datos['esPortadaPlataforma'] = false;
        $datos['forzarInicioPublico'] = false;

        $this->view('home/index', $datos);
    }

    /**
     * @return array{perfil: array<string, mixed>, rutaPaginaPublica: string}|null
     */
    private function resolverAccesoVistaPreviaPerfil(
        string $clvPsi,
        ?string $clvConsEsperado
    ): ?array {
        if (!Session::has('usuario')) {
            return null;
        }

        $usuario = Session::get('usuario');

        if (!is_array($usuario)) {
            return null;
        }

        $rol = strtoupper(trim((string) ($usuario['RolUsu'] ?? '')));
        $model = new Consultorio();

        if ($rol === 'CONSULTORIO') {
            $vinculo = (new ConsultorioUsuario())->buscarPorUsuario(
                (string) ($usuario['ClvUsu'] ?? '')
            );

            if (!$vinculo) {
                return null;
            }

            $clvCons = (string) $vinculo['ClvCons'];

            if (
                $clvConsEsperado !== null
                && $clvConsEsperado !== ''
                && $clvCons !== $clvConsEsperado
            ) {
                return null;
            }

            $perfil = $model->obtenerEspecialistaPublico(
                $clvPsi,
                $clvCons,
                false
            );

            if (!$perfil) {
                return null;
            }

            return [
                'perfil' => $perfil,
                'rutaPaginaPublica' => 'consultorio/vista-previa'
            ];
        }

        if ($rol === 'ADMINISTRADOR') {
            $perfil = $model->obtenerEspecialistaPublico(
                $clvPsi,
                $clvConsEsperado,
                false
            );

            if (!$perfil) {
                return null;
            }

            $clvConsPerfil = (string) ($perfil['ClvCons'] ?? '');

            if (
                $clvConsEsperado !== null
                && $clvConsEsperado !== ''
                && $clvConsPerfil !== $clvConsEsperado
            ) {
                return null;
            }

            return [
                'perfil' => $perfil,
                'rutaPaginaPublica' =>
                    'administrador/consultorios/vista-previa/'
                    . rawurlencode($clvConsPerfil)
            ];
        }

        return null;
    }

    /**
     * @return array{psicologo: string, servicio: string, consultorio: string, creado_en: int}|null
     */
    private function construirIntencionAgendar(
        string $clvPsi,
        string $clvServ
    ): ?array {
        $instalacion = new InstalacionConsultorioService();
        $claveUnica = $instalacion->claveUnicaONull();

        if ($claveUnica === null) {
            return null;
        }

        $psicologo = (new Consultorio())->obtenerEspecialistaPublico(
            $clvPsi,
            $claveUnica,
            true
        );

        if (!$psicologo) {
            return null;
        }

        $clvCons = strtoupper(trim((string) ($psicologo['ClvCons'] ?? '')));

        if ($clvCons === '' || $clvCons !== $claveUnica) {
            return null;
        }

        if ($clvServ !== '') {
            $servicios = (new Consultorio())
                ->obtenerServiciosPublicosPsicologo($clvPsi);

            $encontrado = false;

            foreach ($servicios as $servicio) {
                if (
                    strtoupper((string) ($servicio['ClvServ'] ?? ''))
                    === $clvServ
                ) {
                    $encontrado = true;
                    break;
                }
            }

            if (!$encontrado) {
                $clvServ = '';
            }
        }

        return [
            'psicologo' => $clvPsi,
            'servicio' => $clvServ,
            'consultorio' => $clvCons,
            'creado_en' => time()
        ];
    }

    /**
     * @return array{psicologo: string, servicio: string, consultorio: string}|null
     */
    private function consumirIntencionAgendarValida(): ?array
    {
        $intencion = Session::get('intencion_agendar');
        Session::remove('intencion_agendar');

        if (!is_array($intencion)) {
            return null;
        }

        $clvPsi = $this->normalizarClave($intencion['psicologo'] ?? '');
        $clvServ = $this->normalizarClave($intencion['servicio'] ?? '');
        $clvConsGuardado = $this->normalizarClave(
            $intencion['consultorio'] ?? ''
        );
        $creadoEn = (int) ($intencion['creado_en'] ?? 0);

        if ($clvPsi === '' || $clvConsGuardado === '') {
            return null;
        }

        // Intenciones demasiado antiguas (2 horas) se descartan.
        if ($creadoEn > 0 && (time() - $creadoEn) > 7200) {
            return null;
        }

        $psicologo = (new Consultorio())->obtenerEspecialistaPublico(
            $clvPsi,
            $clvConsGuardado,
            true
        );

        if (!$psicologo) {
            return null;
        }

        if (
            strtoupper((string) ($psicologo['ClvCons'] ?? ''))
            !== $clvConsGuardado
        ) {
            return null;
        }

        if ($clvServ !== '') {
            $servicios = (new Consultorio())
                ->obtenerServiciosPublicosPsicologo($clvPsi);

            $okServicio = false;

            foreach ($servicios as $servicio) {
                if (
                    strtoupper((string) ($servicio['ClvServ'] ?? ''))
                    === $clvServ
                ) {
                    $okServicio = true;
                    break;
                }
            }

            if (!$okServicio) {
                $clvServ = '';
            }
        }

        return [
            'psicologo' => $clvPsi,
            'servicio' => $clvServ,
            'consultorio' => $clvConsGuardado
        ];
    }

    private function responderNoDisponible(string $mensaje): void
    {
        http_response_code(404);
        $this->view(
            'home/no-disponible',
            [
                'titulo' => 'No disponible',
                'mensaje' => $mensaje,
                'consultorio' => null,
                'esPortadaPlataforma' => false,
                'esNavbarGlobal' => true,
                'modoVistaPrevia' => false
            ]
        );
    }

    private function responderSitioEnConfiguracion(?array $consultorio = null): void
    {
        $esAdmin = $this->usuarioEsAdministrador();

        $this->view(
            'home/sitio-en-configuracion',
            [
                'titulo' => 'Sitio en configuración',
                'consultorio' => $consultorio,
                'esAdministrador' => $esAdmin,
                'esPortadaPlataforma' => false,
                'esNavbarGlobal' => true,
                'modoVistaPrevia' => false,
                'cargarMapaHome' => false
            ]
        );
    }

    private function responderInstalacionInvalida(): void
    {
        http_response_code(503);
        $esAdmin = $this->usuarioEsAdministrador();

        $this->view(
            'home/instalacion-invalida',
            [
                'titulo' => 'Configuración pendiente',
                'esAdministrador' => $esAdmin,
                'consultorio' => null,
                'esPortadaPlataforma' => false,
                'esNavbarGlobal' => true,
                'modoVistaPrevia' => false,
                'cargarMapaHome' => false
            ]
        );
    }

    /**
     * PublicadoCons = 1 y cuenta activa (mismo criterio público del modelo).
     */
    private function consultorioPublicoDisponible(array $consultorio): bool
    {
        $clvCons = strtoupper(trim((string) ($consultorio['ClvCons'] ?? '')));

        if ($clvCons === '') {
            return false;
        }

        return (new Consultorio())->obtenerPublicadoPorClave($clvCons) !== null;
    }

    private function usuarioEsAdministrador(): bool
    {
        if (!Session::has('usuario')) {
            return false;
        }

        $usuario = Session::get('usuario');

        if (!is_array($usuario)) {
            return false;
        }

        return strtoupper(trim((string) ($usuario['RolUsu'] ?? '')))
            === 'ADMINISTRADOR';
    }

    private function normalizarBusqueda(mixed $valor): string
    {
        $texto = trim((string) $valor);
        $texto = preg_replace('/\s+/u', ' ', $texto) ?? '';

        if (mb_strlen($texto) > 80) {
            $texto = mb_substr($texto, 0, 80);
        }

        return $texto;
    }

    private function normalizarEspecialidad(mixed $valor): string
    {
        $texto = trim((string) $valor);

        if (mb_strlen($texto) > 100) {
            $texto = mb_substr($texto, 0, 100);
        }

        return $texto;
    }

    private function normalizarClave(mixed $valor): string
    {
        $texto = strtoupper(trim((string) $valor));

        if ($texto === '' || !preg_match('/^[A-Z0-9]{1,10}$/', $texto)) {
            return '';
        }

        return $texto;
    }
}
